#!/usr/bin/env python3
"""Calcula el kerning (el ajuste entre pares de letras) de las fuentes del proyecto y lo guarda en datos/kerning.json.

Es una herramienta de desarrollo: el generador en PHP solo lee el resultado. Para cada cara (familia, estilo y peso)
compone con HarfBuzz cada par de caracteres ASCII imprimibles (U+0021–007E) y guarda, en milésimas de em, lo que el par
se aleja de la suma de los avances sueltos. Solo se guardan los pares que se ajustan (en torno a 1 de cada 40).

Se leen los trozos `basico` de `fuentes/`, que ya traen la tabla de kerning de la fuente original. Hay que volver a
correrla si cambian las fuentes (junto con preparar-fuentes.py):

    pip install fonttools brotli uharfbuzz
    python3 herramientas/kerning.py          # escribe datos/kerning.json
    python3 herramientas/kerning.py --comprobar   # falla si datos/kerning.json no está al día

Las ligaduras (fi, fl) se dejan fuera a propósito: con `letter-spacing` los navegadores las desactivan, y el generador
mide letra a letra.
"""

import io
import json
import sys
from pathlib import Path

import uharfbuzz as hb
from fontTools.ttLib import TTFont

RAIZ = Path(__file__).resolve().parent.parent
UNIDADES = 1000
CARAS = {
    "serif": {"normal": [400], "italic": [400]},
    "sans": {"normal": [400, 700]},
}
ASCII = [chr(c) for c in range(0x21, 0x7F)]


def abrir(ruta: Path) -> tuple[hb.Font, TTFont]:
    """La fuente woff2 como fuente de HarfBuzz (que no lee woff2) y como TTFont, para sus avances."""
    ttf = TTFont(ruta, recalcTimestamp=False)
    plano = io.BytesIO()
    copia = TTFont(ruta, recalcTimestamp=False)
    copia.flavor = None
    copia.save(plano)
    return hb.Font(hb.Face(hb.Blob(plano.getvalue()))), ttf


def pares(ruta: Path) -> dict[str, int]:
    letra, ttf = abrir(ruta)
    por_em = ttf["head"].unitsPerEm
    cmap = ttf.getBestCmap()
    hmtx = ttf["hmtx"]
    resultado: dict[str, int] = {}
    for a in ASCII:
        if ord(a) not in cmap:
            continue
        for b in ASCII:
            if ord(b) not in cmap:
                continue
            texto = hb.Buffer()
            texto.add_str(a + b)
            texto.guess_segment_properties()
            hb.shape(letra, texto, {"kern": True, "liga": False, "clig": False, "calt": False})
            primero = texto.glyph_positions[0].x_advance
            sin_kerning = hmtx[cmap[ord(a)]][0]
            ajuste = round((primero - sin_kerning) * UNIDADES / por_em)
            if ajuste:
                resultado[a + b] = ajuste
    return resultado


def calcular() -> dict:
    caras: dict = {}
    for familia, estilos in CARAS.items():
        for estilo, pesos in estilos.items():
            for peso in pesos:
                ruta = RAIZ / "fuentes" / f"{familia}-{estilo}-{peso}-basico.woff2"
                caras.setdefault(familia, {}).setdefault(estilo, {})[str(peso)] = dict(sorted(pares(ruta).items()))
    return {"unidades": UNIDADES, "caras": caras}


def main(argumentos: list[str]) -> int:
    texto = json.dumps(calcular(), ensure_ascii=False, separators=(",", ":"), sort_keys=True) + "\n"
    destino = RAIZ / "datos" / "kerning.json"
    if "--comprobar" in argumentos:
        if not destino.is_file() or destino.read_text(encoding="utf-8") != texto:
            print("datos/kerning.json no está al día: python3 herramientas/kerning.py", file=sys.stderr)
            return 1
        return 0
    destino.write_text(texto, encoding="utf-8")
    total = sum(len(p) for e in json.loads(texto)["caras"].values() for s in e.values() for p in s.values())
    print(f"{destino.relative_to(RAIZ)}: {total} pares")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
