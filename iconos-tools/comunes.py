"""Los iconos que llevan el mismo nombre en más de un set: se definen una vez, aquí, y cada set incluye los suyos.

Si cada set los dibujara por su cuenta acabarían distintos (ya pasó con ui_profile y action_filter), y quien use
varios sets a la vez esperaría el mismo dibujo. USADO_EN dice en qué sets va cada uno y for_set(set) da los de un
set; validate.py comprueba que cualquier nombre que esté en dos sets sea idéntico en ambos.
"""
from kit import catalog, tile
from lib import Block, Disc, fmt

ICONS, reg, new = catalog()

# clave de cada set (la de su módulo: genealogia, biblioteca, pos) en la que va cada icono
USADO_EN = {
    'ui_calendar': ('genealogia', 'biblioteca'),
    'ui_settings': ('genealogia', 'biblioteca', 'pos'),
    'ui_menu': ('genealogia', 'biblioteca'),
    'ui_profile': ('genealogia', 'biblioteca', 'pos'),
    'action_filter': ('genealogia', 'biblioteca'),
    'action_print': ('genealogia', 'biblioteca'),
    'action_share': ('genealogia', 'biblioteca'),
    'action_search': ('genealogia', 'pos'),
}

reg(tile('ui_calendar', 'red', lambda ic, x, y: [ic.dot(x - 10 + 10 * (i % 3), y - 9.5 + 9.5 * (i // 3), 2, **({'tone': '#FDE047'} if i == 4 else {})) for i in range(9)]))


def _sliders(ic, x, y):
    for dy, kx in ((-8, -4), (0, 4), (8, -1)):
        yy = y + dy
        ic.mark(f'M{fmt(x - 12.5)} {fmt(yy)}H{fmt(x + kx - 3.6)}M{fmt(x + kx + 3.6)} {fmt(yy)}H{fmt(x + 12.5)}', w=2)
        ic.ring(x + kx, yy, 2.4, w=2)


reg(tile('ui_settings', 'ink', _sliders))
reg(tile('ui_menu', 'slate', lambda ic, x, y: ic.mark(f'M{fmt(x - 9.5)} {fmt(y - 7.5)}h19M{fmt(x - 9.5)} {fmt(y)}h19M{fmt(x - 9.5)} {fmt(y + 7.5)}h19', w=3)))

# el yo del árbol genealógico: un disco grande con un punto (sin figura humana, como pide el estilo). Sus medidas son
# propias (radio 16,5 y punto de 5,1): no siguen las de los nodos de genealogia.py, que son más pequeños
ic = new('ui_profile')
ic.solid(Disc(31, 30, 16.5), 'ink', d=4)
ic.dot(31, 30, 5.1)

ic = new('action_filter')
for x, y, w in ((8, 11, 46), (15, 25, 32), (22, 39, 18)):
    ic.solid(Block(x, y, w, 9, r=4.5), 'ink', d=2.4)

ic = new('action_print')
for dx, dy, op, d in ((10, 10, 0.35, 0), (5, 5, 0.6, 0), (0, 0, 1.0, 3.5)):
    ic.solid(Block(14 + dx, 14 + dy, 26, 26, r=6), 'slate', d=d, op=op)

ic = new('action_share')
for x, y in ((47, 14), (51, 31), (47, 48)):
    ic.link([(15, 31), (x, y)], w=2.2)
ic.solid(Disc(15, 31, 8), 'green', d=3)
for x, y in ((47, 14), (51, 31), (47, 48)):
    ic.solid(Disc(x, y, 4.6), 'green', d=2)

ic = new('action_search')
ic.solid(Disc(43, 43, 5.5), 'indigo', d=2.4)
ic.ring(27, 26, 12, tone='indigo', ink='indigo', w=4.6)


def for_set(name):
    """Los iconos compartidos que lleva el set `name`."""
    missing = sorted({i.stem for i in ICONS} ^ set(USADO_EN))
    if missing:
        raise SystemExit(f'comunes.py: USADO_EN y los iconos definidos no coinciden en {missing}')
    return [i for i in ICONS if name in USADO_EN[i.stem]]
