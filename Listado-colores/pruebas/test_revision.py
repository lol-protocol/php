"""El registro de revisiones, sus huellas, el informe y el visor."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar()


FILA_REVISADA = {"idioma": "es", "alcance": "nombres", "elemento": "aliceblue", "veredicto": "correcto",
                 "revisor": "Ana", "fecha": "2026-10-23", "nota": ""}

class Revision(PruebaConErrores):
    def validar(self, filas, **cambios):
        errores = []
        revision.validar(filas, [c for c, _ in gt.IDIOMAS], errores)
        return errores

    def fila(self, **campos):
        fila = {**FILA_REVISADA, **campos}
        fila.setdefault("huella", huellas_reales().get((fila["idioma"], fila["alcance"]), {}).get(fila["elemento"], "0123abcd"))
        return fila

    def test_el_registro_real_empieza_vacio(self):
        self.assertEqual(REAL["revisiones"], [], "nadie ha revisado nada todavía: si cambia, actualiza README y revisiones.md")

    def test_lo_que_se_puede_revisar(self):
        universo = universo_revisable()
        for codigo, _ in gt.IDIOMAS:
            self.assertEqual(len(universo[(codigo, "nombres")]), 148)
            self.assertEqual(universo[(codigo, "significados")], universo[(codigo, "nombres")])
            conceptos = list(dict.fromkeys(f["concepto"] for f in REAL["lexico"] if f["idioma"] == codigo))
            self.assertEqual(universo[(codigo, "lexico")], conceptos)
        self.assertEqual({i for i, a in universo if a == "categorias"}, {"en", "es"})
        self.assertEqual(len(universo[("en", "categorias")]), 61)
        self.assertEqual(len(universo[("es", "categorias")]), 32)
        self.assertNotIn(("fr", "categorias"), universo)

    def test_una_fila_valida_y_el_orden_no_importa(self):
        filas = [self.fila(), self.fila(elemento="red", veredicto="corregir", nota="mejor «rojo»"),
                 self.fila(idioma="en", elemento="blue"), self.fila(alcance="lexico", elemento="white"),
                 self.fila(alcance="categorias", elemento="lilac", idioma="en"), self.fila(revisor="Luis")]
        self.assertEqual(self.validar(filas), [])
        self.assertEqual(self.validar(filas[::-1]), [])

    def test_idioma_o_alcance_desconocidos_son_errores(self):
        self.assertSoloError(self.validar([self.fila(idioma="xx")]), "idioma desconocido 'xx'")
        self.assertSoloError(self.validar([self.fila(alcance="todo")]), "alcance desconocido 'todo'")

    def test_un_elemento_que_ya_no_existe_no_es_un_error_sino_una_revision_huerfana(self):
        # Se renombró o se quitó: romper la generación obligaría a borrar historia. Sale en revisiones.md.
        huerfanas = [self.fila(elemento="nocolor", huella="0123abcd"),
                     self.fila(alcance="lexico", elemento="aliceblue", huella="0123abcd"),  # un color CSS que no es del léxico
                     self.fila(alcance="categorias", idioma="fr", elemento="white", huella="0123abcd")]  # fr no tiene categorías
        self.assertEqual(self.validar(huerfanas), [])
        atencion, validados, con_dudas = revision.situacion(huerfanas, huellas_reales())
        self.assertEqual(atencion["huerfana"], huerfanas)
        self.assertEqual((validados, con_dudas), ({}, {}), "una revisión huérfana no cuenta como revisada")

    def test_el_informe_lista_las_huerfanas_sin_romperse_por_el_orden(self):
        filas = [self.fila(elemento="zzz", huella="0123abcd"), self.fila(elemento="aaa", huella="0123abcd", revisor="Luis"),
                 self.fila(elemento="red", veredicto="dudoso", nota="x"), self.fila(alcance="categorias", idioma="fr", elemento="white", huella="0123abcd")]
        texto = revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales())
        seccion = texto.split("### Revisiones de elementos que ya no existen")[1]
        self.assertLess(seccion.index("`aaa`"), seccion.index("`zzz`"), "al final de su lista, por nombre")
        self.assertIn("`fr` | `categorias` | `white`", seccion)
        self.assertIn("Estado: 0 de ", texto, "las huérfanas no cuentan")
        self.assertIn("y 1 con dudas o correcciones pendientes", texto, "solo cuenta la dudosa de `red`, que existe")

    def test_las_huerfanas_no_cuentan_en_conflictos_ni_en_caducadas(self):
        atencion, _, _ = revision.situacion([self.fila(elemento="nocolor", huella="0123abcd"),
                                          self.fila(elemento="nocolor", revisor="Luis", veredicto="corregir", nota="x", huella="0123abcd")],
                                         huellas_reales())
        self.assertEqual((len(atencion["huerfana"]), atencion["conflicto"], atencion["caducada"]), (2, [], []))

    def test_veredicto_desconocido(self):
        self.assertSoloError(self.validar([self.fila(veredicto="quizá")]), "veredicto desconocido 'quizá'")

    def test_el_revisor_no_puede_estar_vacio_ni_llevar_espacios_o_saltos(self):
        for revisor in ("", " ", " Ana", "Ana ", "A\nna"):
            self.assertSoloError(self.validar([self.fila(revisor=revisor)]), "revisor vacío")

    def test_la_fecha_es_aaaa_mm_dd_y_existe(self):
        for fecha in ("", "23/10/2026", "2026-1-5", "2026-10-23T10:00", "hoy"):
            self.assertSoloError(self.validar([self.fila(fecha=fecha)]), "la fecha debe ser AAAA-MM-DD")
        self.assertSoloError(self.validar([self.fila(fecha="2026-02-30")]), "fecha que no existe")
        self.assertEqual(self.validar([self.fila(fecha="2028-02-29")]), [], "29 de febrero de un año bisiesto")

    def test_la_nota_no_lleva_espacios_sobrantes_ni_saltos(self):
        for nota in (" x", "x ", "x\ny"):
            self.assertSoloError(self.validar([self.fila(nota=nota)]), "nota con espacios sobrantes")

    def test_corregir_y_dudoso_necesitan_nota_y_correcto_y_corregido_no(self):
        for veredicto in ("corregir", "dudoso"):
            self.assertSoloError(self.validar([self.fila(veredicto=veredicto)]), "necesita una nota")
            self.assertEqual(self.validar([self.fila(veredicto=veredicto, nota="algo")]), [])
        for veredicto in ("correcto", "corregido"):
            self.assertEqual(self.validar([self.fila(veredicto=veredicto)]), [])

    def test_una_fila_por_revisor_y_elemento(self):
        self.assertSoloError(self.validar([self.fila(), self.fila(veredicto="dudoso", nota="x")]),
                             "Ana ya revisó nombres/aliceblue en es")
        self.assertEqual(self.validar([self.fila(), self.fila(revisor="Luis")]), [], "otro revisor: vale")
        self.assertEqual(self.validar([self.fila(), self.fila(idioma="fr")]), [], "otro idioma: vale")
        self.assertEqual(self.validar([self.fila(), self.fila(alcance="significados")]), [], "otro alcance: vale")

    def test_el_informe_sin_filas(self):
        texto = revision.informe([], universo_revisable(), gt.IDIOMAS, huellas_reales())
        total = sum(len(v) for v in universo_revisable().values())
        self.assertIn(f"**Estado: ningún elemento revisado todavía (0 de {total}).**", texto)
        self.assertIn("| Español (`es`) | 0 de 148 | 0 de 148 | 0 de 38 | 0 de 32 |", texto)
        self.assertIn("| Francés (`fr`) | 0 de 148 | 0 de 148 | 0 de 38 | — |", texto)
        self.assertIn("| `correcto` | 0 |", texto)
        self.assertIn("No hay correcciones propuestas ni dudas por atender.", texto)

    def test_el_informe_con_filas(self):
        filas = [self.fila(), self.fila(revisor="Luis"),  # dos revisores del mismo elemento cuentan un elemento
                 self.fila(elemento="red", veredicto="corregir", nota="mejor | «rojo»", revisor="Zoe"),
                 self.fila(idioma="en", alcance="categorias", elemento="lilac", veredicto="dudoso", nota="¿verbo?", revisor="Ana"),
                 self.fila(elemento="aqua", veredicto="corregido", revisor="Ana")]
        texto = revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales())
        total = sum(len(v) for v in universo_revisable().values())
        self.assertIn(f"**Estado: 2 de {total} elementos validados y 2 con dudas o correcciones pendientes.**", texto)
        self.assertIn("| Español (`es`) | 2 de 148 (+1 con dudas) | 0 de 148 | 0 de 38 | 0 de 32 |", texto)
        self.assertIn("| Inglés (`en`) | 0 de 148 | 0 de 148 | 0 de 38 | 0 de 61 (+1 con dudas) |", texto)
        for veredicto, cuenta in (("correcto", 2), ("corregir", 1), ("corregido", 1), ("dudoso", 1)):
            self.assertIn(f"| `{veredicto}` | {cuenta} |", texto)
        self.assertNotIn("No hay correcciones propuestas", texto)
        pendientes = texto.split("## Pendientes")[1].split("## Atención")[0]
        self.assertIn("| `en` | `categorias` | `lilac` | `dudoso` | ¿verbo? | Ana | 2026-10-23 |", pendientes)
        self.assertIn("| `es` | `nombres` | `red` | `corregir` | mejor &#124; «rojo» | Zoe | 2026-10-23 |", pendientes)
        self.assertLess(pendientes.index("`lilac`"), pendientes.index("`red`"), "ordenados por idioma (en antes que es)")
        self.assertNotIn("aqua", pendientes, "lo corregido ya no está pendiente")

    def test_una_duda_no_infla_la_cobertura(self):
        # Un `dudoso` o un `corregir` dicen que algo puede estar mal: no suman como elemento validado.
        for veredicto in ("dudoso", "corregir"):
            _, validados, con_dudas = revision.situacion([self.fila(veredicto=veredicto, nota="x")], huellas_reales())
            self.assertEqual((validados, con_dudas), ({}, {("es", "nombres"): {"aliceblue"}}), veredicto)

    def test_un_elemento_con_correcto_y_dudoso_vigentes_no_esta_validado(self):
        _, validados, con_dudas = revision.situacion([self.fila(), self.fila(revisor="Luis", veredicto="dudoso", nota="x")], huellas_reales())
        self.assertEqual((validados, con_dudas), ({}, {("es", "nombres"): {"aliceblue"}}))

    def test_el_aviso_cuenta_cada_tipo_y_calla_si_no_hay_nada(self):
        vacio = {"caducada": [], "ya_cambio": [], "sin_cambio": [], "conflicto": [], "huerfana": []}
        self.assertEqual(revision.aviso(vacio), "")
        self.assertEqual(revision.aviso({**vacio, "huerfana": [1, 2], "caducada": [3]}),
                         "Revisiones que atender (1 caducada(s), 2 huérfana(s)): ver revisiones.md.")

    def test_quitar_la_marca_de_ia_de_un_lema_no_caduca_su_revision(self):
        # Flujo documentado: un revisor confirma un lema propuesto por IA y quien mantiene quita `origen=ia`. La revisión debe seguir vigente.
        con_marca = datos_reales()
        sin_marca = datos_reales()
        i = primera(sin_marca["lexico"], idioma="sw", concepto="olive", tipo="lema")
        self.assertEqual(sin_marca["lexico"][i]["rasgos"], "origen=ia")
        sin_marca["lexico"][i]["rasgos"] = ""
        self.assertEqual(revision.huellas(con_marca)[("sw", "lexico")]["olive"], revision.huellas(sin_marca)[("sw", "lexico")]["olive"])
        # ...pero cambiar la palabra sí la caduca.
        sin_marca["lexico"][i]["forma"] = "otra"
        self.assertNotEqual(revision.huellas(con_marca)[("sw", "lexico")]["olive"], revision.huellas(sin_marca)[("sw", "lexico")]["olive"])

    def test_la_huella_es_fnv1a_de_32_bits(self):
        # Vectores de prueba publicados de FNV-1a de 32 bits.
        for texto, esperado in (("", "811c9dc5"), ("a", "e40c292c"), ("foobar", "bf9cf968")):
            self.assertEqual(revision.huella(texto), esperado, texto)
        self.assertEqual(revision.huella("ñandú"), revision.huella("ñandú"))
        self.assertNotEqual(revision.huella("ñandú"), revision.huella("nandu"))

    def test_la_huella_debe_ser_de_ocho_cifras_hexadecimales_en_minuscula(self):
        for huella in ("", "abc", "0123abcg", "0123ABCD", "0123abcd0"):
            self.assertSoloError(self.validar([self.fila(huella=huella)]), "la huella debe ser de 8 cifras")
        self.assertEqual(self.validar([self.fila(huella="0123abcd")]), [], "una huella de otro momento es válida: caduca, no es un error")

    def test_hay_una_huella_por_cada_elemento_revisable(self):
        huellas = huellas_reales()
        universo = universo_revisable()
        self.assertEqual({k: set(v) for k, v in huellas.items()}, {k: set(v) for k, v in universo.items()})
        self.assertTrue(all(len(h) == 8 for lista in huellas.values() for h in lista.values()))

    def test_la_huella_cambia_con_lo_que_ve_el_revisor_y_con_nada_mas(self):
        base = huellas_reales()
        for alcance, campo, cambiar in (
                ("nombres", "anotada", lambda d: d["anotada"][primera(d["anotada"], nombre_ingles="aliceblue")].update(nombre_es="otro")),
                ("significados", "anotada", lambda d: d["anotada"][primera(d["anotada"], nombre_ingles="aliceblue")].update(significado_mistico="otro")),
                ("lexico", "lexico", lambda d: d["lexico"][primera(d["lexico"], idioma="es", concepto="white", tipo="lema")].update(forma="otro")),
                ("categorias", "categorias", lambda d: d["categorias"][primera(d["categorias"], idioma="es", concepto="white", categoria="ADJ")].update(ejemplo="Otra frase."))):
            with self.subTest(alcance):
                datos = datos_reales()
                cambiar(datos)
                nuevas = revision.huellas(datos)
                cambiadas = {(k, e) for k, lista in nuevas.items() for e, h in lista.items() if h != base[k][e]}
                elemento = "aliceblue" if alcance in ("nombres", "significados") else "white"
                self.assertEqual(cambiadas, {(("es", alcance), elemento)})

    def test_la_huella_de_significados_en_otro_idioma_sale_de_significados_idiomas(self):
        datos = datos_reales()
        datos["significados"][("ja", "aliceblue")] = ("x", "y")
        self.assertEqual(revision.huellas(datos)[("ja", "significados")]["aliceblue"], revision.huella("x\ny"))

    def test_un_correcto_cuyo_dato_cambio_caduca_y_no_cuenta(self):
        filas = [self.fila(huella="0123abcd")]
        atencion, validados, con_dudas = revision.situacion(filas, huellas_reales())
        self.assertEqual(atencion["caducada"], filas)
        self.assertEqual((validados, con_dudas), ({}, {}))
        texto = revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales())
        self.assertIn("ningún elemento revisado todavía", texto)
        self.assertIn("### Revisiones `correcto` caducadas", texto)
        self.assertIn("| `es` | `nombres` | `aliceblue` | `correcto` | Ana | 2026-10-23 |", texto)

    def test_un_correcto_vigente_cuenta_y_no_pide_atencion(self):
        atencion, validados, con_dudas = revision.situacion([self.fila()], huellas_reales())
        self.assertEqual(atencion, {"caducada": [], "ya_cambio": [], "sin_cambio": [], "conflicto": [], "huerfana": []})
        self.assertEqual((validados, con_dudas), ({("es", "nombres"): {"aliceblue"}}, {}))

    def test_un_corregir_cuyo_dato_cambio_pide_marcarlo_corregido(self):
        filas = [self.fila(veredicto="corregir", nota="mejor otro", huella="0123abcd")]
        atencion, validados, con_dudas = revision.situacion(filas, huellas_reales())
        self.assertEqual(atencion["ya_cambio"], filas)
        self.assertEqual((validados, con_dudas), ({}, {("es", "nombres"): {"aliceblue"}}), "una corrección pendiente no es una revisión aprobada")
        self.assertIn("### Correcciones o dudas cuyo dato ya cambió", revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales()))

    def test_un_dudoso_cuyo_dato_cambio_tambien(self):
        atencion, _, _ = revision.situacion([self.fila(veredicto="dudoso", nota="x", huella="0123abcd")], huellas_reales())
        self.assertEqual(len(atencion["ya_cambio"]), 1)

    def test_un_corregir_vigente_no_pide_atencion_de_cambio(self):
        atencion, _, _ = revision.situacion([self.fila(veredicto="corregir", nota="x")], huellas_reales())
        self.assertEqual(atencion["ya_cambio"], [])

    def test_un_corregido_con_el_dato_igual_no_esta_aplicado(self):
        filas = [self.fila(veredicto="corregido")]
        atencion, validados, _ = revision.situacion(filas, huellas_reales())
        self.assertEqual(atencion["sin_cambio"], filas)
        self.assertEqual(validados, {("es", "nombres"): {"aliceblue"}}, "un corregido cuenta como validado, aunque avise")
        self.assertIn("### Filas `corregido` con el dato sin cambios", revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales()))

    def test_un_corregido_con_el_dato_distinto_esta_bien(self):
        atencion, validados, con_dudas = revision.situacion([self.fila(veredicto="corregido", huella="0123abcd")], huellas_reales())
        self.assertEqual(atencion, {"caducada": [], "ya_cambio": [], "sin_cambio": [], "conflicto": [], "huerfana": []})
        self.assertEqual((validados, con_dudas), ({("es", "nombres"): {"aliceblue"}}, {}))

    def test_revisores_en_desacuerdo(self):
        filas = [self.fila(), self.fila(revisor="Luis", veredicto="corregir", nota="mejor otro")]
        atencion, validados, con_dudas = revision.situacion(filas, huellas_reales())
        self.assertEqual(atencion["conflicto"], filas)
        self.assertEqual((validados, con_dudas), ({}, {("es", "nombres"): {"aliceblue"}}), "con desacuerdo, el elemento no está validado")
        texto = revision.informe(filas, universo_revisable(), gt.IDIOMAS, huellas_reales())
        self.assertIn("### Revisores en desacuerdo", texto)
        self.assertIn("| `es` | `nombres` | `aliceblue` | `correcto` | Ana | 2026-10-23 |", texto)
        self.assertIn("| `es` | `nombres` | `aliceblue` | `corregir` | Luis | 2026-10-23 |", texto)

    def test_dos_correcto_no_son_un_conflicto_ni_un_correcto_y_un_dato_de_otro_elemento(self):
        for filas in ([self.fila(), self.fila(revisor="Luis")],
                      [self.fila(), self.fila(revisor="Luis", elemento="red", veredicto="corregir", nota="x")]):
            self.assertEqual(revision.situacion(filas, huellas_reales())[0]["conflicto"], [])

    def test_un_correcto_y_un_corregir_ya_caducado_no_son_conflicto(self):
        filas = [self.fila(), self.fila(revisor="Luis", veredicto="corregir", nota="x", huella="0123abcd")]
        self.assertEqual(revision.situacion(filas, huellas_reales())[0]["conflicto"], [], "el corregir es de un dato que ya no existe")

    def test_sin_nada_que_atender_el_informe_lo_dice(self):
        texto = revision.informe([self.fila()], universo_revisable(), gt.IDIOMAS, huellas_reales())
        self.assertIn("Nada que atender: ninguna revisión caducada, sin aplicar ni en conflicto.", texto)

    def test_datos_del_visor(self):
        datos = datos_reales()
        v = revision.datos_visor(datos)
        self.assertEqual(v["huellas"]["es"]["nombres"]["aliceblue"], huellas_reales()[("es", "nombres")]["aliceblue"])
        self.assertNotIn("categorias", v["huellas"]["fr"], "fr no tiene categorías que revisar")
        self.assertEqual(v["idiomas"], [list(par) for par in gt.IDIOMAS])
        self.assertEqual(v["alcances"], list(revision.ALCANCES))
        self.assertEqual(v["veredictos"], list(revision.VEREDICTOS))
        self.assertEqual(list(v["colores"]), [f["nombre_ingles"] for f in REAL["anotada"]])
        alice = v["colores"]["aliceblue"]
        self.assertEqual((alice["hex"], alice["nombres"]["en"], alice["nombres"]["es"]), ("#F0F8FF", "alice blue", "azul alicia"))
        self.assertEqual(list(alice["nombres"]), [c for c, _ in gt.IDIOMAS])
        self.assertEqual(list(alice["significados"]), [c for c, _ in gt.IDIOMAS])
        original = next(f for f in REAL["anotada"] if f["nombre_ingles"] == "aliceblue")
        self.assertEqual(alice["significados"]["es"], [original["significado_psicologico"], original["significado_mistico"]])
        japones = next(f for f in REAL["significados"] if f["idioma"] == "ja" and f["nombre_ingles"] == "aliceblue")
        self.assertEqual(alice["significados"]["ja"], [japones["significado_psicologico"], japones["significado_mistico"]])
        self.assertEqual(v["lexico"]["es"]["white"]["formas"][0], ["blanco", "lema", ""])
        self.assertEqual(v["lexico"]["es"]["white"]["hex"], "#FFFFFF")
        self.assertEqual(v["categorias"]["en"]["lilac"][0][:2], ["lilac", "ADJ"])
        self.assertNotIn("fr", v["categorias"])

    def test_el_visor_lleva_los_datos_y_nada_externo(self):
        html = visor_real()
        plantilla = (AQUI / "visor-plantilla.html").read_text(encoding="utf-8")
        self.assertIn(revision.MARCA_DATOS, plantilla)
        self.assertNotIn(revision.MARCA_DATOS, html)
        self.assertEqual(html.count("<script>"), 1)
        self.assertNotRegex(html, r"(?i)(src|href)\s*=\s*[\"']?(https?:)?//", "sin recursos externos")
        self.assertNotRegex(html, r"(?i)@import|url\(\s*[\"']?https?:", "sin estilos externos")
        trozo = html.split("const D = ", 1)[1].split(";\n  const COLUMNAS", 1)[0]
        datos = json.loads(trozo.replace("<\\/", "</"))
        self.assertEqual(sorted(datos), ["alcances", "categorias", "colores", "huellas", "idiomas", "lexico", "veredictos"])
        self.assertEqual(len(datos["colores"]), 148)
        self.assertEqual(datos["colores"]["aliceblue"]["nombres"]["en"], "alice blue")

    def test_los_datos_no_pueden_cerrar_el_script(self):
        plantilla = "<script>const D = /*DATOS*/null;</script>"
        datos = {"idiomas": [("en", "Inglés")], "anotada": [], "lexico": [], "categorias": [], "significados": {}, "hex_concepto": {}}
        html = revision.visor_html(dict(datos, idiomas=[("en", "</script><b>")]), plantilla)
        self.assertEqual(html.count("</script>"), 1, "el único cierre es el de la plantilla")
        self.assertIn("<\\/script>", html)

    def test_la_plantilla_debe_tener_la_marca_una_vez(self):
        datos = {"idiomas": [], "anotada": [], "lexico": [], "categorias": [], "significados": {}, "hex_concepto": {}}
        for plantilla in ("<script></script>", f"{revision.MARCA_DATOS} {revision.MARCA_DATOS}"):
            with self.assertRaises(ValueError):
                revision.visor_html(datos, plantilla)

    @unittest.skipUnless(shutil.which("node"), "node no está instalado")
    def test_el_script_del_visor_es_javascript_valido(self):
        html = visor_real()
        guion = html.split("<script>", 1)[1].split("</script>", 1)[0]
        with tempfile.TemporaryDirectory() as t:
            ruta = Path(t) / "visor.js"
            ruta.write_text(guion, encoding="utf-8")
            resultado = subprocess.run(["node", "--check", str(ruta)], capture_output=True, text=True)
        self.assertEqual((resultado.returncode, resultado.stderr), (0, ""))
