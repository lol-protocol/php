"""Las páginas Markdown que genera Listado-colores: la tabla del README, las páginas por idioma y su índice.
"""

from constantes import *  # noqa: F401,F403
from md import celda, orden_idiomas
from validadores import alias_de, hex_de_concepto, ids_de



def etiqueta_base(base, referencias, etiquetas, prefijo):
    """Base de un significado con sus referencias enlazadas a referencias.md."""
    texto = etiquetas[base]
    enlaces = ", ".join(f"[{i}]({prefijo}referencias.md#{i})" for i in ids_de(referencias))
    return f"{texto} ({enlaces})" if enlaces else texto


def tabla_readme(filas, evidencia):
    lineas = [
        "| Inglés | Español | Hex | Significado psicológico | Significado místico | Base psicológica | Base mística |",
        "|---|---|---|---|---|---|---|",
    ]
    for f in filas:
        e = evidencia[f["nombre_ingles"]]
        lineas.append(
            f"| `{f['nombre_ingles']}` | {f['nombre_es']} | `{f['hex']}` "
            f"| {f['significado_psicologico']} | {f['significado_mistico']} "
            f"| {etiqueta_base(e['base_psicologico'], e['referencias_psicologico'], BASE_PSICOLOGICO, '')} "
            f"| {etiqueta_base(e['base_mistico'], e['referencias_mistico'], BASE_MISTICO, '')} |"
        )
    return "\n".join(lineas)


def tabla_culturales(codigo, culturales, prefijo="../"):
    """Matices culturales de los ámbitos asociados a un idioma (texto en español)."""
    propios = [f for f in culturales if codigo in AMBITOS[f["ambito"]][1]]
    if not propios:
        return "No hay matices culturales registrados para este idioma. La cobertura es parcial: ver el README."
    ambitos = [AMBITOS[a][0] for a in AMBITOS if codigo in AMBITOS[a][1] and any(f["ambito"] == a for f in propios)]
    lineas = [
        f"Ámbitos asociados a este idioma: {', '.join(ambitos)}. Un idioma no equivale a una cultura, y dentro de cada ámbito "
        "hay variación entre regiones, épocas y generaciones. Los matices están en español y la cobertura es parcial.",
        "",
        "| Color | Ámbito | Matiz | Solidez | Fuentes |",
        "|---|---|---|---|---|",
    ]
    for f in propios:
        enlaces = ", ".join(f"[{i}]({prefijo}referencias.md#{i})" for i in ids_de(f["referencias"]))
        lineas.append(f"| `{f['color']}` | {AMBITOS[f['ambito']][0]} | {f['matiz']} | {f['solidez']} | {enlaces} |")
    return "\n".join(lineas)


def referencias_md(refs):
    partes = [
        "# Referencias",
        "",
        "Generado por `generar-tabla.py` a partir de `referencias.csv`. No editar a mano.",
        "",
        "Fuentes de `evidencia.csv` y `significados-culturales.csv`. La columna *Verificación* dice cuánto se pudo comprobar de cada una:",
        "",
        *[f"- **{nivel}**: {texto}." for nivel, texto in VERIFICACION.items()],
        "",
        "Que una obra figure aquí no significa que respalde cada frase de los significados: indica dónde buscar.",
        "",
    ]
    for r in refs:
        enlace = f" <{r['url']}>" if r["url"] else ""
        partes += [
            f"<a id=\"{r['id']}\"></a>",
            f"**{r['id']}** ({TIPOS_REFERENCIA[r['tipo']]}). {r['cita']}{enlace} Verificación: {r['verificacion']}.",
            "",
        ]
    return "\n".join(partes)


def tabla_lexico(codigo, lexico, anotada, completa):
    """Tabla del léxico básico de un idioma: forma base, flexiones, sinónimos y variantes por concepto."""
    por_concepto = {}
    for f in lexico:
        if f["idioma"] == codigo:
            por_concepto.setdefault(f["concepto"], []).append(f)
    lineas = ["| Concepto | Hex | Forma base | Flexiones | Sinónimos | Variantes |", "|---|---|---|---|---|---|"]
    for concepto in CONCEPTOS:
        filas = por_concepto.get(concepto)
        if not filas:
            continue
        lema = next(f["forma"] + (" (propuesta de IA sin revisar)" if f["rasgos"] == ORIGEN_IA else "")
                    for f in filas if f["tipo"] == "lema")
        flexiones = ", ".join(f"{f['forma']} ({RASGOS_FLEXION[f['rasgos']]})" for f in filas if f["tipo"] == "flexion")

        def con_etiqueta(f):
            return f"{f['forma']} ({f['rasgos']})" if f["rasgos"] else f["forma"]

        sinonimos = ", ".join(con_etiqueta(f) for f in filas if f["tipo"] == "sinonimo")
        variantes = ", ".join(con_etiqueta(f) for f in filas if f["tipo"] == "variante")
        hex_ref = hex_de_concepto(concepto, anotada, completa)
        lineas.append(f"| `{concepto}` | `{hex_ref}` | {lema} | {flexiones} | {sinonimos} | {variantes} |")
    return "\n".join(lineas)


def pagina_idioma(codigo, etiqueta, anotada, significados, lexico, completa, evidencia, culturales, alias=None):
    """Página con nombre, hex, alias y los dos significados de los 148 colores en un idioma."""
    es = codigo == "es"
    alias = alias_de(anotada) if alias is None else alias
    # En inglés la columna de nombre es la forma natural (con espacios); en los demás, la traducción.
    columna_nombre = "Forma natural" if codigo == "en" else "Nombre"
    cabecera = (f"| Inglés | {columna_nombre} | Hex | Alias | Significado psicológico | Significado místico "
                "| Base psicológica | Base mística |")
    separador = "|---|---|---|---|---|---|---|---|"
    partes = [
        f"# Colores con nombre en {etiqueta} (`{codigo}`)",
        "",
        "Generado por `generar-tabla.py` a partir de `colores.csv`"
        + ("." if es else " y `significados-idiomas.csv`. No editar a mano."),
        "",
        "Las traducciones son propuestas y no están revisadas por hablantes nativos."
        if not es else "Los nombres y los significados en español son el original de `colores.csv`.",
        "Alias: otros colores CSS con exactamente el mismo hex (`gray` y `grey`; `aqua` y `cyan`; `fuchsia` y `magenta`).",
        "Base: en qué se apoya cada significado (ver [referencias](../referencias.md) y el README). «Hay estudios» indica que existen "
        "estudios sobre ese color, no que el texto resuma sus resultados; «derivado» quiere decir que lo extrapoló el autor.",
        "Volver al [índice de idiomas](../colores-por-idioma.md) o al [README](../README.md).",
        "",
        cabecera,
        separador,
    ]
    for f in anotada:
        nombre = f["nombre_ingles"]
        e = evidencia[nombre]
        if es:
            psicologico, mistico = f["significado_psicologico"], f["significado_mistico"]
        else:
            psicologico, mistico = significados[(codigo, nombre)]
        celdas = [
            f"`{nombre}`",
            f["forma_natural_en"] if codigo == "en" else f[f"nombre_{codigo}"],
            f"`{f['hex']}`",
            ", ".join(f"`{otro}`" for otro, _ in alias[nombre]),
            psicologico,
            mistico,
            etiqueta_base(e["base_psicologico"], e["referencias_psicologico"], BASE_PSICOLOGICO, "../"),
            etiqueta_base(e["base_mistico"], e["referencias_mistico"], BASE_MISTICO, "../"),
        ]
        partes.append("| " + " | ".join(celdas) + " |")
    partes += [
        "",
        f"## Matices culturales ({etiqueta})",
        "",
        tabla_culturales(codigo, culturales),
        "",
        f"## Léxico básico en {etiqueta}",
        "",
        "Términos de color de uso real, con su forma base y las formas que puede tomar. La forma base es la de un diccionario; "
        "las flexiones solo incluyen el nominativo en los idiomas con casos. Detalles en el README.",
        "",
        tabla_lexico(codigo, lexico, anotada, completa),
        "",
    ]
    return "\n".join(partes)


def indice_idiomas(anotada):
    partes = [
        "# Colores con nombre por idioma",
        "",
        "Generado por `generar-tabla.py`. No editar a mano.",
        "",
        f"Un archivo por idioma, con los {len(anotada)} colores CSS: su nombre, su valor hex y sus significados "
        "psicológico y místico en ese idioma. Las traducciones son propuestas y no están revisadas "
        "por hablantes nativos. La tabla en español está también en el [README](README.md).",
        "",
        "| Código | Idioma | Archivo |",
        "|---|---|---|",
    ]
    for codigo, etiqueta in IDIOMAS:
        partes.append(f"| `{codigo}` | {etiqueta} | [por-idioma/{codigo}.md](por-idioma/{codigo}.md) |")
    partes.append("")
    return "\n".join(partes)
