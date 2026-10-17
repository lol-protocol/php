#!/usr/bin/env python3
"""Valida los CSV de Listado-colores y genera la tabla de README.md y las páginas por idioma.

- colores.csv: tabla anotada, 148 colores CSS con su forma natural en inglés, nombre en 30 idiomas, hex y
  significados en español. Su tabla resumida va en el README. De los hex se sacan los alias (colores CSS
  con el mismo hex, como aqua y cyan, o gray y grey).
- significados-idiomas.csv: los significados de esos 148 colores en los otros 29 idiomas.
- lexico-basico.csv: términos de color de uso real (blanco, azul claro, granate…) en los 30 idiomas, con
  su forma base, flexiones, sinónimos y variantes.
- nombres-completos.csv: lista completa de nombres con hex. Sus tres primeras columnas son la fuente;
  css_cercano, delta_e y cercania se calculan aquí (color CSS más cercano según CIEDE2000, en colorimetria.py).
- Por cada idioma se genera por-idioma/<código>.md (nombre, hex y los dos significados en ese idioma)
  y colores-por-idioma.md, que es el índice de esas páginas.

Uso:
  python3 generar-tabla.py              valida los CSV y regenera README.md, colores-por-idioma.md y por-idioma/
  python3 generar-tabla.py --comprobar  valida y sale con error si los archivos generados no están al día
"""
import csv
import io
import re
import sys
from pathlib import Path

CARPETA = Path(__file__).resolve().parent
sys.path.insert(0, str(CARPETA))
import colorimetria  # noqa: E402  (módulo de esta carpeta)

ANOTADA = CARPETA / "colores.csv"
COMPLETA = CARPETA / "nombres-completos.csv"
SIGNIFICADOS = CARPETA / "significados-idiomas.csv"
LEXICO = CARPETA / "lexico-basico.csv"
README = CARPETA / "README.md"
INDICE = CARPETA / "colores-por-idioma.md"
DIR_IDIOMAS = CARPETA / "por-idioma"
INICIO = "<!-- tabla:inicio -->"
FIN = "<!-- tabla:fin -->"

# Código del idioma y nombre en ese idioma. El inglés es la columna nombre_ingles.
IDIOMAS = [
    ("en", "Inglés"), ("es", "Español"), ("fr", "Francés"), ("pt", "Portugués"), ("it", "Italiano"),
    ("de", "Alemán"), ("nl", "Neerlandés"), ("sv", "Sueco"), ("da", "Danés"), ("fi", "Finés"),
    ("pl", "Polaco"), ("cs", "Checo"), ("ro", "Rumano"), ("hu", "Húngaro"), ("el", "Griego"),
    ("ru", "Ruso"), ("uk", "Ucraniano"), ("tr", "Turco"), ("ar", "Árabe"), ("he", "Hebreo"),
    ("fa", "Persa"), ("hi", "Hindi"), ("bn", "Bengalí"), ("zh", "Chino simplificado"),
    ("ja", "Japonés"), ("ko", "Coreano"), ("vi", "Vietnamita"), ("th", "Tailandés"),
    ("id", "Indonesio"), ("sw", "Suajili"),
]
COLUMNAS_ANOTADA = (
    ["nombre_ingles", "forma_natural_en"]
    + [f"nombre_{c}" for c, _ in IDIOMAS[1:]]
    + ["hex", "significado_psicologico", "significado_mistico"]
)
COLUMNAS_FUENTE_COMPLETA = ["nombre_ingles", "hex", "curada"]
COLUMNAS_COMPLETA = COLUMNAS_FUENTE_COMPLETA + ["css_cercano", "delta_e", "cercania"]
# Bandas de cercanía por ΔE00 al color CSS más cercano. Son umbrales de este proyecto, no un estándar.
BANDAS_CERCANIA = [("alta", 3.0), ("media", 8.0)]
COLUMNAS_SIGNIFICADOS = ["idioma", "nombre_ingles", "significado_psicologico", "significado_mistico"]
COLUMNAS_LEXICO = ["concepto", "idioma", "forma", "tipo", "rasgos"]
# Conceptos del léxico básico: el nombre del color CSS si existe; si no, un nombre de nombres-completos.csv.
# Los once primeros son los términos básicos de color y deben estar en los 30 idiomas.
BASICOS = ["white", "black", "red", "green", "yellow", "blue", "brown", "purple", "pink", "orange", "gray"]
CONCEPTOS = BASICOS + [
    "lightblue", "darkblue", "lightgreen", "darkgreen", "skyblue",
    "turquoise", "beige", "gold", "silver", "violet", "indigo", "lavender", "maroon", "olive", "navy", "cyan",
    "magenta", "salmon", "coral", "crimson", "khaki", "lime", "lilac", "burgundy", "cream", "scarlet", "amber",
]
TIPOS_LEXICO = ["lema", "flexion", "sinonimo", "variante"]
# Rasgos válidos de las flexiones. Las claves siguen los rasgos de Universal Dependencies (Gender, Number,
# Animacy); Terminacion marca la terminación del adjetivo alemán o neerlandés.
RASGOS_FLEXION = {
    "Gender=Masc;Number=Sing": "m", "Gender=Fem;Number=Sing": "f", "Gender=Neut;Number=Sing": "n",
    "Gender=Masc;Number=Plur": "m pl", "Gender=Fem;Number=Plur": "f pl", "Gender=Neut;Number=Plur": "n pl",
    "Number=Plur": "pl", "Number=Plur;Animacy=Anim": "pl animado",
    "Terminacion=-e": "-e", "Terminacion=-en": "-en", "Terminacion=-er": "-er",
    "Terminacion=-es": "-es", "Terminacion=-em": "-em",
}
RE_ETIQUETA = re.compile(r"(region|ortografia|registro|uso|forma)=[A-Za-z_-]+")
# Idiomas cuya escritura no es latina: sus formas no pueden llevar letras latinas.
SIN_LETRAS_LATINAS = {"el", "ru", "uk", "ar", "he", "fa", "hi", "bn", "zh", "ja", "ko", "th"}
RE_NOMBRE_CSS = re.compile(r"[a-z]+")
RE_FORMA_NATURAL = re.compile(r"[a-z]+( [a-z]+)*")
RE_HEX = re.compile(r"#[0-9A-F]{6}")


def leer(ruta, columnas):
    with ruta.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames != columnas:
            sys.exit(f"{ruta.name}: cabecera inesperada {lector.fieldnames}")
        return list(lector)


def leer_completa():
    """Lee nombres-completos.csv con la cabecera completa o solo con las tres columnas de la fuente."""
    with COMPLETA.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames not in (COLUMNAS_FUENTE_COMPLETA, COLUMNAS_COMPLETA):
            sys.exit(f"{COMPLETA.name}: cabecera inesperada {lector.fieldnames}")
        return [{c: fila[c] for c in COLUMNAS_FUENTE_COMPLETA} for fila in lector]


def validar_anotada(filas, errores):
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        if any(not (fila[c] or "").strip() for c in COLUMNAS_ANOTADA):
            errores.append(f"colores.csv línea {n}: hay campos vacíos")
            continue
        if any("|" in v or "\n" in v for v in fila.values()):
            errores.append(f"colores.csv línea {n}: no puede llevar '|' ni saltos de línea")
        nombre = fila["nombre_ingles"]
        if not RE_NOMBRE_CSS.fullmatch(nombre):
            errores.append(f"colores.csv línea {n}: nombre_ingles debe ser minúsculas sin espacios: {nombre!r}")
        if nombre in vistos:
            errores.append(f"colores.csv línea {n}: nombre repetido: {nombre!r}")
        vistos.add(nombre)
        natural = fila["forma_natural_en"]
        if not RE_FORMA_NATURAL.fullmatch(natural) or natural.replace(" ", "") != nombre:
            errores.append(f"colores.csv línea {n}: forma_natural_en debe ser {nombre!r} con espacios entre palabras: {natural!r}")
        if not RE_HEX.fullmatch(fila["hex"]):
            errores.append(f"colores.csv línea {n}: hex inválido {fila['hex']!r} (#RRGGBB en mayúsculas)")
    nombres = [f["nombre_ingles"] for f in filas]
    if nombres != sorted(nombres):
        errores.append("colores.csv: las filas deben estar ordenadas por nombre_ingles")


def validar_completa(filas, errores):
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        nombre = fila["nombre_ingles"]
        if not nombre.strip() or nombre != nombre.strip():
            errores.append(f"nombres-completos.csv línea {n}: nombre vacío o con espacios sobrantes")
            continue
        if any(c in nombre for c in '|,"\n'):
            errores.append(f"nombres-completos.csv línea {n}: no puede llevar '|', ',', comillas ni saltos de línea")
        if nombre.lower() in vistos:
            errores.append(f"nombres-completos.csv línea {n}: nombre repetido (sin distinguir mayúsculas): {nombre!r}")
        vistos.add(nombre.lower())
        if not RE_HEX.fullmatch(fila["hex"]):
            errores.append(f"nombres-completos.csv línea {n}: hex inválido {fila['hex']!r}")
        if fila["curada"] not in ("", "x"):
            errores.append(f"nombres-completos.csv línea {n}: curada debe ser vacía o 'x'")
    claves = [(f["nombre_ingles"].lower(), f["nombre_ingles"]) for f in filas]
    if claves != sorted(claves):
        errores.append("nombres-completos.csv: las filas deben estar ordenadas por nombre")


def validar_significados(filas, anotada, errores):
    """Cada color de colores.csv debe tener su par de significados en cada idioma, salvo el español."""
    orden = {codigo: n for n, codigo in enumerate(c for c, _ in IDIOMAS if c != "es")}
    es = {f["nombre_ingles"]: f for f in anotada}
    vistos = set()
    for n, fila in enumerate(filas, start=2):
        idioma, nombre = fila["idioma"], fila["nombre_ingles"]
        if idioma not in orden:
            errores.append(f"significados-idiomas.csv línea {n}: idioma desconocido o español: {idioma!r}")
            continue
        if nombre not in es:
            errores.append(f"significados-idiomas.csv línea {n}: {nombre!r} no está en colores.csv")
            continue
        if (idioma, nombre) in vistos:
            errores.append(f"significados-idiomas.csv línea {n}: repetido {idioma}/{nombre}")
        vistos.add((idioma, nombre))
        for campo in ("significado_psicologico", "significado_mistico"):
            valor = fila[campo]
            if not valor.strip() or valor != valor.strip():
                errores.append(f"significados-idiomas.csv línea {n}: {campo} vacío o con espacios sobrantes")
            elif "|" in valor or "\n" in valor:
                errores.append(f"significados-idiomas.csv línea {n}: {campo} no puede llevar '|' ni saltos de línea")
            elif valor == es[nombre][campo]:
                errores.append(f"significados-idiomas.csv línea {n}: {campo} de {idioma}/{nombre} está sin traducir")
    faltan = [(c, nom) for c in orden for nom in es if (c, nom) not in vistos]
    if faltan:
        ejemplo = ", ".join(f"{c}/{nom}" for c, nom in faltan[:5])
        errores.append(f"significados-idiomas.csv: faltan {len(faltan)} combinaciones idioma/color (por ejemplo {ejemplo})")
    claves = [(f["nombre_ingles"], orden.get(f["idioma"], -1)) for f in filas]
    if claves != sorted(claves):
        errores.append("significados-idiomas.csv: las filas deben ir ordenadas por nombre_ingles y, dentro, por el orden de IDIOMAS en generar-tabla.py")


def hex_de_concepto(concepto, anotada, completa):
    """Hex de referencia de un concepto: el del color CSS o, si no lo hay, el del nombre de la lista completa."""
    for f in anotada:
        if f["nombre_ingles"] == concepto:
            return f["hex"]
    for f in completa:
        if f["nombre_ingles"].lower() == concepto:
            return f["hex"]
    return None


def validar_lexico(filas, anotada, completa, errores):
    """El léxico básico: valida cada fila y que cada concepto tenga su lema en cada idioma donde aparece."""
    codigos = [c for c, _ in IDIOMAS]
    orden_tipo = {tipo: n for n, tipo in enumerate(TIPOS_LEXICO)}
    for concepto in CONCEPTOS:
        if hex_de_concepto(concepto, anotada, completa) is None:
            errores.append(f"lexico-basico.csv: el concepto {concepto!r} no está en colores.csv ni en nombres-completos.csv")
    vistos, lemas, presentes = set(), {}, set()
    claves = []
    for n, fila in enumerate(filas, start=2):
        concepto, idioma, forma, tipo, rasgos = (fila[c] for c in COLUMNAS_LEXICO)
        if concepto not in CONCEPTOS or idioma not in codigos or tipo not in TIPOS_LEXICO:
            errores.append(f"lexico-basico.csv línea {n}: concepto, idioma o tipo desconocido ({concepto}, {idioma}, {tipo})")
            continue
        claves.append((CONCEPTOS.index(concepto), codigos.index(idioma), orden_tipo[tipo]))
        if not forma.strip() or forma != forma.strip() or any(c in forma for c in '|\n"'):
            errores.append(f"lexico-basico.csv línea {n}: forma vacía, con espacios sobrantes o con '|', comillas o saltos de línea")
        if idioma in SIN_LETRAS_LATINAS and re.search(r"[A-Za-z]", forma):
            errores.append(f"lexico-basico.csv línea {n}: letras latinas en {idioma}: {forma!r}")
        if tipo == "flexion" and rasgos not in RASGOS_FLEXION:
            errores.append(f"lexico-basico.csv línea {n}: rasgos de flexión no válidos: {rasgos!r}")
        if tipo == "lema" and rasgos:
            errores.append(f"lexico-basico.csv línea {n}: un lema no lleva rasgos")
        if tipo in ("sinonimo", "variante") and rasgos and not RE_ETIQUETA.fullmatch(rasgos):
            errores.append(f"lexico-basico.csv línea {n}: etiqueta no válida: {rasgos!r}")
        clave = (concepto, idioma, forma, tipo, rasgos)
        if clave in vistos:
            errores.append(f"lexico-basico.csv línea {n}: fila repetida {clave}")
        vistos.add(clave)
        presentes.add((concepto, idioma))
        if tipo == "lema":
            if (concepto, idioma) in lemas:
                errores.append(f"lexico-basico.csv línea {n}: {concepto}/{idioma} tiene más de un lema")
            lemas[(concepto, idioma)] = forma
    for concepto, idioma in sorted(presentes, key=lambda par: (CONCEPTOS.index(par[0]), codigos.index(par[1]))):
        if (concepto, idioma) not in lemas:
            errores.append(f"lexico-basico.csv: {concepto}/{idioma} tiene formas pero no lema")
    faltan = [(c, i) for c in BASICOS for i in codigos if (c, i) not in lemas]
    if faltan:
        ejemplo = ", ".join(f"{c}/{i}" for c, i in faltan[:5])
        errores.append(f"lexico-basico.csv: faltan {len(faltan)} términos básicos (por ejemplo {ejemplo})")
    if claves != sorted(claves):
        errores.append("lexico-basico.csv: las filas deben ir ordenadas por concepto, idioma y tipo (lema, flexion, sinonimo, variante)")


def alias_de(anotada):
    """Para cada color CSS, los otros que tienen su mismo hex: [(nombre, tipo)].

    El tipo es 'ortografia' si solo cambia gray por grey (la especificación CSS define las dos
    grafías) y 'mismo_color' si son nombres distintos del mismo color (aqua y cyan, fuchsia y magenta).
    """
    por_hex = {}
    for f in anotada:
        por_hex.setdefault(f["hex"], []).append(f["nombre_ingles"])
    alias = {f["nombre_ingles"]: [] for f in anotada}
    for nombres in por_hex.values():
        for a in nombres:
            for b in nombres:
                if a != b:
                    tipo = "ortografia" if a.replace("grey", "gray") == b.replace("grey", "gray") else "mismo_color"
                    alias[a].append((b, tipo))
    return alias


def cercania_de(delta, mismo_hex):
    if mismo_hex:
        return "exacto"
    for banda, tope in BANDAS_CERCANIA:
        if delta <= tope:
            return banda
    return "baja"


def calcular_cercanias(completa, anotada):
    """Para cada nombre de la lista completa: color CSS más cercano (CIEDE2000), distancia y banda.

    Si dos colores CSS están igual de cerca (por ejemplo gray y grey, que comparten hex), gana el
    primero por orden alfabético. La comparación redondea a 9 decimales para que el desempate no
    dependa del ruido de coma flotante entre plataformas.
    """
    referencias = sorted((f["nombre_ingles"], f["hex"], colorimetria.hex_a_lab(f["hex"])) for f in anotada)
    resultado = []
    for fila in completa:
        lab = colorimetria.hex_a_lab(fila["hex"])
        distancia, nombre, color = min(
            (round(colorimetria.ciede2000(lab, ref_lab), 9), ref_nombre, ref_hex)
            for ref_nombre, ref_hex, ref_lab in referencias
        )
        resultado.append((nombre, distancia, cercania_de(distancia, color == fila["hex"])))
    return resultado


def texto_completa(completa, cercanias):
    salida = io.StringIO()
    escritor = csv.writer(salida, lineterminator="\n")
    escritor.writerow(COLUMNAS_COMPLETA)
    for fila, (css, distancia, cercania) in zip(completa, cercanias):
        escritor.writerow([fila["nombre_ingles"], fila["hex"], fila["curada"], css, f"{distancia:.2f}", cercania])
    return salida.getvalue()


def tabla_readme(filas):
    lineas = [
        "| Inglés | Español | Hex | Significado psicológico | Significado místico |",
        "|---|---|---|---|---|",
    ]
    for f in filas:
        lineas.append(
            f"| `{f['nombre_ingles']}` | {f['nombre_es']} | `{f['hex']}` "
            f"| {f['significado_psicologico']} | {f['significado_mistico']} |"
        )
    return "\n".join(lineas)


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
        lema = next(f["forma"] for f in filas if f["tipo"] == "lema")
        flexiones = ", ".join(f"{f['forma']} ({RASGOS_FLEXION[f['rasgos']]})" for f in filas if f["tipo"] == "flexion")

        def con_etiqueta(f):
            return f"{f['forma']} ({f['rasgos']})" if f["rasgos"] else f["forma"]

        sinonimos = ", ".join(con_etiqueta(f) for f in filas if f["tipo"] == "sinonimo")
        variantes = ", ".join(con_etiqueta(f) for f in filas if f["tipo"] == "variante")
        hex_ref = hex_de_concepto(concepto, anotada, completa)
        lineas.append(f"| `{concepto}` | `{hex_ref}` | {lema} | {flexiones} | {sinonimos} | {variantes} |")
    return "\n".join(lineas)


def pagina_idioma(codigo, etiqueta, anotada, significados, lexico, completa):
    """Página con nombre, hex, alias y los dos significados de los 148 colores en un idioma."""
    es = codigo == "es"
    alias = alias_de(anotada)
    # En inglés la columna de nombre es la forma natural (con espacios); en los demás, la traducción.
    columna_nombre = "Forma natural" if codigo == "en" else "Nombre"
    cabecera = f"| Inglés | {columna_nombre} | Hex | Alias | Significado psicológico | Significado místico |"
    separador = "|---|---|---|---|---|---|"
    partes = [
        f"# Colores con nombre en {etiqueta} (`{codigo}`)",
        "",
        "Generado por `generar-tabla.py` a partir de `colores.csv`"
        + ("." if es else " y `significados-idiomas.csv`. No editar a mano."),
        "",
        "Las traducciones son propuestas y no están revisadas por hablantes nativos."
        if not es else "Los nombres y los significados en español son el original de `colores.csv`.",
        "Alias: otros colores CSS con exactamente el mismo hex (`gray` y `grey`; `aqua` y `cyan`; `fuchsia` y `magenta`).",
        "Volver al [índice de idiomas](../colores-por-idioma.md) o al [README](../README.md).",
        "",
        cabecera,
        separador,
    ]
    for f in anotada:
        nombre = f["nombre_ingles"]
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
        ]
        partes.append("| " + " | ".join(celdas) + " |")
    partes += [
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


def main():
    anotada = leer(ANOTADA, COLUMNAS_ANOTADA)
    fallos = colorimetria.autoprueba()
    if fallos:
        sys.exit(f"colorimetria.py: CIEDE2000 no coincide con {len(fallos)} vectores de Sharma et al.: {fallos[:2]}")
    completa = leer_completa()
    traducidos = leer(SIGNIFICADOS, COLUMNAS_SIGNIFICADOS)
    lexico = leer(LEXICO, COLUMNAS_LEXICO)
    errores = []
    validar_anotada(anotada, errores)
    validar_completa(completa, errores)
    validar_significados(traducidos, anotada, errores)
    validar_lexico(lexico, anotada, completa, errores)
    if errores:
        sobran = f"\n  ... y {len(errores) - 30} errores más" if len(errores) > 30 else ""
        sys.exit("Errores en los CSV:\n  " + "\n  ".join(errores[:30]) + sobran)

    texto = README.read_text(encoding="utf-8")
    # Cada marcador debe ocupar una línea entera: así no coincide con menciones en la prosa.
    patron = re.compile(r"^" + re.escape(INICIO) + r"$.*?^" + re.escape(FIN) + r"$", re.S | re.M)
    if not patron.search(texto):
        sys.exit(f"README.md: faltan los marcadores {INICIO} y {FIN}")

    significados = {(f["idioma"], f["nombre_ingles"]): (f["significado_psicologico"], f["significado_mistico"])
                    for f in traducidos}
    generados = {
        README: patron.sub(lambda _: f"{INICIO}\n{tabla_readme(anotada)}\n{FIN}", texto),
        INDICE: indice_idiomas(anotada),
        COMPLETA: texto_completa(completa, calcular_cercanias(completa, anotada)),
    }
    for codigo, etiqueta in IDIOMAS:
        generados[DIR_IDIOMAS / f"{codigo}.md"] = pagina_idioma(codigo, etiqueta, anotada, significados, lexico, completa)
    sobrantes = sorted(p for p in DIR_IDIOMAS.glob("*") if p not in generados) if DIR_IDIOMAS.exists() else []

    resumen = (f"{len(anotada)} colores anotados en {len(IDIOMAS)} idiomas, "
               f"{len(traducidos)} significados traducidos, {len(lexico)} formas de léxico básico y {len(completa)} nombres completos")
    if "--comprobar" in sys.argv:
        desfasados = [p.relative_to(CARPETA).as_posix() for p, contenido in generados.items()
                      if not p.exists() or p.read_text(encoding="utf-8") != contenido]
        desfasados += [f"{p.relative_to(CARPETA).as_posix()} (sobra)" for p in sobrantes]
        if desfasados:
            print(f"No coinciden con los CSV: {', '.join(desfasados)}. "
                  "Ejecuta python3 generar-tabla.py", file=sys.stderr)
            return 1
        print(f"OK: {resumen}; archivos generados al día.")
        return 0

    DIR_IDIOMAS.mkdir(exist_ok=True)
    for p, contenido in generados.items():
        p.write_text(contenido, encoding="utf-8")
    for p in sobrantes:
        p.unlink()
    print(f"Generados README.md, nombres-completos.csv, colores-por-idioma.md y {len(IDIOMAS)} páginas en por-idioma/: {resumen}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
