"""Las reglas de los validadores de los CSV: cada una se rompe a propósito en una copia de los datos reales."""
from .apoyo import *  # noqa: F401,F403


def setUpModule():
    preparar()


class DatosReales(unittest.TestCase):
    """Sin romper nada, los validadores no dan errores: es lo que hace significativas las pruebas siguientes."""

    def test_los_csv_reales_pasan_todos_los_validadores(self):
        errores, usadas = [], set()
        gt.validar_anotada(real("anotada"), errores)
        gt.validar_completa(real("completa"), errores)
        gt.validar_significados(real("significados"), real("anotada"), errores)
        gt.validar_lexico(real("lexico"), real("anotada"), real("completa"), errores)
        ids = gt.validar_referencias(real("referencias"), errores)
        gt.validar_evidencia(real("evidencia"), real("anotada"), ids, errores, usadas)
        gt.validar_culturales(real("culturales"), real("anotada"), ids, errores, usadas)
        categorias.validar(real("categorias"), real("anotada"), real("lexico"), [c for c, _ in gt.IDIOMAS], gt.CONCEPTOS, errores)
        revision.validar(real("revisiones"), [c for c, _ in gt.IDIOMAS], errores)
        self.assertEqual(errores, [])
        self.assertEqual(usadas, ids, "ninguna referencia sin usar")

    def test_tamanos_esperados(self):
        self.assertEqual(len(REAL["anotada"]), 148)
        self.assertEqual(len(gt.IDIOMAS), 30)
        self.assertEqual(len(REAL["significados"]), 148 * 29)
        self.assertEqual(len(REAL["evidencia"]), 148)

    def test_los_ambitos_usan_idiomas_conocidos(self):
        codigos = {c for c, _ in gt.IDIOMAS}
        for ambito, (etiqueta, idiomas) in gt.AMBITOS.items():
            self.assertTrue(etiqueta.strip(), ambito)
            self.assertTrue(idiomas and set(idiomas) <= codigos, ambito)


class ColoresCsv(PruebaConErrores):
    def validar(self, filas):
        errores = []
        gt.validar_anotada(filas, errores)
        return errores

    def test_campo_vacio(self):
        filas = real("anotada")
        filas[3]["nombre_es"] = "  "
        self.assertSoloError(self.validar(filas), "línea 5: hay campos vacíos")

    def test_barra_vertical_o_salto_de_linea(self):
        for texto in ("a|b", "a\nb"):
            filas = real("anotada")
            filas[0]["significado_mistico"] += texto
            self.assertSoloError(self.validar(filas), "no puede llevar '|' ni saltos de línea")

    def test_nombre_con_mayuscula_o_espacio(self):
        for nombre in ("AliceBlue", "alice blue"):
            filas = real("anotada")
            filas[0]["nombre_ingles"] = nombre
            self.assertHayError(self.validar(filas), "minúsculas sin espacios")

    def test_nombre_repetido(self):
        filas = real("anotada")
        filas.insert(1, dict(filas[0]))
        self.assertSoloError(self.validar(filas), "nombre repetido: 'aliceblue'")

    def test_forma_natural_que_no_corresponde_al_nombre(self):
        for natural in ("alice blu", "Alice blue", "aliceblue ", "alice  blue"):
            filas = real("anotada")
            filas[0]["forma_natural_en"] = natural
            self.assertHayError(self.validar(filas), "forma_natural_en debe ser")

    def test_hex_invalido(self):
        for valor in ("#f0f8ff", "F0F8FF", "#F0F8F", "#F0F8FFF", "#GGGGGG"):
            filas = real("anotada")
            filas[0]["hex"] = valor
            self.assertSoloError(self.validar(filas), "hex inválido")

    def test_orden_roto(self):
        filas = real("anotada")
        filas[0], filas[1] = filas[1], filas[0]
        self.assertSoloError(self.validar(filas), "ordenadas por nombre_ingles")


class NombresCompletos(PruebaConErrores):
    def validar(self, filas):
        errores = []
        gt.validar_completa(filas, errores)
        return errores

    @staticmethod
    def fila(nombre, color="#000000", curada=""):
        return {"nombre_ingles": nombre, "hex": color, "curada": curada}

    def test_nombres_validos(self):
        self.assertEqual(self.validar([self.fila("Alpha"), self.fila("beta", curada="x"), self.fila("Gamma")]), [])

    def test_nombre_vacio_o_con_espacios_sobrantes(self):
        for nombre in ("", "  ", " Alpha", "Alpha "):
            self.assertHayError(self.validar([self.fila(nombre)]), "nombre vacío o con espacios sobrantes")

    def test_caracteres_prohibidos(self):
        for caracter in ('|', ',', '"', "\n"):
            self.assertHayError(self.validar([self.fila(f"Al{caracter}pha")]), "no puede llevar")

    def test_nombre_repetido_sin_distinguir_mayusculas(self):
        self.assertSoloError(self.validar([self.fila("Foo"), self.fila("foo")]), "nombre repetido")

    def test_hex_invalido(self):
        self.assertSoloError(self.validar([self.fila("Alpha", "#abcdef")]), "hex inválido")

    def test_curada_solo_vacia_o_x(self):
        self.assertSoloError(self.validar([self.fila("Alpha", curada="si")]), "curada debe ser vacía o 'x'")

    def test_orden_roto_sin_distinguir_mayusculas(self):
        self.assertSoloError(self.validar([self.fila("beta"), self.fila("Alpha")]), "ordenadas por nombre")
        self.assertEqual(self.validar([self.fila("Alpha"), self.fila("beta")]), [])


class AliasConElMismoSignificado(PruebaConErrores):
    def validar(self, anotada=None, traducidos=None):
        errores = []
        gt.validar_alias(anotada or real("anotada"), traducidos or real("significados"), errores)
        return errores

    def test_los_datos_reales_no_tienen_alias_con_significados_distintos(self):
        self.assertEqual(self.validar(), [])

    def test_aqua_y_cyan_en_espanol(self):
        for campo in ("significado_psicologico", "significado_mistico"):
            anotada = real("anotada")
            anotada[primera(anotada, nombre_ingles="aqua")][campo] += " distinto"
            self.assertSoloError(self.validar(anotada=anotada), "aqua y cyan tienen el mismo hex y deben tener los mismos significados en español")

    def test_fuchsia_y_magenta_en_otro_idioma(self):
        traducidos = real("significados")
        traducidos[primera(traducidos, idioma="ja", nombre_ingles="fuchsia")]["significado_mistico"] += "。"
        self.assertSoloError(self.validar(traducidos=traducidos), "fuchsia y magenta tienen el mismo hex y deben tener los mismos significados; difieren en ja")

    def test_gray_y_grey_tambien(self):
        traducidos = real("significados")
        for idioma in ("fr", "de"):
            traducidos[primera(traducidos, idioma=idioma, nombre_ingles="grey")]["significado_psicologico"] += "."
        self.assertSoloError(self.validar(traducidos=traducidos), "gray y grey tienen el mismo hex y deben tener los mismos significados; difieren en de, fr")

    def test_dos_colores_con_distinto_hex_pueden_decir_cosas_distintas(self):
        anotada = real("anotada")
        anotada[primera(anotada, nombre_ingles="red")]["significado_mistico"] += " distinto"
        self.assertEqual(self.validar(anotada=anotada), [])


class Significados(PruebaConErrores):
    def validar(self, filas):
        errores = []
        gt.validar_significados(filas, real("anotada"), errores)
        return errores

    def test_falta_una_combinacion(self):
        filas = real("significados")
        del filas[10]
        self.assertSoloError(self.validar(filas), "faltan 1 combinaciones idioma/color")

    def test_significado_sin_traducir(self):
        filas = real("significados")
        es = {f["nombre_ingles"]: f for f in REAL["anotada"]}
        filas[0]["significado_mistico"] = es[filas[0]["nombre_ingles"]]["significado_mistico"]
        self.assertSoloError(self.validar(filas), "significado_mistico de")

    def test_idioma_desconocido_o_espanol(self):
        for idioma in ("xx", "es"):
            filas = real("significados")
            filas[0]["idioma"] = idioma
            self.assertHayError(self.validar(filas), "idioma desconocido o español")

    def test_color_que_no_esta_en_colores_csv(self):
        filas = real("significados")
        filas[0]["nombre_ingles"] = "nocolor"
        self.assertHayError(self.validar(filas), "'nocolor' no está en colores.csv")

    def test_fila_repetida(self):
        filas = real("significados")
        filas.insert(1, dict(filas[0]))
        self.assertSoloError(self.validar(filas), "repetido")

    def test_campo_vacio_con_espacios_sobrantes_o_con_barra(self):
        for texto, fragmento in (("", "vacío o con espacios"), (" x ", "vacío o con espacios"), ("a|b", "no puede llevar '|'")):
            filas = real("significados")
            filas[0]["significado_psicologico"] = texto
            self.assertSoloError(self.validar(filas), fragmento)

    def test_orden_roto(self):
        filas = real("significados")
        filas[0], filas[1] = filas[1], filas[0]
        self.assertSoloError(self.validar(filas), "ordenadas por nombre_ingles")


class Lexico(PruebaConErrores):
    def validar(self, filas, completa=None):
        errores = []
        gt.validar_lexico(filas, real("anotada"), REAL["completa"] if completa is None else completa, errores)
        return errores

    def test_letras_latinas_en_un_idioma_de_otra_escritura(self):
        filas = real("lexico")
        filas[primera(filas, idioma="ru", tipo="lema")]["forma"] = "red"
        self.assertSoloError(self.validar(filas), "letras latinas en ru")

    def test_rasgos_de_flexion_no_validos(self):
        filas = real("lexico")
        filas[primera(filas, tipo="flexion")]["rasgos"] = "Gender=Foo"
        self.assertSoloError(self.validar(filas), "rasgos de flexión no válidos")

    def test_un_lema_no_lleva_rasgos(self):
        filas = real("lexico")
        filas[primera(filas, tipo="lema")]["rasgos"] = "Number=Plur"
        self.assertSoloError(self.validar(filas), "un lema no lleva rasgos")

    def test_un_lema_solo_puede_llevar_el_rasgo_de_propuesta_de_ia(self):
        filas = real("lexico")
        i = primera(filas, idioma="es", concepto="white", tipo="lema")
        filas[i]["rasgos"] = "origen=ia"
        self.assertEqual(self.validar(filas), [], "la marca de propuesta sin revisar es válida en un lema")
        for rasgos in ("origen=nativo", "origen=IA", "region=GB"):
            filas[i]["rasgos"] = rasgos
            self.assertSoloError(self.validar(filas), "un lema no lleva rasgos (solo 'origen=ia'")

    def test_los_lemas_de_ia_reales_son_solo_los_de_los_huecos(self):
        # Cada lema con origen=ia llena un hueco: el idioma no tenía lema para ese concepto en master antes de la propuesta.
        propuestas = [(f["idioma"], f["concepto"]) for f in REAL["lexico"] if f["tipo"] == "lema" and f["rasgos"] == "origen=ia"]
        self.assertEqual(len(propuestas), len(set(propuestas)))
        self.assertFalse(any(c in gt.BASICOS for _, c in propuestas), "los conceptos básicos nunca son propuestas")

    def test_dos_lemas_para_el_mismo_concepto_e_idioma(self):
        filas = real("lexico")
        i = primera(filas, tipo="lema")
        filas.insert(i + 1, {**filas[i], "forma": "otro"})
        self.assertSoloError(self.validar(filas), "tiene más de un lema")

    def test_falta_un_termino_basico(self):
        filas = real("lexico")
        del filas[primera(filas, concepto="white", idioma="sw", tipo="lema")]
        self.assertSoloError(self.validar(filas), "faltan 1 términos básicos")

    def test_formas_sin_lema(self):
        filas = real("lexico")
        del filas[primera(filas, concepto="gold", idioma="nl", tipo="lema")]
        self.assertSoloError(self.validar(filas), "gold/nl tiene formas pero no lema")

    def test_fila_repetida(self):
        filas = real("lexico")
        i = primera(filas, tipo="sinonimo")
        filas.insert(i + 1, dict(filas[i]))
        self.assertSoloError(self.validar(filas), "fila repetida")

    def test_la_misma_forma_con_otros_rasgos_no_es_repetida(self):
        # Pasa en checo y polaco: la forma de dos flexiones coincide y solo cambian los rasgos.
        filas = real("lexico")
        i = primera(filas, tipo="flexion")
        otros = [r for r in gt.RASGOS_FLEXION if r != filas[i]["rasgos"]]
        filas.insert(i + 1, {**filas[i], "rasgos": otros[0]})
        self.assertEqual(self.validar(filas), [])

    def test_concepto_idioma_o_tipo_desconocido(self):
        for campo, valor in (("concepto", "nocolor"), ("idioma", "xx"), ("tipo", "otro")):
            filas = real("lexico")
            filas[0][campo] = valor
            self.assertHayError(self.validar(filas), "concepto, idioma o tipo desconocido")

    def test_forma_vacia_con_espacios_comillas_o_barra(self):
        for forma in ("", " x", "x ", 'a"b', "a|b"):
            filas = real("lexico")
            filas[primera(filas, tipo="sinonimo")]["forma"] = forma
            self.assertHayError(self.validar(filas), "forma vacía")

    def test_etiqueta_de_sinonimo_o_variante(self):
        filas = real("lexico")
        i = primera(filas, tipo="sinonimo")
        filas[i]["rasgos"] = "foo=bar"
        self.assertSoloError(self.validar(filas), "etiqueta no válida")
        filas[i]["rasgos"] = "registro=coloquial"
        self.assertEqual(self.validar(filas), [])

    def test_orden_roto(self):
        filas = real("lexico")
        filas[0], filas[-1] = filas[-1], filas[0]
        self.assertSoloError(self.validar(filas), "ordenadas por concepto, idioma y tipo")

    def test_concepto_sin_hex_de_referencia(self):
        errores = []
        gt.validar_lexico(real("lexico"), [], [], errores)
        sin_hex = [e for e in errores if "no está en colores.csv ni en nombres-completos.csv" in e]
        self.assertEqual(len(sin_hex), len(gt.CONCEPTOS))

    def test_hex_de_concepto_prefiere_el_color_css(self):
        anotada = [{"nombre_ingles": "gold", "hex": "#FFD700"}]
        completa = [{"nombre_ingles": "Gold", "hex": "#111111"}, {"nombre_ingles": "Lilac", "hex": "#CEA2FD"}]
        self.assertEqual(gt.hex_de_concepto("gold", anotada, completa), "#FFD700")
        self.assertEqual(gt.hex_de_concepto("lilac", anotada, completa), "#CEA2FD")
        self.assertIsNone(gt.hex_de_concepto("nocolor", anotada, completa))


class Referencias(PruebaConErrores):
    def validar(self, filas):
        errores = []
        ids = gt.validar_referencias(filas, errores)
        return errores, ids

    def test_devuelve_los_ids(self):
        errores, ids = self.validar(real("referencias"))
        self.assertEqual(errores, [])
        self.assertEqual(ids, {f["id"] for f in REAL["referencias"]})

    def test_id_no_valido(self):
        for i in ("Foo_Bar", "foo bar", "-foo", "foo--bar", ""):
            filas = real("referencias")
            filas[0]["id"] = i
            self.assertHayError(self.validar(filas)[0], "id no válido")

    def test_id_repetido(self):
        filas = real("referencias")
        filas.insert(1, dict(filas[0]))
        self.assertSoloError(self.validar(filas)[0], "id repetido")

    def test_tipo_o_verificacion_desconocidos(self):
        for campo in ("tipo", "verificacion"):
            filas = real("referencias")
            filas[0][campo] = "otro"
            self.assertSoloError(self.validar(filas)[0], "tipo o verificacion no válidos")

    def test_cita_vacia_o_con_barra(self):
        for cita in ("", "a|b", "a\nb"):
            filas = real("referencias")
            filas[0]["cita"] = cita
            self.assertSoloError(self.validar(filas)[0], "cita vacía o con")

    def test_url_que_no_es_https(self):
        filas = real("referencias")
        filas[primera(filas, tipo="web")]["url"] = "http://ejemplo.org"
        self.assertSoloError(self.validar(filas)[0], "la url debe empezar por https://")

    def test_pagina_web_sin_url(self):
        filas = real("referencias")
        filas[primera(filas, tipo="web")]["url"] = ""
        self.assertSoloError(self.validar(filas)[0], "una página web necesita url")

    def test_orden_roto(self):
        filas = real("referencias")
        filas[0], filas[1] = filas[1], filas[0]
        self.assertSoloError(self.validar(filas)[0], "ordenadas por id")


class Evidencia(PruebaConErrores):
    def validar(self, filas):
        errores, usadas = [], set()
        ids = gt.validar_referencias(real("referencias"), [])
        gt.validar_evidencia(filas, real("anotada"), ids, errores, usadas)
        return errores, usadas

    def test_registra_las_referencias_usadas(self):
        errores, usadas = self.validar(real("evidencia"))
        self.assertEqual(errores, [])
        self.assertIn("jonauskaite2020", usadas)
        self.assertIn("leland2016", usadas)

    def test_debe_tener_una_fila_por_color_en_su_orden(self):
        filas = real("evidencia")
        filas.reverse()
        self.assertSoloError(self.validar(filas)[0], "una fila por color de colores.csv")
        self.assertSoloError(self.validar(filas[:-1])[0], "una fila por color de colores.csv")

    def test_base_no_valida(self):
        for campo in ("base_psicologico", "base_mistico"):
            filas = real("evidencia")
            filas[0][campo] = "otra"
            self.assertHayError(self.validar(filas)[0], "base no válida")

    def test_color_con_estudios_sin_citar_el_estudio(self):
        filas = real("evidencia")
        filas[primera(filas, nombre_ingles="red")]["referencias_psicologico"] = ""
        self.assertSoloError(self.validar(filas)[0], "tiene estudios pero no cita jonauskaite2020")

    def test_psicologico_derivado_sin_referencias(self):
        filas = real("evidencia")
        filas[primera(filas, base_psicologico="derivado")]["referencias_psicologico"] = "jonauskaite2020"
        self.assertSoloError(self.validar(filas)[0], "un significado derivado no lleva referencias psicológicas")

    def test_mistico_derivado_sin_referencias(self):
        filas = real("evidencia")
        filas[primera(filas, base_mistico="derivado")]["referencias_mistico"] = "leland2016"
        self.assertSoloError(self.validar(filas)[0], "un significado derivado no lleva referencias místicas")

    def test_tradicion_moderna_debe_citar_a_leland(self):
        filas = real("evidencia")
        filas[primera(filas, base_mistico="tradicion_moderna")]["referencias_mistico"] = ""
        self.assertSoloError(self.validar(filas)[0], "es tradición moderna pero no cita leland2016")

    def test_referencias_desordenadas_o_repetidas(self):
        ids = sorted(f["id"] for f in REAL["referencias"])
        for lista in (f"{ids[1]};{ids[0]}", f"{ids[0]};{ids[0]}"):
            filas = real("evidencia")
            filas[primera(filas, base_mistico="tradicion")]["referencias_mistico"] = lista
            self.assertSoloError(self.validar(filas)[0], "debe ir ordenado y sin repetidos")

    def test_referencia_desconocida(self):
        filas = real("evidencia")
        filas[primera(filas, base_mistico="tradicion")]["referencias_mistico"] = "no-existe"
        self.assertSoloError(self.validar(filas)[0], "referencia desconocida 'no-existe'")

    def test_los_colores_con_estudios_son_exactamente_los_indicados(self):
        filas = real("evidencia")
        i = primera(filas, nombre_ingles="red")
        filas[i]["base_psicologico"], filas[i]["referencias_psicologico"] = "derivado", ""
        self.assertSoloError(self.validar(filas)[0], "los colores con estudios deben ser exactamente")


class Culturales(PruebaConErrores):
    def validar(self, filas):
        errores, usadas = [], set()
        ids = gt.validar_referencias(real("referencias"), [])
        gt.validar_culturales(filas, real("anotada"), ids, errores, usadas)
        return errores

    def test_color_ambito_o_solidez_no_validos(self):
        for campo, valor in (("color", "nocolor"), ("ambito", "atlantida"), ("solidez", "total")):
            filas = real("culturales")
            filas[0][campo] = valor
            self.assertSoloError(self.validar(filas), "color, ámbito o solidez no válidos")

    def test_matiz_vacio_o_con_barra(self):
        for matiz in ("", "a|b", "a\nb"):
            filas = real("culturales")
            filas[0]["matiz"] = matiz
            self.assertSoloError(self.validar(filas), "matiz vacío o con")

    def test_sin_referencias(self):
        filas = real("culturales")
        filas[0]["referencias"] = ""
        self.assertSoloError(self.validar(filas), "necesita al menos una referencia")

    def test_referencia_desconocida(self):
        filas = real("culturales")
        filas[0]["referencias"] = "no-existe"
        self.assertSoloError(self.validar(filas), "referencia desconocida 'no-existe'")

    def test_orden_roto(self):
        filas = real("culturales")
        filas[0], filas[-1] = filas[-1], filas[0]
        self.assertSoloError(self.validar(filas), "ordenadas por color")
