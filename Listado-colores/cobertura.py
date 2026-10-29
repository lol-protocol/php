"""Informe cobertura.md: lo que falta o es ambiguo en los datos de Listado-colores, calculado de los CSV.

Solo biblioteca estándar. Lo usa generar-tabla.py. No corrige nada ni hace fallar la generación: dice dónde están los huecos
(conceptos del léxico sin lema en un idioma, idiomas sin categorías gramaticales) y las ambigüedades que no son errores de un
archivo pero que quien use los datos debe conocer (dos colores con el mismo nombre en un idioma, un lema que sirve para dos
colores distintos).
"""


# Un lema con este rasgo lo propuso un asistente de IA y ningún hablante nativo lo ha revisado.
ORIGEN_IA = "origen=ia"


def propuestas_ia(lexico):
    """[(idioma, concepto, lema)] de los lemas propuestos por IA sin revisar, en el orden del archivo."""
    return [(f["idioma"], f["concepto"], f["forma"]) for f in lexico if f["tipo"] == "lema" and f["rasgos"] == ORIGEN_IA]


def huecos_lexico(idiomas, lexico, conceptos):
    """{código: [conceptos del léxico sin lema en ese idioma]}, solo los idiomas con huecos."""
    con_lema = {(f["idioma"], f["concepto"]) for f in lexico if f["tipo"] == "lema"}
    huecos = {c: [k for k in conceptos if (c, k) not in con_lema] for c, _ in idiomas}
    return {c: lista for c, lista in huecos.items() if lista}


def colisiones_nombres(idiomas, anotada):
    """{código: [(nombre, [colores CSS con hex distinto que se llaman así])]}: un mismo nombre para colores que no son el mismo."""
    resultado = {}
    for codigo, _ in idiomas:
        por_nombre = {}
        for f in anotada:
            nombre = f["forma_natural_en"] if codigo == "en" else f[f"nombre_{codigo}"]
            por_nombre.setdefault(nombre.casefold(), []).append(f)
        casos = [(lista[0]["forma_natural_en"] if codigo == "en" else lista[0][f"nombre_{codigo}"], [f["nombre_ingles"] for f in lista])
                 for lista in por_nombre.values() if len({f["hex"] for f in lista}) > 1]
        if casos:
            resultado[codigo] = sorted(casos, key=lambda c: c[0].casefold())
    return resultado


def lemas_compartidos(idiomas, lexico, hex_concepto):
    """{código: [(lema, [conceptos])]}: un lema del léxico que sirve a conceptos de distinto hex (violet para purple y violet)."""
    resultado = {}
    for codigo, _ in idiomas:
        por_lema = {}
        for f in lexico:
            if f["idioma"] == codigo and f["tipo"] == "lema":
                por_lema.setdefault(f["forma"].casefold(), []).append(f)
        casos = [(lista[0]["forma"], [f["concepto"] for f in lista]) for lista in por_lema.values()
                 if len({hex_concepto[f["concepto"]] for f in lista}) > 1]
        if casos:
            resultado[codigo] = sorted(casos, key=lambda c: c[0].casefold())
    return resultado


def _codigos(lista):
    return ", ".join(f"`{x}`" for x in lista)


def informe(idiomas, anotada, lexico, categorias, conceptos, hex_concepto):
    """El texto de cobertura.md."""
    nombres = dict(idiomas)
    huecos = huecos_lexico(idiomas, lexico, conceptos)
    colisiones = colisiones_nombres(idiomas, anotada)
    compartidos = lemas_compartidos(idiomas, lexico, hex_concepto)
    propuestas = propuestas_ia(lexico)
    con_categorias = sorted({f["idioma"] for f in categorias}, key=[c for c, _ in idiomas].index)
    sin_categorias = [c for c, _ in idiomas if c not in con_categorias]
    partes = [
        "# Cobertura y ambigüedades de los datos",
        "",
        "Generado por `generar-tabla.py` a partir de los CSV. No editar a mano.",
        "",
        "Este informe no corrige nada ni hace fallar la generación: dice dónde faltan datos y qué ambigüedades tienen, para no "
        "tomar los datos por más completos o más precisos de lo que son. Nada de esto lo ha revisado un hablante nativo.",
        "",
        "## Resumen",
        "",
        f"- Léxico básico: {len(conceptos)} conceptos. {len(idiomas) - len(huecos)} idiomas tienen lema para todos; "
        f"{len(huecos)} tienen huecos ({sum(len(v) for v in huecos.values())} lemas en total).",
        f"- De esos huecos, {len(propuestas)} los llenó una propuesta de IA sin revisar (rasgo `{ORIGEN_IA}` en `lexico-basico.csv`).",
        f"- Categorías gramaticales: {len(con_categorias)} idiomas ({', '.join(nombres[c] for c in con_categorias)}); "
        f"{len(sin_categorias)} sin ninguna.",
        f"- Nombres CSS repetidos con hex distinto: {sum(len(v) for v in colisiones.values())} casos en {len(colisiones)} idiomas.",
        f"- Lemas del léxico que sirven a colores de distinto hex: {sum(len(v) for v in compartidos.values())} casos "
        f"en {len(compartidos)} idiomas.",
        "",
        "## Conceptos del léxico sin lema",
        "",
        "El generador solo exige lema en los 30 idiomas para los 11 conceptos básicos; el resto puede faltar.",
        "",
        "| Idioma | Con lema | Faltan |",
        "|---|---|---|",
    ]
    for codigo, nombre in idiomas:
        faltan = huecos.get(codigo, [])
        partes.append(f"| {nombre} (`{codigo}`) | {len(conceptos) - len(faltan)} de {len(conceptos)} | {_codigos(faltan) or '—'} |")
    partes += ["", "## Lemas propuestos por IA sin revisar", "",
               "Llenan huecos del léxico; los escribió un asistente de IA y ningún hablante nativo los ha visto. Cuando uno los "
               "confirme (en `revisiones.csv`), se quita el rasgo `origen=ia` del lema; si no, se corrige o se borra la fila.", ""]
    if not propuestas:
        partes.append("Ninguno.")
    else:
        partes += ["| Idioma | Concepto | Lema |", "|---|---|---|"]
        for codigo, nombre in idiomas:
            for idioma, concepto, lema in propuestas:
                if idioma == codigo:
                    partes.append(f"| {nombre} (`{codigo}`) | `{concepto}` | {lema} |")
    partes += ["", "## Idiomas sin categorías gramaticales", "", _codigos(sin_categorias) or "Ninguno.", "",
               "## Nombres CSS repetidos con hex distinto", "",
               "Un mismo nombre para dos colores que no son el mismo: quien busque por nombre no sabe cuál es.", ""]
    if not colisiones:
        partes.append("Ninguno.")
    else:
        partes += ["| Idioma | Nombre | Colores CSS |", "|---|---|---|"]
        for codigo, nombre in idiomas:
            for forma, colores in colisiones.get(codigo, []):
                partes.append(f"| {nombre} (`{codigo}`) | {forma} | {_codigos(colores)} |")
    partes += ["", "## Lemas que sirven a colores de distinto hex", "",
               "Un lema del léxico asignado a dos conceptos que no son alias (por ejemplo, `violet` en francés es `purple` y `violet`); "
               "puede ser un idioma que no distingue los dos colores.", ""]
    if not compartidos:
        partes.append("Ninguno.")
    else:
        partes += ["| Idioma | Lema | Conceptos |", "|---|---|---|"]
        for codigo, nombre in idiomas:
            for lema, lista in compartidos.get(codigo, []):
                partes.append(f"| {nombre} (`{codigo}`) | {lema} | {_codigos(lista)} |")
    partes.append("")
    return "\n".join(partes)
