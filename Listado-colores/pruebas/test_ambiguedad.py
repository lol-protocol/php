"""El informe de ambigüedad."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar_base()


PURPURA, VIOLETA, LILA, VERDE, LIMA, GRIS = "#800080", "#EE82EE", "#CEA2FD", "#008000", "#00FF00", "#808080"


def fila_gazetteer(forma, idioma, concepto, color, tipo="lema", fuente="lexico"):
    return (forma, idioma, fuente, tipo, concepto, color, "", normalizacion.normalizar(forma, idioma))


class Ambiguedad(unittest.TestCase):
    def calcular(self, *filas, codigos=("es", "fr", "de", "pt")):
        return ambiguedad.calcular(list(filas), list(codigos))

    def assertListasVaciasSalvo(self, resultado, *claves):
        for clave, elementos in resultado.items():
            if clave not in claves:
                self.assertEqual(elementos, [], clave)

    def test_una_forma_con_dos_colores_en_un_idioma(self):
        resultado = self.calcular(fila_gazetteer("violet", "fr", "purple", PURPURA), fila_gazetteer("violet", "fr", "violet", VIOLETA))
        self.assertListasVaciasSalvo(resultado, "dentro")
        (caso,) = resultado["dentro"]
        self.assertEqual((caso["idioma"], caso["forma"], caso["colores"]), ("fr", "violet", [(PURPURA, ["purple"]), (VIOLETA, ["violet"])]))
        esperada = colorimetria.ciede2000(colorimetria.hex_a_lab(PURPURA), colorimetria.hex_a_lab(VIOLETA))
        self.assertAlmostEqual(caso["delta"], esperada, places=9)

    def test_los_alias_del_mismo_hex_no_son_ambiguos(self):
        resultado = self.calcular(fila_gazetteer("gris", "es", "gray", GRIS), fila_gazetteer("gris", "es", "grey", GRIS))
        self.assertListasVaciasSalvo(resultado)

    def test_mayusculas_distintas_son_la_misma_forma(self):
        resultado = self.calcular(fila_gazetteer("Violet", "fr", "purple", PURPURA), fila_gazetteer("violet", "fr", "violet", VIOLETA))
        (caso,) = resultado["dentro"]
        self.assertEqual(caso["forma"], "Violet")

    def test_la_misma_forma_con_colores_distintos_en_dos_idiomas(self):
        resultado = self.calcular(fila_gazetteer("lila", "de", "purple", PURPURA), fila_gazetteer("lila", "es", "lilac", LILA))
        self.assertListasVaciasSalvo(resultado, "entre")
        (caso,) = resultado["entre"]
        self.assertEqual(caso["forma"], "lila")
        self.assertEqual(caso["idiomas"], [("es", ["lila"], [(LILA, ["lilac"])]), ("de", ["lila"], [(PURPURA, ["purple"])])],
                         "en el orden de idiomas que se pasa, no en el alfabético")
        esperada = colorimetria.ciede2000(colorimetria.hex_a_lab(PURPURA), colorimetria.hex_a_lab(LILA))
        self.assertAlmostEqual(caso["delta"], esperada, places=9)

    def test_la_misma_forma_con_el_mismo_color_en_dos_idiomas_no_es_ambigua(self):
        resultado = self.calcular(fila_gazetteer("magenta", "es", "magenta", "#FF00FF"), fila_gazetteer("magenta", "fr", "magenta", "#FF00FF"))
        self.assertListasVaciasSalvo(resultado)

    def test_dos_idiomas_con_los_mismos_dos_colores_son_ambiguos_dentro_pero_no_entre(self):
        filas = [fila_gazetteer("bordeaux", i, c, h) for i in ("fr", "de") for c, h in (("maroon", "#800000"), ("burgundy", "#900020"))]
        resultado = self.calcular(*filas)
        self.assertListasVaciasSalvo(resultado, "dentro")
        self.assertEqual([c["idioma"] for c in resultado["dentro"]], ["fr", "de"])

    def test_formas_distintas_que_solo_coinciden_al_normalizar_dentro_de_un_idioma(self):
        resultado = self.calcular(fila_gazetteer("grün", "de", "green", VERDE), fila_gazetteer("grun", "de", "lime", LIMA))
        self.assertListasVaciasSalvo(resultado, "dentro_normalizada")
        (caso,) = resultado["dentro_normalizada"]
        self.assertEqual((caso["idioma"], caso["formas"], caso["clave"]), ("de", ["grun", "grün"], "grun"))

    def test_lo_que_ya_es_ambiguo_sin_normalizar_no_se_repite_en_la_lista_3(self):
        resultado = self.calcular(fila_gazetteer("violett", "de", "purple", PURPURA), fila_gazetteer("violett", "de", "violet", VIOLETA),
                                  fila_gazetteer("violétt", "de", "lime", LIMA))
        self.assertListasVaciasSalvo(resultado, "dentro")

    def test_formas_distintas_que_solo_coinciden_al_normalizar_entre_idiomas(self):
        resultado = self.calcular(fila_gazetteer("lilás", "pt", "lilac", LILA), fila_gazetteer("lilas", "fr", "purple", PURPURA))
        self.assertListasVaciasSalvo(resultado, "entre_normalizada")
        (caso,) = resultado["entre_normalizada"]
        self.assertEqual(caso["clave"], "lilas")
        self.assertEqual(caso["idiomas"], [("fr", ["lilas"], [(PURPURA, ["purple"])]), ("pt", ["lilás"], [(LILA, ["lilac"])])])

    def test_lo_que_ya_es_ambiguo_entre_idiomas_no_se_repite_en_la_lista_4(self):
        resultado = self.calcular(fila_gazetteer("lila", "es", "lilac", LILA), fila_gazetteer("lila", "de", "purple", PURPURA),
                                  fila_gazetteer("lilá", "pt", "green", VERDE))
        self.assertListasVaciasSalvo(resultado, "entre")

    def test_bandas(self):
        for delta, esperada in ((0.0, "casi iguales"), (3.0, "casi iguales"), (3.01, "parecidos"), (8.0, "parecidos"),
                                (8.01, "distintos"), (80.0, "distintos")):
            self.assertEqual(ambiguedad.banda(delta), esperada, delta)

    def test_el_informe_escapa_las_barras_y_dice_cuando_no_hay_casos(self):
        filas = [fila_gazetteer("a|b", "es", "purple", PURPURA), fila_gazetteer("a|b", "es", "violet", VIOLETA)]
        texto = ambiguedad.informe(filas, [("es", "Español"), ("fr", "Francés")])
        self.assertIn("«a&#124;b»", texto)
        self.assertIn("## 2. Entre idiomas: la misma forma, otro color\n\nNo hay casos.", texto)
        self.assertTrue(texto.endswith("\n"))
        self.assertEqual(texto, ambiguedad.informe(filas, [("es", "Español"), ("fr", "Francés")]), "es determinista")

    def test_el_resumen_del_informe_coincide_con_sus_tablas(self):
        texto = (BASE["carpeta"] / "ambiguedad.md").read_text(encoding="utf-8")
        resumen = {int(n): int(casos) for n, casos in re.findall(r"^\| (\d)\. [^|]+\| (\d+) \|", texto, flags=re.M)}
        self.assertEqual(sorted(resumen), [1, 2, 3, 4])
        secciones = re.split(r"^## (\d)\. ", texto, flags=re.M)[1:]
        for numero, cuerpo in zip(secciones[::2], secciones[1::2]):
            filas = [linea for linea in cuerpo.splitlines() if linea.startswith("| ")]
            esperadas = max(len(filas) - 1, 0)  # la primera es la cabecera
            self.assertEqual(esperadas, resumen[int(numero)], f"lista {numero}")
            if esperadas == 0:
                self.assertIn("No hay casos.", cuerpo)
        self.assertGreater(resumen[1], 0)
