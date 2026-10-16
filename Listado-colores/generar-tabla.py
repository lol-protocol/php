#!/usr/bin/env python3
"""Valida los CSV de Listado-colores y genera la tabla de README.md y la lista por idioma.

- colores.csv: tabla anotada, 148 colores CSS con nombre en 30 idiomas, hex y significados.
  Su tabla resumida va en el README y su lista completa por idioma va en colores-por-idioma.md.
- nombres-completos.csv: lista completa de nombres con hex, sin significados. Solo se valida.

Uso:
  python3 generar-tabla.py              valida los CSV y regenera README.md y colores-por-idioma.md
  python3 generar-tabla.py --comprobar  valida y sale con error si los archivos generados no están al día
"""
import csv
import re
import sys
from pathlib import Path

CARPETA = Path(__file__).resolve().parent
ANOTADA = CARPETA / "colores.csv"
COMPLETA = CARPETA / "nombres-completos.csv"
README = CARPETA / "README.md"
POR_IDIOMA = CARPETA / "colores-por-idioma.md"
INICIO = "<!-- tabla:inicio -->"
FIN = "<!-- tabla:fin -->"

# Código del idioma y nombre en ese idioma. El inglés es la columna nombre_ingles.
IDIOMAS = [
    ("en", "Inglés"), ("es", "Español"), ("fr", "Francés"), ("pt", "Portugués"), ("it", "Italiano"),
    ("de", "Alemán"), ("nl", "Neerlandés"), ("sv", "Sueco"), ("da", "Danés"), ("fi", "Finés"),
    ("pl", "Polaco"), ("cs", "Checo"), ("ro", "Rumano"), ("hu", "Húngaro"), ("el", "Griego"),
    ("ru", "Ruso"), ("uk", "Ucraniano"), ("tr", "Turco"), ("ar", "Árabe"), ("he", "Hebreo"),
    ("fa", "Persa"), ("hi", "Hindi"), ("bn", "Bengalí"), ("zh", "Chino simplificado"),
    ("ja", "Japonés"), ("ko", "Coreano"), ("vi", "Vietnamita"), ("th", "Tailandés"),
    ("id", "Indonesio"), ("sw", "Suajili"),
]
COLUMNAS_ANOTADA = (
    ["nombre_ingles"]
    + [f"nombre_{c}" for c, _ in IDIOMAS[1:]]
    + ["hex", "significado_psicologico", "significado_mistico"]
)
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


def tabla_readme(filas):
    lineas = [
        "| Inglés | Español | Hex | Significado psicológico | Significado místico |",
        "|---|---|---|---|---|",
    ]
    for f in filas:
        lineas.append(
            f"| `{f['nombre_ingles']}` | {f['nombre_es']} | `{f['hex']}` "
            f"| {f['significado_psicologico']} | {f['significado_mistico']} |"
        )
    return "\n".join(lineas)


def por_idioma(filas):
    partes = [
        "# Colores con nombre por idioma",
        "",
        "Generado por `generar-tabla.py` a partir de `colores.csv`. No editar a mano.",
        "",
        "Cada sección es un idioma. Las traducciones son propuestas y no están revisadas por hablantes nativos.",
        "",
    ]
    for codigo, etiqueta in IDIOMAS:
        partes += [f"## {etiqueta} (`{codigo}`)", "", "| Inglés | Nombre | Hex |", "|---|---|---|"]
        for f in filas:
            nombre = f["nombre_ingles"] if codigo == "en" else f[f"nombre_{codigo}"]
            partes.append(f"| `{f['nombre_ingles']}` | {nombre} | `{f['hex']}` |")
        partes.append("")
    return "\n".join(partes)


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
    nuevo_readme = patron.sub(lambda _: f"{INICIO}\n{tabla_readme(anotada)}\n{FIN}", texto)
    nuevo_por_idioma = por_idioma(anotada)

    resumen = f"{len(anotada)} colores anotados en {len(IDIOMAS)} idiomas y {len(completa)} nombres completos"
    if "--comprobar" in sys.argv:
        desfasados = []
        if nuevo_readme != texto:
            desfasados.append("README.md")
        if not POR_IDIOMA.exists() or POR_IDIOMA.read_text(encoding="utf-8") != nuevo_por_idioma:
            desfasados.append("colores-por-idioma.md")
        if desfasados:
            print(f"No coinciden con colores.csv: {', '.join(desfasados)}. "
                  "Ejecuta python3 generar-tabla.py", file=sys.stderr)
            return 1
        print(f"OK: {resumen}; archivos generados al día.")
        return 0

    README.write_text(nuevo_readme, encoding="utf-8")
    POR_IDIOMA.write_text(nuevo_por_idioma, encoding="utf-8")
    print(f"Generados README.md y colores-por-idioma.md: {resumen}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
