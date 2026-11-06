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
    esp = zodiaco.cargar_espiritus()
    for grupo in ("terminos", "cualidades"):
        for clave, por_idioma in esp[grupo].items():
            if clave != "separador":
                for codigo, texto in por_idioma.items():
                    yield f"espiritus/{grupo}/{clave}", codigo, texto
    arb = zodiaco.cargar_arboles()
    for grupo in ("terminos", "rasgos"):
        for clave, por_idioma in arb[grupo].items():
            if clave != "separador":
                for codigo, texto in por_idioma.items():
                    yield f"arboles/{grupo}/{clave}", codigo, texto
    for clave, a in arb["arboles"].items():
        for codigo, texto in a["nombres"].items():
            yield f"arboles/arboles/{clave}", codigo, texto
    for clave, por_idioma in zodiaco.cargar_numerologia()["terminos"].items():
        if clave != "separador":
            for codigo, texto in por_idioma.items():
                yield f"numerologia/terminos/{clave}", codigo, texto


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
            esp = zodiaco.cargar_espiritus()["terminos"]
            self.assertEqual(secciones, [t["zodiaco_occidental"][codigo], t["horoscopo_chino"][codigo],
                                         esp["angeles_titulo"][codigo], esp["demonios_titulo"][codigo],
                                         zodiaco.cargar_arboles()["terminos"]["celta_titulo"][codigo],
                                         zodiaco.cargar_arboles()["terminos"]["horoscopo_titulo"][codigo],
                                         zodiaco.cargar_arboles()["terminos"]["nakshatra_titulo"][codigo],
                                         zodiaco.cargar_numerologia()["terminos"]["titulo"][codigo]], codigo)

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
        self.assertEqual(len(v["espiritu_de_fecha"]), 366)
        self.assertEqual(set(v["tramos_de_espiritus"]), {"2024", "2025"})
        self.assertEqual(len(v["cualidades_de"]["es"]), 144)
        self.assertEqual((len(v["arbol_celta"]), len(v["arbol_de_fecha"])), (366, 366))
        self.assertEqual(len(v["nakshatra_de_instante"]), len(generar.INSTANTES_LUNARES))
        self.assertEqual(len(v["rasgos_de"]["es"]), 13 + 21 + 27)
        self.assertEqual(set(v["rasgos_de_sha256"]), set(generar.IDIOMAS))
        self.assertEqual(len(v["camino_de_vida"]), len(generar.FECHAS_NUMEROLOGICAS))
        self.assertEqual({n for _, n in v["camino_de_vida"]}, {1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 22, 33})
        self.assertEqual(len(v["rasgos_de_numero"]["es"]), 12)
        self.assertEqual({len(x) for c, x in v["rasgos_de_numero"].items() if c != "es"}, {3})
        self.assertEqual({len(x) for c, x in v["cualidades_de"].items() if c != "es"}, {len(generar.MUESTRA_DE_CUALIDADES)})

    def test_el_archivo_committeado_es_el_que_genera_generar_py(self):
        self.assertEqual(generar.VECTORES.read_text(encoding="utf-8"), generar.vectores_de(self.datos))

    def test_cada_caso_de_error_lanza_excepcion_en_python(self):
        con_fecha = {"signo_occidental", "cuspide", "animal_chino_de_fecha", "angel_de_fecha", "demonio_de_fecha",
                     "arbol_celta", "arbol_de_fecha", "camino_de_vida", "anio_personal"}
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


class PruebasDeEspiritus(unittest.TestCase):
    """Los 72 ángeles del Shem ha-Mephorash y los 72 demonios de la Goetia."""

    @classmethod
    def setUpClass(cls):
        cls.esp = zodiaco.cargar_espiritus()
        cls.datos = cargar_datos()

    def test_los_datos_pasan_la_validacion(self):
        self.assertEqual(generar.validar_espiritus(self.esp), [])

    def test_son_72_de_cada_uno_con_sus_extremos_conocidos(self):
        a, d = self.esp["angeles"], self.esp["demonios"]
        self.assertEqual((len(a), len(d)), (72, 72))
        self.assertEqual((a[0]["nombre"], a[-1]["nombre"]), ("Vehuiah", "Mumiah"))
        self.assertEqual((d[0]["nombre"], d[-1]["nombre"]), ("Bael", "Andromalius"))
        self.assertEqual(self.esp["demonios"][8]["nombre"], "Paimon")  # el noveno, uno de los nueve reyes

    def test_los_demonios_traen_los_rangos_de_la_tradicion(self):
        rangos = [r for d in self.esp["demonios"] for r in d["rangos"]]
        self.assertEqual(rangos.count("rey"), 9)
        self.assertEqual(rangos.count("caballero"), 1)
        self.assertEqual([d["nombre"] for d in self.esp["demonios"] if "caballero" in d["rangos"]], ["Furcas"])
        self.assertEqual({r for r in rangos}, set(zodiaco.RANGOS))
        self.assertEqual(self.esp["demonios"][0]["legiones"], 66)   # Bael
        self.assertEqual(self.esp["demonios"][8]["legiones"], 200)  # Paimon

    def test_los_ids_no_se_repiten_ni_dentro_ni_entre_las_dos_listas(self):
        ids = [e["id"] for e in self.esp["angeles"] + self.esp["demonios"]]
        self.assertEqual(len(ids), len(set(ids)))

    def test_cada_signo_tiene_seis_tramos_seguidos_que_cubren_sus_fechas(self):
        for anio in (2024, 2025):
            for s in (x for x in self.datos["signos"] if x["tipo"] == "occidental"):
                tramos = zodiaco.tramos_de_espiritus(s["id"], anio)
                self.assertEqual(len(tramos), 6, s["id"])
                inicio = datetime.date(anio, *s["tropical"]["desde"])
                fin = datetime.date(anio + (1 if s["tropical"]["hasta"] < s["tropical"]["desde"] else 0), *s["tropical"]["hasta"])
                esperado, dias = inicio, []
                for (m1, d1), (m2, d2) in tramos:
                    desde = datetime.date(esperado.year, m1, d1)
                    self.assertEqual(desde, esperado, f"{s['id']} {anio}: el tramo no empieza donde acabó el anterior")
                    hasta = datetime.date(esperado.year + (1 if (m2, d2) < (m1, d1) else 0), m2, d2)
                    dias.append((hasta - desde).days + 1)
                    esperado = hasta + datetime.timedelta(days=1)
                self.assertEqual(esperado, fin + datetime.timedelta(days=1), f"{s['id']} {anio}: no cubren hasta el final")
                self.assertTrue(4 <= min(dias) and max(dias) <= 6, f"{s['id']} {anio}: {dias}")

    def test_los_angeles_de_fechas_conocidas(self):
        casos = {
            (2025, 3, 21): (1, "Vehuiah"), (2025, 3, 25): (1, "Vehuiah"), (2025, 3, 26): (2, "Jeliel"),
            (1990, 4, 3): (3, "Sitael"), (2025, 3, 20): (72, "Mumiah"), (2025, 12, 31): (56, "Poyel"),
            (2024, 2, 29): (68, "Habuhiah"), (2025, 2, 28): (68, "Habuhiah"),
        }
        for (a, m, d), (numero, nombre) in casos.items():
            x = zodiaco.angel_de_fecha(datetime.date(a, m, d))
            self.assertEqual((x["angel"]["numero"], x["angel"]["nombre"]), (numero, nombre), (a, m, d))

    def test_el_demonio_de_una_fecha_tiene_el_numero_de_su_angel(self):
        for dia in dias_del_anio_bisiesto():
            self.assertEqual(zodiaco.demonio_de_fecha(dia)["demonio"]["numero"], zodiaco.angel_de_fecha(dia)["angel"]["numero"], dia)
        x = zodiaco.demonio_de_fecha(datetime.date(2025, 3, 21))
        self.assertEqual((x["demonio"]["nombre"], x["signo"]["id"], x["tramo"]), ("Bael", "aries", [[3, 21], [3, 25]]))

    def test_de_un_equinoccio_al_siguiente_salen_los_72_en_orden(self):
        dia, vistos = datetime.date(2025, 3, 21), []
        while dia <= datetime.date(2026, 3, 20):
            n = zodiaco.angel_de_fecha(dia)["angel"]["numero"]
            if not vistos or vistos[-1] != n:
                vistos.append(n)
            dia += datetime.timedelta(days=1)
        self.assertEqual(vistos, list(range(1, 73)))

    def test_el_coro_va_de_ocho_en_ocho(self):
        self.assertEqual(zodiaco.angel_de_fecha(datetime.date(2025, 3, 21))["coro"], "serafines")
        self.assertEqual(zodiaco.angel_de_fecha(datetime.date(2025, 3, 20))["coro"], "angeles")
        coros = [zodiaco.CHOROS[(n - 1) // 8] for n in range(1, 73)]
        self.assertEqual([coros.count(c) for c in zodiaco.CHOROS], [8] * 9)

    def test_las_consultas_por_fecha_rechazan_lo_que_no_es_una_fecha(self):
        for funcion in (zodiaco.angel_de_fecha, zodiaco.demonio_de_fecha):
            with self.assertRaises(TypeError):
                funcion("2025-03-21")

    def test_los_tramos_rechazan_un_signo_chino_y_un_anio_que_no_es_int(self):
        with self.assertRaises(ValueError):
            zodiaco.tramos_de_espiritus("rata")
        with self.assertRaises(TypeError):
            zodiaco.tramos_de_espiritus("aries", "2025")

    def test_las_cualidades_se_dicen_en_cada_idioma(self):
        self.assertEqual(zodiaco.cualidades_de("angel", 3, "es"), "Construcción, protección, nobleza")
        self.assertEqual(zodiaco.cualidades_de("demonio", 1, "en"), "Invisibility, wisdom")
        self.assertEqual(zodiaco.cualidades_de("angel", 1, "de"), "Wille, Anfänge, Energie")
        self.assertEqual(zodiaco.cualidades_de("angel", 1, "ar"), "إرادة، بدايات، طاقة")  # coma árabe
        self.assertEqual(zodiaco.cualidades_de("angel", 1, "ja"), "意志、始まり、活力")      # coma japonesa

    def test_la_primera_letra_en_turco_es_la_i_con_punto(self):
        # «irade» (voluntad) empieza por i: en turco la mayúscula es İ, no I.
        self.assertTrue(zodiaco.cualidades_de("angel", 1, "tr").startswith("İrade"))

    def test_todas_las_cualidades_salen_en_los_30_idiomas(self):
        for tipo in zodiaco.TIPOS_DE_ESPIRITU:
            for n in range(1, 73):
                for codigo in generar.IDIOMAS:
                    self.assertTrue(zodiaco.cualidades_de(tipo, n, codigo).strip(), (tipo, n, codigo))

    def test_cualidades_rechaza_entradas_no_validas(self):
        for args, error in ((("arcangel", 1, "es"), ValueError), (("angel", 0, "es"), ValueError),
                            (("angel", 73, "es"), ValueError), (("angel", 1, "xx"), ValueError),
                            (("angel", "1", "es"), TypeError), (("angel", True, "es"), TypeError)):
            with self.assertRaises(error):
                zodiaco.cualidades_de(*args)

    def test_cada_ficha_trae_las_dos_tablas_con_72_filas(self):
        for codigo in generar.IDIOMAS:
            ficha = generar.ficha_de(self.datos, codigo)
            # Solo las dos tablas de los 72: desde su primer título hasta el del calendario celta.
            tramo = ficha[ficha.index(self.esp["terminos"]["angeles_titulo"][codigo]):
                          ficha.index(zodiaco.cargar_arboles()["terminos"]["celta_titulo"][codigo])]
            filas = [l for l in tramo.splitlines() if re.match(r"\| \d+ \|", l)]
            self.assertEqual(len(filas), 144, codigo)
            self.assertIn(self.esp["terminos"]["angeles_nota"][codigo], ficha, codigo)
            self.assertIn(self.esp["terminos"]["demonios_nota"][codigo], ficha, codigo)

    def test_la_ficha_en_espanol_trae_una_fila_conocida_de_cada_tabla(self):
        ficha = generar.ficha_de(self.datos, "es")
        self.assertIn("| 1 | Vehuiah | Serafines | 21/03 – 25/03 | Voluntad, comienzos, energía |", ficha)
        self.assertIn("| 17 | Botis | Presidente / Conde | 60 | 11/06 – 15/06 |", ficha)
        self.assertIn("| 3 | Vassago | Príncipe | — | 31/03 – 04/04 |", ficha)  # sin legiones en la fuente

    def test_las_tablas_van_dentro_del_bloque_de_derecha_a_izquierda(self):
        ficha = generar.ficha_de(self.datos, "ar")
        self.assertLess(ficha.index('<div dir="rtl"'), ficha.index(self.esp["terminos"]["angeles_titulo"]["ar"]))
        self.assertLess(ficha.index(self.esp["terminos"]["demonios_titulo"]["ar"]), ficha.index("</div>"))


class PruebasDeValidacionDeEspiritus(unittest.TestCase):
    def setUp(self):
        self.esp = copy.deepcopy(zodiaco.cargar_espiritus())

    def errores_con(self, texto):
        return [e for e in generar.validar_espiritus(self.esp) if texto in e]

    def test_detecta_un_angel_que_falta(self):
        del self.esp["angeles"][10]
        self.assertTrue(self.errores_con("espiritus/angeles: tienen que ser 72"))

    def test_detecta_numeros_desordenados(self):
        self.esp["demonios"][0]["numero"], self.esp["demonios"][1]["numero"] = 2, 1
        self.assertTrue(self.errores_con("espiritus/demonios: tienen que ser 72"))

    def test_detecta_una_cualidad_que_no_esta_en_el_glosario(self):
        self.esp["angeles"][0]["cualidades"].append("inventada")
        self.assertTrue(self.errores_con("'inventada' no está en el glosario"))

    def test_detecta_una_cualidad_que_nadie_usa(self):
        self.esp["cualidades"]["sobrante"] = dict(self.esp["cualidades"]["amor"])
        self.assertTrue(self.errores_con("espiritus/cualidades/sobrante: nadie la usa"))

    def test_detecta_una_cualidad_sin_un_idioma(self):
        del self.esp["cualidades"]["amor"]["sw"]
        self.assertTrue(self.errores_con("espiritus/cualidades/amor: no tiene exactamente"))

    def test_detecta_un_texto_vacio(self):
        self.esp["cualidades"]["amor"]["fi"] = "  "
        self.assertTrue(self.errores_con("espiritus/cualidades/amor/fi"))

    def test_detecta_un_termino_que_falta(self):
        del self.esp["terminos"]["rango_rey"]
        self.assertTrue(self.errores_con("espiritus/terminos"))

    def test_detecta_un_rango_desconocido_y_legiones_invalidas(self):
        self.esp["demonios"][0]["rangos"] = ["emperador"]
        self.assertTrue(self.errores_con("espiritus/demonios/1: rangos"))
        self.esp["demonios"][0]["rangos"] = ["rey"]
        self.esp["demonios"][0]["legiones"] = 0
        self.assertTrue(self.errores_con("espiritus/demonios/1: legiones"))
        self.esp["demonios"][0]["legiones"] = None
        self.assertEqual(self.errores_con("legiones"), [])

    def test_detecta_ids_repetidos_y_cualidades_repetidas(self):
        self.esp["angeles"][1]["id"] = self.esp["angeles"][0]["id"]
        self.assertTrue(self.errores_con("ids repetidos"))
        self.esp["angeles"][1]["id"] = "jeliel"
        self.esp["angeles"][0]["cualidades"] = ["amor", "amor"]
        self.assertTrue(self.errores_con("espiritus/angeles/1: de 1 a 5 cualidades"))


class PruebasDeArboles(unittest.TestCase):
    """El calendario celta, el horóscopo de los árboles y los árboles de los 27 nakshatras."""

    @classmethod
    def setUpClass(cls):
        cls.arb = zodiaco.cargar_arboles()
        cls.esp = zodiaco.cargar_espiritus()

    def test_los_datos_pasan_la_validacion(self):
        self.assertEqual(generar.validar_arboles(self.arb, self.esp), [])

    def test_el_calendario_celta_tiene_13_meses_y_el_23_de_diciembre_aparte(self):
        meses = self.arb["calendario_celta"]["meses"]
        self.assertEqual([m["ogham"] for m in meses], ["Beth", "Luis", "Nion", "Fearn", "Saille", "Uath", "Duir", "Tinne",
                                                       "Coll", "Muin", "Gort", "Ngetal", "Ruis"])
        self.assertEqual([m["arbol"] for m in meses[:3]], ["abedul", "serbal", "fresno"])
        self.assertEqual(self.arb["calendario_celta"]["dia_sin_arbol"], [12, 23])

    def test_el_celta_en_fechas_conocidas(self):
        casos = {(2025, 12, 24): "beth", (2026, 1, 20): "beth", (2026, 1, 21): "luis", (2025, 6, 10): "duir",
                 (2025, 12, 22): "ruis", (2024, 2, 29): "nion", (1990, 4, 3): "fearn"}
        for (a, m, d), mes in casos.items():
            self.assertEqual(zodiaco.arbol_celta(datetime.date(a, m, d))["mes"]["id"], mes, (a, m, d))
        self.assertIsNone(zodiaco.arbol_celta(datetime.date(2025, 12, 23)))

    def test_cada_dia_cae_en_un_mes_celta_salvo_el_23_de_diciembre_y_cada_mes_dura_28_dias(self):
        cuenta = {}
        for dia in (datetime.date(2025, 1, 1) + datetime.timedelta(days=n) for n in range(365)):
            r = zodiaco.arbol_celta(dia)
            if (dia.month, dia.day) == (12, 23):
                self.assertIsNone(r)
            else:
                cuenta[r["mes"]["id"]] = cuenta.get(r["mes"]["id"], 0) + 1
        self.assertEqual(set(cuenta.values()), {28})
        self.assertEqual(len(cuenta), 13)

    def test_el_horoscopo_tiene_21_arboles_y_cubre_cada_dia_una_sola_vez(self):
        self.assertEqual(len(self.arb["horoscopo"]), 21)
        for dia in dias_del_anio_bisiesto():
            h = zodiaco.arbol_de_fecha(dia)
            self.assertTrue(zodiaco._en_rango((dia.month, dia.day), h["tramo"][0], h["tramo"][1]), dia)

    def test_los_cuatro_arboles_de_un_solo_dia_son_los_de_los_equinoccios_y_los_solsticios(self):
        casos = {(3, 21): "roble", (6, 24): "abedul", (9, 23): "olivo", (12, 22): "haya"}
        for (m, d), arbol in casos.items():
            r = zodiaco.arbol_de_fecha(datetime.date(2025, m, d))
            self.assertEqual((r["arbol"]["id"], r["tramo"]), (arbol, [[m, d], [m, d]]), (m, d))
        unicos = [h["arbol"] for h in self.arb["horoscopo"] if h["tramos"][0][0] == h["tramos"][0][1] and len(h["tramos"]) == 1]
        self.assertEqual(sorted(unicos), ["abedul", "haya", "olivo", "roble"])

    def test_el_horoscopo_en_fechas_conocidas(self):
        casos = {(2025, 1, 1): "manzano", (2025, 1, 2): "abeto", (2024, 2, 29): "pino", (2025, 5, 10): "alamo",
                 (2025, 7, 10): "abeto", (2025, 12, 31): "manzano", (1990, 4, 3): "serbal", (2025, 8, 10): "alamo"}
        for (a, m, d), arbol in casos.items():
            self.assertEqual(zodiaco.arbol_de_fecha(datetime.date(a, m, d))["arbol"]["id"], arbol, (a, m, d))
        self.assertEqual(len(self.arb["horoscopo"][3]["tramos"]), 3)  # el álamo, en tres tramos

    def test_los_arboles_traen_nombre_cientifico_y_30_idiomas(self):
        a = zodiaco.arbol_celta(datetime.date(2025, 6, 10))["arbol"]
        self.assertEqual((a["id"], a["cientifico"], a["nombres"]["en"], a["nombres"]["de"]), ("roble", "Quercus robur", "oak", "Eiche"))
        self.assertEqual(set(a["nombres"]), set(generar.IDIOMAS))

    def test_hay_27_nakshatras_y_en_11_las_fuentes_no_coinciden(self):
        naks = self.arb["nakshatras"]
        self.assertEqual([n["numero"] for n in naks], list(range(1, 28)))
        self.assertEqual((naks[0]["nombre"], naks[-1]["nombre"]), ("Ashwini", "Revati"))
        self.assertEqual(sum(n["variable"] for n in naks), 11)
        self.assertFalse(naks[7]["variable"])  # Pushya: el peepal, que no se discute
        self.assertEqual((naks[7]["arbol"], naks[7]["cientifico"]), ("Peepal", "Ficus religiosa"))

    def test_la_longitud_de_la_luna_coincide_con_el_ejemplo_de_meeus(self):
        # Meeus, ej. 47.a: 12/04/1992 a las 0 h TD, 133,1627°. Aquí el instante es UTC, ~1 minuto de diferencia (0,009°).
        lon = zodiaco.longitud_de_la_luna(datetime.datetime(1992, 4, 12, tzinfo=datetime.timezone.utc))
        self.assertAlmostEqual(lon, 133.1627, delta=0.02)

    def test_la_luna_en_una_luna_nueva_y_una_llena_conocidas(self):
        # Luna nueva del 6/01/2000 a las 18:14 UTC: la Luna y el Sol en la misma longitud (≈ 285,3°).
        nueva = zodiaco.longitud_de_la_luna(datetime.datetime(2000, 1, 6, 18, 14, tzinfo=datetime.timezone.utc))
        self.assertAlmostEqual(nueva, 285.3, delta=0.7)
        # Luna llena del 21/01/2000 a las 04:40 UTC: 180° más allá (≈ 120,9° - Sol en 300,9°).
        llena = zodiaco.longitud_de_la_luna(datetime.datetime(2000, 1, 21, 4, 40, tzinfo=datetime.timezone.utc))
        self.assertAlmostEqual(llena, 120.9, delta=0.7)

    def test_el_nakshatra_de_un_instante_conocido(self):
        n = zodiaco.nakshatra_de_instante(datetime.datetime(1992, 4, 12, tzinfo=datetime.timezone.utc))
        self.assertEqual((n["nakshatra"]["nombre"], n["nakshatra"]["arbol"]), ("Ashlesha", "Nagkesar"))
        self.assertAlmostEqual(n["longitud_sideral"], 109.4, delta=0.1)  # Ashlesha va de 106,67° a 120°

    def test_la_luna_recorre_los_27_nakshatras_en_orden_y_cada_uno_dura_de_20_a_29_horas(self):
        inicio = datetime.datetime(2025, 1, 1, tzinfo=datetime.timezone.utc)
        anterior, duraciones, horas, cambios = None, [], 0, 0
        for h in range(0, 24 * 60):
            n = zodiaco.nakshatra_de_instante(inicio + datetime.timedelta(hours=h))["nakshatra"]["numero"]
            if anterior is None:
                anterior = n
            elif n != anterior:
                self.assertEqual(n, anterior % 27 + 1, f"salto de {anterior} a {n}")
                cambios += 1
                if cambios > 1:  # el primero empezó antes de la ventana y no se mide entero
                    duraciones.append(horas)
                anterior, horas = n, 0
            horas += 1
        self.assertGreaterEqual(len(duraciones), 50)
        self.assertTrue(all(20 <= d <= 29 for d in duraciones), sorted(set(duraciones)))

    def test_una_fecha_sola_se_toma_a_mediodia_utc_y_los_husos_se_respetan(self):
        a = zodiaco.nakshatra_de_instante(datetime.date(2025, 5, 5))
        b = zodiaco.nakshatra_de_instante(datetime.datetime(2025, 5, 5, 12, tzinfo=datetime.timezone.utc))
        self.assertEqual(a, b)
        sin_huso = zodiaco.nakshatra_de_instante(datetime.datetime(2025, 5, 5, 12))
        self.assertEqual(sin_huso, a)  # un datetime sin huso se toma como UTC
        india = datetime.timezone(datetime.timedelta(hours=5, minutes=30))
        self.assertEqual(zodiaco.nakshatra_de_instante(datetime.datetime(2025, 5, 5, 17, 30, tzinfo=india)), a)

    def test_nakshatra_rechaza_lo_que_no_es_un_instante(self):
        with self.assertRaises(TypeError):
            zodiaco.nakshatra_de_instante("1992-04-12")

    def test_los_rasgos_se_dicen_en_cada_idioma(self):
        self.assertEqual(zodiaco.rasgos_de("horoscopo", "olivo", "es"), "Equilibrio, sabiduría, paz")
        self.assertEqual(zodiaco.rasgos_de("celta", "beth", "ja"), "始まり、清らかさ、復活")
        self.assertEqual(zodiaco.rasgos_de("nakshatra", "ashlesha", "ar"), "حدس، غموض، دهاء")
        self.assertEqual(zodiaco.rasgos_de("celta", "duir", "de"), "Stärke, Durchhaltevermögen, Edelmut")

    def test_los_rasgos_salen_en_los_30_idiomas_para_los_61(self):
        for sistema, clave in generar._claves_de_arboles():
            for codigo in generar.IDIOMAS:
                self.assertTrue(zodiaco.rasgos_de(sistema, clave, codigo).strip(), (sistema, clave, codigo))

    def test_rasgos_rechaza_entradas_no_validas(self):
        for args in (("bosque", "beth", "es"), ("celta", "no-existe", "es"), ("celta", "beth", "xx"), ("horoscopo", "beth", "es")):
            with self.assertRaises(ValueError):
                zodiaco.rasgos_de(*args)

    def test_cada_ficha_trae_las_tres_tablas_de_arboles(self):
        for codigo in generar.IDIOMAS:
            ficha = generar.ficha_de(cargar_datos(), codigo)
            t = self.arb["terminos"]
            for clave in ("celta_nota", "horoscopo_nota", "nakshatra_nota", "dia_nota"):
                self.assertIn(t[clave][codigo], ficha, (codigo, clave))
            self.assertEqual(ficha.count("*Betula pendula*"), 2, codigo)  # el abedul, en el calendario y en el horóscopo
            self.assertEqual(ficha.count("\\*"), 11 + 1, codigo)           # 11 nakshatras marcados y su nota

    def test_la_ficha_en_espanol_trae_una_fila_de_cada_tabla(self):
        ficha = generar.ficha_de(cargar_datos(), "es")
        self.assertIn("| 4 | Aliso | *Alnus glutinosa* | Fearn | 18/03 – 14/04 | Valentía, discernimiento, estabilidad |", ficha)
        self.assertIn("| Álamo | *Populus nigra* | 04/02 – 08/02 · 01/05 – 14/05 · 05/08 – 13/08 | Sensibilidad, adaptación, incertidumbre |", ficha)
        self.assertIn("| 9 | Ashlesha | Nagkesar \\* | *Mesua ferrea* | Intuición, misterio, astucia |", ficha)
        self.assertIn("\\* Las fuentes no coinciden en el árbol de este nakshatra.", ficha)


class PruebasDeValidacionDeArboles(unittest.TestCase):
    def setUp(self):
        self.arb = copy.deepcopy(zodiaco.cargar_arboles())
        self.esp = zodiaco.cargar_espiritus()

    def errores_con(self, texto):
        return [e for e in generar.validar_arboles(self.arb, self.esp) if texto in e]

    def test_detecta_un_mes_que_se_solapa_o_deja_un_hueco(self):
        self.arb["calendario_celta"]["meses"][1]["desde"] = [1, 25]
        self.assertTrue(self.errores_con("arboles/calendario_celta"))

    def test_detecta_un_mes_que_no_dura_28_dias(self):
        self.arb["calendario_celta"]["meses"][3]["hasta"] = [4, 15]
        self.assertTrue(self.errores_con("arboles/calendario_celta"))

    def test_detecta_un_dia_del_horoscopo_sin_arbol_o_con_dos(self):
        self.arb["horoscopo"][0]["tramos"][0] = [[1, 2], [1, 10]]
        self.assertTrue(self.errores_con("arboles/horoscopo: el"))
        self.arb["horoscopo"][0]["tramos"][0] = [[1, 2], [1, 13]]
        self.assertTrue(self.errores_con("arboles/horoscopo: el"))

    def test_detecta_un_arbol_del_horoscopo_que_falta(self):
        del self.arb["horoscopo"][5]
        self.assertTrue(self.errores_con("arboles/horoscopo: tienen que ser 21"))

    def test_detecta_un_rasgo_que_no_esta_en_ningun_glosario_o_que_ya_esta_en_el_de_los_72(self):
        self.arb["horoscopo"][0]["rasgos"].append("inventado")
        self.assertTrue(self.errores_con("'inventado' no está en ningún glosario"))
        self.arb["horoscopo"][0]["rasgos"].pop()
        self.arb["rasgos"]["proteccion"] = dict(self.arb["rasgos"]["fuerza"])
        self.assertTrue(self.errores_con("ya está en el glosario de espiritus.json"))

    def test_detecta_un_rasgo_que_nadie_usa_y_un_arbol_que_nadie_usa(self):
        self.arb["rasgos"]["sobrante"] = dict(self.arb["rasgos"]["fuerza"])
        self.assertTrue(self.errores_con("arboles/rasgos/sobrante: nadie lo usa"))
        self.arb["arboles"]["huerfano"] = copy.deepcopy(self.arb["arboles"]["roble"])
        self.assertTrue(self.errores_con("arboles/arboles/huerfano: ningún sistema lo usa"))

    def test_detecta_un_nombre_sin_un_idioma_y_un_nombre_cientifico_vacio(self):
        del self.arb["arboles"]["roble"]["nombres"]["fi"]
        self.assertTrue(self.errores_con("arboles/arboles/roble/nombres: no tiene exactamente"))
        self.arb["arboles"]["roble"]["cientifico"] = " "
        self.assertTrue(self.errores_con("arboles/arboles/roble: id"))

    def test_detecta_nakshatras_que_faltan_o_con_variable_mal(self):
        del self.arb["nakshatras"][26]
        self.assertTrue(self.errores_con("arboles/nakshatras: tienen que ser 27"))
        self.arb["nakshatras"].append({**self.arb["nakshatras"][0], "numero": 27, "id": "revati", "variable": "si"})
        self.assertTrue(self.errores_con("variable true o false"))

    def test_detecta_un_termino_que_falta(self):
        del self.arb["terminos"]["celta_nota"]
        self.assertTrue(self.errores_con("arboles/terminos"))


class PruebasDeNumerologia(unittest.TestCase):
    """El número del camino de vida, el año personal y los rasgos de los 12 números."""

    @classmethod
    def setUpClass(cls):
        cls.num = zodiaco.cargar_numerologia()

    def test_los_datos_pasan_la_validacion(self):
        self.assertEqual(generar.validar_numerologia(self.num, zodiaco.cargar_arboles(), zodiaco.cargar_espiritus()), [])

    def test_reducir_numero(self):
        for n, con_maestros, sin_maestros in ((0, 0, 0), (9, 9, 9), (10, 1, 1), (29, 11, 2), (38, 11, 2), (44, 8, 8), (22, 22, 4),
                                              (33, 33, 6), (99, 9, 9), (1999, 1, 1)):  # 1999 → 28 → 10 → 1
            self.assertEqual(zodiaco.reducir_numero(n), con_maestros, n)
            self.assertEqual(zodiaco.reducir_numero(n, maestros=False), sin_maestros, n)

    def test_reducir_numero_rechaza_negativos_y_lo_que_no_es_un_entero(self):
        for malo in (-1, True, 2.5, "9"):
            with self.assertRaises(ValueError):
                zodiaco.reducir_numero(malo)

    def test_el_camino_de_vida_en_fechas_conocidas(self):
        casos = {(1990, 4, 3): 8, (1980, 1, 19): 11, (1980, 1, 3): 22, (1980, 4, 29): 33, (2000, 1, 1): 4, (1999, 12, 31): 8}
        for (a, m, d), esperado in casos.items():
            self.assertEqual(zodiaco.camino_de_vida(datetime.date(a, m, d)), esperado, (a, m, d))

    def test_el_camino_de_vida_siempre_es_uno_de_los_12_y_salen_los_12(self):
        vistos, dia = set(), datetime.date(1900, 1, 1)
        while dia.year < 2100:
            vistos.add(zodiaco.camino_de_vida(dia))
            dia += datetime.timedelta(days=1)
        self.assertEqual(vistos, {1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 22, 33})

    def test_el_camino_de_vida_acepta_un_datetime(self):
        self.assertEqual(zodiaco.camino_de_vida(datetime.datetime(1990, 4, 3, 23, 59)), 8)

    def test_el_anio_personal_va_de_1_a_9(self):
        self.assertEqual(zodiaco.anio_personal(datetime.date(1990, 4, 3), 2026), 8)   # 3+4+2+0+2+6 = 17 → 8
        self.assertEqual(zodiaco.anio_personal(datetime.date(1990, 4, 3), 2027), 9)
        self.assertEqual(zodiaco.anio_personal(datetime.date(1990, 4, 3), 2028), 1)   # 3+4+2+0+2+8 = 19 → 10 → 1
        self.assertEqual({zodiaco.anio_personal(datetime.date(1990, 4, 3), a) for a in range(1990, 2100)}, set(range(1, 10)))
        self.assertEqual(zodiaco.anio_personal(datetime.date(1980, 1, 19), 2026), 3)  # 1+9+1+2+0+2+6 = 21 → 3

    def test_el_anio_personal_rechaza_entradas_no_validas(self):
        with self.assertRaises(TypeError):
            zodiaco.anio_personal("1990-04-03", 2026)
        for malo in (0, -5, "2026", True):
            with self.assertRaises(ValueError):
                zodiaco.anio_personal(datetime.date(1990, 4, 3), malo)

    def test_el_camino_de_vida_rechaza_lo_que_no_es_una_fecha(self):
        with self.assertRaises(TypeError):
            zodiaco.camino_de_vida("1990-04-03")

    def test_los_rasgos_de_los_12_numeros_en_espanol(self):
        esperados = {1: "Liderazgo, independencia, iniciativa", 11: "Intuición, inspiración, idealismo",
                     22: "Construcción, determinación, liderazgo", 33: "Compasión, ternura, devoción"}
        for numero, texto in esperados.items():
            self.assertEqual(zodiaco.rasgos_de_numero(numero, "es"), texto)

    def test_los_rasgos_salen_en_los_30_idiomas_con_su_separador(self):
        for n in self.num["numeros"]:
            for codigo in generar.IDIOMAS:
                self.assertTrue(zodiaco.rasgos_de_numero(n["numero"], codigo).strip(), (n["numero"], codigo))
        self.assertIn("،", zodiaco.rasgos_de_numero(1, "ar"))
        self.assertIn("、", zodiaco.rasgos_de_numero(1, "ja"))

    def test_rasgos_de_numero_rechaza_entradas_no_validas(self):
        for args, error in (((10, "es"), ValueError), ((1, "xx"), ValueError), (("1", "es"), TypeError), ((True, "es"), TypeError)):
            with self.assertRaises(error):
                zodiaco.rasgos_de_numero(*args)

    def test_cada_ficha_trae_la_tabla_con_los_12_numeros(self):
        for codigo in generar.IDIOMAS:
            ficha = generar.ficha_de(cargar_datos(), codigo)
            tramo = ficha[ficha.index(self.num["terminos"]["titulo"][codigo]):]
            filas = [l for l in tramo.splitlines() if re.match(r"\| \d+ \|", l)]
            self.assertEqual([l.split(" | ")[0].strip("| ") for l in filas], ["1", "2", "3", "4", "5", "6", "7", "8", "9", "11", "22", "33"], codigo)
            self.assertIn(self.num["terminos"]["nota"][codigo], ficha, codigo)


class PruebasDeValidacionDeNumerologia(unittest.TestCase):
    def setUp(self):
        self.num = copy.deepcopy(zodiaco.cargar_numerologia())
        self.arb, self.esp = zodiaco.cargar_arboles(), zodiaco.cargar_espiritus()

    def errores_con(self, texto):
        return [e for e in generar.validar_numerologia(self.num, self.arb, self.esp) if texto in e]

    def test_detecta_un_numero_que_falta_o_esta_desordenado(self):
        del self.num["numeros"][9]
        self.assertTrue(self.errores_con("tienen que ser 1 a 9, 11, 22 y 33"))

    def test_detecta_un_rasgo_que_no_esta_en_ningun_glosario(self):
        self.num["numeros"][0]["rasgos"].append("inventado")
        self.assertTrue(self.errores_con("'inventado' no está en ningún glosario"))

    def test_detecta_un_texto_sin_un_idioma_o_vacio(self):
        del self.num["terminos"]["nota"]["fi"]
        self.assertTrue(self.errores_con("numerologia/terminos/nota: no tiene exactamente"))
        self.num["terminos"]["titulo"]["es"] = " "
        self.assertTrue(self.errores_con("numerologia/terminos/titulo/es"))

    def test_detecta_un_termino_que_falta_y_rasgos_repetidos(self):
        del self.num["terminos"]["col_numero"]
        self.assertTrue(self.errores_con("numerologia/terminos"))
        self.num["numeros"][1]["rasgos"] = ["armonia", "armonia"]
        self.assertTrue(self.errores_con("numerologia/numeros/2: de 1 a 5 rasgos"))


if __name__ == "__main__":
    unittest.main()
