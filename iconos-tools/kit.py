#!/usr/bin/env python3
"""Piezas comunes del estilo abstracto: volúmenes, discos, formas con patrón y composiciones de movimiento.

Ningún icono dibuja la silueta de un objeto: el significado lo llevan la forma, el relleno, el patrón
y la posición relativa de dos o tres piezas.
"""
import math

from lib import Block, Disc, Icon, RPoly, fmt

# --------------------------------------------------------------------------- géneros: forma x patrón
CX, CY = 30.5, 29.0
PATTERNS = ('plain', 'stripes', 'dots', 'waves', 'ring', 'grid', 'split')
FAMILY_HUE = {'circle': 'pink', 'square': 'brown', 'triangle': 'red', 'hexagon': 'teal', 'diamond': 'violet', 'half': 'green'}

# geometría de cada forma: (contorno o None, centro del patrón, radio libre para el patrón)
SHAPES = {
    'circle': (None, (CX, CY), 7.4),
    'square': ([(CX - 11.5, CY - 11.5), (CX + 11.5, CY - 11.5), (CX + 11.5, CY + 11.5), (CX - 11.5, CY + 11.5)], (CX, CY), 7.4),
    'triangle': ([(CX, CY - 13.5), (CX + 14, CY + 11), (CX - 14, CY + 11)], (CX, CY + 2.9), 6.6),
    'hexagon': ([(CX + 13.5 * math.cos(math.radians(60 * k)), CY + 13.5 * math.sin(math.radians(60 * k))) for k in range(6)], (CX, CY), 7.8),
    'diamond': ([(CX, CY - 15), (CX + 15, CY), (CX, CY + 15), (CX - 15, CY)], (CX, CY), 8.4),
    'half': (None, (CX, CY + 0.2), 5.8),
}


def _half_path():
    return f'M{fmt(CX - 13.5)} {fmt(CY + 6.75)}A13.5 13.5 0 0 1 {fmt(CX + 13.5)} {fmt(CY + 6.75)}Z'


def _draw_shape(ic, shape, filled):
    pts = SHAPES[shape][0]
    if shape == 'circle':
        ic.dot(CX, CY, 13) if filled else ic.ring(CX, CY, 13, w=2.6)
    elif shape == 'half':
        ic.mark(_half_path(), filled=filled, w=2.6)
    else:
        ic.poly(pts, True, filled=filled, w=2.6)


def _draw_pattern(ic, pattern, px, py, p):
    if pattern == 'stripes':
        ic.mark(''.join(f'M{fmt(px - 0.72 * p)} {fmt(py + dy * p)}h{fmt(1.44 * p)}' for dy in (-0.66, 0, 0.66)), w=2.6)
    elif pattern == 'dots':
        for dx, dy in ((0, -0.66), (-0.66, 0.46), (0.66, 0.46)):
            ic.dot(px + dx * p, py + dy * p, 2.3)
    elif pattern == 'waves':
        ic.mark(f'M{fmt(px - 0.9 * p)} {fmt(py)}Q{fmt(px - 0.45 * p)} {fmt(py - 0.9 * p)} {fmt(px)} {fmt(py)}'
                f'T{fmt(px + 0.9 * p)} {fmt(py)}', w=2.6)
    elif pattern == 'ring':
        ic.ring(px, py, 0.62 * p, w=2.8)
    elif pattern == 'grid':
        ic.mark(f'M{fmt(px - 0.8 * p)} {fmt(py)}h{fmt(1.6 * p)}M{fmt(px)} {fmt(py - 0.8 * p)}v{fmt(1.6 * p)}', w=2.6)
    elif pattern == 'split':
        ic.mark(f'M{fmt(px)} {fmt(py - 0.8 * p)}A{fmt(0.8 * p)} {fmt(0.8 * p)} 0 0 0 {fmt(px)} {fmt(py + 0.8 * p)}Z', filled=True)


def genre(stem, shape, pattern):
    """Un cubo con una forma blanca: la forma identifica la familia y el patrón al género dentro de ella."""
    ic = Icon(stem)
    ic.solid(Block(11, 9, 40, 40, r=9), FAMILY_HUE[shape], d=6)
    _draw_shape(ic, shape, pattern == 'plain')
    if pattern != 'plain':
        _, (px, py), p = SHAPES[shape]
        _draw_pattern(ic, pattern, px, py, p)
    return ic


# --------------------------------------------------------------------------- piezas sueltas
def volume(ic, hue, x=15, y=10, w=32, h=38, d=7, r=6, rot=0):
    return ic.solid(Block(x, y, w, h, r=r, rot=rot), hue, d=d)


def badge(stem, hue, indicator):
    """Disco con un indicador blanco: el estado se lee por el relleno (lleno, mitad, anillo, punto...)."""
    ic = Icon(stem)
    ic.solid(Disc(31, 30, 19.5), hue, d=4)
    indicator(ic)
    return ic


def hexagon(stem, hue, fn):
    ic = Icon(stem)
    ic.solid(RPoly([(31 + 21 * math.cos(math.radians(60 * k)), 30 + 21 * math.sin(math.radians(60 * k))) for k in range(6)], r=4), hue, d=4)
    fn(ic, 31, 30)
    return ic


def tile(stem, hue, fn):
    ic = Icon(stem)
    ic.solid(Block(12, 10, 39, 39, r=9.5), hue, d=4)
    fn(ic, 31.5, 29.5)
    return ic
