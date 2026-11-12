"""Lo que comparten los módulos de pruebas: los datos reales, el proyecto reducido y los ayudantes."""
import atexit
import contextlib
import copy
import csv
import hashlib
import importlib.util
import io
import json
import re
import shutil
import sqlite3
import subprocess
import sys
import tempfile
import unicodedata
import unittest
from pathlib import Path
from unittest import mock

AQUI = Path(__file__).resolve().parent.parent  # la carpeta del proyecto
sys.dont_write_bytecode = True  # no dejar __pycache__ de los módulos de al lado
sys.path.insert(0, str(AQUI))
import ambiguedad  # noqa: E402
import auditoria  # noqa: E402
import categorias  # noqa: E402
import cercania  # noqa: E402
import cobertura  # noqa: E402
import colorimetria  # noqa: E402
import exportes  # noqa: E402
import manifiesto  # noqa: E402
import normalizacion  # noqa: E402
import revision  # noqa: E402

# El generador se llama generar-tabla.py (con guion): no se puede importar con `import`.
_especificacion = importlib.util.spec_from_file_location("generar_tabla", AQUI / "generar-tabla.py")
gt = importlib.util.module_from_spec(_especificacion)
_especificacion.loader.exec_module(gt)

ARCHIVOS_DE_ENTRADA = ["colores.csv", "significados-idiomas.csv", "lexico-basico.csv", "evidencia.csv",
                       "significados-culturales.csv", "categorias.csv", "referencias.csv", "revisiones.csv", "colisiones-revisadas.csv", "visor-plantilla.html",
                       "CHANGELOG.md", "README.md"]
REAL = {}  # los CSV reales, leídos una vez; cada prueba trabaja con copias (ver real())
BASE = {}  # la carpeta temporal con el proyecto reducido ya generado


def preparar():
    """Lee los CSV reales, una sola vez por ejecución (cada módulo de pruebas lo pide en su setUpModule)."""
    if REAL:
        return
    REAL["anotada"] = gt.leer(gt.ANOTADA, gt.COLUMNAS_ANOTADA)
    REAL["completa"] = gt.leer_completa()
    REAL["significados"] = gt.leer(gt.SIGNIFICADOS, gt.COLUMNAS_SIGNIFICADOS)
    REAL["lexico"] = gt.leer(gt.LEXICO, gt.COLUMNAS_LEXICO)
    REAL["referencias"] = gt.leer(gt.REFERENCIAS, gt.COLUMNAS_REFERENCIAS)
    REAL["evidencia"] = gt.leer(gt.EVIDENCIA, gt.COLUMNAS_EVIDENCIA)
    REAL["culturales"] = gt.leer(gt.CULTURALES, gt.COLUMNAS_CULTURALES)
    REAL["categorias"] = gt.leer(gt.CATEGORIAS, categorias.COLUMNAS)
    REAL["revisiones"] = gt.leer(gt.REVISIONES, revision.COLUMNAS)
    REAL["colisiones_revisadas"] = gt.leer(gt.COLISIONES_REVISADAS, gt.COLUMNAS_COLISIONES)


def preparar_base():
    """Además de los CSV, genera una sola vez el proyecto reducido en una carpeta temporal (la usan las pruebas de main())."""
    preparar()
    if BASE:
        return
    BASE["temporal"] = tempfile.TemporaryDirectory()
    atexit.register(BASE["temporal"].cleanup)
    BASE["carpeta"] = Path(BASE["temporal"].name)
    preparar_proyecto_reducido(BASE["carpeta"])
    codigo, salida, error = ejecutar(BASE["carpeta"])
    if codigo != 0:
        raise RuntimeError(f"no se pudo generar el proyecto reducido: {salida}{error}")


def real(nombre):
    """Copia de los datos reales para romperla sin tocar los demás.

    Casi todos son listas de filas (diccionarios de texto): copiar cada fila basta y cuesta mucho menos que deepcopy.
    """
    datos = REAL[nombre]
    if isinstance(datos, list) and all(isinstance(fila, dict) for fila in datos[:1]):
        return [dict(fila) for fila in datos]
    return copy.deepcopy(datos)


def escribir_csv(ruta, columnas, filas):
    with ruta.open("w", encoding="utf-8", newline="") as f:
        escritor = csv.DictWriter(f, fieldnames=columnas, lineterminator="\n")
        escritor.writeheader()
        escritor.writerows(filas)


def preparar_proyecto_reducido(carpeta):
    """Copia los CSV y el README, con nombres-completos.csv reducido a los conceptos del léxico y una de cada 150 filas."""
    for nombre in ARCHIVOS_DE_ENTRADA:
        shutil.copy(AQUI / nombre, carpeta / nombre)
    conceptos = set(gt.CONCEPTOS)
    elegidas = [f for n, f in enumerate(REAL["completa"]) if f["nombre_ingles"].lower() in conceptos or n % 150 == 0]
    escribir_csv(carpeta / "nombres-completos.csv", gt.COLUMNAS_FUENTE_COMPLETA, elegidas)


def rutas_para(carpeta):
    """Las rutas de archivos de generar-tabla.py, apuntando a `carpeta`. Una prueba comprueba que no falte ninguna."""
    return {
        "CARPETA": carpeta, "ANOTADA": carpeta / "colores.csv", "COMPLETA": carpeta / "nombres-completos.csv",
        "SIGNIFICADOS": carpeta / "significados-idiomas.csv", "LEXICO": carpeta / "lexico-basico.csv",
        "REFERENCIAS": carpeta / "referencias.csv", "EVIDENCIA": carpeta / "evidencia.csv",
        "CULTURALES": carpeta / "significados-culturales.csv", "CATEGORIAS": carpeta / "categorias.csv",
        "CATEGORIAS_MD": carpeta / "categorias.md", "COBERTURA": carpeta / "cobertura.md", "REVISIONES": carpeta / "revisiones.csv", "COLISIONES_REVISADAS": carpeta / "colisiones-revisadas.csv",
        "REVISIONES_MD": carpeta / "revisiones.md", "VISOR_PLANTILLA": carpeta / "visor-plantilla.html",
        "REFS_MD": carpeta / "referencias.md",
        "AMBIGUEDAD": carpeta / "ambiguedad.md", "AUDITORIA": carpeta / "auditoria-nombres.md", "CHANGELOG": carpeta / "CHANGELOG.md", "DIR_EXPORTES": carpeta / "exportes", "README": carpeta / "README.md",
        "INDICE": carpeta / "colores-por-idioma.md", "DIR_IDIOMAS": carpeta / "por-idioma",
    }


def ejecutar(carpeta, *argumentos):
    """Corre main() de generar-tabla.py contra `carpeta`. Devuelve (código de salida, stdout, stderr)."""
    rutas = rutas_para(carpeta)
    salida, error = io.StringIO(), io.StringIO()
    with mock.patch.multiple(gt, **rutas), mock.patch.object(sys, "argv", ["generar-tabla.py", *argumentos]), \
            contextlib.redirect_stdout(salida), contextlib.redirect_stderr(error):
        try:
            codigo = gt.main()
        except SystemExit as salida_anticipada:  # sys.exit("texto") sale con 1 y escribe el texto en stderr
            if isinstance(salida_anticipada.code, str):
                print(salida_anticipada.code, file=error)
                codigo = 1
            else:
                codigo = salida_anticipada.code
    return codigo, salida.getvalue(), error.getvalue()


def primera(filas, **criterios):
    """Índice de la primera fila que cumple todos los criterios."""
    return next(i for i, f in enumerate(filas) if all(f[campo] == valor for campo, valor in criterios.items()))


def universo_revisable(**cambios):
    """Lo que se puede revisar según los CSV reales (o con alguno cambiado)."""
    datos = {"idiomas": gt.IDIOMAS, "anotada": real("anotada"), "lexico": real("lexico"), "categorias": real("categorias")}
    return revision.universo({**datos, **cambios})


class PruebaConErrores(unittest.TestCase):
    def assertSoloError(self, errores, fragmento):
        self.assertEqual(len(errores), 1, errores)
        self.assertIn(fragmento, errores[0])

    def assertHayError(self, errores, fragmento):
        self.assertTrue(any(fragmento in e for e in errores), f"ningún error contiene {fragmento!r}: {errores}")


def datos_reales():
    """Lo que generar-tabla.py le pasa a revision.py y a exportes.py, con los CSV reales (construir_datos, el mismo de main())."""
    return gt.construir_datos(real("anotada"), REAL["completa"], real("significados"), real("lexico"), real("referencias"),
                              real("evidencia"), real("culturales"), real("categorias"), gt.alias_de(REAL["anotada"]), cercanias=[])


def visor_real():
    """El HTML del visor con los datos reales: ya no se guarda en el repositorio, se construye con --visor."""
    if "visor" not in REAL:
        REAL["visor"] = revision.visor_html(datos_reales(), (AQUI / "visor-plantilla.html").read_text(encoding="utf-8"))
    return REAL["visor"]


def conllu_real():
    """Las frases de ejemplo en CoNLL-U con los datos reales: se construye con --conllu, ya no se guarda."""
    if "conllu" not in REAL:
        REAL["conllu"] = exportes.ejemplos_conllu(datos_reales())
    return REAL["conllu"]


def huellas_reales():
    if "huellas" not in REAL:
        REAL["huellas"] = revision.huellas(datos_reales())
    return REAL["huellas"]
