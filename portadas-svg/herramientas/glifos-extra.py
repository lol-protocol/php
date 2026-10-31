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
No aplica a alfabetos que necesitan formas que cambian según las letras de al lado (árabe, devanagari) ni a los que
se escriben de derecha a izquierda (hebreo, árabe): el generador pone las letras de izquierda a derecha, una a una.
"""

import argparse
import csv
import json
import sys
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
    return "".join("".join(fila) for fila in csv.reader(contenido.splitlines()))


def cubiertas() -> set[int]:
    """Las letras que ya tienen las fuentes del proyecto, según datos/anchos.json."""
    datos = json.loads((RAIZ / "datos" / "anchos.json").read_text(encoding="utf-8"))
    codigos: set[int] = set()
    for estilos in datos["caras"].values():
        for pesos in estilos.values():
            for cara in pesos.values():
                codigos |= {int(c) for c in cara["anchos"]}
    return codigos


def main() -> int:
    ap = argparse.ArgumentParser(description="Saca de una fuente los contornos de las letras que faltan.")
    ap.add_argument("fuente", type=Path, help="archivo de fuente (.otf, .ttf, .woff2)")
    ap.add_argument("salida", type=Path, help="archivo .json que se escribe")
    origen = ap.add_mutually_exclusive_group(required=True)
    origen.add_argument("--texto", help="las letras que hacen falta")
    origen.add_argument("--lista", type=Path, help="un CSV o JSON de libros del que se toman las letras")
    args = ap.parse_args()

    texto = args.texto if args.texto is not None else letras_de(args.lista)
    ya_estan = cubiertas()
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
