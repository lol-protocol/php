#!/usr/bin/env python3
"""Pruebas de Listado-paises. Uso, desde esta carpeta: python3 -m unittest -v pruebas

Solo biblioteca estándar. Usan series sintéticas con resultado calculado a mano; no tocan los
CSV del proyecto (las pruebas de main() trabajan en una carpeta temporal).
"""
import csv
import shutil
import tempfile
import unittest
from pathlib import Path

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

    def test_suma_con_la_natalidad_del_archivo_y_0040_donde_falta(self):
        # 1000 personas desde -1000: 2800 anios a 0,040 hasta 1799 y 226 anios a 0,010 desde 1800.
        natalidad = {t: 0.010 for t in range(1800, 2026)}
        esperado = 1000 * 0.04 * 2800 + 1000 * 0.010 * 226
        self.assertAlmostEqual(hp.nacimientos([(-1000, 1000.0), (0, 1000.0)], natalidad), esperado)

    def test_suma_sin_natalidad_usa_la_tasa_preindustrial(self):
        # 50 personas desde el anio 100 hasta 2025, siempre a 0,040.
        esperado = 50 * 0.04 * (2026 - 100)
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

    def test_ultimo_dato_solo_si_es_desde_el_anio_minimo(self):
        import preparar_datos as pd
        self.assertEqual(pd.ultimo_dato({2015: 50.0, 2022: 60.0}, 2010), (2022, 60.0))
        self.assertIsNone(pd.ultimo_dato({2008: 50.0}, 2010))
        self.assertIsNone(pd.ultimo_dato({}, 2010))

    def test_pib_nominal_usa_el_ultimo_anio_con_pib_y_poblacion_dentro_del_rango(self):
        import preparar_datos as pd
        pib = {pd.ANIO_PIB_NOMINAL: 300.0, pd.ANIO_PIB_NOMINAL - 1: 200.0}
        poblacion = {pd.ANIO_PIB_NOMINAL - 1: 10.0}  # sin población del último año
        self.assertEqual(pd.pib_nominal(pib, poblacion), (pd.ANIO_PIB_NOMINAL - 1, 20.0))
        viejo = pd.ANIO_PIB_NOMINAL_MINIMO - 1
        self.assertIsNone(pd.pib_nominal({viejo: 100.0}, {viejo: 10.0}))

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

    def test_ip_es_el_maximo_de_las_lenguas_y_ip_local_el_de_la_primera(self):
        niveles = {f["lengua"]: int(f["nivel"]) for f in leer_csv(AQUI / "niveles_lenguas.csv")}
        for f in leer_csv(AQUI / "paises_pos.csv"):
            lenguas = [l.strip() for l in f["idioma_principal"].split(";")]
            self.assertEqual(int(f["indice_pos"]), max(niveles[l] for l in lenguas), f["codigo"])
            self.assertEqual(int(f["indice_pos_local"]), niveles[lenguas[0]], f["codigo"])


if __name__ == "__main__":
    unittest.main()
