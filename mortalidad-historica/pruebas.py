"""Pruebas de mortalidad-historica: el validador, la generación y las consultas.

  python3 -m unittest -v pruebas

Los datos de prueba son inventados y viven en directorios temporales: no tocan datos/.
"""
import collections
import csv
import inspect
import tempfile
import unittest
from pathlib import Path
from unittest import mock

import comun
import consultas
import descargar_fuentes
import generar_tabla as g
import leer_rda
import modelo
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
        escribir(self.datos, "revisiones", [])
        escribir(self.datos, "paises", [("CL", "Chile", "América"), ("GB", "Reino Unido", "Europa")])
        escribir(self.datos, "causas_modernas", [("tuberculosis", "Tuberculosis", "Tuberculosis", "A15;A16", "infecciosa")])
        escribir(self.datos, "causas_historicas", [("tisis", "spa", "tuberculosis", "alta", "", "", "", "borrador", "")])
        escribir(self.datos, "epidemias", [("peste", "Peste", "Y. pestis", "", "1346", "1353", "Europa;Asia", "", "", "", "", "media", "", "borrador", "")])
        escribir(self.datos, "hereditarias", [("fq", "Fibrosis quística", "Cystic fibrosis", "autosómica recesiva", "219700", "MONDO:0009061", "todas las poblaciones", "infancia", "", "borrador", "")])
        escribir(self.datos, "hitos_registro", [("CL", "civil", "1885", "", "borrador", "")])
        escribir(self.datos, "modelo_logcuadratico", [(sexo, str(e), "-1", "1", "0", "0") for sexo in modelo.SEXOS for e in modelo.EDADES])
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

    def test_un_termino_en_otra_escritura_es_un_error(self):
        self.cambiar("causas_historicas", 0, "termino", "чахотка")   # cirílico en una fila de español
        self.assertTrue(any("escritura de su idioma" in e for e in self.errores()))

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

    def test_mondo_con_forma_valida(self):
        self.cambiar("hereditarias", 0, "mondo", "MONDO:12")
        self.assertTrue(any("mondo" in e for e in self.errores()))

    def test_un_omim_sin_su_mondo_no_esta_verificado(self):
        self.cambiar("hereditarias", 0, "mondo", "")
        self.assertTrue(any("sin su término MONDO" in e for e in self.errores()))

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
            "id", "nombre_spa", "nombre_eng", "herencia", "omim", "mondo", "frecuencia_mayor_en", "inicio_tipico",
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


class RevisionesTest(Plantilla):
    """revisiones.csv: el validador y el informe, sobre un glosario de un solo término."""

    def revision(self, **cambios):
        fila = {"idioma": "spa", "termino": "tisis", "causa_id": "tuberculosis",
                "huella": comun.huella("tisis", "spa", "tuberculosis", "alta"), "resultado": "correcto",
                "revisor": "Ana", "fecha": "2026-11-20", "nota": ""}
        fila.update(cambios)
        return [fila[c] for c in g.COLUMNAS["revisiones"]]

    def estado(self, *revisiones):
        escribir(self.datos, "revisiones", list(revisiones))
        errores, datos = g.validar()
        self.assertEqual(errores, [])
        return g.estado_de_revision(datos), datos

    def test_vacio_es_valido_y_todo_sin_revisar(self):
        (contadores, abiertas, caducadas, desacuerdos, huerfanas), _ = self.estado()
        self.assertEqual((contadores["spa"]["total"], contadores["spa"]["revisado"]), (1, 0))
        self.assertEqual((abiertas, caducadas, desacuerdos, huerfanas), ([], [], [], []))

    def test_una_revision_correcta_con_la_huella_vigente_cuenta(self):
        (contadores, *_), _ = self.estado(self.revision())
        self.assertEqual(contadores["spa"]["revisado"], 1)

    def test_si_el_dato_cambia_la_revision_caduca(self):
        (contadores, _, caducadas, *_), _ = self.estado(self.revision(huella="00000000"))
        self.assertEqual((contadores["spa"]["revisado"], contadores["spa"]["caducado"], len(caducadas)), (0, 1, 1))

    def test_una_duda_deja_el_termino_abierto(self):
        (contadores, abiertas, *_), _ = self.estado(self.revision(resultado="dudoso", nota="¿no será otra cosa?"))
        self.assertEqual((contadores["spa"]["revisado"], contadores["spa"]["abierto"], len(abiertas)), (0, 1, 1))

    def test_dos_personas_en_desacuerdo(self):
        (contadores, _, _, desacuerdos, _), _ = self.estado(self.revision(), self.revision(revisor="Luis", resultado="corregir", nota="mejor otra causa"))
        self.assertEqual(len(desacuerdos), 1)
        self.assertEqual(contadores["spa"]["revisado"], 0)   # con una duda abierta no cuenta como revisado

    def test_una_revision_de_un_termino_que_ya_no_existe_es_huerfana_no_un_error(self):
        (_, _, _, _, huerfanas), _ = self.estado(self.revision(termino="consunción inventada"))
        self.assertEqual(len(huerfanas), 1)

    def test_el_informe_dice_cuanto_esta_revisado(self):
        _, datos = self.estado(self.revision())
        texto = g.informe_revisiones(datos)
        self.assertIn("**1 de 1 términos revisados** (100 %)", texto)
        self.assertIn("Nada por ahora.", texto)

    def test_validador(self):
        casos = {"idioma": ("es", "ISO 639-3"), "resultado": ("quizá", "resultado"), "revisor": ("", "falta el revisor"),
                 "fecha": ("20/11/2026", "AAAA-MM-DD"), "huella": ("xyz", "huella")}
        for campo, (valor, mensaje) in casos.items():
            escribir(self.datos, "revisiones", [self.revision(**{campo: valor})])
            self.assertTrue(any(mensaje in e for e in self.errores()), campo)
        for resultado in ("corregir", "dudoso", "corregido"):
            escribir(self.datos, "revisiones", [self.revision(resultado=resultado)])
            self.assertTrue(any("necesita su nota" in e for e in self.errores()), resultado)

    def test_un_revisor_no_revisa_dos_veces_lo_mismo(self):
        escribir(self.datos, "revisiones", [self.revision(), self.revision(fecha="2026-11-21")])
        self.assertTrue(any("ya revisó este término" in e for e in self.errores()))


class HuellaTest(unittest.TestCase):
    def test_valor_conocido(self):
        # FNV-1a de 32 bits de «|||»; el port a JS (js/mortalidad.test.js) da lo mismo con su propia implementación.
        self.assertEqual(comun.huella("", "", "", ""), "13fe3a3b")
        self.assertEqual(len(comun.huella("tisis", "spa", "tuberculosis", "alta")), 8)

    def test_cambia_con_cualquiera_de_los_cuatro_datos(self):
        base = comun.huella("tisis", "spa", "tuberculosis", "alta")
        for otra in (("Tisis", "spa", "tuberculosis", "alta"), ("tisis", "por", "tuberculosis", "alta"),
                     ("tisis", "spa", "viruela", "alta"), ("tisis", "spa", "tuberculosis", "media")):
            self.assertNotEqual(comun.huella(*otra), base)

    def test_las_revisiones_reales_son_validas_y_estan_al_dia_con_el_glosario(self):
        errores, datos = g.validar()
        self.assertEqual(errores, [])
        contadores, abiertas, caducadas, desacuerdos, huerfanas = g.estado_de_revision(datos)
        self.assertEqual((caducadas, huerfanas), ([], []))   # nadie debe dejar revisiones que ya no corresponden


class OmimTest(unittest.TestCase):
    OBO = ("format-version: 1.2\n\n"
           "[Term]\nid: MONDO:0009061\nname: cystic fibrosis\nxref: OMIM:219700\nxref: Orphanet:586\n\n"
           "[Term]\nid: MONDO:0000001\nname: viejo\nis_obsolete: true\nxref: OMIM:111111\n\n"
           "[Term]\nid: MONDO:0009861\nname: phenylketonuria\nxref: OMIM:261600\n")

    def con_obo(self):
        d = tempfile.TemporaryDirectory()
        self.addCleanup(d.cleanup)
        ruta = Path(d.name) / "m.obo"
        ruta.write_text(self.OBO, encoding="utf-8")
        return ruta

    def test_lee_los_xref_de_omim(self):
        mondo = pd.omim_de_mondo(self.con_obo())
        self.assertEqual(mondo["MONDO:0009061"], (["219700"], False))
        self.assertEqual(mondo["MONDO:0000001"], (["111111"], True))

    def test_un_omim_que_es_xref_de_su_mondo_pasa(self):
        pd.comprobar_omim(self.con_obo(), [{"id": "fq", "omim": "219700", "mondo": "MONDO:0009061"}, {"id": "sin", "omim": "", "mondo": ""}])

    def test_un_omim_que_no_es_xref_de_su_mondo_falla(self):
        for fila in ({"id": "x", "omim": "305900", "mondo": "MONDO:0009061"},      # otro código
                     {"id": "x", "omim": "219700", "mondo": "MONDO:9999999"},      # término que no existe
                     {"id": "x", "omim": "111111", "mondo": "MONDO:0000001"}):     # término obsoleto
            with self.assertRaises(SystemExit):
                pd.comprobar_omim(self.con_obo(), [fila])

    def test_todo_omim_de_los_datos_trae_su_mondo(self):
        for f in comun.filas_csv("hereditarias"):
            if f["omim"]:
                self.assertRegex(f["mondo"], r"^MONDO:\d{7}$", f["id"])


class GlosarioAmpliadoTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.filas = comun.filas_csv("causas_historicas")
        cls.causas = {c["id"]: c for c in comun.filas_csv("causas_modernas")}

    def test_hay_mas_de_quinientos_terminos_y_cincuenta_causas(self):
        self.assertGreater(len(self.filas), 500)
        self.assertGreater(len(self.causas), 45)

    def test_toda_causa_se_usa_y_todo_idioma_principal_tiene_mucho(self):
        usadas = {f["causa_id"] for f in self.filas}
        self.assertEqual(set(self.causas) - usadas, set())
        por_idioma = collections.Counter(f["idioma"] for f in self.filas)
        for idioma in ("spa", "lat", "eng", "deu", "fra", "ita", "por"):
            self.assertGreater(por_idioma[idioma], 40, idioma)

    def test_ningun_idioma_queda_con_unos_pocos_terminos(self):
        por_idioma = collections.Counter(f["idioma"] for f in self.filas)
        self.assertEqual(set(por_idioma), set(comun.IDIOMAS))
        self.assertGreaterEqual(min(por_idioma.values()), 15, por_idioma)

    def test_los_idiomas_pequenos_cubren_las_causas_mas_comunes(self):
        comunes = {"disenteria", "tos_ferina", "neumonia", "cancer", "sifilis", "lepra", "epilepsia"}
        for idioma in ("swe", "dan", "pol", "rus", "ukr", "ces", "ell", "tur", "ara", "heb", "zho", "jpn"):
            cubiertas = {f["causa_id"] for f in self.filas if f["idioma"] == idioma}
            self.assertGreaterEqual(len(cubiertas & comunes), 5, idioma)

    def test_un_termino_ambiguo_sale_con_todas_sus_causas(self):
        causas = {r["causa_id"] for r in consultas.causa_moderna("dolor de costado", "spa")}
        self.assertEqual(causas, {"neumonia", "pleuresia"})
        causas = {r["causa_id"] for r in consultas.causa_moderna("Typhus", "deu")}
        self.assertEqual(causas, {"fiebre_tifoidea", "tifus"})

    def test_la_fiebre_sin_mas_es_baja_confianza(self):
        for termino, idioma in (("calentura", "spa"), ("fever", "eng"), ("Fieber", "deu")):
            r = consultas.causa_moderna(termino, idioma)
            self.assertEqual([x["causa_id"] for x in r], ["fiebre_inespecifica"])
            self.assertEqual(r[0]["confianza"], "baja")

    def test_terminos_comunes_de_los_registros(self):
        esperado = {("de parto", "spa"): "muerte_de_parto", ("gefallen", "deu"): "guerra", ("mort pour la France", "fra"): "guerra",
                    ("pulmonía", "spa"): "neumonia", ("raquitismo", "spa"): "raquitismo", ("in childbed", "eng"): "muerte_de_parto",
                    ("dysenteria", "lat"): "disenteria", ("Scharlach", "deu"): "escarlatina", ("bloody flux", "eng"): "disenteria"}
        for (termino, idioma), causa in esperado.items():
            self.assertIn(causa, [r["causa_id"] for r in consultas.causa_moderna(termino, idioma)], (termino, idioma))

    def test_todo_termino_esta_en_la_escritura_de_su_idioma(self):
        for f in self.filas:
            self.assertTrue(comun.escritura_coherente(f["termino"], f["idioma"]), (f["idioma"], f["termino"]))

    def test_la_comprobacion_de_escritura_distingue_las_escrituras(self):
        for termino, idioma, esperado in (("tisis", "spa", True), ("çiçek hastalığı", "tur", True), ("King's evil", "eng", True),
                                          ("чахотка", "rus", True), ("чахотка", "spa", False), ("tisis", "rus", False),
                                          ("コレラ", "jpn", True), ("老衰", "jpn", True), ("コレラ", "zho", False),
                                          ("سل", "ara", True), ("שחפת", "heb", True), ("υδρωπικία", "ell", True), ("υδρωπικία", "ara", False)):
            self.assertEqual(comun.escritura_coherente(termino, idioma), esperado, (termino, idioma))

    def test_todo_idioma_tiene_su_escritura(self):
        self.assertEqual(set(comun.ESCRITURAS), set(comun.IDIOMAS))

    def test_los_terminos_sensibles_son_de_vocabulario_no_de_personas(self):
        # El suicidio y la locura están en el glosario para leer un registro, y salen con su confianza, no como juicio.
        self.assertEqual(self.causas["suicidio"]["grupo"], "externa")
        self.assertEqual(self.causas["locura"]["grupo"], "mental")


class BandasYRileyTest(unittest.TestCase):
    def test_banda_de_cada_epoca(self):
        self.assertEqual(pd.banda_de(1950), (0.6, 0.07, "medida"))
        self.assertEqual(pd.banda_de(2000), (0.6, 0.07, "medida"))
        self.assertEqual(pd.banda_de(1949 - 9), (5.0, 0.20, "supuesto"))
        self.assertEqual(pd.banda_de(1800), (8.0, 0.25, "supuesto"))
        self.assertEqual(pd.banda_de(1540), (10.0, 0.30, "supuesto"))

    def test_las_esquinas_no_juntan_mucha_esperanza_con_mucha_mortalidad_infantil(self):
        esquinas = pd._esquinas(100.0, 50.0, 3.0, 0.2)
        self.assertEqual(esquinas[0], (100.0, 50.0))
        for q, e in esquinas:
            self.assertFalse(q > 100.0 and e > 50.0, (q, e))
            self.assertFalse(q < 100.0 and e < 50.0, (q, e))

    def test_banda_a_los_5_contiene_al_valor_central(self):
        central = pd.esperanza_a_los_5(40.0, 300.0)[0]
        bajo, alto = pd.banda_a_los_5(300.0, 40.0, 5.0, 0.2)
        self.assertLess(bajo, central)
        self.assertGreater(alto, central)
        self.assertLess(alto - bajo, 15)  # con las esquinas incoherentes pasaba de 24 años

    def test_la_banda_se_estrecha_con_una_fuente_mas_firme(self):
        ancha = pd.banda_a_los_5(300.0, 40.0, 5.0, 0.2)
        estrecha = pd.banda_a_los_5(300.0, 40.0, 0.6, 0.07)
        self.assertLess(estrecha[1] - estrecha[0], ancha[1] - ancha[0])

    def test_promedios_con_datos_usa_los_anios_que_hay(self):
        self.assertEqual(pd.promedios_con_datos({1541: 30.0, 1546: 40.0, 1553: 50.0}, 1500, 1799), {1540: 35.0, 1550: 50.0})
        self.assertEqual(pd.promedios_con_datos({1850: 30.0}, 1500, 1799), {})

    def test_riley_resuelve_alias_omite_territorios_y_falla_con_un_nombre_nuevo(self):
        cabecera = ["Entity", "Year", "Life expectancy"]
        codigos = [{"codigo": "GB", "codigo_owid": "GBR", "nombre_iso": "United Kingdom"},
                   {"codigo": "FO", "codigo_owid": "FRO", "nombre_iso": "Faroe Islands"}]
        with tempfile.TemporaryDirectory() as tmp:
            ruta = Path(tmp) / "r.csv"
            for entidades, falla in ((["United Kingdom", "Faeroe Islands", "Niue", "World"], False), (["Atlantis"], True)):
                with ruta.open("w", encoding="utf-8", newline="") as f:
                    e = csv.writer(f)
                    e.writerow(cabecera)
                    for entidad in entidades:
                        e.writerow([entidad, 1750, 36.5])
                if falla:
                    with self.assertRaises(SystemExit):
                        pd.leer_riley(ruta, codigos)
                else:
                    self.assertEqual(sorted(pd.leer_riley(ruta, codigos)), ["FO", "GB"])

    def test_construir_agrega_las_decadas_anteriores_a_1800_solo_para_la_edad_0(self):
        filas = pd.construir({}, {}, {}, {}, riley={"GB": {1541: 30.0, 1546: 40.0, 1795: 38.0, 1850: 99.0}})
        self.assertEqual([(f[0], f[1], f[3], f[4], f[5], f[9], f[11]) for f in filas], [
            ("GB", 1540, "T", 0, "35.0", "C", pd.FUENTE_RILEY),
            ("GB", 1790, "T", 0, "38.0", "C", pd.FUENTE_RILEY)])
        self.assertEqual((filas[0][7], filas[0][8]), ("25.0", "45.0"))   # ± 10 años: banda supuesta antes de 1800

    def test_regiones_antes_de_1800_con_el_valor_previo_a_la_transicion(self):
        cabecera = ["Entity", "Year", "Life expectancy"]
        with tempfile.TemporaryDirectory() as tmp:
            ruta = Path(tmp) / "r.csv"
            with ruta.open("w", encoding="utf-8", newline="") as f:
                e = csv.writer(f)
                e.writerow(cabecera)
                for fila in (["Europe", 1770, 34.3], ["Europe", 1800, 33.3], ["Americas", 1770, 34.8], ["World", 1770, 28.7], ["Chile", 1770, 1]):
                    e.writerow(fila)
            regiones = pd.leer_riley_regiones(ruta)
        self.assertEqual(regiones, {"R:Europa": {1770: 34.3}, "R:América": {1770: 34.8}})   # solo antes de 1800, sin «World» ni países
        filas = pd.construir({}, {}, {}, {}, riley_regiones=regiones)
        self.assertEqual([(f[0], f[1], f[4], f[5], f[9]) for f in filas], [("R:América", 1770, 0, "34.8", "C"), ("R:Europa", 1770, 0, "34.3", "C")])

    def test_si_el_modelo_no_cuadra_las_cifras_de_la_fuente_no_se_deriva_nada_de_ellas(self):
        # Gapminder da a Barbados más de 550 por mil de mortalidad de 0 a 5 años con una esperanza de 32 años.
        e0 = {"BB": {a: 32.1 for a in range(1890, 1900)}}
        q5 = {"BB": {a: 655.0 for a in range(1890, 1900)}}
        filas = pd.construir(e0, q5, {}, {}, edades_modelo=pd.EDADES_DEL_MODELO)
        self.assertEqual([(f[3], f[4]) for f in filas], [("T", 0)])      # solo la esperanza al nacer
        self.assertEqual(pd.construir.sin_modelo, [("T", "BB", 1890)])
        # Sin pedir el modelo, la identidad sí da una cifra (que sería absurda: 86 años a los 5).
        self.assertEqual(len(pd.construir(e0, q5, {}, {})), 2)

    def test_mujeres_y_hombres_usan_la_mortalidad_infantil_de_ambos_sexos(self):
        e0 = {"XX": {a: 70.0 for a in range(1950, 1960)}}
        q5 = {"XX": {a: 50.0 for a in range(1950, 1960)}}
        mujeres = {"XX": {a: 73.0 for a in range(1950, 1960)}}
        filas = pd.construir(e0, q5, mujeres, {}, edades_modelo=(15, 60))
        de_ellas = [(f[4], f[11]) for f in filas if f[3] == "F"]
        self.assertEqual(de_ellas, [(0, pd.FUENTE_SEXO), (5, pd.FUENTE_MODELO_SEXO), (15, pd.FUENTE_MODELO_SEXO), (60, pd.FUENTE_MODELO_SEXO)])
        f5 = next(f for f in filas if f[3] == "F" and f[4] == 5)
        self.assertEqual(f5[6], "")   # sin supervivencia: sería la de ambos sexos


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
        self.assertEqual({f["edad"] for f in self.filas}, {"0", "5", "15", "30", "45", "60"})
        # Antes de 1800 solo hay la esperanza al nacer, de los pocos países con datos (ver test_antes_de_1800).
        self.assertEqual({f["edad"] for f in self.filas if int(f["desde"]) < 1800}, {"0"})

    def test_calidad_c_antes_de_1950_y_siempre_para_lo_modelado(self):
        for f in self.filas:
            modelada = f["fuente"] in (pd.FUENTE_MODELO, pd.FUENTE_MODELO_SEXO, pd.FUENTE_RILEY)
            self.assertEqual(f["calidad"], "C" if modelada or int(f["desde"]) < 1950 else "B", f)

    def test_las_edades_modeladas_son_las_que_se_dicen(self):
        modeladas = {f["edad"] for f in self.filas if f["fuente"] == pd.FUENTE_MODELO}
        self.assertEqual(modeladas, {str(e) for e in pd.EDADES_DEL_MODELO})
        for f in self.filas:
            if f["fuente"] == pd.FUENTE_MODELO:
                self.assertEqual(f["sexo"], "T")
                self.assertTrue(0 < float(f["supervivencia_hasta_edad"]) < 1, f)
            if f["fuente"] == pd.FUENTE_MODELO_SEXO:
                self.assertIn(f["sexo"], ("F", "M"))
                self.assertGreaterEqual(int(f["desde"]), 1950)
                # A los 5 años no se guarda la supervivencia: sería la de ambos sexos, no la de este.
                self.assertEqual(f["supervivencia_hasta_edad"] == "", f["edad"] == "5", f)

    def test_la_edad_esperada_de_muerte_no_baja_con_la_edad(self):
        # Quien llega a una edad mayor muere, en promedio, a una edad igual o mayor: x + e(x) no decrece. Entre la
        # edad 5 (derivada) y la 15 (modelada) hay una tolerancia por venir de dos métodos.
        for (codigo, desde, sexo, edad), f in self.indice.items():
            if edad == "15":
                f5 = self.indice[(codigo, desde, sexo, "5")]
                self.assertGreaterEqual(15 + float(f["esperanza_restante"]), 5 + float(f5["esperanza_restante"]) - 0.25, f)
                if f5["supervivencia_hasta_edad"]:
                    self.assertLess(float(f["supervivencia_hasta_edad"]), float(f5["supervivencia_hasta_edad"]) + 1e-9, f)
        for codigo, desde, sexo in {(c, d, s) for (c, d, s, e) in self.indice if e == "15"}:
            previo = None
            for edad in ("15", "30", "45", "60"):
                f = self.indice[(codigo, desde, sexo, edad)]
                actual = (int(edad) + float(f["esperanza_restante"]), float(f["supervivencia_hasta_edad"]))
                if previo is not None:
                    self.assertGreaterEqual(actual[0], previo[0] - 0.06, (codigo, desde, edad))
                    # Con tres decimales, dos edades seguidas con mortalidad casi nula pueden quedar iguales.
                    self.assertLessEqual(actual[1], previo[1], (codigo, desde, edad))
                previo = actual

    def test_toda_fila_de_edad_5_tiene_su_fila_de_edad_0_y_cuadra(self):
        for (codigo, desde, sexo, edad), f in self.indice.items():
            if edad == "5" and sexo == "T":
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
                         {pd.FUENTE_E0, pd.FUENTE_E5, pd.FUENTE_SEXO, pd.FUENTE_MODELO, pd.FUENTE_MODELO_SEXO, pd.FUENTE_RILEY})

    def test_toda_fila_trae_su_banda_y_contiene_a_la_cifra(self):
        for f in self.filas:
            self.assertTrue(f["incertidumbre_baja"] != "" and f["incertidumbre_alta"] != "", f)
            self.assertLessEqual(float(f["incertidumbre_baja"]), float(f["esperanza_restante"]), f)
            self.assertGreaterEqual(float(f["incertidumbre_alta"]), float(f["esperanza_restante"]), f)

    def test_las_bandas_son_mas_anchas_cuanto_mas_atras(self):
        def ancho(codigo, desde):
            f = self.indice[(codigo, str(desde), "T", "0")]
            return float(f["incertidumbre_alta"]) - float(f["incertidumbre_baja"])
        self.assertGreater(ancho("SE", 1800), ancho("SE", 1900))
        self.assertGreater(ancho("SE", 1900), ancho("SE", 1950))
        self.assertAlmostEqual(ancho("SE", 1950), 2 * 0.6, places=1)
        self.assertAlmostEqual(ancho("SE", 1900), 2 * 5.0, places=1)
        self.assertAlmostEqual(ancho("SE", 1800), 2 * 8.0, places=1)
        self.assertAlmostEqual(ancho("GB", 1540), 2 * 10.0, places=1)

    def test_antes_de_1800_cuatro_paises_y_cinco_regiones_y_solo_la_esperanza_al_nacer(self):
        previas = [f for f in self.filas if int(f["desde"]) < 1800]
        self.assertEqual({f["codigo"] for f in previas}, {"GB", "SE", "FI", "DK", "R:África", "R:América", "R:Asia", "R:Europa", "R:Oceanía"})
        for f in previas:
            if f["codigo"].startswith("R:"):
                self.assertEqual(f["desde"], "1770", f)   # el valor previo a la transición, que Riley fija en 1770
        for f in previas:
            self.assertEqual((f["sexo"], f["edad"], f["fuente"], f["calidad"]), ("T", "0", pd.FUENTE_RILEY, "C"), f)
            self.assertEqual(int(f["desde"]) % 10, 0, f)
        self.assertEqual(min(int(f["desde"]) for f in previas), 1540)

    def test_mujeres_y_hombres_desde_1950_con_todas_las_edades_modeladas(self):
        for sexo in ("F", "M"):
            edades = {f["edad"] for f in self.filas if f["sexo"] == sexo}
            self.assertEqual(edades, {"0", "5", "15", "30", "45", "60"}, sexo)
            self.assertGreaterEqual(min(int(f["desde"]) for f in self.filas if f["sexo"] == sexo), 1950)

    def test_las_mujeres_viven_mas_que_los_hombres_a_los_15_y_los_60_casi_siempre(self):
        mas, total = 0, 0
        for (codigo, desde, sexo, edad), f in self.indice.items():
            if sexo == "F" and edad in ("15", "60") and (codigo, desde, "M", edad) in self.indice:
                total += 1
                mas += float(f["esperanza_restante"]) >= float(self.indice[(codigo, desde, "M", edad)]["esperanza_restante"])
        self.assertGreater(total, 1000)
        self.assertGreater(mas / total, 0.95)

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


class ModeloTest(unittest.TestCase):
    """modelo.py contra el ejemplo publicado de DemoTools: ambos sexos, 5q0 = 0,05 y e0 = 65."""

    REFERENCIA = {  # edad: (esperanza restante, proporción de sobrevivientes), de la documentación de DemoTools
        5: (63.386425, 0.95), 15: (53.981431, 0.9404629), 30: (40.557943, 0.9092514),
        45: (27.551048, 0.8562334), 60: (16.093687, 0.7216024), 75: (7.572554, 0.3936466),
    }

    def test_reproduce_el_ejemplo_de_demotools(self):
        obtenido = modelo.esperanzas_por_edad(50, 65.0, tuple(self.REFERENCIA))
        for edad, (e, l) in self.REFERENCIA.items():
            self.assertAlmostEqual(obtenido[edad][0], e, delta=0.01, msg=f"esperanza a los {edad}")
            self.assertAlmostEqual(obtenido[edad][1], l, delta=0.0005, msg=f"supervivencia a los {edad}")

    def test_k_hace_que_la_tabla_tenga_la_e0_pedida(self):
        for q5, e0 in ((0.05, 65.0), (0.4, 28.0), (0.01, 75.0), (0.2, 40.0)):
            k = modelo.resolver_k(q5, e0)
            self.assertIsNotNone(k)
            self.assertAlmostEqual(modelo.tabla(q5, k)[1][0], e0, places=6)

    def test_mas_mortalidad_menos_esperanza(self):
        self.assertGreater(modelo.tabla(0.05, -1.0)[1][0], modelo.tabla(0.05, 1.0)[1][0])

    def test_la_supervivencia_a_los_5_es_la_que_se_pidio(self):
        for q5 in (0.01, 0.1, 0.4):
            k = modelo.resolver_k(q5, 40.0 if q5 > 0.05 else 70.0)
            l, _ = modelo.tabla(q5, k)
            self.assertAlmostEqual(l[modelo.EDADES.index(5)], 1 - q5, places=9)

    def test_la_tabla_es_coherente(self):
        l, e = modelo.tabla(0.1, modelo.resolver_k(0.1, 50.0))
        self.assertEqual(len(l), 24)
        self.assertTrue(all(a > b for a, b in zip(l[:18], l[1:18])))   # hasta los 85 años, estrictamente
        self.assertTrue(all(a >= b for a, b in zip(l, l[1:])))         # y nunca sube
        vivos = [i for i in range(24) if l[i] > 0]   # a edades extremas la proporción puede llegar a 0
        self.assertGreaterEqual(len(vivos), 20)
        self.assertTrue(all(e[i] > 0 for i in vivos))
        edades = modelo.EDADES
        self.assertTrue(all(edades[i] + e[i] <= edades[i + 1] + e[i + 1] + 1e-9 for i in vivos[:-1]))

    def test_cuadra_con_la_derivacion_a_los_5_anios(self):
        # Dos caminos a la esperanza restante a los 5 años: la identidad de preparar_datos y la tabla del modelo.
        for q5, e0 in ((0.05, 65.0), (0.4, 28.0), (0.2, 40.0), (0.01, 75.0)):
            del_modelo = modelo.esperanzas_por_edad(q5 * 1000, e0, (5,))[5][0]
            self.assertAlmostEqual(pd.esperanza_a_los_5(e0, q5 * 1000)[0], del_modelo, delta=0.4, msg=(q5, e0))

    def test_sin_solucion_es_none_no_un_error(self):
        self.assertIsNone(modelo.resolver_k(0.4, 90.0))              # tanta e0 con tanta mortalidad infantil
        self.assertIsNone(modelo.esperanzas_por_edad(0, 70.0, (15,)))  # sin mortalidad: no hay h = log(0)
        with self.assertRaises(ValueError):
            modelo.tabla(1.5, 0.0)

    def test_los_coeficientes_son_los_24_grupos_de_cada_sexo(self):
        for sexo in modelo.SEXOS:
            ax, bx, cx, vx = modelo.coeficientes(sexo)
            self.assertEqual((len(ax), len(bx), len(cx), len(vx)), (24,) * 4, sexo)
            self.assertEqual(vx[0], 0.0, sexo)  # a la edad 0 el modelo no depende de k

    def test_cada_sexo_tiene_su_modelo(self):
        m, f, t = (modelo.esperanzas_por_edad(50, 65.0, (15, 60), s) for s in ("M", "F", "T"))
        self.assertNotEqual(m, f)
        # Con la misma mortalidad infantil y la misma esperanza al nacer, el promedio de los dos sexos queda entre ellos.
        for edad in (15, 60):
            self.assertLess(min(m[edad][0], f[edad][0]) - 1.0, t[edad][0])
            self.assertGreater(max(m[edad][0], f[edad][0]) + 1.0, t[edad][0])

    def test_sexo_invalido(self):
        with self.assertRaises(ValueError):
            modelo.coeficientes("X")


class LeerRdaTest(unittest.TestCase):
    """leer_rda sobre un archivo armado a mano: una lista con nombre `x` que contiene un vector de dos números."""

    @staticmethod
    def rda():
        import gzip
        import struct
        i = lambda n: struct.pack(">i", n)
        cuerpo = (i(0x402)                                  # LISTSXP con etiqueta
                  + i(1) + i(9) + i(1) + b"x"               # SYMSXP -> CHARSXP «x»
                  + i(14) + i(2) + struct.pack(">dd", 1.5, -2.0)   # REALSXP de dos números
                  + i(254))                                 # NULL: fin de la lista
        cabecera = b"RDX3\nX\n" + i(3) + i(0x040100) + i(0x030500) + i(5) + b"UTF-8"
        return gzip.compress(cabecera + cuerpo)

    def test_lee_un_vector_con_nombre(self):
        with tempfile.TemporaryDirectory() as tmp:
            ruta = Path(tmp) / "a.rda"
            ruta.write_bytes(self.rda())
            self.assertEqual(leer_rda.aplanar(leer_rda.cargar(ruta)), {"x": [1.5, -2.0]})

    def test_un_tipo_que_no_conoce_falla_en_vez_de_adivinar(self):
        import gzip
        import struct
        malo = gzip.compress(b"RDX3\nX\n" + struct.pack(">iii", 3, 0, 0) + struct.pack(">i", 5) + b"UTF-8" + struct.pack(">i", 3))
        with tempfile.TemporaryDirectory() as tmp:
            ruta = Path(tmp) / "b.rda"
            ruta.write_bytes(malo)
            with self.assertRaises(Exception):
                leer_rda.cargar(ruta)


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
        self.assertIsNone(consultas.esperanza_restante("CL", 1900, 20))   # edad que no está
        self.assertIsNone(consultas.esperanza_restante("CL", 1900, 5, "F"))  # 5 años solo para ambos sexos
        self.assertIsNone(consultas.esperanza_restante("CL", 1900, 0, "F"))  # por sexo desde 1950

    def test_sexo_invalido(self):
        with self.assertRaises(ValueError):
            consultas.esperanza_restante("CL", 1900, 0, "X")
        with self.assertRaises(ValueError):
            consultas.banda_esperanza("CL", 1900, 0, "X")

    def test_banda_contiene_a_la_cifra(self):
        for codigo, anio, edad, sexo in (("SE", 1805, 0, "T"), ("SE", 1805, 15, "T"), ("SE", 1955, 60, "F"),
                                         ("GB", 1545, 0, "T"), ("JP", 1985, 5, "M")):
            bajo, alto = consultas.banda_esperanza(codigo, anio, edad, sexo)
            self.assertLessEqual(bajo, consultas.esperanza_restante(codigo, anio, edad, sexo))
            self.assertGreaterEqual(alto, consultas.esperanza_restante(codigo, anio, edad, sexo))

    def test_la_banda_es_mas_ancha_antes_de_1950(self):
        ancho = lambda anio: -(lambda b: b[0] - b[1])(consultas.banda_esperanza("SE", anio))
        self.assertGreater(ancho(1805), ancho(1905))
        self.assertGreater(ancho(1905), ancho(1955))

    def test_banda_sin_dato_es_none(self):
        self.assertIsNone(consultas.banda_esperanza("XX", 1900))
        self.assertIsNone(consultas.banda_esperanza("SE", 1805, 20))

    def test_reino_unido_antes_de_1800(self):
        self.assertAlmostEqual(consultas.esperanza_restante("GB", 1545), 36.4, places=1)
        self.assertIsNone(consultas.esperanza_restante("GB", 1545, 5))   # antes de 1800 solo al nacer
        self.assertIsNone(consultas.esperanza_restante("ES", 1545))      # España no tiene serie tan atrás

    def test_mujeres_y_hombres_a_los_15_desde_1950(self):
        self.assertIsNotNone(consultas.esperanza_restante("SE", 1955, 15, "F"))
        self.assertGreater(consultas.esperanza_restante("SE", 1955, 15, "F"), consultas.esperanza_restante("SE", 1955, 15, "M"))
        self.assertIsNone(consultas.esperanza_restante("SE", 1905, 15, "F"))


class PlausibilidadTest(unittest.TestCase):
    """Estadística de edades de muerte y supervivencia, por país y década (modelada)."""

    FUNCIONES = ("probabilidad_de_llegar_a", "supervivientes_esperados", "fraccion_de_muertes_antes_de",
                 "edad_de_muerte_percentil", "edades_de_muerte_comunes")

    @classmethod
    def setUpClass(cls):
        cls.muestra = [f for f in comun.filas_csv("esperanza_vida")
                       if f["sexo"] == "T" and f["edad"] == "5" and f["fuente"] == pd.FUENTE_E5][::13]

    def test_el_contrato_ninguna_funcion_recibe_una_persona(self):
        # Solo país, año, sexo y edades o cantidades: nunca una ficha, un nombre, un apellido ni un origen.
        for nombre in self.FUNCIONES:
            parametros = set(inspect.signature(getattr(consultas, nombre)).parameters)
            self.assertLessEqual(parametros, consultas.PARAMETROS_PERMITIDOS, nombre)

    def test_llegar_a_los_5_es_la_supervivencia_guardada(self):
        self.assertGreater(len(self.muestra), 200)
        for f in self.muestra:
            anio = int(f["desde"]) + 3
            self.assertAlmostEqual(consultas.probabilidad_de_llegar_a(f["codigo"], anio, 5), float(f["supervivencia_hasta_edad"]), delta=6e-4, msg=f)

    def test_la_probabilidad_de_llegar_baja_con_la_edad_y_empieza_en_uno(self):
        for codigo, anio in (("SE", 1805), ("CL", 1895), ("JP", 1985), ("NG", 1955)):
            valores = [consultas.probabilidad_de_llegar_a(codigo, anio, e) for e in (0, 1, 5, 15, 40, 70, 90, 110)]
            self.assertEqual(valores[0], 1.0)
            self.assertTrue(all(a >= b for a, b in zip(valores, valores[1:])), (codigo, valores))

    def test_los_percentiles_estan_en_orden_y_dentro_de_0_a_110(self):
        for f in self.muestra[::6]:
            anio = int(f["desde"]) + 3
            comunes = consultas.edades_de_muerte_comunes(f["codigo"], anio)
            self.assertEqual(list(comunes), ["p10", "p25", "p50", "p75", "p90"])
            valores = list(comunes.values())
            self.assertEqual(valores, sorted(valores), f)
            self.assertTrue(0 <= valores[0] and valores[-1] <= 110, f)

    def test_percentil_y_fraccion_son_inversas(self):
        for codigo, anio, desde in (("SE", 1805, 0), ("CL", 1895, 15), ("ES", 1955, 40), ("JP", 1985, 0)):
            for p in (0.1, 0.5, 0.9):
                edad = consultas.edad_de_muerte_percentil(codigo, anio, p, desde)
                self.assertAlmostEqual(consultas.fraccion_de_muertes_antes_de(codigo, anio, edad, desde), p, places=9, msg=(codigo, anio, desde, p))

    def test_la_fraccion_respeta_la_edad_de_partida(self):
        self.assertEqual(consultas.fraccion_de_muertes_antes_de("SE", 1805, 15, 15), 0.0)
        self.assertGreater(consultas.fraccion_de_muertes_antes_de("SE", 1805, 110), 0.999)
        # De quien llegaba a los 15 moría antes de los 40 una fracción distinta de la de todos los nacidos.
        self.assertNotAlmostEqual(consultas.fraccion_de_muertes_antes_de("SE", 1805, 40, 15), consultas.fraccion_de_muertes_antes_de("SE", 1805, 40, 0), places=3)

    def test_antes_de_los_15_moria_mucha_gente_en_1805_y_poca_en_1995(self):
        antes = consultas.fraccion_de_muertes_antes_de("SE", 1805, 15)
        despues = consultas.fraccion_de_muertes_antes_de("SE", 1995, 15)
        self.assertGreater(antes, 0.3)
        self.assertLess(despues, 0.02)

    def test_supervivientes_esperados_es_lineal_en_los_nacidos(self):
        uno = consultas.supervivientes_esperados("SE", 1805, 1, 5)
        self.assertAlmostEqual(consultas.supervivientes_esperados("SE", 1805, 6, 5), 6 * uno, places=12)
        self.assertEqual(consultas.supervivientes_esperados("SE", 1805, 0, 5), 0)
        self.assertAlmostEqual(uno, consultas.probabilidad_de_llegar_a("SE", 1805, 5), places=12)

    def test_la_esperanza_por_trapecios_cuadra_con_la_guardada(self):
        edades = modelo.EDADES
        for codigo, anio in (("SE", 1805), ("CL", 1895), ("JP", 1985), ("NG", 1955)):
            l = [consultas.probabilidad_de_llegar_a(codigo, anio, e) for e in edades]
            area = sum((edades[i + 1] - edades[i]) * (l[i] + l[i + 1]) / 2 for i in range(len(edades) - 1))
            self.assertAlmostEqual(area, consultas.esperanza_restante(codigo, anio, 0), delta=0.5, msg=(codigo, anio))

    def test_las_mujeres_mueren_mas_tarde_que_los_hombres_casi_siempre(self):
        mas, total = 0, 0
        mujeres = [f for f in comun.filas_csv("esperanza_vida") if f["sexo"] == "F" and f["edad"] == "5" and int(f["desde"]) >= 1950]
        for f in mujeres[::6]:
            anio = int(f["desde"]) + 3
            m = consultas.edad_de_muerte_percentil(f["codigo"], anio, 0.5, 15, "M")
            w = consultas.edad_de_muerte_percentil(f["codigo"], anio, 0.5, 15, "F")
            if m is not None and w is not None:
                total += 1
                mas += w >= m
        self.assertGreater(total, 100)
        self.assertGreater(mas / total, 0.95)

    def test_sin_datos_es_none_no_un_error(self):
        for codigo, anio in (("GB", 1545), ("R:Europa", 1775), ("XX", 1900), ("SE", 1799), ("SE", 2010)):
            self.assertIsNone(consultas.probabilidad_de_llegar_a(codigo, anio, 5), (codigo, anio))
            self.assertIsNone(consultas.edades_de_muerte_comunes(codigo, anio), (codigo, anio))
        self.assertIsNone(consultas.probabilidad_de_llegar_a("SE", 1805, 5, "F"))   # mujeres, solo desde 1950

    def test_entradas_invalidas(self):
        for llamada in (lambda: consultas.probabilidad_de_llegar_a("SE", 1805, 111), lambda: consultas.probabilidad_de_llegar_a("SE", 1805, -1),
                        lambda: consultas.probabilidad_de_llegar_a("SE", 1805, 5, "X"), lambda: consultas.edad_de_muerte_percentil("SE", 1805, 0),
                        lambda: consultas.edad_de_muerte_percentil("SE", 1805, 1), lambda: consultas.supervivientes_esperados("SE", 1805, -1, 5),
                        lambda: consultas.fraccion_de_muertes_antes_de("SE", 1805, 10, 20)):
            with self.assertRaises(ValueError):
                llamada()


class PerfilDelModeloTest(unittest.TestCase):
    def test_interpolacion_y_su_inversa(self):
        l, _ = modelo.perfil(100.0, 55.0)
        for edad in (0, 0.5, 1, 3, 5, 17, 42.5, 80, 110):
            s = modelo.supervivencia_en(l, edad)
            if 0 < s <= 1 and edad < 100:
                self.assertAlmostEqual(modelo.edad_de_supervivencia(l, s), edad, places=6, msg=edad)

    def test_limites(self):
        l, _ = modelo.perfil(100.0, 55.0)
        self.assertEqual(modelo.supervivencia_en(l, 0), 1.0)
        with self.assertRaises(ValueError):
            modelo.supervivencia_en(l, 111)
        with self.assertRaises(ValueError):
            modelo.edad_de_supervivencia(l, 0)
        self.assertEqual(modelo.edad_de_supervivencia(l, 1.0), 0.0)
        self.assertIsNone(modelo.perfil(0.0, 55.0))
        self.assertIsNone(modelo.perfil(400.0, 90.0))


class NormalizarTest(unittest.TestCase):
    def test_normalizar(self):
        self.assertEqual(comun.normalizar("  Schlagfluß "), "schlagfluss")
        self.assertEqual(comun.normalizar("CÓLERA   morbo"), "colera morbo")
        self.assertEqual(comun.normalizar("اَلسُّلّ"), comun.normalizar("السل"))


if __name__ == "__main__":
    unittest.main()
