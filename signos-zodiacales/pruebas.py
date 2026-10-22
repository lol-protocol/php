"""Pruebas de signos-zodiacales: coherencia de los datos y de lo que genera generar.py.

Uso, desde esta carpeta:  python3 -m unittest -v pruebas
Solo usa la biblioteca estándar.
"""
import copy
import datetime
import json
import re
import subprocess
import sys
import unittest
from xml.etree import ElementTree

import generar

SVG_NS = "{http://www.w3.org/2000/svg}"


def cargar_datos():
    with generar.DATOS.open(encoding="utf-8") as f:
        return json.load(f)


class PruebasDeDatos(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.datos = cargar_datos()
        cls.signos = cls.datos["signos"]
        cls.occidentales = [s for s in cls.signos if s["tipo"] == "occidental"]
        cls.chinos = [s for s in cls.signos if s["tipo"] == "chino"]

    def test_los_datos_pasan_la_validacion(self):
        self.assertEqual(generar.validar(self.datos), [])

    def test_hay_doce_signos_de_cada_tipo(self):
        self.assertEqual(len(self.occidentales), 12)
        self.assertEqual(len(self.chinos), 12)

    def test_son_treinta_idiomas(self):
        self.assertEqual(len(generar.IDIOMAS), 30)

    def test_las_fechas_occidentales_cubren_el_ano_sin_huecos_ni_solapes(self):
        # Se recorre 2024, que es bisiesto: el 29 de febrero también tiene que caer en un signo.
        rangos = []
        for s in self.occidentales:
            ini, fin = (tuple(int(n) for n in p.split("/")[::-1]) for p in s["fechas"].split(" – "))
            rangos.append((ini, fin))
        dias = {}
        for i, (ini, fin) in enumerate(rangos):
            dia = datetime.date(2024, *ini)
            ultimo = datetime.date(2024, *fin)
            if ultimo < dia:  # el rango cruza el fin de año (Capricornio)
                ultimo = datetime.date(2025, *fin)
            while dia <= ultimo:
                clave = (dia.month, dia.day)
                self.assertFalse(clave in dias, f"{clave[1]:02d}/{clave[0]:02d} está en dos signos")
                dias[clave] = i
                dia += datetime.timedelta(days=1)
        esperados = set()
        dia = datetime.date(2024, 1, 1)
        while dia.year == 2024:
            esperados.add((dia.month, dia.day))
            dia += datetime.timedelta(days=1)
        self.assertEqual(esperados - set(dias), set(), "hay días sin signo")
        self.assertIn((2, 29), dias)

    def test_cada_elemento_occidental_tiene_tres_signos_y_cada_modalidad_cuatro(self):
        por_elemento, por_modalidad = {}, {}
        for s in self.occidentales:
            por_elemento.setdefault(s["elemento"], []).append(s["id"])
            por_modalidad.setdefault(s["modalidad"], []).append(s["id"])
        self.assertEqual({k: len(v) for k, v in por_elemento.items()}, {"fuego": 3, "tierra": 3, "aire": 3, "agua": 3})
        self.assertEqual({k: len(v) for k, v in por_modalidad.items()}, {"cardinal": 4, "fijo": 4, "mutable": 4})

    def test_elemento_y_modalidad_no_se_repiten_en_un_signo_occidental(self):
        pares = {(s["elemento"], s["modalidad"]) for s in self.occidentales}
        self.assertEqual(len(pares), 12)

    def test_el_horoscopo_chino_alterna_yang_y_yin(self):
        self.assertEqual([s["polaridad"] for s in self.chinos], ["yang", "yin"] * 6)

    def test_el_ciclo_chino_cae_en_los_anos_correctos(self):
        # Referencias conocidas: 2020 Rata, 2024 Dragón, 2026 Caballo.
        por_id = {s["id"]: s for s in self.chinos}
        self.assertIn(2020, por_id["rata"]["anios"])
        self.assertIn(2024, por_id["dragon"]["anios"])
        self.assertIn(2026, por_id["caballo"]["anios"])

    def test_cada_ano_pertenece_a_un_solo_animal(self):
        vistos = {}
        for s in self.chinos:
            for anio in s["anios"]:
                self.assertNotIn(anio, vistos, f"{anio} está en {vistos.get(anio)} y {s['id']}")
                vistos[anio] = s["id"]

    def test_los_textos_largos_son_mas_largos_que_los_cortos(self):
        for s in self.signos:
            for codigo in generar.IDIOMAS:
                self.assertGreater(len(s["descripciones_largas"][codigo]), len(s["descripciones"][codigo]),
                                   f"{s['id']}/{codigo}")


class PruebasDeValidacion(unittest.TestCase):
    """validar() tiene que atrapar datos rotos, no solo aprobar los buenos."""

    def setUp(self):
        self.datos = copy.deepcopy(cargar_datos())

    def test_detecta_un_idioma_que_falta(self):
        del self.datos["signos"][0]["nombres"]["da"]
        self.assertTrue(any("nombres" in e for e in generar.validar(self.datos)))

    def test_detecta_un_texto_vacio(self):
        self.datos["signos"][3]["descripciones"]["es"] = "  "
        self.assertTrue(any("vacío" in e for e in generar.validar(self.datos)))

    def test_detecta_una_linea_a_una_estrella_inexistente(self):
        self.datos["signos"][0]["constelacion"]["lineas"].append([0, 99])
        self.assertTrue(any("no existe" in e for e in generar.validar(self.datos)))

    def test_detecta_una_estrella_fuera_del_cuadro(self):
        self.datos["signos"][0]["constelacion"]["estrellas"][0] = [250, 10]
        self.assertTrue(any("fuera del cuadro" in e for e in generar.validar(self.datos)))

    def test_detecta_un_elemento_invalido(self):
        self.datos["signos"][0]["elemento"] = "plasma"
        self.assertTrue(any("elemento" in e for e in generar.validar(self.datos)))

    def test_detecta_un_ciclo_de_anios_roto(self):
        chino = next(s for s in self.datos["signos"] if s["tipo"] == "chino")
        chino["anios"] = [1996, 2009]
        self.assertTrue(any("ciclo" in e for e in generar.validar(self.datos)))

    def test_detecta_una_figura_vacia(self):
        self.datos["signos"][0]["figura"] = []
        self.assertTrue(any("figura" in e for e in generar.validar(self.datos)))

    def test_detecta_un_termino_sin_traducir(self):
        del self.datos["terminos"]["fuego"]["ja"]
        self.assertTrue(any("terminos/fuego" in e for e in generar.validar(self.datos)))

    def test_detecta_ids_repetidos(self):
        self.datos["signos"][1]["id"] = self.datos["signos"][0]["id"]
        self.assertTrue(any("repetidos" in e for e in generar.validar(self.datos)))


class PruebasDeSalidas(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.datos = cargar_datos()
        cls.archivos = generar.esperados(cls.datos)

    def test_hay_un_svg_por_signo(self):
        svgs = {p.stem for p in self.archivos if p.suffix == ".svg"}
        self.assertEqual(svgs, {s["id"] for s in self.datos["signos"]})

    def test_hay_una_ficha_por_idioma_y_un_indice(self):
        fichas = {p.stem for p in self.archivos if p.suffix == ".md"}
        self.assertEqual(fichas, set(generar.IDIOMAS) | {"README"})

    def test_cada_svg_es_xml_valido_con_titulo_y_descripcion(self):
        for ruta, texto in self.archivos.items():
            if ruta.suffix != ".svg":
                continue
            raiz = ElementTree.fromstring(texto)
            self.assertEqual(raiz.tag, SVG_NS + "svg", ruta.name)
            self.assertIsNotNone(raiz.find(SVG_NS + "title"), ruta.name)
            self.assertIsNotNone(raiz.find(SVG_NS + "desc"), ruta.name)

    def test_ningun_svg_trae_scripts_ni_enlaces_externos(self):
        for ruta, texto in self.archivos.items():
            if ruta.suffix != ".svg":
                continue
            self.assertNotRegex(texto, r"<script|href=|xlink:|javascript:|<image", ruta.name)

    def test_los_ids_internos_de_cada_svg_son_unicos_entre_svg(self):
        # Si dos SVG se incrustan en la misma página, sus ids no pueden chocar.
        vistos = {}
        for ruta, texto in self.archivos.items():
            if ruta.suffix != ".svg":
                continue
            for ident in re.findall(r'\sid="([^"]+)"', texto):
                self.assertNotIn(ident, vistos, f"{ident} en {ruta.name} y {vistos.get(ident)}")
                vistos[ident] = ruta.name

    def test_cada_svg_dibuja_una_estrella_por_punto_de_los_datos(self):
        por_id = {s["id"]: s for s in self.datos["signos"]}
        for ruta, texto in self.archivos.items():
            if ruta.suffix != ".svg":
                continue
            raiz = ElementTree.fromstring(texto)
            grupo = [g for g in raiz.iter(SVG_NS + "g") if g.get("filter")][0]
            self.assertEqual(len(list(grupo)), len(por_id[ruta.stem]["constelacion"]["estrellas"]), ruta.name)

    def test_los_trazos_de_la_figura_solo_usan_comandos_de_trazado(self):
        permitido = re.compile(r"^[MmLlHhVvCcSsQqTtAaZz0-9eE\s,.\-+]+$")
        for s in self.datos["signos"]:
            for d in s["figura"]:
                self.assertRegex(d, permitido, f"{s['id']}: {d[:40]}")

    def test_la_ficha_de_cada_idioma_nombra_los_veinticuatro_signos(self):
        for ruta, texto in self.archivos.items():
            if ruta.suffix != ".md" or ruta.stem == "README":
                continue
            for s in self.datos["signos"]:
                self.assertIn(f"### {s['nombres'][ruta.stem]}", texto, f"{s['id']} en {ruta.name}")

    def test_los_archivos_committeados_estan_al_dia(self):
        resultado = subprocess.run(
            [sys.executable, str(generar.RAIZ / "generar.py"), "--comprobar"],
            capture_output=True, text=True, cwd=generar.RAIZ,
        )
        self.assertEqual(resultado.returncode, 0, resultado.stdout + resultado.stderr)


if __name__ == "__main__":
    unittest.main()
