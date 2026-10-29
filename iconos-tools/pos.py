#!/usr/bin/env python3
"""Catálogo abstracto del punto de venta: catálogo, pedidos, cuenta, carrito y pago, con la gramática de biblioteca y genealogía."""
import comunes
from kit import catalog, volume
from lib import Block, Disc, RPoly

ICONS, reg, new = catalog()


# --------------------------------------------------------------------------- catalog_: lo que se vende
# un producto: un volumen con la argolla de su etiqueta
ic = new('catalog_product')
volume(ic, 'amber', 11, 12, 40, 38, d=6, r=7)
ic.ring(40, 21, 3.2, w=2.4)
ic.line(18, 40, 34, 40, w=2.6, op=0.55)

# variantes: el mismo volumen en varios tonos, escalonados
ic = new('catalog_variants')
for x, y, hue in ((8, 28, 'amber'), (19, 18, 'teal'), (30, 8, 'rose')):
    ic.solid(Block(x, y, 24, 24, r=6), hue, d=3)

# atributos: cada fila es una muestra de color y su valor
ic = new('catalog_attributes')
for y, hue, w in ((13, 'teal', 26), (29, 'rose', 18), (45, 'amber', 22)):
    ic.solid(Disc(14, y, 6), hue, d=2.2)
    ic.solid(Block(26, y - 3.4, w, 6.8, r=3.4), 'mist', d=2)

# --------------------------------------------------------------------------- order_: lo que se compró
# pedidos: dos volúmenes sobre una bandeja
ic = new('order_history')
ic.solid(Block(13, 15, 17, 17, r=4), 'amber', d=3)
ic.solid(Block(34, 22, 17, 17, r=4), 'orange', d=3)
ic.solid(Block(7, 40, 50, 13, r=6.5), 'indigo', d=3)

# factura: un documento con filas de concepto e importe y la barra del total
ic = new('order_invoice')
ic.solid(Block(11, 7, 40, 46, r=7), 'teal', d=4)
ic.mark('M19 17H27M35 17H43M19 26H27M35 26H43', w=2.6)
ic.line(19, 40, 43, 40, w=4.4)
ic.solid(Disc(47, 49, 6.5), 'red', d=2)

# seguimiento: la ruta recorrida (trazo continuo), el paquete en su punto actual y el destino por llegar
ic = new('order_tracking')
ic.link([(11, 47), (11, 22), (35, 22)], w=2.6)
ic.link([(35, 22), (35, 47), (52, 47)], 'dotted', w=2.6)
ic.solid(Disc(11, 47, 4.6), 'green', d=2)
ic.solid(Disc(11, 22, 4.6), 'green', d=2)
ic.solid(Disc(35, 22, 7.4), 'orange', d=2.8)
ic.ring(52, 47, 5, tone='slate', ink='slate', w=2.4, dash='3 3.2')

# --------------------------------------------------------------------------- account_: lo que guarda el cliente
# deseos: un volumen aún no comprado, con la gema de lo deseado
ic = new('account_wishlist')
volume(ic, 'rose', 13, 10, 36, 38, d=6, r=7)
ic.poly([(31, 20), (40, 29), (31, 40), (22, 29)], True, filled=True)

# direcciones: dos marcadores, uno por lugar de entrega
ic = new('account_addresses')
ic.ring(17, 39, 7.5, tone='teal', ink='teal', w=3)
ic.solid(Disc(17, 25, 6), 'teal', d=2.2)
ic.ring(43, 39, 10, tone='orange', ink='orange', w=3.2)
ic.solid(Disc(43, 23, 8), 'orange', d=2.8)

# colección: varios productos juntos, dentro de un marco discontinuo
ic = new('catalog_collection')
ic.poly([(7, 9), (57, 9), (57, 55), (7, 55)], True, tone='slate', ink='slate', w=2.2, dash='3.5 3.8', behind=True)
for x, y, hue in ((13, 15, 'amber'), (34, 15, 'teal'), (13, 34, 'rose'), (34, 34, 'indigo')):
    ic.solid(Block(x, y, 17, 15, r=4), hue, d=2.4)

# etiqueta: un rótulo con su agujero
ic = new('catalog_tag')
ic.solid(RPoly([(8, 18), (36, 18), (56, 32), (36, 46), (8, 46)], r=3), 'teal', d=4)
ic.ring(19, 32, 3.4, w=2.8)

# grupo: productos que van juntos, tres discos que se tocan
ic = new('catalog_group')
ic.solid(Disc(22, 41, 12), 'amber', d=3)
ic.solid(Disc(42, 41, 12), 'rose', d=3)
ic.solid(Disc(32, 23, 12), 'teal', d=3)

# devolución: el paquete y la flecha de checkout_shipping, en sentido contrario
ic = new('order_return')
volume(ic, 'amber', 30, 16, 28, 30, d=5, r=5)
ic.arrow(24, 31, 7, 31, head=5, tone='orange', ink='orange', w=3.6)
ic.mark('M15 22h8M15 40h8', tone='orange', ink='orange', w=2.6, op=0.6)


# --------------------------------------------------------------------------- cart_: el carrito
def _basket(ic, small, items):
    """La cesta (un trapecio) con los productos que asoman por arriba; small deja sitio a la insignia de una acción."""
    for x, y, w, h, hue in items:
        ic.solid(Block(x, y, w, h, r=3), hue, d=2)
    top, bottom, left, right = (22, 44, 6, 46) if small else (26, 52, 7, 57)
    ic.solid(RPoly([(left, top), (right, top), (right - 5.5, bottom), (left + 5.5, bottom)], r=4), 'sky', d=4)


def _badge(ic, hue, symbol):
    ic.solid(Disc(47, 46, 8.6), hue, d=2.4)
    symbol(47, 46)


ITEMS = ((14, 12, 12, 18, 'amber'), (28, 8, 12, 22, 'rose'), (41, 14, 10, 16, 'teal'))
# la acción se lee también por lo que hay en la cesta: uno más al añadir, uno solo al quitar
ADDED_ITEMS = ((8, 10, 10, 16, 'amber'), (20, 6, 10, 20, 'rose'), (32, 11, 9, 15, 'teal'))
SMALL_ITEMS = ((11, 9, 11, 17, 'amber'), (25, 6, 11, 20, 'rose'))
LAST_ITEM = ((18, 8, 11, 18, 'rose'),)

ic = new('cart_items')
_basket(ic, False, ITEMS)

ic = new('cart_add')
_basket(ic, True, ADDED_ITEMS)
_badge(ic, 'green', lambda x, y: ic.plus(x, y, 4.2, w=2.8))

ic = new('cart_remove')
_basket(ic, True, LAST_ITEM)
_badge(ic, 'red', lambda x, y: ic.line(x - 4.6, y, x + 4.6, y, w=3))

ic = new('cart_update')
_basket(ic, True, SMALL_ITEMS)
_badge(ic, 'amber', lambda x, y: [ic.arc(x, y, 4.3, a0, a0 + 130, w=2.2) for a0 in (-150, 30)])

# vaciar: la cesta sin nada y los contornos discontinuos de lo que había
ic = new('cart_clear')
_basket(ic, False, ())
for x, y, w, h in ((14, 12, 12, 18), (28, 8, 12, 22), (41, 14, 10, 16)):
    ic.poly([(x, y), (x + w, y), (x + w, y + h - 3), (x, y + h - 3)], True, tone='sky', ink='sky', w=2, dash='2.6 3')


# --------------------------------------------------------------------------- checkout_: el pago
# envío: el paquete y su flecha
ic = new('checkout_shipping')
volume(ic, 'amber', 7, 16, 28, 30, d=5, r=5)
ic.arrow(40, 31, 57, 31, head=5, tone='sky', ink='sky', w=3.6)
ic.mark('M41 22h8M41 40h8', tone='sky', ink='sky', w=2.6, op=0.6)

# pago: una tarjeta con su banda y su chip
ic = new('checkout_payment')
ic.solid(Block(6, 15, 52, 34, r=6), 'indigo', d=4)
ic.solid(Block(12, 34, 13, 9, r=2.5), 'amber', d=0)
ic.mark('M10 26H54', w=6)

# confirmar: el pedido con la insignia verde de visto bueno
ic = new('checkout_confirm')
volume(ic, 'amber', 9, 10, 36, 36, d=5, r=6)
ic.solid(Disc(45, 43, 11), 'green', d=2.6)
ic.check(45, 43, 5.5, w=3.4)

# los que se llaman igual en otro set, definidos una vez en comunes.py
ICONS.extend(comunes.for_set('pos'))
