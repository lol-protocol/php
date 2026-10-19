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
import importlib.util
import io
import json
import shutil
import sqlite3
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

AQUI = Path(__file__).resolve().parent
sys.dont_write_bytecode = True  # no dejar __pycache__ de los módulos de al lado
sys.path.insert(0, str(AQUI))
import colorimetria  # noqa: E402
import exportes  # noqa: E402

# El generador se llama generar-tabla.py (con guion): no se puede importar con `import`.
_especificacion = importlib.util.spec_from_file_location("generar_tabla", AQUI / "generar-tabla.py")
gt = importlib.util.module_from_spec(_especificacion)
_especificacion.loader.exec_module(gt)

ARCHIVOS_DE_ENTRADA = ["colores.csv", "significados-idiomas.csv", "lexico-basico.csv", "evidencia.csv",
                       "significados-culturales.csv", "referencias.csv", "README.md"]
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


def ejecutar(carpeta, *argumentos):
    """Corre main() de generar-tabla.py contra `carpeta`. Devuelve (código de salida, stdout, stderr)."""
    rutas = {
        "CARPETA": carpeta, "ANOTADA": carpeta / "colores.csv", "COMPLETA": carpeta / "nombres-completos.csv",
        "SIGNIFICADOS": carpeta / "significados-idiomas.csv", "LEXICO": carpeta / "lexico-basico.csv",
        "REFERENCIAS": carpeta / "referencias.csv", "EVIDENCIA": carpeta / "evidencia.csv",
        "CULTURALES": carpeta / "significados-culturales.csv", "REFS_MD": carpeta / "referencias.md",
        "DIR_EXPORTES": carpeta / "exportes", "README": carpeta / "README.md",
        "INDICE": carpeta / "colores-por-idioma.md", "DIR_IDIOMAS": carpeta / "por-idioma",
    }
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


class ArchivosGenerados(unittest.TestCase):
    """Lo que genera main() sobre el proyecto reducido, comparado con los archivos del repositorio."""

    def test_salvo_nombres_completos_salen_identicos_a_los_del_repositorio(self):
        repositorio = [AQUI / "README.md", AQUI / "colores-por-idioma.md", AQUI / "referencias.md",
                       *sorted((AQUI / "por-idioma").glob("*")), *sorted((AQUI / "exportes").glob("*"))]
        self.assertEqual(len(repositorio), 3 + 30 + 2, "tres archivos sueltos, 30 páginas por idioma y 2 exportes")
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
        self.assertEqual({tuple(sorted(o)) for o in objetos}, {("alias", "culturales", "evidencia", "forma_con_guion_en",
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

    def test_gazetteer_tsv(self):
        lineas = (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()
        self.assertEqual(lineas[0].split("\t"), exportes.COLUMNAS_GAZETTEER)
        filas = [linea.split("\t") for linea in lineas[1:]]
        self.assertTrue(all(len(f) == len(exportes.COLUMNAS_GAZETTEER) for f in filas))
        self.assertEqual(len(filas), len(set(map(tuple, filas))), "sin filas repetidas")
        formas = [f[0] for f in filas]
        self.assertEqual(formas, sorted(formas, key=lambda x: (x.casefold(), x)))
        self.assertIn(["alice blue", "en", "css", "forma_natural", "aliceblue", "#F0F8FF", ""], filas)
        self.assertIn(["alice-blue", "en", "css", "forma_con_guion", "aliceblue", "#F0F8FF", ""], filas)
        self.assertTrue(any(f[1] == "es" and f[2] == "lexico" and f[3] == "lema" and f[4] == "red" for f in filas))


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
                               ("nombres-completos.csv", en_el_ultimo_campo), ("por-idioma/es.md", al_final),
                               ("por-idioma/ja.md", al_final), ("exportes/colores.jsonl", al_final),
                               ("exportes/gazetteer.tsv", al_final)):
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

    def test_cabecera_inesperada(self):
        self.reescribir("lexico-basico.csv", lambda t: t.replace("concepto,", "concept,", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("lexico-basico.csv: cabecera inesperada", error)

    def test_una_fila_con_mas_o_menos_campos_que_la_cabecera_es_un_error_en_cada_csv(self):
        # Antes, una fila corta rompía con un traceback en cuatro CSV y una larga se aceptaba en silencio en seis.
        for nombre, columnas in (("colores.csv", 34), ("significados-idiomas.csv", 4), ("lexico-basico.csv", 5),
                                 ("evidencia.csv", 5), ("significados-culturales.csv", 5), ("referencias.csv", 5),
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
        self.assertEqual(cuenta("nombres_completos"), len((self.carpeta / "nombres-completos.csv").read_text(encoding="utf-8").splitlines()) - 1)
        japones = next(f for f in REAL["significados"] if f["idioma"] == "ja" and f["nombre_ingles"] == "crimson")
        consulta = ("SELECT s.psicologico FROM nombres n JOIN significados s USING (color_id, idioma) "
                    "WHERE n.color_id = 'crimson' AND n.idioma = 'ja'")
        self.assertEqual(conexion.execute(consulta).fetchone()[0], japones["significado_psicologico"])
        conexion.close()
        ruta.unlink()
        self.assertEqual(self.estado(), antes)

    def test_sqlite_sin_ruta(self):
        codigo, _, error = ejecutar(self.carpeta, "--sqlite")
        self.assertEqual(codigo, 1)
        self.assertIn("--sqlite necesita la ruta de la base", error)

    def test_comprobar_falla_si_la_base_no_coincide_con_los_csv(self):
        original = exportes.construir_sqlite

        def con_una_fila_menos(ruta, datos):
            conexion = original(ruta, datos)
            conexion.execute("DELETE FROM lexico WHERE rowid = (SELECT min(rowid) FROM lexico)")
            return conexion

        with mock.patch.object(exportes, "construir_sqlite", con_una_fila_menos):
            codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("La base SQLite no coincide con los CSV", error)
        self.assertIn(f"lexico: {len(REAL['lexico']) - 1} filas, se esperaban {len(REAL['lexico'])}", error)

    def test_la_autoprueba_de_color_corta_antes_de_leer_nada(self):
        with mock.patch.object(colorimetria, "VECTORES_SHARMA", [((50.0, 2.6772, -79.7751), (50.0, 0.0, -82.7485), 9.0)]):
            codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("CIEDE2000 no coincide con 1 vectores de Sharma et al.", error)


if __name__ == "__main__":
    unittest.main()
