#!/usr/bin/env python3
"""Valida paises.csv y genera tabla.md, ordenada por prioridad.

Uso: python3 generar_tabla.py   (solo biblioteca estándar de Python 3)

Prioridad = indice_genealogia x indice_operabilidad (1 a 25).
Excluido = indice_operabilidad <= UMBRAL_EXCLUSION.
"""
import csv
import sys
from pathlib import Path

AQUI = Path(__file__).resolve().parent
ENTRADA = AQUI / "paises.csv"
SALIDA = AQUI / "tabla.md"

COLUMNAS = [
    "codigo", "pais", "tipo", "region", "poblacion_millones",
    "poblacion_historica_acumulada", "idh", "pib_per_capita_usd",
    "indice_genealogia", "indice_operabilidad", "comentarios",
]
TIPOS = {"estado", "territorio", "entidad disputada"}
NUMERICAS = ["poblacion_millones", "idh", "pib_per_capita_usd"]
UMBRAL_EXCLUSION = 2


def leer(ruta):
    with ruta.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames != COLUMNAS:
            sys.exit(f"Encabezado inesperado en {ruta.name}: {lector.fieldnames}")
        return list(enumerate(lector, start=2))


def errores_de(linea, fila):
    errs = []
    if None in fila or any(v is None for v in fila.values()):
        errs.append("número de columnas distinto del encabezado")
        return errs
    if not fila["codigo"] or len(fila["codigo"]) != 2:
        errs.append("codigo debe tener 2 letras (ISO 3166-1 alfa-2)")
    if fila["tipo"] not in TIPOS:
        errs.append(f"tipo '{fila['tipo']}' no válido")
    for campo in ("indice_genealogia", "indice_operabilidad"):
        if fila[campo] not in {"1", "2", "3", "4", "5"}:
            errs.append(f"{campo} debe ser un entero de 1 a 5, es '{fila[campo]}'")
    for campo in NUMERICAS:
        if fila[campo]:
            try:
                float(fila[campo])
            except ValueError:
                errs.append(f"{campo} no es numérico: '{fila[campo]}'")
    return [f"línea {linea} ({fila['codigo']}): {e}" for e in errs]


def preparar(filas):
    errores, vistos, salida = [], set(), []
    for linea, fila in filas:
        errores += errores_de(linea, fila)
        codigo = fila.get("codigo")
        if codigo in vistos:
            errores.append(f"línea {linea}: código {codigo} repetido")
        vistos.add(codigo)
        if not errores_de(linea, fila):
            ig, io = int(fila["indice_genealogia"]), int(fila["indice_operabilidad"])
            fila["prioridad"] = ig * io
            fila["excluido"] = io <= UMBRAL_EXCLUSION
            salida.append(fila)
    if errores:
        sys.exit("Errores en paises.csv:\n  " + "\n  ".join(errores))
    return salida


def celda(valor):
    return valor if valor else "—"


def tabla_md(filas):
    enc = ["País", "Tipo", "Región", "Población (M)", "Población histórica acumulada",
           "IDH", "PIB pc (USD)", "Genealogía (IG)", "Operabilidad (IO)",
           "Prioridad", "Excluido", "Comentarios"]
    lineas = [
        "# Listado de países",
        "",
        "> Generado por `generar_tabla.py` desde `paises.csv`. No editar a mano.",
        "> Cifras de población, IDH y PIB pc **sin contrastar** con las fuentes oficiales; "
        "ver `README.md`, sección «Estado de los datos».",
        "",
        "| " + " | ".join(enc) + " |",
        "|" + "|".join(["---"] * len(enc)) + "|",
    ]
    orden = sorted(filas, key=lambda f: (f["excluido"], -f["prioridad"], f["pais"]))
    for f in orden:
        lineas.append("| " + " | ".join([
            f["pais"], f["tipo"], f["region"], celda(f["poblacion_millones"]),
            "pendiente", celda(f["idh"]), celda(f["pib_per_capita_usd"]),
            f["indice_genealogia"], f["indice_operabilidad"], str(f["prioridad"]),
            "sí" if f["excluido"] else "no", f["comentarios"],
        ]) + " |")
    return "\n".join(lineas) + "\n"


def main():
    filas = preparar(leer(ENTRADA))
    SALIDA.write_text(tabla_md(filas), encoding="utf-8")
    excluidos = sum(1 for f in filas if f["excluido"])
    print(f"{len(filas)} países; {excluidos} excluidos; escrito {SALIDA.name}")


if __name__ == "__main__":
    main()
