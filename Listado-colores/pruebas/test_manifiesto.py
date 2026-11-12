"""El CHANGELOG, el manifiesto y los ayudantes de generar-tabla.py."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar_base()


class Changelog(unittest.TestCase):
    VALIDO = ("# Título\n\nTexto.\n\n## 1.2.0 - 2026-03-01\n\nAlgo.\n\n## 1.1.0 - 2026-02-01\n\nOtra cosa.\n\n"
              "## 1.0.0 - 2026-01-01\n\nPrimera.\n")

    def leer(self, texto):
        errores = []
        return manifiesto.leer_changelog(texto, errores), errores

    def test_devuelve_la_primera_entrada(self):
        self.assertEqual(self.leer(self.VALIDO), ("1.2.0", []))

    def test_las_versiones_se_comparan_como_numeros(self):
        texto = "## 1.10.0 - 2026-02-01\n\nUna.\n\n## 1.9.0 - 2026-01-01\n\nOtra.\n"
        self.assertEqual(self.leer(texto), ("1.10.0", []))

    def test_el_changelog_real_es_valido_y_su_version_es_la_del_manifiesto(self):
        version, errores = self.leer((AQUI / "CHANGELOG.md").read_text(encoding="utf-8"))
        self.assertEqual(errores, [])
        self.assertEqual(version, json.loads((AQUI / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))["version"])

    def test_sin_entradas(self):
        self.assertEqual(self.leer("# Solo un título\n\nTexto.\n"), (None, ["CHANGELOG.md: no hay ninguna entrada «## X.Y.Z - AAAA-MM-DD»"]))

    def test_encabezados_mal_formados(self):
        for encabezado in ("## 1.0 - 2026-01-01", "## v1.0.0 - 2026-01-01", "## 1.0.0 – 2026-01-01", "## 01.0.0 - 2026-01-01",
                           "## 1.0.0 - 2026-1-1", "## Otra cosa"):
            version, errores = self.leer(f"{encabezado}\n\nTexto.\n")
            self.assertEqual(version, None, encabezado)
            self.assertTrue(any("el encabezado debe ser" in e for e in errores), (encabezado, errores))

    def test_fecha_que_no_existe(self):
        version, errores = self.leer("## 1.0.0 - 2026-02-30\n\nTexto.\n")
        self.assertEqual(version, None)
        self.assertTrue(any("fecha que no existe" in e for e in errores), errores)

    def test_las_versiones_deben_bajar(self):
        for arriba, abajo in (("1.0.0", "1.0.0"), ("1.0.0", "1.1.0"), ("1.9.0", "1.10.0")):
            _, errores = self.leer(f"## {arriba} - 2026-02-01\n\nUna.\n\n## {abajo} - 2026-01-01\n\nOtra.\n")
            self.assertTrue(any("las versiones deben bajar" in e for e in errores), (arriba, abajo, errores))

    def test_las_fechas_no_pueden_subir(self):
        _, errores = self.leer("## 1.1.0 - 2026-01-01\n\nUna.\n\n## 1.0.0 - 2026-02-01\n\nOtra.\n")
        self.assertTrue(any("las fechas no pueden subir" in e for e in errores), errores)
        _, errores = self.leer("## 1.1.0 - 2026-01-01\n\nUna.\n\n## 1.0.0 - 2026-01-01\n\nOtra.\n")
        self.assertEqual(errores, [], "el mismo día es válido")

    def test_una_entrada_sin_texto(self):
        _, errores = self.leer("## 1.1.0 - 2026-02-01\n\n## 1.0.0 - 2026-01-01\n\nOtra.\n")
        self.assertEqual(len(errores), 1)
        self.assertIn("línea 1: la entrada 1.1.0 no dice qué cambió", errores[0])


class Manifiesto(unittest.TestCase):
    @staticmethod
    def suma(ruta):
        return hashlib.sha256(ruta.read_text(encoding="utf-8").encode("utf-8")).hexdigest()

    def comprobar_contra_archivos(self, carpeta):
        datos = json.loads((carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))
        self.assertEqual(list(datos), ["version", "idiomas", "entradas", "exportes"])
        self.assertEqual(datos["idiomas"], [c for c, _ in gt.IDIOMAS])
        self.assertEqual([e["archivo"] for e in datos["entradas"]],
                         ["colores.csv", "significados-idiomas.csv", "lexico-basico.csv", "evidencia.csv",
                          "significados-culturales.csv", "categorias.csv", "referencias.csv", "nombres-completos.csv"])
        self.assertEqual([e["archivo"] for e in datos["exportes"]],
                         ["exportes/colores.jsonl", "exportes/gazetteer.tsv"]
                         + [f"exportes/spacy/{c}.jsonl" for c, _ in gt.IDIOMAS]
                         + ["exportes/lexico-ud.tsv"])
        for entrada in datos["entradas"] + datos["exportes"]:
            ruta = carpeta / entrada["archivo"]
            texto = ruta.read_text(encoding="utf-8")
            if ruta.suffix == ".conllu":
                filas = texto.count("# sent_id")  # una por frase
            else:
                filas = texto.count("\n") - (0 if ruta.suffix == ".jsonl" else 1)  # menos la cabecera
            self.assertEqual(entrada["sha256"], self.suma(ruta), entrada["archivo"])
            self.assertEqual(entrada["filas"], filas, entrada["archivo"])
        por_archivo = {e["archivo"]: e for e in datos["exportes"]}
        self.assertEqual(por_archivo["exportes/gazetteer.tsv"]["columnas"], exportes.COLUMNAS_GAZETTEER)
        self.assertEqual(por_archivo["exportes/lexico-ud.tsv"]["columnas"], exportes.COLUMNAS_LEXICO_UD)
        return datos

    def test_el_manifiesto_del_repositorio_coincide_con_sus_archivos(self):
        datos = self.comprobar_contra_archivos(AQUI)
        self.assertEqual({e["archivo"]: e["filas"] for e in datos["entradas"]}["colores.csv"], 148)

    def test_el_manifiesto_de_la_carpeta_reducida_coincide_con_sus_archivos(self):
        datos = self.comprobar_contra_archivos(BASE["carpeta"])
        self.assertEqual({e["archivo"]: e["filas"] for e in datos["entradas"]}["nombres-completos.csv"], 244)
        self.assertNotEqual(datos["entradas"][-1]["sha256"],
                            json.loads((AQUI / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))["entradas"][-1]["sha256"])

    def test_manifiesto_sintetico(self):
        texto = manifiesto.manifiesto("1.2.3", ["es", "en"], [("ñandú.csv", 2, "x\n", None)], [("exportes/t.tsv", 1, "ñandú", ["a", "b"])])
        self.assertTrue(texto.endswith("}\n"))
        self.assertIn('"archivo": "ñandú.csv"', texto, "sin escapar los caracteres no ASCII")
        esperado = {"version": "1.2.3", "idiomas": ["es", "en"],
                    "entradas": [{"archivo": "ñandú.csv", "filas": 2, "sha256": hashlib.sha256(b"x\n").hexdigest()}],
                    "exportes": [{"archivo": "exportes/t.tsv", "filas": 1, "columnas": ["a", "b"],
                                  "sha256": hashlib.sha256("ñandú".encode("utf-8")).hexdigest()}]}
        self.assertEqual(json.loads(texto), esperado)

    def test_las_sumas_cambian_con_cualquier_byte(self):
        self.assertNotEqual(manifiesto.sha256("a"), manifiesto.sha256("a "))
        self.assertEqual(manifiesto.sha256("a"), hashlib.sha256(b"a").hexdigest())


class Auxiliares(unittest.TestCase):
    def test_ids_de(self):
        self.assertEqual(gt.ids_de(""), [])
        self.assertEqual(gt.ids_de("a;b;;c"), ["a", "b", "c"])

    def test_etiqueta_base_con_y_sin_referencias(self):
        self.assertEqual(gt.etiqueta_base("derivado", "", gt.BASE_PSICOLOGICO, "../"), "derivado")
        self.assertEqual(gt.etiqueta_base("hay_estudios", "a;b", gt.BASE_PSICOLOGICO, "../"),
                         "hay estudios ([a](../referencias.md#a), [b](../referencias.md#b))")

    def test_leer_rechaza_una_cabecera_distinta(self):
        with tempfile.TemporaryDirectory() as carpeta:
            ruta = Path(carpeta) / "x.csv"
            ruta.write_text("a,b\n1,2\n", encoding="utf-8")
            self.assertEqual(gt.leer(ruta, ["a", "b"]), [{"a": "1", "b": "2"}])
            with self.assertRaises(SystemExit) as contexto:
                gt.leer(ruta, ["a", "c"])
            self.assertIn("cabecera inesperada", str(contexto.exception))

    def test_leer_rechaza_filas_con_mas_o_menos_campos_que_la_cabecera(self):
        with tempfile.TemporaryDirectory() as carpeta:
            ruta = Path(carpeta) / "x.csv"
            for fila in ("1", "1,2,3"):
                ruta.write_text(f"a,b\n1,2\n{fila}\n", encoding="utf-8")
                with self.assertRaises(SystemExit) as contexto:
                    gt.leer(ruta, ["a", "b"])
                self.assertIn("x.csv línea 3: debe tener 2 campos", str(contexto.exception), fila)
            ruta.write_text('a,b\n1,"2,3"\n', encoding="utf-8")  # con comillas, la coma es parte del texto
            self.assertEqual(gt.leer(ruta, ["a", "b"]), [{"a": "1", "b": "2,3"}])

    def test_leer_completa_acepta_tres_o_seis_columnas_y_nada_mas(self):
        with tempfile.TemporaryDirectory() as carpeta:
            ruta = Path(carpeta) / "completa.csv"
            with mock.patch.object(gt, "COMPLETA", ruta):
                ruta.write_text("nombre_ingles,hex,curada\nAlpha,#000000,x\n", encoding="utf-8")
                self.assertEqual(gt.leer_completa(), [{"nombre_ingles": "Alpha", "hex": "#000000", "curada": "x"}])
                ruta.write_text("nombre_ingles,hex,curada,css_cercano,delta_e,cercania\nAlpha,#000000,x,black,0.00,exacto\n",
                                encoding="utf-8")
                self.assertEqual(gt.leer_completa(), [{"nombre_ingles": "Alpha", "hex": "#000000", "curada": "x"}])
                ruta.write_text("nombre_ingles,hex\nAlpha,#000000\n", encoding="utf-8")
                with self.assertRaises(SystemExit):
                    gt.leer_completa()
