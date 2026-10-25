#!/usr/bin/env python3
"""Catálogo abstracto del punto de venta: catálogo, pedidos y cuenta del cliente, con la gramática de biblioteca y genealogía."""
from kit import volume
from lib import Block, Disc, Icon

ICONS = []


def reg(ic):
    ICONS.append(ic)
    return ic


def new(stem):
    return reg(Icon(stem))


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
ic.ring(17, 45, 7.5, tone='teal', ink='teal', w=3)
ic.solid(Disc(17, 31, 6), 'teal', d=2.2)
ic.ring(43, 45, 10, tone='orange', ink='orange', w=3.2)
ic.solid(Disc(43, 29, 8), 'orange', d=2.8)
