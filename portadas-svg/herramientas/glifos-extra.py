#!/usr/bin/env python3
"""Saca de una fuente tuya los contornos de las letras que las fuentes del proyecto no tienen.

Es una herramienta de desarrollo. Las fuentes incluidas cubren el latín, el griego y el cirílico; para un título
en chino, japonés, hebreo u otro alfabeto no hay fuente. En vez de meter fuentes enormes en el repositorio, este
script toma una fuente que ya tengas (por ejemplo Noto Sans CJK, con licencia libre) y guarda solo los contornos de
las letras que tus libros usan. El generador los usa con `--trazos` y con ellos mide el texto:

    pip install fonttools brotli
    python3 herramientas/glifos-extra.py NotoSansTC-Regular.otf mis-glifos.json --texto "道德經"
    python3 herramientas/glifos-extra.py NotoSansTC-Regular.otf mis-glifos.json --lista libros.csv
    php bin/generar.php --lista=libros.csv --trazos --extra=carpeta/con/mis-glifos

`--lista` lee un CSV (o un JSON) de libros y toma las letras que faltan en los textos. `--extra` es la carpeta
donde está el .json; sin esa opción se busca en `fuentes/extra/`. Puedes dejar varios .json en la carpeta, uno
por alfabeto. El .json no se sube al repositorio si la licencia de tu fuente no lo permite.

No hace falta correrlo con texto en latín, griego o cirílico: de eso se encargan las fuentes del proyecto.

Hay alfabetos cuyas letras cambian de forma según las vecinas (árabe, devanagari) o se escriben de derecha a
izquierda (hebreo, árabe). Para esos, con `--formas`, en lugar de guardar letra a letra se guarda **cada palabra ya
compuesta** por HarfBuzz (el motor que usan los navegadores): sus letras en su sitio y con la forma que les toca.
El generador pone esas palabras tal cual (y las líneas escritas solo con palabras de derecha a izquierda las invierte):

    pip install fonttools brotli uharfbuzz
    python3 herramientas/glifos-extra.py NotoSansArabic-Regular.otf fuentes/extra/arabe.formas.json --formas --lista libros.csv

El archivo se llama `*.formas.json`. Una palabra que no esté en él no se dibuja, así que hay que volver a correr el
script cuando cambien los textos.
"""

import argparse
import csv
import io
import json
import re
import sys
import unicodedata
from pathlib import Path

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.ttLib import TTFont

RAIZ = Path(__file__).resolve().parent.parent
UNIDADES = 1000


def letras_de(origen: Path) -> str:
    """Todo el texto de un CSV o un JSON de libros."""
    if origen.suffix.lower() == ".json":
        return origen.read_text(encoding="utf-8")
    contenido = origen.read_text(encoding="utf-8-sig")
    # Cada celda va separada por un espacio: así una palabra no se pega con la de la celda de al lado.
    return " ".join(" ".join(fila) for fila in csv.reader(contenido.splitlines()))


def cubiertas() -> set[int]:
    """Las letras que ya tienen las fuentes del proyecto, según datos/anchos.json."""
    datos = json.loads((RAIZ / "datos" / "anchos.json").read_text(encoding="utf-8"))
    codigos: set[int] = set()
    for estilos in datos["caras"].values():
        for pesos in estilos.values():
            for cara in pesos.values():
                codigos |= {int(c) for c in cara["anchos"]}
    return codigos


def nucleo(palabra: str) -> str:
    """La palabra sin los signos de puntuación y los símbolos pegados al principio y al final («palabra», palabra.)."""
    def es_signo(c: str) -> bool:
        return unicodedata.category(c)[0] in "PS"
    i, j = 0, len(palabra)
    while i < j and es_signo(palabra[i]):
        i += 1
    while j > i and es_signo(palabra[j - 1]):
        j -= 1
    return palabra[i:j]


def formas(fuente: TTFont, palabras: list[str]) -> tuple[dict[str, list], list[str]]:
    """Cada palabra compuesta con HarfBuzz: [avance, derecha_a_izquierda, [[x, y, contorno], ...]], en milésimas de em.

    HarfBuzz no lee woff2: la fuente se pasa antes a un TrueType/OpenType normal. Las letras salen en el orden en que
    se ven de izquierda a derecha (HarfBuzz ya invierte las de derecha a izquierda).
    """
    import uharfbuzz as hb

    sin_empaquetar = io.BytesIO()
    copia = TTFont(io.BytesIO(_guardar(fuente)), recalcTimestamp=False)
    copia.flavor = None
    copia.save(sin_empaquetar)
    cara = hb.Face(hb.Blob(sin_empaquetar.getvalue()))
    letra = hb.Font(cara)
    por_em = fuente["head"].unitsPerEm
    escala = UNIDADES / por_em
    glifos = fuente.getGlyphSet()
    orden = fuente.getGlyphOrder()

    resultado: dict[str, list] = {}
    sin_forma = []
    for palabra in palabras:
        texto = hb.Buffer()
        texto.add_str(palabra)
        texto.guess_segment_properties()
        hb.shape(letra, texto, {"kern": True, "liga": True, "ccmp": True, "locl": True})
        x = 0
        piezas = []
        for info, pos in zip(texto.glyph_infos, texto.glyph_positions):
            if info.codepoint == 0:  # el glifo «.notdef»: la fuente no tiene la letra
                sin_forma.append(palabra)
                break
            lapiz = SVGPathPen(glifos, ntos=lambda v: str(round(v)))
            glifos[orden[info.codepoint]].draw(TransformPen(lapiz, (escala, 0, 0, -escala, 0, 0)))
            contorno = lapiz.getCommands()
            if contorno:
                piezas.append([round((x + pos.x_offset) * escala), round(-pos.y_offset * escala), contorno])
            x += pos.x_advance
        else:
            resultado[palabra] = [round(x * escala), 1 if texto.direction == "rtl" else 0, piezas]
    return resultado, sin_forma


def _guardar(fuente: TTFont) -> bytes:
    """La fuente en memoria, para volver a abrirla sin tocar la original."""
    memoria = io.BytesIO()
    fuente.save(memoria)
    return memoria.getvalue()


def main() -> int:
    ap = argparse.ArgumentParser(description="Saca de una fuente los contornos de las letras que faltan.")
    ap.add_argument("fuente", type=Path, help="archivo de fuente (.otf, .ttf, .woff2)")
    ap.add_argument("salida", type=Path, help="archivo .json que se escribe")
    ap.add_argument("--formas", action="store_true", help="guardar palabras compuestas con HarfBuzz, para árabe, hebreo, devanagari…")
    origen = ap.add_mutually_exclusive_group(required=True)
    origen.add_argument("--texto", help="las letras que hacen falta")
    origen.add_argument("--lista", type=Path, help="un CSV o JSON de libros del que se toman las letras")
    args = ap.parse_args()

    texto = args.texto if args.texto is not None else letras_de(args.lista)
    ya_estan = cubiertas()
    if args.formas:
        # Se compone el núcleo de cada palabra: el generador separa los signos que se le peguen.
        palabras = sorted({nucleo(p) for p in re.split(r"\s+", texto) if any(ord(c) >= 0x20 and ord(c) not in ya_estan for c in p)} - {""})
        fuente = TTFont(args.fuente, recalcTimestamp=False)
        cmap = fuente.getBestCmap()
        # Una palabra de otro alfabeto no es de esta fuente: se deja para la fuente que le toque.
        palabras = [p for p in palabras if any(ord(c) in cmap for c in p if not unicodedata.category(c)[0] in "PSZ")]
        resultado, sin_forma = formas(fuente, palabras)
        args.salida.write_text(json.dumps(resultado, separators=(",", ":"), ensure_ascii=False) + "\n", encoding="utf-8")
        print(f"{len(resultado)} palabras escritas en {args.salida} ({args.salida.stat().st_size} bytes)", file=sys.stderr)
        if sin_forma:
            print(f"la fuente no puede componer: {' '.join(sin_forma)}", file=sys.stderr)
        return 0

    pedidas = sorted({ord(c) for c in texto if ord(c) >= 0x20 and ord(c) not in ya_estan})

    fuente = TTFont(args.fuente, recalcTimestamp=False)
    cmap = fuente.getBestCmap()
    glifos = fuente.getGlyphSet()
    por_em = fuente["head"].unitsPerEm
    escala = UNIDADES / por_em
    resultado: dict[str, list] = {}
    faltan = []
    for codigo in pedidas:
        if codigo not in cmap:
            faltan.append(chr(codigo))
            continue
        nombre = cmap[codigo]
        lapiz = SVGPathPen(glifos, ntos=lambda v: str(round(v)))
        glifos[nombre].draw(TransformPen(lapiz, (escala, 0, 0, -escala, 0, 0)))
        resultado[str(codigo)] = [round(fuente["hmtx"][nombre][0] * escala), lapiz.getCommands()]

    args.salida.write_text(json.dumps(resultado, separators=(",", ":"), ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"{len(resultado)} letras escritas en {args.salida} ({args.salida.stat().st_size} bytes)", file=sys.stderr)
    if faltan:
        print(f"la fuente no tiene: {''.join(faltan)}", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
