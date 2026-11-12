#!/usr/bin/env python3
"""Pruebas de Listado-paises. Uso, desde esta carpeta: python3 -m unittest -v pruebas

Solo biblioteca estándar. Usan series sintéticas con resultado calculado a mano; no tocan los
CSV del proyecto (las pruebas de main() trabajan en una carpeta temporal).
"""
import csv
import json
import shutil
import tempfile
import unittest
from pathlib import Path
from unittest import mock

import generar_tabla
import historia_poblacion as hp

AQUI = Path(__file__).resolve().parent


def leer_csv(ruta):
    with ruta.open(encoding="utf-8", newline="") as f:
        return list(csv.DictReader(f))


def escribir_serie(ruta, filas, columnas=("codigo", "anio", "poblacion")):
    with ruta.open("w", encoding="utf-8", newline="") as f:
        escritor = csv.writer(f)
        escritor.writerow(columnas)
        escritor.writerows(filas)


class InterpolacionYNacimientos(unittest.TestCase):
    def test_interpolacion_geometrica_entre_puntos_positivos(self):
        self.assertAlmostEqual(hp.poblacion_en([0, 100], [100, 400], 50), 200)

    def test_interpolacion_lineal_si_un_punto_es_cero(self):
        self.assertAlmostEqual(hp.poblacion_en([0, 100], [0, 100], 50), 50)

    def test_cero_antes_del_primer_punto_y_ultimo_valor_despues(self):
        self.assertEqual(hp.poblacion_en([100, 200], [50, 50], 10), 0.0)
        self.assertEqual(hp.poblacion_en([100, 200], [50, 50], 999), 50)

    def test_natalidad_por_epoca_en_los_bordes(self):
        self.assertEqual([hp.natalidad_por_epoca(t) for t in (-5000, 0, 1, 1649, 1650, 1749, 1750, 2000)],
                         [0.080, 0.080, 0.060, 0.060, 0.050, 0.050, 0.040, 0.040])

    def test_suma_con_la_natalidad_del_archivo_y_la_de_la_epoca_donde_falta(self):
        # 1000 personas desde -1000. Sin archivo: 1001 anios a 0,080 (hasta el 0), 1649 a 0,060,
        # 100 a 0,050 y 50 a 0,040 (1750-1799). Con archivo: 226 anios a 0,010 (1800-2025).
        natalidad = {t: 0.010 for t in range(1800, 2026)}
        esperado = 1000 * (0.08 * 1001 + 0.06 * 1649 + 0.05 * 100 + 0.04 * 50 + 0.010 * 226)
        self.assertAlmostEqual(hp.nacimientos([(-1000, 1000.0), (0, 1000.0)], natalidad), esperado)

    def test_suma_sin_natalidad_usa_la_de_cada_epoca(self):
        # 50 personas desde el anio 100 hasta 2025: 1550 anios a 0,060, 100 a 0,050 y 276 a 0,040.
        esperado = 50 * (0.06 * 1550 + 0.05 * 100 + 0.04 * 276)
        self.assertAlmostEqual(hp.nacimientos([(100, 50.0), (200, 50.0)], {}), esperado)


class CargaYCalculo(unittest.TestCase):
    def setUp(self):
        self.carpeta = Path(tempfile.mkdtemp())
        self.addCleanup(shutil.rmtree, self.carpeta)

    def test_un_anio_repetido_detiene_el_calculo(self):
        escribir_serie(self.carpeta / "s.csv", [("AA", 1000, 100), ("AA", 1000, 200), ("AA", 2000, 300)])
        with self.assertRaises(ValueError):
            hp.cargar_puntos(self.carpeta / "s.csv")

    def test_puntos_desordenados_se_ordenan(self):
        escribir_serie(self.carpeta / "s.csv", [("AA", 2000, 300), ("AA", 1000, 100)])
        self.assertEqual(hp.cargar_puntos(self.carpeta / "s.csv")["AA"], [(1000, 100.0), (2000, 300.0)])

    def test_sin_serie_larga_no_cambia_paises_csv(self):
        shutil.copy(AQUI / "paises.csv", self.carpeta / "paises.csv")
        antes = (self.carpeta / "paises.csv").read_bytes()
        hp.main(self.carpeta)
        self.assertEqual((self.carpeta / "paises.csv").read_bytes(), antes)

    def test_serie_que_termina_antes_de_2000_se_salta_y_otra_se_calcula(self):
        shutil.copy(AQUI / "paises.csv", self.carpeta / "paises.csv")
        escribir_serie(self.carpeta / "poblacion_larga.csv", [
            ("BB", 1000, 100), ("BB", 1800, 100),  # Barbados: termina en 1800, se salta
            ("CU", 1000, 100), ("CU", 2023, 100),  # Cuba: completa
        ])
        hp.main(self.carpeta)
        filas = {f["codigo"]: f for f in leer_csv(self.carpeta / "paises.csv")}
        self.assertEqual(filas["BB"]["poblacion_historica_acumulada"], "")
        self.assertNotEqual(filas["CU"]["poblacion_historica_acumulada"], "")


class DatosPreparados(unittest.TestCase):
    def test_extension_sigue_a_la_region_y_falla_claro_sin_poblacion_regional(self):
        import preparar_datos
        region = [(2000, 100.0), (2010, 200.0), (2024, 300.0)]
        self.assertEqual(preparar_datos.extender([(1990, 5.0), (2010, 20.0)], region, hasta=2024), [(2024, 30.0)])
        with self.assertRaises(SystemExit):
            preparar_datos.retroproyectar([(1500, 10.0)], [(1000, 0.0), (1600, 0.0)])

    def test_poblamiento_es_una_serie_que_empieza_antes_de_1800_con_poca_gente(self):
        import preparar_datos as pd
        self.assertTrue(pd.empieza_poblado([(1500, 373.0), (1600, 2000.0)]))
        self.assertFalse(pd.empieza_poblado([(1600, 859_300.0)]))  # Sudán del Sur: hay gente antes
        self.assertFalse(pd.empieza_poblado([(1800, 5000.0)]))  # micro-estado sin dato anterior
        self.assertFalse(pd.empieza_poblado([(-10000, 10.0)]))
        self.assertFalse(pd.empieza_poblado([]))

    def test_america_se_escala_a_denevan_hasta_1500_y_queda_igual_despues(self):
        import preparar_datos as pd
        series = {"MX": [(1000, 10e6), (1500, 34.348e6), (1600, 1e6)], "FR": [(1500, 15e6)]}
        factores = pd.ajustar_america(series)
        self.assertEqual(set(factores), {"México"})  # sin países presentes, la región se salta
        self.assertAlmostEqual(factores["México"], 0.5)
        self.assertEqual([round(p) for _, p in series["MX"]], [5_000_000, 17_174_000, 1_000_000])
        self.assertEqual(series["FR"], [(1500, 15e6)])
        with self.assertRaises(SystemExit):
            pd.ajustar_america({"MX": [(1600, 1e6)]})

    def test_retroproyeccion_usa_la_cuota_del_pais_en_su_region(self):
        import preparar_datos
        # La región tiene 100 en el año 0 y 200 en el año 100; el país tiene 20 en el año 100
        # (cuota 0,1), así que en el año 0 se estima en 10.
        puntos = preparar_datos.retroproyectar([(100, 20.0)], [(0, 100.0), (100, 200.0)])
        self.assertEqual(len(puntos), 1)
        self.assertEqual(puntos[0][0], 0)
        self.assertAlmostEqual(puntos[0][1], 10.0)

    def test_natalidad_escala_la_ultima_tasa_con_la_fecundidad(self):
        import preparar_datos as pd
        fin = hp.ANIO_FIN
        serie = pd.natalidad_pais({fin - 2: 30.0}, {fin - 2: 3.0, fin - 1: 2.0})
        self.assertEqual(serie[fin - 2], 30.0)
        self.assertAlmostEqual(serie[fin - 1], 20.0)  # 30 x 2 / 3
        self.assertEqual(serie[fin], 30.0)  # sin fecundidad ese año: la última tasa
        self.assertNotIn(fin + 1, pd.natalidad_pais({fin + 1: 30.0}, {}))

    def test_natalidad_sin_tasa_usa_la_fecundidad_y_sin_nada_queda_vacia(self):
        import preparar_datos as pd
        self.assertEqual(pd.natalidad_pais({}, {1900: 6.0}), {1900: pd.FACTOR_NATALIDAD * 6.0})
        self.assertEqual(pd.natalidad_pais({}, {}), {})

    def test_empalme_escala_los_puntos_viejos_para_coincidir_en_el_primer_anio_conocido(self):
        import preparar_datos as pd
        # La fuente vieja da 100 en 1800 y la conocida 110: sus puntos anteriores se escalan por 1,1.
        viejos = [(1500, 50.0), (1800, 100.0), (1900, 300.0)]
        self.assertEqual(pd.empalmar(viejos, [(1800, 110.0)]), [(1500, 55.0)])

    def test_empalme_se_descarta_si_las_fuentes_difieren_demasiado(self):
        import preparar_datos as pd
        viejos = [(1500, 50.0), (1800, 100.0)]
        self.assertEqual(pd.empalmar(viejos, [(1800, 100.0 * pd.EMPALME_MAXIMO * 1.01)]), [])
        self.assertEqual(pd.empalmar(viejos, [(1500, 50.0)]), [])  # nada anterior al primer año

    def test_ultimo_dato_solo_si_es_desde_el_anio_minimo(self):
        import preparar_datos as pd
        self.assertEqual(pd.ultimo_dato({2015: 50.0, 2022: 60.0}, 2010), (2022, 60.0))
        self.assertIsNone(pd.ultimo_dato({2008: 50.0}, 2010))
        self.assertIsNone(pd.ultimo_dato({}, 2010))

    def test_pib_nominal_usa_el_ultimo_anio_con_dato_dentro_del_rango(self):
        import preparar_datos as pd
        self.assertEqual(pd.pib_nominal({pd.ANIO_PIB_NOMINAL - 1: 200.0, pd.ANIO_PIB_NOMINAL - 2: 100.0}),
                         (pd.ANIO_PIB_NOMINAL - 1, 200.0))
        self.assertIsNone(pd.pib_nominal({pd.ANIO_PIB_NOMINAL_MINIMO - 1: 100.0}))

    def test_la_columna_de_nacidos_coincide_con_el_calculo_sobre_los_archivos_de_entrada(self):
        carpeta = Path(tempfile.mkdtemp())
        self.addCleanup(shutil.rmtree, carpeta)
        for nombre in ("paises.csv", "poblacion_larga.csv", "natalidad.csv"):
            shutil.copy(AQUI / nombre, carpeta / nombre)
        hp.main(carpeta)
        guardado = {f["codigo"]: f["poblacion_historica_acumulada"] for f in leer_csv(AQUI / "paises.csv")}
        calculado = {f["codigo"]: f["poblacion_historica_acumulada"] for f in leer_csv(carpeta / "paises.csv")}
        self.assertEqual(guardado, calculado)


class GeneradorDeTablas(unittest.TestCase):
    def test_los_datos_del_proyecto_son_validos_y_las_tablas_estan_al_dia(self):
        generar_tabla.main(comprobar=True)

    def test_puestos_comparten_el_puesto_en_empate_y_saltan_al_siguiente(self):
        registros = [{"c": "A", "p": 9}, {"c": "B", "p": 9}, {"c": "C", "p": 4}, {"c": "D", "p": 1}]
        puestos = generar_tabla.puestos(registros, lambda d: d["p"], lambda d: d["c"] == "D")
        self.assertEqual(puestos, {"A": 1, "B": 1, "C": 3, "D": None})

    def test_ip_es_el_maximo_de_las_lenguas_y_ip_local_el_de_la_primera(self):
        niveles = {f["lengua"]: int(f["nivel"]) for f in leer_csv(AQUI / "niveles_lenguas.csv")}
        for f in leer_csv(AQUI / "paises_pos.csv"):
            lenguas = [l.strip() for l in f["idioma_principal"].split(";")]
            self.assertEqual(int(f["indice_pos"]), max(niveles[l] for l in lenguas), f["codigo"])
            self.assertEqual(int(f["indice_pos_local"]), niveles[lenguas[0]], f["codigo"])

    def test_la_prioridad_y_exclusion_del_csv_final_coinciden_con_la_regla_vigente(self):
        """priorizacion.csv sale de ESCENARIOS_GEN[0]/ESCENARIOS_POS[0], no de una fórmula aparte que podría
        desincronizarse de la etiquetada «Vigente» en escenarios.md."""
        _, puntaje_gen, excluido_gen = generar_tabla.ESCENARIOS_GEN[0]
        _, puntaje_pos, excluido_pos = generar_tabla.ESCENARIOS_POS[0]
        for f in leer_csv(AQUI / "priorizacion.csv"):
            d = {"ig": int(f["indice_genealogia"]), "io": int(f["indice_operabilidad"]),
                 "il": int(f["indice_lectura"]), "ipl": int(f["indice_pos_local"])}
            self.assertEqual(int(f["prioridad_genealogia"]), puntaje_gen(d), f["codigo"])
            self.assertEqual(f["excluido_genealogia"] == "si", excluido_gen(d), f["codigo"])
            self.assertEqual(int(f["prioridad_pos"]), puntaje_pos(d), f["codigo"])
            self.assertEqual(f["excluido_pos"] == "si", excluido_pos(d), f["codigo"])


class ValidacionYOrden(unittest.TestCase):
    def fila_de_ejemplo(self, **cambios):
        return {**leer_csv(AQUI / "paises.csv")[0], **cambios}

    def errores(self, fila):
        return generar_tabla.errores_de("x", 2, fila, generar_tabla.ESCALAS_PAISES, generar_tabla.NUMERICAS_PAISES)

    def test_la_fila_del_proyecto_no_da_errores(self):
        self.assertEqual(self.errores(self.fila_de_ejemplo()), [])

    def test_un_numero_negativo_o_no_finito_es_un_error(self):
        for campo, valor, texto in (("poblacion_millones", "nan", "finito"), ("pib_per_capita_usd", "inf", "finito"),
                                    ("pib_per_capita_usd", "-500", "negativo"), ("idh", "-0.1", "negativo")):
            errores = self.errores(self.fila_de_ejemplo(**{campo: valor}))
            self.assertTrue(any(campo in e and texto in e for e in errores), (campo, valor, errores))

    def test_los_emigrantes_no_pasan_del_100_por_ciento(self):
        self.assertIn("emigrantes_pct", generar_tabla.RANGOS)
        with mock.patch.multiple(generar_tabla, PAISES=self.copia_con(emigrantes_pct="250")):
            with self.assertRaises(SystemExit) as error:
                generar_tabla.main(comprobar=True)
        self.assertIn("emigrantes_pct fuera de 0–100: 250", str(error.exception))

    def copia_con(self, **cambios):
        """Una copia de paises.csv con la primera fila cambiada."""
        carpeta = Path(tempfile.mkdtemp())
        self.addCleanup(shutil.rmtree, carpeta)
        filas = leer_csv(AQUI / "paises.csv")
        filas[0].update(cambios)
        with (carpeta / "paises.csv").open("w", encoding="utf-8", newline="") as f:
            escritor = csv.DictWriter(f, fieldnames=generar_tabla.COLUMNAS_PAISES)
            escritor.writeheader()
            escritor.writerows(filas)
        return carpeta / "paises.csv"

    def test_el_tope_de_io_por_internet_sale_de_la_constante(self):
        # Con el tope en 2, un IO 3 con poco internet es un error; antes solo se miraban IO 4 y 5 a mano.
        ruta = self.copia_con(usuarios_internet="10", indice_operabilidad="3")
        with mock.patch.multiple(generar_tabla, PAISES=ruta, TOPE_IO_INTERNET=(60, 2)):
            with self.assertRaises(SystemExit) as error:
                generar_tabla.main(comprobar=True)
        self.assertIn("IO 3 supera el tope 2", str(error.exception))

    def test_el_nombre_se_ordena_sin_acentos_como_en_la_vista(self):
        nombres = ["Libia", "Liberia", "Líbano", "México", "Moldavia", "Mónaco"]
        self.assertEqual(sorted(nombres, key=generar_tabla.clave_nombre),
                         ["Líbano", "Liberia", "Libia", "México", "Moldavia", "Mónaco"])

    def test_una_barra_vertical_no_rompe_la_tabla_markdown(self):
        tabla = generar_tabla.tabla_md("T", "n", ["A", "B"], [["uno | dos", "tres\ncuatro"]])
        self.assertIn("| uno \\| dos | tres cuatro |", tabla)

    def test_si_no(self):
        self.assertEqual([generar_tabla.si_no(True), generar_tabla.si_no(False)], ["sí", "no"])
        self.assertEqual(generar_tabla.si_no(True, ascii=True), "si")

    def test_la_vista_busca_sin_acentos(self):
        for archivo in ("vista_plantilla.html", "vista.html"):
            texto = (AQUI / archivo).read_text(encoding="utf-8")
            self.assertIn('normalize("NFD")', texto, archivo)
            self.assertNotIn(".toLowerCase().includes(texto)", texto, archivo)


class PreparacionDePuntaAPunta(unittest.TestCase):
    """preparar_datos.main y preparar_lenguas.main sobre fuentes sintéticas mínimas.

    Tres países: AA está en todas las fuentes; BB no está en OWID y usa la población del Banco
    Mundial; CC solo está en la población de Gapminder (serie larga, sin población actual).
    """

    def setUp(self):
        self.d = Path(tempfile.mkdtemp())
        self.addCleanup(shutil.rmtree, self.d)

    def csv(self, nombre, columnas, filas):
        escribir_serie(self.d / nombre, filas, columnas)
        return str(self.d / nombre)

    def test_preparar_datos(self):
        import preparar_datos as pd
        vacia = {c: "" for c in generar_tabla.COLUMNAS_PAISES}
        with (self.d / "paises.csv").open("w", encoding="utf-8", newline="") as f:
            w = csv.DictWriter(f, fieldnames=generar_tabla.COLUMNAS_PAISES)
            w.writeheader()
            for codigo in ("AA", "BB", "CC", "DD", "EE", "FF"):
                w.writerow({**vacia, "codigo": codigo, "pais": codigo, "region": "Europa"})
        self.csv("codigos_iso.csv", ("codigo", "codigo_owid", "nombre_iso"),
                 [("AA", "AAA", "A"), ("BB", "BBB", "B"), ("CC", "CCC", "C"),
                  ("DD", "DDD", "D"), ("EE", "EEE", "E"), ("FF", "FFF", "F")])
        hyde = "Global Population by Region (HYDE (2016) and UN (2017))"
        a = {
            "--co2": self.csv("co2.csv", ("iso_code", "country", "year", "population"),
                              [("AAA", "Aland", 1850, 1_000_000), ("AAA", "Aland", 2024, 4_000_000),
                               ("FFF", "Fland", 2024, 2_000_000),
                               ("", "Europe", 2010, 1e8), ("", "Europe", 2024, 1.2e8)]),
            # DD empieza en 1600 con 500 personas (su poblamiento); EE en 1800, con un hueco antes.
            "--poblacion-owid": self.csv("owid.csv", ("entity", "code", "year", "population_historical"),
                                         [("D", "DDD", 1600, 500), ("D", "DDD", 2023, 50_000),
                                          ("E", "EEE", 1800, 10_000), ("E", "EEE", 2023, 30_000)]),
            "--maddison": self.csv("mad.csv", ("Entity", "Year", "Population"),
                                   [("Aland", 1700, 800_000), ("D", 1000, 100)]),
            "--hyde": self.csv("hyde.csv", ("Entity", "Year", hyde), [("Europe", -10000, 1e6), ("Europe", 1500, 1e8)]),
            "--clio": self.csv("clio.csv", ("Entity", "Year", "Population (Clio)"),
                               [("Aland", 1500, 300_000), ("Aland", 1700, 640_000)]),
            "--natalidad-gapminder": self.csv("cbr.csv", ("geo", "time", "cbr"), [("aaa", 2015, 30.0)]),
            "--fecundidad-gapminder": self.csv("tfr.csv", ("country", "time", "tfr"),
                                               [("aaa", 2015, 3.0), ("aaa", 2016, 1.5)]),
            "--idh": self.csv("idh.csv", ("geo", "time", "hdi"), [("aaa", pd.ANIO_IDH, 0.8), ("fff", pd.ANIO_IDH, 0.0)]),
            "--alfabetizacion": self.csv("alf.csv", ("geo", "time", "lit"), [("aaa", 2009, 90.0), ("bbb", 2020, 70.0)]),
            "--internet": self.csv("net.csv", ("geo", "time", "net"), [("aaa", 2024, 80.0)]),
            "--emigrantes": self.csv("emi.csv", ("country", "time", "emigrant_n"), [("aaa", pd.ANIO_EMIGRANTES, 400_000), ("fff", pd.ANIO_EMIGRANTES, 0)]),
            "--poblacion-gapminder": self.csv("popg.csv", ("geo", "time", "pop"),
                                              [("ccc", 1800, 1000), ("ccc", 2010, 5000), ("ccc", 2030, 9000)]),
            "--libertad": self.csv("fh.csv", ("country", "time", "freedstatus_fh"),
                                   [("aaa", 2022, "PF"), ("bbb", 2010, "F")]),
            "--democracia": self.csv("eiu.csv", ("country", "time", "demox_eiu"), [("aaa", 2020, 61.5)]),
            "--corrupcion": self.csv("cpi.csv", ("country", "time", "cpi"), [("aaa", 2023, 45.0)]),
            "--pib-ppa": self.csv("ppa.csv", ("country", "time", "gdp_pcap"), [("aaa", pd.ANIO_PIB_PPA, 30000.4), ("fff", pd.ANIO_PIB_PPA, 0.0)]),
            "--pib-pc-bm": self.csv("gdp.csv", ("Country Name", "Country Code", "Year", "Value"),
                                    [("A", "AAA", pd.ANIO_PIB_NOMINAL, 20000.4),
                                     ("B", "BBB", pd.ANIO_PIB_NOMINAL - 2, 10000)]),
            "--poblacion-bm": self.csv("pop.csv", ("Country Name", "Country Code", "Year", "Value"),
                                       [("A", "AAA", 2023, 4e6), ("B", "BBB", 1960, 1e5), ("B", "BBB", 2022, 2e5),
                                        ("B", "BBB", 2024, 2.5e5)]),
        }
        bm = self.d / "cbr-bm.json"
        bm.write_text(json.dumps([{"page": 1}, [
            {"countryiso3code": "AAA", "date": "2017", "value": 10.0},
            {"countryiso3code": "AAA", "date": "2014", "value": 99.0},  # antes de 2016 manda Gapminder
            {"countryiso3code": "EEE", "date": "2020", "value": 12.0},
            {"countryiso3code": "", "date": "2020", "value": 5.0}]]), encoding="utf-8")
        a["--natalidad-bm"] = str(bm)
        pd.main([x for par in a.items() for x in par], self.d)

        paises = {f["codigo"]: f for f in leer_csv(self.d / "paises.csv")}
        aa, bb, cc = paises["AA"], paises["BB"], paises["CC"]
        self.assertEqual(aa["poblacion_millones"], "4.00")
        self.assertEqual(aa["idh"], "0.800")
        self.assertEqual(aa["pib_per_capita_usd"], "20000")
        self.assertEqual(aa["pib_per_capita_ppa_usd"], "30000")
        self.assertEqual(aa["alfabetizacion_adultos"], "")  # 2009 es anterior al mínimo
        self.assertEqual(aa["usuarios_internet"], "80.0")
        self.assertEqual(aa["emigrantes_pct"], "10.0")  # 400 000 / 4 000 000
        self.assertEqual((aa["libertad_fh"], aa["democracia_eiu"], aa["corrupcion_cpi"]), ("parcial", "61.5", "45"))
        self.assertEqual(bb["libertad_fh"], "")  # 2010 es anterior al mínimo
        self.assertEqual(bb["poblacion_millones"], "0.25")  # Banco Mundial, sin OWID
        self.assertEqual(bb["pib_per_capita_usd"], "10000")  # de dos años antes
        self.assertEqual(bb["alfabetizacion_adultos"], "70.0")
        self.assertEqual(cc["poblacion_millones"], "")  # Gapminder no da la población actual
        # Un 0 de la fuente es un dato, no un dato que falta.
        ff = paises["FF"]
        self.assertEqual((ff["idh"], ff["pib_per_capita_ppa_usd"], ff["emigrantes_pct"]), ("0.000", "0", "0.0"))
        detalle = {(f["codigo"], f["columna"]): (f["anio"], f["fuente"]) for f in leer_csv(self.d / "datos_detalle.csv")}
        self.assertEqual(detalle[("BB", "poblacion_millones")], ("2024", "Banco Mundial"))
        self.assertEqual(detalle[("BB", "pib_per_capita_usd")], (str(pd.ANIO_PIB_NOMINAL - 2), "Banco Mundial"))
        self.assertEqual(detalle[("BB", "alfabetizacion_adultos")], ("2020", "UNESCO vía Gapminder"))
        self.assertNotIn(("CC", "poblacion_millones"), detalle)

        larga = {}
        for f in leer_csv(self.d / "poblacion_larga.csv"):
            larga.setdefault(f["codigo"], {})[int(f["anio"])] = int(f["poblacion"])
        # AA: Clio 1500 empalmado con Maddison 1700 (factor 800 000 / 640 000 = 1,25), y antes HYDE
        # con la cuota de 1500.
        self.assertEqual(larga["AA"][1500], 375_000)
        self.assertEqual(larga["AA"][1700], 800_000)
        self.assertEqual(larga["AA"][-10000], round(375_000 / 1e8 * 1e6))
        self.assertEqual(larga["BB"][1960], 100_000)
        self.assertNotIn(2030, larga["CC"])  # la proyección de Gapminder no entra
        self.assertEqual(larga["CC"][2010], 5000)
        self.assertEqual(larga["CC"][2024], 6000)  # extendida con Europa: 5000 x 1,2e8 / 1e8
        self.assertEqual(min(larga["DD"]), 1600)  # nada antes del poblamiento, ni Maddison ni HYDE
        self.assertEqual(min(larga["EE"]), -10000)  # empieza en 1800: HYDE completa hacia atrás

        natalidad = {(f["codigo"], int(f["anio"])): float(f["tasa_por_mil"])
                     for f in leer_csv(self.d / "natalidad.csv")}
        self.assertEqual(natalidad[("AA", 2015)], 30.0)
        self.assertEqual(natalidad[("AA", 2016)], 20.0)  # entre Gapminder 2015 (30) y Banco Mundial 2017 (10)
        self.assertEqual(natalidad[("AA", 2017)], 10.0)  # Banco Mundial
        self.assertEqual(natalidad[("EE", 2020)], 12.0)

    def test_preparar_lenguas(self):
        import preparar_lenguas as pl
        ud = self.d / "ud"
        ud.mkdir()
        (ud / "UD_Aland-Uno.xml").write_text(
            "<treebank><size><total><tokens>9</tokens><words>600000</words></total></size></treebank>",
            encoding="utf-8")
        (self.d / "codes.yaml").write_text("Aland:\n  lcode: al\n  iso3: ala\n", encoding="utf-8")
        masakha = self.d / "masakha" / "blb"
        masakha.mkdir(parents=True)
        (masakha / "train.txt").write_text("palabra NOUN\n" * 20000, encoding="utf-8")
        self.csv("cc.csv", ("crawl", "primary_language", "pages", "urls", "%pages/crawl"),
                 [(pl.CRAWL, "ala", 1, 1, "1.5"), ("CC-VIEJO", "blb", 1, 1, "9")])
        self.csv("niveles_lenguas.csv", generar_tabla.COLUMNAS_NIVELES,
                 [("alandés", "ala", "", "", "", ""), ("bolo", "blb", "", "", "", ""), ("nada", "zzz", "", "", "", "")])
        self.csv("paises_pos.csv", generar_tabla.COLUMNAS_POS,
                 [("BB", "bolo; alandés", "3", "", "", "IP por x (mejor recurso listado). Alfabetización baja"),
                  ("AA", "alandés", "4", "", "", "")])
        pl.main(["--ud-stats", str(ud), "--ud-codigos", str(self.d / "codes.yaml"),
                 "--masakhapos", str(self.d / "masakha"), "--common-crawl", str(self.d / "cc.csv")], self.d)

        lenguas = {f["lengua"]: f for f in leer_csv(self.d / "niveles_lenguas.csv")}
        self.assertEqual((lenguas["alandés"]["palabras_anotadas"], lenguas["alandés"]["nivel"]), ("600000", "5"))
        self.assertEqual(lenguas["alandés"]["paginas_web_pct"], "1.5000")
        self.assertEqual((lenguas["bolo"]["palabras_anotadas"], lenguas["bolo"]["nivel"]), ("20000", "3"))
        self.assertEqual(lenguas["bolo"]["paginas_web_pct"], "")  # solo está en otro rastreo
        self.assertEqual(lenguas["nada"]["nivel"], "1")
        pos = {f["codigo"]: f for f in leer_csv(self.d / "paises_pos.csv")}
        self.assertEqual((pos["BB"]["indice_pos"], pos["BB"]["indice_pos_local"]), ("5", "3"))
        self.assertEqual(pos["BB"]["comentarios_pos"], "IP por alandés (mejor recurso listado). Alfabetización baja")
        self.assertEqual(pos["AA"]["comentarios_pos"], "")

    def test_preparar_lenguas_explica_lo_que_falta_en_vez_de_fallar_con_un_error_interno(self):
        import preparar_lenguas as pl
        ud = self.d / "ud"
        ud.mkdir()
        (ud / "UD_Aland-Uno.xml").write_text("<treebank></treebank>", encoding="utf-8")
        with self.assertRaises(SystemExit) as error:
            pl.palabras_ud(ud, {"Aland": "ala"})
        self.assertIn("UD_Aland-Uno.xml", str(error.exception))
        (ud / "UD_Aland-Uno.xml").write_text("<treebank><total><sentences>3</sentences></total></treebank>",
                                             encoding="utf-8")
        with self.assertRaises(SystemExit) as error:
            pl.palabras_ud(ud, {"Aland": "ala"})
        self.assertIn("<words> ni <tokens>", str(error.exception))

        self.csv("niveles_lenguas.csv", generar_tabla.COLUMNAS_NIVELES, [("alandés", "ala", "", "", "", "")])
        self.csv("paises_pos.csv", generar_tabla.COLUMNAS_POS, [("AA", "alandés; inventada", "3", "", "", "")])
        self.csv("cc.csv", ("crawl", "primary_language", "pages", "urls", "%pages/crawl"), [(pl.CRAWL, "ala", 1, 1, "1")])
        (ud / "UD_Aland-Uno.xml").write_text("<total><words>10</words></total>", encoding="utf-8")
        (self.d / "codes.yaml").write_text("Aland:\n  iso3: ala\n", encoding="utf-8")
        (self.d / "masakha").mkdir()
        with self.assertRaises(SystemExit) as error:
            pl.main(["--ud-stats", str(ud), "--ud-codigos", str(self.d / "codes.yaml"),
                     "--masakhapos", str(self.d / "masakha"), "--common-crawl", str(self.d / "cc.csv")], self.d)
        self.assertIn("inventada", str(error.exception))


if __name__ == "__main__":
    unittest.main()
