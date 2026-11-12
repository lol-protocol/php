"""La auditoría automática de nombres."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar_base()


def fila_completa(nombre, color="#000000", curada=""):
    return {"nombre_ingles": nombre, "hex": color, "curada": curada}


class Auditoria(unittest.TestCase):
    LIMPIOS = ["Plain Blue", "Sea Green", "Arctic Fox"]

    def ejemplos(self):
        """Un nombre que dispara cada comprobación (clave -> nombre). Los caracteres raros salen de chr() para que se vean."""
        return {
            "control": "Bad" + chr(7) + "Bell",
            "formato": "Zero" + chr(0x200B) + "Width",
            "espacios": "No" + chr(0xA0) + "Break",
            "espacios_dobles": "Two  Spaces",
            "no_nfc": "Cafe" + chr(0x301),
            "mojibake": "CafÃ©",
            "inicia_minuscula": "iCloud Blue",
            "apostrofo_ascii": "Don't Blue",
            "todo_mayusculas": "NASA",
            "inicia_digito": "7 Up",
            "frase": "One Two Three Four",
            "letras_no_ascii": "Crème",
            "apostrofo_tipografico": "Mann" + chr(0x2019) + "s Mint",
            "guion": "Ice-Blue",
        }

    def test_cada_comprobacion_marca_su_ejemplo_y_ninguna_marca_los_limpios(self):
        ejemplos = self.ejemplos()
        self.assertEqual(set(ejemplos), {clave for clave, _t, _p in auditoria.COMPROBACIONES}, "un ejemplo por comprobación")
        filas = [fila_completa(n) for n in [*ejemplos.values(), *self.LIMPIOS]]
        a = auditoria.analizar(filas)
        for clave, ejemplo in ejemplos.items():
            self.assertIn(ejemplo, a["comprobaciones"][clave], clave)
        for clave, nombres in a["comprobaciones"].items():
            for limpio in self.LIMPIOS:
                self.assertNotIn(limpio, nombres, clave)

    def test_los_defectos_son_las_primeras_comprobaciones(self):
        claves = [clave for clave, _t, _p in auditoria.COMPROBACIONES]
        self.assertEqual(claves[:auditoria.DEFECTOS], ["control", "formato", "espacios", "espacios_dobles", "no_nfc", "mojibake",
                                                        "inicia_minuscula", "apostrofo_ascii"])

    def test_duplicados_al_normalizar(self):
        a = auditoria.analizar([fila_completa(n) for n in ("Ice-Blue", "Ice Blue", "Icé Blue", "Fire Red", "Rose", "Rosé", "Rose Red")])
        self.assertEqual(a["duplicados"], [["Ice Blue", "Ice-Blue", "Icé Blue"], ["Rose", "Rosé"]])

    def test_hex_repetidos(self):
        a = auditoria.analizar([fila_completa("B Red", "#FF0000"), fila_completa("A Red", "#FF0000"), fila_completa("Blue", "#0000FF"),
                                fila_completa("Z Grey", "#808080"), fila_completa("Y Grey", "#808080")])
        self.assertEqual(a["hex_repetidos"], [("#808080", ["Y Grey", "Z Grey"]), ("#FF0000", ["A Red", "B Red"])])

    def test_curadas_y_signos(self):
        a = auditoria.analizar([fila_completa("Abc 12 & 3", curada="x"), fila_completa("Q&A"), fila_completa("Hi-Fi")])
        self.assertEqual(a["curadas"], {"Abc 12 & 3"})
        self.assertEqual(a["signos"], {"0-9": ["Abc 12 & 3"], "&": ["Abc 12 & 3", "Q&A"], "-": ["Hi-Fi"]}, "un nombre cuenta una vez por signo")

    def test_los_mas_largos_por_longitud_y_luego_por_nombre(self):
        nombres = ["aaa", "bbbbb", "Ccccc", "dd", "eeeeee", "ffffff", "g", "hhhhhhh"]
        a = auditoria.analizar([fila_completa(n) for n in nombres])
        self.assertEqual(a["mas_largos"], ["hhhhhhh", "eeeeee", "ffffff", "bbbbb", "Ccccc"])

    def test_cifras_con_formato_espanol(self):
        self.assertEqual(auditoria._miles(31935), "31.935")
        self.assertEqual(auditoria._miles(7), "7")
        self.assertEqual(auditoria._porcentaje(4966, 31935), "15,6 %")
        self.assertEqual(auditoria._porcentaje(0, 0), "0 %")

    def test_el_informe_dice_cuando_no_hay_casos_y_corta_las_listas_largas(self):
        sin_nada = auditoria.informe([fila_completa("Plain Blue", "#111111"), fila_completa("Sea Green", "#222222")])
        self.assertIn("## Duplicados al normalizar", sin_nada)
        self.assertEqual(sin_nada.count("No hay casos."), 2, "duplicados y hex repetidos")
        muchos = []
        for i in range(27):  # 27 grupos de duplicados, cada nombre con su propio hex
            muchos += [fila_completa(f"Nombre{i:02d}", f"#{2 * i:06X}"), fila_completa(f"Nómbre{i:02d}", f"#{2 * i + 1:06X}")]
        texto = auditoria.informe(muchos)
        self.assertEqual(texto.count("| ... y 2 más |"), 1, "solo la lista de duplicados pasa de 25")
        self.assertIn("«Nombre24», «Nómbre24»", texto)
        self.assertNotIn("«Nombre25», «Nómbre25»", texto)
        self.assertTrue(texto.endswith("\n"))
        self.assertEqual(texto, auditoria.informe(muchos), "es determinista")

    def test_el_informe_escapa_las_barras(self):
        texto = auditoria.informe([fila_completa("A|B", "#111111"), fila_completa("Plain Blue", "#222222")])
        self.assertIn("«A&#124;B»", texto)
        self.assertNotIn("A|B", texto)

    def test_el_informe_del_repositorio_esta_al_dia(self):
        # nombres-completos.csv real, sin recalcular las distancias: es el mismo texto que genera main().
        self.assertEqual(auditoria.informe(REAL["completa"]), (AQUI / "auditoria-nombres.md").read_text(encoding="utf-8"))

    def test_el_informe_de_la_carpeta_reducida_cuenta_sus_nombres(self):
        texto = (BASE["carpeta"] / "auditoria-nombres.md").read_text(encoding="utf-8")
        self.assertIn("Revisa automáticamente los 244 nombres", texto)
        self.assertIn("Hex distintos: 244 para 244 nombres.", texto)
