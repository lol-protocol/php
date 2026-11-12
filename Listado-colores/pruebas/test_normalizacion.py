"""La normalización de formas."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar_base()


class Normalizacion(unittest.TestCase):
    def assertNormaliza(self, idioma, casos):
        for forma, esperada in casos:
            self.assertEqual(normalizacion.normalizar(forma, idioma), esperada, (idioma, forma))

    def test_mayusculas_espacios_y_guiones(self):
        self.assertNormaliza("es", [("Azul Marino", "azul marino"), ("azul-marino", "azul marino"),
                                    ("  azul   marino ", "azul marino"), ("azul\u00a0marino", "azul marino"),
                                    ("azul\u2013marino", "azul marino"), ("azul_marino", "azul marino")])
        self.assertNormaliza("en", [("alice blue", "alice blue"), ("alice-blue", "alice blue"), ("aliceblue", "aliceblue")])

    def test_se_quitan_las_marcas_en_escritura_latina(self):
        self.assertNormaliza("fr", [("Crème Brûlée", "creme brulee")])
        self.assertNormaliza("de", [("Grün", "grun"), ("Weiß", "weiss")])
        self.assertNormaliza("es", [("marrón", "marron"), ("café", "cafe")])
        self.assertNormaliza("vi", [("đỏ", "do"), ("xanh lá", "xanh la")])
        self.assertNormaliza("pl", [("żółty", "zolty")])
        self.assertNormaliza("cs", [("žlutá", "zluta")])
        self.assertNormaliza("ro", [("roșu", "rosu")])
        self.assertNormaliza("hu", [("zöld", "zold"), ("fűzöld", "fuzold")])
        self.assertNormaliza("tr", [("Yeşil", "yesil"), ("Kırmızı", "kirmizi"), ("İstanbul", "istanbul")])
        self.assertNormaliza("da", [("grøn", "gron"), ("blå", "bla"), ("æble", "aeble")])

    def test_el_griego_pierde_los_acentos_y_la_sigma_final(self):
        self.assertNormaliza("el", [("κόκκινο", "κοκκινο"), ("ΜΑΎΡΟ", "μαυρο"), ("άσπρος", "ασπροσ")])

    def test_en_las_demas_escrituras_no_se_quita_ninguna_marca(self):
        # Allí las marcas son parte de la letra: quitarlas daría otra palabra.
        self.assertNormaliza("ru", [("красный", "красный"), ("Зелёный", "зелёный")])
        self.assertNormaliza("uk", [("синій", "синій"), ("блакитний", "блакитний")])
        self.assertNormaliza("ja", [("ピンク", "ピンク"), ("ガ", "ガ"), ("みどり", "みどり")])
        self.assertNormaliza("th", [("สีแดง", "สีแดง"), ("เขียว", "เขียว")])
        self.assertNormaliza("hi", [("नीला", "नीला"), ("हरा", "हरा")])
        self.assertNormaliza("bn", [("নীল", "নীল")])
        self.assertNormaliza("ar", [("أحمر", "أحمر")])
        self.assertNormaliza("ko", [("빨강", "빨강")])
        self.assertNormaliza("zh", [("红色", "红色")])
        self.assertNormaliza("he", [("אדום", "אדום")])

    def test_el_zwnj_del_persa_se_conserva(self):
        self.assertNormaliza("fa", [("نیمه\u200cشب", "نیمه\u200cشب")])

    def test_nfkc_unifica_anchos_y_kana(self):
        self.assertNormaliza("en", [("ＡＢＣ", "abc")])
        self.assertNormaliza("ja", [("ﾋﾟﾝｸ", "ピンク")])

    def test_los_apostrofos_se_unifican_y_se_conservan(self):
        self.assertNormaliza("fr", [("d\u2019eau", "d'eau"), ("d\u02bceau", "d'eau")])
        self.assertNormaliza("uk", [("м\u2019ятний", "м'ятний")])
        self.assertNormaliza("he", [("ג\u05f3ינס", "ג'ינס")])

    def test_se_quitan_los_caracteres_invisibles(self):
        self.assertNormaliza("en", [("a\u00adb", "ab"), ("a\u200bb", "ab"), ("\ufeffab", "ab")])

    def test_es_idempotente_y_nunca_queda_vacia_en_las_formas_reales(self):
        lineas = (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()[1:]
        formas = [tuple(linea.split("\t")[:2]) for linea in lineas]
        self.assertGreater(len(formas), 7000)
        for forma, idioma in formas:
            una = normalizacion.normalizar(forma, idioma)
            self.assertEqual(normalizacion.normalizar(una, idioma), una, (forma, idioma))
            self.assertNotEqual(una, "", (forma, idioma))

    def test_los_idiomas_latinos_y_los_demas_cubren_los_30_sin_solaparse(self):
        codigos = {c for c, _ in gt.IDIOMAS}
        self.assertEqual(normalizacion.LATINOS | gt.SIN_LETRAS_LATINAS, codigos)
        self.assertEqual(normalizacion.LATINOS & gt.SIN_LETRAS_LATINAS, set())
        self.assertEqual(normalizacion.QUITAR_MARCAS, normalizacion.LATINOS | {"el"})


PURPURA, VIOLETA, LILA, VERDE, LIMA, GRIS = "#800080", "#EE82EE", "#CEA2FD", "#008000", "#00FF00", "#808080"
