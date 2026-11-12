"""hex cotidiano, cálculos de una sola vez, celdas Markdown y el informe de cobertura."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar()


class HexCotidiano(unittest.TestCase):
    def test_solo_cuentan_las_entradas_curadas_con_el_mismo_nombre(self):
        completa = [{"nombre_ingles": "Lime", "hex": "#AAFF32", "curada": "x"},
                    {"nombre_ingles": "Navy", "hex": "#01153E", "curada": ""},
                    {"nombre_ingles": "limes", "hex": "#000000", "curada": "x"}]
        anotada = [{"nombre_ingles": "lime", "hex": "#00FF00"}, {"nombre_ingles": "green", "hex": "#008000"}]
        self.assertEqual(gt.hex_cotidiano_de_concepto("lime", completa, anotada), "#AAFF32", "sin distinguir mayúsculas")
        self.assertEqual(gt.hex_cotidiano_de_concepto("navy", completa, anotada), "", "una entrada no curada no vale")
        self.assertEqual(gt.hex_cotidiano_de_concepto("red", completa, anotada), "", "sin entrada: vacío, no el hex CSS")

    def test_una_entrada_que_es_el_hex_de_otro_color_css_se_descarta(self):
        anotada = [{"nombre_ingles": "lime", "hex": "#00FF00"}, {"nombre_ingles": "green", "hex": "#008000"}]
        completa = [{"nombre_ingles": "Green", "hex": "#00FF00", "curada": "x"}, {"nombre_ingles": "Lime", "hex": "#00FF00", "curada": "x"}]
        self.assertEqual(gt.hex_cotidiano_de_concepto("green", completa, anotada), "", "#00FF00 es lime de CSS, no un verde corriente")
        self.assertEqual(gt.hex_cotidiano_de_concepto("lime", completa, anotada), "#00FF00", "el propio color CSS no se descarta")

    def test_valores_reales_que_motivan_la_columna(self):
        for concepto, esperado in (("lime", "#AAFF32"), ("khaki", "#C3B091"), ("violet", "#9A0EEA"), ("green", "")):
            self.assertEqual(gt.hex_cotidiano_de_concepto(concepto, REAL["completa"], REAL["anotada"]), esperado, concepto)
        self.assertEqual(gt.hex_cotidiano_de_concepto("navy", REAL["completa"], REAL["anotada"]), "", "navy no está curado")

    def test_el_hex_de_referencia_no_cambia(self):
        # El hex CSS sigue siendo el de referencia: la columna nueva no lo reemplaza.
        self.assertEqual(gt.hex_de_concepto("lime", REAL["anotada"], REAL["completa"]), "#00FF00")

    def test_sqlite_lleva_la_columna_en_conceptos(self):
        conexion = exportes.construir_sqlite(":memory:", {**datos_reales(), "completa": [], "cercanias": []})
        self.addCleanup(conexion.close)
        self.assertEqual(conexion.execute("SELECT hex, hex_cotidiano FROM conceptos WHERE concepto = 'lime'").fetchone(), ("#00FF00", "#AAFF32"))
        self.assertEqual(conexion.execute("SELECT hex_cotidiano FROM conceptos WHERE concepto = 'navy'").fetchone(), ("",))


class UnaSolaVez(unittest.TestCase):
    """Lo que main() calcula una vez y reparte da lo mismo que calcularlo cada quien por su cuenta."""

    def test_el_gazetteer_y_los_patrones_con_filas_ya_calculadas_son_iguales(self):
        datos = datos_reales()
        filas = exportes.filas_gazetteer(datos)
        self.assertEqual(exportes.gazetteer_tsv(datos, filas), exportes.gazetteer_tsv(datos))
        self.assertEqual(exportes.spacy_patrones(datos, filas), exportes.spacy_patrones(datos))

    def test_una_pagina_y_la_validacion_de_alias_con_el_alias_ya_calculado_son_iguales(self):
        datos = datos_reales()
        for codigo, etiqueta in (("es", "Español"), ("ja", "Japonés")):
            args = (codigo, etiqueta, datos["anotada"], datos["significados"], datos["lexico"], REAL["completa"], datos["evidencia"],
                    datos["culturales"])
            self.assertEqual(gt.pagina_idioma(*args, datos["alias"]), gt.pagina_idioma(*args))
        e1, e2 = [], []
        gt.validar_alias(datos["anotada"], real("significados"), e1, datos["alias"])
        gt.validar_alias(datos["anotada"], real("significados"), e2)
        self.assertEqual((e1, e2), ([], []))

    def test_construir_datos_trae_todo_lo_que_piden_los_consumidores(self):
        datos = datos_reales()
        self.assertEqual(sorted(datos), ["alias", "anotada", "categorias", "cercanias", "completa", "culturales", "evidencia",
                                          "hex_concepto", "hex_cotidiano", "idiomas", "lexico", "referencias", "significados"])
        self.assertEqual(set(datos["hex_concepto"]), set(gt.CONCEPTOS))
        self.assertEqual(set(datos["hex_cotidiano"]), set(gt.CONCEPTOS))


class CeldaMarkdown(unittest.TestCase):
    def test_escapa_la_barra_y_los_saltos_de_linea(self):
        import md  # noqa: PLC0415
        self.assertEqual(md.celda("a|b\nc"), "a&#124;b c")
        self.assertEqual(md.celda(5), "5")

    def test_texto_valido(self):
        import md  # noqa: PLC0415
        for valor, kwargs, esperado in (
                ("hola", {}, True), ("", {}, False), ("   ", {}, False), ("a|b", {}, False), ("a\nb", {}, False),
                (" hola", {}, True), (" hola", {"sin_espacios_sobrantes": True}, False), ("hola ", {"sin_espacios_sobrantes": True}, False),
                ("a|b", {"prohibidos": "\n"}, True), ("a\nb", {"prohibidos": "\n"}, False), ('di "x"', {"prohibidos": '|\n"'}, False),
                ('di "x"', {}, True)):
            self.assertEqual(md.texto_valido(valor, **kwargs), esperado, (valor, kwargs))

    def test_los_validadores_siguen_dando_los_mismos_avisos(self):
        # Antes de usar md.texto_valido cada uno tenía su condición; los casos límite no cambian.
        errores = []
        filas = real("lexico")
        filas[0]["forma"] = " blanco"
        gt.validar_lexico(filas, real("anotada"), REAL["completa"], errores)
        self.assertTrue(any("forma vacía, con espacios sobrantes" in e for e in errores))
        errores = []
        revision.validar([{"idioma": "es", "alcance": "nombres", "elemento": "red", "veredicto": "correcto", "revisor": "A|na",
                           "fecha": "2026-10-23", "huella": "0123abcd", "nota": "ok | si"}], ["es"], errores)
        self.assertEqual(errores, [], "la barra vertical es válida en el revisor y en la nota")

    def test_orden_idiomas_acepta_codigos_y_pares(self):
        import md  # noqa: PLC0415
        self.assertEqual(md.orden_idiomas(["es", "en"]), {"es": 0, "en": 1})
        self.assertEqual(md.orden_idiomas([("es", "Español"), ("en", "Inglés")]), {"es": 0, "en": 1})
        self.assertEqual(md.orden_idiomas(gt.IDIOMAS), {c: n for n, (c, _) in enumerate(gt.IDIOMAS)})
        self.assertEqual(md.orden_idiomas([]), {})

    def test_los_cuatro_informes_usan_la_misma_funcion(self):
        import md  # noqa: PLC0415
        for modulo in (ambiguedad, auditoria, categorias, revision):
            self.assertIs(modulo.celda, md.celda, modulo.__name__)
            self.assertFalse(hasattr(modulo, "_celda"), f"{modulo.__name__} conserva su copia")


class Cobertura(unittest.TestCase):
    def hex_concepto(self):
        return {c: gt.hex_de_concepto(c, REAL["anotada"], REAL["completa"]) for c in gt.CONCEPTOS}

    def test_huecos_del_lexico_reales(self):
        huecos = cobertura.huecos_lexico(gt.IDIOMAS, REAL["lexico"], gt.CONCEPTOS)
        self.assertEqual(sum(len(v) for v in huecos.values()), 33)
        self.assertEqual(len(huecos["sw"]), 11)
        self.assertNotIn("en", huecos)
        # coherencia con el validador: los 11 conceptos básicos nunca faltan
        self.assertTrue(all(c not in gt.BASICOS for lista in huecos.values() for c in lista))

    def test_huecos_con_datos_sinteticos(self):
        lexico = [{"idioma": "en", "concepto": "red", "tipo": "lema", "forma": "red", "rasgos": ""},
                  {"idioma": "en", "concepto": "blue", "tipo": "flexion", "forma": "blues", "rasgos": ""},
                  {"idioma": "es", "concepto": "red", "tipo": "lema", "forma": "rojo", "rasgos": ""},
                  {"idioma": "es", "concepto": "blue", "tipo": "lema", "forma": "azul", "rasgos": ""}]
        self.assertEqual(cobertura.huecos_lexico([("en", "Inglés"), ("es", "Español")], lexico, ["red", "blue"]), {"en": ["blue"]},
                         "una flexión no cuenta como lema")

    def test_colisiones_de_nombres_reales(self):
        colisiones = cobertura.colisiones_nombres(gt.IDIOMAS, REAL["anotada"])
        self.assertEqual(sum(len(v) for v in colisiones.values()), 29)
        self.assertEqual(len(colisiones), 18)
        self.assertNotIn("es", colisiones)
        self.assertIn(("סגול", ["purple", "violet"]), colisiones["he"])

    def test_las_propuestas_de_ia_son_las_filas_lema_con_origen_ia(self):
        lexico = [{"idioma": "sw", "concepto": "olive", "tipo": "lema", "forma": "zeituni", "rasgos": "origen=ia"},
                  {"idioma": "sw", "concepto": "red", "tipo": "lema", "forma": "nyekundu", "rasgos": ""},
                  {"idioma": "es", "concepto": "red", "tipo": "flexion", "forma": "rojos", "rasgos": "origen=ia"},
                  {"idioma": "es", "concepto": "blue", "tipo": "sinonimo", "forma": "x", "rasgos": "origen=ia"}]
        self.assertEqual(cobertura.propuestas_ia(lexico), [("sw", "olive", "zeituni")])
        reales = cobertura.propuestas_ia(REAL["lexico"])
        self.assertEqual(len(reales), 12)
        self.assertTrue(all(c in gt.CONCEPTOS for _, c, _ in reales))

    def test_las_propuestas_de_ia_no_repiten_el_lema_de_otro_concepto(self):
        # Una propuesta debe llenar un hueco, no sumar una ambigüedad (como «lilla» para `purple` y `lilac`).
        lemas = {}
        for f in REAL["lexico"]:
            if f["tipo"] == "lema":
                lemas.setdefault((f["idioma"], f["forma"].casefold()), []).append(f["concepto"])
        for idioma, concepto, lema in cobertura.propuestas_ia(REAL["lexico"]):
            self.assertEqual(lemas[(idioma, lema.casefold())], [concepto], (idioma, lema))

    def test_gray_y_grey_no_son_una_colision(self):
        anotada = [{"nombre_ingles": n, "forma_natural_en": n, "nombre_es": "gris", "hex": "#808080"} for n in ("gray", "grey")]
        self.assertEqual(cobertura.colisiones_nombres([("es", "Español")], anotada), {}, "mismo hex: es un alias")
        anotada[1]["hex"] = "#808081"
        self.assertEqual(cobertura.colisiones_nombres([("es", "Español")], anotada), {"es": [("gris", ["gray", "grey"])]})

    def test_nombres_iguales_solo_sin_distinguir_mayusculas(self):
        anotada = [{"nombre_ingles": "a", "forma_natural_en": "a", "nombre_es": "Azul", "hex": "#000001"},
                   {"nombre_ingles": "b", "forma_natural_en": "b", "nombre_es": "azul", "hex": "#000002"}]
        self.assertEqual(cobertura.colisiones_nombres([("es", "Español")], anotada), {"es": [("Azul", ["a", "b"])]})

    def test_una_colision_nueva_o_corregida_hace_fallar_la_lista_revisada(self):
        anotada = [{"nombre_ingles": n, "forma_natural_en": n, "nombre_es": "Azul", "hex": h} for n, h in (("a", "#000001"), ("b", "#000002"))]
        def errores(revisadas):
            salida = []
            gt.validar_colisiones(revisadas, anotada, salida, [("es", "Español")])
            return salida
        self.assertEqual(errores([{"idioma": "es", "nombre": "azul", "colores": "a|b"}]), [])
        self.assertIn("colisión nueva sin revisar", errores([])[0])
        self.assertIn("ya no existe", errores([{"idioma": "es", "nombre": "azul", "colores": "a|b"},
                                                {"idioma": "es", "nombre": "rojo", "colores": "c|d"}])[0])
        self.assertIn("repetidas", errores([{"idioma": "es", "nombre": "azul", "colores": "a|b"}] * 2)[0])

    def test_las_colisiones_reales_estan_todas_revisadas(self):
        errores = []
        gt.validar_colisiones(REAL["colisiones_revisadas"], REAL["anotada"], errores)
        self.assertEqual(errores, [])
        self.assertEqual(len(REAL["colisiones_revisadas"]), 29)

    def test_cada_colision_y_lema_compartido_de_cobertura_esta_en_la_lista_dentro_de_un_idioma_de_ambiguedad(self):
        # Los dos informes miran lo mismo desde dos sitios: que uno no liste algo que el otro no ve.
        datos = datos_reales()
        reales = ambiguedad.calcular(exportes.filas_gazetteer(datos), [c for c, _ in gt.IDIOMAS])
        dentro = {(e["idioma"], e["forma"].casefold()) for e in reales["dentro"]}
        for idioma, lista in cobertura.colisiones_nombres(gt.IDIOMAS, REAL["anotada"]).items():
            for nombre, _ in lista:
                self.assertIn((idioma, nombre.casefold()), dentro, ("colisión de nombre CSS", idioma, nombre))
        for idioma, lista in cobertura.lemas_compartidos(gt.IDIOMAS, REAL["lexico"], self.hex_concepto()).items():
            for lema, _ in lista:
                self.assertIn((idioma, lema.casefold()), dentro, ("lema compartido", idioma, lema))

    def test_cada_informe_remite_al_otro(self):
        self.assertIn("ambiguedad.md", cobertura.informe(gt.IDIOMAS, REAL["anotada"], REAL["lexico"], REAL["categorias"], gt.CONCEPTOS, self.hex_concepto()))
        self.assertIn("cobertura.md", (AQUI / "ambiguedad.md").read_text(encoding="utf-8"))

    def test_lemas_compartidos_reales(self):
        compartidos = cobertura.lemas_compartidos(gt.IDIOMAS, REAL["lexico"], self.hex_concepto())
        self.assertEqual(sum(len(v) for v in compartidos.values()), 25)  # las propuestas de IA no agregan ambigüedad
        self.assertIn(("violet", ["purple", "violet"]), compartidos["fr"])
        self.assertNotIn("es", compartidos)

    def test_un_lema_para_alias_no_es_compartido(self):
        lexico = [{"idioma": "es", "concepto": c, "tipo": "lema", "forma": "gris", "rasgos": ""} for c in ("gray", "grey")]
        self.assertEqual(cobertura.lemas_compartidos([("es", "Español")], lexico, {"gray": "#808080", "grey": "#808080"}), {})

    def test_el_informe_resume_lo_mismo_que_calculan_las_funciones(self):
        texto = cobertura.informe(gt.IDIOMAS, REAL["anotada"], REAL["lexico"], REAL["categorias"], gt.CONCEPTOS, self.hex_concepto())
        self.assertIn("17 idiomas tienen lema para todos; 13 tienen huecos (33 lemas en total).", texto)
        self.assertIn("2 idiomas (Inglés, Español); 28 sin ninguna.", texto)
        self.assertIn("29 casos en 18 idiomas.", texto)
        self.assertIn("25 casos en 17 idiomas.", texto)
        self.assertIn("| Suajili (`sw`) | 27 de 38 |", texto)
        self.assertIn("12 los llenó una propuesta de IA sin revisar", texto)
        self.assertIn("| Suajili (`sw`) | `turquoise` | feruzi |", texto)
        self.assertIn("| Español (`es`) | 38 de 38 | — |", texto)
        self.assertIn("| Hebreo (`he`) | סגול | `purple`, `violet` |", texto)
