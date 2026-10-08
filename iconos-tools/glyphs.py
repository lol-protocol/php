#!/usr/bin/env python3
"""Glifos geometricos reutilizables (sin letras ni numeros), dibujados en una caja de +-10 alrededor de (x, y)."""
import math

from lib import ellipse_path, fmt

GLYPHS = {}


def glyph(fn):
    GLYPHS[fn.__name__[2:]] = fn
    return fn


def _o(kw, **d):
    d.update(kw)
    return d


def _arc_arrow(ic, x, y, r, a0, a1, head=4.4, **kw):
    ic.arc(x, y, r, a0, a1, **kw)
    a = math.radians(a1)
    ex, ey = x + r * math.cos(a), y + r * math.sin(a)
    tx, ty = -math.sin(a), math.cos(a)
    pts = []
    for s in (1, -1):
        g = math.radians(38 * s)
        bx = -(tx * math.cos(g) - ty * math.sin(g))
        by = -(tx * math.sin(g) + ty * math.cos(g))
        pts.append((ex + bx * head, ey + by * head))
    ic.poly([pts[0], (ex, ey), pts[1]], **{k: v for k, v in kw.items() if k != 'dash'})


# ------------------------------------------------------------------ basicos
@glyph
def g_plus(ic, x, y, **kw):
    ic.plus(x, y, 8.5, **_o(kw, w=3.4))


@glyph
def g_minus(ic, x, y, **kw):
    ic.line(x - 8.5, y, x + 8.5, y, **_o(kw, w=3.4))


@glyph
def g_cross(ic, x, y, **kw):
    ic.cross(x, y, 7.5, **_o(kw, w=3.4))


@glyph
def g_check(ic, x, y, **kw):
    ic.check(x, y, 9, **_o(kw, w=3.4))


@glyph
def g_arrow_right(ic, x, y, **kw):
    ic.arrow(x - 9, y, x + 9, y, head=6, **_o(kw, w=3.2))


@glyph
def g_arrow_left(ic, x, y, **kw):
    ic.arrow(x + 9, y, x - 9, y, head=6, **_o(kw, w=3.2))


@glyph
def g_arrow_up(ic, x, y, **kw):
    ic.arrow(x, y + 9, x, y - 9, head=6, **_o(kw, w=3.2))


@glyph
def g_arrow_down(ic, x, y, **kw):
    ic.arrow(x, y - 9, x, y + 9, head=6, **_o(kw, w=3.2))


@glyph
def g_download(ic, x, y, **kw):
    ic.arrow(x, y - 9, x, y + 3, head=5, **_o(kw, w=3))
    ic.poly([(x - 9, y + 3), (x - 9, y + 9), (x + 9, y + 9), (x + 9, y + 3)], **_o(kw, w=3))


@glyph
def g_upload(ic, x, y, **kw):
    ic.arrow(x, y + 3, x, y - 9, head=5, **_o(kw, w=3))
    ic.poly([(x - 9, y + 3), (x - 9, y + 9), (x + 9, y + 9), (x + 9, y + 3)], **_o(kw, w=3))


@glyph
def g_arrow_in(ic, x, y, **kw):
    ic.arrow(x - 10, y, x + 3, y, head=5.5, **_o(kw, w=3))
    ic.poly([(x + 3, y - 9), (x + 9, y - 9), (x + 9, y + 9), (x + 3, y + 9)], **_o(kw, w=3))


@glyph
def g_arrow_out(ic, x, y, **kw):
    ic.arrow(x - 2, y, x + 10, y, head=5.5, **_o(kw, w=3))
    ic.poly([(x - 3, y - 9), (x - 9, y - 9), (x - 9, y + 9), (x - 3, y + 9)], **_o(kw, w=3))


@glyph
def g_refresh(ic, x, y, **kw):
    _arc_arrow(ic, x, y, 7.5, 205, 335, **_o(kw, w=2.8))
    _arc_arrow(ic, x, y, 7.5, 25, 155, **_o(kw, w=2.8))


@glyph
def g_undo(ic, x, y, **kw):
    ic.path(f'M{fmt(x - 3)} {fmt(y - 7)}H{fmt(x + 2)}A6.5 6.5 0 0 1 {fmt(x + 2)} {fmt(y + 6)}H{fmt(x - 5)}', **_o(kw, w=2.8))
    ic.poly([(x - 2, y - 11), (x - 7, y - 7), (x - 2, y - 3)], **_o(kw, w=2.8))


@glyph
def g_redo(ic, x, y, **kw):
    ic.path(f'M{fmt(x + 3)} {fmt(y - 7)}H{fmt(x - 2)}A6.5 6.5 0 0 0 {fmt(x - 2)} {fmt(y + 6)}H{fmt(x + 5)}', **_o(kw, w=2.8))
    ic.poly([(x + 2, y - 11), (x + 7, y - 7), (x + 2, y - 3)], **_o(kw, w=2.8))


@glyph
def g_search(ic, x, y, **kw):
    ic.ring(x - 2, y - 2, 6.5, **_o(kw, w=3))
    ic.line(x + 2.8, y + 2.8, x + 9, y + 9, **_o(kw, w=3.6))


@glyph
def g_zoom_in(ic, x, y, **kw):
    g_search(ic, x, y, **kw)
    ic.plus(x - 2, y - 2, 3, **_o(kw, w=2))


@glyph
def g_zoom_out(ic, x, y, **kw):
    g_search(ic, x, y, **kw)
    ic.line(x - 5, y - 2, x + 1, y - 2, **_o(kw, w=2))


@glyph
def g_filter(ic, x, y, **kw):
    ic.poly([(x - 9, y - 8), (x + 9, y - 8), (x + 2, y + 1), (x + 2, y + 8), (x - 2, y + 6), (x - 2, y + 1)], True, filled=True, **kw)


@glyph
def g_sort(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 9)} {fmt(y - 6)}h18M{fmt(x - 9)} {fmt(y)}h12M{fmt(x - 9)} {fmt(y + 6)}h6', **_o(kw, w=3))


@glyph
def g_edit(ic, x, y, **kw):
    ic.poly([(x + 5.9, y - 9.1), (x + 9.1, y - 5.9), (x - 2.4, y + 5.6), (x - 8.5, y + 8.5), (x - 5.6, y + 2.4)], True, filled=True, **kw)


@glyph
def g_copy(ic, x, y, **kw):
    ic.poly([(x - 8, y - 2), (x + 3, y - 2), (x + 3, y + 9), (x - 8, y + 9)], True, **_o(kw, w=2.4))
    ic.poly([(x - 3, y - 5), (x - 3, y - 8), (x + 8, y - 8), (x + 8, y + 3), (x + 6, y + 3)], **_o(kw, w=2.4))


@glyph
def g_link(ic, x, y, **kw):
    ic.mark(ellipse_path(x - 3.6, y + 3.6, 7, 3.8, -45), **_o(kw, w=2.4))
    ic.mark(ellipse_path(x + 3.6, y - 3.6, 7, 3.8, -45), **_o(kw, w=2.4))


@glyph
def g_unlink(ic, x, y, **kw):
    ic.mark(ellipse_path(x - 5.4, y + 5.4, 6, 3.4, -45), **_o(kw, w=2.4))
    ic.mark(ellipse_path(x + 5.4, y - 5.4, 6, 3.4, -45), **_o(kw, w=2.4))
    ic.line(x - 1.5, y + 1.5, x + 1.5, y - 1.5, **_o(kw, w=2))


@glyph
def g_merge(ic, x, y, **kw):
    ic.poly([(x - 8, y - 9), (x, y - 1), (x + 8, y - 9)], **_o(kw, w=2.8))
    ic.arrow(x, y - 2, x, y + 9, head=4.6, **_o(kw, w=2.8))


@glyph
def g_split(ic, x, y, **kw):
    ic.line(x, y + 9, x, y + 1, **_o(kw, w=2.8))
    ic.arrow(x, y + 1, x - 8, y - 8, head=4.4, **_o(kw, w=2.8))
    ic.arrow(x, y + 1, x + 8, y - 8, head=4.4, **_o(kw, w=2.8))


@glyph
def g_share(ic, x, y, **kw):
    ic.line(x - 7, y, x + 6, y - 7, **_o(kw, w=2))
    ic.line(x - 7, y, x + 6, y + 7, **_o(kw, w=2))
    for px, py in ((x - 7, y), (x + 6, y - 7), (x + 6, y + 7)):
        ic.dot(px, py, 3.4, **kw)


@glyph
def g_hub(ic, x, y, **kw):
    for px, py in ((x - 8, y - 7), (x + 8, y - 7), (x - 8, y + 7), (x + 8, y + 7)):
        ic.line(x, y, px, py, **_o(kw, w=1.8))
        ic.dot(px, py, 2.4, **kw)
    ic.dot(x, y, 3.8, **kw)


@glyph
def g_expand(ic, x, y, **kw):
    for sx, sy in ((-1, -1), (1, -1), (-1, 1), (1, 1)):
        ic.poly([(x + sx * 3, y + sy * 9), (x + sx * 9, y + sy * 9), (x + sx * 9, y + sy * 3)], **_o(kw, w=2.8))


@glyph
def g_collapse(ic, x, y, **kw):
    for sx, sy in ((-1, -1), (1, -1), (-1, 1), (1, 1)):
        ic.poly([(x + sx * 9, y + sy * 3), (x + sx * 3, y + sy * 3), (x + sx * 3, y + sy * 9)], **_o(kw, w=2.8))


@glyph
def g_print(ic, x, y, **kw):
    ic.poly([(x - 5, y - 3), (x - 5, y - 9), (x + 5, y - 9), (x + 5, y - 3)], **_o(kw, w=2.2))
    ic.poly([(x - 9, y - 3), (x + 9, y - 3), (x + 9, y + 5), (x - 9, y + 5)], True, **_o(kw, w=2.2))
    ic.poly([(x - 5, y + 2), (x + 5, y + 2), (x + 5, y + 9), (x - 5, y + 9)], True, filled=True, **kw)


@glyph
def g_save(ic, x, y, **kw):
    ic.poly([(x - 8, y - 9), (x + 5, y - 9), (x + 9, y - 5), (x + 9, y + 9), (x - 8, y + 9)], True, **_o(kw, w=2.2))
    ic.poly([(x - 4, y - 9), (x - 4, y - 3), (x + 3, y - 3), (x + 3, y - 9)], **_o(kw, w=2.2))
    ic.poly([(x - 4, y + 9), (x - 4, y + 3), (x + 4, y + 3), (x + 4, y + 9)], **_o(kw, w=2.2))


@glyph
def g_sliders(ic, x, y, **kw):
    for i, (dy, kx) in enumerate(((-6, -3), (0, 3), (6, -1))):
        ic.mark(f'M{fmt(x - 9)} {fmt(y + dy)}H{fmt(x + kx - 3.4)}M{fmt(x + kx + 3.4)} {fmt(y + dy)}H{fmt(x + 9)}', **_o(kw, w=2))
        ic.ring(x + kx, y + dy, 2.2, **_o(kw, w=2))


@glyph
def g_menu(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 8)} {fmt(y - 6)}h16M{fmt(x - 8)} {fmt(y)}h16M{fmt(x - 8)} {fmt(y + 6)}h16', **_o(kw, w=3))


@glyph
def g_calendar(ic, x, y, **kw):
    ic.poly([(x - 9, y - 7), (x + 9, y - 7), (x + 9, y + 9), (x - 9, y + 9)], True, **_o(kw, w=2.2))
    ic.line(x - 9, y - 2, x + 9, y - 2, **_o(kw, w=2.2))
    ic.mark(f'M{fmt(x - 4)} {fmt(y - 10)}v5M{fmt(x + 4)} {fmt(y - 10)}v5', **_o(kw, w=2.4))
    for dx in (-4, 1):
        for dy in (2.5, 6.5):
            ic.dot(x + dx + 1.5, y + dy, 1.2, **kw)


@glyph
def g_clock(ic, x, y, **kw):
    ic.ring(x, y, 9, **_o(kw, w=2.4))
    ic.poly([(x, y - 5), (x, y), (x + 4, y + 3)], **_o(kw, w=2.4))


@glyph
def g_bell(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 7)} {fmt(y + 5)}Q{fmt(x - 7)} {fmt(y - 9)} {fmt(x)} {fmt(y - 9)}Q{fmt(x + 7)} {fmt(y - 9)} {fmt(x + 7)} {fmt(y + 5)}Z', filled=True, **kw)
    ic.dot(x, y + 8.5, 2, **kw)


@glyph
def g_lock(ic, x, y, **kw):
    ic.poly([(x - 7, y - 1), (x + 7, y - 1), (x + 7, y + 9), (x - 7, y + 9)], True, filled=True, **kw)
    ic.arc(x, y - 1, 4.6, 180, 360, **_o(kw, w=2.6))
    ic.mark(f'M{fmt(x - 4.6)} {fmt(y - 1)}V{fmt(y - 2)}M{fmt(x + 4.6)} {fmt(y - 1)}V{fmt(y - 2)}', **_o(kw, w=2.6))


@glyph
def g_unlock(ic, x, y, **kw):
    ic.poly([(x - 7, y - 1), (x + 7, y - 1), (x + 7, y + 9), (x - 7, y + 9)], True, filled=True, **kw)
    ic.arc(x + 2, y - 1, 4.6, 180, 360, **_o(kw, w=2.6))
    ic.mark(f'M{fmt(x - 2.6)} {fmt(y - 1)}V{fmt(y - 3)}', **_o(kw, w=2.6))


@glyph
def g_eye(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 10)} {fmt(y)}Q{fmt(x)} {fmt(y - 12)} {fmt(x + 10)} {fmt(y)}Q{fmt(x)} {fmt(y + 12)} {fmt(x - 10)} {fmt(y)}Z', **_o(kw, w=2.2))
    ic.dot(x, y, 3.2, **kw)


@glyph
def g_star(ic, x, y, **kw):
    ic.star(x, y, 10, **kw)


@glyph
def g_heart(ic, x, y, **kw):
    d = (f'M{fmt(x)} {fmt(y + 8)}C{fmt(x - 12)} {fmt(y - 1)} {fmt(x - 6)} {fmt(y - 10)} {fmt(x)} {fmt(y - 4)}'
         f'C{fmt(x + 6)} {fmt(y - 10)} {fmt(x + 12)} {fmt(y - 1)} {fmt(x)} {fmt(y + 8)}Z')
    ic.mark(d, filled=True, **kw)


@glyph
def g_bookmark(ic, x, y, **kw):
    ic.poly([(x - 5.5, y - 9), (x + 5.5, y - 9), (x + 5.5, y + 9), (x, y + 4.5), (x - 5.5, y + 9)], True, filled=True, **kw)


@glyph
def g_flag(ic, x, y, **kw):
    ic.line(x - 6, y + 9, x - 6, y - 9, **_o(kw, w=2.4))
    ic.poly([(x - 6, y - 9), (x + 8, y - 9), (x + 4, y - 4), (x + 8, y + 1), (x - 6, y + 1)], True, filled=True, **kw)


@glyph
def g_pin(ic, x, y, **kw):
    d = (f'M{fmt(x)} {fmt(y + 10)}C{fmt(x - 9)} {fmt(y + 1)} {fmt(x - 8)} {fmt(y - 9)} {fmt(x)} {fmt(y - 9)}'
         f'C{fmt(x + 8)} {fmt(y - 9)} {fmt(x + 9)} {fmt(y + 1)} {fmt(x)} {fmt(y + 10)}Z')
    ic.mark(d, **_o(kw, w=2.4))
    ic.dot(x, y - 2.5, 2.8, **kw)


@glyph
def g_image(ic, x, y, **kw):
    ic.poly([(x - 9, y - 8), (x + 9, y - 8), (x + 9, y + 8), (x - 9, y + 8)], True, **_o(kw, w=2.2))
    ic.poly([(x - 9, y + 5), (x - 3, y - 1), (x + 1, y + 3), (x + 4, y), (x + 9, y + 5)], **_o(kw, w=2.2))
    ic.dot(x + 4, y - 4, 1.8, **kw)


@glyph
def g_document(ic, x, y, **kw):
    ic.poly([(x - 7, y - 10), (x + 2, y - 10), (x + 8, y - 4), (x + 8, y + 10), (x - 7, y + 10)], True, **_o(kw, w=2.2))
    ic.poly([(x + 2, y - 10), (x + 2, y - 4), (x + 8, y - 4)], **_o(kw, w=2.2))
    ic.mark(f'M{fmt(x - 3)} {fmt(y + 1)}h7M{fmt(x - 3)} {fmt(y + 5.5)}h7', **_o(kw, w=2))


@glyph
def g_folder(ic, x, y, **kw):
    ic.poly([(x - 9, y - 7), (x - 2, y - 7), (x + 1, y - 4), (x + 9, y - 4), (x + 9, y + 8), (x - 9, y + 8)], True, **_o(kw, w=2.2))


@glyph
def g_tag(ic, x, y, **kw):
    ic.poly([(x - 9, y - 1), (x - 2, y - 8), (x + 8, y - 8), (x + 8, y + 2), (x + 1, y + 9)], True, **_o(kw, w=2.2))
    ic.dot(x + 3.5, y - 3.5, 1.8, **kw)


@glyph
def g_user(ic, x, y, **kw):
    ic.dot(x, y - 4, 4.6, **kw)
    ic.mark(f'M{fmt(x - 8)} {fmt(y + 9)}A8 8 0 0 1 {fmt(x + 8)} {fmt(y + 9)}Z', filled=True, **kw)


@glyph
def g_users(ic, x, y, **kw):
    ic.dot(x - 4, y - 4, 3.6, **kw)
    ic.mark(f'M{fmt(x - 10)} {fmt(y + 9)}A6 6 0 0 1 {fmt(x + 2)} {fmt(y + 9)}Z', filled=True, **kw)
    ic.dot(x + 5, y - 5, 3.2, **_o(kw, op=0.8))
    ic.mark(f'M{fmt(x - 1)} {fmt(y + 9)}A5.5 5.5 0 0 1 {fmt(x + 10)} {fmt(y + 9)}Z', filled=True, **_o(kw, op=0.8))


@glyph
def g_globe(ic, x, y, **kw):
    ic.ring(x, y, 9, **_o(kw, w=2.2))
    ic.mark(ellipse_path(x, y, 4, 9), **_o(kw, w=1.8))
    ic.line(x - 9, y, x + 9, y, **_o(kw, w=1.8))


@glyph
def g_map(ic, x, y, **kw):
    ic.poly([(x - 9, y - 6), (x - 3, y - 9), (x + 3, y - 6), (x + 9, y - 9), (x + 9, y + 6), (x + 3, y + 9), (x - 3, y + 6), (x - 9, y + 9)], True, **_o(kw, w=2))
    ic.mark(f'M{fmt(x - 3)} {fmt(y - 9)}V{fmt(y + 6)}M{fmt(x + 3)} {fmt(y - 6)}V{fmt(y + 9)}', **_o(kw, w=1.8))


@glyph
def g_bars(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 6)} {fmt(y + 9)}V{fmt(y + 3)}M{fmt(x)} {fmt(y + 9)}V{fmt(y - 2)}M{fmt(x + 6)} {fmt(y + 9)}V{fmt(y - 8)}', **_o(kw, w=3.6))


@glyph
def g_pie(ic, x, y, **kw):
    ic.ring(x, y, 9, **_o(kw, w=2.2))
    ic.poly([(x, y - 9), (x, y), (x + 8, y + 4.5)], **_o(kw, w=2.2))


@glyph
def g_grid(ic, x, y, **kw):
    for dx in (-8.6, 0.6):
        for dy in (-8.6, 0.6):
            ic.poly([(x + dx, y + dy), (x + dx + 8, y + dy), (x + dx + 8, y + dy + 8), (x + dx, y + dy + 8)], True, filled=True, **kw)


@glyph
def g_list(ic, x, y, **kw):
    for dy in (-6, 0, 6):
        ic.dot(x - 7, y + dy, 1.7, **kw)
        ic.line(x - 3, y + dy, x + 9, y + dy, **_o(kw, w=2.2))


@glyph
def g_cards(ic, x, y, **kw):
    ic.poly([(x - 9, y - 4), (x + 3, y - 8), (x + 8, y + 3), (x - 4, y + 7)], True, **_o(kw, w=2, op=0.6))
    ic.poly([(x - 5, y - 8), (x + 8, y - 6), (x + 6, y + 8), (x - 7, y + 6)], True, **_o(kw, w=2.2))


@glyph
def g_hourglass(ic, x, y, **kw):
    ic.poly([(x - 7, y - 9), (x + 7, y - 9), (x, y), (x + 7, y + 9), (x - 7, y + 9), (x, y)], True, **_o(kw, w=2.4))


@glyph
def g_flame(ic, x, y, **kw):
    d = (f'M{fmt(x)} {fmt(y - 10)}C{fmt(x + 3)} {fmt(y - 5)} {fmt(x + 8)} {fmt(y - 2)} {fmt(x + 7)} {fmt(y + 4)}'
         f'C{fmt(x + 6)} {fmt(y + 9)} {fmt(x + 2)} {fmt(y + 10)} {fmt(x)} {fmt(y + 10)}'
         f'C{fmt(x - 4)} {fmt(y + 10)} {fmt(x - 7)} {fmt(y + 7)} {fmt(x - 6)} {fmt(y + 2)}'
         f'C{fmt(x - 5)} {fmt(y - 1)} {fmt(x - 2)} {fmt(y - 3)} {fmt(x)} {fmt(y - 10)}Z')
    ic.mark(d, filled=True, **kw)


@glyph
def g_podium(ic, x, y, **kw):
    for x0, x1, top in ((-9, -3, 1), (-3, 3, -6), (3, 9, 4)):
        ic.poly([(x + x0 + 0.4, y + 9), (x + x0 + 0.4, y + top), (x + x1 - 0.4, y + top), (x + x1 - 0.4, y + 9)], True, filled=True, **kw)


@glyph
def g_chevrons_up(ic, x, y, **kw):
    ic.poly([(x - 8, y + 2), (x, y - 6), (x + 8, y + 2)], **_o(kw, w=3))
    ic.poly([(x - 8, y + 9), (x, y + 1), (x + 8, y + 9)], **_o(kw, w=3))


@glyph
def g_pause(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 4.5)} {fmt(y - 8)}V{fmt(y + 8)}M{fmt(x + 4.5)} {fmt(y - 8)}V{fmt(y + 8)}', **_o(kw, w=4))


@glyph
def g_play(ic, x, y, **kw):
    ic.poly([(x - 5, y - 9), (x + 9, y), (x - 5, y + 9)], True, filled=True, **kw)


@glyph
def g_speech(ic, x, y, **kw):
    ic.poly([(x - 9, y - 8), (x + 9, y - 8), (x + 9, y + 3), (x + 1, y + 3), (x - 4, y + 8), (x - 4, y + 3), (x - 9, y + 3)], True, **_o(kw, w=2.2))


@glyph
def g_tray(ic, x, y, **kw):
    ic.poly([(x - 9, y - 8), (x + 9, y - 8), (x + 9, y - 3), (x - 9, y - 3)], True, **_o(kw, w=2.2))
    ic.poly([(x - 7, y - 3), (x - 7, y + 8), (x + 7, y + 8), (x + 7, y - 3)], **_o(kw, w=2.2))
    ic.line(x - 3, y + 1, x + 3, y + 1, **_o(kw, w=2.2))


@glyph
def g_barcode(ic, x, y, **kw):
    for dx, w in ((-8, 1.6), (-5, 3), (-1, 1.6), (2, 2.6), (5.5, 1.4), (8, 2.4)):
        ic.line(x + dx, y - 8, x + dx, y + 8, **_o(kw, w=w))


@glyph
def g_crown(ic, x, y, **kw):
    ic.poly([(x - 9, y + 7), (x - 9, y - 5), (x - 4, y + 1), (x, y - 7), (x + 4, y + 1), (x + 9, y - 5), (x + 9, y + 7)], True, filled=True, **kw)


@glyph
def g_sparkles(ic, x, y, **kw):
    ic.spark(x - 2, y + 1, 8, **kw)
    ic.spark(x + 7, y - 7, 3.6, **kw)
    ic.spark(x + 7, y + 8, 2.6, **kw)


@glyph
def g_spark(ic, x, y, **kw):
    ic.spark(x, y, 11, **kw)


@glyph
def g_dna(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 5)} {fmt(y - 10)}C{fmt(x - 5)} {fmt(y - 3)} {fmt(x + 5)} {fmt(y - 3)} {fmt(x + 5)} {fmt(y)}'
            f'C{fmt(x + 5)} {fmt(y + 3)} {fmt(x - 5)} {fmt(y + 3)} {fmt(x - 5)} {fmt(y + 10)}', **_o(kw, w=2.4))
    ic.mark(f'M{fmt(x + 5)} {fmt(y - 10)}C{fmt(x + 5)} {fmt(y - 3)} {fmt(x - 5)} {fmt(y - 3)} {fmt(x - 5)} {fmt(y)}'
            f'C{fmt(x - 5)} {fmt(y + 3)} {fmt(x + 5)} {fmt(y + 3)} {fmt(x + 5)} {fmt(y + 10)}', **_o(kw, w=2.4))
    ic.line(x - 3, y - 6, x + 3, y - 6, **_o(kw, w=1.6))
    ic.line(x - 3, y + 6, x + 3, y + 6, **_o(kw, w=1.6))


@glyph
def g_tree(ic, x, y, **kw):
    ic.poly([(x, y + 9), (x, y - 1)], **_o(kw, w=2))
    ic.poly([(x - 7, y + 3), (x - 7, y - 1), (x + 7, y - 1), (x + 7, y + 3)], **_o(kw, w=2))
    ic.dot(x, y - 6, 3.2, **kw)
    ic.dot(x - 7, y + 5.5, 2.6, **kw)
    ic.dot(x + 7, y + 5.5, 2.6, **kw)


@glyph
def g_nodes_down(ic, x, y, **kw):
    ic.poly([(x, y - 6), (x, y), (x - 6, y), (x - 6, y + 5)], **_o(kw, w=2))
    ic.poly([(x, y), (x + 6, y), (x + 6, y + 5)], **_o(kw, w=2))
    ic.dot(x, y - 7, 3, **kw)
    ic.dot(x - 6, y + 7, 2.6, **kw)
    ic.dot(x + 6, y + 7, 2.6, **kw)


@glyph
def g_nodes_up(ic, x, y, **kw):
    ic.poly([(x, y + 6), (x, y), (x - 6, y), (x - 6, y - 5)], **_o(kw, w=2))
    ic.poly([(x, y), (x + 6, y), (x + 6, y - 5)], **_o(kw, w=2))
    ic.dot(x, y + 7, 3, **kw)
    ic.dot(x - 6, y - 7, 2.6, **kw)
    ic.dot(x + 6, y - 7, 2.6, **kw)


@glyph
def g_warning(ic, x, y, **kw):
    ic.poly([(x, y - 9), (x + 10, y + 8), (x - 10, y + 8)], True, **_o(kw, w=2.4))
    ic.line(x, y - 2, x, y + 2.5, **_o(kw, w=2.6))
    ic.dot(x, y + 5.4, 1.2, **kw)


@glyph
def g_shield(ic, x, y, **kw):
    ic.mark(f'M{fmt(x)} {fmt(y - 10)}L{fmt(x + 9)} {fmt(y - 6)}V{fmt(y + 1)}Q{fmt(x + 9)} {fmt(y + 7)} {fmt(x)} {fmt(y + 10)}'
            f'Q{fmt(x - 9)} {fmt(y + 7)} {fmt(x - 9)} {fmt(y + 1)}V{fmt(y - 6)}Z', **_o(kw, w=2.4))
    ic.check(x, y, 4, **_o(kw, w=2.4))


@glyph
def g_house(ic, x, y, **kw):
    ic.poly([(x - 10, y), (x, y - 9), (x + 10, y)], **_o(kw, w=2.6))
    ic.poly([(x - 7, y - 2), (x - 7, y + 9), (x + 7, y + 9), (x + 7, y - 2)], **_o(kw, w=2.4))


@glyph
def g_camera(ic, x, y, **kw):
    ic.poly([(x - 9, y - 4), (x - 4, y - 4), (x - 2, y - 8), (x + 2, y - 8), (x + 4, y - 4), (x + 9, y - 4), (x + 9, y + 8), (x - 9, y + 8)], True, **_o(kw, w=2.2))
    ic.ring(x, y + 2, 3.4, **_o(kw, w=2.2))


@glyph
def g_key(ic, x, y, **kw):
    ic.ring(x - 4.5, y - 4.5, 4.4, **_o(kw, w=2.6))
    ic.poly([(x - 1.4, y - 1.4), (x + 9, y + 9)], **_o(kw, w=2.6))
    ic.line(x + 4, y + 4, x + 7.5, y + 0.5, **_o(kw, w=2.4))


@glyph
def g_gift(ic, x, y, **kw):
    ic.poly([(x - 8, y - 2), (x + 8, y - 2), (x + 8, y + 9), (x - 8, y + 9)], True, **_o(kw, w=2.2))
    ic.poly([(x - 9, y - 7), (x + 9, y - 7), (x + 9, y - 2), (x - 9, y - 2)], True, filled=True, **kw)
    ic.line(x, y - 7, x, y + 9, **_o(kw, w=2))


@glyph
def g_envelope(ic, x, y, **kw):
    ic.poly([(x - 10, y - 7), (x + 10, y - 7), (x + 10, y + 7), (x - 10, y + 7)], True, **_o(kw, w=2.2))
    ic.poly([(x - 10, y - 7), (x, y + 1), (x + 10, y - 7)], **_o(kw, w=2.2))


@glyph
def g_music(ic, x, y, **kw):
    ic.dot(x - 3.5, y + 6, 3.6, **kw)
    ic.line(x - 0.5, y + 6, x - 0.5, y - 9, **_o(kw, w=2.4))
    ic.mark(f'M{fmt(x - 0.5)} {fmt(y - 9)}Q{fmt(x + 8)} {fmt(y - 6)} {fmt(x + 6)} {fmt(y + 1)}', **_o(kw, w=2.4))


@glyph
def g_scale(ic, x, y, **kw):
    ic.line(x, y - 9, x, y + 8, **_o(kw, w=2.2))
    ic.line(x - 9, y - 6, x + 9, y - 6, **_o(kw, w=2.2))
    ic.mark(f'M{fmt(x - 9)} {fmt(y - 6)}L{fmt(x - 12)} {fmt(y + 1)}H{fmt(x - 6)}ZM{fmt(x + 9)} {fmt(y - 6)}L{fmt(x + 6)} {fmt(y + 1)}H{fmt(x + 12)}Z', **_o(kw, w=1.8))
    ic.line(x - 5, y + 8, x + 5, y + 8, **_o(kw, w=2.4))


@glyph
def g_leaf(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 8)} {fmt(y + 8)}C{fmt(x - 8)} {fmt(y - 4)} {fmt(x + 2)} {fmt(y - 9)} {fmt(x + 9)} {fmt(y - 9)}'
            f'C{fmt(x + 9)} {fmt(y - 1)} {fmt(x + 3)} {fmt(y + 8)} {fmt(x - 8)} {fmt(y + 8)}Z', filled=True, **kw)


@glyph
def g_drop(ic, x, y, **kw):
    ic.mark(f'M{fmt(x)} {fmt(y - 10)}C{fmt(x + 8)} {fmt(y - 1)} {fmt(x + 8)} {fmt(y + 9)} {fmt(x)} {fmt(y + 9)}'
            f'C{fmt(x - 8)} {fmt(y + 9)} {fmt(x - 8)} {fmt(y - 1)} {fmt(x)} {fmt(y - 10)}Z', filled=True, **kw)


@glyph
def g_bolt(ic, x, y, **kw):
    ic.poly([(x + 3, y - 10), (x - 5, y + 1), (x, y + 1), (x - 3, y + 10), (x + 6, y - 2), (x + 1, y - 2)], True, filled=True, **kw)


@glyph
def g_fast(ic, x, y, **kw):
    ic.poly([(x - 9, y - 8), (x - 1, y), (x - 9, y + 8)], True, filled=True, **kw)
    ic.poly([(x, y - 8), (x + 8, y), (x, y + 8)], True, filled=True, **kw)
