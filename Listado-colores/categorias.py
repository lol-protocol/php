"""Categorías gramaticales de las palabras de color (categorias.csv): validación y el informe categorias.md.

Solo biblioteca estándar. Lo usa generar-tabla.py. Cada fila dice que una palabra de color puede aparecer con una
categoría gramatical (UPOS de Universal Dependencies: ADJ, NOUN, PROPN o VERB) y da una frase de ejemplo con esa
categoría. Son las lecturas posibles de la palabra, no cuántas veces ocurre cada una. Cubre las palabras de una sola
palabra de dos idiomas: inglés (los nombres CSS de una palabra y los términos del léxico que no son CSS) y español (los
lemas de una palabra del léxico básico). Lo escribió un asistente de IA y nadie con dominio nativo lo ha revisado.
"""
import re

COLUMNAS = ["idioma", "concepto", "forma", "categoria", "ocurrencia", "ejemplo"]
UPOS = ["ADJ", "NOUN", "PROPN", "VERB"]
NOMBRES_UPOS = {"ADJ": "adjetivo", "NOUN": "sustantivo", "PROPN": "nombre propio", "VERB": "verbo"}
# Cuántas letras más que la forma puede tener la ocurrencia en la frase (plural, «-ed», «-s»).
MAXIMO_SUFIJO = 4


def lemas_de(lexico):
    """{(idioma, concepto): lema} del léxico básico."""
    return {(f["idioma"], f["concepto"]): f["forma"] for f in lexico if f["tipo"] == "lema"}


def forma_de(idioma, concepto, anotada, lemas):
    """La palabra de `concepto` en `idioma`: en inglés, la forma natural del color CSS si lo es; en lo demás, el lema del léxico."""
    for f in anotada:
        if idioma == "en" and f["nombre_ingles"] == concepto:
            return f["forma_natural_en"]
    return lemas.get((idioma, concepto))


def palabras_esperadas(idioma, anotada, lemas, conceptos):
    """Los conceptos de `idioma` cuya palabra es una sola: los que categorias.csv debe cubrir si el idioma está."""
    esperados = {c for c in conceptos if " " not in (lemas.get((idioma, c)) or " ")}
    if idioma == "en":
        esperados |= {f["nombre_ingles"] for f in anotada if " " not in f["forma_natural_en"]}
    return esperados


def clave_concepto(concepto, anotada, conceptos):
    """El orden de los conceptos: primero los colores CSS en el orden de colores.csv, luego los demás del léxico."""
    for n, f in enumerate(anotada):
        if f["nombre_ingles"] == concepto:
            return n
    return 1000 + conceptos.index(concepto)


def validar(filas, anotada, lexico, codigos, conceptos, errores):
    lemas = lemas_de(lexico)
    css = {f["nombre_ingles"] for f in anotada}
    orden_idioma = {c: n for n, c in enumerate(codigos)}
    vistos, claves, presentes = set(), [], {}
    for n, fila in enumerate(filas, start=2):
        idioma, concepto, forma, categoria, ocurrencia, ejemplo = (fila[c] for c in COLUMNAS)
        donde = f"categorias.csv línea {n}"
        if idioma not in orden_idioma or (concepto not in css and concepto not in conceptos):
            errores.append(f"{donde}: idioma o concepto desconocido ({idioma}, {concepto})")
            continue
        esperada = forma_de(idioma, concepto, anotada, lemas)
        if esperada is None:
            errores.append(f"{donde}: {concepto} no tiene forma en {idioma} (ni color CSS en inglés ni lema del léxico)")
            continue
        if forma != esperada:
            errores.append(f"{donde}: la forma de {concepto} en {idioma} es {esperada!r}, no {forma!r}")
        if not forma.strip() or re.search(r"\s", forma):
            errores.append(f"{donde}: la forma debe ser una sola palabra: {forma!r}")
        if categoria not in UPOS:
            errores.append(f"{donde}: categoría desconocida {categoria!r} (debe ser {', '.join(UPOS)})")
        if not ocurrencia.casefold().startswith(forma.casefold()) or len(ocurrencia) > len(forma) + MAXIMO_SUFIJO:
            errores.append(f"{donde}: la ocurrencia {ocurrencia!r} debe ser la forma {forma!r} con a lo sumo {MAXIMO_SUFIJO} letras más")
        if categoria == "PROPN" and not ocurrencia[:1].isupper():
            errores.append(f"{donde}: un nombre propio se escribe con mayúscula inicial: {ocurrencia!r}")
        if not ejemplo.strip() or ejemplo != ejemplo.strip() or any(c in ejemplo for c in "|\n"):
            errores.append(f"{donde}: ejemplo vacío, con espacios sobrantes o con '|' o saltos de línea")
        elif ejemplo[-1] not in ".!?":
            errores.append(f"{donde}: la frase de ejemplo debe terminar en '.', '!' o '?'")
        if not re.fullmatch(r"\w+", ocurrencia):
            errores.append(f"{donde}: la ocurrencia {ocurrencia!r} debe tener solo letras y dígitos (es un token de ejemplos.conllu)")
        veces = len(re.findall(r"(?<!\w)" + re.escape(ocurrencia) + r"(?!\w)", ejemplo))
        if veces != 1:
            errores.append(f"{donde}: la frase debe contener la ocurrencia {ocurrencia!r} una vez como palabra entera, no {veces}")
        clave = (idioma, concepto, categoria)
        if clave in vistos:
            errores.append(f"{donde}: repetida {idioma}/{concepto}/{categoria}")
        vistos.add(clave)
        presentes.setdefault(idioma, set()).add(concepto)
        claves.append((orden_idioma[idioma], clave_concepto(concepto, anotada, conceptos), UPOS.index(categoria) if categoria in UPOS else -1))
    adjetivos = {(f["idioma"], f["concepto"]) for f in filas if f["categoria"] == "ADJ"}
    for idioma, concepto in sorted({(f["idioma"], f["concepto"]) for f in filas} - adjetivos, key=lambda p: (orden_idioma.get(p[0], 0), p[1])):
        errores.append(f"categorias.csv: {idioma}/{concepto} no tiene la categoría ADJ (toda palabra de color puede ser adjetivo)")
    for idioma in sorted(presentes, key=orden_idioma.get):
        esperados = palabras_esperadas(idioma, anotada, lemas, conceptos)
        faltan, sobran = sorted(esperados - presentes[idioma]), sorted(presentes[idioma] - esperados)
        if faltan:
            errores.append(f"categorias.csv: a {idioma} le faltan {len(faltan)} palabras (por ejemplo {', '.join(faltan[:5])})")
        if sobran:
            errores.append(f"categorias.csv: {idioma} tiene {len(sobran)} conceptos que no son de una sola palabra (por ejemplo {', '.join(sobran[:5])})")
    if claves != sorted(claves):
        errores.append("categorias.csv: las filas deben ir ordenadas por idioma, concepto (colores CSS primero, luego el léxico) y categoría (ADJ, NOUN, PROPN, VERB)")


def por_concepto(filas):
    """{(idioma, concepto): [filas]} en el orden del archivo."""
    resultado = {}
    for f in filas:
        resultado.setdefault((f["idioma"], f["concepto"]), []).append(f)
    return resultado


def _celda(texto):
    return str(texto).replace("|", "&#124;")


def informe(filas, idiomas):
    """El texto de categorias.md. `idiomas` es la lista [(código, nombre)] de generar-tabla.py."""
    nombres = dict(idiomas)
    presentes = [c for c, _ in idiomas if any(f["idioma"] == c for f in filas)]
    agrupadas = por_concepto(filas)
    partes = [
        "# Categorías gramaticales de las palabras de color",
        "",
        "Generado por `generar-tabla.py` a partir de `categorias.csv`. No editar a mano.",
        "",
        "Muchas palabras de color son también sustantivos, nombres propios o verbos (`coral`, `lime`, `tan`, `peru`, `violet`, `lima`). "
        "Para cada palabra de color de una sola palabra, las categorías gramaticales con que puede aparecer en un texto (las de "
        "[Universal Dependencies](https://universaldependencies.org/u/pos/): `ADJ` adjetivo, `NOUN` sustantivo, `PROPN` nombre propio y "
        "`VERB` verbo) y una frase de ejemplo por categoría.",
        "",
        "Límites:",
        "",
        "- Cubre dos idiomas: inglés (los nombres CSS de una sola palabra y los términos del léxico básico que no son CSS) y español "
        "(los lemas de una sola palabra del léxico básico). Las palabras compuestas (`azul marino`, `light blue`) no entran.",
        "- Son las lecturas **posibles** de la palabra, no cuántas veces ocurre cada una, y cada frase es un ejemplo, no el único uso. "
        "No se distingue el sentido: `coral` sustantivo es el animal y el color.",
        "- Hay casos discutibles: un sustantivo que modifica a otro (`a linen shirt`) puede etiquetarse `NOUN` o `ADJ` según el criterio; "
        "los ejemplos de `ADJ` en inglés usan la palabra como color en una posición clara (`painted the walls linen`).",
        "- Nueve nombres CSS casi no se usan como color fuera de CSS (`cornsilk`, `firebrick`, `gainsboro`, `goldenrod`, `moccasin`, "
        "`peru`, `snow`, `thistle`, `tomato`): su ejemplo de `ADJ` habla de una página web (`The header is thistle`), porque en "
        "otro contexto no sonaría natural. Es uso de nombre CSS, no de lengua corriente.",
        "- Está escrito por un asistente de IA y ningún hablante nativo lo ha revisado.",
        "",
        "## Resumen",
        "",
        "| Idioma | Palabras | " + " | ".join(f"`{u}`" for u in UPOS) + " | Con más de una categoría |",
        "|---|---|" + "---|" * len(UPOS) + "---|",
    ]
    for codigo in presentes:
        propias = {c: lista for (i, c), lista in agrupadas.items() if i == codigo}
        cuentas = [sum(1 for lista in propias.values() for f in lista if f["categoria"] == u) for u in UPOS]
        varias = sum(1 for lista in propias.values() if len(lista) > 1)
        partes.append(f"| {nombres[codigo]} (`{codigo}`) | {len(propias)} | " + " | ".join(map(str, cuentas)) + f" | {varias} |")
    partes.append("")
    for codigo in presentes:
        partes += [f"## {nombres[codigo]} (`{codigo}`)", "", "| Concepto | Forma | Categoría | Ejemplo |", "|---|---|---|---|"]
        for f in (f for f in filas if f["idioma"] == codigo):
            partes.append(f"| `{f['concepto']}` | {_celda(f['forma'])} | `{f['categoria']}` | {_celda(f['ejemplo'])} |")
        partes.append("")
    return "\n".join(partes)
