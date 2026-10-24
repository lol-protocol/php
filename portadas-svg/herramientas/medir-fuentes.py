#!/usr/bin/env python3
"""Genera datos/anchos.json: lo que mide de ancho cada letra de las fuentes de fuentes/.

Es una herramienta de desarrollo. El generador en PHP no la necesita para correr: solo lee el JSON que
esta produce, para medir el texto sin un navegador. Hay que volver a correrla solo si cambian las fuentes
de fuentes/ o los pesos que usan las plantillas.

    pip install fonttools brotli
    python3 herramientas/medir-fuentes.py

Las fuentes son variables en el eje de peso (wght). Para cada peso que usan las plantillas se fija ese
peso y se lee el ancho de avance de cada letra. Los anchos van en milésimas del tamaño de letra (em).
No se tiene en cuenta el kerning (el ajuste entre pares de letras), que casi siempre acorta una línea
uno o dos por ciento: la medida sale igual o algo mayor que la de un navegador, no menor.
"""

import json
import sys
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib.instancer import instantiateVariableFont

RAIZ = Path(__file__).resolve().parent.parent

# familia -> estilo -> archivo
FUENTES = {
    "serif": {
        "normal": "fuentes/source-serif-4-latin-wght-normal.woff2",
        "italic": "fuentes/source-serif-4-latin-wght-italic.woff2",
    },
    "sans": {
        "normal": "fuentes/inter-latin-wght-normal.woff2",
    },
}

# familia -> estilo -> pesos que usan las plantillas
PESOS = {
    "serif": {"normal": [400], "italic": [400]},
    "sans": {"normal": [400, 700]},
}

UNIDADES = 1000


def anchos(fuente: TTFont) -> dict[str, int]:
    """Ancho de avance de cada letra de la fuente, en milésimas de em, por código Unicode."""
    cmap = fuente.getBestCmap()
    hmtx = fuente["hmtx"]
    por_em = fuente["head"].unitsPerEm
    return {
        str(codigo): round(hmtx[nombre][0] * UNIDADES / por_em)
        for codigo, nombre in sorted(cmap.items())
        if codigo >= 0x20
    }


def promedio(tabla: dict[str, int]) -> int:
    """Ancho medio de las minúsculas a-z: lo que se usa para una letra que la fuente no tiene."""
    minusculas = [tabla[str(ord(c))] for c in "abcdefghijklmnopqrstuvwxyz"]
    return round(sum(minusculas) / len(minusculas))


def main() -> int:
    resultado: dict = {"unidades": UNIDADES, "caras": {}}
    for familia, estilos in FUENTES.items():
        resultado["caras"][familia] = {}
        for estilo, ruta in estilos.items():
            resultado["caras"][familia][estilo] = {}
            for peso in PESOS[familia][estilo]:
                fuente = TTFont(RAIZ / ruta)
                fijada = instantiateVariableFont(fuente, {"wght": peso}, inplace=False)
                tabla = anchos(fijada)
                resultado["caras"][familia][estilo][str(peso)] = {
                    "promedio": promedio(tabla),
                    "anchos": tabla,
                }
                print(f"{familia} {estilo} {peso}: {len(tabla)} letras, promedio {promedio(tabla)}", file=sys.stderr)

    destino = RAIZ / "datos" / "anchos.json"
    destino.parent.mkdir(exist_ok=True)
    destino.write_text(json.dumps(resultado, separators=(",", ":")) + "\n", encoding="utf-8")
    print(f"escrito {destino.relative_to(RAIZ)} ({destino.stat().st_size} bytes)", file=sys.stderr)
    return 0


if __name__ == "__main__":
    sys.exit(main())
