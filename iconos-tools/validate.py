#!/usr/bin/env python3
"""Valida los sets de iconos. Código de salida 1 si algo falla.

    python3 iconos-tools/validate.py

Reglas: SVG bien formado de 64x64 sin texto, tres variantes con los mismos archivos, nombres
<prefijo>_<descripcion> dentro de la carpeta del mismo prefijo, gris sin colores cromáticos, líneas sin
rellenos, ids únicos y ningún icono repetido.
"""
import collections
import hashlib
import re
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
VARIANTS = ('color', 'lineas', 'gris')
SETS = {
    'iconos-genealogia': ('person', 'relationship', 'state', 'view', 'action', 'ui'),
    'iconos-biblioteca': ('book', 'genre', 'format', 'status', 'action', 'attr', 'ui'),
}
NAME = re.compile(r'^[a-z]+(_[a-z0-9]+)+$')
HEX = re.compile(r'(?<=["\':\s])#([0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})(?![0-9A-Za-z_])')
failures = []


def fail(message):
    failures.append(message)


def local(tag):
    return tag.split('}')[-1]


def signature(root):
    """(solidos, marcas): lo que debe coincidir entre variantes. La sombra solo existe en color y gris, y un
    enlace puede quedar oculto del todo en lineas por quedar tapado por los nodos."""
    groups = {g.get('class'): g for g in root if local(g.tag) == 'g'}
    solids = groups.get('solids')
    marks = groups.get('marks')
    return (len(solids) if solids is not None else 0, len(marks) if marks is not None else 0)


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
    for folder, prefixes in SETS.items():
        base = ROOT / folder
        if not base.is_dir():
            fail(f'{folder}: no existe')
            continue
        extra = sorted(p.name for p in base.iterdir() if p.name not in VARIANTS)
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
                for e in roots['color'].iter():
                    if e.get('id'):
                        if e.get('id') in seen_ids:
                            fail(f'{folder}/{rel}: id {e.get("id")} repetido en {seen_ids[e.get("id")]}')
                        seen_ids[e.get('id')] = rel
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
