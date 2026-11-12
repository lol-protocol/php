"""Los archivos generados y main() de punta a punta en una carpeta temporal."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar_base()


def exportes_orden(idioma):
    return [c for c, _ in gt.IDIOMAS].index(idioma)


class ArchivosGenerados(unittest.TestCase):
    """Lo que genera main() sobre el proyecto reducido, comparado con los archivos del repositorio."""

    def test_salvo_nombres_completos_salen_identicos_a_los_del_repositorio(self):
        repositorio = [AQUI / "README.md", AQUI / "colores-por-idioma.md", AQUI / "referencias.md", AQUI / "ambiguedad.md",
                       AQUI / "categorias.md", AQUI / "cobertura.md", AQUI / "revisiones.md",
                       *sorted((AQUI / "por-idioma").glob("*")),
                       # El manifiesto de la carpeta reducida es otro (otro nombres-completos.csv): se prueba aparte.
                       *sorted(p for p in (AQUI / "exportes").rglob("*") if p.is_file() and p.name != "manifiesto.json")]
        self.assertEqual(len(repositorio), 7 + 30 + (3 + 30),
                         "siete archivos sueltos, 30 páginas por idioma, 3 exportes y 30 patrones de spaCy")
        for ruta in repositorio:
            relativa = ruta.relative_to(AQUI)
            generado = BASE["carpeta"] / relativa
            self.assertTrue(generado.exists(), f"main() no generó {relativa}")
            self.assertEqual(generado.read_text(encoding="utf-8"), ruta.read_text(encoding="utf-8"),
                             f"{relativa} del repositorio no coincide con lo que generan los CSV")

    def test_hay_una_pagina_por_idioma_y_el_indice_las_lista(self):
        codigos = [c for c, _ in gt.IDIOMAS]
        paginas = sorted(p.stem for p in (BASE["carpeta"] / "por-idioma").glob("*.md"))
        self.assertEqual(paginas, sorted(codigos))
        indice = (BASE["carpeta"] / "colores-por-idioma.md").read_text(encoding="utf-8")
        for codigo in codigos:
            self.assertIn(f"[por-idioma/{codigo}.md](por-idioma/{codigo}.md)", indice)

    def test_la_pagina_en_usa_la_forma_natural_y_las_demas_el_nombre(self):
        pagina = lambda codigo: (BASE["carpeta"] / "por-idioma" / f"{codigo}.md").read_text(encoding="utf-8")
        self.assertIn("| Inglés | Forma natural |", pagina("en"))
        self.assertIn("| `aliceblue` | alice blue |", pagina("en"))
        self.assertIn("| Inglés | Nombre |", pagina("es"))
        self.assertIn("No hay matices culturales registrados para este idioma", pagina("uk"))
        self.assertIn("| Color | Ámbito | Matiz | Solidez | Fuentes |", pagina("zh"))

    def test_la_tabla_del_readme_tiene_una_fila_por_color(self):
        texto = (BASE["carpeta"] / "README.md").read_text(encoding="utf-8")
        tabla = texto.split(gt.INICIO + "\n", 1)[1].split("\n" + gt.FIN, 1)[0]
        self.assertEqual(len(tabla.splitlines()), 2 + 148)

    def test_referencias_md_tiene_un_ancla_por_referencia(self):
        texto = (BASE["carpeta"] / "referencias.md").read_text(encoding="utf-8")
        for f in REAL["referencias"]:
            self.assertEqual(texto.count(f'<a id="{f["id"]}"></a>'), 1, f["id"])

    def test_nombres_completos_gana_las_tres_columnas_calculadas(self):
        with (BASE["carpeta"] / "nombres-completos.csv").open(encoding="utf-8", newline="") as f:
            lector = csv.DictReader(f)
            self.assertEqual(lector.fieldnames, gt.COLUMNAS_COMPLETA)
            filas = list(lector)
        self.assertGreater(len(filas), 100)
        self.assertTrue(all(f["cercania"] in ("exacto", "alta", "media", "baja") for f in filas))

    def test_colores_jsonl(self):
        lineas = (BASE["carpeta"] / "exportes" / "colores.jsonl").read_text(encoding="utf-8").splitlines()
        objetos = [json.loads(linea) for linea in lineas]
        self.assertEqual([o["id"] for o in objetos], [f["nombre_ingles"] for f in REAL["anotada"]])
        self.assertEqual({tuple(sorted(o)) for o in objetos}, {("alias", "categorias", "culturales", "evidencia", "forma_con_guion_en",
                                                                 "forma_natural_en", "hex", "id", "lexico", "nombres",
                                                                 "rgb", "significados")})
        alice = objetos[0]
        self.assertEqual(alice["rgb"], [240, 248, 255])
        self.assertEqual(alice["forma_con_guion_en"], "alice-blue")
        self.assertEqual(list(alice["nombres"]), [c for c, _ in gt.IDIOMAS])
        self.assertEqual(alice["nombres"]["en"], "alice blue")
        gris = next(o for o in objetos if o["id"] == "gray")
        self.assertEqual(gris["alias"], [{"id": "grey", "tipo": "ortografia"}])
        self.assertIn("es", gris["lexico"])
        lima = next(o for o in objetos if o["id"] == "lime")
        self.assertEqual({c: [x["categoria"] for x in lista] for c, lista in lima["categorias"].items()},
                         {"en": ["ADJ", "NOUN", "VERB"], "es": ["ADJ", "NOUN", "VERB"]})
        self.assertEqual(lima["categorias"]["es"][2], {"forma": "lima", "categoria": "VERB", "ocurrencia": "lima",
                                                       "ejemplo": "Ella lima las uñas antes de pintarlas."})
        self.assertEqual(next(o for o in objetos if o["id"] == "aliceblue")["categorias"], {})
        css = {f["nombre_ingles"] for f in REAL["anotada"]}
        propios = sum(1 for f in REAL["categorias"] if f["concepto"] in css)
        entradas = sum(len(lista) for o in objetos for lista in o["categorias"].values())
        self.assertGreaterEqual(entradas, propios)
        self.assertNotIn("lilac", [o["id"] for o in objetos], "los conceptos que no son CSS no entran")
        grey = next(o for o in objetos if o["id"] == "grey")
        self.assertEqual(grey["categorias"]["es"], next(o for o in objetos if o["id"] == "gray")["categorias"]["es"],
                         "el alias hereda las categorías que solo tenía su pareja")

    def test_gazetteer_tsv(self):
        lineas = (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()
        self.assertEqual(lineas[0].split("\t"), exportes.COLUMNAS_GAZETTEER)
        filas = [linea.split("\t") for linea in lineas[1:]]
        self.assertTrue(all(len(f) == len(exportes.COLUMNAS_GAZETTEER) for f in filas))
        self.assertEqual(len(filas), len(set(map(tuple, filas))), "sin filas repetidas")
        formas = [f[0] for f in filas]
        self.assertEqual(formas, sorted(formas, key=lambda x: (x.casefold(), x)))
        self.assertIn(["alice blue", "en", "css", "forma_natural", "aliceblue", "#F0F8FF", "", "alice blue", "", ""], filas)
        self.assertIn(["alice-blue", "en", "css", "forma_con_guion", "aliceblue", "#F0F8FF", "", "alice blue", "", ""], filas)
        self.assertIn(["aliceblue", "en", "css", "identificador", "aliceblue", "#F0F8FF", "", "aliceblue", "", ""], filas)
        self.assertTrue(any(f[1] == "es" and f[2] == "lexico" and f[3] == "lema" and f[4] == "red" for f in filas))

    def test_gazetteer_categorias(self):
        lineas = (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()
        filas = [linea.split("\t") for linea in lineas[1:]]
        self.assertEqual(exportes.COLUMNAS_GAZETTEER[8], "categorias", "va después de las ocho primeras: no las mueve")
        esperadas = {}
        for f in REAL["categorias"]:
            esperadas.setdefault((f["idioma"], f["concepto"], f["forma"]), []).append(f["categoria"])
        # Una forma tiene las categorías de categorias.csv para ella y su hex (así los alias gray y grey las comparten).
        hex_de = {(f[1], f[4]): f[5] for f in filas}
        por_hex = {}
        for (idioma, concepto, forma), lista in esperadas.items():
            por_hex.setdefault((idioma, forma, hex_de[(idioma, concepto)]), lista)
        for f in filas:
            self.assertEqual(f[8], ",".join(por_hex.get((f[1], f[0], f[5]), [])), f[:6])
        self.assertTrue(all(any(f[8] for f in filas if (f[1], f[4], f[0]) == clave) for clave in esperadas),
                        "cada palabra de categorias.csv sale con sus categorías")
        gris = {(f[4], f[8]) for f in filas if f[0] == "gris" and f[1] == "es" and f[2] == "css"}
        self.assertEqual(gris, {("gray", "ADJ,NOUN"), ("grey", "ADJ,NOUN")}, "el alias grey hereda las de gray")
        # Cada palabra de categorias.csv sale en el gazetteer como lema (o como nombre CSS en inglés).
        self.assertTrue(all(any(f[:5] == [forma, idioma, "lexico", "lema", concepto] or f[:5] == [forma, idioma, "css", "forma_natural", concepto]
                                for f in filas) for idioma, concepto, forma in esperadas))
        self.assertIn(["lima", "es", "lexico", "lema", "lime", "#00FF00", "", "lima", "ADJ,NOUN,VERB", "#AAFF32"], filas)
        # Las flexiones y las palabras de otros idiomas no traen categorías.
        self.assertTrue(all(f[8] == "" for f in filas if f[3] == "flexion" or f[1] not in ("en", "es")))

    def test_patrones_de_spacy(self):
        gazetteer = [linea.split("\t") for linea in
                     (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()[1:]]
        conceptos_validos = {f["nombre_ingles"] for f in REAL["anotada"]} | set(gt.CONCEPTOS)
        archivos = sorted(p.name for p in (BASE["carpeta"] / "exportes" / "spacy").iterdir())
        self.assertEqual(archivos, sorted(f"{c}.jsonl" for c, _ in gt.IDIOMAS))
        for codigo, _ in gt.IDIOMAS:
            with self.subTest(codigo):
                texto = (BASE["carpeta"] / "exportes" / "spacy" / f"{codigo}.jsonl").read_text(encoding="utf-8")
                self.assertTrue(texto.endswith("\n"))
                patrones = [json.loads(linea) for linea in texto.splitlines()]
                self.assertTrue(all(list(p) == ["label", "pattern", "id"] for p in patrones))
                self.assertEqual({p["label"] for p in patrones}, {"COLOR"})
                formas = [p["pattern"] for p in patrones]
                self.assertEqual(len(formas), len(set(formas)), "un patrón por forma")
                esperadas = {}
                for forma, idioma, _, _, concepto, *_ in gazetteer:
                    if idioma == codigo:
                        esperadas.setdefault(forma, set()).add(concepto)
                self.assertEqual(formas, list(esperadas), "las formas del gazetteer, en su orden")
                for p in patrones:
                    ids = p["id"].split(";")
                    self.assertEqual(ids, sorted(esperadas[p["pattern"]]), p)
                    self.assertLessEqual(set(ids), conceptos_validos, p)

    def test_patrones_de_spacy_con_formas_de_varios_conceptos(self):
        por_forma = {json.loads(l)["pattern"]: json.loads(l)["id"] for l in
                     (BASE["carpeta"] / "exportes" / "spacy" / "es.jsonl").read_text(encoding="utf-8").splitlines()}
        self.assertEqual(por_forma["verde lima"], "lime;limegreen")  # separados por «;» y en orden alfabético
        self.assertEqual(por_forma["azul claro"], "lightblue")
        self.assertEqual(por_forma["blancas"], "white", "las flexiones también")
        en = {json.loads(l)["pattern"]: json.loads(l)["id"] for l in
              (BASE["carpeta"] / "exportes" / "spacy" / "en.jsonl").read_text(encoding="utf-8").splitlines()}
        self.assertEqual((en["alice blue"], en["alice-blue"], en["aliceblue"]), ("aliceblue",) * 3)

    @unittest.skipUnless(importlib.util.find_spec("spacy"), "spaCy no está instalado")
    def test_los_patrones_cargan_en_spacy_y_reconocen_las_formas(self):
        import spacy  # noqa: PLC0415  (opcional: solo si está instalado)
        for codigo, texto, esperado in (("en", "She wore a light blue dress.", [("light blue", "lightblue")]),
                                        ("es", "Una bufanda verde lima.", [("verde lima", "lime;limegreen")]),
                                        ("zh", "她穿着一条浅蓝色的裙子。", [("浅蓝色", "lightblue")])):
            with self.subTest(codigo):
                nlp = spacy.blank(codigo)
                ruler = nlp.add_pipe("entity_ruler", config={"phrase_matcher_attr": "LOWER"})
                ruler.from_disk(BASE["carpeta"] / "exportes" / "spacy" / f"{codigo}.jsonl")
                self.assertEqual([(e.text, e.ent_id_) for e in nlp(texto).ents], esperado)

    def test_feats_ud(self):
        for rasgos, esperado in (("Gender=Fem;Number=Sing", "Gender=Fem|Number=Sing"),
                                 ("Number=Plur;Animacy=Anim", "Animacy=Anim|Number=Plur"),  # orden alfabético
                                 ("Number=Plur", "Number=Plur"), ("", "_"), ("Terminacion=-e", "_"),
                                 ("Gender=Fem;Terminacion=-e", "_"), ("region=GB", "_")):
            self.assertEqual(exportes._feats_ud(rasgos), esperado, rasgos)

    def test_lexico_ud(self):
        lineas = (BASE["carpeta"] / "exportes" / "lexico-ud.tsv").read_text(encoding="utf-8").splitlines()
        self.assertEqual(lineas[0].split("\t"), exportes.COLUMNAS_LEXICO_UD)
        filas = [linea.split("\t") for linea in lineas[1:]]
        self.assertTrue(all(len(f) == 6 for f in filas))
        self.assertEqual(len(filas), len(set(map(tuple, filas))), "sin filas repetidas")
        self.assertEqual({f[4] for f in filas}, {"en", "es"})
        self.assertLessEqual({f[2] for f in filas}, set(categorias.UPOS))
        # Cada fila de categorias.csv sale con su categoría (los nombres propios con su mayúscula inicial).
        for c in REAL["categorias"]:
            forma = c["ocurrencia"] if c["categoria"] == "PROPN" else c["forma"]
            self.assertIn([forma, forma, c["categoria"], "_", c["idioma"], c["concepto"]], filas)
        for forma, lema, upos, feats, idioma, concepto in filas:
            if upos == "PROPN":
                self.assertTrue(forma[0].isupper(), forma)
            if feats != "_":
                pares = feats.split("|")
                self.assertEqual(pares, sorted(pares), "FEATS en orden alfabético")
                self.assertLessEqual({p.split("=")[0] for p in pares}, exportes.RASGOS_UD)
        # Las flexiones del léxico salen solo como ADJ, con sus rasgos de UD.
        self.assertIn(["blancas", "blanco", "ADJ", "Gender=Fem|Number=Plur", "es", "white"], filas)
        self.assertNotIn(["blancas", "blanco", "NOUN", "Gender=Fem|Number=Plur", "es", "white"], filas)
        # Y las formas flexionadas de las frases de ejemplo, con su lema.
        self.assertIn(["blacked", "black", "VERB", "_", "en", "black"], filas)
        self.assertIn(["khakis", "khaki", "NOUN", "_", "en", "khaki"], filas)
        self.assertEqual(len([f for f in filas if f[0] == "lima"]), 3)
        orden = [(exportes_orden(f[4]), f[0].casefold(), f[0]) for f in filas]
        self.assertEqual(orden, sorted(orden), "ordenado por idioma y forma")

    def test_lexico_ud_con_una_ocurrencia_en_mayuscula_de_frase(self):
        # «Blacked» al empezar la frase es la misma forma que «blacked»; un nombre propio sí conserva la mayúscula.
        categoria = {"idioma": "en", "concepto": "black", "forma": "black", "categoria": "VERB", "ocurrencia": "Blacked", "ejemplo": "Blacked out."}
        propio = {"idioma": "en", "concepto": "peru", "forma": "peru", "categoria": "PROPN", "ocurrencia": "Peru", "ejemplo": "Peru."}
        texto = exportes.lexico_ud({"idiomas": [("en", "Inglés")], "lexico": [], "categorias": [categoria, propio]})
        self.assertEqual(texto.splitlines()[1:], ["black\tblack\tVERB\t_\ten\tblack", "blacked\tblack\tVERB\t_\ten\tblack",
                                                   "Peru\tPeru\tPROPN\t_\ten\tperu"])

    def test_ejemplos_conllu(self):
        texto = conllu_real()
        self.assertTrue(texto.endswith("\n\n") and not texto.endswith("\n\n\n"), "cada frase termina en una línea vacía")
        bloques = texto.rstrip("\n").split("\n\n")
        self.assertEqual(len(bloques), len(REAL["categorias"]))
        ids = []
        for bloque, fila in zip(bloques, REAL["categorias"]):
            lineas = bloque.split("\n")
            self.assertEqual(lineas[0], f"# sent_id = {fila['idioma']}-{fila['concepto']}-{fila['categoria']}")
            self.assertEqual(lineas[1], f"# text = {fila['ejemplo']}")
            ids.append(lineas[0])
            fichas = [linea.split("\t") for linea in lineas[2:]]
            self.assertTrue(all(len(f) == 10 for f in fichas), bloque)
            self.assertEqual([f[0] for f in fichas], [str(n) for n in range(1, len(fichas) + 1)])
            # Las fichas, con su SpaceAfter, reconstruyen la frase.
            rehecha = "".join(f[1] + ("" if f[9] == "SpaceAfter=No" else " ") for f in fichas).rstrip()
            self.assertEqual(rehecha, fila["ejemplo"])
            anotadas = [f for f in fichas if f[3] != "_"]
            self.assertEqual(len(anotadas), 1, bloque)
            lema = fila["ocurrencia"] if fila["categoria"] == "PROPN" else fila["forma"]
            self.assertEqual(anotadas[0][1:4], [fila["ocurrencia"], lema, fila["categoria"]])
            self.assertTrue(all(f[2] == "_" and f[4:9] == ["_"] * 5 for f in fichas if f[3] == "_"))
        self.assertEqual(len(ids), len(set(ids)), "sent_id únicos")

    def test_ejemplos_conllu_pega_los_signos(self):
        datos = {"categorias": [{"idioma": "en", "concepto": "aqua", "forma": "aqua", "categoria": "ADJ",
                                 "ocurrencia": "aqua", "ejemplo": "It is aqua, really!"}]}
        self.assertEqual(exportes.ejemplos_conllu(datos).splitlines(), [
            "# sent_id = en-aqua-ADJ", "# text = It is aqua, really!",
            "1\tIt\t_\t_\t_\t_\t_\t_\t_\t_", "2\tis\t_\t_\t_\t_\t_\t_\t_\t_",
            "3\taqua\taqua\tADJ\t_\t_\t_\t_\t_\tSpaceAfter=No", "4\t,\t_\t_\t_\t_\t_\t_\t_\t_",
            "5\treally\t_\t_\t_\t_\t_\t_\t_\tSpaceAfter=No", "6\t!\t_\t_\t_\t_\t_\t_\t_\t_", ""])

    def test_dos_alias_con_las_mismas_categorias_no_las_duplican_en_el_gazetteer(self):
        datos = datos_reales()
        copias = [dict(f, concepto="grey") for f in datos["categorias"] if f["idioma"] == "es" and f["concepto"] == "gray"]
        datos["categorias"] = datos["categorias"] + copias  # gray y grey (mismo hex) con las mismas filas para «gris»
        gris = [fila for fila in exportes.filas_gazetteer(datos) if fila[1] == "es" and fila[0] == "gris"]
        self.assertTrue(gris)
        self.assertEqual({fila[8] for fila in gris}, {"ADJ,NOUN"})

    def test_gazetteer_forma_normalizada(self):
        lineas = (BASE["carpeta"] / "exportes" / "gazetteer.tsv").read_text(encoding="utf-8").splitlines()
        filas = [linea.split("\t") for linea in lineas[1:]]
        self.assertEqual(exportes.COLUMNAS_GAZETTEER[7], "forma_normalizada", "va después de las siete primeras: no las mueve")
        for forma, idioma, *_, normalizada, _categorias, _cotidiano in filas:
            self.assertEqual(normalizada, normalizacion.normalizar(forma, idioma), (forma, idioma))
            self.assertNotEqual(normalizada, "", (forma, idioma))
        self.assertIn(["marrón", "es", "lexico", "lema", "brown"], [f[:5] for f in filas if f[7] == "marron"])


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
