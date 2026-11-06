"""Lo que comparten los scripts de Listado-paises: lectura y escritura de CSV y años de los datos.

Todos los CSV del proyecto se escriben con el módulo csv (fines de línea \\r\\n) y se leen con
newline="" para conservarlos tal cual. Solo biblioteca estándar.
"""
import csv
from pathlib import Path

# Año de cada dato de paises.csv. Los demás (alfabetización, internet, libertad, democracia y
# corrupción) varían por país: datos_detalle.csv guarda el año de cada uno.
ANIO_POBLACION = 2024
ANIO_IDH = 2023
ANIO_PIB_PPA = 2023
ANIO_PIB_NOMINAL = 2024  # si falta, el último año con dato desde ANIO_PIB_NOMINAL_MINIMO
ANIO_PIB_NOMINAL_MINIMO = 2021
ANIO_EMIGRANTES = 2024


def leer_csv(ruta):
    """(columnas, filas como dict) de un CSV."""
    with Path(ruta).open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        return lector.fieldnames, list(lector)


def filas_csv(ruta):
    """Solo las filas, como dict."""
    return leer_csv(ruta)[1]


def escribir_dicts(ruta, columnas, filas):
    with Path(ruta).open("w", encoding="utf-8", newline="") as f:
        escritor = csv.DictWriter(f, fieldnames=columnas)
        escritor.writeheader()
        escritor.writerows(filas)


def escribir_filas(ruta, encabezado, filas):
    with Path(ruta).open("w", encoding="utf-8", newline="") as f:
        escritor = csv.writer(f)
        escritor.writerow(encabezado)
        escritor.writerows(filas)
