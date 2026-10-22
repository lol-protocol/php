#!/usr/bin/env python3
"""Valida los sets de iconos. Código de salida 1 si algo falla.

    python3 iconos-tools/validate.py

Reglas: SVG bien formado de 64x64 sin texto, tres variantes con los mismos archivos, nombres
<prefijo>_<descripcion> dentro de la carpeta del mismo prefijo, gris sin colores cromáticos, líneas sin
rellenos, ids únicos, cada url(#id) apuntando a un id definido y todo id usado, todo el dibujo dentro del
lienzo (a más de 1 unidad del borde) y ningún icono repetido.
"""
import collections
import hashlib
import math
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
sys.path.insert(0, str(HERE))

from catalogo import GROUPS, PREFIXES, VARIANTS  # noqa: E402

MARGIN = 1  # unidades que el dibujo debe quedar lejos del borde del lienzo de 64 (los trazos tienen grosor)
NAME = re.compile(r'^[a-z]+(_[a-z0-9]+)+$')
HEX = re.compile(r'(?<=["\':\s])#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})(?![0-9A-Za-z_])')
failures = []


def fail(message):
    failures.append(message)


def local(tag):
    return tag.split('}')[-1]


def signature(root):
    """(solidos, marcas): lo que debe coincidir entre variantes. La sombra solo existe en color y gris, y un
    enlace o una marca de la capa back pueden quedar ocultos del todo en lineas por quedar tapados por un volumen."""
    groups = {g.get('class'): g for g in root if local(g.tag) == 'g'}
    solids = groups.get('solids')
    marks = groups.get('marks')
    return (len(solids) if solids is not None else 0, len(marks) if marks is not None else 0)


# ------------------------------------------------------------------ geometría del dibujo
PATH_TOKEN = re.compile(r'([MmLlHhVvAaQqTtCcSsZz])|(-?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?)')
PATH_ARGS = {'M': 2, 'L': 2, 'H': 1, 'V': 1, 'A': 7, 'Q': 4, 'T': 2, 'C': 6, 'S': 4, 'Z': 0}


def arc_extremes(x1, y1, rx, ry, phi, large, sweep, x2, y2):
    """Puntos más a la izquierda, derecha, arriba y abajo de un arco SVG que caen dentro del propio arco."""
    rx, ry = abs(rx), abs(ry)
    if not rx or not ry or (x1, y1) == (x2, y2):
        return []
    rot = math.radians(phi)
    c, s = math.cos(rot), math.sin(rot)
    dx, dy = (x1 - x2) / 2, (y1 - y2) / 2
    px, py = c * dx + s * dy, -s * dx + c * dy
    scale = px * px / (rx * rx) + py * py / (ry * ry)
    if scale > 1:  # radios demasiado pequeños para unir los extremos: el navegador los agranda
        rx, ry = rx * math.sqrt(scale), ry * math.sqrt(scale)
    num = rx * rx * ry * ry - rx * rx * py * py - ry * ry * px * px
    den = rx * rx * py * py + ry * ry * px * px
    coef = math.sqrt(max(0.0, num / den)) * (1 if large != sweep else -1)
    cxp, cyp = coef * rx * py / ry, -coef * ry * px / rx
    cx, cy = c * cxp - s * cyp + (x1 + x2) / 2, s * cxp + c * cyp + (y1 + y2) / 2
    t1 = math.atan2((py - cyp) / ry, (px - cxp) / rx)
    span = math.atan2((-py - cyp) / ry, (-px - cxp) / rx) - t1
    if sweep and span < 0:
        span += 2 * math.pi
    elif not sweep and span > 0:
        span -= 2 * math.pi
    out = []
    for base in (math.atan2(-ry * s, rx * c), math.atan2(ry * c, rx * s)):  # donde dx/dt y dy/dt se anulan
        for t in (base, base + math.pi):
            walked = (t - t1) % (2 * math.pi) if span > 0 else -((t1 - t) % (2 * math.pi))
            if abs(walked) <= abs(span):
                out.append((cx + rx * math.cos(t) * c - ry * math.sin(t) * s, cy + rx * math.cos(t) * s + ry * math.sin(t) * c))
    return out


def path_bbox(d):
    """(xmin, ymin, xmax, ymax) de un atributo d, o None si no dibuja nada. Cuenta los puntos de control de las curvas."""
    pts, nums, cmd = [], [], None
    x = y = start_x = start_y = 0.0
    for letter, number in PATH_TOKEN.findall(d):
        if letter:
            cmd, nums = letter, []
            if letter in 'Zz':
                x, y = start_x, start_y
            continue
        nums.append(float(number))
        n = PATH_ARGS[cmd.upper()]
        if len(nums) < n:
            continue
        up = cmd.upper()
        ox, oy = (x, y) if cmd.islower() else (0.0, 0.0)
        if up == 'H':
            nx, ny = nums[0] + ox, y
        elif up == 'V':
            nx, ny = x, nums[0] + oy
        elif up == 'A':
            nx, ny = nums[5] + ox, nums[6] + oy
            pts += arc_extremes(x, y, nums[0], nums[1], nums[2], int(nums[3]), int(nums[4]), nx, ny)
        else:
            pts += [(nums[i] + ox, nums[i + 1] + oy) for i in range(0, n - 2, 2)]
            nx, ny = nums[n - 2] + ox, nums[n - 1] + oy
        x, y = nx, ny
        pts.append((x, y))
        if up == 'M':
            start_x, start_y = x, y
            cmd = 'l' if cmd.islower() else 'L'
        nums = []
    if not pts:
        return None
    xs, ys = [p[0] for p in pts], [p[1] for p in pts]
    return min(xs), min(ys), max(xs), max(ys)


def number(e, name):
    return float(e.get(name, 0))


def outside_canvas(root):
    """Primer elemento cuyo dibujo queda a menos de MARGIN del borde del lienzo, como (etiqueta, caja); None si no hay."""
    low, high = MARGIN, 64 - MARGIN
    for e in root.iter():
        tag = local(e.tag)
        if tag == 'path' and e.get('d'):
            box = path_bbox(e.get('d'))
        elif tag in ('circle', 'ellipse'):
            rx, ry = (number(e, 'r'), number(e, 'r')) if tag == 'circle' else (number(e, 'rx'), number(e, 'ry'))
            box = (number(e, 'cx') - rx, number(e, 'cy') - ry, number(e, 'cx') + rx, number(e, 'cy') + ry)
        elif tag == 'rect' and not e.get('transform'):  # uno girado se sale de su caja sin estar fuera
            box = (number(e, 'x'), number(e, 'y'), number(e, 'x') + number(e, 'width'), number(e, 'y') + number(e, 'height'))
        else:
            continue
        if box and (min(box[:2]) < low or max(box[2:]) > high):
            return tag, tuple(round(v, 1) for v in box)
    return None


def check_file(path, rel, variant, text):
    try:
        root = ET.fromstring(text)
    except ET.ParseError as e:
        fail(f'{rel}: XML mal formado ({e})')
        return None
    if root.get('viewBox') != '0 0 64 64' or root.get('width') != '64' or root.get('height') != '64':
        fail(f'{rel}: debe ser 64x64 con viewBox="0 0 64 64"')
    if any(local(e.tag) in ('text', 'tspan') or 'font-family' in e.attrib or 'font-size' in e.attrib for e in root.iter()):
        fail(f'{rel}: contiene texto')
    ids = [e.get('id') for e in root.iter() if e.get('id')]
    if len(ids) != len(set(ids)):
        fail(f'{rel}: ids repetidos')
    used = set(re.findall(r'url\(#([^)]+)\)', text))
    for name in sorted(used - set(ids)):
        fail(f'{rel}: url(#{name}) apunta a un id que no existe')
    for name in sorted(set(ids) - used):
        fail(f'{rel}: el id {name} no lo usa nada')
    found = outside_canvas(root)
    if found:
        fail(f'{rel}: {found[0]} fuera del lienzo (a menos de {MARGIN} del borde): {found[1]}')
    if 'ns0:' in text:
        fail(f'{rel}: prefijo de namespace ns0:')
    if variant == 'gris':
        for m in HEX.finditer(text):
            h = m.group(1)
            h = ''.join(c * 2 for c in h) if len(h) == 3 else h
            rgb = [int(h[i:i + 2], 16) for i in (0, 2, 4)]
            if max(rgb) - min(rgb) > 6:
                fail(f'{rel}: color no gris #{h}')
                break
    if variant == 'lineas':
        if 'Gradient' in text or re.search(r'fill="(?!none")', text):
            fail(f'{rel}: la variante lineas no debe tener rellenos')
    return root


def main():
    for prefix in sorted({p for prefixes in PREFIXES.values() for p in prefixes} - set(GROUPS)):
        fail(f'catalogo.GROUPS: falta la etiqueta del prefijo {prefix}')
    for folder, prefixes in PREFIXES.items():
        base = ROOT / folder
        if not base.is_dir():
            fail(f'{folder}: no existe')
            continue
        extra = sorted(p.name for p in base.iterdir() if p.name not in VARIANTS and p.name != 'README.md')
        if extra:
            fail(f'{folder}: elementos fuera de las variantes: {extra}')
        files = {v: {p.relative_to(base / v).as_posix() for p in (base / v).rglob('*') if p.is_file()} for v in VARIANTS}
        if not (files['color'] == files['lineas'] == files['gris']):
            fail(f'{folder}: las tres variantes no tienen los mismos archivos')
        seen_ids, seen_shapes = {}, {}
        for rel in sorted(files['color']):
            sub, _, name = rel.partition('/')
            stem = name[:-4]
            if not name.endswith('.svg'):
                fail(f'{folder}/{rel}: no es .svg')
                continue
            toks = stem.split('_')
            if not NAME.match(stem) or toks[0] not in prefixes:
                fail(f'{folder}/{rel}: nombre fuera de convención')
            if sub != toks[0]:
                fail(f'{folder}/{rel}: la carpeta debe coincidir con el prefijo')
            if any(a == b and not a.isdigit() for a, b in zip(toks, toks[1:])):
                fail(f'{folder}/{rel}: token repetido en el nombre')
            roots = {}
            for v in VARIANTS:
                text = (base / v / rel).read_text(encoding='utf-8')
                roots[v] = check_file(base / v / rel, f'{folder}/{v}/{rel}', v, text)
                if v == 'gris':
                    digest = hashlib.md5(re.sub(r'grad_[a-z0-9_]+', 'g', text).encode()).hexdigest()
                    if digest in seen_shapes:
                        fail(f'{folder}/{rel}: idéntico a {seen_shapes[digest]}')
                    seen_shapes[digest] = rel
            if all(r is not None for r in roots.values()):
                counts = {v: signature(r) for v, r in roots.items()}
                if len(set(counts.values())) != 1:
                    fail(f'{folder}/{rel}: distinto número de sólidos y marcas entre variantes {counts}')
                for v, root in roots.items():
                    for e in root.iter():
                        if e.get('id'):
                            if e.get('id') in seen_ids:
                                fail(f'{folder}/{v}/{rel}: id {e.get("id")} repetido en {seen_ids[e.get("id")]}')
                            seen_ids[e.get('id')] = f'{v}/{rel}'
        by_prefix = collections.Counter(Path(r).name.split('_')[0] for r in files['color'])
        print(f'{folder}: {len(files["color"])} iconos x {len(VARIANTS)} variantes {dict(sorted(by_prefix.items()))}')
    if failures:
        print(f'\n{len(failures)} fallos:')
        for f in failures[:60]:
            print('  -', f)
        sys.exit(1)
    print('TODO OK')


if __name__ == '__main__':
    main()
