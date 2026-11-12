"""Pruebas de Listado-colores. Uso, desde la carpeta del proyecto: python3 -m unittest -v pruebas

Solo biblioteca estándar. Dos tipos de prueba:

- Los validadores de generar-tabla.py se prueban en memoria: cada regla se rompe a propósito en una
  copia de los CSV reales y debe dar su error (y las rutas sin romper no dan ninguno).
- main() se prueba de punta a punta en una carpeta temporal con una copia de los CSV. Su
  nombres-completos.csv se reduce a unas 240 filas para no recalcular las 31.935 distancias; tiene todos
  los conceptos del léxico, así que todos los archivos generados salen idénticos a los del repositorio
  salvo el propio nombres-completos.csv. Nunca se escribe en la carpeta del proyecto.

La CI corre además `python3 generar-tabla.py --comprobar` sobre los archivos reales.

Cada módulo de esta carpeta (test_*.py) prueba una parte; apoyo.py guarda lo que comparten. `python3 -m unittest pruebas`
los encuentra todos con load_tests.
"""
from pathlib import Path


def load_tests(cargador, pruebas, patron):
    carpeta = Path(__file__).resolve().parent
    return cargador.discover(str(carpeta), pattern="test_*.py", top_level_dir=str(carpeta.parent))
