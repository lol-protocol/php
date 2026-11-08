#!/usr/bin/env python3
"""Mantiene al día los datos que `editor/index.html` copia del generador (paletas, franja de texto, tonos,
contraste mínimo, velos y límites de subida), para que la vista previa y el servidor no diverjan.

    python generator/editor_datos.py             # reescribe el bloque de datos de la página
    python generator/editor_datos.py --comprobar # sale con error si la página no está al día
"""
import argparse
import json
import re
import sys
from pathlib import Path

import editor
import plantillas
import qa
from scene import ALTO, ANCHO, FRANJA_CENTRAL

RAIZ = Path(__file__).resolve().parent.parent
PAGINA = RAIZ / "editor" / "index.html"
INICIO, FIN = "/* DATOS:inicio */", "/* DATOS:fin */"

# Paletas que ofrece el editor y su nombre en pantalla.
PALETAS_EDITOR = {"atardecer": "Atardecer", "amanecer": "Amanecer", "noche": "Noche", "niebla": "Niebla", "otono": "Otoño",
                  "rosa": "Rosa", "esmeralda": "Esmeralda", "medianoche": "Medianoche", "ceniza": "Ceniza", "dorada": "Dorada",
                  "tempestad": "Tempestad", "cripta": "Cripta"}
CLAVES = {"cielo_alto": "a", "cielo_bajo": "b", "sol": "s", "lejos": "l", "cerca": "c", "figura": "f"}


def datos():
    """Lo que la página necesita saber. Las paletas son las «legibles», como las dibuja el catálogo."""
    paletas = {}
    for nombre, mostrado in PALETAS_EDITOR.items():
        p = plantillas.paleta_legible(nombre)
        paletas[nombre] = {"n": mostrado, **{corto: p[largo] for largo, corto in CLAVES.items()}}
    x0, y0, x1, y1 = FRANJA_CENTRAL
    return {
        "ancho": ANCHO,
        "alto": ALTO,
        "paletas": paletas,
        "franja": {"x0": x0, "y0": y0, "x1": x1, "y1": y1},
        "tonos": qa.TONOS_TEXTO,
        "minContraste": qa.MIN_CONTRASTE_TEXTO,
        "velos": list(qa.OPACIDADES_VELO),
        "maxBytes": editor.MAX_BYTES,
        "maxPixeles": editor.MAX_PIXELES,
        "ladoGuardado": editor.LADO_MAXIMO_GUARDADO,
    }


def bloque():
    return f"{INICIO}{json.dumps(datos(), ensure_ascii=False, indent=2)}{FIN}"


def actualizado(texto):
    """El texto de la página con su bloque de datos al día."""
    nuevo, n = re.subn(re.escape(INICIO) + r".*?" + re.escape(FIN), lambda _: bloque(), texto, count=1, flags=re.S)
    if n != 1:
        raise ValueError("la página no tiene el bloque de datos (DATOS:inicio … DATOS:fin)")
    return nuevo


def main(argv=None):
    ap = argparse.ArgumentParser()
    ap.add_argument("--comprobar", action="store_true")
    args = ap.parse_args(argv)
    actual = PAGINA.read_text(encoding="utf-8")
    nuevo = actualizado(actual)
    if args.comprobar:
        if nuevo != actual:
            print("editor/index.html no está al día con el generador: ejecuta python generator/editor_datos.py")
            return 1
        print("editor/index.html al día")
        return 0
    PAGINA.write_text(nuevo, encoding="utf-8")
    print("editor/index.html actualizado" if nuevo != actual else "editor/index.html ya estaba al día")
    return 0


if __name__ == "__main__":
    sys.exit(main())
