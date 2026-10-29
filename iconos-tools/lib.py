#!/usr/bin/env python3
"""Primitivas para iconos abstractos 2.5D, sin texto.

Un icono se define una sola vez (solidos con extrusion, enlaces y marcas) y se
renderiza en tres variantes: color, lineas y gris.
"""
import math
import re

from catalogo import SCOPE_CLASS, VARIANTS

LIGHT = (0.6, 0.8)  # direccion de la extrusion: abajo a la derecha
INSET = 0.3  # cuanto se mete el borde trasero de la extrusion bajo la cara frontal (ver exact_sweep)
LINE_MIN, LINE_MAX = 1.8, 2.2  # grosor en lineas: el contorno de un volumen es LINE_MIN; marcas y enlaces, entre los dos
BAR = 2.8  # un enlace mas grueso es una barra a proposito (relationship_blood_bond, action_connect): no se normaliza
GRAY_GAP = 20  # minimo de gris (de 255) entre dos volumenes de tonos distintos que se tocan, en la variante gris

BASE = {
    'mist': '#CBD5E1', 'slate': '#94A3B8', 'ink': '#475569', 'night': '#1E293B',
    'indigo': '#4F46E5', 'blue': '#2563EB', 'sky': '#0284C7', 'teal': '#0D9488',
    'green': '#16A34A', 'lime': '#65A30D', 'amber': '#D97706', 'orange': '#EA580C',
    'red': '#DC2626', 'rose': '#E11D48', 'pink': '#DB2777', 'violet': '#7C3AED',
    'brown': '#92613A', 'paper': '#FEF3C7',
}
SHADOW = '#0F172A'


# ---------------------------------------------------------------- numeros y color
def fmt(v):
    s = f'{v:.2f}'.rstrip('0').rstrip('.')
    return '0' if s in ('-0', '') else s


def pt(p):
    return f'{fmt(p[0])} {fmt(p[1])}'


def _rgb(h):
    h = h.lstrip('#')
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def _hex(rgb):
    return '#%02X%02X%02X' % tuple(max(0, min(255, round(v))) for v in rgb)


def mix(a, b, t):
    return _hex(tuple(x + (y - x) * t for x, y in zip(_rgb(a), _rgb(b))))


def gray_level(c):
    r, g, b = _rgb(c)
    return round(0.299 * r + 0.587 * g + 0.114 * b)


def gray(c, shift=0):
    y = max(0, min(255, gray_level(c) + shift))
    return '#%02X%02X%02X' % (y, y, y)


def tones(hue):
    base = BASE[hue]
    return mix(base, '#FFFFFF', 0.32), base, mix(base, '#000000', 0.42)


def theme_style(hues, indent=''):
    """Líneas del <style> que deja cambiar el color de cada tono de `lineas` con --ic-<tono>; vacío si no hay tonos."""
    if not hues:
        return []
    # dentro de @supports: un renderizador sin variables CSS (resvg...) se salta el bloque entero y deja los
    # colores del atributo stroke; en un navegador la pagina puede sobrescribir --ic-<tono>. Las reglas van
    # acotadas a la clase de la raiz: un <style> en linea vale para todo el documento
    rules = [f'    .{SCOPE_CLASS} .ic-{h}{{stroke:var(--ic-{h},{tones(h)[2]})}}' for h in hues]
    return [f'{indent}{line}' for line in ['<style>', '  @supports (--a:0){', *rules, '  }', '</style>']]


# ---------------------------------------------------------------- geometria
def _unit(v):
    n = math.hypot(*v) or 1.0
    return (v[0] / n, v[1] / n)


def hull(points):
    pts = sorted({(round(x, 3), round(y, 3)) for x, y in points})
    if len(pts) <= 2:
        return pts

    def cross(o, a, b):
        return (a[0] - o[0]) * (b[1] - o[1]) - (a[1] - o[1]) * (b[0] - o[0])

    lower, upper = [], []
    for p in pts:
        while len(lower) >= 2 and cross(lower[-2], lower[-1], p) <= 0:
            lower.pop()
        lower.append(p)
    for p in reversed(pts):
        while len(upper) >= 2 and cross(upper[-2], upper[-1], p) <= 0:
            upper.pop()
        upper.append(p)
    return lower[:-1] + upper[:-1]


def poly_path(points):
    return 'M' + 'L'.join(pt(p) for p in points) + 'Z'


def circle_path(x, y, r):
    return f'M{fmt(x - r)} {fmt(y)}a{fmt(r)} {fmt(r)} 0 1 0 {fmt(2 * r)} 0a{fmt(r)} {fmt(r)} 0 1 0 {fmt(-2 * r)} 0Z'


def arc_path(x, y, r, a0, a1):
    p0 = (x + r * math.cos(math.radians(a0)), y + r * math.sin(math.radians(a0)))
    p1 = (x + r * math.cos(math.radians(a1)), y + r * math.sin(math.radians(a1)))
    large = 1 if (a1 - a0) % 360 > 180 else 0
    return f'M{pt(p0)}A{fmt(r)} {fmt(r)} 0 {large} 1 {pt(p1)}'


# ---------------------------------------------------------------- formas
def in_convex(poly, p):
    pos = neg = False
    for i in range(len(poly)):
        a, b = poly[i], poly[(i + 1) % len(poly)]
        c = (b[0] - a[0]) * (p[1] - a[1]) - (b[1] - a[1]) * (p[0] - a[0])
        pos = pos or c > 1e-9
        neg = neg or c < -1e-9
        if pos and neg:
            return False
    return True


def _arc_ends(c, r, a0, a1):
    return ((c[0] + r * math.cos(a0), c[1] + r * math.sin(a0)),
            (c[0] + r * math.cos(a1), c[1] + r * math.sin(a1)))


def _ends(el):
    return (el[1], el[2]) if el[0] == 'L' else _arc_ends(*el[1:])


def _split_arc(c, r, a0, a1, phi):
    """Parte un arco donde su normal es perpendicular a la extrusión: ahí la pared cambia de cara."""
    ts = [0.0, 1.0]
    for target in (phi + math.pi / 2, phi - math.pi / 2):
        for k in range(-3, 4):
            t = (target + 2 * math.pi * k - a0) / (a1 - a0)
            if 1e-9 < t < 1 - 1e-9:
                ts.append(t)
    ts = sorted(set(round(t, 9) for t in ts))
    return [('A', c, r, a0 + (a1 - a0) * u, a0 + (a1 - a0) * v) for u, v in zip(ts, ts[1:])]


def _faces_light(el, e):
    """True si el tramo mira hacia donde se extruye (su normal exterior tiene componente a favor de e)."""
    if el[0] == 'L':
        n = el[3]
    else:
        mid = (el[3] + el[4]) / 2
        n = (math.cos(mid), math.sin(mid))
    return n[0] * e[0] + n[1] * e[1] > 1e-9


def _emit(el, dx, dy):
    end = _ends(el)[1]
    end = (end[0] + dx, end[1] + dy)
    if el[0] == 'L':
        return f'L{pt(end)}'
    r = fmt(el[2])
    return f'A{r} {r} 0 {1 if abs(el[4] - el[3]) > math.pi else 0} {1 if el[4] > el[3] else 0} {pt(end)}'


def exact_sweep(outline, ex, ey, inset=0.0):
    """Contorno de una forma convexa arrastrada (ex, ey), con las mismas líneas y arcos de la forma.

    outline: tramos en orden, ('L', p, q, normal_exterior) o ('A', centro, radio, a0, a1). El contorno
    resultante sigue el original en la parte que mira contra la extrusión, la copia desplazada en la que mira
    a favor, y dos tramos paralelos a (ex, ey) que las unen. None si la forma no es convexa.

    inset: cuánto se adelanta, a lo largo de la extrusión, la parte que sigue al contorno original. Así ese
    borde queda tapado por la cara frontal y no asoma como una línea fina en su borde antialiasado.
    """
    phi = math.atan2(ey, ex)
    length = math.hypot(ex, ey)
    ox, oy = (ex / length * inset, ey / length * inset) if length else (0.0, 0.0)
    pieces = []
    for el in outline:
        pieces += _split_arc(*el[1:], phi) if el[0] == 'A' else [el]
    flags = [_faces_light(p, (ex, ey)) for p in pieces]
    start = next((i for i, f in enumerate(flags) if f and not flags[i - 1]), None)
    if start is None:
        return None
    pieces, flags = pieces[start:] + pieces[:start], flags[start:] + flags[:start]
    k = flags.index(False)
    if not all(flags[:k]) or any(flags[k:]):
        return None
    first = _ends(pieces[0])[0]
    d = f'M{pt((first[0] + ex, first[1] + ey))}'
    d += ''.join(_emit(p, ex, ey) for p in pieces[:k])
    last = _ends(pieces[k - 1])[1]
    d += f'L{pt((last[0] + ox, last[1] + oy))}'
    d += ''.join(_emit(p, ox, oy) for p in pieces[k:])
    return d + 'Z'


def _outline_from(corners, orient):
    """Une las esquinas [(inicio, fin, arco o None)] con rectas. orient: +1 si el recorrido gira a la derecha."""
    out = []
    for i, (_, end, arc) in enumerate(corners):
        if arc:
            out.append(('A',) + arc)
        nxt = corners[(i + 1) % len(corners)][0]
        dx, dy = nxt[0] - end[0], nxt[1] - end[1]
        length = math.hypot(dx, dy)
        if length > 1e-9:
            out.append(('L', end, nxt, (orient * dy / length, -orient * dx / length)))
    return out


class Shape:
    convex = True
    rot = 0

    def light_axis(self):
        """Eje del degradado de la cara en unidades de su caja: de arriba a la izquierda a abajo a la derecha.

        Una forma girada arrastra su degradado al girar, y un rombo quedaba iluminado de arriba a abajo mientras
        discos y cuadrados lo estaban en diagonal: el eje se gira de vuelta para que la luz sea la misma."""
        phi = math.radians(45 - self.rot)
        c, s = math.cos(phi), math.sin(phi)
        half = 0.5 / max(abs(c), abs(s))
        return (0.5 - half * c, 0.5 - half * s, 0.5 + half * c, 0.5 + half * s)

    def outline(self):
        """Tramos exactos del contorno (ver exact_sweep), o None si la forma no los da."""
        return None

    def sweep(self, ex, ey, inset=0.0):
        outline = self.outline()
        d = exact_sweep(outline, ex, ey, inset) if outline else None
        if d:
            return d
        pts = self.pts()
        return poly_path(hull(pts + [(x + ex, y + ey) for x, y in pts]))


class Disc(Shape):
    def __init__(self, cx, cy, r):
        self.cx, self.cy, self.r = cx, cy, r

    def pts(self, k=36):
        return [(self.cx + self.r * math.cos(2 * math.pi * i / k),
                 self.cy + self.r * math.sin(2 * math.pi * i / k)) for i in range(k)]

    def front(self, attrs):
        return f'<circle cx="{fmt(self.cx)}" cy="{fmt(self.cy)}" r="{fmt(self.r)}" {attrs}/>'

    def sweep(self, ex, ey, inset=0.0):
        length = math.hypot(ex, ey)
        if length < 1e-6:
            return None
        ux, uy = ex / length, ey / length
        nx, ny = -uy, ux
        r = self.r
        x0, y0 = self.cx + ux * inset, self.cy + uy * inset
        a = (x0 + r * nx, y0 + r * ny)
        b = (self.cx + ex + r * nx, self.cy + ey + r * ny)
        c = (self.cx + ex - r * nx, self.cy + ey - r * ny)
        d = (x0 - r * nx, y0 - r * ny)
        rr = fmt(r)
        return f'M{pt(a)}L{pt(b)}A{rr} {rr} 0 0 0 {pt(c)}L{pt(d)}A{rr} {rr} 0 0 0 {pt(a)}Z'


class Block(Shape):
    """Rectangulo con esquinas redondeadas y giro opcional alrededor de su centro."""

    def __init__(self, x, y, w, h, r=3, rot=0):
        self.x, self.y, self.w, self.h, self.r, self.rot = x, y, w, h, r, rot

    def _rot(self, p):
        if not self.rot:
            return p
        cx, cy = self.x + self.w / 2, self.y + self.h / 2
        c, s = math.cos(math.radians(self.rot)), math.sin(math.radians(self.rot))
        dx, dy = p[0] - cx, p[1] - cy
        return (cx + dx * c - dy * s, cy + dx * s + dy * c)

    def pts(self, k=6):
        r = min(self.r, self.w / 2, self.h / 2)
        x0, y0, w, h = self.x, self.y, self.w, self.h
        corners = [(x0 + r, y0 + r, 180), (x0 + w - r, y0 + r, 270),
                   (x0 + w - r, y0 + h - r, 0), (x0 + r, y0 + h - r, 90)]
        out = []
        for cx, cy, a0 in corners:
            for i in range(k + 1):
                a = math.radians(a0 + 90 * i / k)
                out.append((cx + r * math.cos(a), cy + r * math.sin(a)))
        return [self._rot(p) for p in out]

    def outline(self):
        r = min(self.r, self.w / 2, self.h / 2)
        x0, y0, w, h = self.x, self.y, self.w, self.h
        corners = []
        for cx, cy, deg in ((x0 + r, y0 + r, 180), (x0 + w - r, y0 + r, 270),
                            (x0 + w - r, y0 + h - r, 0), (x0 + r, y0 + h - r, 90)):
            c = self._rot((cx, cy))
            if r < 1e-9:
                corners.append((c, c, None))
                continue
            a0 = math.radians(deg + self.rot)
            arc = (c, r, a0, a0 + math.pi / 2)
            start, end = _arc_ends(*arc)
            corners.append((start, end, arc))
        return _outline_from(corners, 1)

    def front(self, attrs):
        r = min(self.r, self.w / 2, self.h / 2)
        tr = ''
        if self.rot:
            tr = f' transform="rotate({fmt(self.rot)} {fmt(self.x + self.w / 2)} {fmt(self.y + self.h / 2)})"'
        return (f'<rect x="{fmt(self.x)}" y="{fmt(self.y)}" width="{fmt(self.w)}" height="{fmt(self.h)}" '
                f'rx="{fmt(r)}"{tr} {attrs}/>')


class RPoly(Shape):
    """Poligono con esquinas redondeadas (triangulos, estrellas, flechas)."""

    def __init__(self, points, r=1.5):
        self.p = [(float(x), float(y)) for x, y in points]
        self.r = r
        self.corners = self._corners()
        self.convex = len(hull(self.p)) == len(self.p)

    def sweep(self, ex, ey, inset=0.0):
        if self.convex:
            return super().sweep(ex, ey, inset)
        return RPoly([(x + ex, y + ey) for x, y in self.p], self.r).path()

    def _corners(self):
        n, out = len(self.p), []
        for i in range(n):
            v, a, b = self.p[i], self.p[i - 1], self.p[(i + 1) % n]
            u1 = _unit((a[0] - v[0], a[1] - v[1]))
            u2 = _unit((b[0] - v[0], b[1] - v[1]))
            theta = math.acos(max(-1.0, min(1.0, u1[0] * u2[0] + u1[1] * u2[1])))
            if theta < 1e-3 or abs(theta - math.pi) < 1e-3:
                out.append((v, v, v, 0.0, 0))
                continue
            t = self.r / math.tan(theta / 2)
            t = min(t, 0.45 * math.hypot(a[0] - v[0], a[1] - v[1]), 0.45 * math.hypot(b[0] - v[0], b[1] - v[1]))
            r_eff = t * math.tan(theta / 2)
            s = (v[0] + u1[0] * t, v[1] + u1[1] * t)
            e = (v[0] + u2[0] * t, v[1] + u2[1] * t)
            cross = (v[0] - a[0]) * (b[1] - v[1]) - (v[1] - a[1]) * (b[0] - v[0])
            bis = _unit((u1[0] + u2[0], u1[1] + u2[1]))
            dist = r_eff / math.sin(theta / 2)
            c = (v[0] + bis[0] * dist, v[1] + bis[1] * dist)
            out.append((s, e, c, r_eff, 1 if cross > 0 else 0))
        return out

    def outline(self):
        if not self.convex:
            return None
        area = sum(a[0] * b[1] - b[0] * a[1] for a, b in zip(self.p, self.p[1:] + self.p[:1]))
        corners = []
        for s, e, c, r, _ in self.corners:
            if not r:
                corners.append((s, e, None))
                continue
            a0 = math.atan2(s[1] - c[1], s[0] - c[0])
            da = (math.atan2(e[1] - c[1], e[0] - c[0]) - a0 + math.pi) % (2 * math.pi) - math.pi
            corners.append((s, e, (c, r, a0, a0 + da)))
        return _outline_from(corners, 1 if area > 0 else -1)

    def path(self):
        d = ''
        for i, (s, e, _, r, sweep) in enumerate(self.corners):
            d += ('M' if i == 0 else 'L') + pt(s)
            if r:
                d += f'A{fmt(r)} {fmt(r)} 0 0 {sweep} {pt(e)}'
        return d + 'Z'

    def pts(self, k=6):
        out = []
        for s, e, c, r, _ in self.corners:
            if not r:
                out.append(s)
                continue
            a0 = math.atan2(s[1] - c[1], s[0] - c[0])
            a1 = math.atan2(e[1] - c[1], e[0] - c[0])
            da = (a1 - a0 + math.pi) % (2 * math.pi) - math.pi
            for i in range(k + 1):
                a = a0 + da * i / k
                out.append((c[0] + r * math.cos(a), c[1] + r * math.sin(a)))
        return out

    def front(self, attrs):
        return f'<path d="{self.path()}" {attrs}/>'


# ---------------------------------------------------------------- icono
class Icon:
    def __init__(self, stem):
        self.stem = stem
        self.links, self.solids, self.marks = [], [], []

    # ----- construccion
    def solid(self, shape, hue, d=3.2, depth=None, op=1.0):
        self.solids.append(dict(shape=shape, hue=hue, d=d, depth=depth, op=op))
        return self

    def link(self, pts, style='line', hue='slate', w=2.6):
        pts = [(float(x), float(y)) for x, y in pts]
        distinct = [p for i, p in enumerate(pts) if i == 0 or p != pts[i - 1]]  # un punto repetido no dibuja nada
        self.links.append(dict(pts=distinct if len(distinct) > 1 else pts, style=style, hue=hue, w=w))
        return self

    def mark(self, d, filled=False, tone='white', ink='ink', w=2.4, op=1.0, dash=None, behind=False, pts=None):
        """behind: la marca va detrás de los volúmenes (un contorno de referencia, no un detalle sobre uno).
        pts: sus puntos, si es una polilínea, para ocultar en lineas lo que tapa un volumen."""
        self.marks.append(dict(d=d, filled=filled, tone=tone, ink=ink, w=w, op=op, dash=dash, behind=behind, pts=pts))
        return self

    def poly(self, points, close=False, **kw):
        pts = [(float(x), float(y)) for x, y in points]
        return self.mark('M' + 'L'.join(pt(p) for p in pts) + ('Z' if close else ''), pts=pts + pts[:1] if close else pts, **kw)

    def dot(self, x, y, r, **kw):
        return self.mark(circle_path(x, y, r), filled=True, **kw)

    def ring(self, x, y, r, **kw):
        return self.mark(circle_path(x, y, r), **kw)

    def line(self, x1, y1, x2, y2, **kw):
        return self.mark(f'M{fmt(x1)} {fmt(y1)}L{fmt(x2)} {fmt(y2)}', **kw)

    def path(self, d, **kw):
        """@deprecated Sin uso desde que se comprobó (2026-10-25): ningún icono la llama, y mark() hace lo mismo.
        Se conserva como referencia, no se borra (regla de CLAUDE.md)."""
        return self.mark(d, **kw)

    def plus(self, x, y, s, **kw):
        return self.mark(f'M{fmt(x - s)} {fmt(y)}H{fmt(x + s)}M{fmt(x)} {fmt(y - s)}V{fmt(y + s)}', **kw)

    def cross(self, x, y, s, **kw):
        return self.mark(f'M{fmt(x - s)} {fmt(y - s)}L{fmt(x + s)} {fmt(y + s)}M{fmt(x + s)} {fmt(y - s)}L{fmt(x - s)} {fmt(y + s)}', **kw)

    def check(self, x, y, s, **kw):
        return self.mark(f'M{fmt(x - s)} {fmt(y + 0.05 * s)}L{fmt(x - 0.3 * s)} {fmt(y + 0.7 * s)}L{fmt(x + s)} {fmt(y - 0.7 * s)}', **kw)

    def bars(self, x, y, widths, gap, **kw):
        return self.mark(''.join(f'M{fmt(x)} {fmt(y + i * gap)}h{fmt(w)}' for i, w in enumerate(widths)), **kw)

    def arc(self, x, y, r, a0, a1, **kw):
        return self.mark(arc_path(x, y, r, a0, a1), **kw)

    def arrow(self, x1, y1, x2, y2, head=4.0, **kw):
        ux, uy = _unit((x2 - x1, y2 - y1))
        px, py = -uy, ux
        h = head
        d = (f'M{fmt(x1)} {fmt(y1)}L{fmt(x2)} {fmt(y2)}'
             f'M{fmt(x2 - ux * h + px * h * 0.8)} {fmt(y2 - uy * h + py * h * 0.8)}L{fmt(x2)} {fmt(y2)}'
             f'L{fmt(x2 - ux * h - px * h * 0.8)} {fmt(y2 - uy * h - py * h * 0.8)}')
        return self.mark(d, **kw)

    # ----- render
    @staticmethod
    def _box(s):
        """Caja del volumen con su extrusion, algo ensanchada para que cuenten los que solo se rozan."""
        ex, ey = LIGHT[0] * s['d'], LIGHT[1] * s['d']
        pts = s['shape'].pts()
        xs = [p[0] for p in pts] + [p[0] + ex for p in pts]
        ys = [p[1] for p in pts] + [p[1] + ey for p in pts]
        return min(xs) - 0.5, min(ys) - 0.5, max(xs) + 0.5, max(ys) + 0.5

    def _gray_shifts(self):
        """Cuanto aclarar u oscurecer cada volumen en la variante gris para que no se funda con otro que toca.

        Los tonos de la paleta se reparten bien en color pero mal en luminosidad: siete caen en 11 niveles de gris.
        Dos volumenes que se tocan y son de tonos distintos se separan al menos GRAY_GAP; cede el de delante."""
        shifts, grays, boxes = [], [], []
        for s in self.solids:
            level, box = gray_level(BASE[s['hue']]), self._box(s)
            others = [grays[j] for j, o in enumerate(self.solids[:len(grays)])
                      if o['hue'] != s['hue'] and not (box[2] <= boxes[j][0] or boxes[j][2] <= box[0] or box[3] <= boxes[j][1] or boxes[j][3] <= box[1])]
            shift = 0
            if others and min(abs(level - g) for g in others) < GRAY_GAP:
                away = 1 if level >= min(others, key=lambda g: abs(level - g)) else -1  # alejarse del vecino mas cercano
                for k in range(1, 256):
                    ok = [c for c in (away * k, -away * k) if 30 <= level + c <= 215 and all(abs(level + c - g) >= GRAY_GAP for g in others)]
                    if ok:
                        shift = ok[0]
                        break
            shifts.append(shift)
            grays.append(level + shift)
            boxes.append(box)
        return shifts

    def _bbox(self):
        xs, ys = [], []
        for s in self.solids:
            ex, ey = LIGHT[0] * s['d'], LIGHT[1] * s['d']
            for x, y in s['shape'].pts():
                xs += [x, x + ex]
                ys += [y, y + ey]
        return (min(xs), min(ys), max(xs), max(ys)) if xs else (16, 16, 48, 48)

    def _compose(self, variant):
        """(tonos usados, líneas del dibujo): lo que comparten el archivo suelto (`render`) y el <symbol> del sprite
        (`symbol`). Las líneas van sin sangría de base: quien las usa las coloca donde las necesita."""
        assert variant in VARIANTS
        lin = variant == 'lineas'
        col = gray if variant == 'gris' else (lambda c: c)
        grads, out_shadow, out_back, out_links, out_solids, out_marks = {}, [], [], [], [], []

        if not lin:
            x0, _, x1, y1 = self._bbox()
            cx, cy = (x0 + x1) / 2, min(y1 + 2.4, 59.5)
            rx = max(8.0, min(22.0, (x1 - x0) * 0.46))
            out_shadow.append(f'<ellipse cx="{fmt(cx)}" cy="{fmt(cy)}" rx="{fmt(rx)}" ry="2.6" fill="{col(SHADOW)}" opacity="0.14"/>')

        for ln in self.links:
            out_links += self._render_link(ln, variant, col)

        shifts = self._gray_shifts() if variant == 'gris' else [0] * len(self.solids)
        for s, shift in zip(self.solids, shifts):
            hi, base, dark = tones(s['hue'])
            ex, ey = LIGHT[0] * s['d'], LIGHT[1] * s['d']
            tint = (lambda c, k=shift: gray(c, k)) if variant == 'gris' else col
            sweep = s['shape'].sweep(ex, ey, 0.0 if lin else min(INSET, math.hypot(ex, ey) / 3)) if s['d'] > 0 else None
            parts = []
            if lin:
                if sweep and s['shape'].convex:
                    parts.append(f'<path d="{sweep}" fill="none" stroke-width="{fmt(LINE_MIN)}" stroke-linejoin="round"/>')
                parts.append(s['shape'].front(f'fill="none" stroke-width="{fmt(LINE_MIN)}" stroke-linejoin="round"'))
            else:
                if sweep:
                    dfill = tones(s['depth'])[1] if s['depth'] else dark
                    parts.append(f'<path d="{sweep}" fill="{tint(dfill)}"/>')
                turn = f'_r{fmt(s["shape"].rot)}'.replace('-', 'm').replace('.', 'p') if s['shape'].rot else ''
                moved = f'_{"m" if shift < 0 else "p"}{abs(shift)}' if shift else ''
                gid = f'grad_{self.stem}_{s["hue"]}{turn}{"_g" if variant == "gris" else ""}{moved}'
                grads[gid] = (tint(hi), tint(base), s['shape'].light_axis())
                op = f' opacity="{fmt(s["op"])}"' if s['op'] != 1 else ''
                parts.append(s['shape'].front(f'fill="url(#{gid})"{op}'))
            out_solids.append(f'<g class="ic-{s["hue"]}" stroke="{dark}">' + ''.join(parts) + '</g>' if lin else '<g>' + ''.join(parts) + '</g>')

        sil = self._silhouettes() if lin and any(m['behind'] for m in self.marks) else []
        for m in self.marks:
            if not m['behind']:
                out_marks.append(self._render_mark(m, lin, col))
                continue
            d = m['d']
            if lin and m['pts']:  # sin relleno, un volumen no tapa lo que tiene detrás: se oculta como en los enlaces
                d = ''.join('M' + 'L'.join(pt(q) for q in pl) for pl in self._trim(m['pts'], sil))
            if d:
                out_back.append(self._render_mark(m, lin, col, d))

        used = sorted(set(re.findall(r'class="ic-([a-z]+)"', ''.join(out_back + out_links + out_solids + out_marks)))) if lin else []
        lines = []
        if grads:
            lines.append('<defs>')
            for gid, (c0, c1, axis) in grads.items():
                x1, y1, x2, y2 = (fmt(v) for v in axis)
                lines += [f'  <linearGradient id="{gid}" x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}">',
                          f'    <stop offset="0" stop-color="{c0}"/>',
                          f'    <stop offset="1" stop-color="{c1}"/>',
                          '  </linearGradient>']
            lines.append('</defs>')
        for cls, items in (('shadow', out_shadow), ('back', out_back), ('links', out_links), ('solids', out_solids), ('marks', out_marks)):
            if items:
                lines.append(f'<g class="{cls}">')
                lines += [f'  {i}' for i in items]
                lines.append('</g>')
        return used, lines

    def render(self, variant):
        used, body = self._compose(variant)
        scope = f' class="{SCOPE_CLASS}"' if used else ''
        lines = ['<?xml version="1.0" encoding="UTF-8"?>',
                 f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64"{scope}>']
        lines += theme_style(used, '  ')
        lines += [f'  {line}' for line in body]
        lines.append('</svg>')
        return '\n'.join(lines) + '\n'

    def symbol(self, variant):
        """(tonos usados, texto) del icono como <symbol id="<nombre>"> para un sprite (ver `sprite`): mismo dibujo que
        `render`, sin <style>."""
        used, body = self._compose(variant)
        return used, '\n'.join([f'  <symbol id="{self.stem}" viewBox="0 0 64 64">', *[f'    {line}' for line in body], '  </symbol>'])

    # ----- piezas
    def _silhouettes(self):
        out = []
        for s in self.solids:
            pts = s['shape'].pts()
            ex, ey = LIGHT[0] * s['d'], LIGHT[1] * s['d']
            out.append(hull(pts + [(x + ex, y + ey) for x, y in pts]) if s['d'] > 0 else hull(pts))
        return out

    @staticmethod
    def _trim(points, sil):
        """Partes de la polilinea que quedan fuera de todas las siluetas (linea oculta)."""
        segs = list(zip(points, points[1:]))
        lens = [math.hypot(b[0] - a[0], b[1] - a[1]) for a, b in segs]
        total = sum(lens)
        if total < 1e-6:
            return []

        def at(s):
            acc = 0.0
            for (a, b), length in zip(segs, lens):
                if s <= acc + length or (a, b) == segs[-1]:
                    t = 0 if length == 0 else min(1.0, (s - acc) / length)
                    return (a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t)
                acc += length

        n = max(2, int(total / 0.25))
        vis = [not any(in_convex(poly, at(total * i / n)) for poly in sil) for i in range(n + 1)]
        out, i = [], 0
        while i <= n:
            if not vis[i]:
                i += 1
                continue
            j = i
            while j + 1 <= n and vis[j + 1]:
                j += 1
            s0, s1 = total * i / n, total * j / n
            pl, acc = [at(s0)], 0.0
            for (a, b), length in zip(segs, lens):
                acc += length
                if s0 + 1e-6 < acc < s1 - 1e-6:
                    pl.append(b)
            pl.append(at(s1))
            if s1 - s0 > 0.6:
                out.append(pl)
            i = j + 1
        return out

    def _render_link(self, ln, variant, col):
        _, base, dark = tones(ln['hue'])
        lin = variant == 'lineas'
        c = dark if lin else col(base)
        w, p, style = ln['w'], ln['pts'], ln['style']
        if lin and w <= BAR:  # en lineas los enlaces pesaban 2,6 y los contornos 1,8: todo trazo normal entre los dos
            w = max(LINE_MIN, min(w, LINE_MAX))
        cap = 'butt' if lin and style not in ('dotted', 'dashdot') else 'round'
        cls = f'class="ic-{ln["hue"]}" ' if lin else ''
        common = f'{cls}fill="none" stroke="{c}" stroke-linecap="{cap}" stroke-linejoin="round"'
        sil = self._silhouettes() if lin else []

        def polys(points):
            return self._trim(points, sil) if lin else [points]

        def d_of(pls):
            return ''.join('M' + 'L'.join(pt(q) for q in pl) for pl in pls)

        a, b = p[0], p[-1]
        ux, uy = _unit((b[0] - a[0], b[1] - a[1]))
        nx, ny = -uy, ux
        if style in ('line', 'dashed', 'dotted', 'dashdot'):
            d = d_of(polys(p))
            extra = {'line': '', 'dashed': ' stroke-dasharray="4 4.5"', 'dotted': ' stroke-dasharray="0.1 5.2"',
                     'dashdot': ' stroke-dasharray="5 4.4 0.1 4.4"'}[style]
            return [f'<path d="{d}" {common} stroke-width="{fmt(w)}"{extra}/>'] if d else []
        if style == 'tie':  # vínculo lateral (entre hermanos, hermanastros, cuñados): una raya con un travesaño en el medio
            d = d_of(polys(p))
            mid = ((a[0] + b[0]) / 2, (a[1] + b[1]) / 2)
            d += f'M{pt((mid[0] + nx * 3.6, mid[1] + ny * 3.6))}L{pt((mid[0] - nx * 3.6, mid[1] - ny * 3.6))}'
            return [f'<path d="{d}" {common} stroke-width="{fmt(w)}"/>'] if d else []
        if style == 'double':
            off = 1.9
            d = ''
            for s in (-off, off):
                d += d_of(polys([(a[0] + nx * s, a[1] + ny * s), (b[0] + nx * s, b[1] + ny * s)]))
            return [f'<path d="{d}" {common} stroke-width="{fmt(LINE_MIN if lin else round(w * 0.62, 1))}"/>'] if d else []
        if style == 'cut':
            mid = ((a[0] + b[0]) / 2, (a[1] + b[1]) / 2)
            g = 5.6
            e1 = (mid[0] - ux * g, mid[1] - uy * g)
            e2 = (mid[0] + ux * g, mid[1] + uy * g)
            ang = math.radians(62)
            vx, vy = _unit((ux * math.cos(ang) - uy * math.sin(ang), ux * math.sin(ang) + uy * math.cos(ang)))
            d = d_of(polys([a, e1])) + d_of(polys([e2, b]))
            for s in (-2.8, 2.8):
                cx, cy = mid[0] + ux * s, mid[1] + uy * s
                d += f'M{pt((cx - vx * 6.0, cy - vy * 6.0))}L{pt((cx + vx * 6.0, cy + vy * 6.0))}'
            return [f'<path d="{d}" {common} stroke-width="{fmt(w)}"/>']
        raise ValueError(style)

    def _render_mark(self, m, lin, col, d=None):
        if lin:
            hue = m['tone'] if m['tone'] in BASE else m['ink']
            w = LINE_MIN if m['filled'] else max(LINE_MIN, min(m['w'], LINE_MAX))
            attrs = (f'class="ic-{hue}" fill="none" stroke="{tones(hue)[2]}" stroke-width="{fmt(w)}" '
                     'stroke-linecap="round" stroke-linejoin="round"')
        else:
            if m['tone'] == 'white':
                c = '#FFFFFF'
            elif m['tone'].startswith('#'):
                c = m['tone']
            else:
                c = BASE[m['tone']]
            c = col(c)
            if m['filled']:
                attrs = f'fill="{c}"'
            else:
                attrs = f'fill="none" stroke="{c}" stroke-width="{fmt(m["w"])}" stroke-linecap="round" stroke-linejoin="round"'
        if m['dash'] and not m['filled']:
            attrs += f' stroke-dasharray="{m["dash"]}"'
        if m['op'] != 1:
            attrs += f' opacity="{fmt(m["op"])}"'
        return f'<path d="{d or m["d"]}" {attrs}/>'


def sprite(icons, variant):
    """Todos los iconos de un set, en una variante, como un solo SVG de <symbol> (uno por icono, con su nombre de id)
    para pintarlos con <use href="sprite.svg#nombre">. En `lineas` lleva un solo <style> con la unión de los tonos."""
    parts = [(hues, text) for _, hues, text in sorted((i.stem, *i.symbol(variant)) for i in icons)]
    used = sorted({h for hues, _ in parts for h in hues})
    scope = f' class="{SCOPE_CLASS}"' if used else ''
    lines = ['<?xml version="1.0" encoding="UTF-8"?>', f'<svg xmlns="http://www.w3.org/2000/svg"{scope}>']
    lines += theme_style(used, '  ')
    lines += [text for _, text in parts]
    lines.append('</svg>')
    return '\n'.join(lines) + '\n'
