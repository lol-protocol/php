"""Revisión por hablantes nativos de Listado-colores: validación de revisiones.csv, el informe revisiones.md y el visor HTML.

Solo biblioteca estándar. Lo usa generar-tabla.py. Casi todo el contenido del proyecto lo escribió un asistente de IA sin
revisión de hablantes nativos; revisiones.csv es el registro de quién ha revisado qué. Cada fila dice que una persona
revisó un elemento (el nombre de un color en un idioma, sus significados, un concepto del léxico o las categorías
gramaticales de un concepto) y con qué resultado. El archivo empieza vacío: mientras no haya filas, nada está revisado.
"""
import datetime
import json
import re

COLUMNAS = ["idioma", "alcance", "elemento", "veredicto", "revisor", "fecha", "nota"]
# alcance -> qué se revisa. El elemento es el id del color CSS (nombres, significados) o el concepto (lexico, categorias).
ALCANCES = {
    "nombres": "el nombre del color en el idioma (colores.csv)",
    "significados": "los significados psicológico y místico en el idioma (colores.csv y significados-idiomas.csv)",
    "lexico": "las formas del léxico básico de un concepto en el idioma (lexico-basico.csv)",
    "categorias": "las categorías gramaticales de un concepto en el idioma (categorias.csv)",
}
# veredicto -> qué quiere decir.
VEREDICTOS = {
    "correcto": "la revisión no encontró nada que cambiar",
    "corregir": "hay una corrección propuesta en `nota`, todavía sin aplicar a los CSV",
    "corregido": "la corrección propuesta ya está aplicada a los CSV",
    "dudoso": "el revisor no está seguro y deja constancia en `nota`",
}
MARCA_DATOS = "/*DATOS*/null"


def universo(d):
    """{(idioma, alcance): [elementos]} de lo que se puede revisar, en el orden de los CSV."""
    codigos = [c for c, _ in d["idiomas"]]
    colores = [f["nombre_ingles"] for f in d["anotada"]]
    resultado = {}
    for codigo in codigos:
        resultado[(codigo, "nombres")] = colores
        resultado[(codigo, "significados")] = colores
    for alcance, filas in (("lexico", d["lexico"]), ("categorias", d["categorias"])):
        for f in filas:
            conceptos = resultado.setdefault((f["idioma"], alcance), [])
            if f["concepto"] not in conceptos:
                conceptos.append(f["concepto"])
    return resultado


def validar(filas, universo_revisable, codigos, errores):
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        idioma, alcance, elemento, veredicto, revisor, fecha, nota = (fila[c] for c in COLUMNAS)
        donde = f"revisiones.csv línea {n}"
        if idioma not in codigos:
            errores.append(f"{donde}: idioma desconocido {idioma!r}")
        elif alcance not in ALCANCES:
            errores.append(f"{donde}: alcance desconocido {alcance!r} (debe ser {', '.join(ALCANCES)})")
        elif (idioma, alcance) not in universo_revisable:
            errores.append(f"{donde}: {alcance} no tiene datos en {idioma} (categorias.csv solo cubre algunos idiomas)")
        elif elemento not in universo_revisable[(idioma, alcance)]:
            errores.append(f"{donde}: {elemento!r} no es un elemento de {alcance} en {idioma}")
        if veredicto not in VEREDICTOS:
            errores.append(f"{donde}: veredicto desconocido {veredicto!r} (debe ser {', '.join(VEREDICTOS)})")
        if not revisor.strip() or revisor != revisor.strip() or "\n" in revisor:
            errores.append(f"{donde}: revisor vacío, con espacios sobrantes o con saltos de línea")
        if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", fecha):
            errores.append(f"{donde}: la fecha debe ser AAAA-MM-DD: {fecha!r}")
        else:
            try:
                datetime.date.fromisoformat(fecha)
            except ValueError:
                errores.append(f"{donde}: fecha que no existe: {fecha!r}")
        if nota != nota.strip() or "\n" in nota:
            errores.append(f"{donde}: nota con espacios sobrantes o con saltos de línea")
        if veredicto in ("corregir", "dudoso") and not nota:
            errores.append(f"{donde}: un veredicto {veredicto!r} necesita una nota que diga qué corregir o de qué se duda")
        clave = (idioma, alcance, elemento, revisor)
        if clave in vistos:
            errores.append(f"{donde}: {revisor} ya revisó {alcance}/{elemento} en {idioma}: edita esa fila en vez de repetirla")
        vistos.add(clave)


def _celda(texto):
    return str(texto).replace("|", "&#124;").replace("\n", " ")


def informe(filas, universo_revisable, idiomas):
    """El texto de revisiones.md. `idiomas` es la lista [(código, nombre)] de generar-tabla.py."""
    nombres = dict(idiomas)
    por_celda = {}
    for f in filas:
        por_celda.setdefault((f["idioma"], f["alcance"]), []).append(f)
    total = sum(len(v) for v in universo_revisable.values())
    revisados = sum(len({f["elemento"] for f in lista}) for lista in por_celda.values())
    partes = [
        "# Revisión por hablantes nativos",
        "",
        "Generado por `generar-tabla.py` a partir de `revisiones.csv`. No editar a mano.",
        "",
        "Casi todo el contenido de este proyecto (traducciones de nombres, significados, léxico y categorías gramaticales) lo escribió "
        "un asistente de IA y ningún hablante nativo lo ha revisado. `revisiones.csv` es el registro de quién ha revisado qué; este "
        "informe lo resume.",
        "",
        (f"**Estado: {revisados} de {total} elementos revisados.**" if revisados
         else f"**Estado: ningún elemento revisado todavía (0 de {total}).**"),
        "",
        "## Cómo revisar",
        "",
        "1. Abre `visor-revision.html` en un navegador (no necesita servidor ni conexión), elige tu idioma y lo que vas a revisar.",
        "2. Marca cada elemento: `correcto`, `corregir` (con la corrección propuesta en la nota) o `dudoso`. Escribe tu nombre o alias y la fecha.",
        "3. Copia las filas que genera el visor y añádelas al final de `revisiones.csv` (en un PR o pásalas a quien mantiene el proyecto). "
        "El orden de las filas no importa; el generador las valida.",
        "4. Quien mantiene el proyecto aplica las correcciones a los CSV y cambia esas filas de `corregir` a `corregido`.",
        "",
        "Alcances (qué es un elemento):",
        "",
        *[f"- `{a}`: {texto}." for a, texto in ALCANCES.items()],
        "",
        "Veredictos:",
        "",
        *[f"- `{v}`: {texto}." for v, texto in VEREDICTOS.items()],
        "",
        "Una fila por revisor y elemento: si vuelves a revisar algo, edita tu fila. El historial queda en git.",
        "",
        "## Cobertura por idioma",
        "",
        "Elementos con al menos una fila, sobre el total de cada alcance (`—` si el idioma no tiene datos de ese alcance).",
        "",
        "| Idioma | " + " | ".join(f"`{a}`" for a in ALCANCES) + " |",
        "|---|" + "---|" * len(ALCANCES),
    ]
    for codigo, nombre in idiomas:
        celdas = []
        for alcance in ALCANCES:
            todos = universo_revisable.get((codigo, alcance))
            if todos is None:
                celdas.append("—")
            else:
                hechos = len({f["elemento"] for f in por_celda.get((codigo, alcance), [])})
                celdas.append(f"{hechos} de {len(todos)}")
        partes.append(f"| {nombre} (`{codigo}`) | " + " | ".join(celdas) + " |")
    partes += ["", "## Filas por veredicto", "", "| Veredicto | Filas |", "|---|---|"]
    for veredicto in VEREDICTOS:
        partes.append(f"| `{veredicto}` | {sum(1 for f in filas if f['veredicto'] == veredicto)} |")
    pendientes = [f for f in filas if f["veredicto"] in ("corregir", "dudoso")]
    partes += ["", "## Pendientes", ""]
    if not pendientes:
        partes.append("No hay correcciones propuestas ni dudas por atender.")
    else:
        orden_idioma = {c: n for n, (c, _) in enumerate(idiomas)}
        orden_alcance = {a: n for n, a in enumerate(ALCANCES)}
        partes += ["| Idioma | Alcance | Elemento | Veredicto | Nota | Revisor | Fecha |", "|---|---|---|---|---|---|---|"]
        for f in sorted(pendientes, key=lambda f: (orden_idioma[f["idioma"]], orden_alcance[f["alcance"]],
                                                    universo_revisable[(f["idioma"], f["alcance"])].index(f["elemento"]), f["revisor"].casefold())):
            partes.append(f"| `{f['idioma']}` | `{f['alcance']}` | `{f['elemento']}` | `{f['veredicto']}` | {_celda(f['nota'])} | "
                          f"{_celda(f['revisor'])} | {f['fecha']} |")
    partes.append("")
    return "\n".join(partes)


def datos_visor(d):
    """Lo que necesita el visor, como un diccionario que se escribe como JSON dentro del HTML."""
    codigos = [c for c, _ in d["idiomas"]]
    lexico, categorias = {}, {}
    for f in d["lexico"]:
        propios = lexico.setdefault(f["idioma"], {}).setdefault(
            f["concepto"], {"hex": d["hex_concepto"][f["concepto"]], "formas": []})
        propios["formas"].append([f["forma"], f["tipo"], f["rasgos"]])
    for f in d["categorias"]:
        categorias.setdefault(f["idioma"], {}).setdefault(f["concepto"], []).append(
            [f["forma"], f["categoria"], f["ocurrencia"], f["ejemplo"]])
    colores = {}
    for f in d["anotada"]:
        n = f["nombre_ingles"]
        colores[n] = {
            "hex": f["hex"],
            "nombres": {c: (f["forma_natural_en"] if c == "en" else f[f"nombre_{c}"]) for c in codigos},
            "significados": {c: ([f["significado_psicologico"], f["significado_mistico"]] if c == "es"
                                 else list(d["significados"][(c, n)])) for c in codigos},
        }
    return {
        "idiomas": [list(par) for par in d["idiomas"]],
        "alcances": list(ALCANCES),
        "veredictos": list(VEREDICTOS),
        "colores": colores,
        "lexico": lexico,
        "categorias": categorias,
    }


def visor_html(d, plantilla):
    """El texto de visor-plantilla.html con los datos dentro. Con `</` escapado para que un dato no cierre el <script>."""
    if plantilla.count(MARCA_DATOS) != 1:
        raise ValueError(f"visor-plantilla.html debe contener exactamente una vez {MARCA_DATOS}")
    texto = json.dumps(datos_visor(d), ensure_ascii=False, separators=(",", ":")).replace("</", "<\\/")
    return plantilla.replace(MARCA_DATOS, texto)
