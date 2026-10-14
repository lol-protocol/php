#!/usr/bin/env python3
"""Valida colores.csv y genera la tabla que hay entre los marcadores de README.md.

Uso:
  python3 generar-tabla.py              valida colores.csv y reescribe la tabla del README
  python3 generar-tabla.py --comprobar  valida y sale con error si el README no está al día
"""
import csv
import re
import sys
from pathlib import Path

CARPETA = Path(__file__).resolve().parent
CSV_PATH = CARPETA / "colores.csv"
README = CARPETA / "README.md"
INICIO = "<!-- tabla:inicio -->"
FIN = "<!-- tabla:fin -->"
COLUMNAS = [
    "nombre_ingles",
    "nombre_espanol",
    "hex",
    "significado_psicologico",
    "significado_mistico",
]
RE_NOMBRE = re.compile(r"[a-z]+")
RE_HEX = re.compile(r"#[0-9A-F]{6}")


def leer_colores():
    with CSV_PATH.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames != COLUMNAS:
            sys.exit(f"colores.csv: cabecera inesperada {lector.fieldnames}")
        filas = list(lector)

    errores = []
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        if None in fila or any(v is None or v.strip() == "" for v in fila.values()):
            errores.append(f"línea {n}: faltan campos o hay campos de más")
            continue
        if any("|" in v or "\n" in v for v in fila.values()):
            errores.append(f"línea {n}: no puede llevar '|' ni saltos de línea")
        nombre = fila["nombre_ingles"]
        if not RE_NOMBRE.fullmatch(nombre):
            errores.append(f"línea {n}: nombre_ingles debe ser minúsculas sin espacios: {nombre!r}")
        if nombre in vistos:
            errores.append(f"línea {n}: nombre repetido: {nombre!r}")
        vistos.add(nombre)
        if not RE_HEX.fullmatch(fila["hex"]):
            errores.append(f"línea {n}: hex inválido {fila['hex']!r} (formato #RRGGBB en mayúsculas)")

    nombres = [f["nombre_ingles"] for f in filas if None not in f]
    if nombres != sorted(nombres):
        errores.append("las filas deben estar ordenadas por nombre_ingles")

    if errores:
        sys.exit("colores.csv:\n  " + "\n  ".join(errores))
    return filas


def tabla(filas):
    lineas = [
        "| Nombre (inglés) | Nombre (español) | Hex | Significado psicológico | Significado místico |",
        "|---|---|---|---|---|",
    ]
    for f in filas:
        lineas.append(
            f"| `{f['nombre_ingles']}` | {f['nombre_espanol']} | `{f['hex']}` "
            f"| {f['significado_psicologico']} | {f['significado_mistico']} |"
        )
    return "\n".join(lineas)


def main():
    filas = leer_colores()
    texto = README.read_text(encoding="utf-8")
    # Cada marcador debe ocupar una línea entera: así no coincide con menciones en la prosa.
    patron = re.compile(r"^" + re.escape(INICIO) + r"$.*?^" + re.escape(FIN) + r"$", re.S | re.M)
    if not patron.search(texto):
        sys.exit(f"README.md: faltan los marcadores {INICIO} y {FIN}")
    nuevo = patron.sub(lambda _: f"{INICIO}\n{tabla(filas)}\n{FIN}", texto)

    if "--comprobar" in sys.argv:
        if nuevo != texto:
            print("README.md no coincide con colores.csv: ejecuta python3 generar-tabla.py",
                  file=sys.stderr)
            return 1
        print(f"OK: {len(filas)} colores válidos y README al día.")
        return 0

    README.write_text(nuevo, encoding="utf-8")
    print(f"README.md actualizado con {len(filas)} colores.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
