#!/usr/bin/env python3
"""Piezas comunes del estilo abstracto: volúmenes, discos, formas con patrón y composiciones de movimiento.

Ningún icono dibuja la silueta de un objeto: el significado lo llevan la forma, el relleno, el patrón
y la posición relativa de dos o tres piezas.
"""
import math

from lib import Block, Disc, Icon, RPoly, fmt

# --------------------------------------------------------------------------- catálogo de un módulo
def catalog():
    """(ICONS, reg, new) de un módulo: reg(ic) añade un icono ya hecho y new(stem) crea uno vacío y lo añade."""
    icons = []

    def reg(ic):
        icons.append(ic)
        return ic

    def new(stem):
        return reg(Icon(stem))

    return icons, reg, new


# --------------------------------------------------------------------------- géneros: forma x patrón
CX, CY = 30.5, 29.0
# la octava solo la usa la familia que tiene ocho géneros (rombo); las de siete no llegan a ella
PATTERNS = ('plain', 'stripes', 'dots', 'waves', 'ring', 'grid', 'split', 'chevron')
FAMILY_HUE = {'circle': 'pink', 'square': 'brown', 'triangle': 'red', 'hexagon': 'teal', 'diamond': 'violet', 'half': 'green'}

HALF_R = 15  # radio del semicírculo: con 13,5 el patrón no cabía y las rayas y la cruz se fundían con el contorno


def _geometry(outline, center, p, kx=1.0, pr=None):
    """(contorno o None, centro del patrón, radio libre del patrón, estiramiento horizontal, radio de los patrones redondos).

    El semicírculo es ancho y bajo: su patrón se estira a lo ancho (`kx`) y los patrones redondos (anillo, mitad) usan
    un radio propio (`pr`), porque rayas y cruz no caben más altas. A 24 px así se distinguen mejor.
    """
    return outline, center, p, kx, p if pr is None else pr


SHAPES = {
    'circle': _geometry(None, (CX, CY), 7.4),
    'square': _geometry([(CX - 11.5, CY - 11.5), (CX + 11.5, CY - 11.5), (CX + 11.5, CY + 11.5), (CX - 11.5, CY + 11.5)], (CX, CY), 7.4),
    'triangle': _geometry([(CX, CY - 13.5), (CX + 14, CY + 11), (CX - 14, CY + 11)], (CX, CY + 2.9), 6.6),
    'hexagon': _geometry([(CX + 13.5 * math.cos(math.radians(60 * k)), CY + 13.5 * math.sin(math.radians(60 * k))) for k in range(6)], (CX, CY), 7.8),
    'diamond': _geometry([(CX, CY - 15), (CX + 15, CY), (CX, CY + 15), (CX - 15, CY)], (CX, CY), 8.4),
    'half': _geometry(None, (CX, CY + 0.4), 5.2, kx=1.7, pr=6.4),
}


def _half_path():
    return f'M{fmt(CX - HALF_R)} {fmt(CY + HALF_R / 2)}A{HALF_R} {HALF_R} 0 0 1 {fmt(CX + HALF_R)} {fmt(CY + HALF_R / 2)}Z'


def _draw_shape(ic, shape, filled):
    pts = SHAPES[shape][0]
    if shape == 'circle':
        ic.dot(CX, CY, 13) if filled else ic.ring(CX, CY, 13, w=2.6)
    elif shape == 'half':
        ic.mark(_half_path(), filled=filled, w=2.6)
    else:
        ic.poly(pts, True, filled=filled, w=2.6)


def _draw_pattern(ic, pattern, px, py, p, kx, pr):
    if pattern == 'stripes':
        ic.mark(''.join(f'M{fmt(px - 0.72 * p * kx)} {fmt(py + dy * p)}h{fmt(1.44 * p * kx)}' for dy in (-0.66, 0, 0.66)), w=2.6)
    elif pattern == 'dots':
        for dx, dy in ((0, -0.66), (-0.66, 0.46), (0.66, 0.46)):
            ic.dot(px + dx * p * kx, py + dy * p, 2.3)
    elif pattern == 'waves':
        ic.mark(f'M{fmt(px - 0.9 * p * kx)} {fmt(py)}Q{fmt(px - 0.45 * p * kx)} {fmt(py - 0.9 * p)} {fmt(px)} {fmt(py)}'
                f'T{fmt(px + 0.9 * p * kx)} {fmt(py)}', w=2.6)
    elif pattern == 'ring':
        ic.ring(px, py, 0.62 * pr, w=2.8)
    elif pattern == 'grid':
        ic.mark(f'M{fmt(px - 0.8 * p * kx)} {fmt(py)}h{fmt(1.6 * p * kx)}M{fmt(px)} {fmt(py - 0.8 * p)}v{fmt(1.6 * p)}', w=2.6)
    elif pattern == 'chevron':
        ic.poly([(px - 0.75 * p * kx, py - 0.35 * p), (px, py + 0.4 * p), (px + 0.75 * p * kx, py - 0.35 * p)], w=2.8)
    elif pattern == 'split':
        ic.mark(f'M{fmt(px)} {fmt(py - 0.8 * pr)}A{fmt(0.8 * pr)} {fmt(0.8 * pr)} 0 0 0 {fmt(px)} {fmt(py + 0.8 * pr)}Z', filled=True)


def genre(stem, shape, pattern):
    """Un cubo con una forma blanca: la forma identifica la familia y el patrón al género dentro de ella."""
    ic = Icon(stem)
    ic.solid(Block(11, 9, 40, 40, r=9), FAMILY_HUE[shape], d=6)
    _draw_shape(ic, shape, pattern == 'plain')
    if pattern != 'plain':
        _, (px, py), p, kx, pr = SHAPES[shape]
        _draw_pattern(ic, pattern, px, py, p, kx, pr)
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
