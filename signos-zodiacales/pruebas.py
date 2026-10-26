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

    def test_version_y_revision(self):
        self.assertRegex(zodiaco.version(), r"^\d+\.\d+\.\d+$")
        self.assertEqual(zodiaco.revision("es")["estado"], "sin_revisar")
        with self.assertRaises(ValueError):
            zodiaco.revision("xx")


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


    def test_detecta_una_version_mal_escrita(self):
        for mala in ("1.1", "v1.1.0", "", "1.1.0-beta"):
            self.datos["version"] = mala
            self.assertTrue(self.errores_con("version:"), mala)

    def test_detecta_un_idioma_sin_estado_de_revision(self):
        del self.datos["revision"]["sw"]
        self.assertTrue(self.errores_con("revision: no tiene exactamente"))

    def test_detecta_un_estado_de_revision_desconocido(self):
        self.datos["revision"]["es"]["estado"] = "casi_revisado"
        self.assertTrue(self.errores_con("revision/es"))

    def test_un_idioma_revisado_necesita_quien_y_cuando(self):
        self.datos["revision"]["es"] = {"estado": "revisado"}
        self.assertTrue(self.errores_con("revision/es"))
        self.datos["revision"]["es"] = {"estado": "revisado", "por": "Ana", "fecha": "2026-13-01"}
        self.assertTrue(self.errores_con("revision/es"))
        self.datos["revision"]["es"] = {"estado": "revisado", "por": "  ", "fecha": "2026-10-26"}
        self.assertTrue(self.errores_con("revision/es"))

    def test_un_idioma_revisado_completo_es_valido(self):
        self.datos["revision"]["es"] = {"estado": "revisado", "por": "Ana", "fecha": "2026-10-26"}
        self.assertEqual(generar.validar(self.datos), [])
        self.assertIn("Revisada por Ana (2026-10-26)", generar.indice_de(self.datos))
        self.assertIn("Traducción revisada por Ana el 2026-10-26.", generar.ficha_de(self.datos, "es"))

    def test_sin_revisar_no_lleva_mas_campos(self):
        self.datos["revision"]["es"] = {"estado": "sin_revisar", "por": "Ana"}
        self.assertTrue(self.errores_con("revision/es"))


class PruebasDelChangelog(unittest.TestCase):
    def test_el_changelog_real_coincide_con_la_version_de_los_datos(self):
        self.assertEqual(generar.validar_changelog(generar.CHANGELOG.read_text(encoding="utf-8"), zodiaco.version()), [])

    def test_detecta_una_version_que_no_esta_arriba(self):
        texto = "## [1.0.0] - 2026-10-24\n\n## [0.9.0] - 2026-10-01\n"
        self.assertTrue(generar.validar_changelog(texto, "1.1.0"))

    def test_detecta_versiones_desordenadas_o_repetidas(self):
        self.assertTrue(generar.validar_changelog("## [1.0.0] - 2026-10-24\n## [1.1.0] - 2026-10-26\n", "1.0.0"))
        self.assertTrue(generar.validar_changelog("## [1.0.0] - 2026-10-24\n## [1.0.0] - 2026-10-20\n", "1.0.0"))

    def test_detecta_un_changelog_vacio_o_con_fecha_imposible(self):
        self.assertTrue(generar.validar_changelog("# Historial\n", "1.0.0"))
        self.assertTrue(generar.validar_changelog("## [1.0.0] - 2026-02-30\n", "1.0.0"))

    def test_un_changelog_bien_hecho_pasa(self):
        self.assertEqual(generar.validar_changelog("## [1.1.0] - 2026-10-26\n## [1.0.0] - 2026-10-24\n", "1.1.0"), [])


class PruebasDeSalidas(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.datos = cargar_datos()
        cls.archivos = generar.esperados(cls.datos)
        cls.svgs = {ruta: texto for ruta, texto in cls.archivos.items() if ruta.suffix == ".svg"}

    def test_hay_un_svg_por_signo_y_por_variante_de_idioma(self):
        ids = {ident for s in self.datos["signos"] for ident, _ in generar.ilustraciones(s)}
        self.assertIn("conejo-vi", ids)  # el Gato (Mèo) que el vietnamita pone en el lugar del Conejo
        for carpeta in (generar.SALIDA_SVG, generar.SALIDA_SVG_SIN_FONDO):
            self.assertEqual({p.stem for p in self.svgs if p.parent == carpeta}, ids, carpeta.name)

    def test_los_svg_con_fondo_son_ligeros(self):
        # El fondo de estrellas va en pocos trazos de puntos; un <circle> por estrella lo duplicaría.
        for ruta, texto in self.svgs.items():
            if ruta.parent == generar.SALIDA_SVG:
                self.assertLess(len(texto.encode("utf-8")), 4500, ruta.name)

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
        por_id = {ident: d for s in self.datos["signos"] for ident, d in generar.ilustraciones(s)}
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


class PruebasDeAnioNuevo(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        with zodiaco.ANIO_NUEVO.open(encoding="utf-8") as f:
            cls.crudas = json.load(f)["fechas"]

    def test_la_tabla_pasa_la_validacion_y_cubre_1900_a_2099(self):
        self.assertEqual(generar.validar_anio_nuevo(self.crudas), [])
        self.assertEqual((min(map(int, self.crudas)), max(map(int, self.crudas))), (1900, 2099))

    def test_fechas_conocidas_del_anio_nuevo(self):
        casos = {1900: "1900-01-31", 1985: "1985-02-20", 2000: "2000-02-05", 2024: "2024-02-10",
                 2025: "2025-01-29", 2026: "2026-02-17", 2034: "2034-02-19"}
        for anio, fecha in casos.items():
            self.assertEqual(self.crudas[str(anio)], fecha, anio)

    def test_animal_de_una_fecha_cambia_el_dia_del_anio_nuevo(self):
        casos = {(2026, 2, 16): ("serpiente", 2025), (2026, 2, 17): ("caballo", 2026),
                 (2024, 2, 9): ("conejo", 2023), (2024, 2, 10): ("dragon", 2024),
                 (1985, 2, 19): ("rata", 1984), (1985, 2, 20): ("buey", 1985),
                 (1900, 1, 1): ("cerdo", 1899), (2099, 12, 31): ("cabra", 2099)}
        for fecha, (animal, anio_lunar) in casos.items():
            r = zodiaco.animal_chino_de_fecha(datetime.date(*fecha))
            self.assertEqual((r["signo"]["id"], r["anio_lunar"]), (animal, anio_lunar), fecha)

    def test_despues_de_febrero_coincide_con_animal_chino_del_mismo_anio(self):
        for anio in range(1900, 2100):
            self.assertEqual(zodiaco.animal_chino_de_fecha(datetime.date(anio, 3, 1))["signo"]["id"],
                             zodiaco.animal_chino(anio)["signo"]["id"], anio)

    def test_animal_de_fecha_rechaza_entradas_no_validas(self):
        with self.assertRaises(ValueError):
            zodiaco.animal_chino_de_fecha(datetime.date(2100, 3, 1))
        with self.assertRaises(TypeError):
            zodiaco.animal_chino_de_fecha("2026-02-17")

    def test_la_validacion_detecta_una_fecha_fuera_de_la_ventana(self):
        malas = dict(self.crudas, **{"2024": "2024-03-10"})
        self.assertTrue(any("2024" in e for e in generar.validar_anio_nuevo(malas)))

    def test_la_validacion_detecta_un_anio_que_falta(self):
        malas = {k: v for k, v in self.crudas.items() if k != "1950"}
        self.assertTrue(any("faltan" in e for e in generar.validar_anio_nuevo(malas)))


class PruebasDeCuspide(unittest.TestCase):
    def test_un_dia_del_cambio_es_cuspide(self):
        for fecha, par in {(2024, 4, 19): ("aries", "tauro"), (2024, 4, 20): ("aries", "tauro"),
                           (2024, 12, 22): ("sagitario", "capricornio"), (2025, 1, 19): ("capricornio", "acuario")}.items():
            self.assertEqual(tuple(s["id"] for s in zodiaco.cuspide(datetime.date(*fecha))), par, fecha)

    def test_lejos_del_cambio_no_es_cuspide(self):
        self.assertIsNone(zodiaco.cuspide(datetime.date(2024, 4, 5)))
        self.assertFalse(zodiaco.es_cuspide(datetime.date(2024, 4, 22)))

    def test_el_margen_amplia_la_cuspide(self):
        self.assertFalse(zodiaco.es_cuspide(datetime.date(2024, 4, 22)))
        self.assertTrue(zodiaco.es_cuspide(datetime.date(2024, 4, 22), margen=3))

    def test_el_sideral_tiene_sus_propias_cuspides(self):
        self.assertTrue(zodiaco.es_cuspide(datetime.date(2024, 4, 14), sistema="sideral"))
        self.assertFalse(zodiaco.es_cuspide(datetime.date(2024, 4, 14)))

    def test_cuspide_rechaza_un_margen_no_valido(self):
        for malo in (-1, 1.5, True):
            with self.assertRaises(ValueError):
                zodiaco.cuspide(datetime.date(2024, 4, 19), margen=malo)


class PruebasDeIdiomas(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.datos = cargar_datos()
        cls.archivos = generar.esperados(cls.datos)

    def test_los_24_nombres_son_distintos_en_cada_idioma(self):
        # Si Tauro y el Buey se llaman igual, quien lee no sabe de cuál se habla.
        for codigo in generar.IDIOMAS:
            nombres = [s["nombres"][codigo] for s in self.datos["signos"]]
            repetidos = sorted({n for n in nombres if nombres.count(n) > 1})
            self.assertEqual(repetidos, [], codigo)

    def test_las_fichas_de_derecha_a_izquierda_van_en_un_div_rtl(self):
        for codigo in generar.IDIOMAS:
            ficha = self.archivos[generar.SALIDA_MD / f"{codigo}.md"]
            if codigo in generar.DERECHA_A_IZQUIERDA:
                self.assertIn(f'<div dir="rtl" lang="{codigo}">\n\n', ficha, codigo)
                self.assertTrue(ficha.rstrip().endswith("</div>"), codigo)
            else:
                self.assertNotIn('dir="rtl"', ficha, codigo)

    def test_la_validacion_detecta_una_variante_para_un_idioma_desconocido(self):
        datos = copy.deepcopy(self.datos)
        conejo = next(s for s in datos["signos"] if s["id"] == "conejo")
        conejo["variantes"]["xx"] = conejo["variantes"]["vi"]
        self.assertTrue(any("idioma desconocido" in e for e in generar.validar(datos)))

    def test_la_ficha_vietnamita_y_la_galeria_usan_el_gato(self):
        conejo = next(s for s in self.datos["signos"] if s["id"] == "conejo")
        self.assertEqual(generar.archivo_svg(conejo, "vi"), "conejo-vi")
        self.assertEqual(generar.archivo_svg(conejo, "es"), "conejo")


class PruebasDeGaleria(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.datos = cargar_datos()
        cls.html = generar.esperados(cls.datos)[generar.GALERIA]
        cls.contenido = json.loads(re.search(r"const DATOS = (.*);\n", cls.html).group(1).replace("<\\/", "</"))

    def test_trae_los_30_idiomas_y_los_24_signos(self):
        self.assertEqual([c for c, _ in self.contenido["idiomas"]], list(generar.IDIOMAS))
        self.assertEqual([s["id"] for s in self.contenido["signos"]], [s["id"] for s in self.datos["signos"]])

    def test_cada_imagen_que_pide_existe(self):
        for s in self.contenido["signos"]:
            for codigo in generar.IDIOMAS:
                nombre = s["archivos"].get(codigo, s["id"])
                for carpeta in (generar.SALIDA_SVG, generar.SALIDA_SVG_SIN_FONDO):
                    self.assertTrue((carpeta / f"{nombre}.svg").exists(), f"{carpeta.name}/{nombre}.svg")

    def test_no_carga_nada_de_fuera(self):
        self.assertNotRegex(self.html, r"https?://|<link\b|src=\"http")

    def test_los_datos_no_cierran_el_script_antes_de_tiempo(self):
        bloque = self.html.split("<script>", 1)[1].rsplit("</script>", 1)[0]
        self.assertNotIn("</", bloque.replace("<\\/", ""))



class PruebasDeHusoHorario(unittest.TestCase):
    utc = datetime.timezone.utc

    def test_un_datetime_con_huso_se_pasa_a_la_hora_de_china(self):
        # Las 20:00 UTC del 16/02/2026 son las 04:00 del 17/02 en China: ya es el año del Caballo.
        r = zodiaco.animal_chino_de_fecha(datetime.datetime(2026, 2, 16, 20, 0, tzinfo=self.utc))
        self.assertEqual(r["signo"]["id"], "caballo")

    def test_antes_de_la_medianoche_de_china_sigue_el_anio_anterior(self):
        r = zodiaco.animal_chino_de_fecha(datetime.datetime(2026, 2, 16, 15, 59, tzinfo=self.utc))
        self.assertEqual(r["signo"]["id"], "serpiente")

    def test_un_huso_al_oeste_tambien_se_convierte(self):
        nueva_york = datetime.timezone(datetime.timedelta(hours=-5))
        r = zodiaco.animal_chino_de_fecha(datetime.datetime(2026, 2, 16, 12, 0, tzinfo=nueva_york))
        self.assertEqual(r["signo"]["id"], "caballo")  # 17:00 UTC = 01:00 del 17/02 en China

    def test_sin_huso_se_toma_tal_cual(self):
        self.assertEqual(zodiaco.animal_chino_de_fecha(datetime.datetime(2026, 2, 16, 23, 59))["signo"]["id"], "serpiente")
        self.assertEqual(zodiaco.animal_chino_de_fecha(datetime.date(2026, 2, 16))["signo"]["id"], "serpiente")


class PruebasDeNombresDelCiclo(unittest.TestCase):
    def test_nombres_conocidos(self):
        casos = {
            (2026, "es"): "Caballo de Fuego", (2026, "en"): "Fire Horse", (2024, "de"): "Holz-Drache",
            (2021, "de"): "Metall-Ochse", (2009, "de"): "Erd-Ochse", (2032, "fr"): "Rat d'Eau",
            (2032, "it"): "Topo d'Acqua", (2024, "zh"): "木龙", (2026, "fi"): "Tulihevonen",
            (2026, "id"): "Kuda Api", (2026, "ro"): "Cal de Foc", (2026, "pt"): "Cavalo de Fogo",
        }
        for (anio, idioma), esperado in casos.items():
            self.assertEqual(zodiaco.nombre_del_anio(anio, idioma), esperado, (anio, idioma))

    def test_los_idiomas_sin_forma_fija_usan_la_generica(self):
        datos = zodiaco.cargar_datos()
        for codigo, regla in datos["nombres_del_ciclo"].items():
            if not regla["idiomatica"]:
                self.assertEqual(regla["plantilla"], "{animal} ({elemento})", codigo)
        self.assertEqual(zodiaco.nombre_del_anio(2026, "ru"), "Лошадь (Огонь)")

    def test_los_60_nombres_son_distintos_en_cada_idioma(self):
        for codigo in generar.IDIOMAS:
            nombres = {zodiaco.nombre_del_anio(a, codigo) for a in range(1984, 2044)}
            self.assertEqual(len(nombres), 60, codigo)

    def test_rechaza_un_idioma_desconocido(self):
        with self.assertRaises(ValueError):
            zodiaco.nombre_del_anio(2026, "xx")

    def test_la_validacion_detecta_una_plantilla_rota(self):
        datos = copy.deepcopy(cargar_datos())
        datos["nombres_del_ciclo"]["es"]["plantilla"] = "{animal}"
        self.assertTrue(any("nombres_del_ciclo/es" in e for e in generar.validar(datos)))


class PruebasDeCompatibilidad(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        datos = cargar_datos()
        cls.occidentales = [s["id"] for s in datos["signos"] if s["tipo"] == "occidental"]
        cls.chinos = [s["id"] for s in datos["signos"] if s["tipo"] == "chino"]

    def test_es_simetrica(self):
        for grupo in (self.occidentales, self.chinos):
            for a in grupo:
                for b in grupo:
                    self.assertEqual(zodiaco.compatibilidad(a, b), zodiaco.compatibilidad(b, a), (a, b))

    def test_parejas_occidentales_conocidas(self):
        casos = {("aries", "leo"): "alta", ("aries", "geminis"): "alta", ("aries", "libra"): "media",
                 ("aries", "cancer"): "baja", ("aries", "capricornio"): "baja", ("aries", "tauro"): "neutra",
                 ("tauro", "virgo"): "alta", ("tauro", "escorpio"): "media"}
        for (a, b), afinidad in casos.items():
            self.assertEqual(zodiaco.compatibilidad(a, b)["afinidad"], afinidad, (a, b))

    def test_cada_animal_tiene_dos_del_trino_un_amigo_secreto_y_un_choque(self):
        for a in self.chinos:
            otros = [zodiaco.compatibilidad(a, b)["relaciones"] for b in self.chinos if b != a]
            self.assertEqual(sum("trino" in r for r in otros), 2, a)
            self.assertEqual(sum("amigos_secretos" in r for r in otros), 1, a)
            self.assertEqual(sum("choque" in r for r in otros), 1, a)

    def test_parejas_chinas_conocidas(self):
        self.assertIn("trino", zodiaco.compatibilidad("tigre", "perro")["relaciones"])
        self.assertIn("amigos_secretos", zodiaco.compatibilidad("caballo", "cabra")["relaciones"])
        self.assertIn("choque", zodiaco.compatibilidad("serpiente", "cerdo")["relaciones"])

    def test_rechaza_mezclar_zodiacos_o_ids_desconocidos(self):
        with self.assertRaises(ValueError):
            zodiaco.compatibilidad("aries", "rata")
        with self.assertRaises(ValueError):
            zodiaco.compatibilidad("aries", "unicornio")


class PruebasDeDecanatos(unittest.TestCase):
    def test_los_regentes_siguen_el_orden_caldeo(self):
        esperados = {"aries": ["marte", "sol", "venus"], "tauro": ["mercurio", "luna", "saturno"],
                     "capricornio": ["jupiter", "marte", "sol"], "piscis": ["saturno", "jupiter", "marte"]}
        for signo, regentes in esperados.items():
            self.assertEqual(zodiaco.regentes_de_decanatos(signo), regentes, signo)

    def test_decanatos_en_fechas_conocidas(self):
        casos = {(1990, 3, 21): (1, "aries"), (1990, 3, 30): (1, "aries"), (1990, 3, 31): (2, "leo"),
                 (1990, 4, 9): (2, "leo"), (1990, 4, 10): (3, "sagitario"), (1990, 4, 19): (3, "sagitario")}
        for fecha, (numero, regente_por_triplicidad) in casos.items():
            d = zodiaco.decanato(datetime.date(*fecha))
            self.assertEqual((d["signo"]["id"], d["numero"], d["signo_regente"]["id"]),
                             ("aries", numero, regente_por_triplicidad), fecha)

    def test_capricornio_cruza_el_fin_de_anio(self):
        self.assertEqual(zodiaco.decanato(datetime.date(2025, 12, 31))["numero"], 1)
        self.assertEqual(zodiaco.decanato(datetime.date(2026, 1, 1))["numero"], 2)
        self.assertEqual(zodiaco.decanato(datetime.date(2026, 1, 19))["numero"], 3)

    def test_cada_dia_del_anio_tiene_un_decanato_del_1_al_3(self):
        dia = datetime.date(2024, 1, 1)
        while dia.year == 2024:
            for sistema in zodiaco.SISTEMAS:
                self.assertIn(zodiaco.decanato(dia, sistema)["numero"], (1, 2, 3), (dia, sistema))
            dia += datetime.timedelta(days=1)

    def test_los_tramos_cubren_el_signo_sin_huecos(self):
        for s in cargar_datos()["signos"]:
            if s["tipo"] != "occidental":
                continue
            tramos = zodiaco.tramos_de_decanatos(s)
            self.assertEqual(tramos[0][0], s["tropical"]["desde"], s["id"])
            self.assertEqual(tramos[-1][1], s["tropical"]["hasta"], s["id"])


class PruebasDeTextosTraducidos(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.datos = cargar_datos()
        cls.archivos = generar.esperados(cls.datos)

    def test_las_secciones_de_cada_ficha_estan_en_su_idioma(self):
        t = self.datos["terminos"]
        for codigo in generar.IDIOMAS:
            ficha = self.archivos[generar.SALIDA_MD / f"{codigo}.md"]
            secciones = [l[3:] for l in ficha.splitlines() if l.startswith("## ")]
            self.assertEqual(secciones, [t["zodiaco_occidental"][codigo], t["horoscopo_chino"][codigo]], codigo)

    def test_cada_signo_occidental_trae_sus_decanatos_en_la_ficha(self):
        ficha = self.archivos[generar.SALIDA_MD / "es.md"]
        self.assertIn("Decanatos: 21/03 – 30/03 Marte · 31/03 – 09/04 Sol · 10/04 – 19/04 Venus", ficha)
        self.assertEqual(ficha.count("Decanatos: "), 12)

    def test_la_galeria_trae_la_interfaz_en_los_30_idiomas(self):
        html = self.archivos[generar.GALERIA]
        contenido = json.loads(re.search(r"const DATOS = (.*);\n", html).group(1).replace("<\\/", "</"))
        self.assertEqual(set(contenido["ui"]), set(generar.IDIOMAS))
        for codigo, ui in contenido["ui"].items():
            self.assertEqual(set(ui), generar.TERMINOS_DE_INTERFAZ, codigo)

    def test_la_validacion_detecta_un_termino_de_interfaz_sin_traducir(self):
        datos = copy.deepcopy(self.datos)
        del datos["terminos"]["sin_fondo"]["ko"]
        self.assertTrue(any("terminos/sin_fondo" in e for e in generar.validar(datos)))


    def test_cada_ficha_dice_si_su_traduccion_esta_revisada(self):
        for codigo in generar.IDIOMAS:
            self.assertIn("> Traducción sin revisar por una persona nativa.", self.archivos[generar.SALIDA_MD / f"{codigo}.md"], codigo)
        indice = self.archivos[generar.SALIDA_MD / "README.md"]
        self.assertEqual(indice.count("| Sin revisar |"), len(generar.IDIOMAS))
        self.assertIn(f"Versión de los datos: {zodiaco.version()}", indice)


class PruebasDeVectores(unittest.TestCase):
    """datos/vectores.json es lo que comparten Python y los ports: tiene que estar completo y sus errores, fallar."""

    @classmethod
    def setUpClass(cls):
        cls.datos = cargar_datos()
        cls.vectores = json.loads(generar.vectores_de(cls.datos))

    def test_estan_todas_las_consultas_con_el_tamano_esperado(self):
        v = self.vectores
        self.assertEqual(v["version"], zodiaco.version())
        self.assertEqual({k: len(x) for k, x in v["signo_occidental"].items()}, {"tropical": 366, "sideral": 366})
        self.assertEqual(len(v["cuspide"]), 366)
        self.assertEqual(len(v["animal_chino"]), 200)
        self.assertEqual(len(v["animal_chino_de_fecha"]), 400)
        self.assertEqual(set(v["nombre_del_anio"]), set(generar.IDIOMAS))
        self.assertEqual(len(v["compatibilidad"]), 2 * 12 * 12)
        self.assertEqual({k: len(x) for k, x in v["decanato"].items()}, {"tropical": 366, "sideral": 366})
        self.assertEqual(set(v["revision"]), set(generar.IDIOMAS))

    def test_el_archivo_committeado_es_el_que_genera_generar_py(self):
        self.assertEqual(generar.VECTORES.read_text(encoding="utf-8"), generar.vectores_de(self.datos))

    def test_cada_caso_de_error_lanza_excepcion_en_python(self):
        con_fecha = {"signo_occidental", "cuspide", "animal_chino_de_fecha"}
        for caso in self.vectores["errores"]:
            args = list(caso["args"])
            if caso["funcion"] in con_fecha:
                args[0] = datetime.date.fromisoformat(args[0])
            with self.subTest(caso=caso), self.assertRaises((TypeError, ValueError, LookupError)):
                getattr(zodiaco, caso["funcion"])(*args)

    def test_los_instantes_con_huso_dan_lo_mismo_que_en_la_hora_de_china(self):
        for texto, anio_lunar, _ in self.vectores["animal_chino_de_instante"]:
            instante = datetime.datetime.fromisoformat(texto)
            en_china = instante.astimezone(zodiaco.HORA_DE_CHINA).replace(tzinfo=None)
            self.assertEqual(zodiaco.animal_chino_de_fecha(en_china)["anio_lunar"], anio_lunar, texto)

    def test_hay_un_instante_que_cambia_de_dia_al_pasar_a_china(self):
        # Sin la conversión, 16/02/2026 20:00 UTC sería Serpiente; en China ya es el 17 y es Caballo.
        sin_convertir = zodiaco.animal_chino_de_fecha(datetime.date(2026, 2, 16))["anio_lunar"]
        convertido = zodiaco.animal_chino_de_fecha(datetime.datetime(2026, 2, 16, 20, tzinfo=datetime.timezone.utc))["anio_lunar"]
        self.assertEqual((sin_convertir, convertido), (2025, 2026))


if __name__ == "__main__":
    unittest.main()
