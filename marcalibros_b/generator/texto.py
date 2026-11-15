"""Texto como vectores: contornos de glifos de una fuente, sin depender de fuentes del visor.

La fuente latina (Liberation Serif Cursiva, licencia OFL) se vende en generator/fuentes/. Cada glifo
se convierte una sola vez a un contorno con 100 unidades por em (enteros, para que el SVG pese poco).
El SVG los define una vez y los reutiliza con <use>; el renderer raster rellena los mismos
contornos, así los dos salen iguales.

Las escrituras no latinas (japonés, chino, árabe/persa, devanagari) usan subconjuntos de fuentes Noto
(SIL OFL) y HarfBuzz para la composición (ligaduras árabes, conjuntos devanagari). Se declaran en
catalog/escrituras.yaml. Un glifo se identifica con su «clave»: el carácter, para el latín (las piezas
latinas no cambian), o (fuente, número de glifo) para las demás escrituras.
"""
from functools import lru_cache
from pathlib import Path

import yaml
from fontTools.pens.basePen import BasePen
from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont

CARPETA_FUENTES = Path(__file__).with_name("fuentes")
RUTA_FUENTE = CARPETA_FUENTES / "LiberationSerif-Italic.ttf"
RUTA_ESCRITURAS = Path(__file__).resolve().parent.parent / "catalog" / "escrituras.yaml"
UNIDADES = 100  # unidades por em de los contornos guardados


@lru_cache(maxsize=1)
def _fuente():
    f = TTFont(RUTA_FUENTE)
    return f, f.getGlyphSet(), f.getBestCmap(), f["hmtx"], f["head"].unitsPerEm


@lru_cache(maxsize=1)
def escrituras():
    """Registro de catalog/escrituras.yaml: fuentes, idiomas y muestras."""
    return yaml.safe_load(RUTA_ESCRITURAS.read_text(encoding="utf-8"))


def fuente_de_idioma(idioma):
    """Clave de la fuente no latina de un idioma, o None si se escribe con la fuente latina."""
    return escrituras()["idiomas"].get(idioma) if idioma else None


@lru_cache(maxsize=None)
def _extra(fuente):
    """Fuente no latina: (TTFont, glyphset, unidades/em, fuente HarfBuzz, datos del registro)."""
    import uharfbuzz as hb  # solo hace falta para escrituras no latinas

    datos = escrituras()["fuentes"][fuente]
    ruta = CARPETA_FUENTES / datos["archivo"]
    f = TTFont(ruta)
    hbf = hb.Font(hb.Face(hb.Blob.from_file_path(str(ruta))))
    return f, f.getGlyphSet(), f["head"].unitsPerEm, hbf, datos


def soporta(caracter, idioma=None):
    fuente = fuente_de_idioma(idioma)
    if fuente:
        return ord(caracter) in _extra(fuente)[0].getBestCmap()
    return ord(caracter) in _fuente()[2]


def id_glifo(clave):
    """Identificador SVG de un glifo: «g<hex>» para el latín (igual que siempre), «<fuente>-<n>» el resto."""
    return f"g{ord(clave):x}" if isinstance(clave, str) else f"{clave[0]}-{clave[1]}"


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


def _glifo(clave):
    """(glyphset, nombre del glifo, unidades/em) de una clave."""
    if isinstance(clave, str):
        _, gs, cmap, _, upm = _fuente()
        return gs, cmap[ord(clave)], upm
    f, gs, upm, _, _ = _extra(clave[0])
    return gs, f.getGlyphName(clave[1]), upm


@lru_cache(maxsize=None)
def trazo(clave):
    """Trazo SVG ('d', y hacia abajo, enteros) del glifo en unidades de 1/100 em."""
    gs, nombre, upm = _glifo(clave)
    s = UNIDADES / upm
    pen = SVGPathPen(gs, ntos=lambda v: str(round(v)))
    gs[nombre].draw(TransformPen(pen, (s, 0, 0, -s, 0, 0)))
    return pen.getCommands()


@lru_cache(maxsize=None)
def contornos(clave):
    """Contornos poligonales del glifo, en unidades de 1/100 em con y hacia abajo."""
    gs, nombre, upm = _glifo(clave)
    s = UNIDADES / upm
    pen = _Aplanador(gs)
    gs[nombre].draw(TransformPen(pen, (s, 0, 0, -s, 0, 0)))
    return [[(round(x), round(y)) for x, y in c] for c in pen.contornos]


@lru_cache(maxsize=4096)
def _componer_linea(texto, fuente):
    """Una línea de una escritura no latina, ya compuesta por HarfBuzz: ([(clave, dx, dy)], ancho)
    en unidades de 1/100 em; dx es la posición desde el borde izquierdo de la línea y dy el
    desplazamiento vertical (y hacia abajo). Los glifos vienen en orden visual (izquierda a derecha)."""
    import uharfbuzz as hb

    f, _, upm, hbf, datos = _extra(fuente)
    buf = hb.Buffer()
    buf.add_str(texto)
    buf.direction = "rtl" if datos["sentido"] == "rtl" else "ltr"
    buf.guess_segment_properties()
    hb.shape(hbf, buf, {})
    k = UNIDADES / upm
    x, glifos = 0.0, []
    for info, pos in zip(buf.glyph_infos, buf.glyph_positions):
        clave = (fuente, info.codepoint)
        if trazo(clave):  # los espacios no dibujan nada
            glifos.append((clave, round((x + pos.x_offset) * k, 2), round(-pos.y_offset * k, 2)))
        x += pos.x_advance
    return tuple(glifos), x * k


def _ancho_linea(texto, tam, fuente=None):
    if fuente:
        return _componer_linea(texto, fuente)[1] * tam / UNIDADES
    return sum(avance(c) for c in texto) * tam / UNIDADES


_NO_ABRE_LINEA = "。、，．」』）〕】！？；：,.;:!?)»”"  # signos que no pueden quedar al principio de una línea CJK


def _partir(texto, tam, ancho, fuente=None):
    """Reparte el texto en líneas sin pasar de `ancho` px: por palabras, o por caracteres en CJK."""
    por_caracter = bool(fuente) and escrituras()["fuentes"][fuente]["corte"] == "caracter"
    piezas = list(texto.replace(" ", "")) if por_caracter else texto.split()
    union = "" if por_caracter else " "
    lineas, actual = [], ""
    for pieza in piezas:
        prueba = (actual + union + pieza).strip() if union else actual + pieza
        if actual and _ancho_linea(prueba, tam, fuente) > ancho and not (por_caracter and pieza in _NO_ABRE_LINEA):
            lineas.append(actual)
            actual = pieza
        else:
            actual = prueba
    if actual:
        lineas.append(actual)
    return lineas


def _glifos_de_linea(linea, t, x0, ancho_caja, y, fuente):
    """Glifos de una línea centrada: [(clave, x, y_linea_base)] y su ancho en px."""
    ancho = _ancho_linea(linea, t, fuente)
    x = x0 + (ancho_caja - ancho) / 2
    if fuente:
        k = t / UNIDADES
        return [(c, round(x + dx * k, 1), round(y + dy * k, 1)) for c, dx, dy in _componer_linea(linea, fuente)[0]]
    glifos = []
    for c in linea:
        if c != " ":
            glifos.append((c, round(x, 1), round(y, 1)))
        x += avance(c) * t / UNIDADES
    return glifos


def componer(frase, autor, caja, tam=15.0, tam_autor=11.0, interlineado=1.32, idioma=None):
    """Compone la frase centrada y, debajo, «— Autor» más pequeño, dentro de la caja
    (x0, y0, x1, y1). Devuelve (bloques, alto_usado); cada bloque es
    {"tam", "glifos": [(clave, x, y_linea_base)]} con coordenadas en px. Si el `idioma` usa una
    escritura no latina, la frase se compone con su fuente; la autoría va siempre en la latina."""
    x0, y0, x1, y1 = caja
    ancho = x1 - x0 - 20
    fuente = fuente_de_idioma(idioma)
    bloques = []
    lineas = _partir(frase, tam, ancho, fuente)
    autores = _partir("— " + autor, tam_autor, ancho) if autor else []  # sin autoría: solo la frase
    alto = len(lineas) * tam * interlineado + (10 + len(autores) * tam_autor * interlineado if autores else 0)
    y = y0 + ((y1 - y0) - alto) / 2 + tam
    for grupo, t, fte in ((lineas, tam, fuente), (autores, tam_autor, None)):
        if not grupo:
            continue
        glifos = []
        for linea in grupo:
            glifos += _glifos_de_linea(linea, t, x0, x1 - x0, y, fte)
            y += t * interlineado
        bloques.append({"tam": t, "glifos": glifos})
        y += 10  # hueco entre la frase y la autoría
    return bloques, alto
