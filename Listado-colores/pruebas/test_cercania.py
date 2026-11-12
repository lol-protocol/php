"""El color CSS más cercano, los alias, la colorimetría y la estructura de los módulos."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar()


class CercaniaConCota(unittest.TestCase):
    """calcular_cercanias deja de calcular los colores que no pueden ganar; el resultado tiene que ser el de calcularlos todos."""

    @staticmethod
    def por_fuerza_bruta(completa, anotada):
        referencias = sorted((f["nombre_ingles"], f["hex"], colorimetria.hex_a_lab(f["hex"])) for f in anotada)
        resultado = []
        for fila in completa:
            lab = colorimetria.hex_a_lab(fila["hex"])
            distancia, nombre, color = min((round(colorimetria.ciede2000(lab, rl), 9), rn, rh) for rn, rh, rl in referencias)
            resultado.append((nombre, distancia, gt.cercania_de(distancia, color == fila["hex"])))
        return resultado

    def test_la_cota_es_segura_para_toda_la_luminosidad(self):
        for décima in range(0, 1001):
            l_media = décima / 10
            s_l = 1 + 0.015 * (l_media - 50) ** 2 / (20 + (l_media - 50) ** 2) ** 0.5
            self.assertLess(s_l, cercania.COTA_SL, l_media)

    def test_da_lo_mismo_que_calcular_las_148_en_una_muestra_con_extremos(self):
        completa = REAL["completa"][::97]  # unos 330 nombres repartidos
        completa += [{"nombre_ingles": "negro", "hex": "#000000", "curada": ""}, {"nombre_ingles": "blanco", "hex": "#FFFFFF", "curada": ""},
                     {"nombre_ingles": "gris", "hex": "#808080", "curada": ""}, {"nombre_ingles": "azul", "hex": "#0000FF", "curada": ""}]
        completa += [{"nombre_ingles": f["nombre_ingles"], "hex": f["hex"], "curada": ""} for f in REAL["anotada"]]  # los propios CSS: distancia 0
        self.assertEqual(gt.calcular_cercanias(completa, REAL["anotada"]), self.por_fuerza_bruta(completa, REAL["anotada"]))

    def test_la_cota_de_verdad_evita_calcular_la_mayoria_de_las_distancias(self):
        muestra = REAL["completa"][::97]
        llamadas = []
        original = colorimetria.ciede2000
        with mock.patch.object(colorimetria, "ciede2000", lambda a, b: llamadas.append(1) or original(a, b)):
            gt.calcular_cercanias(muestra, REAL["anotada"])
        por_nombre = len(llamadas) / len(muestra)
        self.assertLess(por_nombre, 148 / 2, f"{por_nombre:.0f} distancias por nombre: la poda no está funcionando")
        self.assertGreaterEqual(por_nombre, 1)

    def test_con_empate_gana_el_primero_por_nombre(self):
        anotada = [{"nombre_ingles": n, "hex": "#808080"} for n in ("grey", "gray")]
        self.assertEqual(gt.calcular_cercanias([{"nombre_ingles": "x", "hex": "#808081", "curada": ""}], anotada)[0][0], "gray")

    def test_un_solo_color_de_referencia(self):
        anotada = [{"nombre_ingles": "a", "hex": "#123456"}]
        self.assertEqual(gt.calcular_cercanias([{"nombre_ingles": "x", "hex": "#FEDCBA", "curada": ""}], anotada)[0][0], "a")

    def test_comprobar_recalcula_las_31935_distancias(self):
        # El tiempo no se prueba, pero sí que el archivo guardado coincide con el cálculo con cota (lo hace --comprobar).
        with (AQUI / "nombres-completos.csv").open(encoding="utf-8", newline="") as archivo:
            guardado = {f["nombre_ingles"]: f for f in csv.DictReader(archivo)}
        muestra = REAL["completa"][::499]
        for fila, (css, distancia, cercania) in zip(muestra, gt.calcular_cercanias(muestra, REAL["anotada"])):
            g = guardado[fila["nombre_ingles"]]
            self.assertEqual((g["css_cercano"], g["delta_e"], g["cercania"]), (css, f"{distancia:.2f}", cercania))


class AliasYCercania(unittest.TestCase):
    def test_alias_de_los_colores_reales(self):
        alias = gt.alias_de(REAL["anotada"])
        self.assertEqual(alias["gray"], [("grey", "ortografia")])
        self.assertEqual(alias["grey"], [("gray", "ortografia")])
        self.assertEqual(alias["aqua"], [("cyan", "mismo_color")])
        self.assertEqual(alias["magenta"], [("fuchsia", "mismo_color")])
        self.assertEqual(alias["red"], [])
        self.assertEqual(sum(1 for otros in alias.values() if otros), 2 * 9, "nueve pares, cada uno visto desde sus dos lados")

    def test_el_alias_es_simetrico(self):
        alias = gt.alias_de(REAL["anotada"])
        for nombre, otros in alias.items():
            for otro, tipo in otros:
                self.assertIn((nombre, tipo), alias[otro])

    def test_alias_sintetico(self):
        anotada = [{"nombre_ingles": n, "hex": h} for n, h in (("a", "#000000"), ("b", "#000000"), ("c", "#FFFFFF"))]
        self.assertEqual(gt.alias_de(anotada), {"a": [("b", "mismo_color")], "b": [("a", "mismo_color")], "c": []})

    def test_bandas_de_cercania(self):
        for delta, mismo_hex, esperado in (
                (9.0, True, "exacto"), (0.0, False, "alta"), (3.0, False, "alta"), (3.01, False, "media"),
                (8.0, False, "media"), (8.01, False, "baja"), (60.0, False, "baja")):
            self.assertEqual(gt.cercania_de(delta, mismo_hex), esperado, (delta, mismo_hex))

    def test_cercanias_sinteticas_y_desempate_alfabetico(self):
        anotada = [{"nombre_ingles": "grey", "hex": "#808080"}, {"nombre_ingles": "gray", "hex": "#808080"},
                   {"nombre_ingles": "red", "hex": "#FF0000"}]
        completa = [{"nombre_ingles": "Mid", "hex": "#808080"}, {"nombre_ingles": "Casi rojo", "hex": "#FE0000"},
                    {"nombre_ingles": "Negro", "hex": "#000000"}]
        resultado = gt.calcular_cercanias(completa, anotada)
        self.assertEqual(resultado[0], ("gray", 0.0, "exacto"), "gray y grey empatan: gana gray")
        self.assertEqual(resultado[1][0], "red")
        self.assertEqual(resultado[1][2], "alta")
        self.assertEqual(resultado[2][0], "gray")
        self.assertEqual(resultado[2][2], "baja")

    def test_ningun_alias_sale_como_color_mas_cercano(self):
        # Los segundos de cada par (cyan, magenta, las siete variantes …grey) nunca ganan el desempate.
        muestra = [{"nombre_ingles": f["nombre"], "hex": f["hex"]} for f in
                   ({"nombre": n, "hex": h} for n, h in (("A", "#00FFFF"), ("B", "#FF00FF"), ("C", "#A9A9A9"), ("D", "#2F4F4F")))]
        cercanos = {css for css, _, _ in gt.calcular_cercanias(muestra, REAL["anotada"])}
        self.assertTrue(cercanos.isdisjoint({"cyan", "magenta", "darkgrey", "darkslategrey"}), cercanos)

    def test_el_calculo_coincide_con_lo_guardado_en_nombres_completos(self):
        with gt.COMPLETA.open(encoding="utf-8", newline="") as f:
            guardadas = list(csv.DictReader(f))
        muestra = guardadas[::150]
        calculadas = gt.calcular_cercanias([{c: g[c] for c in gt.COLUMNAS_FUENTE_COMPLETA} for g in muestra], REAL["anotada"])
        self.assertGreater(len(muestra), 200)
        for guardada, (css, distancia, cercania) in zip(muestra, calculadas):
            self.assertEqual((guardada["css_cercano"], guardada["delta_e"], guardada["cercania"]),
                             (css, f"{distancia:.2f}", cercania), guardada["nombre_ingles"])


class Colorimetria(unittest.TestCase):
    def test_los_16_vectores_de_sharma_coinciden(self):
        self.assertEqual(len(colorimetria.VECTORES_SHARMA), 16)
        self.assertEqual(colorimetria.autoprueba(), [])

    def test_la_autoprueba_detecta_un_vector_que_no_coincide(self):
        lab1, lab2, esperado = colorimetria.VECTORES_SHARMA[0]
        with mock.patch.object(colorimetria, "VECTORES_SHARMA", [(lab1, lab2, esperado + 0.01)]):
            fallos = colorimetria.autoprueba()
        self.assertEqual(len(fallos), 1)
        self.assertEqual(fallos[0][:3], (lab1, lab2, esperado + 0.01))

    def test_hex_a_lab_en_los_extremos_y_los_primarios(self):
        for color, esperado in (("#000000", (0, 0, 0)), ("#FFFFFF", (100, 0, 0)),
                                ("#FF0000", (53.24, 80.09, 67.20)), ("#0000FF", (32.30, 79.19, -107.86))):
            for obtenido, previsto in zip(colorimetria.hex_a_lab(color), esperado):
                self.assertAlmostEqual(obtenido, previsto, delta=0.05, msg=color)

    def test_la_distancia_es_cero_consigo_misma_y_simetrica(self):
        rojo, azul, gris = (colorimetria.hex_a_lab(h) for h in ("#FF0000", "#0000FF", "#808080"))
        self.assertEqual(colorimetria.ciede2000(rojo, rojo), 0)
        for a, b in ((rojo, azul), (rojo, gris), (azul, gris)):
            self.assertAlmostEqual(colorimetria.ciede2000(a, b), colorimetria.ciede2000(b, a), places=9)

    def test_negro_y_blanco_estan_a_100(self):
        distancia = colorimetria.ciede2000(colorimetria.hex_a_lab("#000000"), colorimetria.hex_a_lab("#FFFFFF"))
        self.assertAlmostEqual(distancia, 100, delta=0.01)


class Rutas(unittest.TestCase):
    def test_las_pruebas_parchean_todas_las_rutas_del_generador(self):
        # Si generar-tabla.py gana una ruta y rutas_para() no la lista, main() escribiría en la carpeta real del proyecto.
        del_modulo = {nombre for nombre, valor in vars(gt).items() if nombre.isupper() and isinstance(valor, Path)}
        self.assertEqual(del_modulo, set(rutas_para(Path("/x"))))


class Codigo(unittest.TestCase):
    def test_los_py_no_llevan_caracteres_invisibles_literales(self):
        # Un guion blando, un espacio de ancho cero o un BOM dentro del código no se ven al leerlo y un editor
        # puede quitarlos sin avisar: se escriben como \uXXXX.
        for ruta in sorted(AQUI.glob("*.py")):
            for n, linea in enumerate(ruta.read_text(encoding="utf-8").splitlines(), start=1):
                invisibles = [c for c in linea if unicodedata.category(c) in ("Cf", "Zs") and c != " "]
                self.assertEqual(invisibles, [], f"{ruta.name} línea {n}")
