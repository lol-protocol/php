#!/usr/bin/env python3
"""Catalogo abstracto de biblioteca: libros, generos, formatos, estados, acciones, atributos e interfaz."""
import math

from glyphs import GLYPHS, _arc_arrow
from lib import Block, Disc, Icon, RPoly, fmt, poly_path, star_points
from motifs import MOTIFS, _ngon

ICONS = []
CX, CY = 31.5, 28.5  # centro del contenido de una portada


def reg(ic):
    ICONS.append(ic)
    return ic


# ------------------------------------------------------------------ contenedores
def cover(ic, hue, x=15, y=8, w=29, h=43, d=4.2, depth='paper', r=3.5, spine=True):
    ic.solid(Block(x, y, w, h, r=r), hue, d=d, depth=depth)
    if spine:
        ic.line(x + 4.5, y + 5, x + 4.5, y + h - 5, w=2.4, op=0.35)
    return ic


def book(stem, hue, fn, **kw):
    ic = Icon(stem)
    cover(ic, hue)
    fn(ic, CX, CY, **kw)
    return reg(ic)


def badge(stem, hue, fn, **kw):
    ic = Icon(stem)
    ic.solid(Disc(31, 30, 19.5), hue, d=4)
    fn(ic, 31, 30, **kw)
    return reg(ic)


def tile(stem, hue, fn, **kw):
    ic = Icon(stem)
    ic.solid(Block(12, 10, 39, 39, r=9.5), hue, d=4)
    fn(ic, 31.5, 29.5, **kw)
    return reg(ic)


def hexagon(stem, hue, fn, **kw):
    ic = Icon(stem)
    ic.solid(RPoly(_ngon(31, 30, 21, 6, 0), r=4), hue, d=4)
    fn(ic, 31, 30, **kw)
    return reg(ic)


def g(name):
    return GLYPHS[name]


# ------------------------------------------------------------------ book_
ic = reg(Icon('book_standard'))
cover(ic, 'brown')
ic.ring(32.5, 28, 6)

ic = reg(Icon('book_open'))
ic.solid(Block(8, 24, 48, 27, r=4), 'brown', d=2.5)
ic.solid(Block(11, 13, 21, 32, r=2.5, rot=-3), 'paper', d=2)
ic.solid(Block(32, 13, 21, 32, r=2.5, rot=3), 'paper', d=2)
ic.line(32, 16, 32, 46, tone='brown', ink='brown', w=1.6)

ic = reg(Icon('book_ebook'))
ic.solid(Block(19, 7, 26, 45, r=4.5), 'night', d=3.6)
ic.solid(Block(23, 11.5, 18, 31, r=2), 'sky', d=0)
ic.dot(32, 48, 1.8)

ic = reg(Icon('book_audiobook'))
ic.solid(Block(10, 31, 10, 18, r=4.5), 'indigo', d=2.6)
ic.solid(Block(42, 31, 10, 18, r=4.5), 'indigo', d=2.6)
ic.arc(31, 33, 17.5, 195, 345, tone='indigo', ink='indigo', w=4.6)
ic.solid(Disc(31, 42, 6.5), 'amber', d=2.6)

ic = reg(Icon('book_hardcover'))
cover(ic, 'brown', d=6, spine=False)
ic.line(20.5, 11, 20.5, 48, w=2, op=0.45)
ic.poly([(24, 14), (40, 14), (40, 45), (24, 45)], True, w=2, op=0.55)
ic.ring(32, 29.5, 3.6)

ic = reg(Icon('book_paperback'))
cover(ic, 'teal', x=17, w=27, d=2.2, depth='paper', spine=False)
ic.mark('M21.5 11Q25 29.5 21.5 48', w=2, op=0.55)
ic.ring(33, 28, 5.5)

ic = reg(Icon('book_pocket'))
ic.poly([(10, 6), (40, 6), (40, 50), (10, 50)], True, tone='slate', ink='slate', w=2, dash='3 3.5')
ic.solid(Block(27, 25, 20, 27, r=3), 'orange', d=3.2, depth='paper')
ic.ring(37.5, 38, 3.8)

ic = reg(Icon('book_boxset'))
for x, hue in ((12, 'indigo'), (26, 'pink'), (40, 'amber')):
    ic.solid(Block(x, 8, 11, 38, r=2.5), hue, d=2)
ic.solid(Block(8, 28, 48, 24, r=4), 'brown', d=3.5, depth='paper')
ic.line(14, 40, 50, 40, w=2.4, op=0.45)

ic = reg(Icon('book_collection'))
for y, x, w, hue in ((38, 9, 46, 'brown'), (27, 12, 40, 'indigo'), (16, 10, 44, 'amber')):
    ic.solid(Block(x, y, w, 11, r=2.5), hue, d=3, depth='paper')
    ic.line(x + 5, y + 2.5, x + 5, y + 8.5, w=2, op=0.5)

ic = reg(Icon('book_manuscript'))
ic.solid(Block(15, 8, 30, 43, r=3), 'paper', d=3.2)
for y in (17, 23, 29):
    ic.line(21, y, 39, y, tone='brown', ink='brown', w=2, op=0.8)
ic.solid(RPoly([(29.5, 45), (26, 56), (31.5, 53), (35, 56), (33.5, 45)], r=1.2), 'red', d=0)
ic.solid(Disc(31.5, 43, 5.6), 'red', d=1.8)

ic = reg(Icon('book_ancient_tome'))
cover(ic, 'brown', d=6, spine=False)
ic.line(15, 29.5, 44, 29.5, w=5, op=0.5)
ic.ring(29.5, 29.5, 3.6, w=2.4)
for x, y in ((19.5, 13), (39.5, 13), (19.5, 46), (39.5, 46)):
    ic.dot(x, y, 1.5)

ic = reg(Icon('book_rare'))
cover(ic, 'violet')
ic.poly([(CX, 17), (CX + 9, 26), (CX, 40), (CX - 9, 26)], True, filled=True)
ic.line(CX - 9, 26, CX + 9, 26, tone='violet', ink='violet', w=1.6)
ic.spark(CX + 8, 15, 3.4)

ic = reg(Icon('book_notebook'))
cover(ic, 'green', spine=False)
for y in (14, 22, 30, 38, 46):
    ic.ring(15, y, 2.3, w=2)
ic.poly([(24, 16), (39, 16), (39, 27), (24, 27)], True, w=2)

ic = reg(Icon('book_bibliography'))
cover(ic, 'indigo')
g('link')(ic, CX, CY)

ic = reg(Icon('book_index'))
cover(ic, 'brown', w=27)
for y, hue in ((13, 'amber'), (24, 'pink'), (35, 'teal')):
    ic.solid(Block(40, y, 9, 8, r=2), hue, d=1.2)

ic = reg(Icon('book_binding'))
cover(ic, 'amber', spine=False)
ic.line(21.5, 10, 21.5, 49, w=2.4, dash='3 3.5')
ic.line(15, 14, 44, 14, w=2, op=0.5)
ic.line(15, 45, 44, 45, w=2, op=0.5)
ic.ring(33, 29, 4.5)

ic = reg(Icon('book_folded_page'))
ic.solid(RPoly([(16, 8), (34, 8), (46, 20), (46, 51), (16, 51)], r=3), 'paper', d=3.2)
ic.solid(RPoly([(34, 8), (46, 20), (34, 20)], r=1.5), 'amber', d=0)

ic = reg(Icon('book_digital'))
cover(ic, 'sky')
ic.mark(f'M{fmt(CX - 9)} {fmt(CY + 6)}a4.5 4.5 0 0 1 1 -9a6.5 6.5 0 0 1 12 -2a5 5 0 0 1 4.5 11Z', filled=True)

ic = reg(Icon('book_cover'))
ic.solid(Block(14, 9, 32, 42, r=3.5), 'brown', d=2.2)
ic.poly([(22, 17), (38, 17), (38, 32), (22, 32)], True, w=2.4)
ic.line(22, 40, 38, 40, w=2.4, op=0.7)

ic = reg(Icon('book_spine'))
ic.solid(Block(22, 8, 18, 43, r=3), 'brown', d=3.6, depth='paper')
ic.line(22.5, 15, 39.5, 15, w=2.2)
ic.line(22.5, 20, 39.5, 20, w=2.2)
ic.ring(31, 38, 3.8)

ic = reg(Icon('book_page'))
ic.solid(Block(14, 14, 28, 38, r=2.5), 'paper', d=2.4)
ic.solid(Block(21, 8, 28, 38, r=2.5), 'paper', d=2.4)

ic = reg(Icon('book_bookmark'))
cover(ic, 'brown')
ic.solid(RPoly([(34, 5), (42, 5), (42, 33), (38, 28), (34, 33)], r=1.2), 'red', d=1.6)

ic = reg(Icon('book_chapter'))
cover(ic, 'teal')
ic.poly([(CX - 9, CY - 7), (CX - 1, CY - 7), (CX - 1, CY), (CX + 8, CY), (CX + 8, CY + 7), (CX + 1, CY + 7)], w=2.6)


# ------------------------------------------------------------------ genre_
GENRE_HUE = {
    'fiction': 'indigo', 'non_fiction': 'ink', 'literary_fiction': 'brown', 'narrative': 'violet',
    'romance': 'pink', 'suspense': 'red', 'drama': 'rose', 'comedy': 'amber', 'scifi': 'violet',
    'fantasy': 'indigo', 'horror': 'night', 'mystery': 'ink', 'thriller': 'red', 'adventure': 'orange',
    'western': 'brown', 'history': 'amber', 'biography': 'blue', 'memoir': 'teal', 'essay': 'green',
    'essay_collection': 'teal', 'chronicle': 'brown', 'diary': 'pink', 'epistolary': 'sky',
    'epistle': 'indigo', 'treatise': 'ink', 'screenplay': 'night', 'poetry': 'rose', 'philosophy': 'violet',
    'psychology': 'teal', 'self_help': 'green', 'article': 'blue', 'anthology': 'orange',
    'children': 'orange', 'young_adult': 'pink', 'graphic_novel': 'indigo', 'manga': 'red',
    'cooking': 'amber', 'travel': 'sky', 'art': 'pink', 'science': 'teal', 'technology': 'blue',
    'mathematics': 'indigo', 'economics': 'green', 'law': 'brown', 'medicine': 'red', 'religion': 'amber',
    'sports': 'green', 'nature': 'green', 'education': 'blue', 'reference': 'ink', 'music': 'violet',
}
for name, hue in GENRE_HUE.items():
    book(f'genre_{name}', hue, MOTIFS[name])

# cuento infantil: luna creciente y destello
def _crescent(x, y, R=9.5, dx=4.2, dy=-3.2, r=8.0):
    """Luna creciente: circulo grande menos otro circulo desplazado (poligono muestreado)."""
    cx2, cy2 = x + dx, y + dy
    n = 90
    outer = [(x + R * math.cos(math.radians(i * 4)), y + R * math.sin(math.radians(i * 4))) for i in range(n)]
    out_in = [math.hypot(px - cx2, py - cy2) < r for px, py in outer]
    start = next(i for i in range(n) if not out_in[i] and out_in[i - 1])
    arc = []
    i = start
    while not out_in[i % n]:
        arc.append(outer[i % n])
        i += 1
    inner = [(cx2 + r * math.cos(math.radians(i * 4)), cy2 + r * math.sin(math.radians(i * 4))) for i in range(n)]
    inn = [(px, py) for px, py in inner if math.hypot(px - x, py - y) < R]
    if math.hypot(inn[0][0] - arc[-1][0], inn[0][1] - arc[-1][1]) > math.hypot(inn[-1][0] - arc[-1][0], inn[-1][1] - arc[-1][1]):
        inn.reverse()
    return poly_path(arc + inn)


ic = Icon('genre_children_story')
cover(ic, 'rose')
ic.mark(_crescent(CX - 1, CY + 1), filled=True)
ic.spark(CX + 8, CY - 6, 4.4)
reg(ic)

# ------------------------------------------------------------------ format_
ic = reg(Icon('format_luxury_edition'))
cover(ic, 'amber', spine=False)
ic.poly([(20, 13), (39, 13), (39, 46), (20, 46)], True, w=2, op=0.8)
ic.poly([(CX, 21), (CX + 5, 29), (CX, 37), (CX - 5, 29)], True, filled=True)

ic = reg(Icon('format_limited_edition'))
cover(ic, 'violet', spine=False)
ic.poly([(20, 13), (39, 13), (39, 46), (20, 46)], True, w=2, dash='0.1 4.4')
ic.spark(CX, CY, 8)

ic = reg(Icon('format_masterpiece'))
cover(ic, 'amber')
g('crown')(ic, CX, CY)

ic = reg(Icon('format_series'))
for x, h in ((9, 31), (24, 38), (39, 45)):
    ic.solid(Block(x, 51 - h, 13, h, r=2.5), 'brown', d=3.4, depth='paper')
    ic.line(x + 3.5, 51 - h + 6.5, x + 9.5, 51 - h + 6.5, w=2.2)

ic = reg(Icon('format_large_print'))
cover(ic, 'blue')
ic.mark(f'M{fmt(CX - 9)} {fmt(CY - 8)}h18M{fmt(CX - 9)} {fmt(CY + 1)}h18M{fmt(CX - 9)} {fmt(CY + 10)}h10', w=5.2)

ic = reg(Icon('format_braille'))
cover(ic, 'night', spine=False)
for i, (dx, dy) in enumerate(((-5, -9), (5, -9), (-5, 0), (5, 0), (-5, 9), (5, 9))):
    if i in (0, 3, 4):
        ic.dot(CX + dx, CY + dy, 3)
    else:
        ic.ring(CX + dx, CY + dy, 2.4, w=1.6, op=0.6)

ic = reg(Icon('format_audio_edition'))
cover(ic, 'indigo')
ic.poly([(CX - 9, CY - 3), (CX - 4, CY - 3), (CX + 1, CY - 8), (CX + 1, CY + 8), (CX - 4, CY + 3), (CX - 9, CY + 3)], True, filled=True)
ic.arc(CX + 1, CY, 6, -50, 50, w=2.2)
ic.arc(CX + 1, CY, 10, -50, 50, w=2.2)

# ------------------------------------------------------------------ status_
badge('status_available', 'green', g('check'))
badge('status_unavailable', 'red', g('cross'))
badge('status_borrowed', 'sky', g('arrow_out'))
badge('status_reserved', 'violet', g('bookmark'))
badge('status_new', 'amber', g('spark'))
badge('status_bestseller', 'orange', g('podium'))
badge('status_recommended', 'indigo', g('chevrons_up'))
badge('status_popular', 'rose', g('flame'))
badge('status_upcoming_release', 'teal', g('hourglass'))
badge('status_overdue', 'red', g('clock'))
badge('status_renewed', 'green', g('refresh'))
badge('status_returned', 'blue', g('arrow_in'))
badge('status_on_hold', 'amber', g('pause'))
badge('status_archived', 'ink', g('tray'))

ic = Icon('status_lost')
ic.solid(Disc(31, 30, 19.5), 'slate', d=4)
ic.ring(31, 30, 11, w=2.6, dash='3 3.5')
ic.cross(31, 30, 4.6, w=2.6)
reg(ic)

ic = Icon('status_damaged')
ic.solid(Disc(31, 30, 19.5), 'orange', d=4)
ic.poly([(24, 16), (33, 25), (27, 30), (37, 40), (35, 46)], w=3)
reg(ic)

ic = Icon('status_in_transit')
ic.solid(Disc(31, 30, 19.5), 'sky', d=4)
ic.mark('M17 30h3.5M23.5 30h3', w=2.8)
ic.arrow(29, 30, 45, 30, head=6, w=3)
reg(ic)

book('status_read', 'blue', lambda ic, x, y: ic.check(x, y, 9, w=3.4))


def _reading(ic, x, y):
    ic.line(x - 8, y + 12, x + 10, y + 12, w=4.2, op=0.4)
    ic.line(x - 8, y + 12, x + 1, y + 12, w=4.2, tone='#FBBF24')


book('status_reading', 'blue', _reading)
book('status_read_later', 'brown', lambda ic, x, y: g('bookmark')(ic, x, y))
book('status_quick_read', 'teal', lambda ic, x, y: g('fast')(ic, x, y))


def _long(ic, x, y):
    ic.mark(f'M{fmt(x - 9)} {fmt(y - 9)}h18M{fmt(x - 9)} {fmt(y - 3)}h18M{fmt(x - 9)} {fmt(y + 3)}h18M{fmt(x - 9)} {fmt(y + 9)}h10', w=2.4)


book('status_long_read', 'brown', _long)

# ------------------------------------------------------------------ action_
def _rate(ic, x, y):
    for dx in (-10, 0):
        ic.star(x + dx, y, 5.6)
    ic.mark(poly_path(star_points(x + 10, y, 5.6)), w=1.8)


tile('action_rate', 'amber', _rate)
tile('action_review', 'teal', g('speech'))
tile('action_share', 'green', g('share'))
tile('action_download_book', 'blue', g('download'))
tile('action_favorite', 'rose', g('heart'))
tile('action_all_connections', 'indigo', g('hub'))
tile('action_filter', 'ink', g('filter'))
tile('action_search_book', 'indigo', g('search'))
tile('action_sort', 'sky', g('sort'))
tile('action_borrow', 'sky', g('arrow_out'))
tile('action_return', 'blue', g('arrow_in'))
tile('action_renew', 'green', g('refresh'))
tile('action_reserve', 'violet', g('flag'))
tile('action_bookmark', 'orange', g('bookmark'))
tile('action_annotate', 'teal', g('edit'))
tile('action_scan', 'ink', g('barcode'))
tile('action_print', 'slate', g('print'))
tile('action_recommend', 'amber', g('sparkles'))
tile('action_subscribe', 'orange', g('bell'))
tile('action_preview', 'indigo', g('eye'))


def _shelf(ic, x, y, sign):
    ic.line(x - 10, y + 10, x + 10, y + 10, w=3)
    for dx, h in ((-8, 11), (-3, 15), (2, 9)):
        ic.poly([(x + dx, y + 8), (x + dx, y + 8 - h), (x + dx + 3.6, y + 8 - h), (x + dx + 3.6, y + 8)], True, filled=True)
    if sign > 0:
        ic.plus(x + 8, y - 4, 4.4, w=2.8)
    else:
        ic.line(x + 3.6, y - 4, x + 12.4, y - 4, w=2.8)


tile('action_add_to_shelf', 'green', lambda ic, x, y: _shelf(ic, x, y, 1))
tile('action_remove_from_shelf', 'red', lambda ic, x, y: _shelf(ic, x, y, -1))


def _highlight(ic, x, y):
    ic.line(x - 10, y, x + 10, y, w=9, tone='#FDE047', op=0.95)
    ic.line(x - 10, y - 9, x + 6, y - 9, w=2.4)
    ic.line(x - 10, y + 9, x + 10, y + 9, w=2.4)


tile('action_highlight', 'indigo', _highlight)


def _follow(ic, x, y):
    g('user')(ic, x - 3, y + 1)
    ic.plus(x + 9, y - 6, 4.4, w=2.8)


tile('action_follow', 'blue', _follow)

# ------------------------------------------------------------------ attr_
def _translation(ic, x, y):
    ic.arrow(x - 10, y - 5, x + 10, y - 5, head=5.4, w=2.8)
    ic.arrow(x + 10, y + 6, x - 10, y + 6, head=5.4, w=2.8)


hexagon('attr_translation', 'teal', _translation)
hexagon('attr_donation', 'pink', g('gift'))
hexagon('attr_critical_praise', 'amber', g('star'))
hexagon('attr_controversial', 'red', g('split'))
hexagon('attr_premium', 'violet', g('crown'))
hexagon('attr_public', 'blue', g('globe'))
hexagon('attr_private', 'ink', g('lock'))
hexagon('attr_multi_reader', 'green', g('users'))
hexagon('attr_illustrated', 'sky', g('image'))
hexagon('attr_first_edition', 'orange', g('sparkles'))
hexagon('attr_annotated', 'indigo', g('speech'))


def _award(ic, x, y):
    ic.poly([(x - 5, y + 3), (x - 9, y + 14), (x - 3, y + 11), (x - 1, y + 4)], True, filled=True)
    ic.poly([(x + 5, y + 3), (x + 9, y + 14), (x + 3, y + 11), (x + 1, y + 4)], True, filled=True)
    ic.dot(x, y - 3, 7.4)
    ic.ring(x, y - 3, 3.6, tone='orange', ink='orange', w=2)


hexagon('attr_award', 'orange', _award)


def _signed(ic, x, y):
    ic.mark(f'M{fmt(x - 10)} {fmt(y + 3)}C{fmt(x - 6)} {fmt(y - 9)} {fmt(x - 2)} {fmt(y + 8)} {fmt(x + 2)} {fmt(y - 1)}S{fmt(x + 8)} {fmt(y - 3)} {fmt(x + 10)} {fmt(y + 2)}', w=2.6)
    ic.line(x - 10, y + 9, x + 10, y + 9, w=2.4)


hexagon('attr_signed', 'brown', _signed)


def _bilingual(ic, x, y):
    ic.poly([(x - 10, y - 9), (x + 2, y - 9), (x + 2, y), (x - 4, y), (x - 8, y + 3), (x - 8, y), (x - 10, y)], True, w=2.2)
    ic.poly([(x - 2, y - 2), (x + 10, y - 2), (x + 10, y + 7), (x + 8, y + 7), (x + 8, y + 10), (x + 4, y + 7), (x - 2, y + 7)], True, filled=True, op=0.9)


hexagon('attr_bilingual', 'teal', _bilingual)

# ------------------------------------------------------------------ ui_
tile('ui_map', 'teal', g('map'))
tile('ui_reference', 'brown', g('grid'))
tile('ui_chart', 'blue', g('bars'))
tile('ui_notifications', 'amber', g('bell'))
tile('ui_music', 'violet', g('music'))
tile('ui_catalog', 'sky', g('list'))
tile('ui_calendar', 'red', g('calendar'))
tile('ui_settings', 'ink', g('sliders'))
tile('ui_menu', 'slate', g('menu'))
tile('ui_home', 'orange', g('house'))

ic = reg(Icon('ui_profile'))
ic.solid(Disc(31, 18, 9), 'sky', d=3)
ic.solid(Block(15, 31, 32, 21, r=10.5), 'blue', d=3)

ic = reg(Icon('ui_history'))
ic.solid(Disc(31, 30, 19.5), 'ink', d=4)
ic.ring(31, 30, 6.5, w=2.4)
ic.poly([(31, 26), (31, 30), (34, 32)], w=2.2)
_arc_arrow(ic, 31, 30, 12.5, 150, 330, w=2.4)

ic = reg(Icon('ui_languages'))
ic.solid(Disc(21, 25, 10.5), 'teal', d=3)
ic.solid(Block(33, 15, 20, 20, r=4), 'indigo', d=3)
ic.solid(RPoly([(32, 33), (45, 54), (19, 54)], r=2.5), 'pink', d=3)

ic = reg(Icon('ui_newspaper'))
ic.solid(Block(12, 8, 40, 45, r=3.5), 'paper', d=3.6, depth='slate')
ic.line(18, 17, 46, 17, tone='night', ink='night', w=4.6)
ic.solid(Block(18, 24, 13, 13, r=2), 'mist', d=0)
ic.bars(35, 26, [11, 11, 7], 5, tone='#94A3B8', ink='slate', w=2)
ic.bars(18, 44, [28, 20], 5, tone='#94A3B8', ink='slate', w=2)

ic = reg(Icon('ui_magazine'))
ic.solid(Block(14, 8, 32, 43, r=3.5), 'pink', d=4, depth='paper')
ic.line(20, 16, 40, 16, w=5)
ic.solid(Block(20, 24, 20, 14, r=2), 'mist', d=0)
ic.line(20, 44, 32, 44, w=2.4, op=0.8)

ic = reg(Icon('ui_collection'))
for dx, dy, hue in ((0, 0, 'indigo'), (6, 6, 'violet'), (12, 12, 'pink')):
    ic.solid(Block(10 + dx, 9 + dy, 28, 26, r=4), hue, d=2.4)


def _books_on_shelf(ic):
    ic.solid(Block(8, 41, 48, 6, r=2), 'brown', d=2.4)
    for x, y, w, h, hue in ((12, 18, 8, 23, 'indigo'), (21, 11, 8, 30, 'pink'), (30, 20, 8, 21, 'teal'), (39, 14, 9, 27, 'amber')):
        ic.solid(Block(x, y, w, h, r=2), hue, d=2, depth='paper')


ic = reg(Icon('ui_shelf'))
_books_on_shelf(ic)

ic = reg(Icon('ui_personal_library'))
_books_on_shelf(ic)
ic.solid(Disc(46, 14, 8.5), 'rose', d=2)
s = 0.55
ic.mark(f'M46 {fmt(14 + 8 * s)}C{fmt(46 - 12 * s)} {fmt(14 - s)} {fmt(46 - 6 * s)} {fmt(14 - 10 * s)} 46 {fmt(14 - 4 * s)}'
        f'C{fmt(46 + 6 * s)} {fmt(14 - 10 * s)} {fmt(46 + 12 * s)} {fmt(14 - s)} 46 {fmt(14 + 8 * s)}Z', filled=True)

ic = reg(Icon('ui_library_card'))
ic.solid(Block(8, 16, 48, 32, r=4.5), 'sky', d=3.4)
ic.ring(21, 32, 6.5, w=2.4)
ic.bars(32, 27, [16, 12, 8], 6, w=2.4)
