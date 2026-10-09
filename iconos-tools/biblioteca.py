#!/usr/bin/env python3
"""Catálogo abstracto de biblioteca: volúmenes, géneros por forma y patrón, estados por relleno, acciones por movimiento."""
from kit import PATTERNS, badge, genre, hexagon, volume
from lib import Block, Disc, Icon, RPoly, fmt

ICONS = []


def reg(ic):
    ICONS.append(ic)
    return ic


def new(stem):
    return reg(Icon(stem))


# --------------------------------------------------------------------------- book_: volúmenes neutros
ic = new('book_standard')
volume(ic, 'brown')
ic.line(21.5, 16, 21.5, 42, w=2.4, op=0.4)

ic = new('book_open')
volume(ic, 'brown', 8, 16, 24, 30, d=3, rot=-9)
volume(ic, 'brown', 31, 16, 24, 30, d=3, rot=9)

ic = new('book_collection')
for x, y, hue in ((10, 35, 'brown'), (14, 24, 'indigo'), (18, 13, 'amber')):
    volume(ic, hue, x, y, 34, 12, d=3, r=5)

ic = new('book_ebook')
volume(ic, 'night', 19, 8, 26, 46, d=3.6, r=5)
ic.solid(Block(23, 13, 18, 32, r=2.5), 'sky', d=0)

ic = new('book_audiobook')
volume(ic, 'brown', 10, 14, 28, 34, d=5)
ic.arc(38, 31, 9, -55, 55, tone='indigo', ink='indigo', w=3.2)
ic.arc(38, 31, 16, -55, 55, tone='indigo', ink='indigo', w=3.2)

ic = new('book_hardcover')
volume(ic, 'brown', d=10)
ic.poly([(21, 16), (41, 16), (41, 42), (21, 42)], True, w=2, op=0.55)

ic = new('book_paperback')
volume(ic, 'teal', 17, 10, 28, 38, d=2)
ic.mark('M22 14Q26 29 22 44', w=2, op=0.55)

ic = new('book_pocket')
ic.poly([(10, 6), (40, 6), (40, 50), (10, 50)], True, tone='slate', ink='slate', w=2, dash='3 3.5')
volume(ic, 'orange', 26, 25, 20, 26, d=3.2, r=4)

ic = new('book_boxset')
for x, hue in ((12, 'indigo'), (26, 'pink'), (40, 'amber')):
    ic.solid(Block(x, 8, 11, 38, r=2.5), hue, d=2)
volume(ic, 'brown', 8, 28, 48, 24, d=3.5, r=4)

ic = new('book_manuscript')
ic.solid(Block(15, 10, 32, 38, r=4), 'paper', d=3.2)
ic.solid(Disc(39, 41, 6), 'red', d=1.8)

ic = new('book_ancient_tome')
volume(ic, 'brown', d=10)
ic.line(15, 29, 47, 29, w=5, op=0.5)
ic.ring(31, 29, 3.6, w=2.4)

ic = new('book_rare')
volume(ic, 'violet')
ic.poly([(31, 19), (40, 28), (31, 40), (22, 28)], True, filled=True)

ic = new('book_notebook')
volume(ic, 'green')
for y in (15, 23, 31, 39):
    ic.ring(17, y, 2.3, w=2)

ic = new('book_bibliography')
volume(ic, 'indigo')
ic.ring(27, 29, 6.5, w=2.4)
ic.ring(35, 29, 6.5, w=2.4)

ic = new('book_index')
volume(ic, 'brown', 15, 10, 28, 38)
for y, hue in ((14, 'amber'), (25, 'pink'), (36, 'teal')):
    ic.solid(Block(41, y, 9, 8, r=2), hue, d=1.2)

ic = new('book_binding')
volume(ic, 'amber')
ic.line(22, 14, 22, 44, w=2.4, dash='3 3.5')
ic.line(15, 18, 47, 18, w=2, op=0.5)
ic.line(15, 40, 47, 40, w=2, op=0.5)

ic = new('book_folded_page')
ic.solid(RPoly([(16, 8), (34, 8), (46, 20), (46, 51), (16, 51)], r=3), 'paper', d=3.2)
ic.solid(RPoly([(34, 8), (46, 20), (34, 20)], r=1.5), 'amber', d=0)

ic = new('book_digital')
volume(ic, 'sky')
for dx in (-7, 0, 7):
    for dy in (-7, 0, 7):
        ic.dot(31 + dx, 29 + dy, 1.9)

ic = new('book_cover')
ic.solid(Block(14, 9, 32, 42, r=3.5), 'brown', d=2.2)
ic.poly([(22, 17), (38, 17), (38, 32), (22, 32)], True, w=2.4)

ic = new('book_spine')
volume(ic, 'brown', 22, 8, 18, 43, d=5, r=3)
ic.line(22.5, 15, 39.5, 15, w=2.2)
ic.line(22.5, 20, 39.5, 20, w=2.2)

ic = new('book_page')
ic.solid(Block(14, 14, 28, 38, r=2.5), 'paper', d=2.4)
ic.solid(Block(21, 8, 28, 38, r=2.5), 'paper', d=2.4)

ic = new('book_bookmark')
volume(ic, 'brown')
ic.solid(RPoly([(34, 5), (42, 5), (42, 33), (38, 28), (34, 33)], r=1.2), 'red', d=1.6)

ic = new('book_chapter')
volume(ic, 'teal')
ic.poly([(22, 22), (30, 22), (30, 29), (40, 29), (40, 36), (32, 36)], w=2.6)

# --------------------------------------------------------------------------- genre_: 6 formas x 7 patrones
GENRES = {
    'circle': ('romance', 'drama', 'comedy', 'poetry', 'memoir', 'biography', 'diary'),
    'square': ('history', 'essay', 'reference', 'article', 'treatise', 'philosophy', 'chronicle'),
    'triangle': ('suspense', 'thriller', 'mystery', 'adventure', 'horror', 'western', 'manga'),
    'hexagon': ('science', 'technology', 'scifi', 'mathematics', 'economics', 'medicine', 'education'),
    'diamond': ('fantasy', 'art', 'music', 'religion', 'screenplay', 'graphic_novel', 'literary_fiction'),
    'half': ('cooking', 'travel', 'sports', 'nature', 'self_help', 'children', 'young_adult'),
}
for shape, names in GENRES.items():
    for pattern, name in zip(PATTERNS, names):
        reg(genre(f'genre_{name}', shape, pattern))

# --------------------------------------------------------------------------- format_
ic = new('format_luxury_edition')
volume(ic, 'amber', d=8)
ic.poly([(20, 16), (42, 16), (42, 42), (20, 42)], True, w=2, op=0.8)
ic.poly([(31, 23), (36, 29), (31, 35), (26, 29)], True, filled=True)

ic = new('format_limited_edition')
volume(ic, 'violet', d=8)
ic.poly([(20, 16), (42, 16), (42, 42), (20, 42)], True, w=2, dash='0.1 4.4')
ic.dot(31, 29, 4)

ic = new('format_masterpiece')
volume(ic, 'amber', d=8)
ic.ring(31, 29, 9, w=2.6)
ic.ring(31, 29, 3.4, w=2.4)

ic = new('format_series')
for x, h in ((9, 31), (24, 38), (39, 45)):
    volume(ic, 'brown', x, 51 - h, 13, h, d=4, r=2.5)
    ic.line(x + 3.5, 51 - h + 6.5, x + 9.5, 51 - h + 6.5, w=2.2)

ic = new('format_large_print')
volume(ic, 'blue')
ic.mark('M22 19h18M22 28h18M22 37h10', w=5.2)

ic = new('format_braille')
volume(ic, 'night')
for i, (dx, dy) in enumerate(((-5, -9), (5, -9), (-5, 0), (5, 0), (-5, 9), (5, 9))):
    if i in (0, 3, 4):
        ic.dot(31 + dx, 29 + dy, 3)
    else:
        ic.ring(31 + dx, 29 + dy, 2.4, w=1.6, op=0.6)

ic = new('format_audio_edition')
volume(ic, 'indigo', 10, 14, 28, 34, d=5)
ic.arc(38, 31, 9, -55, 55, tone='indigo', ink='indigo', w=3.2)
ic.arc(38, 31, 16, -55, 55, tone='indigo', ink='indigo', w=3.2)

# --------------------------------------------------------------------------- status_: el estado se lee por el relleno
for stem, hue, fn in (
    ('available', 'green', lambda ic: ic.dot(31, 30, 11.5)),
    ('unavailable', 'red', lambda ic: ic.dot(31, 30, 4.2)),
    ('borrowed', 'sky', lambda ic: ic.mark('M31 18.5A11.5 11.5 0 0 0 31 41.5Z', filled=True)),
    ('returned', 'blue', lambda ic: ic.mark('M31 18.5A11.5 11.5 0 0 1 31 41.5Z', filled=True)),
    ('reserved', 'violet', lambda ic: ic.ring(31, 30, 10.5, w=3.4)),
    ('overdue', 'orange', lambda ic: ic.arc(31, 30, 10.5, -60, 240, w=3.4)),
    ('renewed', 'green', lambda ic: (ic.arc(31, 30, 11, -45, 225, w=3.2), ic.dot(31, 30, 3.4))),
    ('new', 'amber', lambda ic: (ic.ring(31, 30, 11, w=3), ic.dot(31, 30, 4))),
    ('bestseller', 'orange', lambda ic: ic.mark('M22 41V35M31 41V24M40 41V30', w=5.4)),
    ('recommended', 'indigo', lambda ic: ic.poly([(31, 19), (42, 39), (20, 39)], True, filled=True)),
    ('popular', 'rose', lambda ic: [ic.dot(31 + dx, 30 + dy, 3.6) for dx, dy in ((0, -8), (-8, 6), (8, 6))]),
    ('upcoming_release', 'teal', lambda ic: (ic.arc(31, 34, 11, 180, 360, w=3.4), ic.dot(31, 38, 3.2))),
    ('on_hold', 'amber', lambda ic: ic.mark('M25 21V39M37 21V39', w=5.2)),
    ('archived', 'ink', lambda ic: ic.mark('M19.5 30A11.5 11.5 0 0 1 42.5 30Z', filled=True)),
    ('lost', 'slate', lambda ic: ic.ring(31, 30, 11, w=3, dash='3.2 3.6')),
    ('damaged', 'orange', lambda ic: ic.mark('M22.9 38.1A11.5 11.5 0 0 1 39.1 21.9Z', filled=True)),
    ('in_transit', 'sky', lambda ic: [ic.dot(x, 30, r) for x, r in ((19.5, 2.2), (29, 3.2), (41, 4.4))]),
):
    reg(badge(f'status_{stem}', hue, fn))


def reading(stem, hue, fn):
    ic = new(f'status_{stem}')
    volume(ic, hue)
    fn(ic)


reading('read', 'blue', lambda ic: ic.line(21, 42, 41, 42, w=4.2))
reading('reading', 'blue', lambda ic: (ic.line(21, 42, 41, 42, w=4.2, op=0.4), ic.line(21, 42, 31, 42, w=4.2)))
reading('read_later', 'brown', lambda ic: (ic.line(21, 42, 41, 42, w=4.2, op=0.4), ic.dot(21, 30, 3)))
reading('quick_read', 'teal', lambda ic: ic.line(21, 42, 28, 42, w=4.2))
reading('long_read', 'brown', lambda ic: ic.line(21, 42, 41, 42, w=4.2, dash='4 3'))

# --------------------------------------------------------------------------- action_: formas en movimiento
ic = new('action_rate')
for x in (14, 31):
    ic.solid(Disc(x, 30, 8), 'amber', d=3)
ic.ring(48, 30, 7.5, tone='amber', ink='amber', w=3.4)

ic = new('action_review')
ic.solid(Disc(24, 26, 14), 'teal', d=3.5)
ic.solid(Disc(45, 42, 8), 'sky', d=2.6)

ic = new('action_share')
for x, y in ((47, 14), (51, 31), (47, 48)):
    ic.link([(15, 31), (x, y)], w=2.2)
ic.solid(Disc(15, 31, 8), 'green', d=3)
for x, y in ((47, 14), (51, 31), (47, 48)):
    ic.solid(Disc(x, y, 4.6), 'green', d=2)

ic = new('action_download_book')
ic.solid(Block(23, 6, 18, 22, r=4), 'brown', d=3)
ic.mark('M31 33v4M24 35v3M38 35v3', tone='brown', ink='brown', w=2.8)
ic.solid(Block(11, 44, 42, 8, r=4), 'ink', d=3)

ic = new('action_favorite')
ic.ring(31, 30, 15, tone='rose', ink='rose', w=4)
ic.solid(Disc(31, 22, 5), 'rose', d=2.2)

ic = new('action_all_connections')
sats = ((12, 12), (50, 12), (12, 48), (50, 48))
for x, y in sats:
    ic.link([(31, 30), (x, y)], w=2.2)
ic.solid(Disc(31, 30, 7), 'indigo', d=3)
for x, y in sats:
    ic.solid(Disc(x, y, 4), 'indigo', d=2)

ic = new('action_filter')
for x, y, w in ((10, 14, 44), (18, 27, 28), (25, 40, 14)):
    ic.solid(Block(x, y, w, 8, r=4), 'ink', d=2.4)

ic = new('action_search_book')
ic.solid(Disc(43, 43, 5.5), 'indigo', d=2.4)
ic.ring(27, 26, 12, tone='indigo', ink='indigo', w=4.6)

ic = new('action_sort')
ic.solid(Disc(17, 30, 12), 'indigo', d=3.2)
ic.solid(Disc(40, 30, 7.5), 'indigo', d=2.6)
ic.solid(Disc(54, 30, 4), 'indigo', d=2)

ic = new('action_borrow')
ic.poly([(20, 14), (10, 14), (10, 46), (20, 46)], w=4, tone='sky', ink='sky')
ic.line(24, 30, 32, 30, w=2.6, tone='sky', ink='sky', dash='2.5 3')
ic.solid(Disc(44, 30, 9), 'sky', d=3)

ic = new('action_return')
ic.poly([(44, 14), (54, 14), (54, 46), (44, 46)], w=4, tone='blue', ink='blue')
ic.line(32, 30, 40, 30, w=2.6, tone='blue', ink='blue', dash='2.5 3')
ic.solid(Disc(20, 30, 9), 'blue', d=3)

ic = new('action_renew')
ic.arc(31, 30, 14, 200, 340, tone='green', ink='green', w=5.4)
ic.arc(31, 30, 14, 20, 160, tone='green', ink='green', w=5.4)

ic = new('action_reserve')
ic.solid(Disc(31, 30, 10), 'violet', d=3)
ic.ring(31, 30, 19, tone='violet', ink='violet', w=3, dash='4 4')

ic = new('action_bookmark')
ic.solid(Block(23, 8, 18, 44, r=5), 'orange', d=4)
ic.dot(32, 18, 3.2)

ic = new('action_annotate')
ic.link([(48, 14), (36, 28)], w=2.2)
ic.solid(Block(8, 22, 36, 28, r=6), 'teal', d=3)
ic.solid(Disc(48, 14, 6), 'teal', d=2)

ic = new('action_scan')
ic.solid(Block(14, 12, 36, 38, r=6), 'ink', d=4)
ic.line(6, 31, 58, 31, w=4, tone='amber', ink='amber')

ic = new('action_print')
ic.solid(Block(24, 24, 26, 26, r=6), 'slate', d=0, op=0.35)
ic.solid(Block(19, 19, 26, 26, r=6), 'slate', d=0, op=0.6)
ic.solid(Block(14, 14, 26, 26, r=6), 'slate', d=3.5)

ic = new('action_recommend')
ic.solid(RPoly([(31, 6), (41, 21), (21, 21)], r=2), 'amber', d=2.2)
ic.solid(Disc(31, 39, 11), 'amber', d=3)

ic = new('action_subscribe')
ic.solid(Disc(31, 31, 8), 'orange', d=3)
ic.ring(31, 31, 16, tone='orange', ink='orange', w=3.4, op=0.8)

ic = new('action_preview')
ic.solid(Disc(40, 26, 14), 'indigo', d=3)
ic.solid(Block(8, 28, 32, 26, r=6), 'mist', d=3, op=0.85)

ic = new('action_add_to_shelf')
ic.solid(Block(8, 44, 48, 5, r=2.5), 'brown', d=2)
for x, y, h, hue in ((12, 22, 22, 'indigo'), (22, 17, 27, 'pink'), (32, 24, 20, 'teal')):
    ic.solid(Block(x, y, 8, h, r=2), hue, d=2)
ic.mark('M48 22v4', tone='green', ink='green', w=2.6)
ic.solid(Disc(48, 14, 6), 'green', d=2)

ic = new('action_remove_from_shelf')
ic.solid(Block(8, 44, 48, 5, r=2.5), 'brown', d=2)
for x, y, h, hue in ((12, 22, 22, 'indigo'), (22, 17, 27, 'pink'), (32, 24, 20, 'teal')):
    ic.solid(Block(x, y, 8, h, r=2), hue, d=2)
ic.mark('M48 30v-5', tone='red', ink='red', w=2.6, dash='2 3')
ic.solid(Disc(48, 16, 6), 'red', d=2)

ic = new('action_highlight')
ic.solid(Block(8, 12, 34, 5, r=2.5), 'ink', d=1.6)
ic.solid(Block(8, 24, 48, 12, r=6), 'amber', d=0, op=0.9)
ic.solid(Block(8, 43, 48, 5, r=2.5), 'ink', d=1.6)

ic = new('action_follow')
ic.ring(26, 32, 19, tone='blue', ink='blue', w=2.4, dash='3 4')
ic.solid(Disc(26, 32, 11), 'blue', d=3.2)
ic.solid(Disc(44, 20, 5), 'blue', d=2)

# --------------------------------------------------------------------------- attr_: hexágonos con composiciones
def attr(name, hue, fn):
    reg(hexagon(f'attr_{name}', hue, fn))


attr('translation', 'teal', lambda ic, x, y: (ic.ring(x - 5, y, 8, w=2.6), ic.ring(x + 5, y, 8, w=2.6, dash='3 3')))
attr('donation', 'pink', lambda ic, x, y: (ic.dot(x - 7, y + 4, 5.5), ic.mark(f'M{fmt(x - 3)} {fmt(y - 2)}Q{fmt(x + 2)} {fmt(y - 10)} {fmt(x + 8)} {fmt(y - 6)}', w=2.2, dash='0.1 4'), ic.ring(x + 9, y - 6, 3, w=2.2)))
attr('critical_praise', 'amber', lambda ic, x, y: (ic.dot(x, y, 5.5), ic.mark(''.join(f'M{fmt(x + 8.5 * c)} {fmt(y + 8.5 * s)}L{fmt(x + 12 * c)} {fmt(y + 12 * s)}' for c, s in ((1, 0), (-1, 0), (0, 1), (0, -1))), w=2.6)))
attr('controversial', 'red', lambda ic, x, y: (ic.mark(f'M{fmt(x - 2.5)} {fmt(y - 10)}A10 10 0 0 0 {fmt(x - 2.5)} {fmt(y + 10)}Z', filled=True), ic.mark(f'M{fmt(x + 2.5)} {fmt(y - 10)}A10 10 0 0 1 {fmt(x + 2.5)} {fmt(y + 10)}Z', w=2.4)))
attr('premium', 'violet', lambda ic, x, y: (ic.poly([(x, y - 11), (x + 9, y), (x, y + 11), (x - 9, y)], True, filled=True), ic.ring(x, y, 3.2, tone='violet', ink='violet', w=2)))
attr('public', 'blue', lambda ic, x, y: (ic.ring(x, y, 10, w=2.2), [ic.dot(x + dx, y + dy, 2.6) for dx, dy in ((0, -10), (8.7, 5), (-8.7, 5))]))
attr('private', 'ink', lambda ic, x, y: (ic.ring(x, y, 11, w=2.6), ic.dot(x, y, 5)))
attr('multi_reader', 'green', lambda ic, x, y: [ic.dot(x + dx, y, 6, op=0.9) for dx in (-8, 0, 8)])
attr('illustrated', 'sky', lambda ic, x, y: (ic.poly([(x - 10, y - 9), (x + 10, y - 9), (x + 10, y + 9), (x - 10, y + 9)], True, w=2.2), ic.poly([(x - 7, y + 6), (x - 2, y - 1), (x + 3, y + 6)], True, filled=True), ic.dot(x + 5, y - 4, 2.2)))
attr('first_edition', 'orange', lambda ic, x, y: (ic.ring(x, y + 2, 9, w=2.6), ic.dot(x - 6.5, y - 6, 3)))
attr('annotated', 'indigo', lambda ic, x, y: (ic.poly([(x - 10, y - 9), (x + 4, y - 9), (x + 4, y + 9), (x - 10, y + 9)], True, w=2.2), ic.dot(x + 9, y - 4, 2.2), ic.dot(x + 9, y + 3, 2.2)))
attr('award', 'orange', lambda ic, x, y: (ic.ring(x, y - 3, 8, w=2.8), ic.dot(x, y - 3, 3.2), ic.ring(x, y + 9, 3, w=2.4)))
attr('signed', 'brown', lambda ic, x, y: (ic.mark(f'M{fmt(x - 10)} {fmt(y + 3)}C{fmt(x - 6)} {fmt(y - 9)} {fmt(x - 2)} {fmt(y + 8)} {fmt(x + 2)} {fmt(y - 1)}S{fmt(x + 8)} {fmt(y - 3)} {fmt(x + 10)} {fmt(y + 2)}', w=2.6), ic.line(x - 10, y + 9, x + 10, y + 9, w=2.4)))
attr('bilingual', 'teal', lambda ic, x, y: (ic.dot(x - 4, y - 3, 8, op=0.9), ic.ring(x + 5, y + 4, 6, w=2.4)))

# --------------------------------------------------------------------------- ui_
ic = new('ui_map')
ic.solid(Block(12, 10, 39, 39, r=9.5), 'teal', d=4)
ic.poly([(18, 40), (28, 24), (38, 36), (46, 19)], w=2.4, dash='3 3.4')
for x, y in ((18, 40), (28, 24), (38, 36), (46, 19)):
    ic.dot(x, y, 2.6)

ic = new('ui_reference')
for x, y in ((11, 11), (32, 11), (11, 32), (32, 32)):
    ic.solid(Block(x, y, 19, 19, r=4), 'brown', d=2.6)

ic = new('ui_chart')
for x, y, h in ((10, 34, 16), (25, 24, 26), (40, 12, 38)):
    ic.solid(Block(x, y, 12, h, r=3), 'blue', d=2.6)

ic = new('ui_profile')
ic.solid(Disc(31, 18, 9), 'sky', d=3)
ic.solid(Block(15, 31, 32, 21, r=10.5), 'blue', d=3)

ic = new('ui_history')
ic.arc(31, 30, 16, -60, 215, tone='ink', ink='ink', w=4.4)
ic.solid(Disc(31, 30, 5), 'ink', d=2)
ic.solid(Disc(40.5, 14.4, 3.4), 'ink', d=1.4)

ic = new('ui_notifications')
ic.solid(Disc(31, 31, 6), 'amber', d=2.4)
ic.ring(31, 31, 13, tone='amber', ink='amber', w=3, op=0.85)
ic.ring(31, 31, 20, tone='amber', ink='amber', w=3, op=0.55)
ic.ring(31, 31, 27, tone='amber', ink='amber', w=3, op=0.3)

ic = new('ui_collection')
for dx, dy, hue in ((0, 0, 'indigo'), (6, 6, 'violet'), (12, 12, 'pink')):
    ic.solid(Block(10 + dx, 9 + dy, 28, 26, r=4), hue, d=2.4)

ic = new('ui_languages')
ic.solid(Disc(21, 25, 10.5), 'teal', d=3)
ic.solid(Block(33, 15, 20, 20, r=4), 'indigo', d=3)
ic.solid(RPoly([(32, 33), (45, 54), (19, 54)], r=2.5), 'pink', d=3)

ic = new('ui_music')
ic.solid(Block(12, 10, 39, 39, r=9.5), 'violet', d=4)
ic.mark(f'M18 24Q24.5 16 31 24T44 24', w=2.6)
ic.mark(f'M18 35Q24.5 27 31 35T44 35', w=2.6, op=0.7)

ic = new('ui_magazine')
volume(ic, 'pink', 14, 8, 32, 43, d=4)
ic.line(20, 16, 40, 16, w=5)
ic.solid(Block(20, 24, 20, 14, r=2), 'mist', d=0)

ic = new('ui_newspaper')
ic.solid(Block(12, 8, 40, 45, r=3.5), 'paper', d=3.6, depth='slate')
ic.line(18, 17, 46, 17, tone='night', ink='night', w=4.6)
ic.solid(Block(18, 24, 13, 13, r=2), 'mist', d=0)
ic.bars(35, 26, [11, 11, 7], 5, tone='#94A3B8', ink='slate', w=2)
ic.bars(18, 44, [28, 20], 5, tone='#94A3B8', ink='slate', w=2)


def _shelf(ic):
    ic.solid(Block(8, 41, 48, 6, r=2), 'brown', d=2.4)
    for x, y, w, h, hue in ((12, 18, 8, 23, 'indigo'), (21, 11, 8, 30, 'pink'), (30, 20, 8, 21, 'teal'), (39, 14, 9, 27, 'amber')):
        ic.solid(Block(x, y, w, h, r=2), hue, d=2)


ic = new('ui_shelf')
_shelf(ic)

ic = new('ui_personal_library')
_shelf(ic)
ic.solid(Disc(46, 14, 8.5), 'rose', d=2)

ic = new('ui_catalog')
ic.solid(Block(12, 10, 39, 39, r=9.5), 'sky', d=4)
for y in (21, 30, 39):
    ic.dot(21, y, 1.9)
    ic.line(27, y, 42, y, w=2.4)

ic = new('ui_library_card')
ic.solid(Block(8, 16, 48, 32, r=4.5), 'sky', d=3.4)
ic.ring(21, 32, 6.5, w=2.4)
ic.bars(32, 27, [16, 12, 8], 6, w=2.4)

ic = new('ui_calendar')
ic.solid(Block(12, 10, 39, 39, r=9.5), 'red', d=4)
for i in range(9):
    ic.dot(21.5 + 10 * (i % 3), 20 + 9.5 * (i // 3), 2, **({'tone': '#FDE047'} if i == 4 else {}))

ic = new('ui_settings')
ic.solid(Block(12, 10, 39, 39, r=9.5), 'ink', d=4)
for dy, kx in ((-8, -4), (0, 4), (8, -1)):
    y = 29.5 + dy
    ic.mark(f'M19 {fmt(y)}H{fmt(31.5 + kx - 3.6)}M{fmt(31.5 + kx + 3.6)} {fmt(y)}H44', w=2)
    ic.ring(31.5 + kx, y, 2.4, w=2)

ic = new('ui_menu')
ic.solid(Block(12, 10, 39, 39, r=9.5), 'slate', d=4)
ic.mark('M22 22h19M22 29.5h19M22 37h19', w=3)

ic = new('ui_home')
ic.solid(Block(12, 10, 39, 39, r=9.5), 'orange', d=4)
ic.mark('M23 41V31A8.5 8.5 0 0 1 40 31V41', w=2.8)
ic.dot(31.5, 36, 2.4)
