"""Texto como vectores: contornos de glifos de una fuente, sin depender de fuentes del visor.

La fuente (Liberation Serif Cursiva, licencia OFL) se vende en generator/fuentes/. Cada glifo se
convierte una sola vez a un contorno con 100 unidades por em (enteros, para que el SVG pese poco).
El SVG los define una vez y los reutiliza con <use>; el renderer raster rellena los mismos
contornos, así los dos salen iguales.
"""
from functools import lru_cache
from pathlib import Path

from fontTools.pens.basePen import BasePen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont

RUTA_FUENTE = Path(__file__).with_name("fuentes") / "LiberationSerif-Italic.ttf"
UNIDADES = 100  # unidades por em de los contornos guardados


@lru_cache(maxsize=1)
def _fuente():
    f = TTFont(RUTA_FUENTE)
    return f, f.getGlyphSet(), f.getBestCmap(), f["hmtx"], f["head"].unitsPerEm


def soporta(caracter):
    return ord(caracter) in _fuente()[2]


def avance(caracter):
    """Avance horizontal del glifo en unidades de 1/100 em."""
    f, _, cmap, hmtx, upm = _fuente()
    return hmtx[cmap[ord(caracter)]][0] * UNIDADES / upm


class _Aplanador(BasePen):
    """Convierte un glifo en contornos poligonales (curvas aplanadas en 6 tramos)."""

    def __init__(self, glyph_set):
        super().__init__(glyph_set)
        self.contornos, self._actual = [], None

    def _moveTo(self, p):
        self._actual = [p]

    def _lineTo(self, p):
        self._actual.append(p)

    def _curveToOne(self, p1, p2, p3):
        p0 = self._actual[-1]
        for k in range(1, 7):
            t = k / 6
            a, b, c, d = (1 - t) ** 3, 3 * (1 - t) ** 2 * t, 3 * (1 - t) * t * t, t ** 3
            self._actual.append((a * p0[0] + b * p1[0] + c * p2[0] + d * p3[0], a * p0[1] + b * p1[1] + c * p2[1] + d * p3[1]))

    def _qCurveToOne(self, p1, p2):
        p0 = self._actual[-1]
        for k in range(1, 7):
            t = k / 6
            a, b, c = (1 - t) ** 2, 2 * (1 - t) * t, t * t
            self._actual.append((a * p0[0] + b * p1[0] + c * p2[0], a * p0[1] + b * p1[1] + c * p2[1]))

    def _closePath(self):
        if self._actual:
            self.contornos.append(self._actual)
        self._actual = None

    _endPath = _closePath


@lru_cache(maxsize=None)
def trazo(caracter):
    """Trazo SVG ('d', y hacia abajo, enteros) del glifo en unidades de 1/100 em."""
    f, gs, cmap, _, upm = _fuente()
    s = UNIDADES / upm
    pen = SVGPathPen(gs, ntos=lambda v: str(round(v)))
    gs[cmap[ord(caracter)]].draw(TransformPen(pen, (s, 0, 0, -s, 0, 0)))
    return pen.getCommands()


@lru_cache(maxsize=None)
def contornos(caracter):
    """Contornos poligonales del glifo, en unidades de 1/100 em con y hacia abajo."""
    f, gs, cmap, _, upm = _fuente()
    s = UNIDADES / upm
    pen = _Aplanador(gs)
    gs[cmap[ord(caracter)]].draw(TransformPen(pen, (s, 0, 0, -s, 0, 0)))
    return [[(round(x), round(y)) for x, y in c] for c in pen.contornos]


def _ancho_linea(texto, tam):
    return sum(avance(c) for c in texto) * tam / UNIDADES


def _partir(texto, tam, ancho):
    """Reparte el texto en líneas por palabras, sin pasar de `ancho` px."""
    lineas, actual = [], ""
    for palabra in texto.split():
        prueba = (actual + " " + palabra).strip()
        if actual and _ancho_linea(prueba, tam) > ancho:
            lineas.append(actual)
            actual = palabra
        else:
            actual = prueba
    if actual:
        lineas.append(actual)
    return lineas


def componer(frase, autor, caja, tam=15.0, tam_autor=11.0, interlineado=1.32):
    """Compone la frase centrada y, debajo, «— Autor» más pequeño, dentro de la caja
    (x0, y0, x1, y1). Devuelve (bloques, alto_usado); cada bloque es
    {"tam", "glifos": [(caracter, x, y_linea_base)]} con coordenadas en px."""
    x0, y0, x1, y1 = caja
    ancho = x1 - x0 - 20
    bloques = []
    lineas = _partir(frase, tam, ancho)
    autores = _partir("— " + autor, tam_autor, ancho) if autor else []  # sin autoría: solo la frase
    alto = len(lineas) * tam * interlineado + (10 + len(autores) * tam_autor * interlineado if autores else 0)
    y = y0 + ((y1 - y0) - alto) / 2 + tam
    for grupo, t in ((lineas, tam), (autores, tam_autor)):
        if not grupo:
            continue
        glifos = []
        for linea in grupo:
            x = x0 + ((x1 - x0) - _ancho_linea(linea, t)) / 2
            for c in linea:
                if c != " ":
                    glifos.append((c, round(x, 1), round(y, 1)))
                x += avance(c) * t / UNIDADES
            y += t * interlineado
        bloques.append({"tam": t, "glifos": glifos})
        y += 10  # hueco entre la frase y la autoría
    return bloques, alto
