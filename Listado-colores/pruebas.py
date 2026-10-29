#!/usr/bin/env python3
"""Pruebas de Listado-colores. Uso, desde esta carpeta: python3 -m unittest -v pruebas

Solo biblioteca estándar. Dos tipos de prueba:

- Los validadores de generar-tabla.py se prueban en memoria: cada regla se rompe a propósito en una
  copia de los CSV reales y debe dar su error (y las rutas sin romper no dan ninguno).
- main() se prueba de punta a punta en una carpeta temporal con una copia de los CSV. Su
  nombres-completos.csv se reduce a unas 240 filas para no recalcular las 31.935 distancias; tiene todos
  los conceptos del léxico, así que todos los archivos generados salen idénticos a los del repositorio
  salvo el propio nombres-completos.csv. Nunca se escribe en la carpeta del proyecto.

La CI corre además `python3 generar-tabla.py --comprobar` sobre los archivos reales.
"""
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

AQUI = Path(__file__).resolve().parent
sys.dont_write_bytecode = True  # no dejar __pycache__ de los módulos de al lado
sys.path.insert(0, str(AQUI))
import ambiguedad  # noqa: E402
import auditoria  # noqa: E402
import categorias  # noqa: E402
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
                       "significados-culturales.csv", "categorias.csv", "referencias.csv", "revisiones.csv", "visor-plantilla.html",
                       "CHANGELOG.md", "README.md"]
REAL = {}  # los CSV reales, leídos una vez; cada prueba trabaja con copias (ver real())
BASE = {}  # la carpeta temporal con el proyecto reducido ya generado


def setUpModule():
    REAL["anotada"] = gt.leer(gt.ANOTADA, gt.COLUMNAS_ANOTADA)
    REAL["completa"] = gt.leer_completa()
    REAL["significados"] = gt.leer(gt.SIGNIFICADOS, gt.COLUMNAS_SIGNIFICADOS)
    REAL["lexico"] = gt.leer(gt.LEXICO, gt.COLUMNAS_LEXICO)
    REAL["referencias"] = gt.leer(gt.REFERENCIAS, gt.COLUMNAS_REFERENCIAS)
    REAL["evidencia"] = gt.leer(gt.EVIDENCIA, gt.COLUMNAS_EVIDENCIA)
    REAL["culturales"] = gt.leer(gt.CULTURALES, gt.COLUMNAS_CULTURALES)
    REAL["categorias"] = gt.leer(gt.CATEGORIAS, categorias.COLUMNAS)
    REAL["revisiones"] = gt.leer(gt.REVISIONES, revision.COLUMNAS)
    BASE["temporal"] = tempfile.TemporaryDirectory()
    BASE["carpeta"] = Path(BASE["temporal"].name)
    preparar_proyecto_reducido(BASE["carpeta"])
    codigo, salida, error = ejecutar(BASE["carpeta"])
    if codigo != 0:
        raise RuntimeError(f"no se pudo generar el proyecto reducido: {salida}{error}")


def tearDownModule():
    BASE["temporal"].cleanup()


def real(nombre):
    """Copia de los datos reales para romperla sin tocar los demás."""
    return copy.deepcopy(REAL[nombre])


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
        "CATEGORIAS_MD": carpeta / "categorias.md", "COBERTURA": carpeta / "cobertura.md", "REVISIONES": carpeta / "revisiones.csv",
        "REVISIONES_MD": carpeta / "revisiones.md", "VISOR_PLANTILLA": carpeta / "visor-plantilla.html",
        "VISOR": carpeta / "visor-revision.html", "REFS_MD": carpeta / "referencias.md",
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


class DatosReales(unittest.TestCase):
    """Sin romper nada, los validadores no dan errores: es lo que hace significativas las pruebas siguientes."""

    def test_los_csv_reales_pasan_todos_los_validadores(self):
        errores, usadas = [], set()
        gt.validar_anotada(real("anotada"), errores)
        gt.validar_completa(real("completa"), errores)
        gt.validar_significados(real("significados"), real("anotada"), errores)
        gt.validar_lexico(real("lexico"), real("anotada"), real("completa"), errores)
        ids = gt.validar_referencias(real("referencias"), errores)
        gt.validar_evidencia(real("evidencia"), real("anotada"), ids, errores, usadas)
        gt.validar_culturales(real("culturales"), real("anotada"), ids, errores, usadas)
        categorias.validar(real("categorias"), real("anotada"), real("lexico"), [c for c, _ in gt.IDIOMAS], gt.CONCEPTOS, errores)
        revision.validar(real("revisiones"), universo_revisable(), [c for c, _ in gt.IDIOMAS], errores)
        self.assertEqual(errores, [])
        self.assertEqual(usadas, ids, "ninguna referencia sin usar")

    def test_tamanos_esperados(self):
        self.assertEqual(len(REAL["anotada"]), 148)
        self.assertEqual(len(gt.IDIOMAS), 30)
        self.assertEqual(len(REAL["significados"]), 148 * 29)
        self.assertEqual(len(REAL["evidencia"]), 148)

    def test_los_ambitos_usan_idiomas_conocidos(self):
        codigos = {c for c, _ in gt.IDIOMAS}
        for ambito, (etiqueta, idiomas) in gt.AMBITOS.items():
            self.assertTrue(etiqueta.strip(), ambito)
            self.assertTrue(idiomas and set(idiomas) <= codigos, ambito)


class ColoresCsv(PruebaConErrores):
    def validar(self, filas):
        errores = []
        gt.validar_anotada(filas, errores)
        return errores

    def test_campo_vacio(self):
        filas = real("anotada")
        filas[3]["nombre_es"] = "  "
        self.assertSoloError(self.validar(filas), "línea 5: hay campos vacíos")

    def test_barra_vertical_o_salto_de_linea(self):
        for texto in ("a|b", "a\nb"):
            filas = real("anotada")
            filas[0]["significado_mistico"] += texto
            self.assertSoloError(self.validar(filas), "no puede llevar '|' ni saltos de línea")

    def test_nombre_con_mayuscula_o_espacio(self):
        for nombre in ("AliceBlue", "alice blue"):
            filas = real("anotada")
            filas[0]["nombre_ingles"] = nombre
            self.assertHayError(self.validar(filas), "minúsculas sin espacios")

    def test_nombre_repetido(self):
        filas = real("anotada")
        filas.insert(1, dict(filas[0]))
        self.assertSoloError(self.validar(filas), "nombre repetido: 'aliceblue'")

    def test_forma_natural_que_no_corresponde_al_nombre(self):
        for natural in ("alice blu", "Alice blue", "aliceblue ", "alice  blue"):
            filas = real("anotada")
            filas[0]["forma_natural_en"] = natural
            self.assertHayError(self.validar(filas), "forma_natural_en debe ser")

    def test_hex_invalido(self):
        for valor in ("#f0f8ff", "F0F8FF", "#F0F8F", "#F0F8FFF", "#GGGGGG"):
            filas = real("anotada")
            filas[0]["hex"] = valor
            self.assertSoloError(self.validar(filas), "hex inválido")

    def test_orden_roto(self):
        filas = real("anotada")
        filas[0], filas[1] = filas[1], filas[0]
        self.assertSoloError(self.validar(filas), "ordenadas por nombre_ingles")


class NombresCompletos(PruebaConErrores):
    def validar(self, filas):
        errores = []
        gt.validar_completa(filas, errores)
        return errores

    @staticmethod
    def fila(nombre, color="#000000", curada=""):
        return {"nombre_ingles": nombre, "hex": color, "curada": curada}

    def test_nombres_validos(self):
        self.assertEqual(self.validar([self.fila("Alpha"), self.fila("beta", curada="x"), self.fila("Gamma")]), [])

    def test_nombre_vacio_o_con_espacios_sobrantes(self):
        for nombre in ("", "  ", " Alpha", "Alpha "):
            self.assertHayError(self.validar([self.fila(nombre)]), "nombre vacío o con espacios sobrantes")

    def test_caracteres_prohibidos(self):
        for caracter in ('|', ',', '"', "\n"):
            self.assertHayError(self.validar([self.fila(f"Al{caracter}pha")]), "no puede llevar")

    def test_nombre_repetido_sin_distinguir_mayusculas(self):
        self.assertSoloError(self.validar([self.fila("Foo"), self.fila("foo")]), "nombre repetido")

    def test_hex_invalido(self):
        self.assertSoloError(self.validar([self.fila("Alpha", "#abcdef")]), "hex inválido")

    def test_curada_solo_vacia_o_x(self):
        self.assertSoloError(self.validar([self.fila("Alpha", curada="si")]), "curada debe ser vacía o 'x'")

    def test_orden_roto_sin_distinguir_mayusculas(self):
        self.assertSoloError(self.validar([self.fila("beta"), self.fila("Alpha")]), "ordenadas por nombre")
        self.assertEqual(self.validar([self.fila("Alpha"), self.fila("beta")]), [])


class AliasConElMismoSignificado(PruebaConErrores):
    def validar(self, anotada=None, traducidos=None):
        errores = []
        gt.validar_alias(anotada or real("anotada"), traducidos or real("significados"), errores)
        return errores

    def test_los_datos_reales_no_tienen_alias_con_significados_distintos(self):
        self.assertEqual(self.validar(), [])

    def test_aqua_y_cyan_en_espanol(self):
        for campo in ("significado_psicologico", "significado_mistico"):
            anotada = real("anotada")
            anotada[primera(anotada, nombre_ingles="aqua")][campo] += " distinto"
            self.assertSoloError(self.validar(anotada=anotada), "aqua y cyan tienen el mismo hex y deben tener los mismos significados en español")

    def test_fuchsia_y_magenta_en_otro_idioma(self):
        traducidos = real("significados")
        traducidos[primera(traducidos, idioma="ja", nombre_ingles="fuchsia")]["significado_mistico"] += "。"
        self.assertSoloError(self.validar(traducidos=traducidos), "fuchsia y magenta tienen el mismo hex y deben tener los mismos significados; difieren en ja")

    def test_gray_y_grey_tambien(self):
        traducidos = real("significados")
        for idioma in ("fr", "de"):
            traducidos[primera(traducidos, idioma=idioma, nombre_ingles="grey")]["significado_psicologico"] += "."
        self.assertSoloError(self.validar(traducidos=traducidos), "gray y grey tienen el mismo hex y deben tener los mismos significados; difieren en de, fr")

    def test_dos_colores_con_distinto_hex_pueden_decir_cosas_distintas(self):
        anotada = real("anotada")
        anotada[primera(anotada, nombre_ingles="red")]["significado_mistico"] += " distinto"
        self.assertEqual(self.validar(anotada=anotada), [])


class Significados(PruebaConErrores):
    def validar(self, filas):
        errores = []
        gt.validar_significados(filas, real("anotada"), errores)
        return errores

    def test_falta_una_combinacion(self):
        filas = real("significados")
        del filas[10]
        self.assertSoloError(self.validar(filas), "faltan 1 combinaciones idioma/color")

    def test_significado_sin_traducir(self):
        filas = real("significados")
        es = {f["nombre_ingles"]: f for f in REAL["anotada"]}
        filas[0]["significado_mistico"] = es[filas[0]["nombre_ingles"]]["significado_mistico"]
        self.assertSoloError(self.validar(filas), "significado_mistico de")

    def test_idioma_desconocido_o_espanol(self):
        for idioma in ("xx", "es"):
            filas = real("significados")
            filas[0]["idioma"] = idioma
            self.assertHayError(self.validar(filas), "idioma desconocido o español")

    def test_color_que_no_esta_en_colores_csv(self):
        filas = real("significados")
        filas[0]["nombre_ingles"] = "nocolor"
        self.assertHayError(self.validar(filas), "'nocolor' no está en colores.csv")

    def test_fila_repetida(self):
        filas = real("significados")
        filas.insert(1, dict(filas[0]))
        self.assertSoloError(self.validar(filas), "repetido")

    def test_campo_vacio_con_espacios_sobrantes_o_con_barra(self):
        for texto, fragmento in (("", "vacío o con espacios"), (" x ", "vacío o con espacios"), ("a|b", "no puede llevar '|'")):
            filas = real("significados")
            filas[0]["significado_psicologico"] = texto
            self.assertSoloError(self.validar(filas), fragmento)

    def test_orden_roto(self):
        filas = real("significados")
        filas[0], filas[1] = filas[1], filas[0]
        self.assertSoloError(self.validar(filas), "ordenadas por nombre_ingles")


class Lexico(PruebaConErrores):
    def validar(self, filas, completa=None):
        errores = []
        gt.validar_lexico(filas, real("anotada"), REAL["completa"] if completa is None else completa, errores)
        return errores

    def test_letras_latinas_en_un_idioma_de_otra_escritura(self):
        filas = real("lexico")
        filas[primera(filas, idioma="ru", tipo="lema")]["forma"] = "red"
        self.assertSoloError(self.validar(filas), "letras latinas en ru")

    def test_rasgos_de_flexion_no_validos(self):
        filas = real("lexico")
        filas[primera(filas, tipo="flexion")]["rasgos"] = "Gender=Foo"
        self.assertSoloError(self.validar(filas), "rasgos de flexión no válidos")

    def test_un_lema_no_lleva_rasgos(self):
        filas = real("lexico")
        filas[primera(filas, tipo="lema")]["rasgos"] = "Number=Plur"
        self.assertSoloError(self.validar(filas), "un lema no lleva rasgos")

    def test_un_lema_solo_puede_llevar_el_rasgo_de_propuesta_de_ia(self):
        filas = real("lexico")
        i = primera(filas, idioma="es", concepto="white", tipo="lema")
        filas[i]["rasgos"] = "origen=ia"
        self.assertEqual(self.validar(filas), [], "la marca de propuesta sin revisar es válida en un lema")
        for rasgos in ("origen=nativo", "origen=IA", "region=GB"):
            filas[i]["rasgos"] = rasgos
            self.assertSoloError(self.validar(filas), "un lema no lleva rasgos (solo 'origen=ia'")

    def test_los_lemas_de_ia_reales_son_solo_los_de_los_huecos(self):
        # Cada lema con origen=ia llena un hueco: el idioma no tenía lema para ese concepto en master antes de la propuesta.
        propuestas = [(f["idioma"], f["concepto"]) for f in REAL["lexico"] if f["tipo"] == "lema" and f["rasgos"] == "origen=ia"]
        self.assertEqual(len(propuestas), len(set(propuestas)))
        self.assertFalse(any(c in gt.BASICOS for _, c in propuestas), "los conceptos básicos nunca son propuestas")

    def test_dos_lemas_para_el_mismo_concepto_e_idioma(self):
        filas = real("lexico")
        i = primera(filas, tipo="lema")
        filas.insert(i + 1, {**filas[i], "forma": "otro"})
        self.assertSoloError(self.validar(filas), "tiene más de un lema")

    def test_falta_un_termino_basico(self):
        filas = real("lexico")
        del filas[primera(filas, concepto="white", idioma="sw", tipo="lema")]
        self.assertSoloError(self.validar(filas), "faltan 1 términos básicos")

    def test_formas_sin_lema(self):
        filas = real("lexico")
        del filas[primera(filas, concepto="gold", idioma="nl", tipo="lema")]
        self.assertSoloError(self.validar(filas), "gold/nl tiene formas pero no lema")

    def test_fila_repetida(self):
        filas = real("lexico")
        i = primera(filas, tipo="sinonimo")
        filas.insert(i + 1, dict(filas[i]))
        self.assertSoloError(self.validar(filas), "fila repetida")

    def test_la_misma_forma_con_otros_rasgos_no_es_repetida(self):
        # Pasa en checo y polaco: la forma de dos flexiones coincide y solo cambian los rasgos.
        filas = real("lexico")
        i = primera(filas, tipo="flexion")
        otros = [r for r in gt.RASGOS_FLEXION if r != filas[i]["rasgos"]]
        filas.insert(i + 1, {**filas[i], "rasgos": otros[0]})
        self.assertEqual(self.validar(filas), [])

    def test_concepto_idioma_o_tipo_desconocido(self):
        for campo, valor in (("concepto", "nocolor"), ("idioma", "xx"), ("tipo", "otro")):
            filas = real("lexico")
            filas[0][campo] = valor
            self.assertHayError(self.validar(filas), "concepto, idioma o tipo desconocido")

    def test_forma_vacia_con_espacios_comillas_o_barra(self):
        for forma in ("", " x", "x ", 'a"b', "a|b"):
            filas = real("lexico")
            filas[primera(filas, tipo="sinonimo")]["forma"] = forma
            self.assertHayError(self.validar(filas), "forma vacía")

    def test_etiqueta_de_sinonimo_o_variante(self):
        filas = real("lexico")
        i = primera(filas, tipo="sinonimo")
        filas[i]["rasgos"] = "foo=bar"
        self.assertSoloError(self.validar(filas), "etiqueta no válida")
        filas[i]["rasgos"] = "registro=coloquial"
        self.assertEqual(self.validar(filas), [])

    def test_orden_roto(self):
        filas = real("lexico")
        filas[0], filas[-1] = filas[-1], filas[0]
        self.assertSoloError(self.validar(filas), "ordenadas por concepto, idioma y tipo")

    def test_concepto_sin_hex_de_referencia(self):
        errores = []
        gt.validar_lexico(real("lexico"), [], [], errores)
        sin_hex = [e for e in errores if "no está en colores.csv ni en nombres-completos.csv" in e]
        self.assertEqual(len(sin_hex), len(gt.CONCEPTOS))

    def test_hex_de_concepto_prefiere_el_color_css(self):
        anotada = [{"nombre_ingles": "gold", "hex": "#FFD700"}]
        completa = [{"nombre_ingles": "Gold", "hex": "#111111"}, {"nombre_ingles": "Lilac", "hex": "#CEA2FD"}]
        self.assertEqual(gt.hex_de_concepto("gold", anotada, completa), "#FFD700")
        self.assertEqual(gt.hex_de_concepto("lilac", anotada, completa), "#CEA2FD")
        self.assertIsNone(gt.hex_de_concepto("nocolor", anotada, completa))


class Referencias(PruebaConErrores):
    def validar(self, filas):
        errores = []
        ids = gt.validar_referencias(filas, errores)
        return errores, ids

    def test_devuelve_los_ids(self):
        errores, ids = self.validar(real("referencias"))
        self.assertEqual(errores, [])
        self.assertEqual(ids, {f["id"] for f in REAL["referencias"]})

    def test_id_no_valido(self):
        for i in ("Foo_Bar", "foo bar", "-foo", "foo--bar", ""):
            filas = real("referencias")
            filas[0]["id"] = i
            self.assertHayError(self.validar(filas)[0], "id no válido")

    def test_id_repetido(self):
        filas = real("referencias")
        filas.insert(1, dict(filas[0]))
        self.assertSoloError(self.validar(filas)[0], "id repetido")

    def test_tipo_o_verificacion_desconocidos(self):
        for campo in ("tipo", "verificacion"):
            filas = real("referencias")
            filas[0][campo] = "otro"
            self.assertSoloError(self.validar(filas)[0], "tipo o verificacion no válidos")

    def test_cita_vacia_o_con_barra(self):
        for cita in ("", "a|b", "a\nb"):
            filas = real("referencias")
            filas[0]["cita"] = cita
            self.assertSoloError(self.validar(filas)[0], "cita vacía o con")

    def test_url_que_no_es_https(self):
        filas = real("referencias")
        filas[primera(filas, tipo="web")]["url"] = "http://ejemplo.org"
        self.assertSoloError(self.validar(filas)[0], "la url debe empezar por https://")

    def test_pagina_web_sin_url(self):
        filas = real("referencias")
        filas[primera(filas, tipo="web")]["url"] = ""
        self.assertSoloError(self.validar(filas)[0], "una página web necesita url")

    def test_orden_roto(self):
        filas = real("referencias")
        filas[0], filas[1] = filas[1], filas[0]
        self.assertSoloError(self.validar(filas)[0], "ordenadas por id")


class Evidencia(PruebaConErrores):
    def validar(self, filas):
        errores, usadas = [], set()
        ids = gt.validar_referencias(real("referencias"), [])
        gt.validar_evidencia(filas, real("anotada"), ids, errores, usadas)
        return errores, usadas

    def test_registra_las_referencias_usadas(self):
        errores, usadas = self.validar(real("evidencia"))
        self.assertEqual(errores, [])
        self.assertIn("jonauskaite2020", usadas)
        self.assertIn("leland2016", usadas)

    def test_debe_tener_una_fila_por_color_en_su_orden(self):
        filas = real("evidencia")
        filas.reverse()
        self.assertSoloError(self.validar(filas)[0], "una fila por color de colores.csv")
        self.assertSoloError(self.validar(filas[:-1])[0], "una fila por color de colores.csv")

    def test_base_no_valida(self):
        for campo in ("base_psicologico", "base_mistico"):
            filas = real("evidencia")
            filas[0][campo] = "otra"
            self.assertHayError(self.validar(filas)[0], "base no válida")

    def test_color_con_estudios_sin_citar_el_estudio(self):
        filas = real("evidencia")
        filas[primera(filas, nombre_ingles="red")]["referencias_psicologico"] = ""
        self.assertSoloError(self.validar(filas)[0], "tiene estudios pero no cita jonauskaite2020")

    def test_psicologico_derivado_sin_referencias(self):
        filas = real("evidencia")
        filas[primera(filas, base_psicologico="derivado")]["referencias_psicologico"] = "jonauskaite2020"
        self.assertSoloError(self.validar(filas)[0], "un significado derivado no lleva referencias psicológicas")

    def test_mistico_derivado_sin_referencias(self):
        filas = real("evidencia")
        filas[primera(filas, base_mistico="derivado")]["referencias_mistico"] = "leland2016"
        self.assertSoloError(self.validar(filas)[0], "un significado derivado no lleva referencias místicas")

    def test_tradicion_moderna_debe_citar_a_leland(self):
        filas = real("evidencia")
        filas[primera(filas, base_mistico="tradicion_moderna")]["referencias_mistico"] = ""
        self.assertSoloError(self.validar(filas)[0], "es tradición moderna pero no cita leland2016")

    def test_referencias_desordenadas_o_repetidas(self):
        ids = sorted(f["id"] for f in REAL["referencias"])
        for lista in (f"{ids[1]};{ids[0]}", f"{ids[0]};{ids[0]}"):
            filas = real("evidencia")
            filas[primera(filas, base_mistico="tradicion")]["referencias_mistico"] = lista
            self.assertSoloError(self.validar(filas)[0], "debe ir ordenado y sin repetidos")

    def test_referencia_desconocida(self):
        filas = real("evidencia")
        filas[primera(filas, base_mistico="tradicion")]["referencias_mistico"] = "no-existe"
        self.assertSoloError(self.validar(filas)[0], "referencia desconocida 'no-existe'")

    def test_los_colores_con_estudios_son_exactamente_los_indicados(self):
        filas = real("evidencia")
        i = primera(filas, nombre_ingles="red")
        filas[i]["base_psicologico"], filas[i]["referencias_psicologico"] = "derivado", ""
        self.assertSoloError(self.validar(filas)[0], "los colores con estudios deben ser exactamente")


class Culturales(PruebaConErrores):
    def validar(self, filas):
        errores, usadas = [], set()
        ids = gt.validar_referencias(real("referencias"), [])
        gt.validar_culturales(filas, real("anotada"), ids, errores, usadas)
        return errores

    def test_color_ambito_o_solidez_no_validos(self):
        for campo, valor in (("color", "nocolor"), ("ambito", "atlantida"), ("solidez", "total")):
            filas = real("culturales")
            filas[0][campo] = valor
            self.assertSoloError(self.validar(filas), "color, ámbito o solidez no válidos")

    def test_matiz_vacio_o_con_barra(self):
        for matiz in ("", "a|b", "a\nb"):
            filas = real("culturales")
            filas[0]["matiz"] = matiz
            self.assertSoloError(self.validar(filas), "matiz vacío o con")

    def test_sin_referencias(self):
        filas = real("culturales")
        filas[0]["referencias"] = ""
        self.assertSoloError(self.validar(filas), "necesita al menos una referencia")

    def test_referencia_desconocida(self):
        filas = real("culturales")
        filas[0]["referencias"] = "no-existe"
        self.assertSoloError(self.validar(filas), "referencia desconocida 'no-existe'")

    def test_orden_roto(self):
        filas = real("culturales")
        filas[0], filas[-1] = filas[-1], filas[0]
        self.assertSoloError(self.validar(filas), "ordenadas por color")


class Categorias(PruebaConErrores):
    def validar(self, filas):
        errores = []
        categorias.validar(filas, real("anotada"), real("lexico"), [c for c, _ in gt.IDIOMAS], gt.CONCEPTOS, errores)
        return errores

    def test_las_filas_reales_cubren_ingles_y_espanol(self):
        filas = real("categorias")
        self.assertEqual({f["idioma"] for f in filas}, {"en", "es"})
        self.assertEqual({f["categoria"] for f in filas}, set(categorias.UPOS))
        self.assertEqual(len(filas), len(REAL["categorias"]))
        en = {f["concepto"] for f in filas if f["idioma"] == "en"}
        self.assertEqual(en, {f["nombre_ingles"] for f in REAL["anotada"] if " " not in f["forma_natural_en"]}
                         | {"lilac", "burgundy", "cream", "scarlet", "amber"})
        es = {f["concepto"] for f in filas if f["idioma"] == "es"}
        self.assertEqual(es, {f["concepto"] for f in REAL["lexico"]
                              if f["idioma"] == "es" and f["tipo"] == "lema" and " " not in f["forma"]})

    def test_cada_palabra_tiene_adjetivo_y_las_demas_son_lecturas_extra(self):
        # Una palabra de color siempre puede ser adjetivo (es lo que la hace palabra de color).
        for (idioma, concepto), lista in categorias.por_concepto(real("categorias")).items():
            self.assertIn("ADJ", [f["categoria"] for f in lista], (idioma, concepto))

    def test_idioma_o_concepto_desconocidos(self):
        for campo, valor in (("idioma", "xx"), ("concepto", "nocolor")):
            filas = real("categorias")
            filas[0][campo] = valor
            self.assertHayError(self.validar(filas), "idioma o concepto desconocido")

    def test_un_concepto_css_sin_forma_en_el_idioma(self):
        filas = real("categorias")
        filas[0].update(idioma="es", concepto="aliceblue")  # color CSS que no está en el léxico en español
        self.assertHayError(self.validar(filas), "aliceblue no tiene forma en es")

    def test_la_forma_debe_ser_la_del_color_o_la_del_lema(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="aqua", categoria="ADJ")
        filas[i]["forma"] = "Aqua"
        self.assertHayError(self.validar(filas), "la forma de aqua en en es 'aqua', no 'Aqua'")
        filas = real("categorias")
        i = primera(filas, idioma="es", concepto="lime", categoria="VERB")
        filas[i]["forma"] = "limón"
        self.assertHayError(self.validar(filas), "la forma de lime en es es 'lima', no 'limón'")

    def test_la_forma_debe_ser_una_sola_palabra(self):
        filas = real("categorias")
        i = primera(filas, idioma="es", concepto="red", categoria="ADJ")
        filas[i].update(concepto="lightblue", forma="azul claro", ocurrencia="azul claro", ejemplo="Pintó la pared azul claro.")
        errores = self.validar(filas)
        self.assertHayError(errores, "la forma debe ser una sola palabra")
        self.assertHayError(errores, "no son de una sola palabra")

    def test_categoria_desconocida(self):
        filas = real("categorias")
        filas[0]["categoria"] = "ADV"
        self.assertHayError(self.validar(filas), "categoría desconocida 'ADV'")

    def test_la_ocurrencia_es_la_forma_con_pocas_letras_mas(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="black", categoria="VERB")
        self.assertEqual(filas[i]["ocurrencia"], "blacked")  # dos letras más: vale
        filas[i].update(ocurrencia="blackened", ejemplo="They blackened the windows.")  # cuatro más: vale
        self.assertEqual(self.validar(filas), [])
        filas[i].update(ocurrencia="blackeners", ejemplo="The blackeners came.")  # seis más: no
        self.assertHayError(self.validar(filas), "debe ser la forma 'black' con a lo sumo 4 letras más")
        filas[i].update(ocurrencia="darken", ejemplo="They darken the windows.")
        self.assertHayError(self.validar(filas), "debe ser la forma 'black'")

    def test_un_nombre_propio_lleva_mayuscula(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="peru", categoria="PROPN")
        filas[i].update(ocurrencia="peru", ejemplo="She was born in peru.")
        self.assertHayError(self.validar(filas), "un nombre propio se escribe con mayúscula inicial")

    def test_ejemplo_vacio_con_barra_o_sin_punto_final(self):
        for ejemplo, esperado in (("", "ejemplo vacío"), (" He wore a beige coat.", "espacios sobrantes"),
                                  ("He wore a beige | coat.", "'|' o saltos"), ("He wore a\nbeige coat.", "saltos de línea"),
                                  ("He wore a beige coat", "debe terminar en")):
            with self.subTest(ejemplo):
                filas = real("categorias")
                i = primera(filas, idioma="en", concepto="beige", categoria="ADJ")
                filas[i]["ejemplo"] = ejemplo
                self.assertHayError(self.validar(filas), esperado)

    def test_toda_palabra_de_color_tiene_adjetivo(self):
        filas = [f for f in real("categorias") if not (f["idioma"] == "es" and f["concepto"] == "white" and f["categoria"] == "ADJ")]
        self.assertSoloError(self.validar(filas), "es/white no tiene la categoría ADJ")

    def test_los_signos_finales_validos(self):
        for final in ".!?":
            filas = real("categorias")
            i = primera(filas, idioma="en", concepto="beige", categoria="ADJ")
            filas[i]["ejemplo"] = f"He wore a beige coat{final}"
            self.assertEqual(self.validar(filas), [], final)

    def test_la_frase_contiene_la_ocurrencia_como_palabra_entera(self):
        filas = real("categorias")
        i = primera(filas, idioma="es", concepto="lime", categoria="VERB")
        for ejemplo in ("Ella pinta las uñas.", "Ella limaba las uñas.", "Ella sublima las uñas."):
            filas[i]["ejemplo"] = ejemplo
            self.assertHayError(self.validar(filas), "una vez como palabra entera, no 0")
        filas[i]["ejemplo"] = "Lima es la capital."  # la mayúscula inicial no es la ocurrencia 'lima': no vale
        self.assertHayError(self.validar(filas), "una vez como palabra entera, no 0")

    def test_la_ocurrencia_sale_una_sola_vez_y_es_una_palabra_sin_signos(self):
        # ejemplos.conllu marca un solo token por frase: una ocurrencia repetida o con guion no tendría un token claro.
        filas = real("categorias")
        i = primera(filas, idioma="es", concepto="lime", categoria="VERB")
        filas[i]["ejemplo"] = "Ella lima las uñas y lima la madera."
        self.assertSoloError(self.validar(filas), "una vez como palabra entera, no 2")
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="black", categoria="ADJ")
        filas[i].update(ocurrencia="black-", ejemplo="She wore a black- dress.")
        self.assertHayError(self.validar(filas), "debe tener solo letras y dígitos")

    def test_una_categoria_repetida_para_el_mismo_concepto(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="aqua", categoria="ADJ")
        filas.insert(i + 1, dict(filas[i]))
        self.assertHayError(self.validar(filas), "repetida en/aqua/ADJ")

    def test_el_orden_de_las_categorias_es_adj_noun_propn_verb(self):
        self.assertEqual(categorias.UPOS, ["ADJ", "NOUN", "PROPN", "VERB"])
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="olive", categoria="PROPN")
        verbo = dict(filas[i], categoria="VERB", ocurrencia="Olive", ejemplo="They Olive the walls.")
        filas.insert(i + 1, verbo)  # ADJ, NOUN, PROPN, VERB: vale
        self.assertEqual(self.validar(filas), [])
        filas[i], filas[i + 1] = filas[i + 1], filas[i]  # ADJ, NOUN, VERB, PROPN: no
        self.assertSoloError(self.validar(filas), "ordenadas por idioma, concepto")

    def test_el_orden_es_idioma_concepto_y_categoria(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="aqua", categoria="ADJ")
        filas[i], filas[i + 1] = filas[i + 1], filas[i]  # NOUN antes que ADJ
        self.assertSoloError(self.validar(filas), "ordenadas por idioma, concepto")
        filas = real("categorias")
        filas = [f for f in filas if f["idioma"] == "es"] + [f for f in filas if f["idioma"] == "en"]
        self.assertSoloError(self.validar(filas), "ordenadas por idioma, concepto")

    def test_a_un_idioma_presente_no_le_puede_faltar_ninguna_palabra(self):
        filas = [f for f in real("categorias") if not (f["idioma"] == "es" and f["concepto"] == "lime")]
        self.assertSoloError(self.validar(filas), "a es le faltan 1 palabras (por ejemplo lime)")

    def test_un_idioma_ausente_no_da_error_de_cobertura(self):
        self.assertEqual(self.validar([f for f in real("categorias") if f["idioma"] == "en"]), [])
        self.assertEqual(self.validar([]), [])

    def test_a_ingles_le_faltan_los_colores_css_y_los_del_lexico(self):
        for concepto in ("azure", "lilac"):
            filas = [f for f in real("categorias") if not (f["idioma"] == "en" and f["concepto"] == concepto)]
            self.assertSoloError(self.validar(filas), f"a en le faltan 1 palabras (por ejemplo {concepto})")

    def test_las_formas_de_ejemplo_siguen_a_las_del_lexico(self):
        # Si el léxico cambia el lema de «lime» en español, categorias.csv deja de coincidir y lo dice.
        lexico = real("lexico")
        i = primera(lexico, idioma="es", concepto="lime", tipo="lema")
        lexico[i]["forma"] = "lima verde"
        errores = []
        categorias.validar(real("categorias"), real("anotada"), lexico, [c for c, _ in gt.IDIOMAS], gt.CONCEPTOS, errores)
        self.assertHayError(errores, "la forma de lime en es es 'lima verde', no 'lima'")

    def test_el_informe_cuenta_las_categorias_de_los_datos_reales(self):
        filas = real("categorias")
        texto = categorias.informe(filas, gt.IDIOMAS)
        for codigo, nombre in (("en", "Inglés"), ("es", "Español")):
            propias = [f for f in filas if f["idioma"] == codigo]
            cuentas = [sum(1 for f in propias if f["categoria"] == u) for u in categorias.UPOS]
            palabras = {f["concepto"] for f in propias}
            varias = sum(1 for c in palabras if sum(1 for f in propias if f["concepto"] == c) > 1)
            fila = f"| {nombre} (`{codigo}`) | {len(palabras)} | " + " | ".join(map(str, cuentas)) + f" | {varias} |"
            self.assertIn(fila, texto)
            self.assertIn(f"## {nombre} (`{codigo}`)", texto)
        self.assertNotIn("(`fr`)", texto)
        self.assertIn("| `lime` | lima | `VERB` | Ella lima las uñas antes de pintarlas. |", texto)

    def test_la_nota_de_los_nueve_nombres_css_coincide_con_los_ejemplos(self):
        # Si se agrega o se cambia un ejemplo de ADJ de contexto web, la nota del informe y del README deben seguirlo.
        web = re.compile(r"\b(page|header|panel|button|text color|border)\b", re.I)
        nueve = sorted(f["concepto"] for f in REAL["categorias"] if f["idioma"] == "en" and f["categoria"] == "ADJ" and web.search(f["ejemplo"]))
        informe = categorias.informe(real("categorias"), gt.IDIOMAS)
        readme = (AQUI / "README.md").read_text(encoding="utf-8")
        self.assertEqual(len(nueve), 9)
        for nombre in nueve:
            self.assertIn(f"`{nombre}`", informe.split("Nueve nombres CSS")[1].split("\n")[0])
            self.assertIn(f"`{nombre}`", readme.split("Nueve nombres CSS")[1].split("\n")[0])

    def test_el_informe_escapa_la_barra_vertical(self):
        filas = [dict(real("categorias")[0], ejemplo="Una | barra.")]
        self.assertIn("Una &#124; barra.", categorias.informe(filas, gt.IDIOMAS))


FILA_REVISADA = {"idioma": "es", "alcance": "nombres", "elemento": "aliceblue", "veredicto": "correcto",
                 "revisor": "Ana", "fecha": "2026-10-23", "nota": ""}


def datos_reales():
    """Lo que generar-tabla.py le pasa a revision.py y a exportes.py, con los CSV reales."""
    return {"idiomas": gt.IDIOMAS, "anotada": real("anotada"), "lexico": real("lexico"), "categorias": real("categorias"),
            "significados": {(f["idioma"], f["nombre_ingles"]): (f["significado_psicologico"], f["significado_mistico"])
                             for f in REAL["significados"]},
            "hex_concepto": {c: gt.hex_de_concepto(c, REAL["anotada"], REAL["completa"]) for c in gt.CONCEPTOS},
            "hex_cotidiano": {c: gt.hex_cotidiano_de_concepto(c, REAL["completa"]) for c in gt.CONCEPTOS}}


def huellas_reales():
    if "huellas" not in REAL:
        REAL["huellas"] = revision.huellas(datos_reales())
    return REAL["huellas"]


class Revision(PruebaConErrores):
    def validar(self, filas, **cambios):
        errores = []
        revision.validar(filas, universo_revisable(**cambios), [c for c, _ in gt.IDIOMAS], errores)
        return errores

    def fila(self, **campos):
        fila = {**FILA_REVISADA, **campos}
        fila.setdefault("huella", huellas_reales().get((fila["idioma"], fila["alcance"]), {}).get(fila["elemento"], "0123abcd"))
        return fila

    def test_el_registro_real_empieza_vacio(self):
        self.assertEqual(REAL["revisiones"], [], "nadie ha revisado nada todavía: si cambia, actualiza README y revisiones.md")

    def test_lo_que_se_puede_revisar(self):
        universo = universo_revisable()
        for codigo, _ in gt.IDIOMAS:
            self.assertEqual(len(universo[(codigo, "nombres")]), 148)
            self.assertEqual(universo[(codigo, "significados")], universo[(codigo, "nombres")])
            conceptos = list(dict.fromkeys(f["concepto"] for f in REAL["lexico"] if f["idioma"] == codigo))
            self.assertEqual(universo[(codigo, "lexico")], conceptos)
        self.assertEqual({i for i, a in universo if a == "categorias"}, {"en", "es"})
        self.assertEqual(len(universo[("en", "categorias")]), 61)
        self.assertEqual(len(universo[("es", "categorias")]), 32)
        self.assertNotIn(("fr", "categorias"), universo)

    def test_una_fila_valida_y_el_orden_no_importa(self):
        filas = [self.fila(), self.fila(elemento="red", veredicto="corregir", nota="mejor «rojo»"),
                 self.fila(idioma="en", elemento="blue"), self.fila(alcance="lexico", elemento="white"),
                 self.fila(alcance="categorias", elemento="lilac", idioma="en"), self.fila(revisor="Luis")]
        self.assertEqual(self.validar(filas), [])
        self.assertEqual(self.validar(filas[::-1]), [])

    def test_idioma_alcance_o_elemento_desconocidos(self):
        self.assertSoloError(self.validar([self.fila(idioma="xx")]), "idioma desconocido 'xx'")
        self.assertSoloError(self.validar([self.fila(alcance="todo")]), "alcance desconocido 'todo'")
        self.assertSoloError(self.validar([self.fila(elemento="nocolor")]), "'nocolor' no es un elemento de nombres en es")
        # El léxico solo tiene conceptos propios: un color CSS que no está en él no vale.
        self.assertSoloError(self.validar([self.fila(alcance="lexico", elemento="aliceblue")]),
                             "'aliceblue' no es un elemento de lexico en es")

    def test_categorias_solo_en_los_idiomas_que_las_tienen(self):
        self.assertSoloError(self.validar([self.fila(alcance="categorias", idioma="fr", elemento="white")]),
                             "categorias no tiene datos en fr")

    def test_veredicto_desconocido(self):
        self.assertSoloError(self.validar([self.fila(veredicto="quizá")]), "veredicto desconocido 'quizá'")

    def test_el_revisor_no_puede_estar_vacio_ni_llevar_espacios_o_saltos(self):
        for revisor in ("", " ", " Ana", "Ana ", "A\nna"):
            self.assertSoloError(self.validar([self.fila(revisor=revisor)]), "revisor vacío")

    def test_la_fecha_es_aaaa_mm_dd_y_existe(self):
        for fecha in ("", "23/10/2026", "2026-1-5", "2026-10-23T10:00", "hoy"):
            self.assertSoloError(self.validar([self.fila(fecha=fecha)]), "la fecha debe ser AAAA-MM-DD")
        self.assertSoloError(self.validar([self.fila(fecha="2026-02-30")]), "fecha que no existe")
        self.assertEqual(self.validar([self.fila(fecha="2028-02-29")]), [], "29 de febrero de un año bisiesto")

    def test_la_nota_no_lleva_espacios_sobrantes_ni_saltos(self):
        for nota in (" x", "x ", "x\ny"):
            self.assertSoloError(self.validar([self.fila(nota=nota)]), "nota con espacios sobrantes")

    def test_corregir_y_dudoso_necesitan_nota_y_correcto_y_corregido_no(self):
        for veredicto in ("corregir", "dudoso"):
            self.assertSoloError(self.validar([self.fila(veredicto=veredicto)]), "necesita una nota")
            self.assertEqual(self.validar([self.fila(veredicto=veredicto, nota="algo")]), [])
        for veredicto in ("correcto", "corregido"):
            self.assertEqual(self.validar([self.fila(veredicto=veredicto)]), [])

    def test_una_fila_por_revisor_y_elemento(self):
        self.assertSoloError(self.validar([self.fila(), self.fila(veredicto="dudoso", nota="x")]),
                             "Ana ya revisó nombres/aliceblue en es")
        self.assertEqual(self.validar([self.fila(), self.fila(revisor="Luis")]), [], "otro revisor: vale")
        self.assertEqual(self.validar([self.fila(), self.fila(idioma="fr")]), [], "otro idioma: vale")
        self.assertEqual(self.validar([self.fila(), self.fila(alcance="significados")]), [], "otro alcance: vale")

    def test_el_informe_sin_filas(self):
        texto = revision.informe([], universo_revisable(), gt.IDIOMAS, huellas_reales())
        total = sum(len(v) for v in universo_revisable().values())
        self.assertIn(f"**Estado: ningún elemento revisado todavía (0 de {total}).**", texto)
        self.assertIn("| Español (`es`) | 0 de 148 | 0 de 148 | 0 de 38 | 0 de 32 |", texto)
        self.assertIn("| Francés (`fr`) | 0 de 148 | 0 de 148 | 0 de 38 | — |", texto)
        self.assertIn("| `correcto` | 0 |", texto)
        self.assertIn("No hay correcciones propuestas ni dudas por atender.", texto)

    def test_el_informe_con_filas(self):
        filas = [self.fila(), self.fila(revisor="Luis"),  # dos revisores del mismo elemento cuentan un elemento
                 self.fila(elemento="red", veredicto="corregir", nota="mejor | «rojo»", revisor="Zoe"),
                 self.fila(idioma="en", alcance="categorias", elemento="lilac", veredicto="dudoso", nota="¿verbo?", revisor="Ana"),
                 self.fila(elemento="aqua", veredicto="corregido", revisor="Ana")]
        texto = revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales())
        total = sum(len(v) for v in universo_revisable().values())
        self.assertIn(f"**Estado: 4 de {total} elementos revisados.**", texto)
        self.assertIn("| Español (`es`) | 3 de 148 | 0 de 148 | 0 de 38 | 0 de 32 |", texto)
        self.assertIn("| Inglés (`en`) | 0 de 148 | 0 de 148 | 0 de 38 | 1 de 61 |", texto)
        for veredicto, cuenta in (("correcto", 2), ("corregir", 1), ("corregido", 1), ("dudoso", 1)):
            self.assertIn(f"| `{veredicto}` | {cuenta} |", texto)
        self.assertNotIn("No hay correcciones propuestas", texto)
        pendientes = texto.split("## Pendientes")[1].split("## Atención")[0]
        self.assertIn("| `en` | `categorias` | `lilac` | `dudoso` | ¿verbo? | Ana | 2026-10-23 |", pendientes)
        self.assertIn("| `es` | `nombres` | `red` | `corregir` | mejor &#124; «rojo» | Zoe | 2026-10-23 |", pendientes)
        self.assertLess(pendientes.index("`lilac`"), pendientes.index("`red`"), "ordenados por idioma (en antes que es)")
        self.assertNotIn("aqua", pendientes, "lo corregido ya no está pendiente")

    def test_la_huella_es_fnv1a_de_32_bits(self):
        # Vectores de prueba publicados de FNV-1a de 32 bits.
        for texto, esperado in (("", "811c9dc5"), ("a", "e40c292c"), ("foobar", "bf9cf968")):
            self.assertEqual(revision.huella(texto), esperado, texto)
        self.assertEqual(revision.huella("ñandú"), revision.huella("ñandú"))
        self.assertNotEqual(revision.huella("ñandú"), revision.huella("nandu"))

    def test_la_huella_debe_ser_de_ocho_cifras_hexadecimales_en_minuscula(self):
        for huella in ("", "abc", "0123abcg", "0123ABCD", "0123abcd0"):
            self.assertSoloError(self.validar([self.fila(huella=huella)]), "la huella debe ser de 8 cifras")
        self.assertEqual(self.validar([self.fila(huella="0123abcd")]), [], "una huella de otro momento es válida: caduca, no es un error")

    def test_hay_una_huella_por_cada_elemento_revisable(self):
        huellas = huellas_reales()
        universo = universo_revisable()
        self.assertEqual({k: set(v) for k, v in huellas.items()}, {k: set(v) for k, v in universo.items()})
        self.assertTrue(all(len(h) == 8 for lista in huellas.values() for h in lista.values()))

    def test_la_huella_cambia_con_lo_que_ve_el_revisor_y_con_nada_mas(self):
        base = huellas_reales()
        for alcance, campo, cambiar in (
                ("nombres", "anotada", lambda d: d["anotada"][primera(d["anotada"], nombre_ingles="aliceblue")].update(nombre_es="otro")),
                ("significados", "anotada", lambda d: d["anotada"][primera(d["anotada"], nombre_ingles="aliceblue")].update(significado_mistico="otro")),
                ("lexico", "lexico", lambda d: d["lexico"][primera(d["lexico"], idioma="es", concepto="white", tipo="lema")].update(forma="otro")),
                ("categorias", "categorias", lambda d: d["categorias"][primera(d["categorias"], idioma="es", concepto="white", categoria="ADJ")].update(ejemplo="Otra frase."))):
            with self.subTest(alcance):
                datos = datos_reales()
                cambiar(datos)
                nuevas = revision.huellas(datos)
                cambiadas = {(k, e) for k, lista in nuevas.items() for e, h in lista.items() if h != base[k][e]}
                elemento = "aliceblue" if alcance in ("nombres", "significados") else "white"
                self.assertEqual(cambiadas, {(("es", alcance), elemento)})

    def test_la_huella_de_significados_en_otro_idioma_sale_de_significados_idiomas(self):
        datos = datos_reales()
        datos["significados"][("ja", "aliceblue")] = ("x", "y")
        self.assertEqual(revision.huellas(datos)[("ja", "significados")]["aliceblue"], revision.huella("x\ny"))

    def test_un_correcto_cuyo_dato_cambio_caduca_y_no_cuenta(self):
        filas = [self.fila(huella="0123abcd")]
        atencion, revisados = revision.situacion(filas, huellas_reales())
        self.assertEqual(atencion["caducada"], filas)
        self.assertEqual(revisados, {})
        texto = revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales())
        self.assertIn("ningún elemento revisado todavía", texto)
        self.assertIn("### Revisiones `correcto` caducadas", texto)
        self.assertIn("| `es` | `nombres` | `aliceblue` | `correcto` | Ana | 2026-10-23 |", texto)

    def test_un_correcto_vigente_cuenta_y_no_pide_atencion(self):
        atencion, revisados = revision.situacion([self.fila()], huellas_reales())
        self.assertEqual(atencion, {"caducada": [], "ya_cambio": [], "sin_cambio": [], "conflicto": []})
        self.assertEqual(revisados, {("es", "nombres"): {"aliceblue"}})

    def test_un_corregir_cuyo_dato_cambio_pide_marcarlo_corregido(self):
        filas = [self.fila(veredicto="corregir", nota="mejor otro", huella="0123abcd")]
        atencion, revisados = revision.situacion(filas, huellas_reales())
        self.assertEqual(atencion["ya_cambio"], filas)
        self.assertEqual(revisados, {("es", "nombres"): {"aliceblue"}}, "una duda o corrección sigue contando: alguien miró")
        self.assertIn("### Correcciones o dudas cuyo dato ya cambió", revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales()))

    def test_un_dudoso_cuyo_dato_cambio_tambien(self):
        atencion, _ = revision.situacion([self.fila(veredicto="dudoso", nota="x", huella="0123abcd")], huellas_reales())
        self.assertEqual(len(atencion["ya_cambio"]), 1)

    def test_un_corregir_vigente_no_pide_atencion_de_cambio(self):
        atencion, _ = revision.situacion([self.fila(veredicto="corregir", nota="x")], huellas_reales())
        self.assertEqual(atencion["ya_cambio"], [])

    def test_un_corregido_con_el_dato_igual_no_esta_aplicado(self):
        filas = [self.fila(veredicto="corregido")]
        atencion, revisados = revision.situacion(filas, huellas_reales())
        self.assertEqual(atencion["sin_cambio"], filas)
        self.assertIn("### Filas `corregido` con el dato sin cambios", revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales()))

    def test_un_corregido_con_el_dato_distinto_esta_bien(self):
        atencion, revisados = revision.situacion([self.fila(veredicto="corregido", huella="0123abcd")], huellas_reales())
        self.assertEqual(atencion, {"caducada": [], "ya_cambio": [], "sin_cambio": [], "conflicto": []})
        self.assertEqual(revisados, {("es", "nombres"): {"aliceblue"}})

    def test_revisores_en_desacuerdo(self):
        filas = [self.fila(), self.fila(revisor="Luis", veredicto="corregir", nota="mejor otro")]
        atencion, _ = revision.situacion(filas, huellas_reales())
        self.assertEqual(atencion["conflicto"], filas)
        texto = revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales())
        self.assertIn("### Revisores en desacuerdo", texto)
        self.assertIn("| `es` | `nombres` | `aliceblue` | `correcto` | Ana | 2026-10-23 |", texto)
        self.assertIn("| `es` | `nombres` | `aliceblue` | `corregir` | Luis | 2026-10-23 |", texto)

    def test_dos_correcto_no_son_un_conflicto_ni_un_correcto_y_un_dato_de_otro_elemento(self):
        for filas in ([self.fila(), self.fila(revisor="Luis")],
                      [self.fila(), self.fila(revisor="Luis", elemento="red", veredicto="corregir", nota="x")]):
            self.assertEqual(revision.situacion(filas, huellas_reales())[0]["conflicto"], [])

    def test_un_correcto_y_un_corregir_ya_caducado_no_son_conflicto(self):
        filas = [self.fila(), self.fila(revisor="Luis", veredicto="corregir", nota="x", huella="0123abcd")]
        self.assertEqual(revision.situacion(filas, huellas_reales())[0]["conflicto"], [], "el corregir es de un dato que ya no existe")

    def test_sin_nada_que_atender_el_informe_lo_dice(self):
        texto = revision.informe([self.fila()], universo_revisable(), gt.IDIOMAS, huellas_reales())
        self.assertIn("Nada que atender: ninguna revisión caducada, sin aplicar ni en conflicto.", texto)

    def test_datos_del_visor(self):
        datos = datos_reales()
        v = revision.datos_visor(datos)
        self.assertEqual(v["huellas"]["es"]["nombres"]["aliceblue"], huellas_reales()[("es", "nombres")]["aliceblue"])
        self.assertNotIn("categorias", v["huellas"]["fr"], "fr no tiene categorías que revisar")
        self.assertEqual(v["idiomas"], [list(par) for par in gt.IDIOMAS])
        self.assertEqual(v["alcances"], list(revision.ALCANCES))
        self.assertEqual(v["veredictos"], list(revision.VEREDICTOS))
        self.assertEqual(list(v["colores"]), [f["nombre_ingles"] for f in REAL["anotada"]])
        alice = v["colores"]["aliceblue"]
        self.assertEqual((alice["hex"], alice["nombres"]["en"], alice["nombres"]["es"]), ("#F0F8FF", "alice blue", "azul alicia"))
        self.assertEqual(list(alice["nombres"]), [c for c, _ in gt.IDIOMAS])
        self.assertEqual(list(alice["significados"]), [c for c, _ in gt.IDIOMAS])
        original = next(f for f in REAL["anotada"] if f["nombre_ingles"] == "aliceblue")
        self.assertEqual(alice["significados"]["es"], [original["significado_psicologico"], original["significado_mistico"]])
        japones = next(f for f in REAL["significados"] if f["idioma"] == "ja" and f["nombre_ingles"] == "aliceblue")
        self.assertEqual(alice["significados"]["ja"], [japones["significado_psicologico"], japones["significado_mistico"]])
        self.assertEqual(v["lexico"]["es"]["white"]["formas"][0], ["blanco", "lema", ""])
        self.assertEqual(v["lexico"]["es"]["white"]["hex"], "#FFFFFF")
        self.assertEqual(v["categorias"]["en"]["lilac"][0][:2], ["lilac", "ADJ"])
        self.assertNotIn("fr", v["categorias"])

    def test_el_visor_lleva_los_datos_y_nada_externo(self):
        html = (BASE["carpeta"] / "visor-revision.html").read_text(encoding="utf-8")
        plantilla = (AQUI / "visor-plantilla.html").read_text(encoding="utf-8")
        self.assertIn(revision.MARCA_DATOS, plantilla)
        self.assertNotIn(revision.MARCA_DATOS, html)
        self.assertEqual(html.count("<script>"), 1)
        self.assertNotRegex(html, r"(?i)(src|href)\s*=\s*[\"']?(https?:)?//", "sin recursos externos")
        self.assertNotRegex(html, r"(?i)@import|url\(\s*[\"']?https?:", "sin estilos externos")
        trozo = html.split("const D = ", 1)[1].split(";\n  const COLUMNAS", 1)[0]
        datos = json.loads(trozo.replace("<\\/", "</"))
        self.assertEqual(sorted(datos), ["alcances", "categorias", "colores", "huellas", "idiomas", "lexico", "veredictos"])
        self.assertEqual(len(datos["colores"]), 148)
        self.assertEqual(datos["colores"]["aliceblue"]["nombres"]["en"], "alice blue")

    def test_los_datos_no_pueden_cerrar_el_script(self):
        plantilla = "<script>const D = /*DATOS*/null;</script>"
        datos = {"idiomas": [("en", "Inglés")], "anotada": [], "lexico": [], "categorias": [], "significados": {}, "hex_concepto": {}}
        html = revision.visor_html(dict(datos, idiomas=[("en", "</script><b>")]), plantilla)
        self.assertEqual(html.count("</script>"), 1, "el único cierre es el de la plantilla")
        self.assertIn("<\\/script>", html)

    def test_la_plantilla_debe_tener_la_marca_una_vez(self):
        datos = {"idiomas": [], "anotada": [], "lexico": [], "categorias": [], "significados": {}, "hex_concepto": {}}
        for plantilla in ("<script></script>", f"{revision.MARCA_DATOS} {revision.MARCA_DATOS}"):
            with self.assertRaises(ValueError):
                revision.visor_html(datos, plantilla)

    @unittest.skipUnless(shutil.which("node"), "node no está instalado")
    def test_el_script_del_visor_es_javascript_valido(self):
        html = (BASE["carpeta"] / "visor-revision.html").read_text(encoding="utf-8")
        guion = html.split("<script>", 1)[1].split("</script>", 1)[0]
        with tempfile.TemporaryDirectory() as t:
            ruta = Path(t) / "visor.js"
            ruta.write_text(guion, encoding="utf-8")
            resultado = subprocess.run(["node", "--check", str(ruta)], capture_output=True, text=True)
        self.assertEqual((resultado.returncode, resultado.stderr), (0, ""))


class HexCotidiano(unittest.TestCase):
    def test_solo_cuentan_las_entradas_curadas_con_el_mismo_nombre(self):
        completa = [{"nombre_ingles": "Lime", "hex": "#AAFF32", "curada": "x"},
                    {"nombre_ingles": "Navy", "hex": "#01153E", "curada": ""},
                    {"nombre_ingles": "limes", "hex": "#000000", "curada": "x"}]
        self.assertEqual(gt.hex_cotidiano_de_concepto("lime", completa), "#AAFF32", "sin distinguir mayúsculas")
        self.assertEqual(gt.hex_cotidiano_de_concepto("navy", completa), "", "una entrada no curada no vale")
        self.assertEqual(gt.hex_cotidiano_de_concepto("red", completa), "", "sin entrada: vacío, no el hex CSS")

    def test_valores_reales_que_motivan_la_columna(self):
        for concepto, esperado in (("lime", "#AAFF32"), ("khaki", "#C3B091"), ("violet", "#9A0EEA"), ("green", "#00FF00")):
            self.assertEqual(gt.hex_cotidiano_de_concepto(concepto, REAL["completa"]), esperado, concepto)
        self.assertEqual(gt.hex_cotidiano_de_concepto("navy", REAL["completa"]), "", "navy no está curado")

    def test_el_hex_de_referencia_no_cambia(self):
        # El hex CSS sigue siendo el de referencia: la columna nueva no lo reemplaza.
        self.assertEqual(gt.hex_de_concepto("lime", REAL["anotada"], REAL["completa"]), "#00FF00")

    def test_sqlite_lleva_la_columna_en_conceptos(self):
        conexion = exportes.construir_sqlite(":memory:", {**datos_reales(), "completa": REAL["completa"], "cercanias": [],
                                                           "significados": datos_reales()["significados"],
                                                           "referencias": REAL["referencias"], "evidencia": {f["nombre_ingles"]: f for f in REAL["evidencia"]},
                                                           "culturales": REAL["culturales"], "alias": gt.alias_de(REAL["anotada"])})
        self.addCleanup(conexion.close)
        self.assertEqual(conexion.execute("SELECT hex, hex_cotidiano FROM conceptos WHERE concepto = 'lime'").fetchone(), ("#00FF00", "#AAFF32"))
        self.assertEqual(conexion.execute("SELECT hex_cotidiano FROM conceptos WHERE concepto = 'navy'").fetchone(), ("",))


class Cobertura(unittest.TestCase):
    def hex_concepto(self):
        return {c: gt.hex_de_concepto(c, REAL["anotada"], REAL["completa"]) for c in gt.CONCEPTOS}

    def test_huecos_del_lexico_reales(self):
        huecos = cobertura.huecos_lexico(gt.IDIOMAS, REAL["lexico"], gt.CONCEPTOS)
        self.assertEqual(sum(len(v) for v in huecos.values()), 33)
        self.assertEqual(len(huecos["sw"]), 11)
        self.assertNotIn("en", huecos)
        # coherencia con el validador: los 11 conceptos básicos nunca faltan
        self.assertTrue(all(c not in gt.BASICOS for lista in huecos.values() for c in lista))

    def test_huecos_con_datos_sinteticos(self):
        lexico = [{"idioma": "en", "concepto": "red", "tipo": "lema", "forma": "red", "rasgos": ""},
                  {"idioma": "en", "concepto": "blue", "tipo": "flexion", "forma": "blues", "rasgos": ""},
                  {"idioma": "es", "concepto": "red", "tipo": "lema", "forma": "rojo", "rasgos": ""},
                  {"idioma": "es", "concepto": "blue", "tipo": "lema", "forma": "azul", "rasgos": ""}]
        self.assertEqual(cobertura.huecos_lexico([("en", "Inglés"), ("es", "Español")], lexico, ["red", "blue"]), {"en": ["blue"]},
                         "una flexión no cuenta como lema")

    def test_colisiones_de_nombres_reales(self):
        colisiones = cobertura.colisiones_nombres(gt.IDIOMAS, REAL["anotada"])
        self.assertEqual(sum(len(v) for v in colisiones.values()), 29)
        self.assertEqual(len(colisiones), 18)
        self.assertNotIn("es", colisiones)
        self.assertIn(("סגול", ["purple", "violet"]), colisiones["he"])

    def test_las_propuestas_de_ia_son_las_filas_lema_con_origen_ia(self):
        lexico = [{"idioma": "sw", "concepto": "olive", "tipo": "lema", "forma": "zeituni", "rasgos": "origen=ia"},
                  {"idioma": "sw", "concepto": "red", "tipo": "lema", "forma": "nyekundu", "rasgos": ""},
                  {"idioma": "es", "concepto": "red", "tipo": "flexion", "forma": "rojos", "rasgos": "origen=ia"},
                  {"idioma": "es", "concepto": "blue", "tipo": "sinonimo", "forma": "x", "rasgos": "origen=ia"}]
        self.assertEqual(cobertura.propuestas_ia(lexico), [("sw", "olive", "zeituni")])
        reales = cobertura.propuestas_ia(REAL["lexico"])
        self.assertEqual(len(reales), 12)
        self.assertTrue(all(c in gt.CONCEPTOS for _, c, _ in reales))

    def test_las_propuestas_de_ia_no_repiten_el_lema_de_otro_concepto(self):
        # Una propuesta debe llenar un hueco, no sumar una ambigüedad (como «lilla» para `purple` y `lilac`).
        lemas = {}
        for f in REAL["lexico"]:
            if f["tipo"] == "lema":
                lemas.setdefault((f["idioma"], f["forma"].casefold()), []).append(f["concepto"])
        for idioma, concepto, lema in cobertura.propuestas_ia(REAL["lexico"]):
            self.assertEqual(lemas[(idioma, lema.casefold())], [concepto], (idioma, lema))

    def test_gray_y_grey_no_son_una_colision(self):
        anotada = [{"nombre_ingles": n, "forma_natural_en": n, "nombre_es": "gris", "hex": "#808080"} for n in ("gray", "grey")]
        self.assertEqual(cobertura.colisiones_nombres([("es", "Español")], anotada), {}, "mismo hex: es un alias")
        anotada[1]["hex"] = "#808081"
        self.assertEqual(cobertura.colisiones_nombres([("es", "Español")], anotada), {"es": [("gris", ["gray", "grey"])]})

    def test_nombres_iguales_solo_sin_distinguir_mayusculas(self):
        anotada = [{"nombre_ingles": "a", "forma_natural_en": "a", "nombre_es": "Azul", "hex": "#000001"},
                   {"nombre_ingles": "b", "forma_natural_en": "b", "nombre_es": "azul", "hex": "#000002"}]
        self.assertEqual(cobertura.colisiones_nombres([("es", "Español")], anotada), {"es": [("Azul", ["a", "b"])]})

    def test_lemas_compartidos_reales(self):
        compartidos = cobertura.lemas_compartidos(gt.IDIOMAS, REAL["lexico"], self.hex_concepto())
        self.assertEqual(sum(len(v) for v in compartidos.values()), 25)  # las propuestas de IA no agregan ambigüedad
        self.assertIn(("violet", ["purple", "violet"]), compartidos["fr"])
        self.assertNotIn("es", compartidos)

    def test_un_lema_para_alias_no_es_compartido(self):
        lexico = [{"idioma": "es", "concepto": c, "tipo": "lema", "forma": "gris", "rasgos": ""} for c in ("gray", "grey")]
        self.assertEqual(cobertura.lemas_compartidos([("es", "Español")], lexico, {"gray": "#808080", "grey": "#808080"}), {})

    def test_el_informe_resume_lo_mismo_que_calculan_las_funciones(self):
        texto = cobertura.informe(gt.IDIOMAS, REAL["anotada"], REAL["lexico"], REAL["categorias"], gt.CONCEPTOS, self.hex_concepto())
        self.assertIn("17 idiomas tienen lema para todos; 13 tienen huecos (33 lemas en total).", texto)
        self.assertIn("2 idiomas (Inglés, Español); 28 sin ninguna.", texto)
        self.assertIn("29 casos en 18 idiomas.", texto)
        self.assertIn("25 casos en 17 idiomas.", texto)
        self.assertIn("| Suajili (`sw`) | 27 de 38 |", texto)
        self.assertIn("12 los llenó una propuesta de IA sin revisar", texto)
        self.assertIn("| Suajili (`sw`) | `turquoise` | feruzi |", texto)
        self.assertIn("| Español (`es`) | 38 de 38 | — |", texto)
        self.assertIn("| Hebreo (`he`) | סגול | `purple`, `violet` |", texto)


class AliasYCercania(unittest.TestCase):
    def test_alias_de_los_colores_reales(self):
        alias = gt.alias_de(REAL["anotada"])
        self.assertEqual(alias["gray"], [("grey", "ortografia")])
        self.assertEqual(alias["grey"], [("gray", "ortografia")])
        self.assertEqual(alias["aqua"], [("cyan", "mismo_color")])
        self.assertEqual(alias["magenta"], [("fuchsia", "mismo_color")])
        self.assertEqual(alias["red"], [])
        self.assertEqual(sum(1 for otros in alias.values() if otros), 2 * 9, "nueve pares, cada uno visto desde sus dos lados")

    def test_el_alias_es_simetrico(self):
        alias = gt.alias_de(REAL["anotada"])
        for nombre, otros in alias.items():
            for otro, tipo in otros:
                self.assertIn((nombre, tipo), alias[otro])

    def test_alias_sintetico(self):
        anotada = [{"nombre_ingles": n, "hex": h} for n, h in (("a", "#000000"), ("b", "#000000"), ("c", "#FFFFFF"))]
        self.assertEqual(gt.alias_de(anotada), {"a": [("b", "mismo_color")], "b": [("a", "mismo_color")], "c": []})

    def test_bandas_de_cercania(self):
        for delta, mismo_hex, esperado in (
                (9.0, True, "exacto"), (0.0, False, "alta"), (3.0, False, "alta"), (3.01, False, "media"),
                (8.0, False, "media"), (8.01, False, "baja"), (60.0, False, "baja")):
            self.assertEqual(gt.cercania_de(delta, mismo_hex), esperado, (delta, mismo_hex))

    def test_cercanias_sinteticas_y_desempate_alfabetico(self):
        anotada = [{"nombre_ingles": "grey", "hex": "#808080"}, {"nombre_ingles": "gray", "hex": "#808080"},
                   {"nombre_ingles": "red", "hex": "#FF0000"}]
        completa = [{"nombre_ingles": "Mid", "hex": "#808080"}, {"nombre_ingles": "Casi rojo", "hex": "#FE0000"},
                    {"nombre_ingles": "Negro", "hex": "#000000"}]
        resultado = gt.calcular_cercanias(completa, anotada)
        self.assertEqual(resultado[0], ("gray", 0.0, "exacto"), "gray y grey empatan: gana gray")
        self.assertEqual(resultado[1][0], "red")
        self.assertEqual(resultado[1][2], "alta")
        self.assertEqual(resultado[2][0], "gray")
        self.assertEqual(resultado[2][2], "baja")

    def test_ningun_alias_sale_como_color_mas_cercano(self):
        # Los segundos de cada par (cyan, magenta, las siete variantes …grey) nunca ganan el desempate.
        muestra = [{"nombre_ingles": f["nombre"], "hex": f["hex"]} for f in
                   ({"nombre": n, "hex": h} for n, h in (("A", "#00FFFF"), ("B", "#FF00FF"), ("C", "#A9A9A9"), ("D", "#2F4F4F")))]
        cercanos = {css for css, _, _ in gt.calcular_cercanias(muestra, REAL["anotada"])}
        self.assertTrue(cercanos.isdisjoint({"cyan", "magenta", "darkgrey", "darkslategrey"}), cercanos)

    def test_el_calculo_coincide_con_lo_guardado_en_nombres_completos(self):
        with gt.COMPLETA.open(encoding="utf-8", newline="") as f:
            guardadas = list(csv.DictReader(f))
        muestra = guardadas[::150]
        calculadas = gt.calcular_cercanias([{c: g[c] for c in gt.COLUMNAS_FUENTE_COMPLETA} for g in muestra], REAL["anotada"])
        self.assertGreater(len(muestra), 200)
        for guardada, (css, distancia, cercania) in zip(muestra, calculadas):
            self.assertEqual((guardada["css_cercano"], guardada["delta_e"], guardada["cercania"]),
                             (css, f"{distancia:.2f}", cercania), guardada["nombre_ingles"])


class Colorimetria(unittest.TestCase):
    def test_los_16_vectores_de_sharma_coinciden(self):
        self.assertEqual(len(colorimetria.VECTORES_SHARMA), 16)
        self.assertEqual(colorimetria.autoprueba(), [])

    def test_la_autoprueba_detecta_un_vector_que_no_coincide(self):
        lab1, lab2, esperado = colorimetria.VECTORES_SHARMA[0]
        with mock.patch.object(colorimetria, "VECTORES_SHARMA", [(lab1, lab2, esperado + 0.01)]):
            fallos = colorimetria.autoprueba()
        self.assertEqual(len(fallos), 1)
        self.assertEqual(fallos[0][:3], (lab1, lab2, esperado + 0.01))

    def test_hex_a_lab_en_los_extremos_y_los_primarios(self):
        for color, esperado in (("#000000", (0, 0, 0)), ("#FFFFFF", (100, 0, 0)),
                                ("#FF0000", (53.24, 80.09, 67.20)), ("#0000FF", (32.30, 79.19, -107.86))):
            for obtenido, previsto in zip(colorimetria.hex_a_lab(color), esperado):
                self.assertAlmostEqual(obtenido, previsto, delta=0.05, msg=color)

    def test_la_distancia_es_cero_consigo_misma_y_simetrica(self):
        rojo, azul, gris = (colorimetria.hex_a_lab(h) for h in ("#FF0000", "#0000FF", "#808080"))
        self.assertEqual(colorimetria.ciede2000(rojo, rojo), 0)
        for a, b in ((rojo, azul), (rojo, gris), (azul, gris)):
            self.assertAlmostEqual(colorimetria.ciede2000(a, b), colorimetria.ciede2000(b, a), places=9)

    def test_negro_y_blanco_estan_a_100(self):
        distancia = colorimetria.ciede2000(colorimetria.hex_a_lab("#000000"), colorimetria.hex_a_lab("#FFFFFF"))
        self.assertAlmostEqual(distancia, 100, delta=0.01)


class Rutas(unittest.TestCase):
    def test_las_pruebas_parchean_todas_las_rutas_del_generador(self):
        # Si generar-tabla.py gana una ruta y rutas_para() no la lista, main() escribiría en la carpeta real del proyecto.
        del_modulo = {nombre for nombre, valor in vars(gt).items() if nombre.isupper() and isinstance(valor, Path)}
        self.assertEqual(del_modulo, set(rutas_para(Path("/x"))))


class Codigo(unittest.TestCase):
    def test_los_py_no_llevan_caracteres_invisibles_literales(self):
        # Un guion blando, un espacio de ancho cero o un BOM dentro del código no se ven al leerlo y un editor
        # puede quitarlos sin avisar: se escriben como \uXXXX.
        for ruta in sorted(AQUI.glob("*.py")):
            for n, linea in enumerate(ruta.read_text(encoding="utf-8").splitlines(), start=1):
                invisibles = [c for c in linea if unicodedata.category(c) in ("Cf", "Zs") and c != " "]
                self.assertEqual(invisibles, [], f"{ruta.name} línea {n}")


class Normalizacion(unittest.TestCase):
    def assertNormaliza(self, idioma, casos):
        for forma, esperada in casos:
            self.assertEqual(normalizacion.normalizar(forma, idioma), esperada, (idioma, forma))

    def test_mayusculas_espacios_y_guiones(self):
        self.assertNormaliza("es", [("Azul Marino", "azul marino"), ("azul-marino", "azul marino"),
                                    ("  azul   marino ", "azul marino"), ("azul\u00a0marino", "azul marino"),
                                    ("azul\u2013marino", "azul marino"), ("azul_marino", "azul marino")])
        self.assertNormaliza("en", [("alice blue", "alice blue"), ("alice-blue", "alice blue"), ("aliceblue", "aliceblue")])

    def test_se_quitan_las_marcas_en_escritura_latina(self):
        self.assertNormaliza("fr", [("Crème Brûlée", "creme brulee")])
        self.assertNormaliza("de", [("Grün", "grun"), ("Weiß", "weiss")])
        self.assertNormaliza("es", [("marrón", "marron"), ("café", "cafe")])
        self.assertNormaliza("vi", [("đỏ", "do"), ("xanh lá", "xanh la")])
        self.assertNormaliza("pl", [("żółty", "zolty")])
        self.assertNormaliza("cs", [("žlutá", "zluta")])
        self.assertNormaliza("ro", [("roșu", "rosu")])
        self.assertNormaliza("hu", [("zöld", "zold"), ("fűzöld", "fuzold")])
        self.assertNormaliza("tr", [("Yeşil", "yesil"), ("Kırmızı", "kirmizi"), ("İstanbul", "istanbul")])
        self.assertNormaliza("da", [("grøn", "gron"), ("blå", "bla"), ("æble", "aeble")])

    def test_el_griego_pierde_los_acentos_y_la_sigma_final(self):
        self.assertNormaliza("el", [("κόκκινο", "κοκκινο"), ("ΜΑΎΡΟ", "μαυρο"), ("άσπρος", "ασπροσ")])

    def test_en_las_demas_escrituras_no_se_quita_ninguna_marca(self):
        # Allí las marcas son parte de la letra: quitarlas daría otra palabra.
        self.assertNormaliza("ru", [("красный", "красный"), ("Зелёный", "зелёный")])
        self.assertNormaliza("uk", [("синій", "синій"), ("блакитний", "блакитний")])
        self.assertNormaliza("ja", [("ピンク", "ピンク"), ("ガ", "ガ"), ("みどり", "みどり")])
        self.assertNormaliza("th", [("สีแดง", "สีแดง"), ("เขียว", "เขียว")])
        self.assertNormaliza("hi", [("नीला", "नीला"), ("हरा", "हरा")])
        self.assertNormaliza("bn", [("নীল", "নীল")])
        self.assertNormaliza("ar", [("أحمر", "أحمر")])
        self.assertNormaliza("ko", [("빨강", "빨강")])
        self.assertNormaliza("zh", [("红色", "红色")])
        self.assertNormaliza("he", [("אדום", "אדום")])

    def test_el_zwnj_del_persa_se_conserva(self):
        self.assertNormaliza("fa", [("نیمه\u200cشب", "نیمه\u200cشب")])

    def test_nfkc_unifica_anchos_y_kana(self):
        self.assertNormaliza("en", [("ＡＢＣ", "abc")])
        self.assertNormaliza("ja", [("ﾋﾟﾝｸ", "ピンク")])

    def test_los_apostrofos_se_unifican_y_se_conservan(self):
        self.assertNormaliza("fr", [("d\u2019eau", "d'eau"), ("d\u02bceau", "d'eau")])
        self.assertNormaliza("uk", [("м\u2019ятний", "м'ятний")])
        self.assertNormaliza("he", [("ג\u05f3ינס", "ג'ינס")])

    def test_se_quitan_los_caracteres_invisibles(self):
        self.assertNormaliza("en", [("a\u00adb", "ab"), ("a\u200bb", "ab"), ("\ufeffab", "ab")])

    def test_es_idempotente_y_nunca_queda_vacia_en_las_formas_reales(self):
        lineas = (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()[1:]
        formas = [tuple(linea.split("\t")[:2]) for linea in lineas]
        self.assertGreater(len(formas), 7000)
        for forma, idioma in formas:
            una = normalizacion.normalizar(forma, idioma)
            self.assertEqual(normalizacion.normalizar(una, idioma), una, (forma, idioma))
            self.assertNotEqual(una, "", (forma, idioma))

    def test_los_idiomas_latinos_y_los_demas_cubren_los_30_sin_solaparse(self):
        codigos = {c for c, _ in gt.IDIOMAS}
        self.assertEqual(normalizacion.LATINOS | gt.SIN_LETRAS_LATINAS, codigos)
        self.assertEqual(normalizacion.LATINOS & gt.SIN_LETRAS_LATINAS, set())
        self.assertEqual(normalizacion.QUITAR_MARCAS, normalizacion.LATINOS | {"el"})


PURPURA, VIOLETA, LILA, VERDE, LIMA, GRIS = "#800080", "#EE82EE", "#CEA2FD", "#008000", "#00FF00", "#808080"


def fila_gazetteer(forma, idioma, concepto, color, tipo="lema", fuente="lexico"):
    return (forma, idioma, fuente, tipo, concepto, color, "", normalizacion.normalizar(forma, idioma))


class Ambiguedad(unittest.TestCase):
    def calcular(self, *filas, codigos=("es", "fr", "de", "pt")):
        return ambiguedad.calcular(list(filas), list(codigos))

    def assertListasVaciasSalvo(self, resultado, *claves):
        for clave, elementos in resultado.items():
            if clave not in claves:
                self.assertEqual(elementos, [], clave)

    def test_una_forma_con_dos_colores_en_un_idioma(self):
        resultado = self.calcular(fila_gazetteer("violet", "fr", "purple", PURPURA), fila_gazetteer("violet", "fr", "violet", VIOLETA))
        self.assertListasVaciasSalvo(resultado, "dentro")
        (caso,) = resultado["dentro"]
        self.assertEqual((caso["idioma"], caso["forma"], caso["colores"]), ("fr", "violet", [(PURPURA, ["purple"]), (VIOLETA, ["violet"])]))
        esperada = colorimetria.ciede2000(colorimetria.hex_a_lab(PURPURA), colorimetria.hex_a_lab(VIOLETA))
        self.assertAlmostEqual(caso["delta"], esperada, places=9)

    def test_los_alias_del_mismo_hex_no_son_ambiguos(self):
        resultado = self.calcular(fila_gazetteer("gris", "es", "gray", GRIS), fila_gazetteer("gris", "es", "grey", GRIS))
        self.assertListasVaciasSalvo(resultado)

    def test_mayusculas_distintas_son_la_misma_forma(self):
        resultado = self.calcular(fila_gazetteer("Violet", "fr", "purple", PURPURA), fila_gazetteer("violet", "fr", "violet", VIOLETA))
        (caso,) = resultado["dentro"]
        self.assertEqual(caso["forma"], "Violet")

    def test_la_misma_forma_con_colores_distintos_en_dos_idiomas(self):
        resultado = self.calcular(fila_gazetteer("lila", "de", "purple", PURPURA), fila_gazetteer("lila", "es", "lilac", LILA))
        self.assertListasVaciasSalvo(resultado, "entre")
        (caso,) = resultado["entre"]
        self.assertEqual(caso["forma"], "lila")
        self.assertEqual(caso["idiomas"], [("es", ["lila"], [(LILA, ["lilac"])]), ("de", ["lila"], [(PURPURA, ["purple"])])],
                         "en el orden de idiomas que se pasa, no en el alfabético")
        esperada = colorimetria.ciede2000(colorimetria.hex_a_lab(PURPURA), colorimetria.hex_a_lab(LILA))
        self.assertAlmostEqual(caso["delta"], esperada, places=9)

    def test_la_misma_forma_con_el_mismo_color_en_dos_idiomas_no_es_ambigua(self):
        resultado = self.calcular(fila_gazetteer("magenta", "es", "magenta", "#FF00FF"), fila_gazetteer("magenta", "fr", "magenta", "#FF00FF"))
        self.assertListasVaciasSalvo(resultado)

    def test_dos_idiomas_con_los_mismos_dos_colores_son_ambiguos_dentro_pero_no_entre(self):
        filas = [fila_gazetteer("bordeaux", i, c, h) for i in ("fr", "de") for c, h in (("maroon", "#800000"), ("burgundy", "#900020"))]
        resultado = self.calcular(*filas)
        self.assertListasVaciasSalvo(resultado, "dentro")
        self.assertEqual([c["idioma"] for c in resultado["dentro"]], ["fr", "de"])

    def test_formas_distintas_que_solo_coinciden_al_normalizar_dentro_de_un_idioma(self):
        resultado = self.calcular(fila_gazetteer("grün", "de", "green", VERDE), fila_gazetteer("grun", "de", "lime", LIMA))
        self.assertListasVaciasSalvo(resultado, "dentro_normalizada")
        (caso,) = resultado["dentro_normalizada"]
        self.assertEqual((caso["idioma"], caso["formas"], caso["clave"]), ("de", ["grun", "grün"], "grun"))

    def test_lo_que_ya_es_ambiguo_sin_normalizar_no_se_repite_en_la_lista_3(self):
        resultado = self.calcular(fila_gazetteer("violett", "de", "purple", PURPURA), fila_gazetteer("violett", "de", "violet", VIOLETA),
                                  fila_gazetteer("violétt", "de", "lime", LIMA))
        self.assertListasVaciasSalvo(resultado, "dentro")

    def test_formas_distintas_que_solo_coinciden_al_normalizar_entre_idiomas(self):
        resultado = self.calcular(fila_gazetteer("lilás", "pt", "lilac", LILA), fila_gazetteer("lilas", "fr", "purple", PURPURA))
        self.assertListasVaciasSalvo(resultado, "entre_normalizada")
        (caso,) = resultado["entre_normalizada"]
        self.assertEqual(caso["clave"], "lilas")
        self.assertEqual(caso["idiomas"], [("fr", ["lilas"], [(PURPURA, ["purple"])]), ("pt", ["lilás"], [(LILA, ["lilac"])])])

    def test_lo_que_ya_es_ambiguo_entre_idiomas_no_se_repite_en_la_lista_4(self):
        resultado = self.calcular(fila_gazetteer("lila", "es", "lilac", LILA), fila_gazetteer("lila", "de", "purple", PURPURA),
                                  fila_gazetteer("lilá", "pt", "green", VERDE))
        self.assertListasVaciasSalvo(resultado, "entre")

    def test_bandas(self):
        for delta, esperada in ((0.0, "casi iguales"), (3.0, "casi iguales"), (3.01, "parecidos"), (8.0, "parecidos"),
                                (8.01, "distintos"), (80.0, "distintos")):
            self.assertEqual(ambiguedad.banda(delta), esperada, delta)

    def test_el_informe_escapa_las_barras_y_dice_cuando_no_hay_casos(self):
        filas = [fila_gazetteer("a|b", "es", "purple", PURPURA), fila_gazetteer("a|b", "es", "violet", VIOLETA)]
        texto = ambiguedad.informe(filas, [("es", "Español"), ("fr", "Francés")])
        self.assertIn("«a&#124;b»", texto)
        self.assertIn("## 2. Entre idiomas: la misma forma, otro color\n\nNo hay casos.", texto)
        self.assertTrue(texto.endswith("\n"))
        self.assertEqual(texto, ambiguedad.informe(filas, [("es", "Español"), ("fr", "Francés")]), "es determinista")

    def test_el_resumen_del_informe_coincide_con_sus_tablas(self):
        texto = (BASE["carpeta"] / "ambiguedad.md").read_text(encoding="utf-8")
        resumen = {int(n): int(casos) for n, casos in re.findall(r"^\| (\d)\. [^|]+\| (\d+) \|", texto, flags=re.M)}
        self.assertEqual(sorted(resumen), [1, 2, 3, 4])
        secciones = re.split(r"^## (\d)\. ", texto, flags=re.M)[1:]
        for numero, cuerpo in zip(secciones[::2], secciones[1::2]):
            filas = [linea for linea in cuerpo.splitlines() if linea.startswith("| ")]
            esperadas = max(len(filas) - 1, 0)  # la primera es la cabecera
            self.assertEqual(esperadas, resumen[int(numero)], f"lista {numero}")
            if esperadas == 0:
                self.assertIn("No hay casos.", cuerpo)
        self.assertGreater(resumen[1], 0)


def fila_completa(nombre, color="#000000", curada=""):
    return {"nombre_ingles": nombre, "hex": color, "curada": curada}


class Auditoria(unittest.TestCase):
    LIMPIOS = ["Plain Blue", "Sea Green", "Arctic Fox"]

    def ejemplos(self):
        """Un nombre que dispara cada comprobación (clave -> nombre). Los caracteres raros salen de chr() para que se vean."""
        return {
            "control": "Bad" + chr(7) + "Bell",
            "formato": "Zero" + chr(0x200B) + "Width",
            "espacios": "No" + chr(0xA0) + "Break",
            "espacios_dobles": "Two  Spaces",
            "no_nfc": "Cafe" + chr(0x301),
            "mojibake": "CafÃ©",
            "inicia_minuscula": "iCloud Blue",
            "apostrofo_ascii": "Don't Blue",
            "todo_mayusculas": "NASA",
            "inicia_digito": "7 Up",
            "frase": "One Two Three Four",
            "letras_no_ascii": "Crème",
            "apostrofo_tipografico": "Mann" + chr(0x2019) + "s Mint",
            "guion": "Ice-Blue",
        }

    def test_cada_comprobacion_marca_su_ejemplo_y_ninguna_marca_los_limpios(self):
        ejemplos = self.ejemplos()
        self.assertEqual(set(ejemplos), {clave for clave, _t, _p in auditoria.COMPROBACIONES}, "un ejemplo por comprobación")
        filas = [fila_completa(n) for n in [*ejemplos.values(), *self.LIMPIOS]]
        a = auditoria.analizar(filas)
        for clave, ejemplo in ejemplos.items():
            self.assertIn(ejemplo, a["comprobaciones"][clave], clave)
        for clave, nombres in a["comprobaciones"].items():
            for limpio in self.LIMPIOS:
                self.assertNotIn(limpio, nombres, clave)

    def test_los_defectos_son_las_primeras_comprobaciones(self):
        claves = [clave for clave, _t, _p in auditoria.COMPROBACIONES]
        self.assertEqual(claves[:auditoria.DEFECTOS], ["control", "formato", "espacios", "espacios_dobles", "no_nfc", "mojibake",
                                                        "inicia_minuscula", "apostrofo_ascii"])

    def test_duplicados_al_normalizar(self):
        a = auditoria.analizar([fila_completa(n) for n in ("Ice-Blue", "Ice Blue", "Icé Blue", "Fire Red", "Rose", "Rosé", "Rose Red")])
        self.assertEqual(a["duplicados"], [["Ice Blue", "Ice-Blue", "Icé Blue"], ["Rose", "Rosé"]])

    def test_hex_repetidos(self):
        a = auditoria.analizar([fila_completa("B Red", "#FF0000"), fila_completa("A Red", "#FF0000"), fila_completa("Blue", "#0000FF"),
                                fila_completa("Z Grey", "#808080"), fila_completa("Y Grey", "#808080")])
        self.assertEqual(a["hex_repetidos"], [("#808080", ["Y Grey", "Z Grey"]), ("#FF0000", ["A Red", "B Red"])])

    def test_curadas_y_signos(self):
        a = auditoria.analizar([fila_completa("Abc 12 & 3", curada="x"), fila_completa("Q&A"), fila_completa("Hi-Fi")])
        self.assertEqual(a["curadas"], {"Abc 12 & 3"})
        self.assertEqual(a["signos"], {"0-9": ["Abc 12 & 3"], "&": ["Abc 12 & 3", "Q&A"], "-": ["Hi-Fi"]}, "un nombre cuenta una vez por signo")

    def test_los_mas_largos_por_longitud_y_luego_por_nombre(self):
        nombres = ["aaa", "bbbbb", "Ccccc", "dd", "eeeeee", "ffffff", "g", "hhhhhhh"]
        a = auditoria.analizar([fila_completa(n) for n in nombres])
        self.assertEqual(a["mas_largos"], ["hhhhhhh", "eeeeee", "ffffff", "bbbbb", "Ccccc"])

    def test_cifras_con_formato_espanol(self):
        self.assertEqual(auditoria._miles(31935), "31.935")
        self.assertEqual(auditoria._miles(7), "7")
        self.assertEqual(auditoria._porcentaje(4966, 31935), "15,6 %")
        self.assertEqual(auditoria._porcentaje(0, 0), "0 %")

    def test_el_informe_dice_cuando_no_hay_casos_y_corta_las_listas_largas(self):
        sin_nada = auditoria.informe([fila_completa("Plain Blue", "#111111"), fila_completa("Sea Green", "#222222")])
        self.assertIn("## Duplicados al normalizar", sin_nada)
        self.assertEqual(sin_nada.count("No hay casos."), 2, "duplicados y hex repetidos")
        muchos = []
        for i in range(27):  # 27 grupos de duplicados, cada nombre con su propio hex
            muchos += [fila_completa(f"Nombre{i:02d}", f"#{2 * i:06X}"), fila_completa(f"Nómbre{i:02d}", f"#{2 * i + 1:06X}")]
        texto = auditoria.informe(muchos)
        self.assertEqual(texto.count("| ... y 2 más |"), 1, "solo la lista de duplicados pasa de 25")
        self.assertIn("«Nombre24», «Nómbre24»", texto)
        self.assertNotIn("«Nombre25», «Nómbre25»", texto)
        self.assertTrue(texto.endswith("\n"))
        self.assertEqual(texto, auditoria.informe(muchos), "es determinista")

    def test_el_informe_escapa_las_barras(self):
        texto = auditoria.informe([fila_completa("A|B", "#111111"), fila_completa("Plain Blue", "#222222")])
        self.assertIn("«A&#124;B»", texto)
        self.assertNotIn("A|B", texto)

    def test_el_informe_del_repositorio_esta_al_dia(self):
        # nombres-completos.csv real, sin recalcular las distancias: es el mismo texto que genera main().
        self.assertEqual(auditoria.informe(REAL["completa"]), (AQUI / "auditoria-nombres.md").read_text(encoding="utf-8"))

    def test_el_informe_de_la_carpeta_reducida_cuenta_sus_nombres(self):
        texto = (BASE["carpeta"] / "auditoria-nombres.md").read_text(encoding="utf-8")
        self.assertIn("Revisa automáticamente los 244 nombres", texto)
        self.assertIn("Hex distintos: 244 para 244 nombres.", texto)


class Changelog(unittest.TestCase):
    VALIDO = ("# Título\n\nTexto.\n\n## 1.2.0 - 2026-03-01\n\nAlgo.\n\n## 1.1.0 - 2026-02-01\n\nOtra cosa.\n\n"
              "## 1.0.0 - 2026-01-01\n\nPrimera.\n")

    def leer(self, texto):
        errores = []
        return manifiesto.leer_changelog(texto, errores), errores

    def test_devuelve_la_primera_entrada(self):
        self.assertEqual(self.leer(self.VALIDO), ("1.2.0", []))

    def test_las_versiones_se_comparan_como_numeros(self):
        texto = "## 1.10.0 - 2026-02-01\n\nUna.\n\n## 1.9.0 - 2026-01-01\n\nOtra.\n"
        self.assertEqual(self.leer(texto), ("1.10.0", []))

    def test_el_changelog_real_es_valido_y_su_version_es_la_del_manifiesto(self):
        version, errores = self.leer((AQUI / "CHANGELOG.md").read_text(encoding="utf-8"))
        self.assertEqual(errores, [])
        self.assertEqual(version, json.loads((AQUI / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))["version"])

    def test_sin_entradas(self):
        self.assertEqual(self.leer("# Solo un título\n\nTexto.\n"), (None, ["CHANGELOG.md: no hay ninguna entrada «## X.Y.Z - AAAA-MM-DD»"]))

    def test_encabezados_mal_formados(self):
        for encabezado in ("## 1.0 - 2026-01-01", "## v1.0.0 - 2026-01-01", "## 1.0.0 – 2026-01-01", "## 01.0.0 - 2026-01-01",
                           "## 1.0.0 - 2026-1-1", "## Otra cosa"):
            version, errores = self.leer(f"{encabezado}\n\nTexto.\n")
            self.assertEqual(version, None, encabezado)
            self.assertTrue(any("el encabezado debe ser" in e for e in errores), (encabezado, errores))

    def test_fecha_que_no_existe(self):
        version, errores = self.leer("## 1.0.0 - 2026-02-30\n\nTexto.\n")
        self.assertEqual(version, None)
        self.assertTrue(any("fecha que no existe" in e for e in errores), errores)

    def test_las_versiones_deben_bajar(self):
        for arriba, abajo in (("1.0.0", "1.0.0"), ("1.0.0", "1.1.0"), ("1.9.0", "1.10.0")):
            _, errores = self.leer(f"## {arriba} - 2026-02-01\n\nUna.\n\n## {abajo} - 2026-01-01\n\nOtra.\n")
            self.assertTrue(any("las versiones deben bajar" in e for e in errores), (arriba, abajo, errores))

    def test_las_fechas_no_pueden_subir(self):
        _, errores = self.leer("## 1.1.0 - 2026-01-01\n\nUna.\n\n## 1.0.0 - 2026-02-01\n\nOtra.\n")
        self.assertTrue(any("las fechas no pueden subir" in e for e in errores), errores)
        _, errores = self.leer("## 1.1.0 - 2026-01-01\n\nUna.\n\n## 1.0.0 - 2026-01-01\n\nOtra.\n")
        self.assertEqual(errores, [], "el mismo día es válido")

    def test_una_entrada_sin_texto(self):
        _, errores = self.leer("## 1.1.0 - 2026-02-01\n\n## 1.0.0 - 2026-01-01\n\nOtra.\n")
        self.assertEqual(len(errores), 1)
        self.assertIn("línea 1: la entrada 1.1.0 no dice qué cambió", errores[0])


class Manifiesto(unittest.TestCase):
    @staticmethod
    def suma(ruta):
        return hashlib.sha256(ruta.read_text(encoding="utf-8").encode("utf-8")).hexdigest()

    def comprobar_contra_archivos(self, carpeta):
        datos = json.loads((carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))
        self.assertEqual(list(datos), ["version", "idiomas", "entradas", "exportes"])
        self.assertEqual(datos["idiomas"], [c for c, _ in gt.IDIOMAS])
        self.assertEqual([e["archivo"] for e in datos["entradas"]],
                         ["colores.csv", "significados-idiomas.csv", "lexico-basico.csv", "evidencia.csv",
                          "significados-culturales.csv", "categorias.csv", "referencias.csv", "nombres-completos.csv"])
        self.assertEqual([e["archivo"] for e in datos["exportes"]],
                         ["exportes/colores.jsonl", "exportes/gazetteer.tsv"]
                         + [f"exportes/spacy/{c}.jsonl" for c, _ in gt.IDIOMAS]
                         + ["exportes/lexico-ud.tsv", "exportes/ejemplos.conllu"])
        for entrada in datos["entradas"] + datos["exportes"]:
            ruta = carpeta / entrada["archivo"]
            texto = ruta.read_text(encoding="utf-8")
            if ruta.suffix == ".conllu":
                filas = texto.count("# sent_id")  # una por frase
            else:
                filas = texto.count("\n") - (0 if ruta.suffix == ".jsonl" else 1)  # menos la cabecera
            self.assertEqual(entrada["sha256"], self.suma(ruta), entrada["archivo"])
            self.assertEqual(entrada["filas"], filas, entrada["archivo"])
        por_archivo = {e["archivo"]: e for e in datos["exportes"]}
        self.assertEqual(por_archivo["exportes/gazetteer.tsv"]["columnas"], exportes.COLUMNAS_GAZETTEER)
        self.assertEqual(por_archivo["exportes/lexico-ud.tsv"]["columnas"], exportes.COLUMNAS_LEXICO_UD)
        return datos

    def test_el_manifiesto_del_repositorio_coincide_con_sus_archivos(self):
        datos = self.comprobar_contra_archivos(AQUI)
        self.assertEqual({e["archivo"]: e["filas"] for e in datos["entradas"]}["colores.csv"], 148)

    def test_el_manifiesto_de_la_carpeta_reducida_coincide_con_sus_archivos(self):
        datos = self.comprobar_contra_archivos(BASE["carpeta"])
        self.assertEqual({e["archivo"]: e["filas"] for e in datos["entradas"]}["nombres-completos.csv"], 244)
        self.assertNotEqual(datos["entradas"][-1]["sha256"],
                            json.loads((AQUI / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))["entradas"][-1]["sha256"])

    def test_manifiesto_sintetico(self):
        texto = manifiesto.manifiesto("1.2.3", ["es", "en"], [("ñandú.csv", 2, "x\n", None)], [("exportes/t.tsv", 1, "ñandú", ["a", "b"])])
        self.assertTrue(texto.endswith("}\n"))
        self.assertIn('"archivo": "ñandú.csv"', texto, "sin escapar los caracteres no ASCII")
        esperado = {"version": "1.2.3", "idiomas": ["es", "en"],
                    "entradas": [{"archivo": "ñandú.csv", "filas": 2, "sha256": hashlib.sha256(b"x\n").hexdigest()}],
                    "exportes": [{"archivo": "exportes/t.tsv", "filas": 1, "columnas": ["a", "b"],
                                  "sha256": hashlib.sha256("ñandú".encode("utf-8")).hexdigest()}]}
        self.assertEqual(json.loads(texto), esperado)

    def test_las_sumas_cambian_con_cualquier_byte(self):
        self.assertNotEqual(manifiesto.sha256("a"), manifiesto.sha256("a "))
        self.assertEqual(manifiesto.sha256("a"), hashlib.sha256(b"a").hexdigest())


class Auxiliares(unittest.TestCase):
    def test_ids_de(self):
        self.assertEqual(gt.ids_de(""), [])
        self.assertEqual(gt.ids_de("a;b;;c"), ["a", "b", "c"])

    def test_etiqueta_base_con_y_sin_referencias(self):
        self.assertEqual(gt.etiqueta_base("derivado", "", gt.BASE_PSICOLOGICO, "../"), "derivado")
        self.assertEqual(gt.etiqueta_base("hay_estudios", "a;b", gt.BASE_PSICOLOGICO, "../"),
                         "hay estudios ([a](../referencias.md#a), [b](../referencias.md#b))")

    def test_leer_rechaza_una_cabecera_distinta(self):
        with tempfile.TemporaryDirectory() as carpeta:
            ruta = Path(carpeta) / "x.csv"
            ruta.write_text("a,b\n1,2\n", encoding="utf-8")
            self.assertEqual(gt.leer(ruta, ["a", "b"]), [{"a": "1", "b": "2"}])
            with self.assertRaises(SystemExit) as contexto:
                gt.leer(ruta, ["a", "c"])
            self.assertIn("cabecera inesperada", str(contexto.exception))

    def test_leer_rechaza_filas_con_mas_o_menos_campos_que_la_cabecera(self):
        with tempfile.TemporaryDirectory() as carpeta:
            ruta = Path(carpeta) / "x.csv"
            for fila in ("1", "1,2,3"):
                ruta.write_text(f"a,b\n1,2\n{fila}\n", encoding="utf-8")
                with self.assertRaises(SystemExit) as contexto:
                    gt.leer(ruta, ["a", "b"])
                self.assertIn("x.csv línea 3: debe tener 2 campos", str(contexto.exception), fila)
            ruta.write_text('a,b\n1,"2,3"\n', encoding="utf-8")  # con comillas, la coma es parte del texto
            self.assertEqual(gt.leer(ruta, ["a", "b"]), [{"a": "1", "b": "2,3"}])

    def test_leer_completa_acepta_tres_o_seis_columnas_y_nada_mas(self):
        with tempfile.TemporaryDirectory() as carpeta:
            ruta = Path(carpeta) / "completa.csv"
            with mock.patch.object(gt, "COMPLETA", ruta):
                ruta.write_text("nombre_ingles,hex,curada\nAlpha,#000000,x\n", encoding="utf-8")
                self.assertEqual(gt.leer_completa(), [{"nombre_ingles": "Alpha", "hex": "#000000", "curada": "x"}])
                ruta.write_text("nombre_ingles,hex,curada,css_cercano,delta_e,cercania\nAlpha,#000000,x,black,0.00,exacto\n",
                                encoding="utf-8")
                self.assertEqual(gt.leer_completa(), [{"nombre_ingles": "Alpha", "hex": "#000000", "curada": "x"}])
                ruta.write_text("nombre_ingles,hex\nAlpha,#000000\n", encoding="utf-8")
                with self.assertRaises(SystemExit):
                    gt.leer_completa()


def exportes_orden(idioma):
    return [c for c, _ in gt.IDIOMAS].index(idioma)


class ArchivosGenerados(unittest.TestCase):
    """Lo que genera main() sobre el proyecto reducido, comparado con los archivos del repositorio."""

    def test_salvo_nombres_completos_salen_identicos_a_los_del_repositorio(self):
        repositorio = [AQUI / "README.md", AQUI / "colores-por-idioma.md", AQUI / "referencias.md", AQUI / "ambiguedad.md",
                       AQUI / "categorias.md", AQUI / "cobertura.md", AQUI / "revisiones.md", AQUI / "visor-revision.html",
                       *sorted((AQUI / "por-idioma").glob("*")),
                       # El manifiesto de la carpeta reducida es otro (otro nombres-completos.csv): se prueba aparte.
                       *sorted(p for p in (AQUI / "exportes").rglob("*") if p.is_file() and p.name != "manifiesto.json")]
        self.assertEqual(len(repositorio), 8 + 30 + (4 + 30),
                         "ocho archivos sueltos, 30 páginas por idioma, 4 exportes y 30 patrones de spaCy")
        for ruta in repositorio:
            relativa = ruta.relative_to(AQUI)
            generado = BASE["carpeta"] / relativa
            self.assertTrue(generado.exists(), f"main() no generó {relativa}")
            self.assertEqual(generado.read_text(encoding="utf-8"), ruta.read_text(encoding="utf-8"),
                             f"{relativa} del repositorio no coincide con lo que generan los CSV")

    def test_hay_una_pagina_por_idioma_y_el_indice_las_lista(self):
        codigos = [c for c, _ in gt.IDIOMAS]
        paginas = sorted(p.stem for p in (BASE["carpeta"] / "por-idioma").glob("*.md"))
        self.assertEqual(paginas, sorted(codigos))
        indice = (BASE["carpeta"] / "colores-por-idioma.md").read_text(encoding="utf-8")
        for codigo in codigos:
            self.assertIn(f"[por-idioma/{codigo}.md](por-idioma/{codigo}.md)", indice)

    def test_la_pagina_en_usa_la_forma_natural_y_las_demas_el_nombre(self):
        pagina = lambda codigo: (BASE["carpeta"] / "por-idioma" / f"{codigo}.md").read_text(encoding="utf-8")
        self.assertIn("| Inglés | Forma natural |", pagina("en"))
        self.assertIn("| `aliceblue` | alice blue |", pagina("en"))
        self.assertIn("| Inglés | Nombre |", pagina("es"))
        self.assertIn("No hay matices culturales registrados para este idioma", pagina("uk"))
        self.assertIn("| Color | Ámbito | Matiz | Solidez | Fuentes |", pagina("zh"))

    def test_la_tabla_del_readme_tiene_una_fila_por_color(self):
        texto = (BASE["carpeta"] / "README.md").read_text(encoding="utf-8")
        tabla = texto.split(gt.INICIO + "\n", 1)[1].split("\n" + gt.FIN, 1)[0]
        self.assertEqual(len(tabla.splitlines()), 2 + 148)

    def test_referencias_md_tiene_un_ancla_por_referencia(self):
        texto = (BASE["carpeta"] / "referencias.md").read_text(encoding="utf-8")
        for f in REAL["referencias"]:
            self.assertEqual(texto.count(f'<a id="{f["id"]}"></a>'), 1, f["id"])

    def test_nombres_completos_gana_las_tres_columnas_calculadas(self):
        with (BASE["carpeta"] / "nombres-completos.csv").open(encoding="utf-8", newline="") as f:
            lector = csv.DictReader(f)
            self.assertEqual(lector.fieldnames, gt.COLUMNAS_COMPLETA)
            filas = list(lector)
        self.assertGreater(len(filas), 100)
        self.assertTrue(all(f["cercania"] in ("exacto", "alta", "media", "baja") for f in filas))

    def test_colores_jsonl(self):
        lineas = (BASE["carpeta"] / "exportes" / "colores.jsonl").read_text(encoding="utf-8").splitlines()
        objetos = [json.loads(linea) for linea in lineas]
        self.assertEqual([o["id"] for o in objetos], [f["nombre_ingles"] for f in REAL["anotada"]])
        self.assertEqual({tuple(sorted(o)) for o in objetos}, {("alias", "categorias", "culturales", "evidencia", "forma_con_guion_en",
                                                                 "forma_natural_en", "hex", "id", "lexico", "nombres",
                                                                 "rgb", "significados")})
        alice = objetos[0]
        self.assertEqual(alice["rgb"], [240, 248, 255])
        self.assertEqual(alice["forma_con_guion_en"], "alice-blue")
        self.assertEqual(list(alice["nombres"]), [c for c, _ in gt.IDIOMAS])
        self.assertEqual(alice["nombres"]["en"], "alice blue")
        gris = next(o for o in objetos if o["id"] == "gray")
        self.assertEqual(gris["alias"], [{"id": "grey", "tipo": "ortografia"}])
        self.assertIn("es", gris["lexico"])
        lima = next(o for o in objetos if o["id"] == "lime")
        self.assertEqual({c: [x["categoria"] for x in lista] for c, lista in lima["categorias"].items()},
                         {"en": ["ADJ", "NOUN", "VERB"], "es": ["ADJ", "NOUN", "VERB"]})
        self.assertEqual(lima["categorias"]["es"][2], {"forma": "lima", "categoria": "VERB", "ocurrencia": "lima",
                                                       "ejemplo": "Ella lima las uñas antes de pintarlas."})
        self.assertEqual(next(o for o in objetos if o["id"] == "aliceblue")["categorias"], {})
        css = {f["nombre_ingles"] for f in REAL["anotada"]}
        propios = sum(1 for f in REAL["categorias"] if f["concepto"] in css)
        entradas = sum(len(lista) for o in objetos for lista in o["categorias"].values())
        self.assertGreaterEqual(entradas, propios)
        self.assertNotIn("lilac", [o["id"] for o in objetos], "los conceptos que no son CSS no entran")
        grey = next(o for o in objetos if o["id"] == "grey")
        self.assertEqual(grey["categorias"]["es"], next(o for o in objetos if o["id"] == "gray")["categorias"]["es"],
                         "el alias hereda las categorías que solo tenía su pareja")

    def test_gazetteer_tsv(self):
        lineas = (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()
        self.assertEqual(lineas[0].split("\t"), exportes.COLUMNAS_GAZETTEER)
        filas = [linea.split("\t") for linea in lineas[1:]]
        self.assertTrue(all(len(f) == len(exportes.COLUMNAS_GAZETTEER) for f in filas))
        self.assertEqual(len(filas), len(set(map(tuple, filas))), "sin filas repetidas")
        formas = [f[0] for f in filas]
        self.assertEqual(formas, sorted(formas, key=lambda x: (x.casefold(), x)))
        self.assertIn(["alice blue", "en", "css", "forma_natural", "aliceblue", "#F0F8FF", "", "alice blue", "", ""], filas)
        self.assertIn(["alice-blue", "en", "css", "forma_con_guion", "aliceblue", "#F0F8FF", "", "alice blue", "", ""], filas)
        self.assertIn(["aliceblue", "en", "css", "identificador", "aliceblue", "#F0F8FF", "", "aliceblue", "", ""], filas)
        self.assertTrue(any(f[1] == "es" and f[2] == "lexico" and f[3] == "lema" and f[4] == "red" for f in filas))

    def test_gazetteer_categorias(self):
        lineas = (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()
        filas = [linea.split("\t") for linea in lineas[1:]]
        self.assertEqual(exportes.COLUMNAS_GAZETTEER[8], "categorias", "va después de las ocho primeras: no las mueve")
        esperadas = {}
        for f in REAL["categorias"]:
            esperadas.setdefault((f["idioma"], f["concepto"], f["forma"]), []).append(f["categoria"])
        # Una forma tiene las categorías de categorias.csv para ella y su hex (así los alias gray y grey las comparten).
        hex_de = {(f[1], f[4]): f[5] for f in filas}
        por_hex = {}
        for (idioma, concepto, forma), lista in esperadas.items():
            por_hex.setdefault((idioma, forma, hex_de[(idioma, concepto)]), lista)
        for f in filas:
            self.assertEqual(f[8], ",".join(por_hex.get((f[1], f[0], f[5]), [])), f[:6])
        self.assertTrue(all(any(f[8] for f in filas if (f[1], f[4], f[0]) == clave) for clave in esperadas),
                        "cada palabra de categorias.csv sale con sus categorías")
        gris = {(f[4], f[8]) for f in filas if f[0] == "gris" and f[1] == "es" and f[2] == "css"}
        self.assertEqual(gris, {("gray", "ADJ,NOUN"), ("grey", "ADJ,NOUN")}, "el alias grey hereda las de gray")
        # Cada palabra de categorias.csv sale en el gazetteer como lema (o como nombre CSS en inglés).
        self.assertTrue(all(any(f[:5] == [forma, idioma, "lexico", "lema", concepto] or f[:5] == [forma, idioma, "css", "forma_natural", concepto]
                                for f in filas) for idioma, concepto, forma in esperadas))
        self.assertIn(["lima", "es", "lexico", "lema", "lime", "#00FF00", "", "lima", "ADJ,NOUN,VERB", "#AAFF32"], filas)
        # Las flexiones y las palabras de otros idiomas no traen categorías.
        self.assertTrue(all(f[8] == "" for f in filas if f[3] == "flexion" or f[1] not in ("en", "es")))

    def test_patrones_de_spacy(self):
        gazetteer = [linea.split("\t") for linea in
                     (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()[1:]]
        conceptos_validos = {f["nombre_ingles"] for f in REAL["anotada"]} | set(gt.CONCEPTOS)
        archivos = sorted(p.name for p in (BASE["carpeta"] / "exportes" / "spacy").iterdir())
        self.assertEqual(archivos, sorted(f"{c}.jsonl" for c, _ in gt.IDIOMAS))
        for codigo, _ in gt.IDIOMAS:
            with self.subTest(codigo):
                texto = (BASE["carpeta"] / "exportes" / "spacy" / f"{codigo}.jsonl").read_text(encoding="utf-8")
                self.assertTrue(texto.endswith("\n"))
                patrones = [json.loads(linea) for linea in texto.splitlines()]
                self.assertTrue(all(list(p) == ["label", "pattern", "id"] for p in patrones))
                self.assertEqual({p["label"] for p in patrones}, {"COLOR"})
                formas = [p["pattern"] for p in patrones]
                self.assertEqual(len(formas), len(set(formas)), "un patrón por forma")
                esperadas = {}
                for forma, idioma, _, _, concepto, *_ in gazetteer:
                    if idioma == codigo:
                        esperadas.setdefault(forma, set()).add(concepto)
                self.assertEqual(formas, list(esperadas), "las formas del gazetteer, en su orden")
                for p in patrones:
                    ids = p["id"].split(";")
                    self.assertEqual(ids, sorted(esperadas[p["pattern"]]), p)
                    self.assertLessEqual(set(ids), conceptos_validos, p)

    def test_patrones_de_spacy_con_formas_de_varios_conceptos(self):
        por_forma = {json.loads(l)["pattern"]: json.loads(l)["id"] for l in
                     (BASE["carpeta"] / "exportes" / "spacy" / "es.jsonl").read_text(encoding="utf-8").splitlines()}
        self.assertEqual(por_forma["verde lima"], "lime;limegreen")  # separados por «;» y en orden alfabético
        self.assertEqual(por_forma["azul claro"], "lightblue")
        self.assertEqual(por_forma["blancas"], "white", "las flexiones también")
        en = {json.loads(l)["pattern"]: json.loads(l)["id"] for l in
              (BASE["carpeta"] / "exportes" / "spacy" / "en.jsonl").read_text(encoding="utf-8").splitlines()}
        self.assertEqual((en["alice blue"], en["alice-blue"], en["aliceblue"]), ("aliceblue",) * 3)

    @unittest.skipUnless(importlib.util.find_spec("spacy"), "spaCy no está instalado")
    def test_los_patrones_cargan_en_spacy_y_reconocen_las_formas(self):
        import spacy  # noqa: PLC0415  (opcional: solo si está instalado)
        for codigo, texto, esperado in (("en", "She wore a light blue dress.", [("light blue", "lightblue")]),
                                        ("es", "Una bufanda verde lima.", [("verde lima", "lime;limegreen")]),
                                        ("zh", "她穿着一条浅蓝色的裙子。", [("浅蓝色", "lightblue")])):
            with self.subTest(codigo):
                nlp = spacy.blank(codigo)
                ruler = nlp.add_pipe("entity_ruler", config={"phrase_matcher_attr": "LOWER"})
                ruler.from_disk(BASE["carpeta"] / "exportes" / "spacy" / f"{codigo}.jsonl")
                self.assertEqual([(e.text, e.ent_id_) for e in nlp(texto).ents], esperado)

    def test_feats_ud(self):
        for rasgos, esperado in (("Gender=Fem;Number=Sing", "Gender=Fem|Number=Sing"),
                                 ("Number=Plur;Animacy=Anim", "Animacy=Anim|Number=Plur"),  # orden alfabético
                                 ("Number=Plur", "Number=Plur"), ("", "_"), ("Terminacion=-e", "_"),
                                 ("Gender=Fem;Terminacion=-e", "_"), ("region=GB", "_")):
            self.assertEqual(exportes._feats_ud(rasgos), esperado, rasgos)

    def test_lexico_ud(self):
        lineas = (BASE["carpeta"] / "exportes" / "lexico-ud.tsv").read_text(encoding="utf-8").splitlines()
        self.assertEqual(lineas[0].split("\t"), exportes.COLUMNAS_LEXICO_UD)
        filas = [linea.split("\t") for linea in lineas[1:]]
        self.assertTrue(all(len(f) == 6 for f in filas))
        self.assertEqual(len(filas), len(set(map(tuple, filas))), "sin filas repetidas")
        self.assertEqual({f[4] for f in filas}, {"en", "es"})
        self.assertLessEqual({f[2] for f in filas}, set(categorias.UPOS))
        # Cada fila de categorias.csv sale con su categoría (los nombres propios con su mayúscula inicial).
        for c in REAL["categorias"]:
            forma = c["ocurrencia"] if c["categoria"] == "PROPN" else c["forma"]
            self.assertIn([forma, forma, c["categoria"], "_", c["idioma"], c["concepto"]], filas)
        for forma, lema, upos, feats, idioma, concepto in filas:
            if upos == "PROPN":
                self.assertTrue(forma[0].isupper(), forma)
            if feats != "_":
                pares = feats.split("|")
                self.assertEqual(pares, sorted(pares), "FEATS en orden alfabético")
                self.assertLessEqual({p.split("=")[0] for p in pares}, exportes.RASGOS_UD)
        # Las flexiones del léxico salen solo como ADJ, con sus rasgos de UD.
        self.assertIn(["blancas", "blanco", "ADJ", "Gender=Fem|Number=Plur", "es", "white"], filas)
        self.assertNotIn(["blancas", "blanco", "NOUN", "Gender=Fem|Number=Plur", "es", "white"], filas)
        # Y las formas flexionadas de las frases de ejemplo, con su lema.
        self.assertIn(["blacked", "black", "VERB", "_", "en", "black"], filas)
        self.assertIn(["khakis", "khaki", "NOUN", "_", "en", "khaki"], filas)
        self.assertEqual(len([f for f in filas if f[0] == "lima"]), 3)
        orden = [(exportes_orden(f[4]), f[0].casefold(), f[0]) for f in filas]
        self.assertEqual(orden, sorted(orden), "ordenado por idioma y forma")

    def test_lexico_ud_con_una_ocurrencia_en_mayuscula_de_frase(self):
        # «Blacked» al empezar la frase es la misma forma que «blacked»; un nombre propio sí conserva la mayúscula.
        categoria = {"idioma": "en", "concepto": "black", "forma": "black", "categoria": "VERB", "ocurrencia": "Blacked", "ejemplo": "Blacked out."}
        propio = {"idioma": "en", "concepto": "peru", "forma": "peru", "categoria": "PROPN", "ocurrencia": "Peru", "ejemplo": "Peru."}
        texto = exportes.lexico_ud({"idiomas": [("en", "Inglés")], "lexico": [], "categorias": [categoria, propio]})
        self.assertEqual(texto.splitlines()[1:], ["black\tblack\tVERB\t_\ten\tblack", "blacked\tblack\tVERB\t_\ten\tblack",
                                                   "Peru\tPeru\tPROPN\t_\ten\tperu"])

    def test_ejemplos_conllu(self):
        texto = (BASE["carpeta"] / "exportes" / "ejemplos.conllu").read_text(encoding="utf-8")
        self.assertTrue(texto.endswith("\n\n") and not texto.endswith("\n\n\n"), "cada frase termina en una línea vacía")
        bloques = texto.rstrip("\n").split("\n\n")
        self.assertEqual(len(bloques), len(REAL["categorias"]))
        ids = []
        for bloque, fila in zip(bloques, REAL["categorias"]):
            lineas = bloque.split("\n")
            self.assertEqual(lineas[0], f"# sent_id = {fila['idioma']}-{fila['concepto']}-{fila['categoria']}")
            self.assertEqual(lineas[1], f"# text = {fila['ejemplo']}")
            ids.append(lineas[0])
            fichas = [linea.split("\t") for linea in lineas[2:]]
            self.assertTrue(all(len(f) == 10 for f in fichas), bloque)
            self.assertEqual([f[0] for f in fichas], [str(n) for n in range(1, len(fichas) + 1)])
            # Las fichas, con su SpaceAfter, reconstruyen la frase.
            rehecha = "".join(f[1] + ("" if f[9] == "SpaceAfter=No" else " ") for f in fichas).rstrip()
            self.assertEqual(rehecha, fila["ejemplo"])
            anotadas = [f for f in fichas if f[3] != "_"]
            self.assertEqual(len(anotadas), 1, bloque)
            lema = fila["ocurrencia"] if fila["categoria"] == "PROPN" else fila["forma"]
            self.assertEqual(anotadas[0][1:4], [fila["ocurrencia"], lema, fila["categoria"]])
            self.assertTrue(all(f[2] == "_" and f[4:9] == ["_"] * 5 for f in fichas if f[3] == "_"))
        self.assertEqual(len(ids), len(set(ids)), "sent_id únicos")

    def test_ejemplos_conllu_pega_los_signos(self):
        datos = {"categorias": [{"idioma": "en", "concepto": "aqua", "forma": "aqua", "categoria": "ADJ",
                                 "ocurrencia": "aqua", "ejemplo": "It is aqua, really!"}]}
        self.assertEqual(exportes.ejemplos_conllu(datos).splitlines(), [
            "# sent_id = en-aqua-ADJ", "# text = It is aqua, really!",
            "1\tIt\t_\t_\t_\t_\t_\t_\t_\t_", "2\tis\t_\t_\t_\t_\t_\t_\t_\t_",
            "3\taqua\taqua\tADJ\t_\t_\t_\t_\t_\tSpaceAfter=No", "4\t,\t_\t_\t_\t_\t_\t_\t_\t_",
            "5\treally\t_\t_\t_\t_\t_\t_\t_\tSpaceAfter=No", "6\t!\t_\t_\t_\t_\t_\t_\t_\t_", ""])

    def test_dos_alias_con_las_mismas_categorias_no_las_duplican_en_el_gazetteer(self):
        datos = datos_reales()
        copias = [dict(f, concepto="grey") for f in datos["categorias"] if f["idioma"] == "es" and f["concepto"] == "gray"]
        datos["categorias"] = datos["categorias"] + copias  # gray y grey (mismo hex) con las mismas filas para «gris»
        gris = [fila for fila in exportes.filas_gazetteer(datos) if fila[1] == "es" and fila[0] == "gris"]
        self.assertTrue(gris)
        self.assertEqual({fila[8] for fila in gris}, {"ADJ,NOUN"})

    def test_gazetteer_forma_normalizada(self):
        lineas = (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()
        filas = [linea.split("\t") for linea in lineas[1:]]
        self.assertEqual(exportes.COLUMNAS_GAZETTEER[7], "forma_normalizada", "va después de las siete primeras: no las mueve")
        for forma, idioma, *_, normalizada, _categorias, _cotidiano in filas:
            self.assertEqual(normalizada, normalizacion.normalizar(forma, idioma), (forma, idioma))
            self.assertNotEqual(normalizada, "", (forma, idioma))
        self.assertIn(["marrón", "es", "lexico", "lema", "brown"], [f[:5] for f in filas if f[7] == "marron"])


class Programa(unittest.TestCase):
    """main() de punta a punta, en una copia temporal del proyecto reducido."""

    def setUp(self):
        temporal = tempfile.TemporaryDirectory()
        self.addCleanup(temporal.cleanup)
        self.carpeta = Path(temporal.name) / "proyecto"
        shutil.copytree(BASE["carpeta"], self.carpeta)

    def estado(self):
        return {p.relative_to(self.carpeta): p.read_bytes() for p in sorted(self.carpeta.rglob("*")) if p.is_file()}

    def reescribir(self, nombre, cambiar):
        ruta = self.carpeta / nombre
        ruta.write_text(cambiar(ruta.read_text(encoding="utf-8")), encoding="utf-8")

    def test_comprobar_pasa_con_todo_al_dia(self):
        codigo, salida, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual((codigo, error), (0, ""))
        self.assertIn("OK: 148 colores anotados en 30 idiomas, 4292 significados traducidos", salida)
        self.assertIn("archivos generados al día", salida)

    def test_generar_no_cambia_nada_si_todo_estaba_al_dia(self):
        antes = self.estado()
        codigo, salida, _ = ejecutar(self.carpeta)
        self.assertEqual(codigo, 0)
        self.assertIn("Generados README.md, nombres-completos.csv, colores-por-idioma.md y 30 páginas en por-idioma/", salida)
        self.assertEqual(self.estado(), antes)

    def test_un_archivo_generado_editado_a_mano_hace_fallar_comprobar(self):
        def en_la_tabla(texto):  # del README solo es generada la tabla entre marcadores
            inicio = texto.index(gt.INICIO)
            n = texto.index("aliceblue", inicio)
            return texto[:n] + "aliceblux" + texto[n + len("aliceblue"):]

        def al_final(texto):  # en los demás, cualquier cambio; al final para no tocar la cabecera de un CSV
            return texto + "x"

        def en_el_ultimo_campo(texto):  # una fila nueva sería un error de campos; esto cambia una columna calculada
            return texto.rstrip("\n") + "x\n"

        for nombre, editar in (("README.md", en_la_tabla), ("colores-por-idioma.md", al_final), ("referencias.md", al_final),
                               ("ambiguedad.md", al_final), ("auditoria-nombres.md", al_final), ("categorias.md", al_final), ("cobertura.md", al_final), ("revisiones.md", al_final), ("visor-revision.html", al_final), ("exportes/manifiesto.json", al_final), ("nombres-completos.csv", en_el_ultimo_campo), ("por-idioma/es.md", al_final),
                               ("por-idioma/ja.md", al_final), ("exportes/colores.jsonl", al_final),
                               ("exportes/gazetteer.tsv", al_final), ("exportes/spacy/es.jsonl", al_final),
                               ("exportes/lexico-ud.tsv", al_final), ("exportes/ejemplos.conllu", al_final)):
            with self.subTest(nombre):
                ruta = self.carpeta / nombre
                original = ruta.read_text(encoding="utf-8")
                try:
                    ruta.write_text(editar(original), encoding="utf-8")
                    codigo, _, error = ejecutar(self.carpeta, "--comprobar")
                finally:
                    ruta.write_text(original, encoding="utf-8")
                self.assertEqual(codigo, 1)
                self.assertIn("No coinciden con los CSV", error)
                self.assertIn(nombre, error)

    def test_editar_el_readme_fuera_de_la_tabla_no_hace_fallar_comprobar(self):
        self.reescribir("README.md", lambda t: "Una línea nueva en la prosa.\n\n" + t)
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_un_archivo_generado_borrado_hace_fallar_comprobar(self):
        (self.carpeta / "por-idioma" / "ko.md").unlink()
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("por-idioma/ko.md", error)

    def test_un_archivo_sobrante_hace_fallar_comprobar_y_generar_lo_borra(self):
        for carpeta, nombre in (("por-idioma", "xx.md"), ("exportes", "viejo.tsv")):
            (self.carpeta / carpeta / nombre).write_text("sobra", encoding="utf-8")
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("por-idioma/xx.md (sobra)", error)
        self.assertIn("exportes/viejo.tsv (sobra)", error)
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertFalse((self.carpeta / "por-idioma" / "xx.md").exists())
        self.assertFalse((self.carpeta / "exportes" / "viejo.tsv").exists())
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_un_archivo_sobrante_en_una_subcarpeta_de_exportes_tambien_se_detecta(self):
        (self.carpeta / "exportes" / "spacy" / "xx.jsonl").write_text("sobra", encoding="utf-8")
        (self.carpeta / "exportes" / "otra").mkdir()
        (self.carpeta / "exportes" / "otra" / "viejo.txt").write_text("sobra", encoding="utf-8")
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("exportes/spacy/xx.jsonl (sobra)", error)
        self.assertIn("exportes/otra/viejo.txt (sobra)", error)
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertFalse((self.carpeta / "exportes" / "spacy" / "xx.jsonl").exists())
        self.assertFalse((self.carpeta / "exportes" / "otra" / "viejo.txt").exists())
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_editar_un_csv_deja_los_archivos_desfasados_hasta_regenerar(self):
        self.reescribir("colores.csv", lambda t: t.replace("rojo", "rojísimo", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("No coinciden con los CSV", error)
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_un_csv_con_errores_sale_con_1_sin_tocar_nada(self):
        antes = self.estado()
        self.reescribir("colores.csv", lambda t: t.replace("#F0F8FF", "#f0f8ff", 1))
        antes["colores.csv"] = (self.carpeta / "colores.csv").read_bytes()
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual(codigo, 1)
        self.assertIn("Errores en los CSV", error)
        self.assertIn("colores.csv línea 2: hex inválido", error)
        self.assertEqual(salida, "")
        self.assertEqual({str(k): v for k, v in self.estado().items()}, {str(k): v for k, v in antes.items()})

    def test_con_mas_de_30_errores_muestra_30_y_cuenta_el_resto(self):
        def vaciar(texto):
            lineas = texto.splitlines(keepends=True)
            for n in range(1, 36):  # 35 filas de datos
                campos = next(csv.reader([lineas[n]]))
                campos[2] = ""  # nombre_es
                salida = io.StringIO()
                csv.writer(salida, lineterminator="\n").writerow(campos)
                lineas[n] = salida.getvalue()
            return "".join(lineas)
        self.reescribir("colores.csv", vaciar)
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertEqual(error.count("hay campos vacíos"), 30)
        self.assertIn("... y 5 errores más", error)

    def test_varios_csv_rotos_se_cuentan_todos_juntos(self):
        self.reescribir("lexico-basico.csv", lambda t: t.replace("concepto,", "concept,", 1))
        self.reescribir("categorias.csv", lambda t: t.replace("idioma,", "lang,", 1))
        lineas = (self.carpeta / "evidencia.csv").read_text(encoding="utf-8").splitlines(keepends=True)
        lineas[3] = lineas[3].rstrip("\n") + ",sobra\n"
        (self.carpeta / "evidencia.csv").write_text("".join(lineas), encoding="utf-8")
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual((codigo, salida), (1, ""))
        self.assertIn("No se pudieron leer los CSV", error)
        for esperado in ("lexico-basico.csv: cabecera inesperada", "categorias.csv: cabecera inesperada", "evidencia.csv"):
            self.assertIn(esperado, error)

    def test_cabecera_inesperada(self):
        self.reescribir("lexico-basico.csv", lambda t: t.replace("concepto,", "concept,", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("lexico-basico.csv: cabecera inesperada", error)

    def test_una_fila_con_mas_o_menos_campos_que_la_cabecera_es_un_error_en_cada_csv(self):
        # Antes, una fila corta rompía con un traceback en cuatro CSV y una larga se aceptaba en silencio en seis.
        for nombre, columnas in (("colores.csv", 34), ("significados-idiomas.csv", 4), ("lexico-basico.csv", 5),
                                 ("evidencia.csv", 5), ("significados-culturales.csv", 5), ("categorias.csv", 6), ("referencias.csv", 5),
                                 ("nombres-completos.csv", 6)):
            for variante in ("corta", "larga"):
                with self.subTest(nombre, variante=variante):
                    ruta = self.carpeta / nombre
                    original = ruta.read_text(encoding="utf-8")
                    lineas = original.splitlines(keepends=True)
                    lineas[2] = lineas[2].split(",")[0] + "\n" if variante == "corta" else lineas[2].rstrip("\n") + ",sobra\n"
                    try:
                        ruta.write_text("".join(lineas), encoding="utf-8")
                        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
                    finally:
                        ruta.write_text(original, encoding="utf-8")
                    self.assertEqual(codigo, 1)
                    self.assertIn(f"{nombre} línea 3: debe tener {columnas} campos", error)

    def test_cambiar_un_csv_desfasa_el_manifiesto(self):
        self.reescribir("colores.csv", lambda t: t.replace("rojo", "rojísimo", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("exportes/manifiesto.json", error)

    def poner_changelog(self, texto):
        """Un CHANGELOG propio: estas pruebas no dependen de lo que diga el real."""
        (self.carpeta / "CHANGELOG.md").write_text(texto, encoding="utf-8")

    def test_la_version_del_manifiesto_sale_del_changelog(self):
        self.poner_changelog("# Cambios\n\n## 1.0.0 - 2026-01-01\n\nPrimera.\n")
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertEqual(json.loads((self.carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))["version"], "1.0.0")
        self.poner_changelog("# Cambios\n\n## 1.1.0 - 2026-02-01\n\nUn cambio.\n\n## 1.0.0 - 2026-01-01\n\nPrimera.\n")
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("No coinciden con los CSV: exportes/manifiesto.json.", error, "solo el manifiesto cambia con la versión")
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertEqual(json.loads((self.carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))["version"], "1.1.0")
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_sin_changelog_es_un_error(self):
        (self.carpeta / "CHANGELOG.md").unlink()
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("CHANGELOG.md: no existe", error)

    def test_un_changelog_invalido_es_un_error_y_no_toca_nada(self):
        antes = self.estado()
        self.poner_changelog("# Cambios\n\n## 1.0 - 2026-01-01\n\nPrimera.\n")
        antes["CHANGELOG.md"] = (self.carpeta / "CHANGELOG.md").read_bytes()
        codigo, _, error = ejecutar(self.carpeta)
        self.assertEqual(codigo, 1)
        self.assertIn("Errores en los CSV", error)
        self.assertIn("CHANGELOG.md línea", error)
        self.assertEqual({str(k): v for k, v in self.estado().items()}, {str(k): v for k, v in antes.items()})

    def test_un_csv_con_finales_de_linea_crlf_da_el_mismo_manifiesto(self):
        # Un checkout de Windows con autocrlf: las sumas se calculan sobre el texto con los finales de línea unificados.
        for nombre in ("colores.csv", "lexico-basico.csv"):
            ruta = self.carpeta / nombre
            ruta.write_bytes(ruta.read_bytes().replace(b"\n", b"\r\n"))
        codigo, salida, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual((codigo, error), (0, ""), salida)

    def test_readme_sin_marcadores(self):
        self.reescribir("README.md", lambda t: t.replace(gt.INICIO, "<!-- otro -->"))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("faltan los marcadores", error)

    def test_una_mencion_de_los_marcadores_en_la_prosa_no_cuenta(self):
        self.reescribir("README.md", lambda t: t.replace(gt.INICIO, "x " + gt.INICIO + " x", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("faltan los marcadores", error)

    def test_una_referencia_sin_usar_es_un_error(self):
        sin_uso = "zzz-sin-uso,libro,Una obra que nadie cita,,existencia\n"
        self.reescribir("referencias.csv", lambda t: t.rstrip("\n") + "\n" + sin_uso)
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("la referencia 'zzz-sin-uso' no la usa ningún otro CSV", error)

    def test_sqlite_construye_la_base_y_no_regenera_nada(self):
        antes = self.estado()
        ruta = self.carpeta / "datos.db"
        for _ in range(2):  # la segunda vez la base ya existe y se reemplaza
            codigo, salida, error = ejecutar(self.carpeta, "--sqlite", str(ruta))
            self.assertEqual((codigo, error), (0, ""))
        self.assertIn("Base SQLite en", salida)
        conexion = sqlite3.connect(ruta)
        self.addCleanup(conexion.close)
        cuenta = lambda tabla: conexion.execute(f"SELECT count(*) FROM {tabla}").fetchone()[0]
        self.assertEqual({t: cuenta(t) for t in ("colores", "nombres", "significados", "evidencia", "referencias", "lexico")},
                         {"colores": 148, "nombres": 148 * 30, "significados": 148 * 30, "evidencia": 148,
                          "referencias": len(REAL["referencias"]), "lexico": len(REAL["lexico"])})
        self.assertEqual(cuenta("culturales"), len(REAL["culturales"]))
        self.assertEqual(cuenta("categorias"), len(REAL["categorias"]))
        self.assertEqual(conexion.execute(
            "SELECT categoria, ocurrencia FROM categorias WHERE forma = 'lima' AND idioma = 'es' ORDER BY categoria").fetchall(),
            [("ADJ", "lima"), ("NOUN", "lima"), ("VERB", "lima")])
        self.assertEqual(cuenta("nombres_completos"), len((self.carpeta / "nombres-completos.csv").read_text(encoding="utf-8").splitlines()) - 1)
        japones = next(f for f in REAL["significados"] if f["idioma"] == "ja" and f["nombre_ingles"] == "crimson")
        consulta = ("SELECT s.psicologico FROM nombres n JOIN significados s USING (color_id, idioma) "
                    "WHERE n.color_id = 'crimson' AND n.idioma = 'ja'")
        self.assertEqual(conexion.execute(consulta).fetchone()[0], japones["significado_psicologico"])
        # Buscar sin tildes ni mayúsculas: la forma normalizada encuentra «marrón» y «alice blue».
        self.assertIn(("marrón",), conexion.execute(
            "SELECT forma FROM lexico WHERE forma_normalizada = 'marron' AND idioma = 'es'").fetchall())
        self.assertEqual(conexion.execute(
            "SELECT nombre FROM nombres WHERE nombre_normalizado = 'alice blue' AND idioma = 'en'").fetchall(), [("alice blue",)])
        self.assertEqual(conexion.execute(
            "SELECT nombre, nombre_normalizado FROM nombres WHERE color_id = 'crimson' AND idioma = 'es'").fetchall(),
            [("carmesí", "carmesi")])
        conexion.close()
        ruta.unlink()
        self.assertEqual(self.estado(), antes)

    def test_sqlite_sin_ruta(self):
        codigo, _, error = ejecutar(self.carpeta, "--sqlite")
        self.assertEqual(codigo, 1)
        self.assertIn("--sqlite necesita la ruta de la base", error)

    def test_comprobar_falla_si_la_base_no_coincide_con_los_csv(self):
        original = exportes.construir_sqlite
        for tabla in ("lexico", "categorias"):
            def con_una_fila_menos(ruta, datos):
                conexion = original(ruta, datos)
                conexion.execute(f"DELETE FROM {tabla} WHERE rowid = (SELECT min(rowid) FROM {tabla})")
                return conexion

            with self.subTest(tabla), mock.patch.object(exportes, "construir_sqlite", con_una_fila_menos):
                codigo, _, error = ejecutar(self.carpeta, "--comprobar")
                self.assertEqual(codigo, 1)
                self.assertIn("La base SQLite no coincide con los CSV", error)
                esperadas = len(REAL[tabla])
                self.assertIn(f"{tabla}: {esperadas - 1} filas, se esperaban {esperadas}", error)

    def test_un_alias_con_otro_significado_sale_con_1(self):
        def cambiar(texto):
            frase = "Frescura, claridad, calma y comunicación; asociado a lo digital y moderno."
            self.assertEqual(texto.count(frase), 2, "aqua y cyan llevan el mismo texto")
            return texto.replace(frase, frase + " Y más.", 1)  # solo cambia la fila de aqua, la primera
        self.reescribir("colores.csv", cambiar)
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual((codigo, salida), (1, ""))
        self.assertIn("colores.csv: aqua y cyan tienen el mismo hex y deben tener los mismos significados en español", error)

    def test_un_categorias_csv_con_errores_sale_con_1_sin_tocar_nada(self):
        antes = self.estado()
        self.reescribir("categorias.csv", lambda t: t.replace(",ADJ,aqua,", ",ADV,aqua,", 1))
        antes[Path("categorias.csv")] = (self.carpeta / "categorias.csv").read_bytes()
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual(codigo, 1)
        self.assertIn("Errores en los CSV", error)
        self.assertIn("categorias.csv línea 2: categoría desconocida 'ADV'", error)
        self.assertEqual((salida, self.estado()), ("", antes))

    def test_un_revisiones_csv_con_errores_sale_con_1_sin_tocar_nada(self):
        antes = self.estado()
        self.reescribir("revisiones.csv", lambda t: t + "es,nombres,aliceblue,quizá,Ana,2026-10-23,0123abcd,\n")
        antes[Path("revisiones.csv")] = (self.carpeta / "revisiones.csv").read_bytes()
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual(codigo, 1)
        self.assertIn("revisiones.csv línea 2: veredicto desconocido 'quizá'", error)
        self.assertEqual((salida, self.estado()), ("", antes))

    def test_una_revision_nueva_cambia_el_informe_y_no_el_manifiesto(self):
        manifiesto_antes = (self.carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8")
        visor_antes = (self.carpeta / "visor-revision.html").read_bytes()
        self.reescribir("revisiones.csv", lambda t: t + f"es,nombres,aliceblue,correcto,Ana,2026-10-23,{huellas_reales()[('es', 'nombres')]['aliceblue']},\n")
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("revisiones.md", error)
        self.assertNotIn("manifiesto.json", error, "el registro de revisiones no es parte de los datos del manifiesto")
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertIn("| Español (`es`) | 1 de 148 |", (self.carpeta / "revisiones.md").read_text(encoding="utf-8"))
        self.assertEqual((self.carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8"), manifiesto_antes)
        self.assertEqual((self.carpeta / "visor-revision.html").read_bytes(), visor_antes, "el visor no depende de las revisiones")
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_editar_categorias_csv_deja_categorias_md_desfasado_hasta_regenerar(self):
        self.reescribir("categorias.csv", lambda t: t.replace("She painted the bathroom walls aqua.",
                                                              "She painted the walls aqua.", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        for archivo in ("categorias.md", "exportes/colores.jsonl", "exportes/manifiesto.json"):  # el gazetteer no lleva las frases
            self.assertIn(archivo, error)
        self.assertNotIn("gazetteer.tsv", error)
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertIn("She painted the walls aqua.", (self.carpeta / "categorias.md").read_text(encoding="utf-8"))
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_si_la_autoprueba_de_color_falla_main_sale_con_1(self):
        with mock.patch.object(colorimetria, "VECTORES_SHARMA", [((50.0, 2.6772, -79.7751), (50.0, 0.0, -82.7485), 9.0)]):
            codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("CIEDE2000 no coincide con 1 vectores de Sharma et al.", error)


if __name__ == "__main__":
    unittest.main()
