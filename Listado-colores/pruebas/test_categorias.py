"""categorias.csv y categorias.py."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar()


class Categorias(PruebaConErrores):
    def validar(self, filas):
        errores = []
        categorias.validar(filas, real("anotada"), real("lexico"), [c for c, _ in gt.IDIOMAS], gt.CONCEPTOS, errores)
        return errores

    def test_las_filas_reales_cubren_ingles_y_espanol(self):
        filas = real("categorias")
        self.assertEqual({f["idioma"] for f in filas}, {"en", "es"})
        self.assertEqual({f["categoria"] for f in filas}, set(categorias.UPOS))
        self.assertEqual(len(filas), len(REAL["categorias"]))
        en = {f["concepto"] for f in filas if f["idioma"] == "en"}
        self.assertEqual(en, {f["nombre_ingles"] for f in REAL["anotada"] if " " not in f["forma_natural_en"]}
                         | {"lilac", "burgundy", "cream", "scarlet", "amber"})
        es = {f["concepto"] for f in filas if f["idioma"] == "es"}
        self.assertEqual(es, {f["concepto"] for f in REAL["lexico"]
                              if f["idioma"] == "es" and f["tipo"] == "lema" and " " not in f["forma"]})

    def test_cada_palabra_tiene_adjetivo_y_las_demas_son_lecturas_extra(self):
        # Una palabra de color siempre puede ser adjetivo (es lo que la hace palabra de color).
        for (idioma, concepto), lista in categorias.por_concepto(real("categorias")).items():
            self.assertIn("ADJ", [f["categoria"] for f in lista], (idioma, concepto))

    def test_idioma_o_concepto_desconocidos(self):
        for campo, valor in (("idioma", "xx"), ("concepto", "nocolor")):
            filas = real("categorias")
            filas[0][campo] = valor
            self.assertHayError(self.validar(filas), "idioma o concepto desconocido")

    def test_un_concepto_css_sin_forma_en_el_idioma(self):
        filas = real("categorias")
        filas[0].update(idioma="es", concepto="aliceblue")  # color CSS que no está en el léxico en español
        self.assertHayError(self.validar(filas), "aliceblue no tiene forma en es")

    def test_la_forma_debe_ser_la_del_color_o_la_del_lema(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="aqua", categoria="ADJ")
        filas[i]["forma"] = "Aqua"
        self.assertHayError(self.validar(filas), "la forma de aqua en en es 'aqua', no 'Aqua'")
        filas = real("categorias")
        i = primera(filas, idioma="es", concepto="lime", categoria="VERB")
        filas[i]["forma"] = "limón"
        self.assertHayError(self.validar(filas), "la forma de lime en es es 'lima', no 'limón'")

    def test_la_forma_debe_ser_una_sola_palabra(self):
        filas = real("categorias")
        i = primera(filas, idioma="es", concepto="red", categoria="ADJ")
        filas[i].update(concepto="lightblue", forma="azul claro", ocurrencia="azul claro", ejemplo="Pintó la pared azul claro.")
        errores = self.validar(filas)
        self.assertHayError(errores, "la forma debe ser una sola palabra")
        self.assertHayError(errores, "no son de una sola palabra")

    def test_categoria_desconocida(self):
        filas = real("categorias")
        filas[0]["categoria"] = "ADV"
        self.assertHayError(self.validar(filas), "categoría desconocida 'ADV'")

    def test_la_ocurrencia_es_la_forma_con_pocas_letras_mas(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="black", categoria="VERB")
        self.assertEqual(filas[i]["ocurrencia"], "blacked")  # dos letras más: vale
        filas[i].update(ocurrencia="blackened", ejemplo="They blackened the windows.")  # cuatro más: vale
        self.assertEqual(self.validar(filas), [])
        filas[i].update(ocurrencia="blackeners", ejemplo="The blackeners came.")  # seis más: no
        self.assertHayError(self.validar(filas), "debe ser la forma 'black' con a lo sumo 4 letras más")
        filas[i].update(ocurrencia="darken", ejemplo="They darken the windows.")
        self.assertHayError(self.validar(filas), "debe ser la forma 'black'")

    def test_un_nombre_propio_lleva_mayuscula(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="peru", categoria="PROPN")
        filas[i].update(ocurrencia="peru", ejemplo="She was born in peru.")
        self.assertHayError(self.validar(filas), "un nombre propio se escribe con mayúscula inicial")

    def test_ejemplo_vacio_con_barra_o_sin_punto_final(self):
        for ejemplo, esperado in (("", "ejemplo vacío"), (" He wore a beige coat.", "espacios sobrantes"),
                                  ("He wore a beige | coat.", "'|' o saltos"), ("He wore a\nbeige coat.", "saltos de línea"),
                                  ("He wore a beige coat", "debe terminar en")):
            with self.subTest(ejemplo):
                filas = real("categorias")
                i = primera(filas, idioma="en", concepto="beige", categoria="ADJ")
                filas[i]["ejemplo"] = ejemplo
                self.assertHayError(self.validar(filas), esperado)

    def test_toda_palabra_de_color_tiene_adjetivo(self):
        filas = [f for f in real("categorias") if not (f["idioma"] == "es" and f["concepto"] == "white" and f["categoria"] == "ADJ")]
        self.assertSoloError(self.validar(filas), "es/white no tiene la categoría ADJ")

    def test_los_signos_finales_validos(self):
        for final in ".!?":
            filas = real("categorias")
            i = primera(filas, idioma="en", concepto="beige", categoria="ADJ")
            filas[i]["ejemplo"] = f"He wore a beige coat{final}"
            self.assertEqual(self.validar(filas), [], final)

    def test_la_frase_contiene_la_ocurrencia_como_palabra_entera(self):
        filas = real("categorias")
        i = primera(filas, idioma="es", concepto="lime", categoria="VERB")
        for ejemplo in ("Ella pinta las uñas.", "Ella limaba las uñas.", "Ella sublima las uñas."):
            filas[i]["ejemplo"] = ejemplo
            self.assertHayError(self.validar(filas), "una vez como palabra entera, no 0")
        filas[i]["ejemplo"] = "Lima es la capital."  # la mayúscula inicial no es la ocurrencia 'lima': no vale
        self.assertHayError(self.validar(filas), "una vez como palabra entera, no 0")

    def test_la_ocurrencia_sale_una_sola_vez_y_es_una_palabra_sin_signos(self):
        # ejemplos.conllu marca un solo token por frase: una ocurrencia repetida o con guion no tendría un token claro.
        filas = real("categorias")
        i = primera(filas, idioma="es", concepto="lime", categoria="VERB")
        filas[i]["ejemplo"] = "Ella lima las uñas y lima la madera."
        self.assertSoloError(self.validar(filas), "una vez como palabra entera, no 2")
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="black", categoria="ADJ")
        filas[i].update(ocurrencia="black-", ejemplo="She wore a black- dress.")
        self.assertHayError(self.validar(filas), "debe tener solo letras y dígitos")

    def test_una_categoria_repetida_para_el_mismo_concepto(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="aqua", categoria="ADJ")
        filas.insert(i + 1, dict(filas[i]))
        self.assertHayError(self.validar(filas), "repetida en/aqua/ADJ")

    def test_el_orden_de_las_categorias_es_adj_noun_propn_verb(self):
        self.assertEqual(categorias.UPOS, ["ADJ", "NOUN", "PROPN", "VERB"])
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="olive", categoria="PROPN")
        verbo = dict(filas[i], categoria="VERB", ocurrencia="Olive", ejemplo="They Olive the walls.")
        filas.insert(i + 1, verbo)  # ADJ, NOUN, PROPN, VERB: vale
        self.assertEqual(self.validar(filas), [])
        filas[i], filas[i + 1] = filas[i + 1], filas[i]  # ADJ, NOUN, VERB, PROPN: no
        self.assertSoloError(self.validar(filas), "ordenadas por idioma, concepto")

    def test_el_orden_es_idioma_concepto_y_categoria(self):
        filas = real("categorias")
        i = primera(filas, idioma="en", concepto="aqua", categoria="ADJ")
        filas[i], filas[i + 1] = filas[i + 1], filas[i]  # NOUN antes que ADJ
        self.assertSoloError(self.validar(filas), "ordenadas por idioma, concepto")
        filas = real("categorias")
        filas = [f for f in filas if f["idioma"] == "es"] + [f for f in filas if f["idioma"] == "en"]
        self.assertSoloError(self.validar(filas), "ordenadas por idioma, concepto")

    def test_a_un_idioma_presente_no_le_puede_faltar_ninguna_palabra(self):
        filas = [f for f in real("categorias") if not (f["idioma"] == "es" and f["concepto"] == "lime")]
        self.assertSoloError(self.validar(filas), "a es le faltan 1 palabras (por ejemplo lime)")

    def test_un_idioma_ausente_no_da_error_de_cobertura(self):
        self.assertEqual(self.validar([f for f in real("categorias") if f["idioma"] == "en"]), [])
        self.assertEqual(self.validar([]), [])

    def test_a_ingles_le_faltan_los_colores_css_y_los_del_lexico(self):
        for concepto in ("azure", "lilac"):
            filas = [f for f in real("categorias") if not (f["idioma"] == "en" and f["concepto"] == concepto)]
            self.assertSoloError(self.validar(filas), f"a en le faltan 1 palabras (por ejemplo {concepto})")

    def test_las_formas_de_ejemplo_siguen_a_las_del_lexico(self):
        # Si el léxico cambia el lema de «lime» en español, categorias.csv deja de coincidir y lo dice.
        lexico = real("lexico")
        i = primera(lexico, idioma="es", concepto="lime", tipo="lema")
        lexico[i]["forma"] = "lima verde"
        errores = []
        categorias.validar(real("categorias"), real("anotada"), lexico, [c for c, _ in gt.IDIOMAS], gt.CONCEPTOS, errores)
        self.assertHayError(errores, "la forma de lime en es es 'lima verde', no 'lima'")

    def test_el_informe_cuenta_las_categorias_de_los_datos_reales(self):
        filas = real("categorias")
        texto = categorias.informe(filas, gt.IDIOMAS)
        for codigo, nombre in (("en", "Inglés"), ("es", "Español")):
            propias = [f for f in filas if f["idioma"] == codigo]
            cuentas = [sum(1 for f in propias if f["categoria"] == u) for u in categorias.UPOS]
            palabras = {f["concepto"] for f in propias}
            varias = sum(1 for c in palabras if sum(1 for f in propias if f["concepto"] == c) > 1)
            fila = f"| {nombre} (`{codigo}`) | {len(palabras)} | " + " | ".join(map(str, cuentas)) + f" | {varias} |"
            self.assertIn(fila, texto)
            self.assertIn(f"## {nombre} (`{codigo}`)", texto)
        self.assertNotIn("(`fr`)", texto)
        self.assertIn("| `lime` | lima | `VERB` | Ella lima las uñas antes de pintarlas. |", texto)

    def test_la_nota_de_los_nueve_nombres_css_coincide_con_los_ejemplos(self):
        # Si se agrega o se cambia un ejemplo de ADJ de contexto web, la nota del informe y del README deben seguirlo.
        web = re.compile(r"\b(page|header|panel|button|text color|border)\b", re.I)
        nueve = sorted(f["concepto"] for f in REAL["categorias"] if f["idioma"] == "en" and f["categoria"] == "ADJ" and web.search(f["ejemplo"]))
        informe = categorias.informe(real("categorias"), gt.IDIOMAS)
        readme = (AQUI / "README.md").read_text(encoding="utf-8")
        self.assertEqual(len(nueve), 9)
        for nombre in nueve:
            self.assertIn(f"`{nombre}`", informe.split("Nueve nombres CSS")[1].split("\n")[0])
            self.assertIn(f"`{nombre}`", readme.split("Nueve nombres CSS")[1].split("\n")[0])

    def test_las_cifras_del_lexico_en_el_readme_son_las_de_los_datos(self):
        # El README repetia 18 conceptos de suajili, 2.400 filas y 5.785 patrones de spaCy cuando los datos ya decian otra cosa.
        readme = (AQUI / "README.md").read_text(encoding="utf-8")
        miles = lambda n: f"{n:,}".replace(",", ".")
        filas = real("lexico")
        conceptos_sw = len({f["concepto"] for f in filas if f["idioma"] == "sw"})
        lemas = sum(1 for f in filas if f["tipo"] == "lema")
        self.assertIn(f"El suajili tiene {conceptos_sw} conceptos", readme)
        self.assertIn(f"Hay {miles(len(filas))} filas: {miles(lemas)} lemas", readme)
        patrones = sum(len((AQUI / "exportes" / "spacy" / f"{i}.jsonl").read_text(encoding="utf-8").splitlines()) for i, _ in gt.IDIOMAS)
        self.assertIn(f"{miles(patrones)} en total", readme)

    def test_las_demas_cifras_del_readme_son_las_de_los_datos(self):
        # Cada cifra que el README repite a mano se compara con la calculada: si un dato cambia y el README no, falla aquí.
        readme = (AQUI / "README.md").read_text(encoding="utf-8")
        miles = lambda n: f"{n:,}".replace(",", ".")
        datos = datos_reales()
        gazetteer = len(exportes.filas_gazetteer(datos))
        colisiones = cobertura.colisiones_nombres(gt.IDIOMAS, REAL["anotada"])
        compartidos = cobertura.lemas_compartidos(gt.IDIOMAS, REAL["lexico"], datos["hex_concepto"])
        huecos = cobertura.huecos_lexico(gt.IDIOMAS, REAL["lexico"], gt.CONCEPTOS)
        sin_categorias = len({c for c, _ in gt.IDIOMAS} - {f["idioma"] for f in REAL["categorias"]})
        palabras = {(f["idioma"], f["concepto"]) for f in REAL["categorias"]}
        esperadas = [
            f"{miles(gazetteer)} filas separadas",
            f"({miles(len(REAL['significados']))} filas)",
            f"{len(REAL['categorias'])} filas: las categorías gramaticales",
            f"Las {len(REAL['referencias'])} fuentes",
            f"{len(REAL['culturales'])} matices por ámbito cultural",
            f"{miles(len(REAL['completa']))} nombres de color con su hex",
            f"{len(palabras)} palabras y {len(REAL['categorias'])} filas",
            f"({sin_categorias} de {len(gt.IDIOMAS)})",
            f"{sum(len(v) for v in colisiones.values())} casos en {len(colisiones)} idiomas",
            f"{sum(len(v) for v in compartidos.values())} casos en {len(compartidos)} idiomas",
            f"`sw` tiene {len(gt.CONCEPTOS) - len(huecos['sw'])} de {len(gt.CONCEPTOS)}",
            f"{len(cobertura.propuestas_ia(REAL['lexico']))} de los huecos",
        ]
        for frase in esperadas:
            self.assertIn(frase, readme, frase)

    def test_el_readme_dice_los_mismos_casos_de_colision_que_el_archivo_revisado(self):
        with (AQUI / "colisiones-revisadas.csv").open(encoding="utf-8", newline="") as archivo:
            revisadas = len(list(csv.DictReader(archivo)))
        self.assertIn(f"los {revisadas} casos en que un idioma llama igual", (AQUI / "README.md").read_text(encoding="utf-8"))

    def test_el_informe_escapa_la_barra_vertical(self):
        filas = [dict(real("categorias")[0], ejemplo="Una | barra.")]
        self.assertIn("Una &#124; barra.", categorias.informe(filas, gt.IDIOMAS))
