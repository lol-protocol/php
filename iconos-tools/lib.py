#!/usr/bin/env python3
"""Primitivas para iconos abstractos 2.5D, sin texto.

Un icono se define una sola vez (solidos con extrusion, enlaces y marcas) y se
renderiza en tres variantes: color, lineas y gris.
"""
import math
from pathlib import Path

VARIANTS = ('color', 'lineas', 'gris')
LIGHT = (0.6, 0.8)  # direccion de la extrusion: abajo a la derecha

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
    s = f'{v:.1f}'
    if s.endswith('.0'):
        s = s[:-2]
    return '0' if s == '-0' else s


def pt(p):
    return f'{fmt(p[0])} {fmt(p[1])}'


def _rgb(h):
    h = h.lstrip('#')
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def _hex(rgb):
    return '#%02X%02X%02X' % tuple(max(0, min(255, round(v))) for v in rgb)


def mix(a, b, t):
    return _hex(tuple(x + (y - x) * t for x, y in zip(_rgb(a), _rgb(b))))


def gray(c):
    r, g, b = _rgb(c)
    y = round(0.299 * r + 0.587 * g + 0.114 * b)
    return '#%02X%02X%02X' % (y, y, y)


def tones(hue):
    base = BASE[hue]
    return mix(base, '#FFFFFF', 0.32), base, mix(base, '#000000', 0.42)


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


def ellipse_path(x, y, rx, ry, rot=0):
    c, s = math.cos(math.radians(rot)), math.sin(math.radians(rot))
    a = (x - rx * c, y - rx * s)
    b = (x + rx * c, y + rx * s)
    return (f'M{pt(a)}A{fmt(rx)} {fmt(ry)} {fmt(rot)} 1 0 {pt(b)}'
            f'A{fmt(rx)} {fmt(ry)} {fmt(rot)} 1 0 {pt(a)}Z')


def arc_path(x, y, r, a0, a1):
    p0 = (x + r * math.cos(math.radians(a0)), y + r * math.sin(math.radians(a0)))
    p1 = (x + r * math.cos(math.radians(a1)), y + r * math.sin(math.radians(a1)))
    large = 1 if (a1 - a0) % 360 > 180 else 0
    return f'M{pt(p0)}A{fmt(r)} {fmt(r)} 0 {large} 1 {pt(p1)}'


def star_points(x, y, R, n=5, inner=0.46, rot=-90):
    out = []
    for i in range(2 * n):
        rad = R if i % 2 == 0 else R * inner
        a = math.radians(rot + i * 180 / n)
        out.append((x + rad * math.cos(a), y + rad * math.sin(a)))
    return out


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


class Shape:
    convex = True

    def sweep(self, ex, ey):
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

    def sweep(self, ex, ey):
        length = math.hypot(ex, ey)
        if length < 1e-6:
            return None
        ux, uy = ex / length, ey / length
        nx, ny = -uy, ux
        r = self.r
        a = (self.cx + r * nx, self.cy + r * ny)
        b = (a[0] + ex, a[1] + ey)
        c = (self.cx + ex - r * nx, self.cy + ey - r * ny)
        d = (self.cx - r * nx, self.cy - r * ny)
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

    def sweep(self, ex, ey):
        if self.convex:
            return super().sweep(ex, ey)
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

    def path(self):
        d = ''
        for i, (s, e, c, r, sweep) in enumerate(self.corners):
            d += ('M' if i == 0 else 'L') + pt(s)
            if r:
                d += f'A{fmt(r)} {fmt(r)} 0 0 {sweep} {pt(e)}'
        return d + 'Z'

    def pts(self, k=6):
        out = []
        for s, e, c, r, sweep in self.corners:
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
        self.links.append(dict(pts=[(float(x), float(y)) for x, y in pts], style=style, hue=hue, w=w))
        return self

    def mark(self, d, filled=False, tone='white', ink='ink', w=2.4, op=1.0, dash=None):
        self.marks.append(dict(d=d, filled=filled, tone=tone, ink=ink, w=w, op=op, dash=dash))
        return self

    def poly(self, points, close=False, **kw):
        return self.mark('M' + 'L'.join(pt(p) for p in points) + ('Z' if close else ''), **kw)

    def dot(self, x, y, r, **kw):
        return self.mark(circle_path(x, y, r), filled=True, **kw)

    def ring(self, x, y, r, **kw):
        return self.mark(circle_path(x, y, r), **kw)

    def line(self, x1, y1, x2, y2, **kw):
        return self.mark(f'M{fmt(x1)} {fmt(y1)}L{fmt(x2)} {fmt(y2)}', **kw)

    def path(self, d, **kw):
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

    def star(self, x, y, R, n=5, inner=0.46, rot=-90, **kw):
        return self.mark(poly_path(star_points(x, y, R, n, inner, rot)), filled=True, **kw)

    def spark(self, x, y, R, **kw):
        k = R * 0.2
        d = (f'M{fmt(x)} {fmt(y - R)}Q{fmt(x + k)} {fmt(y - k)} {fmt(x + R)} {fmt(y)}'
             f'Q{fmt(x + k)} {fmt(y + k)} {fmt(x)} {fmt(y + R)}'
             f'Q{fmt(x - k)} {fmt(y + k)} {fmt(x - R)} {fmt(y)}'
             f'Q{fmt(x - k)} {fmt(y - k)} {fmt(x)} {fmt(y - R)}Z')
        return self.mark(d, filled=True, **kw)

    # ----- render
    def _bbox(self):
        xs, ys = [], []
        for s in self.solids:
            ex, ey = LIGHT[0] * s['d'], LIGHT[1] * s['d']
            for x, y in s['shape'].pts():
                xs += [x, x + ex]
                ys += [y, y + ey]
        return (min(xs), min(ys), max(xs), max(ys)) if xs else (16, 16, 48, 48)

    def render(self, variant):
        assert variant in VARIANTS
        lin = variant == 'lineas'
        col = (lambda c: gray(c)) if variant == 'gris' else (lambda c: c)
        grads, out_shadow, out_links, out_solids, out_marks = {}, [], [], [], []

        if not lin:
            x0, y0, x1, y1 = self._bbox()
            cx, cy = (x0 + x1) / 2, min(y1 + 2.4, 59.5)
            rx = max(8.0, min(22.0, (x1 - x0) * 0.46))
            out_shadow.append(f'<ellipse cx="{fmt(cx)}" cy="{fmt(cy)}" rx="{fmt(rx)}" ry="2.6" fill="{col(SHADOW)}" opacity="0.14"/>')

        for ln in self.links:
            out_links += self._render_link(ln, variant, col)

        for s in self.solids:
            hi, base, dark = tones(s['hue'])
            ex, ey = LIGHT[0] * s['d'], LIGHT[1] * s['d']
            sweep = s['shape'].sweep(ex, ey) if s['d'] > 0 else None
            parts = []
            if lin:
                if sweep and s['shape'].convex:
                    parts.append(f'<path d="{sweep}" fill="none" stroke="{dark}" stroke-width="1.8" stroke-linejoin="round"/>')
                parts.append(s['shape'].front(f'fill="none" stroke="{dark}" stroke-width="1.8" stroke-linejoin="round"'))
            else:
                if sweep:
                    dfill = tones(s['depth'])[1] if s['depth'] else dark
                    parts.append(f'<path d="{sweep}" fill="{col(dfill)}"/>')
                gid = f'grad_{self.stem}_{s["hue"]}'
                grads[gid] = (col(hi), col(base))
                op = f' opacity="{fmt(s["op"])}"' if s['op'] != 1 else ''
                parts.append(s['shape'].front(f'fill="url(#{gid})"{op}'))
            out_solids.append('<g>' + ''.join(parts) + '</g>')

        for m in self.marks:
            out_marks.append(self._render_mark(m, lin, col))

        lines = ['<?xml version="1.0" encoding="UTF-8"?>',
                 '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64">']
        if grads:
            lines.append('  <defs>')
            for gid, (c0, c1) in grads.items():
                lines += [f'    <linearGradient id="{gid}" x1="0" y1="0" x2="1" y2="1">',
                          f'      <stop offset="0" stop-color="{c0}"/>',
                          f'      <stop offset="1" stop-color="{c1}"/>',
                          '    </linearGradient>']
            lines.append('  </defs>')
        for cls, items in (('shadow', out_shadow), ('links', out_links), ('solids', out_solids), ('marks', out_marks)):
            if items:
                lines.append(f'  <g class="{cls}">')
                lines += [f'    {i}' for i in items]
                lines.append('  </g>')
        lines.append('</svg>')
        return '\n'.join(lines) + '\n'

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
        hi, base, dark = tones(ln['hue'])
        lin = variant == 'lineas'
        c = dark if lin else col(base)
        w, p, style = ln['w'], ln['pts'], ln['style']
        cap = 'butt' if lin and style != 'dotted' else 'round'
        common = f'fill="none" stroke="{c}" stroke-linecap="{cap}" stroke-linejoin="round"'
        sil = self._silhouettes() if lin else []

        def polys(points):
            return self._trim(points, sil) if lin else [points]

        def d_of(pls):
            return ''.join('M' + 'L'.join(pt(q) for q in pl) for pl in pls)

        a, b = p[0], p[-1]
        ux, uy = _unit((b[0] - a[0], b[1] - a[1]))
        nx, ny = -uy, ux
        if style in ('line', 'dashed', 'dotted'):
            d = d_of(polys(p))
            extra = {'line': '', 'dashed': ' stroke-dasharray="4 4.5"', 'dotted': ' stroke-dasharray="0.1 5.2"'}[style]
            return [f'<path d="{d}" {common} stroke-width="{fmt(w)}"{extra}/>'] if d else []
        if style == 'double':
            off = 1.9
            d = ''
            for s in (-off, off):
                d += d_of(polys([(a[0] + nx * s, a[1] + ny * s), (b[0] + nx * s, b[1] + ny * s)]))
            return [f'<path d="{d}" {common} stroke-width="{fmt(w * 0.62)}"/>'] if d else []
        if style == 'cut':
            mid = ((a[0] + b[0]) / 2, (a[1] + b[1]) / 2)
            g = 4.8
            e1 = (mid[0] - ux * g, mid[1] - uy * g)
            e2 = (mid[0] + ux * g, mid[1] + uy * g)
            ang = math.radians(62)
            vx, vy = _unit((ux * math.cos(ang) - uy * math.sin(ang), ux * math.sin(ang) + uy * math.cos(ang)))
            d = d_of(polys([a, e1])) + d_of(polys([e2, b]))
            for s in (-2.4, 2.4):
                cx, cy = mid[0] + ux * s, mid[1] + uy * s
                d += f'M{pt((cx - vx * 5.2, cy - vy * 5.2))}L{pt((cx + vx * 5.2, cy + vy * 5.2))}'
            return [f'<path d="{d}" {common} stroke-width="{fmt(w)}"/>']
        raise ValueError(style)

    def _render_mark(self, m, lin, col):
        if lin:
            hue = m['tone'] if m['tone'] in BASE else m['ink']
            w = 1.8 if m['filled'] else max(1.8, min(m['w'], 2.2))
            attrs = (f'fill="none" stroke="{tones(hue)[2]}" stroke-width="{fmt(w)}" '
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
        return f'<path d="{m["d"]}" {attrs}/>'


def write(icon, root, folder):
    for v in VARIANTS:
        p = Path(root) / v / folder / f'{icon.stem}.svg'
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(icon.render(v), encoding='utf-8')
