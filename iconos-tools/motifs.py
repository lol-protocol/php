#!/usr/bin/env python3
"""Motivos abstractos para portadas de genero (caja de +-10 alrededor de (x, y)). Sin letras ni numeros."""
import math

from glyphs import GLYPHS, _o
from lib import ellipse_path, fmt, pt

MOTIFS = {}


def motif(fn):
    MOTIFS[fn.__name__[2:]] = fn
    return fn


def _ngon(x, y, r, n, rot=-90):
    return [(x + r * math.cos(math.radians(rot + 360 * i / n)), y + r * math.sin(math.radians(rot + 360 * i / n))) for i in range(n)]


@motif
def m_romance(ic, x, y, **kw):
    ic.dot(x - 4, y, 6.4, **_o(kw, op=0.92))
    ic.dot(x + 4, y, 6.4, **_o(kw, op=0.92))


@motif
def m_scifi(ic, x, y, **kw):
    ic.mark(ellipse_path(x, y, 11, 4.4, -28), **_o(kw, w=2.2))
    ic.dot(x, y, 4, **kw)
    ic.dot(x + 8.1, y - 6.3, 1.8, **kw)


@motif
def m_fantasy(ic, x, y, **kw):
    GLYPHS['sparkles'](ic, x, y, **kw)


@motif
def m_horror(ic, x, y, **kw):
    GLYPHS['drop'](ic, x - 1, y, **kw)
    ic.dot(x + 7, y - 6, 1.7, **kw)


@motif
def m_mystery(ic, x, y, **kw):
    GLYPHS['eye'](ic, x, y, **kw)


@motif
def m_thriller(ic, x, y, **kw):
    ic.ring(x, y, 9.5, **_o(kw, w=2.2))
    ic.ring(x, y, 5, **_o(kw, w=2.2))
    ic.dot(x, y, 1.6, **kw)


@motif
def m_suspense(ic, x, y, **kw):
    ic.poly([(x - 9, y - 4), (x + 9, y - 4), (x, y + 9)], True, filled=True, **kw)
    ic.dot(x, y - 9, 1.8, **kw)


@motif
def m_adventure(ic, x, y, **kw):
    ic.poly([(x - 10, y + 7), (x - 3, y - 7), (x + 1, y), (x + 5, y - 5), (x + 10, y + 7)], **_o(kw, w=2.4))
    ic.line(x - 10, y + 7, x + 10, y + 7, **_o(kw, w=2.4))


@motif
def m_western(ic, x, y, **kw):
    ic.star(x, y, 10, **kw)


@motif
def m_history(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 7)} {fmt(y + 9)}V{fmt(y - 1)}A7 7 0 0 1 {fmt(x + 7)} {fmt(y - 1)}V{fmt(y + 9)}', **_o(kw, w=2.6))
    ic.line(x - 10, y + 9, x + 10, y + 9, **_o(kw, w=2.6))


@motif
def m_biography(ic, x, y, **kw):
    GLYPHS['user'](ic, x, y, **kw)


@motif
def m_memoir(ic, x, y, **kw):
    ic.ring(x, y - 3, 4.8, **_o(kw, w=2.6))
    ic.line(x, y + 2, x, y + 9, **_o(kw, w=3.2))


@motif
def m_essay(ic, x, y, **kw):
    ic.line(x - 9, y + 3, x + 9, y + 3, **_o(kw, w=3))
    ic.dot(x - 6, y - 5, 2.4, **kw)


@motif
def m_essay_collection(ic, x, y, **kw):
    for dy in (-6, 0, 6):
        ic.arc(x, y + dy - 4, 9, 25, 155, **_o(kw, w=2.4))


@motif
def m_poetry(ic, x, y, **kw):
    for dy in (-4, 4):
        ic.mark(f'M{fmt(x - 10)} {fmt(y + dy)}Q{fmt(x - 5)} {fmt(y + dy - 7)} {fmt(x)} {fmt(y + dy)}T{fmt(x + 10)} {fmt(y + dy)}', **_o(kw, w=2.4))


@motif
def m_philosophy(ic, x, y, **kw):
    ic.ring(x, y, 8, **_o(kw, w=2.4))
    ic.line(x, y - 11, x, y + 11, **_o(kw, w=2))


@motif
def m_psychology(ic, x, y, **kw):
    ic.arc(x - 3.5, y, 9, -70, 70, **_o(kw, w=2.8))
    ic.arc(x + 3.5, y, 9, 110, 250, **_o(kw, w=2.8))


@motif
def m_self_help(ic, x, y, **kw):
    GLYPHS['chevrons_up'](ic, x, y, **kw)


@motif
def m_children(ic, x, y, **kw):
    for dx, dy in ((0, -6), (-6.5, 4), (6.5, 4)):
        ic.dot(x + dx, y + dy, 3, **kw)
    ic.ring(x, y, 11, **_o(kw, w=1.8, op=0.7))


@motif
def m_young_adult(ic, x, y, **kw):
    GLYPHS['bolt'](ic, x, y, **kw)


@motif
def m_comedy(ic, x, y, **kw):
    ic.arc(x, y - 3, 9, 20, 160, **_o(kw, w=3))
    ic.dot(x - 5, y - 6, 1.9, **kw)
    ic.dot(x + 5, y - 6, 1.9, **kw)


@motif
def m_drama(ic, x, y, **kw):
    ic.arc(x, y - 6, 8, 25, 155, **_o(kw, w=2.8))
    ic.arc(x, y + 9, 8, 205, 335, **_o(kw, w=2.8))


@motif
def m_cooking(ic, x, y, **kw):
    for dx in (-6, 0, 6):
        ic.mark(f'M{fmt(x + dx)} {fmt(y + 4)}C{fmt(x + dx + 3)} {fmt(y)} {fmt(x + dx - 3)} {fmt(y - 3)} {fmt(x + dx)} {fmt(y - 8)}', **_o(kw, w=2.2))
    ic.line(x - 9, y + 9, x + 9, y + 9, **_o(kw, w=2.8))


@motif
def m_travel(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 9)} {fmt(y + 8)}C{fmt(x - 9)} {fmt(y)} {fmt(x + 8)} {fmt(y + 4)} {fmt(x + 8)} {fmt(y - 5)}', **_o(kw, w=2.4, dash='0.1 4.6'))
    ic.ring(x + 8, y - 6, 3, **_o(kw, w=2.2))


@motif
def m_art(ic, x, y, **kw):
    for dx, dy in ((-7, 3), (0, -4), (7, 3)):
        ic.dot(x + dx, y + dy, 3.1, **kw)
    ic.ring(x, y, 11, **_o(kw, w=1.8, op=0.7))


@motif
def m_science(ic, x, y, **kw):
    for rot in (0, 60, -60):
        ic.mark(ellipse_path(x, y, 10, 3.8, rot), **_o(kw, w=1.8))
    ic.dot(x, y, 2, **kw)


@motif
def m_technology(ic, x, y, **kw):
    ic.poly([(x - 6, y - 6), (x + 6, y - 6), (x + 6, y + 6), (x - 6, y + 6)], True, **_o(kw, w=2.2))
    pins = ''.join(f'M{fmt(x + d)} {fmt(y - 10)}v4M{fmt(x + d)} {fmt(y + 10)}v-4M{fmt(x - 10)} {fmt(y + d)}h4M{fmt(x + 10)} {fmt(y + d)}h-4' for d in (-3, 3))
    ic.mark(pins, **_o(kw, w=2))


@motif
def m_mathematics(ic, x, y, **kw):
    ic.poly([(x, y - 9), (x + 9, y + 7), (x - 9, y + 7)], True, **_o(kw, w=2.4))
    ic.ring(x, y + 2, 2.8, **_o(kw, w=2))


@motif
def m_economics(ic, x, y, **kw):
    GLYPHS['bars'](ic, x, y, **kw)


@motif
def m_law(ic, x, y, **kw):
    GLYPHS['scale'](ic, x, y, **kw)


@motif
def m_medicine(ic, x, y, **kw):
    ic.plus(x, y, 9, **_o(kw, w=4.6))


@motif
def m_religion(ic, x, y, **kw):
    ic.dot(x, y, 3.6, **kw)
    rays = ''
    for k in range(8):
        a = math.radians(45 * k)
        rays += f'M{pt((x + 6.6 * math.cos(a), y + 6.6 * math.sin(a)))}L{pt((x + 10 * math.cos(a), y + 10 * math.sin(a)))}'
    ic.mark(rays, **_o(kw, w=2.2))


@motif
def m_sports(ic, x, y, **kw):
    ic.ring(x, y, 9, **_o(kw, w=2.2))
    ic.arc(x - 11, y, 9, -40, 40, **_o(kw, w=2))
    ic.arc(x + 11, y, 9, 140, 220, **_o(kw, w=2))


@motif
def m_nature(ic, x, y, **kw):
    GLYPHS['leaf'](ic, x, y, **kw)


@motif
def m_education(ic, x, y, **kw):
    ic.poly([(x, y - 8), (x + 11, y - 3), (x, y + 2), (x - 11, y - 3)], True, filled=True, **kw)
    ic.mark(f'M{fmt(x - 6)} {fmt(y)}V{fmt(y + 5)}Q{fmt(x)} {fmt(y + 9)} {fmt(x + 6)} {fmt(y + 5)}V{fmt(y)}', **_o(kw, w=2.4))


@motif
def m_reference(ic, x, y, **kw):
    GLYPHS['grid'](ic, x, y, **kw)


@motif
def m_humor(ic, x, y, **kw):
    ic.arc(x, y - 2, 9, 20, 160, **_o(kw, w=3))
    ic.dot(x - 5, y - 6, 1.9, **kw)
    ic.line(x + 2.5, y - 6, x + 7.5, y - 6, **_o(kw, w=2.2))


@motif
def m_music(ic, x, y, **kw):
    GLYPHS['music'](ic, x, y, **kw)


@motif
def m_graphic_novel(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 10)} {fmt(y - 9)}h9v8h-9ZM{fmt(x + 1)} {fmt(y - 9)}h9v8h-9ZM{fmt(x - 10)} {fmt(y + 1)}h20v8h-20Z', **_o(kw, w=1.8))


@motif
def m_manga(ic, x, y, **kw):
    ic.mark(f'M{fmt(x - 9)} {fmt(y + 8)}L{fmt(x + 1)} {fmt(y - 8)}M{fmt(x - 3)} {fmt(y + 8)}L{fmt(x + 7)} {fmt(y - 8)}M{fmt(x + 3)} {fmt(y + 8)}L{fmt(x + 10)} {fmt(y - 3)}', **_o(kw, w=2.6))


@motif
def m_anthology(ic, x, y, **kw):
    for dx in (-8, 0, 8):
        ic.ring(x + dx, y, 3.4, **_o(kw, w=2.2))
    ic.line(x - 8, y + 8, x + 8, y + 8, **_o(kw, w=2.2))


@motif
def m_chronicle(ic, x, y, **kw):
    ic.line(x - 6, y - 9, x - 6, y + 9, **_o(kw, w=2))
    for dy, w in ((-7, 9), (0, 6), (7, 10)):
        ic.dot(x - 6, y + dy, 2.3, **kw)
        ic.line(x - 1, y + dy, x - 1 + w, y + dy, **_o(kw, w=2.4))


@motif
def m_diary(ic, x, y, **kw):
    GLYPHS['lock'](ic, x, y, **kw)


@motif
def m_epistolary(ic, x, y, **kw):
    GLYPHS['envelope'](ic, x, y, **kw)


@motif
def m_epistle(ic, x, y, **kw):
    ic.poly([(x, y - 10), (x + 9, y), (x, y + 10), (x - 9, y)], True, **_o(kw, w=2.4))
    ic.dot(x, y, 2.4, **kw)


@motif
def m_treatise(ic, x, y, **kw):
    ic.poly([(x - 9, y - 9), (x + 9, y - 9), (x + 9, y + 9), (x - 9, y + 9)], True, **_o(kw, w=2.2))
    ic.poly([(x - 4.5, y - 4.5), (x + 4.5, y - 4.5), (x + 4.5, y + 4.5), (x - 4.5, y + 4.5)], True, **_o(kw, w=2.2))


@motif
def m_screenplay(ic, x, y, **kw):
    ic.poly([(x - 10, y - 8), (x + 10, y - 8), (x + 10, y + 8), (x - 10, y + 8)], True, **_o(kw, w=2.2))
    for dx in (-7, 7):
        for dy in (-4, 4):
            ic.dot(x + dx, y + dy, 1.3, **kw)


@motif
def m_literary_fiction(ic, x, y, **kw):
    ic.arc(x, y, 8, 40, 330, **_o(kw, w=3.2))


@motif
def m_narrative(ic, x, y, **kw):
    ic.mark(f'M{fmt(x)} {fmt(y)}a1.6 1.6 0 1 1 3.2 0a3.2 3.2 0 1 1 -6.4 0a4.8 4.8 0 1 1 9.6 0a6.4 6.4 0 1 1 -12.8 0a8 8 0 1 1 16 0', **_o(kw, w=2.2))


@motif
def m_fiction(ic, x, y, **kw):
    ic.ring(x - 7.5, y, 3.4, **_o(kw, w=2.2))
    ic.poly([(x - 3, y - 3.4), (x + 3, y - 3.4), (x + 3, y + 3.4), (x - 3, y + 3.4)], True, **_o(kw, w=2.2))
    ic.poly([(x + 7.5, y - 4), (x + 11.5, y + 3.4), (x + 3.5, y + 3.4)], True, **_o(kw, w=2.2))


@motif
def m_non_fiction(ic, x, y, **kw):
    ic.poly(_ngon(x, y, 9, 6, 0), True, **_o(kw, w=2.4))
    ic.dot(x, y, 2.2, **kw)


@motif
def m_article(ic, x, y, **kw):
    ic.poly([(x - 10, y - 8), (x - 2, y - 8), (x - 2, y + 8), (x - 10, y + 8)], True, **_o(kw, w=2.2))
    ic.poly([(x + 2, y - 8), (x + 10, y - 8), (x + 10, y + 8), (x + 2, y + 8)], True, **_o(kw, w=2.2))
