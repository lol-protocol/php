#!/usr/bin/env python3
"""Valida los CSV de Listado-colores y genera la tabla anotada de README.md.

- colores.csv: tabla anotada (nombres en cinco idiomas, hex y significados). Va en el README.
- nombres-completos.csv: lista completa de nombres con hex, sin significados. No va en el README.

Uso:
  python3 generar-tabla.py              valida los CSV y reescribe la tabla del README
  python3 generar-tabla.py --comprobar  valida y sale con error si el README no está al día
"""
import csv
import re
import sys
from pathlib import Path

CARPETA = Path(__file__).resolve().parent
ANOTADA = CARPETA / "colores.csv"
COMPLETA = CARPETA / "nombres-completos.csv"
README = CARPETA / "README.md"
INICIO = "<!-- tabla:inicio -->"
FIN = "<!-- tabla:fin -->"
COLUMNAS_ANOTADA = [
    "nombre_ingles",
    "nombre_espanol",
    "nombre_frances",
    "nombre_portugues",
    "nombre_italiano",
    "hex",
    "significado_psicologico",
    "significado_mistico",
]
COLUMNAS_COMPLETA = ["nombre_ingles", "hex", "curada"]
RE_NOMBRE_CSS = re.compile(r"[a-z]+")
RE_HEX = re.compile(r"#[0-9A-F]{6}")


def leer(ruta, columnas):
    with ruta.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames != columnas:
            sys.exit(f"{ruta.name}: cabecera inesperada {lector.fieldnames}")
        return list(lector)


def validar_anotada(filas, errores):
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        if any(not (fila[c] or "").strip() for c in COLUMNAS_ANOTADA):
            errores.append(f"colores.csv línea {n}: hay campos vacíos")
            continue
        if any("|" in v or "\n" in v for v in fila.values()):
            errores.append(f"colores.csv línea {n}: no puede llevar '|' ni saltos de línea")
        nombre = fila["nombre_ingles"]
        if not RE_NOMBRE_CSS.fullmatch(nombre):
            errores.append(f"colores.csv línea {n}: nombre_ingles debe ser minúsculas sin espacios: {nombre!r}")
        if nombre in vistos:
            errores.append(f"colores.csv línea {n}: nombre repetido: {nombre!r}")
        vistos.add(nombre)
        if not RE_HEX.fullmatch(fila["hex"]):
            errores.append(f"colores.csv línea {n}: hex inválido {fila['hex']!r} (#RRGGBB en mayúsculas)")
    nombres = [f["nombre_ingles"] for f in filas]
    if nombres != sorted(nombres):
        errores.append("colores.csv: las filas deben estar ordenadas por nombre_ingles")


def validar_completa(filas, errores):
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        nombre = fila["nombre_ingles"]
        if not nombre.strip() or nombre != nombre.strip():
            errores.append(f"nombres-completos.csv línea {n}: nombre vacío o con espacios sobrantes")
            continue
        if "|" in nombre or "\n" in nombre:
            errores.append(f"nombres-completos.csv línea {n}: no puede llevar '|' ni saltos de línea")
        if nombre.lower() in vistos:
            errores.append(f"nombres-completos.csv línea {n}: nombre repetido (sin distinguir mayúsculas): {nombre!r}")
        vistos.add(nombre.lower())
        if not RE_HEX.fullmatch(fila["hex"]):
            errores.append(f"nombres-completos.csv línea {n}: hex inválido {fila['hex']!r}")
        if fila["curada"] not in ("", "x"):
            errores.append(f"nombres-completos.csv línea {n}: curada debe ser vacía o 'x'")
    claves = [(f["nombre_ingles"].lower(), f["nombre_ingles"]) for f in filas]
    if claves != sorted(claves):
        errores.append("nombres-completos.csv: las filas deben estar ordenadas por nombre")


def tabla(filas):
    lineas = [
        "| Inglés | Español | Francés | Portugués | Italiano | Hex | Significado psicológico | Significado místico |",
        "|---|---|---|---|---|---|---|---|",
    ]
    for f in filas:
        lineas.append(
            f"| `{f['nombre_ingles']}` | {f['nombre_espanol']} | {f['nombre_frances']} "
            f"| {f['nombre_portugues']} | {f['nombre_italiano']} | `{f['hex']}` "
            f"| {f['significado_psicologico']} | {f['significado_mistico']} |"
        )
    return "\n".join(lineas)


def main():
    anotada = leer(ANOTADA, COLUMNAS_ANOTADA)
    completa = leer(COMPLETA, COLUMNAS_COMPLETA)
    errores = []
    validar_anotada(anotada, errores)
    validar_completa(completa, errores)
    if errores:
        sys.exit("Errores en los CSV:\n  " + "\n  ".join(errores))

    texto = README.read_text(encoding="utf-8")
    # Cada marcador debe ocupar una línea entera: así no coincide con menciones en la prosa.
    patron = re.compile(r"^" + re.escape(INICIO) + r"$.*?^" + re.escape(FIN) + r"$", re.S | re.M)
    if not patron.search(texto):
        sys.exit(f"README.md: faltan los marcadores {INICIO} y {FIN}")
    nuevo = patron.sub(lambda _: f"{INICIO}\n{tabla(anotada)}\n{FIN}", texto)

    resumen = f"{len(anotada)} colores anotados y {len(completa)} nombres completos"
    if "--comprobar" in sys.argv:
        if nuevo != texto:
            print("README.md no coincide con colores.csv: ejecuta python3 generar-tabla.py", file=sys.stderr)
            return 1
        print(f"OK: {resumen}; README al día.")
        return 0

    README.write_text(nuevo, encoding="utf-8")
    print(f"README.md actualizado: {resumen}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
