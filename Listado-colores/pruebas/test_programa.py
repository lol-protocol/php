"""main() de punta a punta en una carpeta temporal: errores, --comprobar y las opciones a petición."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar_base()


class Programa(unittest.TestCase):
    """main() de punta a punta, en una copia temporal del proyecto reducido."""

    def setUp(self):
        temporal = tempfile.TemporaryDirectory()
        self.addCleanup(temporal.cleanup)
        self.carpeta = Path(temporal.name) / "proyecto"
        shutil.copytree(BASE["carpeta"], self.carpeta)

    def estado(self):
        return {p.relative_to(self.carpeta): p.read_bytes() for p in sorted(self.carpeta.rglob("*")) if p.is_file()}

    def reescribir(self, nombre, cambiar):
        ruta = self.carpeta / nombre
        ruta.write_text(cambiar(ruta.read_text(encoding="utf-8")), encoding="utf-8")

    def test_comprobar_pasa_con_todo_al_dia(self):
        codigo, salida, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual((codigo, error), (0, ""))
        self.assertIn("OK: 148 colores anotados en 30 idiomas, 4292 significados traducidos", salida)
        self.assertIn("archivos generados al día", salida)

    def test_generar_no_cambia_nada_si_todo_estaba_al_dia(self):
        antes = self.estado()
        codigo, salida, _ = ejecutar(self.carpeta)
        self.assertEqual(codigo, 0)
        self.assertIn("Generados README.md, nombres-completos.csv, colores-por-idioma.md y 30 páginas en por-idioma/", salida)
        self.assertEqual(self.estado(), antes)

    def test_un_archivo_generado_editado_a_mano_hace_fallar_comprobar(self):
        def en_la_tabla(texto):  # del README solo es generada la tabla entre marcadores
            inicio = texto.index(gt.INICIO)
            n = texto.index("aliceblue", inicio)
            return texto[:n] + "aliceblux" + texto[n + len("aliceblue"):]

        def al_final(texto):  # en los demás, cualquier cambio; al final para no tocar la cabecera de un CSV
            return texto + "x"

        def en_el_ultimo_campo(texto):  # una fila nueva sería un error de campos; esto cambia una columna calculada
            return texto.rstrip("\n") + "x\n"

        for nombre, editar in (("README.md", en_la_tabla), ("colores-por-idioma.md", al_final), ("referencias.md", al_final),
                               ("ambiguedad.md", al_final), ("auditoria-nombres.md", al_final), ("categorias.md", al_final), ("cobertura.md", al_final), ("revisiones.md", al_final), ("exportes/manifiesto.json", al_final), ("nombres-completos.csv", en_el_ultimo_campo), ("por-idioma/es.md", al_final),
                               ("por-idioma/ja.md", al_final), ("exportes/colores.jsonl", al_final),
                               ("exportes/gazetteer.tsv", al_final), ("exportes/spacy/es.jsonl", al_final),
                               ("exportes/lexico-ud.tsv", al_final)):
            with self.subTest(nombre):
                ruta = self.carpeta / nombre
                original = ruta.read_text(encoding="utf-8")
                try:
                    ruta.write_text(editar(original), encoding="utf-8")
                    codigo, _, error = ejecutar(self.carpeta, "--comprobar")
                finally:
                    ruta.write_text(original, encoding="utf-8")
                self.assertEqual(codigo, 1)
                self.assertIn("No coinciden con los CSV", error)
                self.assertIn(nombre, error)

    def test_editar_el_readme_fuera_de_la_tabla_no_hace_fallar_comprobar(self):
        self.reescribir("README.md", lambda t: "Una línea nueva en la prosa.\n\n" + t)
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_un_archivo_generado_borrado_hace_fallar_comprobar(self):
        (self.carpeta / "por-idioma" / "ko.md").unlink()
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("por-idioma/ko.md", error)

    def test_un_archivo_sobrante_hace_fallar_comprobar_y_generar_lo_borra(self):
        for carpeta, nombre in (("por-idioma", "xx.md"), ("exportes", "viejo.tsv")):
            (self.carpeta / carpeta / nombre).write_text("sobra", encoding="utf-8")
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("por-idioma/xx.md (sobra)", error)
        self.assertIn("exportes/viejo.tsv (sobra)", error)
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertFalse((self.carpeta / "por-idioma" / "xx.md").exists())
        self.assertFalse((self.carpeta / "exportes" / "viejo.tsv").exists())
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_un_archivo_sobrante_en_una_subcarpeta_de_exportes_tambien_se_detecta(self):
        (self.carpeta / "exportes" / "spacy" / "xx.jsonl").write_text("sobra", encoding="utf-8")
        (self.carpeta / "exportes" / "otra").mkdir()
        (self.carpeta / "exportes" / "otra" / "viejo.txt").write_text("sobra", encoding="utf-8")
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("exportes/spacy/xx.jsonl (sobra)", error)
        self.assertIn("exportes/otra/viejo.txt (sobra)", error)
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertFalse((self.carpeta / "exportes" / "spacy" / "xx.jsonl").exists())
        self.assertFalse((self.carpeta / "exportes" / "otra" / "viejo.txt").exists())
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_editar_un_csv_deja_los_archivos_desfasados_hasta_regenerar(self):
        self.reescribir("colores.csv", lambda t: t.replace("rojo", "rojísimo", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("No coinciden con los CSV", error)
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_un_csv_con_errores_sale_con_1_sin_tocar_nada(self):
        antes = self.estado()
        self.reescribir("colores.csv", lambda t: t.replace("#F0F8FF", "#f0f8ff", 1))
        antes["colores.csv"] = (self.carpeta / "colores.csv").read_bytes()
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual(codigo, 1)
        self.assertIn("Errores en los CSV", error)
        self.assertIn("colores.csv línea 2: hex inválido", error)
        self.assertEqual(salida, "")
        self.assertEqual({str(k): v for k, v in self.estado().items()}, {str(k): v for k, v in antes.items()})

    def test_con_mas_de_30_errores_muestra_30_y_cuenta_el_resto(self):
        def vaciar(texto):
            lineas = texto.splitlines(keepends=True)
            for n in range(1, 36):  # 35 filas de datos
                campos = next(csv.reader([lineas[n]]))
                campos[2] = ""  # nombre_es
                salida = io.StringIO()
                csv.writer(salida, lineterminator="\n").writerow(campos)
                lineas[n] = salida.getvalue()
            return "".join(lineas)
        self.reescribir("colores.csv", vaciar)
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertEqual(error.count("hay campos vacíos"), 30)
        self.assertIn("... y 5 errores más", error)

    def test_varios_csv_rotos_se_cuentan_todos_juntos(self):
        self.reescribir("lexico-basico.csv", lambda t: t.replace("concepto,", "concept,", 1))
        self.reescribir("categorias.csv", lambda t: t.replace("idioma,", "lang,", 1))
        lineas = (self.carpeta / "evidencia.csv").read_text(encoding="utf-8").splitlines(keepends=True)
        lineas[3] = lineas[3].rstrip("\n") + ",sobra\n"
        (self.carpeta / "evidencia.csv").write_text("".join(lineas), encoding="utf-8")
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual((codigo, salida), (1, ""))
        self.assertIn("No se pudieron leer los CSV", error)
        for esperado in ("lexico-basico.csv: cabecera inesperada", "categorias.csv: cabecera inesperada", "evidencia.csv"):
            self.assertIn(esperado, error)

    def test_cabecera_inesperada(self):
        self.reescribir("lexico-basico.csv", lambda t: t.replace("concepto,", "concept,", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("lexico-basico.csv: cabecera inesperada", error)

    def test_una_fila_con_mas_o_menos_campos_que_la_cabecera_es_un_error_en_cada_csv(self):
        # Antes, una fila corta rompía con un traceback en cuatro CSV y una larga se aceptaba en silencio en seis.
        for nombre, columnas in (("colores.csv", 34), ("significados-idiomas.csv", 4), ("lexico-basico.csv", 5),
                                 ("evidencia.csv", 5), ("significados-culturales.csv", 5), ("categorias.csv", 6), ("referencias.csv", 5),
                                 ("nombres-completos.csv", 6)):
            for variante in ("corta", "larga"):
                with self.subTest(nombre, variante=variante):
                    ruta = self.carpeta / nombre
                    original = ruta.read_text(encoding="utf-8")
                    lineas = original.splitlines(keepends=True)
                    lineas[2] = lineas[2].split(",")[0] + "\n" if variante == "corta" else lineas[2].rstrip("\n") + ",sobra\n"
                    try:
                        ruta.write_text("".join(lineas), encoding="utf-8")
                        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
                    finally:
                        ruta.write_text(original, encoding="utf-8")
                    self.assertEqual(codigo, 1)
                    self.assertIn(f"{nombre} línea 3: debe tener {columnas} campos", error)

    def test_cambiar_un_csv_desfasa_el_manifiesto(self):
        self.reescribir("colores.csv", lambda t: t.replace("rojo", "rojísimo", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("exportes/manifiesto.json", error)

    def poner_changelog(self, texto):
        """Un CHANGELOG propio: estas pruebas no dependen de lo que diga el real."""
        (self.carpeta / "CHANGELOG.md").write_text(texto, encoding="utf-8")

    def test_la_version_del_manifiesto_sale_del_changelog(self):
        self.poner_changelog("# Cambios\n\n## 1.0.0 - 2026-01-01\n\nPrimera.\n")
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertEqual(json.loads((self.carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))["version"], "1.0.0")
        self.poner_changelog("# Cambios\n\n## 1.1.0 - 2026-02-01\n\nUn cambio.\n\n## 1.0.0 - 2026-01-01\n\nPrimera.\n")
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("No coinciden con los CSV: exportes/manifiesto.json.", error, "solo el manifiesto cambia con la versión")
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertEqual(json.loads((self.carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8"))["version"], "1.1.0")
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_sin_changelog_es_un_error(self):
        (self.carpeta / "CHANGELOG.md").unlink()
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("CHANGELOG.md: no existe", error)

    def test_un_changelog_invalido_es_un_error_y_no_toca_nada(self):
        antes = self.estado()
        self.poner_changelog("# Cambios\n\n## 1.0 - 2026-01-01\n\nPrimera.\n")
        antes["CHANGELOG.md"] = (self.carpeta / "CHANGELOG.md").read_bytes()
        codigo, _, error = ejecutar(self.carpeta)
        self.assertEqual(codigo, 1)
        self.assertIn("Errores en los CSV", error)
        self.assertIn("CHANGELOG.md línea", error)
        self.assertEqual({str(k): v for k, v in self.estado().items()}, {str(k): v for k, v in antes.items()})

    def test_un_csv_con_finales_de_linea_crlf_da_el_mismo_manifiesto(self):
        # Un checkout de Windows con autocrlf: las sumas se calculan sobre el texto con los finales de línea unificados.
        for nombre in ("colores.csv", "lexico-basico.csv"):
            ruta = self.carpeta / nombre
            ruta.write_bytes(ruta.read_bytes().replace(b"\n", b"\r\n"))
        codigo, salida, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual((codigo, error), (0, ""), salida)

    def test_readme_sin_marcadores(self):
        self.reescribir("README.md", lambda t: t.replace(gt.INICIO, "<!-- otro -->"))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("faltan los marcadores", error)

    def test_una_mencion_de_los_marcadores_en_la_prosa_no_cuenta(self):
        self.reescribir("README.md", lambda t: t.replace(gt.INICIO, "x " + gt.INICIO + " x", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("faltan los marcadores", error)

    def test_una_referencia_sin_usar_es_un_error(self):
        sin_uso = "zzz-sin-uso,libro,Una obra que nadie cita,,existencia\n"
        self.reescribir("referencias.csv", lambda t: t.rstrip("\n") + "\n" + sin_uso)
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("la referencia 'zzz-sin-uso' no la usa ningún otro CSV", error)

    def test_sqlite_construye_la_base_y_no_regenera_nada(self):
        antes = self.estado()
        ruta = self.carpeta / "datos.db"
        for _ in range(2):  # la segunda vez la base ya existe y se reemplaza
            codigo, salida, error = ejecutar(self.carpeta, "--sqlite", str(ruta))
            self.assertEqual((codigo, error), (0, ""))
        self.assertIn("Base SQLite en", salida)
        conexion = sqlite3.connect(ruta)
        self.addCleanup(conexion.close)
        cuenta = lambda tabla: conexion.execute(f"SELECT count(*) FROM {tabla}").fetchone()[0]
        self.assertEqual({t: cuenta(t) for t in ("colores", "nombres", "significados", "evidencia", "referencias", "lexico")},
                         {"colores": 148, "nombres": 148 * 30, "significados": 148 * 30, "evidencia": 148,
                          "referencias": len(REAL["referencias"]), "lexico": len(REAL["lexico"])})
        self.assertEqual(cuenta("culturales"), len(REAL["culturales"]))
        self.assertEqual(cuenta("categorias"), len(REAL["categorias"]))
        self.assertEqual(conexion.execute(
            "SELECT categoria, ocurrencia FROM categorias WHERE forma = 'lima' AND idioma = 'es' ORDER BY categoria").fetchall(),
            [("ADJ", "lima"), ("NOUN", "lima"), ("VERB", "lima")])
        self.assertEqual(cuenta("nombres_completos"), len((self.carpeta / "nombres-completos.csv").read_text(encoding="utf-8").splitlines()) - 1)
        japones = next(f for f in REAL["significados"] if f["idioma"] == "ja" and f["nombre_ingles"] == "crimson")
        consulta = ("SELECT s.psicologico FROM nombres n JOIN significados s USING (color_id, idioma) "
                    "WHERE n.color_id = 'crimson' AND n.idioma = 'ja'")
        self.assertEqual(conexion.execute(consulta).fetchone()[0], japones["significado_psicologico"])
        # Buscar sin tildes ni mayúsculas: la forma normalizada encuentra «marrón» y «alice blue».
        self.assertIn(("marrón",), conexion.execute(
            "SELECT forma FROM lexico WHERE forma_normalizada = 'marron' AND idioma = 'es'").fetchall())
        self.assertEqual(conexion.execute(
            "SELECT nombre FROM nombres WHERE nombre_normalizado = 'alice blue' AND idioma = 'en'").fetchall(), [("alice blue",)])
        self.assertEqual(conexion.execute(
            "SELECT nombre, nombre_normalizado FROM nombres WHERE color_id = 'crimson' AND idioma = 'es'").fetchall(),
            [("carmesí", "carmesi")])
        conexion.close()
        ruta.unlink()
        self.assertEqual(self.estado(), antes)

    def test_sqlite_sin_ruta(self):
        codigo, _, error = ejecutar(self.carpeta, "--sqlite")
        self.assertEqual(codigo, 1)
        self.assertIn("--sqlite necesita la ruta de la base", error)

    def test_comprobar_falla_si_la_base_no_coincide_con_los_csv(self):
        original = exportes.construir_sqlite
        for tabla in ("lexico", "categorias"):
            def con_una_fila_menos(ruta, datos):
                conexion = original(ruta, datos)
                conexion.execute(f"DELETE FROM {tabla} WHERE rowid = (SELECT min(rowid) FROM {tabla})")
                return conexion

            with self.subTest(tabla), mock.patch.object(exportes, "construir_sqlite", con_una_fila_menos):
                codigo, _, error = ejecutar(self.carpeta, "--comprobar")
                self.assertEqual(codigo, 1)
                self.assertIn("La base SQLite no coincide con los CSV", error)
                esperadas = len(REAL[tabla])
                self.assertIn(f"{tabla}: {esperadas - 1} filas, se esperaban {esperadas}", error)

    def test_conllu_y_visor_se_construyen_a_peticion_y_no_tocan_el_proyecto(self):
        antes = self.estado()
        conllu, visor = self.carpeta / "c.conllu", self.carpeta / "v.html"
        antes_con_las_dos = {**antes, Path("c.conllu"): None, Path("v.html"): None}
        codigo, salida, error = ejecutar(self.carpeta, "--conllu", str(conllu))
        self.assertEqual((codigo, error), (0, ""))
        self.assertIn(f"{len(REAL['categorias'])} frases de ejemplo en CoNLL-U", salida)
        self.assertEqual(conllu.read_text(encoding="utf-8"), conllu_real())
        codigo, salida, error = ejecutar(self.carpeta, "--visor", str(visor))
        self.assertEqual((codigo, error), (0, ""))
        self.assertIn("Visor de revisión en", salida)
        self.assertEqual(visor.read_text(encoding="utf-8"), visor_real())
        despues = {k: v for k, v in self.estado().items() if k.name not in ("c.conllu", "v.html")}
        self.assertEqual(despues, antes, "no regeneran ni cambian nada más")
        self.assertEqual(set(self.estado()), set(antes_con_las_dos))

    def test_conllu_y_visor_sin_ruta_es_un_error(self):
        for bandera, que in (("--conllu", "del archivo CoNLL-U"), ("--visor", "del archivo HTML"), ("--sqlite", "de la base")):
            codigo, _, error = ejecutar(self.carpeta, bandera)
            self.assertEqual(codigo, 1, bandera)
            self.assertIn(f"{bandera} necesita la ruta {que}", error)

    def test_ya_no_se_guardan_el_conllu_ni_el_visor_y_uno_viejo_sobra(self):
        self.assertFalse((self.carpeta / "exportes" / "ejemplos.conllu").exists())
        self.assertFalse((self.carpeta / "visor-revision.html").exists())
        (self.carpeta / "exportes" / "ejemplos.conllu").write_text("viejo", encoding="utf-8")
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("exportes/ejemplos.conllu (sobra)", error, "un clon con el archivo viejo se entera de que ya no va")
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertFalse((self.carpeta / "exportes" / "ejemplos.conllu").exists())

    def test_las_revisiones_que_atender_se_avisan_por_stderr_sin_fallar(self):
        fila = "es,nombres,nocolor,correcto,Ana,2026-10-23,0123abcd,\n"
        self.reescribir("revisiones.csv", lambda t: t + fila)
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual(codigo, 0, "una revisión huérfana no rompe la generación")
        self.assertIn("Revisiones que atender (1 huérfana(s)): ver revisiones.md.", error)
        self.assertNotIn("Revisiones que atender", salida)
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 0)
        self.assertIn("1 huérfana(s)", error, "también al comprobar, que es lo que corre la CI")

    def test_sin_revisiones_que_atender_no_avisa(self):
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[2], "")

    def test_un_alias_con_otro_significado_sale_con_1(self):
        def cambiar(texto):
            frase = "Frescura, claridad, calma y comunicación; asociado a lo digital y moderno."
            self.assertEqual(texto.count(frase), 2, "aqua y cyan llevan el mismo texto")
            return texto.replace(frase, frase + " Y más.", 1)  # solo cambia la fila de aqua, la primera
        self.reescribir("colores.csv", cambiar)
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual((codigo, salida), (1, ""))
        self.assertIn("colores.csv: aqua y cyan tienen el mismo hex y deben tener los mismos significados en español", error)

    def test_un_categorias_csv_con_errores_sale_con_1_sin_tocar_nada(self):
        antes = self.estado()
        self.reescribir("categorias.csv", lambda t: t.replace(",ADJ,aqua,", ",ADV,aqua,", 1))
        antes[Path("categorias.csv")] = (self.carpeta / "categorias.csv").read_bytes()
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual(codigo, 1)
        self.assertIn("Errores en los CSV", error)
        self.assertIn("categorias.csv línea 2: categoría desconocida 'ADV'", error)
        self.assertEqual((salida, self.estado()), ("", antes))

    def test_un_revisiones_csv_con_errores_sale_con_1_sin_tocar_nada(self):
        antes = self.estado()
        self.reescribir("revisiones.csv", lambda t: t + "es,nombres,aliceblue,quizá,Ana,2026-10-23,0123abcd,\n")
        antes[Path("revisiones.csv")] = (self.carpeta / "revisiones.csv").read_bytes()
        codigo, salida, error = ejecutar(self.carpeta)
        self.assertEqual(codigo, 1)
        self.assertIn("revisiones.csv línea 2: veredicto desconocido 'quizá'", error)
        self.assertEqual((salida, self.estado()), ("", antes))

    def test_una_revision_nueva_cambia_el_informe_y_no_el_manifiesto(self):
        manifiesto_antes = (self.carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8")
        self.reescribir("revisiones.csv", lambda t: t + f"es,nombres,aliceblue,correcto,Ana,2026-10-23,{huellas_reales()[('es', 'nombres')]['aliceblue']},\n")
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("revisiones.md", error)
        self.assertNotIn("manifiesto.json", error, "el registro de revisiones no es parte de los datos del manifiesto")
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertIn("| Español (`es`) | 1 de 148 |", (self.carpeta / "revisiones.md").read_text(encoding="utf-8"))
        self.assertEqual((self.carpeta / "exportes" / "manifiesto.json").read_text(encoding="utf-8"), manifiesto_antes)
        self.assertEqual(ejecutar(self.carpeta, "--visor", str(self.carpeta / "v.html"))[0], 0)  # el visor no depende de las revisiones
        self.assertEqual((self.carpeta / "v.html").read_text(encoding="utf-8"), visor_real())
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_editar_categorias_csv_deja_categorias_md_desfasado_hasta_regenerar(self):
        self.reescribir("categorias.csv", lambda t: t.replace("She painted the bathroom walls aqua.",
                                                              "She painted the walls aqua.", 1))
        codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        for archivo in ("categorias.md", "exportes/colores.jsonl", "exportes/manifiesto.json"):  # el gazetteer no lleva las frases
            self.assertIn(archivo, error)
        self.assertNotIn("gazetteer.tsv", error)
        self.assertEqual(ejecutar(self.carpeta)[0], 0)
        self.assertIn("She painted the walls aqua.", (self.carpeta / "categorias.md").read_text(encoding="utf-8"))
        self.assertEqual(ejecutar(self.carpeta, "--comprobar")[0], 0)

    def test_si_la_autoprueba_de_color_falla_main_sale_con_1(self):
        with mock.patch.object(colorimetria, "VECTORES_SHARMA", [((50.0, 2.6772, -79.7751), (50.0, 0.0, -82.7485), 9.0)]):
            codigo, _, error = ejecutar(self.carpeta, "--comprobar")
        self.assertEqual(codigo, 1)
        self.assertIn("CIEDE2000 no coincide con 1 vectores de Sharma et al.", error)


if __name__ == "__main__":
    unittest.main()
