"""Los archivos generados: su contenido, su orden y que coincidan con los del repositorio."""
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
