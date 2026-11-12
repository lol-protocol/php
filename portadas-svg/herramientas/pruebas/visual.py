#!/usr/bin/env python3
"""Regresión visual: dibuja con resvg las piezas a trazos de ejemplos/salida-trazos/ y las compara con los PNG guardados.

Las piezas a trazos no dependen de ninguna fuente, así que el dibujo es el mismo en cualquier máquina. Las huellas
de `pruebas/dibujos.json` ya avisan de que el SVG cambió; esto avisa de que **lo que se ve** cambió, y deja ver cuánto.

    pip install resvg-py Pillow
    python3 herramientas/pruebas/visual.py               # compara; falla si algo se ve distinto
    python3 herramientas/pruebas/visual.py --actualizar  # guarda los PNG nuevos en pruebas/visual/

Un píxel cuenta como distinto si algún canal se aleja más de CANAL; la pieza falla si más de TOLERANCIA de sus píxeles
son distintos (el antialiasing de los bordes puede variar un poco entre versiones de resvg).
"""

import io
import sys
from pathlib import Path

import resvg_py
from PIL import Image, ImageChops

RAIZ = Path(__file__).resolve().parent.parent.parent
ORIGEN = RAIZ / "ejemplos" / "salida-trazos"
BASE = RAIZ / "pruebas" / "visual"
ANCHO = 360
CANAL = 24
TOLERANCIA = 0.002
PIEZAS = ["portada.svg", "contratapa.svg", "lomo.svg"]


def dibujar(ruta: Path) -> Image.Image:
    png = resvg_py.svg_to_bytes(svg_path=str(ruta), width=ANCHO, dpi=96.0, background="#ffffff", skip_system_fonts=True)
    return Image.open(io.BytesIO(bytes(png))).convert("RGB")


def distintos(a: Image.Image, b: Image.Image) -> float:
    """Fracción de píxeles que se alejan más de CANAL en algún canal."""
    if a.size != b.size:
        return 1.0
    mayor = ImageChops.difference(a, b).convert("L").point(lambda v: 255 if v > CANAL else 0)
    return mayor.histogram()[255] / (a.size[0] * a.size[1])


def piezas() -> list[tuple[str, Path]]:
    return [(f"{libro.name}-{pieza[:-4]}", libro / pieza) for libro in sorted(ORIGEN.iterdir()) if libro.is_dir() for pieza in PIEZAS if (libro / pieza).is_file()]


def main(argumentos: list[str]) -> int:
    actualizar = "--actualizar" in argumentos
    BASE.mkdir(parents=True, exist_ok=True)
    fallos = []
    esperadas = set()
    for nombre, ruta in piezas():
        esperadas.add(f"{nombre}.png")
        imagen = dibujar(ruta)
        guardada = BASE / f"{nombre}.png"
        if actualizar or not guardada.is_file():
            imagen.save(guardada, optimize=True)
            print(f"guardada  {guardada.relative_to(RAIZ)}")
            continue
        fraccion = distintos(imagen, Image.open(guardada).convert("RGB"))
        estado = "FALLO " if fraccion > TOLERANCIA else "ok    "
        print(f"{estado} {nombre}: {fraccion:.4%} de píxeles distintos")
        if fraccion > TOLERANCIA:
            fallos.append(nombre)
            imagen.save(BASE / f"{nombre}.nuevo.png")
    for sobra in sorted(p.name for p in BASE.glob("*.png") if not p.name.endswith(".nuevo.png")):
        if sobra not in esperadas:
            fallos.append(sobra)
            print(f"FALLO  {sobra}: no corresponde a ninguna pieza de ejemplos/salida-trazos/")
    if fallos:
        print(f"\n{len(fallos)} piezas distintas. Si es a propósito: python3 herramientas/pruebas/visual.py --actualizar", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
