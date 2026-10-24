"""Pruebas de signos-zodiacales: coherencia de los datos, las consultas de zodiaco.py y lo que genera generar.py.

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
import zodiaco

SVG_NS = "{http://www.w3.org/2000/svg}"

# Rango de caracteres que tiene que aparecer en los textos de cada idioma de otro alfabeto.
ALFABETOS = {
    "ru": r"[Ѐ-ӿ]", "uk": r"[Ѐ-ӿ]", "el": r"[Ͱ-Ͽἀ-῿]",
    "ar": r"[؀-ۿ]", "fa": r"[؀-ۿ]", "ur": r"[؀-ۿ]",
    "he": r"[֐-׿]", "hi": r"[ऀ-ॿ]", "bn": r"[ঀ-৿]",
    "th": r"[฀-๿]", "ko": r"[가-힯]", "ja": r"[぀-ヿ一-鿿]",
    "zh": r"[一-鿿]",
}
# Lo que no puede aparecer en un idioma de alfabeto latino (texto pegado en la columna equivocada).
NO_LATINO = r"[Ͱ-ϿЀ-ӿ֐-׿؀-ۿऀ-৿฀-๿぀-ヿ一-鿿가-힯]"


def cargar_datos():
    with generar.DATOS.open(encoding="utf-8") as f:
        return json.load(f)


def textos_de(datos):
    """(dónde, idioma, texto) de cada texto traducido: nombres, descripciones y términos."""
    for s in datos["signos"]:
        for campo in ("nombres", "descripciones", "descripciones_largas"):
            for codigo, texto in s[campo].items():
                yield f"{s['id']}/{campo}", codigo, texto
    for clave, por_idioma in datos["terminos"].items():
        for codigo, texto in por_idioma.items():
            yield f"terminos/{clave}", codigo, texto


def dias_del_anio_bisiesto():
    dia = datetime.date(2024, 1, 1)
    while dia.year == 2024:
        yield dia
        dia += datetime.timedelta(days=1)


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

    def test_los_dos_zodiacos_cubren_el_anio_sin_huecos_ni_solapes(self):
        # Se recorre 2024, que es bisiesto: el 29 de febrero también tiene que caer en un signo.
        for sistema in zodiaco.SISTEMAS:
            for dia in dias_del_anio_bisiesto():
                clave = (dia.month, dia.day)
                donde = [s["id"] for s in self.occidentales
                         if zodiaco._en_rango(clave, s[sistema]["desde"], s[sistema]["hasta"])]
                self.assertEqual(len(donde), 1, f"{sistema} {dia:%d/%m}: {donde or 'ningún signo'}")

    def test_el_sideral_va_unos_veinticuatro_dias_detras_del_tropical(self):
        for s in self.occidentales:
            tropical = datetime.date(2024, *s["tropical"]["desde"])
            sideral = datetime.date(2024, *s["sideral"]["desde"])
            if sideral < tropical:  # Capricornio sideral empieza en enero del año siguiente
                sideral = sideral.replace(year=2025)
            self.assertTrue(20 <= (sideral - tropical).days <= 27, s["id"])

    def test_los_simbolos_son_los_doce_del_zodiaco_en_orden(self):
        self.assertEqual("".join(s["simbolo"] for s in self.occidentales), "♈♉♊♋♌♍♎♏♐♑♒♓")

    def test_las_ramas_terrestres_van_en_orden(self):
        self.assertEqual("".join(s["rama"]["hanzi"] for s in self.chinos), "子丑寅卯辰巳午未申酉戌亥")

    def test_los_troncos_celestes_van_en_orden(self):
        self.assertEqual("".join(t["hanzi"] for t in self.datos["troncos"]), "甲乙丙丁戊己庚辛壬癸")

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

    def test_los_textos_largos_son_mas_largos_que_los_cortos(self):
        for s in self.signos:
            for codigo in generar.IDIOMAS:
                self.assertGreater(len(s["descripciones_largas"][codigo]), len(s["descripciones"][codigo]),
                                   f"{s['id']}/{codigo}")

    def test_cada_texto_esta_en_el_alfabeto_de_su_idioma(self):
        for donde, codigo, texto in textos_de(self.datos):
            if codigo in ALFABETOS:
                self.assertRegex(texto, ALFABETOS[codigo], f"{donde}/{codigo}: {texto[:40]}")
            else:
                self.assertNotRegex(texto, NO_LATINO, f"{donde}/{codigo}: {texto[:40]}")
            if codigo == "zh":
                self.assertNotRegex(texto, r"[぀-ヿ]", f"{donde}/zh tiene kana: {texto[:40]}")

    def test_las_descripciones_en_espanol_no_describen_a_la_persona_con_adjetivos(self):
        # Rasgos como sustantivos, sin «valiente, impulsivo» que marque el género de quien lee.
        # Son los adjetivos con género que usaban las descripciones antes de pasarlas a sustantivos.
        # Quedan fuera los que acompañan a un sustantivo (espíritu competitivo, talento artístico).
        con_genero = re.compile(
            r"\b(impulsiv|comunicativ|protector|generos|carismátic|analític|detallista|diplomátic|intens|"
            r"intuitiv|aventurer|disciplinad|ambicios|humanitari|empátic|soñador|astut|ingenios|trabajador|"
            r"apasionad|enérgic|afortunad|sabi|reservad|divertid|organizad|honest|observador|"
            r"just|sincer|afectuos|creativ|lo describe|la describe)(o|a|os|as|e|es)?\b",
            re.IGNORECASE,
        )
        for s in self.signos:
            for campo in ("descripciones", "descripciones_largas"):
                texto = s[campo]["es"]
                self.assertIsNone(con_genero.search(texto), f"{s['id']}/{campo}: {texto}")
            self.assertTrue(s["descripciones_largas"]["es"].startswith("Signo "), s["id"])


class PruebasDeConsultas(unittest.TestCase):
    def test_signo_tropical_en_fechas_conocidas(self):
        casos = {
            (2024, 3, 21): "aries", (2024, 4, 19): "aries", (2024, 4, 20): "tauro",
            (2024, 2, 29): "piscis", (2024, 12, 22): "capricornio", (2025, 1, 19): "capricornio",
            (2025, 1, 20): "acuario", (2024, 8, 1): "leo",
        }
        for fecha, esperado in casos.items():
            self.assertEqual(zodiaco.signo_occidental(datetime.date(*fecha))["id"], esperado, fecha)

    def test_signo_sideral_en_fechas_conocidas(self):
        casos = {(2024, 4, 14): "aries", (2024, 4, 13): "piscis", (2024, 1, 13): "sagitario",
                 (2024, 1, 14): "capricornio", (2024, 3, 21): "piscis"}
        for fecha, esperado in casos.items():
            self.assertEqual(zodiaco.signo_occidental(datetime.date(*fecha), sistema="sideral")["id"], esperado, fecha)

    def test_signo_acepta_datetime(self):
        self.assertEqual(zodiaco.signo_occidental(datetime.datetime(2024, 7, 30, 23, 59))["id"], "leo")

    def test_signo_rechaza_entradas_no_validas(self):
        with self.assertRaises(TypeError):
            zodiaco.signo_occidental("2024-03-21")
        with self.assertRaises(ValueError):
            zodiaco.signo_occidental(datetime.date(2024, 3, 21), sistema="vedico")

    def test_animal_y_elemento_del_anio_en_anios_conocidos(self):
        casos = {
            1900: ("rata", "metal", "庚子"), 1984: ("rata", "madera", "甲子"), 2020: ("rata", "metal", "庚子"),
            2024: ("dragon", "madera", "甲辰"), 2025: ("serpiente", "madera", "乙巳"),
            2026: ("caballo", "fuego", "丙午"), 1966: ("caballo", "fuego", "丙午"),
        }
        for anio, (animal, elemento, nombre) in casos.items():
            r = zodiaco.animal_chino(anio)
            self.assertEqual(r["signo"]["id"], animal, anio)
            self.assertEqual(r["elemento_del_anio"], elemento, anio)
            self.assertEqual(r["tronco"]["hanzi"] + r["signo"]["rama"]["hanzi"], nombre, anio)

    def test_el_ciclo_se_repite_cada_sesenta_anios_y_sirve_antes_del_anio_uno(self):
        for anio in (-500, 4, 1924, 2043, 3000):
            a, b = zodiaco.animal_chino(anio), zodiaco.animal_chino(anio + 60)
            self.assertEqual((a["signo"]["id"], a["tronco"]["hanzi"]), (b["signo"]["id"], b["tronco"]["hanzi"]))

    def test_la_polaridad_del_anio_coincide_con_la_del_animal(self):
        for anio in range(1900, 1960):
            r = zodiaco.animal_chino(anio)
            self.assertEqual(r["polaridad"], r["signo"]["polaridad"], anio)

    def test_animal_rechaza_entradas_no_validas(self):
        for malo in ("2024", 2024.0, True):
            with self.assertRaises(TypeError):
                zodiaco.animal_chino(malo)

    def test_anios_del_animal(self):
        self.assertEqual(zodiaco.anios_del_animal("caballo", 2000, 2030), [2002, 2014, 2026])
        with self.assertRaises(ValueError):
            zodiaco.anios_del_animal("unicornio", 2000, 2030)


class PruebasDeValidacion(unittest.TestCase):
    """validar() tiene que atrapar datos rotos, no solo aprobar los buenos."""

    def setUp(self):
        self.datos = copy.deepcopy(cargar_datos())

    def errores_con(self, texto):
        return [e for e in generar.validar(self.datos) if texto in e]

    def test_detecta_un_idioma_que_falta(self):
        del self.datos["signos"][0]["nombres"]["da"]
        self.assertTrue(self.errores_con("nombres"))

    def test_detecta_un_texto_vacio(self):
        self.datos["signos"][3]["descripciones"]["es"] = "  "
        self.assertTrue(self.errores_con("vacío"))

    def test_detecta_una_fecha_que_no_existe(self):
        self.datos["signos"][0]["tropical"]["desde"] = [2, 31]
        self.assertTrue(self.errores_con("tropical"))

    def test_detecta_una_fecha_en_texto(self):
        self.datos["signos"][0]["sideral"]["hasta"] = "14/05"
        self.assertTrue(self.errores_con("sideral"))

    def test_detecta_un_simbolo_que_falta(self):
        del self.datos["signos"][0]["simbolo"]
        self.assertTrue(self.errores_con("simbolo"))

    def test_detecta_una_rama_sin_hanzi(self):
        chino = next(s for s in self.datos["signos"] if s["tipo"] == "chino")
        chino["rama"] = {"pinyin": "Zi"}
        self.assertTrue(self.errores_con("rama"))

    def test_detecta_troncos_mal_ordenados(self):
        t = self.datos["troncos"]
        t[0], t[2] = t[2], t[0]
        self.assertTrue(self.errores_con("troncos"))

    def test_detecta_una_linea_a_una_estrella_inexistente(self):
        self.datos["signos"][0]["constelacion"]["lineas"].append([0, 99])
        self.assertTrue(self.errores_con("no existe"))

    def test_detecta_una_estrella_fuera_del_cuadro(self):
        self.datos["signos"][0]["constelacion"]["estrellas"][0] = [250, 10]
        self.assertTrue(self.errores_con("fuera del cuadro"))

    def test_detecta_un_elemento_invalido(self):
        self.datos["signos"][0]["elemento"] = "plasma"
        self.assertTrue(self.errores_con("elemento"))

    def test_detecta_una_figura_vacia(self):
        self.datos["signos"][0]["figura"] = []
        self.assertTrue(self.errores_con("figura"))

    def test_detecta_un_termino_sin_traducir(self):
        del self.datos["terminos"]["fuego"]["ja"]
        self.assertTrue(self.errores_con("terminos/fuego"))

    def test_detecta_ids_repetidos(self):
        self.datos["signos"][1]["id"] = self.datos["signos"][0]["id"]
        self.assertTrue(self.errores_con("repetidos"))


class PruebasDeSalidas(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.datos = cargar_datos()
        cls.archivos = generar.esperados(cls.datos)
        cls.svgs = {ruta: texto for ruta, texto in cls.archivos.items() if ruta.suffix == ".svg"}

    def test_hay_un_svg_por_signo_en_cada_variante(self):
        ids = {s["id"] for s in self.datos["signos"]}
        for carpeta in (generar.SALIDA_SVG, generar.SALIDA_SVG_SIN_FONDO):
            self.assertEqual({p.stem for p in self.svgs if p.parent == carpeta}, ids, carpeta.name)

    def test_hay_una_ficha_por_idioma_y_un_indice(self):
        fichas = {p.stem for p in self.archivos if p.suffix == ".md"}
        self.assertEqual(fichas, set(generar.IDIOMAS) | {"README"})

    def test_cada_svg_es_xml_valido(self):
        for ruta, texto in self.svgs.items():
            self.assertEqual(ElementTree.fromstring(texto).tag, SVG_NS + "svg", ruta.name)

    def test_ningun_svg_lleva_texto_en_un_idioma(self):
        # El nombre accesible lo pone quien muestra el SVG, en el idioma de quien lo ve.
        for ruta, texto in self.svgs.items():
            self.assertNotRegex(texto, r"<(text|title|desc)\b", f"{ruta.parent.name}/{ruta.name}")

    def test_ningun_svg_trae_scripts_ni_enlaces_externos(self):
        for ruta, texto in self.svgs.items():
            self.assertNotRegex(texto, r"<script|href=|xlink:|javascript:|<image", ruta.name)

    def test_la_variante_sin_fondo_es_transparente_y_toma_el_color_del_texto(self):
        for ruta, texto in self.svgs.items():
            if ruta.parent != generar.SALIDA_SVG_SIN_FONDO:
                continue
            self.assertNotIn("<rect", texto, ruta.name)
            self.assertNotRegex(texto, r"#[0-9a-fA-F]{3,6}\b", f"{ruta.name}: color fijo")
            self.assertIn("currentColor", texto, ruta.name)

    def test_los_ids_internos_son_unicos_entre_todos_los_svg(self):
        # Si varios SVG se incrustan en la misma página, sus ids no pueden chocar.
        vistos = {}
        for ruta, texto in self.svgs.items():
            for ident in re.findall(r'\sid="([^"]+)"', texto):
                self.assertNotIn(ident, vistos, f"{ident} en {ruta.name} y {vistos.get(ident)}")
                vistos[ident] = ruta.name

    def test_cada_svg_dibuja_una_estrella_por_punto_de_los_datos(self):
        por_id = {s["id"]: s for s in self.datos["signos"]}
        for ruta, texto in self.svgs.items():
            ultimo_grupo = list(ElementTree.fromstring(texto).iter(SVG_NS + "g"))[-1]
            self.assertEqual(len(list(ultimo_grupo)), len(por_id[ruta.stem]["constelacion"]["estrellas"]),
                             f"{ruta.parent.name}/{ruta.name}")

    def test_los_trazos_de_la_figura_solo_usan_comandos_de_trazado(self):
        permitido = re.compile(r"^[MmLlHhVvCcSsQqTtAaZz0-9eE\s,.\-+]+$")
        for s in self.datos["signos"]:
            for d in s["figura"]:
                self.assertRegex(d, permitido, f"{s['id']}: {d[:40]}")

    def test_los_numeros_del_fondo_no_dependen_de_la_version_de_python(self):
        # Valores fijados a mano: si cambian, todos los SVG cambian y --comprobar falla en la CI.
        azar = generar.numeros("aries:fondo")
        self.assertEqual([round(next(azar), 6) for _ in range(3)], PRIMEROS_NUMEROS)

    def test_la_ficha_de_cada_idioma_nombra_los_veinticuatro_signos(self):
        for ruta, texto in self.archivos.items():
            if ruta.suffix != ".md" or ruta.stem == "README":
                continue
            encabezados = [l for l in texto.splitlines() if l.startswith("### ")]
            for s in self.datos["signos"]:
                nombre = s["nombres"][ruta.stem]
                self.assertTrue(any(l.endswith(" " + nombre) for l in encabezados), f"{s['id']} en {ruta.name}")

    def test_las_fichas_hindi_y_bengali_traen_las_fechas_siderales(self):
        aries = next(s for s in self.datos["signos"] if s["id"] == "aries")
        for codigo in generar.SISTEMAS_LOCALES:
            self.assertIn("14/04 – 14/05", generar.rasgos(aries, self.datos["terminos"], codigo))
        self.assertNotIn("14/04", generar.rasgos(aries, self.datos["terminos"], "es"))

    def test_los_archivos_committeados_estan_al_dia(self):
        resultado = subprocess.run(
            [sys.executable, str(generar.RAIZ / "generar.py"), "--comprobar"],
            capture_output=True, text=True, cwd=generar.RAIZ,
        )
        self.assertEqual(resultado.returncode, 0, resultado.stdout + resultado.stderr)


# SHA-256 de "aries:fondo:0", ":1" y ":2", primeros 8 bytes / 2**64 (calculado aparte con sha256sum).
PRIMEROS_NUMEROS = [0.255966, 0.36027, 0.556261]


if __name__ == "__main__":
    unittest.main()
