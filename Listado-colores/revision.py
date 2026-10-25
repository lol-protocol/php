"""Revisión por hablantes nativos de Listado-colores: validación de revisiones.csv, el informe revisiones.md y el visor HTML.

Solo biblioteca estándar. Lo usa generar-tabla.py. Casi todo el contenido del proyecto lo escribió un asistente de IA sin
revisión de hablantes nativos; revisiones.csv es el registro de quién ha revisado qué. Cada fila dice que una persona
revisó un elemento (el nombre de un color en un idioma, sus significados, un concepto del léxico o las categorías
gramaticales de un concepto) y con qué resultado. El archivo empieza vacío: mientras no haya filas, nada está revisado.
"""
import datetime
import json
import re

COLUMNAS = ["idioma", "alcance", "elemento", "veredicto", "revisor", "fecha", "huella", "nota"]
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
CONTAN = ("correcto", "corregir", "dudoso", "corregido")  # todos los veredictos dejan constancia de que alguien miró


def huella(texto):
    """Suma FNV-1a de 32 bits del texto en UTF-8, en 8 cifras hexadecimales: cambia si cambia lo revisado.

    No es criptográfica ni hace falta que lo sea: solo detecta que el dato ya no es el que vio el revisor.
    """
    h = 0x811C9DC5
    for byte in texto.encode("utf-8"):
        h = ((h ^ byte) * 0x01000193) & 0xFFFFFFFF
    return f"{h:08x}"


def huellas(d):
    """{(idioma, alcance): {elemento: huella}} del contenido que ve el revisor, ahora mismo."""
    codigos = [c for c, _ in d["idiomas"]]
    lexico, categorias = {}, {}
    for f in d["lexico"]:
        lexico.setdefault((f["idioma"], f["concepto"]), []).append(f"{f['forma']}\t{f['tipo']}\t{f['rasgos']}")
    for f in d["categorias"]:
        categorias.setdefault((f["idioma"], f["concepto"]), []).append(
            f"{f['forma']}\t{f['categoria']}\t{f['ocurrencia']}\t{f['ejemplo']}")
    resultado = {}
    for codigo in codigos:
        resultado[(codigo, "nombres")] = {
            f["nombre_ingles"]: huella(f["forma_natural_en"] if codigo == "en" else f[f"nombre_{codigo}"]) for f in d["anotada"]}
        resultado[(codigo, "significados")] = {
            f["nombre_ingles"]: huella("\n".join(d["significados"][(codigo, f["nombre_ingles"])] if codigo != "es"
                                                 else (f["significado_psicologico"], f["significado_mistico"])))
            for f in d["anotada"]}
    for (codigo, concepto), lineas in lexico.items():
        resultado.setdefault((codigo, "lexico"), {})[concepto] = huella("\n".join(lineas))
    for (codigo, concepto), lineas in categorias.items():
        resultado.setdefault((codigo, "categorias"), {})[concepto] = huella("\n".join(lineas))
    return resultado


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
        idioma, alcance, elemento, veredicto, revisor, fecha, huella_revisada, nota = (fila[c] for c in COLUMNAS)
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
        if not re.fullmatch(r"[0-9a-f]{8}", huella_revisada):
            errores.append(f"{donde}: la huella debe ser de 8 cifras hexadecimales en minúscula (la da el visor): {huella_revisada!r}")
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


def situacion(filas, huellas_actuales):
    """Qué dice cada fila ante el dato de hoy: ({atención: [filas]}, {(idioma, alcance): {elementos revisados}}).

    - `caducada`: un `correcto` cuyo dato cambió después de la revisión; ya no cuenta como revisado.
    - `ya_cambio`: un `corregir` o `dudoso` cuyo dato cambió: quizá se corrigió, falta marcarlo `corregido`.
    - `sin_cambio`: un `corregido` cuyo dato sigue igual que cuando se revisó: la corrección no está aplicada.
    - `conflicto`: un elemento con un `correcto` vigente de una persona y un `corregir` o `dudoso` de otra.
    """
    atencion = {"caducada": [], "ya_cambio": [], "sin_cambio": [], "conflicto": []}
    revisados = {}
    vigentes = {}
    for f in filas:
        actual = huellas_actuales[(f["idioma"], f["alcance"])][f["elemento"]]
        igual = f["huella"] == actual
        if f["veredicto"] == "correcto" and not igual:
            atencion["caducada"].append(f)
            continue  # no cuenta: nadie ha visto el dato de hoy
        if f["veredicto"] in ("corregir", "dudoso") and not igual:
            atencion["ya_cambio"].append(f)
        if f["veredicto"] == "corregido" and igual:
            atencion["sin_cambio"].append(f)
        revisados.setdefault((f["idioma"], f["alcance"]), set()).add(f["elemento"])
        vigentes.setdefault((f["idioma"], f["alcance"], f["elemento"]), []).append(f)
    for lista in vigentes.values():
        if any(f["veredicto"] == "correcto" for f in lista) and any(f["veredicto"] in ("corregir", "dudoso") and f["huella"] == huellas_actuales[(f["idioma"], f["alcance"])][f["elemento"]] for f in lista):
            atencion["conflicto"].extend(lista)
    return atencion, revisados


def informe(filas, universo_revisable, idiomas, huellas_actuales):
    """El texto de revisiones.md. `idiomas` es la lista [(código, nombre)] de generar-tabla.py."""
    nombres = dict(idiomas)
    atencion, por_celda = situacion(filas, huellas_actuales)
    total = sum(len(v) for v in universo_revisable.values())
    revisados = sum(len(v) for v in por_celda.values())
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
        "2. Marca cada elemento: `correcto`, `corregir` (con la corrección propuesta en la nota) o `dudoso`. Escribe tu nombre o alias y la fecha. Cada fila lleva una huella del dato que viste: si el dato cambia después, la revisión caduca.",
        "3. Copia las filas que genera el visor y añádelas al final de `revisiones.csv` (en un PR o pásalas a quien mantiene el proyecto). "
        "El orden de las filas no importa; el generador las valida.",
        "4. Quien mantiene el proyecto aplica las correcciones a los CSV y cambia esas filas de `corregir` a `corregido` (sin tocar la huella: así se ve que el dato cambió).",
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
                hechos = len(por_celda.get((codigo, alcance), set()))
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
    partes += ["", "## Atención", ""]
    avisos = [
        ("caducada", "Revisiones `correcto` caducadas",
         "El dato cambió después de la revisión: ya no cuentan como revisadas. Hay que volver a revisarlas."),
        ("ya_cambio", "Correcciones o dudas cuyo dato ya cambió",
         "Quizá ya se corrigieron: si es así, cambia la fila a `corregido`."),
        ("sin_cambio", "Filas `corregido` con el dato sin cambios",
         "El dato sigue igual que cuando se revisó: la corrección no parece aplicada."),
        ("conflicto", "Revisores en desacuerdo",
         "Un elemento con `correcto` de una persona y `corregir` o `dudoso` de otra, sobre el dato de hoy."),
    ]
    orden_idioma = {c: n for n, (c, _) in enumerate(idiomas)}
    orden_alcance = {a: n for n, a in enumerate(ALCANCES)}
    hay = False
    for clave, titulo, texto in avisos:
        if not atencion[clave]:
            continue
        hay = True
        partes += [f"### {titulo}", "", texto, "", "| Idioma | Alcance | Elemento | Veredicto | Revisor | Fecha |", "|---|---|---|---|---|---|"]
        for f in sorted(atencion[clave], key=lambda f: (orden_idioma[f["idioma"]], orden_alcance[f["alcance"]],
                                                         universo_revisable[(f["idioma"], f["alcance"])].index(f["elemento"]), f["revisor"].casefold())):
            partes.append(f"| `{f['idioma']}` | `{f['alcance']}` | `{f['elemento']}` | `{f['veredicto']}` | {_celda(f['revisor'])} | {f['fecha']} |")
        partes.append("")
    if not hay:
        partes += ["Nada que atender: ninguna revisión caducada, sin aplicar ni en conflicto.", ""]
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
    todas = huellas(d)
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
        "huellas": {idioma: {a: h for (i, a), h in todas.items() if i == idioma} for idioma in codigos},
        "lexico": lexico,
        "categorias": categorias,
    }


def visor_html(d, plantilla):
    """El texto de visor-plantilla.html con los datos dentro. Con `</` escapado para que un dato no cierre el <script>."""
    if plantilla.count(MARCA_DATOS) != 1:
        raise ValueError(f"visor-plantilla.html debe contener exactamente una vez {MARCA_DATOS}")
    texto = json.dumps(datos_visor(d), ensure_ascii=False, separators=(",", ":")).replace("</", "<\\/")
    return plantilla.replace(MARCA_DATOS, texto)
