"""Pruebas de las herramientas de desarrollo (preparar-fuentes.py y glifos-extra.py).

No usan las fuentes del proyecto: generan una fuente mínima con FontBuilder (una «A» y una «B» cuadradas), así que
corren en cualquier sitio con `pip install fonttools brotli uharfbuzz`.

    python3 -m unittest discover -s herramientas/pruebas -v
"""

import importlib.util
import tempfile
import unittest
from pathlib import Path

from fontTools.fontBuilder import FontBuilder
from fontTools.pens.ttGlyphPen import TTGlyphPen

HERRAMIENTAS = Path(__file__).resolve().parent.parent


def cargar(nombre: str):
    """Importa un script cuyo nombre lleva guion, como preparar-fuentes.py."""
    spec = importlib.util.spec_from_file_location(nombre.replace("-", "_"), HERRAMIENTAS / f"{nombre}.py")
    modulo = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(modulo)
    return modulo


def fuente_minima():
    """Una fuente de 1000 unidades con .notdef, «A» (avance 600) y «B» (avance 700), ambas cuadrados."""
    orden = [".notdef", "A", "B"]
    constructor = FontBuilder(1000, isTTF=True)
    constructor.setupGlyphOrder(orden)
    constructor.setupCharacterMap({0x41: "A", 0x42: "B"})
    glifos = {}
    for nombre, lado in [(".notdef", 400), ("A", 500), ("B", 600)]:
        lapiz = TTGlyphPen(None)
        lapiz.moveTo((50, 0))
        lapiz.lineTo((50, lado))
        lapiz.lineTo((50 + lado, lado))
        lapiz.lineTo((50 + lado, 0))
        lapiz.closePath()
        glifos[nombre] = lapiz.glyph()
    constructor.setupGlyf(glifos)
    constructor.setupHorizontalMetrics({".notdef": (500, 50), "A": (600, 50), "B": (700, 50)})
    constructor.setupHorizontalHeader(ascent=800, descent=-200)
    constructor.setupNameTable({"familyName": "Prueba", "styleName": "Regular"})
    constructor.setupOS2()
    constructor.setupPost()
    return constructor.font


class PrepararFuentes(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.modulo = cargar("preparar-fuentes")

    def test_rangos_agrupa_los_codigos_seguidos(self):
        self.assertEqual("U+20-7E,U+A0", self.modulo.rangos([0xA0] + list(range(0x20, 0x7F))))
        self.assertEqual("U+41", self.modulo.rangos([0x41]))
        self.assertEqual("U+41,U+43", self.modulo.rangos([0x43, 0x41]))

    def test_contornos_da_avance_y_trazado_en_milesimas_de_em(self):
        fuente = fuente_minima()
        resultado = self.modulo.contornos(fuente, [0x41, 0x42, 0x5A], fuente.getBestCmap(), 1000)
        self.assertEqual({"65", "66"}, set(resultado), "una letra que la fuente no tiene no sale")
        self.assertEqual(600, resultado["65"][0])
        self.assertEqual(700, resultado["66"][0])
        # La y va hacia abajo: el cuadrado de «A» (alto 500) sube a -500.
        self.assertIn("-500", resultado["65"][1])
        self.assertTrue(resultado["65"][1].startswith("M50 0"))

    def test_contornos_escala_otras_unidades_por_em(self):
        fuente = fuente_minima()
        # Si la fuente midiera 2000 unidades por em, los avances se reducen a la mitad.
        resultado = self.modulo.contornos(fuente, [0x41], fuente.getBestCmap(), 2000)
        self.assertEqual(300, resultado["65"][0])


class Kerning(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.modulo = cargar("kerning")

    def test_comprimir_quita_lo_que_se_deduce_de_la_letra_base(self):
        # «á» se ajusta igual que «a» antes y después de todo; «é» no (con «V» se ajusta distinto).
        completo = {"aV": -50, "áV": -50, "Va": -30, "Vá": -30, "eV": -10, "éV": -20, "Ve": -5, "Vé": -5}
        codigos = [ord(c) for c in "aáeéV"]
        resultado = self.modulo.comprimir(completo, codigos)
        self.assertEqual("a", resultado["primero"]["á"])
        self.assertEqual("a", resultado["segundo"]["á"])
        self.assertNotIn("é", resultado["primero"])
        self.assertEqual("e", resultado["segundo"]["é"])
        # Con las sustituciones, el ajuste de cualquier par se recupera.
        def ajuste(a, b):
            return resultado["pares"].get(resultado["primero"].get(a, a) + resultado["segundo"].get(b, b), 0)
        for par, esperado in completo.items():
            self.assertEqual(esperado, ajuste(par[0], par[1]), par)

    def test_escritura_separa_latin_griego_y_cirilico(self):
        self.assertEqual("latina", self.modulo.escritura(ord("a")))
        self.assertEqual("griega", self.modulo.escritura(ord("α")))
        self.assertEqual("cirilica", self.modulo.escritura(ord("я")))
        self.assertEqual("comun", self.modulo.escritura(ord("1")))
        self.assertEqual("comun", self.modulo.escritura(ord(".")))


class GlifosExtra(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.modulo = cargar("glifos-extra")

    def test_nucleo_quita_signos_de_los_extremos(self):
        self.assertEqual("palabra", self.modulo.nucleo("«palabra»."))
        self.assertEqual("a-b", self.modulo.nucleo("(a-b)"))
        self.assertEqual("", self.modulo.nucleo("..."))

    def test_letras_de_lee_csv_y_json(self):
        with tempfile.TemporaryDirectory() as carpeta:
            csv_ruta = Path(carpeta) / "libros.csv"
            csv_ruta.write_text("id,titulo\nuno,Ñandú\n", encoding="utf-8-sig")
            self.assertIn("Ñandú", self.modulo.letras_de(csv_ruta))
            self.assertIn("uno Ñandú", self.modulo.letras_de(csv_ruta), "las celdas se separan con un espacio")
            json_ruta = Path(carpeta) / "libro.json"
            json_ruta.write_text('{"titulo": "Ωmega"}', encoding="utf-8")
            self.assertIn("Ωmega", self.modulo.letras_de(json_ruta))

    def test_cubiertas_incluye_las_letras_latinas_del_proyecto(self):
        cubiertas = self.modulo.cubiertas()
        self.assertIn(ord("a"), cubiertas)
        self.assertIn(ord("ñ"), cubiertas)

    def test_formas_compone_una_palabra_con_harfbuzz(self):
        fuente = fuente_minima()
        formas, sin_forma = self.modulo.formas(fuente, ["AB", "AZ"])
        self.assertEqual(["AZ"], sin_forma, "«Z» no está en la fuente")
        avance, rtl, piezas = formas["AB"]
        self.assertEqual(1300, avance)
        self.assertEqual(0, rtl)
        self.assertEqual([0, 600], [pieza[0] for pieza in piezas])


if __name__ == "__main__":
    unittest.main()
