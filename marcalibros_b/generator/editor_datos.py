#!/usr/bin/env python3
"""Mantiene al día los datos que `editor/index.html` copia del generador (paletas, franja de texto, tonos,
contraste mínimo, velos y límites de subida), para que la vista previa y el servidor no diverjan.

    python generator/editor_datos.py             # reescribe el bloque de datos de la página
    python generator/editor_datos.py --comprobar # sale con error si la página no está al día
    python generator/editor_datos.py --sprites   # dibuja editor/plantillas/<movimiento>.jpg (vista de cada plantilla)

Los sprites son la miniatura (80×560) del fondo que dibuja `editor.renderizar_receta` para cada plantilla,
sin texto: no se versionan (los genera la CI) y la página funciona sin ellos con una vista aproximada.
"""
import argparse
import json
import re
import sys
from pathlib import Path

import yaml
from PIL import Image

import editor
import plantillas
import qa
from render_raster import a_imagen
from scene import ALTO, ANCHO, CELDA_REVISION, ESCALA_REVISION, FRANJA_CENTRAL

RAIZ = Path(__file__).resolve().parent.parent
PAGINA = RAIZ / "editor" / "index.html"
SPRITES = RAIZ / "editor" / "plantillas"
COLUMNAS = 20  # piezas por fila en cada sprite
INICIO, FIN = "/* DATOS:inicio */", "/* DATOS:fin */"

# Paletas que ofrece el editor y su nombre en pantalla.
PALETAS_EDITOR = {"atardecer": "Atardecer", "amanecer": "Amanecer", "noche": "Noche", "niebla": "Niebla", "otono": "Otoño",
                  "rosa": "Rosa", "esmeralda": "Esmeralda", "medianoche": "Medianoche", "ceniza": "Ceniza", "dorada": "Dorada",
                  "tempestad": "Tempestad", "cripta": "Cripta"}
CLAVES = {"cielo_alto": "a", "cielo_bajo": "b", "sol": "s", "lejos": "l", "cerca": "c", "figura": "f"}


def _paleta(nombre, mostrado=None):
    p = plantillas.paleta_legible(nombre)
    return {**({"n": mostrado} if mostrado else {}), **{corto: p[largo] for largo, corto in CLAVES.items()}}


def _manifiesto():
    return json.loads((RAIZ / "catalog" / "manifest.json").read_text(encoding="utf-8"))["entradas"]


def catalogo():
    """Las 1000 plantillas del catálogo, en forma compacta. Cada pieza: [id, autor, obra, paleta, composición,
    motivo]; su posición dentro de su movimiento es su lugar en el sprite de ese movimiento."""
    config = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    movs = [{"id": m["id"], "nombre": m["nombre"]} for m in config["movimientos"]]
    autores, piezas = [], []
    for e in _manifiesto():
        clave = (e["movimiento"], e["autor_id"])
        if clave not in [(a["m"], a["id"]) for a in autores]:
            autores.append({"m": e["movimiento"], "id": e["autor_id"], "nombre": e["autor"]})
        autor = next(i for i, a in enumerate(autores) if (a["m"], a["id"]) == clave)
        piezas.append([e["id"], autor, e["obra"], e["paleta"], e["arquetipo"], e["motivo"]])
    return {"movs": movs, "autores": autores, "piezas": piezas}


def datos():
    """Lo que la página necesita saber. Las paletas son las «legibles», como las dibuja el catálogo."""
    paletas = {nombre: _paleta(nombre, mostrado) for nombre, mostrado in PALETAS_EDITOR.items()}
    cat = catalogo()
    usadas = sorted({p[3] for p in cat["piezas"]} - set(paletas))
    x0, y0, x1, y1 = FRANJA_CENTRAL
    return {
        "ancho": ANCHO,
        "alto": ALTO,
        "paletas": paletas,
        "paletasCatalogo": {nombre: _paleta(nombre) for nombre in usadas},
        "catalogo": cat,
        "sprites": {"columnas": COLUMNAS, "celda": list(CELDA_REVISION)},
        "franja": {"x0": x0, "y0": y0, "x1": x1, "y1": y1},
        "tonos": qa.TONOS_TEXTO,
        "minContraste": qa.MIN_CONTRASTE_TEXTO,
        "velos": list(qa.OPACIDADES_VELO),
        "maxBytes": editor.MAX_BYTES,
        "maxPixeles": editor.MAX_PIXELES,
        "ladoGuardado": editor.LADO_MAXIMO_GUARDADO,
    }


def sprites(movimientos=None):
    """Escribe un sprite por movimiento con el fondo de cada plantilla, tal como lo dibuja la receta (sin texto)."""
    SPRITES.mkdir(parents=True, exist_ok=True)
    manifiesto = {"entradas": _manifiesto()}
    por_mov = {}
    for e in manifiesto["entradas"]:
        por_mov.setdefault(e["movimiento"], []).append(e["id"])
    hechos = []
    for mov, ids in por_mov.items():
        if movimientos and mov not in movimientos:
            continue
        ancho, alto = CELDA_REVISION
        hoja = Image.new("RGB", (COLUMNAS * ancho, ((len(ids) + COLUMNAS - 1) // COLUMNAS) * alto), (0, 0, 0))
        for k, ident in enumerate(ids):
            esc = editor._fondo_de_pieza(ident, manifiesto)
            img = a_imagen(esc, ESCALA_REVISION)
            hoja.paste(img, ((k % COLUMNAS) * ancho, (k // COLUMNAS) * alto))
        hoja.save(SPRITES / f"{mov}.jpg", "JPEG", quality=78, optimize=True, progressive=True)
        hechos.append(mov)
    return hechos


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
    ap.add_argument("--sprites", action="store_true", help="dibuja los sprites de las plantillas en editor/plantillas/")
    ap.add_argument("--movimiento", action="append", help="con --sprites, solo ese movimiento (se puede repetir)")
    args = ap.parse_args(argv)
    if args.sprites:
        hechos = sprites(args.movimiento)
        print(f"sprites: {len(hechos)} movimientos en {SPRITES.relative_to(RAIZ)}/")
        return 0
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
