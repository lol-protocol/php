"""Pruebas de mortalidad-historica: el validador, la generación y las consultas.

  python3 -m unittest -v pruebas

Los datos de prueba son inventados y viven en directorios temporales: no tocan datos/.
"""
import csv
import tempfile
import unittest
from pathlib import Path
from unittest import mock

import comun
import consultas
import generar_tabla as g


def escribir(carpeta, nombre, filas):
    with (carpeta / f"{nombre}.csv").open("w", encoding="utf-8", newline="") as f:
        e = csv.writer(f)
        e.writerow(g.COLUMNAS[nombre])
        e.writerows(filas)


class Plantilla(unittest.TestCase):
    """Una carpeta datos/ temporal con filas válidas, que cada prueba estropea a su manera."""

    def setUp(self):
        self._tmp = tempfile.TemporaryDirectory()
        self.addCleanup(self._tmp.cleanup)
        self.datos = Path(self._tmp.name)
        escribir(self.datos, "causas_modernas", [("tuberculosis", "Tuberculosis", "Tuberculosis", "A15;A16", "infecciosa")])
        escribir(self.datos, "causas_historicas", [("tisis", "spa", "tuberculosis", "alta", "", "", "", "borrador", "")])
        escribir(self.datos, "epidemias", [("peste", "Peste", "Y. pestis", "", "1346", "1353", "Europa;Asia", "", "", "", "media", "", "borrador", "")])
        escribir(self.datos, "hereditarias", [("fq", "Fibrosis quística", "Cystic fibrosis", "autosómica recesiva", "219700", "todas las poblaciones", "infancia", "", "borrador", "")])
        escribir(self.datos, "hitos_registro", [("CL", "civil", "1885", "", "borrador", "")])
        escribir(self.datos, "esperanza_vida", [])
        # validar() lee de comun.DATOS a través de leer_csv(DATOS / ...), que generar_tabla importó.
        parche = mock.patch.object(g, "DATOS", self.datos)
        parche.start()
        self.addCleanup(parche.stop)

    def errores(self):
        return g.validar()[0]

    def cambiar(self, nombre, indice, columna, valor):
        columnas, filas = comun.leer_csv(self.datos / f"{nombre}.csv")
        filas[indice][columna] = valor
        escribir(self.datos, nombre, [[f[c] for c in columnas] for f in filas])


class ValidadorTest(Plantilla):
    def test_datos_validos(self):
        self.assertEqual(self.errores(), [])

    def test_columnas_incorrectas(self):
        (self.datos / "hitos_registro.csv").write_text("codigo,tipo\nCL,civil\n", encoding="utf-8")
        self.assertTrue(any("las columnas deberían ser" in e for e in self.errores()))

    def test_idioma_de_dos_letras_no_vale(self):
        self.cambiar("causas_historicas", 0, "idioma", "es")
        self.assertTrue(any("ISO 639-3" in e for e in self.errores()))

    def test_causa_inexistente(self):
        self.cambiar("causas_historicas", 0, "causa_id", "peste_inventada")
        self.assertTrue(any("no está en causas_modernas.csv" in e for e in self.errores()))

    def test_verificado_exige_fuente(self):
        self.cambiar("causas_historicas", 0, "estado", "verificado")
        self.assertTrue(any("necesita su fuente" in e for e in self.errores()))
        self.cambiar("causas_historicas", 0, "fuente", "Farr, 1839")
        self.assertEqual(self.errores(), [])

    def test_termino_repetido_con_la_misma_causa(self):
        escribir(self.datos, "causas_historicas", [
            ("tisis", "spa", "tuberculosis", "alta", "", "", "", "borrador", ""),
            ("Tisis", "spa", "tuberculosis", "media", "", "", "", "borrador", ""),
        ])
        self.assertTrue(any("con la misma causa ya está" in e for e in self.errores()))

    def test_anios_en_desorden(self):
        self.cambiar("epidemias", 0, "desde", "1400")
        self.assertTrue(any("posterior a hasta" in e for e in self.errores()))

    def test_region_inexistente(self):
        self.cambiar("epidemias", 0, "regiones", "Europa;Atlantida")
        self.assertTrue(any("Atlantida" in e for e in self.errores()))

    def test_muertes_van_juntas(self):
        self.cambiar("epidemias", 0, "muertes_min", "100")
        self.assertTrue(any("van juntas" in e for e in self.errores()))

    def test_cie10_mal_formado(self):
        self.cambiar("causas_modernas", 0, "cie10", "A15;tb")
        self.assertTrue(any("cie10 «tb»" in e for e in self.errores()))

    def test_omim_de_seis_digitos(self):
        self.cambiar("hereditarias", 0, "omim", "123")
        self.assertTrue(any("omim" in e for e in self.errores()))

    def test_hereditaria_exige_donde_es_mas_frecuente(self):
        self.cambiar("hereditarias", 0, "frecuencia_mayor_en", "")
        self.assertTrue(any("frecuencia_mayor_en" in e for e in self.errores()))


class EsperanzaDeVidaTest(Plantilla):
    FILA = ["CL", "1900", "1909", "T", "20", "38.5", "0.62", "36.0", "41.0", "B", "borrador", ""]

    def probar(self, **cambios):
        fila = list(self.FILA)
        for columna, valor in cambios.items():
            fila[g.COLUMNAS["esperanza_vida"].index(columna)] = valor
        escribir(self.datos, "esperanza_vida", [fila])
        return self.errores()

    def test_fila_valida(self):
        self.assertEqual(self.probar(), [])

    def test_region_valida(self):
        self.assertEqual(self.probar(codigo="R:Europa"), [])
        self.assertTrue(self.probar(codigo="R:Marte"))

    def test_esperanza_fuera_de_la_banda(self):
        self.assertTrue(any("entre incertidumbre" in e for e in self.probar(esperanza_restante="50")))

    def test_supervivencia_es_una_probabilidad(self):
        self.assertTrue(any("0 a 1" in e for e in self.probar(supervivencia_hasta_edad="62")))

    def test_sexo_y_calidad(self):
        self.assertTrue(self.probar(sexo="X"))
        self.assertTrue(self.probar(calidad="Z"))

    def test_periodo_abierto_no_vale(self):
        self.assertTrue(any("falta hasta" in e for e in self.probar(hasta="")))

    def test_fila_repetida(self):
        escribir(self.datos, "esperanza_vida", [self.FILA, self.FILA])
        self.assertTrue(any("repetido" in e for e in self.errores()))


class GeneracionTest(unittest.TestCase):
    """Sobre los datos reales del repositorio."""

    @classmethod
    def setUpClass(cls):
        cls.errores, cls.datos = g.validar()

    def test_datos_reales_validos(self):
        self.assertEqual(self.errores, [])

    def test_salida_al_dia(self):
        for ruta, contenido in g.salida_esperada(self.datos).items():
            self.assertTrue(ruta.exists(), ruta)
            self.assertEqual(ruta.read_text(encoding="utf-8"), contenido, f"{ruta.name} no está al día")

    def test_hay_una_pagina_por_idioma_y_son_veinte(self):
        self.assertEqual(len(comun.IDIOMAS), 20)
        for codigo in comun.IDIOMAS:
            self.assertEqual(len(codigo), 3, codigo)
            self.assertTrue((comun.POR_IDIOMA / f"{codigo}.md").exists(), codigo)

    def test_arabe_y_hebreo_van_de_derecha_a_izquierda(self):
        for codigo in ("ara", "heb"):
            self.assertIn('<div dir="rtl">', g.pagina_idioma(codigo, self.datos))
        self.assertNotIn("dir=", g.pagina_idioma("spa", self.datos))

    def test_todo_idioma_tiene_terminos(self):
        # No es una regla del validador (un idioma puede quedar vacío un tiempo), pero hoy se cumple y
        # el «Resumen» del README lo dice: si deja de cumplirse, que sea a propósito.
        self.assertEqual({f["idioma"] for f in self.datos["causas_historicas"]}, set(comun.IDIOMAS))

    def test_ningun_dato_se_da_por_verificado_sin_fuente(self):
        for tabla in ("causas_historicas", "epidemias", "hereditarias", "hitos_registro"):
            for f in self.datos[tabla]:
                if f["estado"] == "verificado":
                    self.assertTrue(f["fuente"], (tabla, f))

    def test_las_hereditarias_son_de_poblacion_no_de_personas(self):
        # Ninguna columna habla de una persona, y toda fila dice dónde es mayor la frecuencia.
        self.assertEqual(g.COLUMNAS["hereditarias"], [
            "id", "nombre_spa", "nombre_eng", "herencia", "omim", "frecuencia_mayor_en", "inicio_tipico",
            "nota", "estado", "fuente"])
        self.assertTrue(all(f["frecuencia_mayor_en"] for f in self.datos["hereditarias"]))


class ConsultasTest(unittest.TestCase):
    def test_causa_moderna_ignora_tildes_y_mayusculas(self):
        a = consultas.causa_moderna("Apoplejía", "spa")
        b = consultas.causa_moderna("apoplejia", "spa")
        self.assertEqual(a, b)
        self.assertEqual(a[0]["causa_id"], "ictus")
        self.assertEqual(a[0]["confianza"], "alta")
        self.assertIn("I64", a[0]["cie10"])

    def test_causa_moderna_en_otros_alfabetos(self):
        self.assertEqual(consultas.causa_moderna("чахотка", "rus")[0]["causa_id"], "tuberculosis")
        self.assertEqual(consultas.causa_moderna("سل", "ara")[0]["causa_id"], "tuberculosis")
        self.assertEqual(consultas.causa_moderna("天花", "zho")[0]["causa_id"], "viruela")

    def test_el_idioma_importa(self):
        # «croup» está en inglés; «tisis» no está en inglés.
        self.assertTrue(consultas.causa_moderna("croup", "eng"))
        self.assertEqual(consultas.causa_moderna("tisis", "eng"), [])

    def test_termino_desconocido_no_inventa_nada(self):
        self.assertEqual(consultas.causa_moderna("mal de ojo", "spa"), [])

    def test_idioma_invalido(self):
        with self.assertRaises(ValueError):
            consultas.causa_moderna("tisis", "es")

    def test_resultados_de_mas_a_menos_seguro(self):
        for termino in ("consumption", "croup"):
            confianzas = [x["confianza"] for x in consultas.causa_moderna(termino, "eng")]
            self.assertEqual(confianzas, sorted(confianzas, key=("alta", "media", "baja").index))

    def test_epidemias_en_un_anio_y_una_region(self):
        ids = [e["id"] for e in consultas.epidemias_en(1348, "Europa")]
        self.assertIn("peste_negra", ids)
        self.assertNotIn("gripe_1918", ids)
        self.assertEqual(consultas.epidemias_en(1348, "Oceanía"), [])

    def test_epidemia_abierta_sigue_hasta_hoy(self):
        self.assertIn("viruela_en_america", [e["id"] for e in consultas.epidemias_en(1700, "América")])

    def test_region_invalida(self):
        with self.assertRaises(ValueError):
            consultas.epidemias_en(1900, "Atlantida")

    def test_hitos(self):
        self.assertEqual(consultas.hitos_de("CL")[0]["desde"], "1885")
        self.assertEqual(consultas.hitos_de("ZZ"), [])


class NormalizarTest(unittest.TestCase):
    def test_normalizar(self):
        self.assertEqual(comun.normalizar("  Schlagfluß "), "schlagfluss")
        self.assertEqual(comun.normalizar("CÓLERA   morbo"), "colera morbo")
        self.assertEqual(comun.normalizar("اَلسُّلّ"), comun.normalizar("السل"))


if __name__ == "__main__":
    unittest.main()
