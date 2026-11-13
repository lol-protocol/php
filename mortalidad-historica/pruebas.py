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
import descargar_fuentes
import generar_tabla as g
import preparar_datos as pd


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
        escribir(self.datos, "paises", [("CL", "Chile", "América"), ("GB", "Reino Unido", "Europa")])
        escribir(self.datos, "causas_modernas", [("tuberculosis", "Tuberculosis", "Tuberculosis", "A15;A16", "infecciosa")])
        escribir(self.datos, "causas_historicas", [("tisis", "spa", "tuberculosis", "alta", "", "", "", "borrador", "")])
        escribir(self.datos, "epidemias", [("peste", "Peste", "Y. pestis", "", "1346", "1353", "Europa;Asia", "", "", "", "", "media", "", "borrador", "")])
        escribir(self.datos, "hereditarias", [("fq", "Fibrosis quística", "Cystic fibrosis", "autosómica recesiva", "219700", "todas las poblaciones", "infancia", "", "borrador", "")])
        escribir(self.datos, "hitos_registro", [("CL", "civil", "1885", "", "borrador", "")])
        escribir(self.datos, "londres_muertes", [("1665", "97306", "655", "verificado", "londres-owid")])
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


class ValidadorPaisesYLondresTest(Plantilla):
    def test_region_de_un_pais(self):
        self.cambiar("paises", 0, "region", "Atlantida")
        self.assertTrue(any("region «Atlantida»" in e for e in self.errores()))

    def test_pais_repetido(self):
        escribir(self.datos, "paises", [("CL", "Chile", "América"), ("CL", "Chile otra vez", "América")])
        self.assertTrue(any("repetido" in e for e in self.errores()))

    def test_epidemia_con_pais_que_no_existe(self):
        self.cambiar("epidemias", 0, "paises", "ZZ")
        self.assertTrue(any("«ZZ» no está en paises.csv" in e for e in self.errores()))

    def test_epidemia_con_pais_de_otra_region(self):
        self.cambiar("epidemias", 0, "paises", "CL")  # Chile es de América y la epidemia, de Europa y Asia
        self.assertTrue(any("que no está entre las regiones" in e for e in self.errores()))

    def test_hito_con_pais_que_no_existe(self):
        self.cambiar("hitos_registro", 0, "codigo", "ZZ")
        self.assertTrue(any("no está en paises.csv" in e for e in self.errores()))

    def test_esperanza_con_pais_que_no_existe(self):
        escribir(self.datos, "esperanza_vida", [["ZZ", "1900", "1909", "T", "0", "30.0", "", "", "", "C", "borrador", ""]])
        self.assertTrue(any("«ZZ»" in e for e in self.errores()))

    def test_londres_viruela_no_supera_el_total(self):
        self.cambiar("londres_muertes", 0, "muertes_viruela", "99999")
        self.assertTrue(any("superan" in e for e in self.errores()))

    def test_londres_anio_repetido(self):
        escribir(self.datos, "londres_muertes", [("1665", "10", "1", "verificado", "x"), ("1665", "11", "1", "verificado", "x")])
        self.assertTrue(any("repetido" in e for e in self.errores()))

    def test_londres_verificado_exige_fuente(self):
        self.cambiar("londres_muertes", 0, "fuente", "")
        self.assertTrue(any("necesita su fuente" in e for e in self.errores()))


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

    def test_epidemia_de_larga_duracion(self):
        self.assertIn("peste_tercera_pandemia", [e["id"] for e in consultas.epidemias_en(1950, "Asia")])

    def test_region_invalida(self):
        with self.assertRaises(ValueError):
            consultas.epidemias_en(1900, "Atlantida")

    def test_hitos(self):
        self.assertEqual(consultas.hitos_de("CL")[0]["desde"], "1885")
        self.assertEqual(consultas.hitos_de("ZZ"), [])


class EsperanzaA5Test(unittest.TestCase):
    def test_identidad_con_numeros_conocidos(self):
        # e0 = q5*a + (1-q5)*(5+e5). Con q5 = 0,4, a = 1 y e5 = 40: e0 = 0,4 + 0,6*45 = 27,4.
        e5, l5 = pd.esperanza_a_los_5(27.4, 400)
        self.assertAlmostEqual(e5, 40.0, places=9)
        self.assertAlmostEqual(l5, 0.6, places=9)

    def test_sin_mortalidad_infantil_se_reduce_a_e0_menos_5(self):
        e5, l5 = pd.esperanza_a_los_5(80.0, 0)
        self.assertEqual((e5, l5), (75.0, 1.0))

    def test_sobrevivir_a_la_infancia_nunca_acorta_la_vida_esperada(self):
        for e0 in (20, 30, 45, 60, 80):
            for q5 in (0, 5, 50, 200, 400, 600):
                e5, _ = pd.esperanza_a_los_5(e0, q5)
                self.assertGreaterEqual(5 + e5, e0, (e0, q5))

    def test_el_supuesto_de_la_edad_media_pesa_poco(self):
        # Entre 0,5 y 1,5 años, con una mortalidad de 400 por mil, la esperanza a los 5 cambia menos de 0,35.
        bajo = pd.esperanza_a_los_5(30, 400, edad_media=0.5)[0]
        alto = pd.esperanza_a_los_5(30, 400, edad_media=1.5)[0]
        self.assertLess(abs(bajo - alto), 0.7)
        self.assertLess(abs(pd.esperanza_a_los_5(30, 400)[0] - bajo), 0.35)

    def test_mortalidad_fuera_de_rango(self):
        for q5 in (-1, 1000, 1500):
            with self.assertRaises(ValueError):
                pd.esperanza_a_los_5(30, q5)


class PrepararDatosTest(unittest.TestCase):
    def test_decada_completa_se_promedia(self):
        serie = {a: float(a - 1800) for a in range(1800, 1810)}
        self.assertEqual(pd.promedios_por_decada(serie), {1800: 4.5})

    def test_decada_con_un_anio_faltante_se_omite(self):
        serie = {a: 30.0 for a in range(1800, 1820) if a != 1805}
        self.assertEqual(pd.promedios_por_decada(serie), {1810: 30.0})

    def test_fuera_del_rango_no_entra(self):
        serie = {a: 30.0 for a in range(1790, 1800)} | {a: 50.0 for a in range(2010, 2020)}
        self.assertEqual(pd.promedios_por_decada(serie), {})

    def test_construir(self):
        e0 = {"XX": {a: 30.0 for a in range(1900, 1910)} | {a: 60.0 for a in range(1950, 1960)}}
        q5 = {"XX": {a: 300.0 for a in range(1900, 1910)}}
        mujeres = {"XX": {a: 64.0 for a in range(1950, 1960)}, "YY": {a: 70.0 for a in range(1950, 1960)}}
        filas = pd.construir(e0, q5, mujeres, {})
        resumen = [(f[0], f[1], f[2], f[3], f[4], f[5], f[6], f[9]) for f in filas]
        self.assertEqual(resumen, [
            ("XX", 1900, 1909, "T", 0, "30.0", "", "C"),
            ("XX", 1950, 1959, "T", 0, "60.0", "", "B"),
            ("XX", 1900, 1909, "T", 5, "37.4", "0.700", "C"),   # (30 - 0,3) / 0,7 - 5; en 1950 no hay mortalidad 0-5
            ("XX", 1950, 1959, "F", 0, "64.0", "", "B"),
            ("YY", 1950, 1959, "F", 0, "70.0", "", "B"),         # país solo con datos por sexo
        ])

    def test_toda_fila_construida_es_valida(self):
        e0 = {"XX": {a: 30.0 for a in range(1900, 1910)}}
        q5 = {"XX": {a: 300.0 for a in range(1900, 1910)}}
        with tempfile.TemporaryDirectory() as tmp, mock.patch.object(g, "DATOS", Path(tmp)):
            escribir(Path(tmp), "esperanza_vida", pd.construir(e0, q5, {}, {}))
            errores = g.Errores()
            g.validar_esperanza_vida(errores, {"XX": "América"})
            self.assertEqual(errores.lista, [])

    CODIGOS = [{"codigo": "RU", "codigo_owid": "RUS", "nombre_iso": "Russian Federation"},
               {"codigo": "CL", "codigo_owid": "CHL", "nombre_iso": "Chile"}]

    def escribir_owid(self, carpeta, entidades):
        ruta = Path(carpeta) / "owid.csv"
        with ruta.open("w", encoding="utf-8", newline="") as f:
            e = csv.writer(f)
            e.writerow(["Entity", "Year", "Life expectancy (Gapminder, UN)"])
            for entidad in entidades:
                e.writerow([entidad, 1900, 30])
        return ruta

    def test_owid_resuelve_alias_y_omite_regiones(self):
        with tempfile.TemporaryDirectory() as tmp:
            ruta = self.escribir_owid(tmp, ["Russia", "Chile", "World", "Europe"])
            self.assertEqual(sorted(pd.leer_owid(ruta, self.CODIGOS)), ["CL", "RU"])

    def test_owid_con_un_nombre_nuevo_falla_en_vez_de_perder_un_pais(self):
        with tempfile.TemporaryDirectory() as tmp:
            ruta = self.escribir_owid(tmp, ["Chile", "Atlantis"])
            with self.assertRaises(SystemExit) as error:
                pd.leer_owid(ruta, self.CODIGOS)
            self.assertIn("Atlantis", str(error.exception))

    def test_los_alias_apuntan_a_codigos_distintos_y_no_se_pisan_con_los_omitidos(self):
        self.assertEqual(len(set(pd.ALIAS_OWID.values())), len(pd.ALIAS_OWID))
        self.assertEqual(set(pd.ALIAS_OWID) & pd.OMITIDOS_OWID, set())
        self.assertEqual(set(pd.ALIAS_GAPMINDER) & pd.OMITIDOS_GAPMINDER, set())


class LondresYColeraTest(unittest.TestCase):
    def escribir(self, carpeta, nombre, cabecera, filas):
        ruta = Path(carpeta) / nombre
        with ruta.open("w", encoding="utf-8", newline="") as f:
            e = csv.writer(f)
            e.writerow(cabecera)
            e.writerows(filas)
        return ruta

    def test_construir_londres_salta_los_vacios_y_ordena(self):
        cab = ["Entity", "Year", "Deaths from Smallpox in London (X)", "Deaths from all causes in London (X)", "Tasa"]
        with tempfile.TemporaryDirectory() as tmp:
            ruta = self.escribir(tmp, "l.csv", cab, [
                ["UK", 1666, 38.0, 12738.0, 0.3], ["UK", 1665, 655, 97306, 0.7], ["UK", 1650, "", 5000, ""]])
            self.assertEqual(pd.construir_londres(ruta), [
                [1665, 97306, 655, "verificado", pd.FUENTE_LONDRES],
                [1666, 12738, 38, "verificado", pd.FUENTE_LONDRES]])

    def test_construir_paises(self):
        with tempfile.TemporaryDirectory() as tmp:
            ruta = self.escribir(tmp, "p.csv", ["codigo", "pais", "tipo", "region"],
                                 [["GB", "Reino Unido", "estado", "Europa"], ["CL", "Chile", "estado", "América"]])
            self.assertEqual(pd.construir_paises(ruta), [["CL", "Chile", "América"], ["GB", "Reino Unido", "Europa"]])

    def fila_colera(self, **cambios):
        fila = {"id": "c", "fuente": pd.FUENTE_COLERA, "hasta": "1832", "muertes_min": "31000", "muertes_max": "31000"}
        return fila | cambios

    def test_la_cifra_de_colera_debe_ser_la_de_la_fuente(self):
        with tempfile.TemporaryDirectory() as tmp:
            ruta = self.escribir(tmp, "c.csv", ["Entity", "Year", "Cholera deaths"], [["GB", 1832, 31000], ["GB", 1849, 62000]])
            pd.comprobar_colera(ruta, [self.fila_colera(), self.fila_colera(fuente="otra", muertes_min="1", muertes_max="2")])
            for malo in (self.fila_colera(muertes_min="30000"), self.fila_colera(muertes_max="32000"),
                         self.fila_colera(hasta="1900")):
                with self.assertRaises(SystemExit):
                    pd.comprobar_colera(ruta, [malo])


class FuentesTest(unittest.TestCase):
    def test_el_manifiesto_cubre_todas_las_fuentes(self):
        self.assertEqual(set(descargar_fuentes.leer_manifiesto()), set(descargar_fuentes.FUENTES))
        for huella in descargar_fuentes.leer_manifiesto().values():
            self.assertRegex(huella, r"^[0-9a-f]{64}$")

    def test_las_url_estan_fijadas_a_un_commit(self):
        for url in descargar_fuentes.FUENTES.values():
            self.assertRegex(url, r"/[0-9a-f]{40}/", url)


class EsperanzaDeVidaRealTest(unittest.TestCase):
    """Sobre los datos reales de esperanza_vida.csv."""

    @classmethod
    def setUpClass(cls):
        cls.filas = comun.filas_csv("esperanza_vida")
        cls.indice = {(f["codigo"], f["desde"], f["sexo"], f["edad"]): f for f in cls.filas}

    def test_hay_datos(self):
        self.assertGreater(len(self.filas), 5000)
        self.assertGreater(len({f["codigo"] for f in self.filas}), 150)

    def test_solo_las_edades_que_hay(self):
        self.assertEqual({f["edad"] for f in self.filas}, {"0", "5"})
        self.assertEqual({f["edad"] for f in self.filas if f["sexo"] != "T"}, {"0"})

    def test_todo_antes_de_1950_es_calidad_c(self):
        for f in self.filas:
            self.assertEqual(f["calidad"], "C" if int(f["desde"]) < 1950 else "B", f)

    def test_toda_fila_de_edad_5_tiene_su_fila_de_edad_0_y_cuadra(self):
        for (codigo, desde, sexo, edad), f in self.indice.items():
            if edad == "5":
                e0 = float(self.indice[(codigo, desde, "T", "0")]["esperanza_restante"])
                self.assertGreaterEqual(float(f["esperanza_restante"]) + 5, e0 - 0.06, f)
                self.assertTrue(0 < float(f["supervivencia_hasta_edad"]) < 1, f)

    def test_los_valores_son_plausibles(self):
        for f in self.filas:
            if f["edad"] == "0":
                self.assertTrue(15 <= float(f["esperanza_restante"]) <= 90, f)

    def test_los_periodos_son_decadas_completas(self):
        for f in self.filas:
            self.assertEqual(int(f["hasta"]) - int(f["desde"]), 9, f)
            self.assertEqual(int(f["desde"]) % 10, 0, f)

    def test_cada_fila_dice_su_fuente_con_un_identificador_conocido(self):
        self.assertEqual({f["fuente"] for f in self.filas},
                         {pd.FUENTE_E0, pd.FUENTE_E5, pd.FUENTE_SEXO})

    def test_esta_al_dia_con_construir_para_un_pais_conocido(self):
        # Cualquier cambio a mano de una fila de edad 5 la desalinearía de su fila de edad 0.
        f0, f5 = self.indice[("SE", "1900", "T", "0")], self.indice[("SE", "1900", "T", "5")]
        self.assertEqual(f5["fuente"], pd.FUENTE_E5)
        self.assertEqual(f0["fuente"], pd.FUENTE_E0)


class EpidemiasYLondresRealesTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.epidemias = {f["id"]: f for f in comun.filas_csv("epidemias")}
        cls.londres = {int(f["anio"]): f for f in comun.filas_csv("londres_muertes")}

    def test_los_numeros_citados_en_el_texto_de_la_peste_de_londres_son_los_de_la_serie(self):
        texto = self.epidemias["peste_de_londres_1665"]["mortalidad"]
        self.assertIn("97 306", texto)
        self.assertIn("12 738", texto)
        self.assertEqual(self.londres[1665]["muertes_totales"], "97306")
        self.assertEqual(self.londres[1666]["muertes_totales"], "12738")

    def test_las_filas_verificadas_traen_una_fuente_conocida(self):
        verificadas = {i: e for i, e in self.epidemias.items() if e["estado"] == "verificado"}
        self.assertEqual(set(verificadas), {"colera_gran_bretana_1831_1832", "colera_gran_bretana_1849", "colera_gran_bretana_1866"})
        for e in verificadas.values():
            self.assertEqual(e["fuente"], pd.FUENTE_COLERA)
            self.assertEqual(e["muertes_min"], e["muertes_max"])

    def test_toda_epidemia_con_causa_la_tiene_en_el_glosario(self):
        causas = {f["causa_id"] for f in comun.filas_csv("causas_historicas")}
        for e in self.epidemias.values():
            if e["causa_id"]:
                self.assertIn(e["causa_id"], causas, e["id"])

    def test_la_serie_de_londres_es_la_de_1629_a_1902(self):
        self.assertEqual((min(self.londres), max(self.londres)), (1629, 1902))
        self.assertEqual(len(self.londres), 241)

    def test_epidemias_md_esta_al_dia_y_lista_todas(self):
        _, datos = g.validar()
        texto = (comun.RAIZ / "epidemias.md").read_text(encoding="utf-8")
        self.assertEqual(texto, g.tabla_epidemias(datos))
        for e in self.epidemias.values():
            self.assertIn(e["nombre"], texto)


class ConsultasEpidemiasYLondresTest(unittest.TestCase):
    def ids(self, *args, **kwargs):
        return [e["id"] for e in consultas.epidemias_en(*args, **kwargs)]

    def test_region_de(self):
        self.assertEqual(consultas.region_de("CL"), "América")
        self.assertEqual(consultas.region_de("IT"), "Europa")
        self.assertIsNone(consultas.region_de("ZZ"))

    def test_por_pais_una_epidemia_regional_alcanza_a_los_de_su_region(self):
        self.assertIn("peste_negra", self.ids(1348, pais="IT"))
        self.assertNotIn("peste_negra", self.ids(1348, pais="CL"))

    def test_por_pais_una_epidemia_con_paises_solo_alcanza_a_esos(self):
        self.assertIn("colera_chile_1886", self.ids(1887, pais="CL"))
        self.assertNotIn("colera_chile_1886", self.ids(1887, pais="AR"))
        self.assertIn("sudor_ingles", self.ids(1528, pais="GB"))
        self.assertNotIn("sudor_ingles", self.ids(1528, pais="FR"))

    def test_region_y_pais_a_la_vez(self):
        # Los dos filtros se cumplen a la vez: la peste negra es de Europa, pero Chile no.
        self.assertEqual(self.ids(1348, region="Europa", pais="CL"), [])
        self.assertIn("peste_negra", self.ids(1348, region="Europa", pais="IT"))
        # La quinta pandemia de cólera cubre Europa y América, así que sale con Chile y con Europa.
        self.assertIn("colera_quinta_pandemia", self.ids(1887, region="Europa", pais="CL"))

    def test_pais_invalido(self):
        with self.assertRaises(ValueError):
            consultas.epidemias_en(1900, pais="ZZ")

    def test_los_resultados_traen_los_paises_como_lista(self):
        e = next(e for e in consultas.epidemias_en(1847, pais="IE") if e["id"] == "tifus_irlanda_1846")
        self.assertEqual(e["paises"], ["IE", "GB"])

    def test_muertes_londres(self):
        self.assertEqual(consultas.muertes_londres(1665), {"totales": 97306, "viruela": 655})
        self.assertIsNone(consultas.muertes_londres(1640))  # hueco de la serie
        self.assertIsNone(consultas.muertes_londres(1900 + 3))

    def test_exceso_de_muertes_en_la_peste(self):
        self.assertGreater(consultas.exceso_de_muertes_londres(1665), 4)
        self.assertLess(consultas.exceso_de_muertes_londres(1666), 1)
        self.assertAlmostEqual(consultas.exceso_de_muertes_londres(1800), 1.0, delta=0.4)

    def test_exceso_con_una_serie_conocida(self):
        # Vecinos 100, 100, 100, 100 y el año a medir con 300: la mediana es 100 y el exceso, 3.
        serie = {a: (100, 0) for a in range(1, 6)} | {6: (300, 0)}
        with mock.patch.object(consultas, "_muertes_de_londres", lambda: serie):
            self.assertEqual(consultas.exceso_de_muertes_londres(6), 3.0)

    def test_exceso_con_menos_de_cuatro_vecinos_o_sin_el_anio_es_none(self):
        serie = {1: (100, 0), 2: (100, 0), 3: (300, 0), 4: (100, 0)}
        with mock.patch.object(consultas, "_muertes_de_londres", lambda: serie):
            self.assertIsNone(consultas.exceso_de_muertes_londres(3))   # solo 3 vecinos
            self.assertIsNone(consultas.exceso_de_muertes_londres(99))  # el año no está


class VistaTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.errores, cls.datos = g.validar()
        cls.html = g.vista(cls.datos)

    def test_vista_html_esta_al_dia(self):
        self.assertEqual((comun.RAIZ / "vista.html").read_text(encoding="utf-8"), self.html)

    def test_no_quedan_marcas_sin_rellenar(self):
        for marca in ("/*MODULO*/", "/*TABLAS*/", "/*IDIOMAS*/"):
            self.assertNotIn(marca, self.html)

    def test_incrusta_el_modulo_js_tal_cual(self):
        self.assertIn((comun.RAIZ / "js" / "mortalidad.js").read_text(encoding="utf-8").rstrip("\n"), self.html)

    def test_los_datos_incrustados_son_los_de_los_csv(self):
        tablas = g.tablas_para_la_vista(self.datos)
        self.assertEqual(len(tablas["esperanza_vida"]["filas"]), len(self.datos["esperanza_vida"]))
        self.assertEqual(len(tablas["epidemias"]["filas"]), len(self.datos["epidemias"]))
        self.assertEqual(tablas["epidemias"]["columnas"], g.COLUMNAS["epidemias"])
        for fila in tablas["esperanza_vida"]["filas"]:
            self.assertEqual(len(fila), len(tablas["esperanza_vida"]["columnas"]))

    def test_un_cierre_de_script_en_los_datos_no_rompe_la_pagina(self):
        datos = {**self.datos, "causas_historicas": self.datos["causas_historicas"] + [
            {"termino": "</script><b>", "idioma": "spa", "causa_id": "vejez", "confianza": "alta", "desde": "",
             "hasta": "", "nota": "", "estado": "borrador", "fuente": ""}]}
        html = g.vista(datos)
        self.assertEqual(html.lower().count("</script>"), 2)  # solo los dos de la plantilla

    def test_la_vista_no_carga_nada_de_afuera(self):
        for prohibido in ("http://", "https://", "src=\"", "<link", "@import"):
            self.assertNotIn(prohibido, self.html.replace("http://www.w3.org/2000/svg", ""), prohibido)


class ConsultasEsperanzaTest(unittest.TestCase):
    def test_cualquier_anio_de_la_decada_da_lo_mismo(self):
        self.assertEqual(consultas.esperanza_restante("SE", 1900), consultas.esperanza_restante("SE", 1909))
        self.assertNotEqual(consultas.esperanza_restante("SE", 1899), consultas.esperanza_restante("SE", 1900))

    def test_a_los_5_queda_mas_que_al_nacer_menos_5(self):
        e0 = consultas.esperanza_restante("CL", 1895, 0)
        e5 = consultas.esperanza_restante("CL", 1895, 5)
        self.assertGreater(e5 + 5, e0)

    def test_supervivencia(self):
        s = consultas.supervivencia("CL", 1895)
        self.assertTrue(0 < s < 1)
        self.assertEqual(consultas.supervivencia("CL", 1895, 0), None)  # al nacer no se guarda

    def test_por_sexo(self):
        mujeres = consultas.esperanza_restante("ES", 1985, 0, "F")
        hombres = consultas.esperanza_restante("ES", 1985, 0, "M")
        self.assertGreater(mujeres, hombres)

    def test_sin_dato_devuelve_none_no_inventa(self):
        self.assertIsNone(consultas.esperanza_restante("XX", 1900))       # país sin serie
        self.assertIsNone(consultas.esperanza_restante("CL", 1799))       # antes de 1800
        self.assertIsNone(consultas.esperanza_restante("CL", 2010))       # después de 2009
        self.assertIsNone(consultas.esperanza_restante("CL", 1900, 15))   # edad que no está
        self.assertIsNone(consultas.esperanza_restante("CL", 1900, 5, "F"))  # 5 años solo para ambos sexos
        self.assertIsNone(consultas.esperanza_restante("CL", 1900, 0, "F"))  # por sexo desde 1950

    def test_sexo_invalido(self):
        with self.assertRaises(ValueError):
            consultas.esperanza_restante("CL", 1900, 0, "X")


class NormalizarTest(unittest.TestCase):
    def test_normalizar(self):
        self.assertEqual(comun.normalizar("  Schlagfluß "), "schlagfluss")
        self.assertEqual(comun.normalizar("CÓLERA   morbo"), "colera morbo")
        self.assertEqual(comun.normalizar("اَلسُّلّ"), comun.normalizar("السل"))


if __name__ == "__main__":
    unittest.main()
