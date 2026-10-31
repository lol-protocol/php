#!/usr/bin/env python3
"""Prepara las fuentes que se incrustan en los SVG y mide sus letras.

Es una herramienta de desarrollo. El generador en PHP no la necesita para correr: solo lee lo que esta produce,

  fuentes/*.woff2        las fuentes cortadas en trozos por alfabeto (un archivo por cara y trozo),
  fuentes/trazos/*.json.gz  el contorno de cada letra de cada trozo (comprimido), para la salida con el texto a trazos,
  datos/fuentes.json     qué letras trae cada trozo (un `unicode-range` por archivo),
  datos/anchos.json      el ancho de cada letra, para medir el texto sin un navegador.

Hay que volver a correrla solo si cambian las fuentes o los pesos que usan las plantillas.

    pip install fonttools brotli
    npm pack @fontsource-variable/source-serif-4@5.3.1 @fontsource-variable/inter@5.3.0
    # se extraen los dos .tgz y se pasa la carpeta con sus `files/` juntos, o una que los reúna
    python3 herramientas/preparar-fuentes.py carpeta/con/los/woff2

Las fuentes de partida son variables en el eje de peso (wght) y vienen cortadas por alfabeto. De cada una se
fija el peso que usan las plantillas (queda una fuente estática, mucho más ligera) y el trozo latino se
parte en dos: las letras básicas (U+0020–007E), que casi todo texto usa, y el resto. Cada pieza de la
cubierta incrusta solo los trozos que sus textos necesitan.

No se tiene en cuenta el kerning (el ajuste entre pares de letras) al medir: casi siempre acorta una línea
uno o dos por ciento, así que la medida sale igual o algo mayor que la de un navegador, no menor.
"""

import gzip
import json
import sys
from pathlib import Path

from fontTools.pens.svgPathPen import SVGPathPen
from fontTools.pens.transformPen import TransformPen
from fontTools.subset import Options, Subsetter
from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

RAIZ = Path(__file__).resolve().parent.parent

# familia -> prefijo de los archivos de origen, y las caras (estilo, peso) que usan las plantillas
FAMILIAS = {
    "serif": {"prefijo": "source-serif-4", "caras": [("normal", 400), ("italic", 400)]},
    "sans": {"prefijo": "inter", "caras": [("normal", 400), ("normal", 700)]},
}

# Trozos, en orden de prioridad: una letra va solo en el primero que la tiene. «latin» se parte en dos.
TROZOS = [
    ("basico", "latin"),
    ("latin", "latin"),
    ("latin-ext", "latin-ext"),
    ("vietnamita", "vietnamese"),
    ("griego", "greek"),
    ("griego-ext", "greek-ext"),
    ("cirilico", "cyrillic"),
    ("cirilico-ext", "cyrillic-ext"),
]

UNIDADES = 1000
BASICO = range(0x20, 0x7F)


def rangos(codigos: list[int]) -> str:
    """Códigos como un `unicode-range` de CSS: U+20-7E,U+A0."""
    partes = []
    ordenados = sorted(codigos)
    inicio = anterior = ordenados[0]
    for codigo in ordenados[1:] + [None]:
        if codigo is not None and codigo == anterior + 1:
            anterior = codigo
            continue
        partes.append(f"U+{inicio:X}" if inicio == anterior else f"U+{inicio:X}-{anterior:X}")
        if codigo is not None:
            inicio = anterior = codigo
    return ",".join(partes)


def contornos(fuente: TTFont, codigos: list[int], cmap: dict[int, str], por_em: int) -> dict[str, list]:
    """Por código Unicode: [avance, contorno SVG], en milésimas de em, con la y hacia abajo y la línea base en 0.

    Los números van redondeados a enteros: a 20 mm de tamaño de letra, una milésima son 0,02 mm.
    """
    glifos = fuente.getGlyphSet()
    hmtx = fuente["hmtx"]
    escala = UNIDADES / por_em
    resultado: dict[str, list] = {}
    for codigo in codigos:
        if codigo not in cmap:
            continue
        nombre = cmap[codigo]
        lapiz = SVGPathPen(glifos, ntos=lambda v: str(round(v)))
        glifos[nombre].draw(TransformPen(lapiz, (escala, 0, 0, -escala, 0, 0)))
        resultado[str(codigo)] = [round(hmtx[nombre][0] * escala), lapiz.getCommands()]
    return resultado


def main() -> int:
    if len(sys.argv) != 2:
        print(__doc__, file=sys.stderr)
        return 2
    origen = Path(sys.argv[1])
    (RAIZ / "fuentes").mkdir(exist_ok=True)
    (RAIZ / "datos").mkdir(exist_ok=True)
    (RAIZ / "fuentes" / "trazos").mkdir(exist_ok=True)
    for viejo in [*(RAIZ / "fuentes").glob("*.woff2"), *(RAIZ / "fuentes" / "trazos").glob("*.json.gz")]:
        viejo.unlink()

    indice: dict = {}
    anchos: dict = {"unidades": UNIDADES, "caras": {}}
    for familia, datos in FAMILIAS.items():
        indice[familia] = {}
        anchos["caras"][familia] = {}
        for estilo, peso in datos["caras"]:
            usados: set[int] = set()
            tabla: dict[str, int] = {}
            lista = []
            for trozo, subconjunto in TROZOS:
                ruta = origen / f"{datos['prefijo']}-{subconjunto}-wght-{estilo}.woff2"
                if not ruta.exists():
                    print(f"falta {ruta.name}: se salta {familia} {estilo} {peso} {trozo}", file=sys.stderr)
                    continue
                fuente = TTFont(ruta, recalcTimestamp=False)
                fija = instantiateVariableFont(fuente, {"wght": peso}, inplace=False)
                disponibles = {c for c in fija.getBestCmap() if c >= 0x20 and c not in usados}
                if trozo == "basico":
                    disponibles &= set(BASICO)
                elif trozo == "latin":
                    disponibles -= set(BASICO)
                if not disponibles:
                    continue

                opciones = Options()
                opciones.flavor = "woff2"
                opciones.hinting = False
                opciones.desubroutinize = True
                opciones.notdef_outline = False
                opciones.name_IDs = [1, 2]
                opciones.layout_features = ["kern", "liga", "ccmp", "locl", "mark", "mkmk"]
                subsetter = Subsetter(opciones)
                subsetter.populate(unicodes=sorted(disponibles))
                subsetter.subset(fija)
                nombre = f"{familia}-{estilo}-{peso}-{trozo}.woff2"
                cmap = fija.getBestCmap()
                hmtx = fija["hmtx"]
                por_em = fija["head"].unitsPerEm
                # mtime=0: el mismo contenido da siempre los mismos bytes
                with gzip.GzipFile(RAIZ / "fuentes" / "trazos" / nombre.replace(".woff2", ".json.gz"), "wb", compresslevel=9, mtime=0) as destino:
                    destino.write(json.dumps(contornos(fija, sorted(disponibles), cmap, por_em), separators=(",", ":")).encode("utf-8"))
                fija.flavor = "woff2"
                fija.save(RAIZ / "fuentes" / nombre)

                for codigo in sorted(disponibles):
                    if codigo in cmap:
                        tabla[str(codigo)] = round(hmtx[cmap[codigo]][0] * UNIDADES / por_em)
                presentes = sorted(c for c in disponibles if c in cmap)
                usados |= set(presentes)
                lista.append({"archivo": nombre, "rangos": rangos(presentes)})
                print(f"{nombre}: {len(presentes)} letras, {(RAIZ / 'fuentes' / nombre).stat().st_size} bytes", file=sys.stderr)

            minusculas = [tabla[str(ord(c))] for c in "abcdefghijklmnopqrstuvwxyz"]
            indice[familia].setdefault(estilo, {})[str(peso)] = lista
            anchos["caras"][familia].setdefault(estilo, {})[str(peso)] = {
                "promedio": round(sum(minusculas) / len(minusculas)),
                "anchos": tabla,
            }

    (RAIZ / "datos" / "fuentes.json").write_text(json.dumps({"caras": indice}, separators=(",", ":")) + "\n", encoding="utf-8")
    (RAIZ / "datos" / "anchos.json").write_text(json.dumps(anchos, separators=(",", ":")) + "\n", encoding="utf-8")
    total = sum(f.stat().st_size for f in (RAIZ / "fuentes").glob("*.woff2"))
    print(f"fuentes/: {total} bytes en total", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
