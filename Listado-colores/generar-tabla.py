#!/usr/bin/env python3
"""Valida los CSV de Listado-colores y genera la tabla de README.md y las páginas por idioma.

- colores.csv: tabla anotada, 148 colores CSS con su forma natural en inglés, nombre en 30 idiomas, hex y
  significados en español. Su tabla resumida va en el README. De los hex se sacan los alias (colores CSS
  con el mismo hex, como aqua y cyan, o gray y grey).
- significados-idiomas.csv: los significados de esos 148 colores en los otros 29 idiomas.
- lexico-basico.csv: términos de color de uso real (blanco, azul claro, granate…) en los 30 idiomas, con
  su forma base, flexiones, sinónimos y variantes.
- evidencia.csv: en qué se apoya cada significado (hay estudios, tradición, derivado) y con qué fuentes.
- significados-culturales.csv: matices por ámbito cultural (China, Japón, India…) de algunos colores.
- categorias.csv: las categorías gramaticales (ADJ, NOUN, PROPN, VERB) de las palabras de color de una sola palabra
  en inglés y español, con una frase de ejemplo; se genera categorias.md (ver categorias.py).
- revisiones.csv: el registro de qué elementos han revisado hablantes nativos y con qué resultado (empieza vacío); se
  genera revisiones.md (la cobertura por idioma); el visor para revisar se construye con --visor (ver revision.py).
- referencias.csv: las fuentes citadas por evidencia.csv y significados-culturales.csv; se genera referencias.md.
- nombres-completos.csv: lista completa de nombres con hex. Sus tres primeras columnas son la fuente;
  css_cercano, delta_e y cercania se calculan aquí (color CSS más cercano según CIEDE2000, en colorimetria.py).
- Por cada idioma se genera por-idioma/<código>.md (nombre, hex y los dos significados en ese idioma)
  y colores-por-idioma.md, que es el índice de esas páginas.

- Se generan además exportes/colores.jsonl y exportes/gazetteer.tsv (ver exportes.py) y, a petición, una
  base SQLite, ambiguedad.md (las formas que designan más de un color, ver ambiguedad.py) y exportes/manifiesto.json
  (la versión de CHANGELOG.md y las filas y sumas SHA-256 de los datos, ver manifiesto.py), y auditoria-nombres.md (los
  nombres sospechosos de la lista completa, ver auditoria.py).

Uso:
  python3 generar-tabla.py                   valida los CSV y regenera los archivos generados
  python3 generar-tabla.py --comprobar       valida y sale con error si los archivos generados no están al día
  python3 generar-tabla.py --sqlite ruta.db  valida los CSV y construye una base SQLite (no regenera nada más)
  python3 generar-tabla.py --conllu ruta.conllu  valida y escribe las frases de ejemplo en CoNLL-U (no regenera nada más)
  python3 generar-tabla.py --visor ruta.html     valida y escribe el visor de revisión, un HTML sin dependencias (no regenera nada más)
"""
import bisect
import csv
import io
import re
import sys
from pathlib import Path

CARPETA = Path(__file__).resolve().parent
sys.dont_write_bytecode = True  # no dejar __pycache__ en la carpeta al importar los módulos de al lado
sys.path.insert(0, str(CARPETA))
import ambiguedad  # noqa: E402  (módulos de esta carpeta)
import auditoria  # noqa: E402
import categorias  # noqa: E402
import cobertura  # noqa: E402
import colorimetria  # noqa: E402
import exportes  # noqa: E402
import manifiesto  # noqa: E402
from md import texto_valido  # noqa: E402
import revision  # noqa: E402

ANOTADA = CARPETA / "colores.csv"
COMPLETA = CARPETA / "nombres-completos.csv"
SIGNIFICADOS = CARPETA / "significados-idiomas.csv"
LEXICO = CARPETA / "lexico-basico.csv"
COLISIONES_REVISADAS = CARPETA / "colisiones-revisadas.csv"
REFERENCIAS = CARPETA / "referencias.csv"
EVIDENCIA = CARPETA / "evidencia.csv"
CULTURALES = CARPETA / "significados-culturales.csv"
CATEGORIAS = CARPETA / "categorias.csv"
CATEGORIAS_MD = CARPETA / "categorias.md"
COBERTURA = CARPETA / "cobertura.md"
REVISIONES = CARPETA / "revisiones.csv"
REVISIONES_MD = CARPETA / "revisiones.md"
VISOR_PLANTILLA = CARPETA / "visor-plantilla.html"
REFS_MD = CARPETA / "referencias.md"
AMBIGUEDAD = CARPETA / "ambiguedad.md"
AUDITORIA = CARPETA / "auditoria-nombres.md"
CHANGELOG = CARPETA / "CHANGELOG.md"
DIR_EXPORTES = CARPETA / "exportes"
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
COLUMNAS_REFERENCIAS = ["id", "tipo", "cita", "url", "verificacion"]
COLUMNAS_EVIDENCIA = ["nombre_ingles", "base_psicologico", "referencias_psicologico", "base_mistico", "referencias_mistico"]
COLUMNAS_CULTURALES = ["color", "ambito", "matiz", "solidez", "referencias"]
TIPOS_REFERENCIA = {"articulo": "artículo", "libro": "libro", "web": "página web"}
# Cuánto se pudo comprobar de cada fuente. Es un dato del proyecto, no una valoración de la fuente.
VERIFICACION = {
    "resumen": "la afirmación figura en el resumen (abstract) o en la descripción editorial de la obra",
    "pagina": "la afirmación figura en esa página web, que puede ser divulgativa o comercial; no se leyó una fuente primaria",
    "existencia": "se comprobó que la obra existe y de qué trata, pero no se leyó el pasaje concreto",
}
BASE_PSICOLOGICO = {"hay_estudios": "hay estudios", "derivado": "derivado"}
BASE_MISTICO = {"tradicion": "tradición", "tradicion_moderna": "tradición moderna", "derivado": "derivado"}
# Colores CSS que son (o equivalen a) los 12 términos de color de los estudios transnacionales
# (Jonauskaite et al., 2020): para ellos existen estudios; para el resto el texto es derivado.
COLORES_CON_ESTUDIOS = {"black", "white", "gray", "grey", "brown", "red", "orange", "yellow", "green",
                        "turquoise", "blue", "purple", "pink"}
SOLIDEZ = ["alta", "media", "baja"]
# Ámbito cultural: etiqueta y idiomas con los que se muestra. Un idioma no equivale a una cultura:
# la relación es aproximada y solo decide en qué páginas aparece cada nota.
AMBITOS = {
    "occidente": ("Occidente (Europa y Norteamérica)", ["en", "es", "fr", "pt", "it", "de", "nl", "sv", "da", "fi"]),
    "estados-unidos": ("Estados Unidos", ["en"]),
    "alemania": ("Alemania", ["de"]),
    "paises-bajos": ("Países Bajos", ["nl"]),
    "polonia": ("Polonia", ["pl"]),
    "rusia": ("Rusia", ["ru"]),
    "china": ("China", ["zh"]),
    "japon": ("Japón", ["ja"]),
    "corea": ("Corea", ["ko"]),
    "india": ("India", ["hi", "bn"]),
    "tailandia": ("Tailandia", ["th"]),
    "budismo-theravada": ("Budismo theravada (Sri Lanka, Camboya, Tailandia)", ["th"]),
    "mundo-islamico": ("Mundo islámico", ["ar", "fa", "tr", "id"]),
    "mediterraneo-oriental-y-oriente-medio": ("Mediterráneo oriental y Oriente Medio", ["el", "tr", "ar", "he", "fa"]),
}
RE_ID_REFERENCIA = re.compile(r"[a-z0-9]+(-[a-z0-9]+)*")
COLUMNAS_COLISIONES = ["idioma", "nombre", "colores"]
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
# Un lema con este rasgo lo propuso un asistente de IA y ningún hablante nativo lo ha revisado. Se quita al confirmarlo.
ORIGEN_IA = cobertura.ORIGEN_IA
RE_ETIQUETA = re.compile(r"(region|ortografia|registro|uso|forma)=[A-Za-z_-]+")
# Idiomas cuya escritura no es latina: sus formas no pueden llevar letras latinas.
SIN_LETRAS_LATINAS = {"el", "ru", "uk", "ar", "he", "fa", "hi", "bn", "zh", "ja", "ko", "th"}
RE_NOMBRE_CSS = re.compile(r"[a-z]+")
RE_FORMA_NATURAL = re.compile(r"[a-z]+( [a-z]+)*")
RE_HEX = re.compile(r"#[0-9A-F]{6}")


def filas_de(lector, ruta):
    """Las filas de un DictReader. Sale con error si alguna tiene más o menos campos que la cabecera.

    DictReader descarta en silencio los campos que sobran (por ejemplo, tras una coma sin comillas
    dentro de un texto, que además desplaza las columnas) y rellena con None los que faltan.
    """
    filas = []
    for fila in lector:
        if None in fila or None in fila.values():
            sys.exit(f"{ruta.name} línea {lector.line_num}: debe tener {len(lector.fieldnames)} campos, como la cabecera")
        filas.append(fila)
    return filas


def leer(ruta, columnas):
    with ruta.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames != columnas:
            sys.exit(f"{ruta.name}: cabecera inesperada {lector.fieldnames}")
        return filas_de(lector, ruta)


def leer_completa():
    """Lee nombres-completos.csv con la cabecera completa o solo con las tres columnas de la fuente."""
    with COMPLETA.open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        if lector.fieldnames not in (COLUMNAS_FUENTE_COMPLETA, COLUMNAS_COMPLETA):
            sys.exit(f"{COMPLETA.name}: cabecera inesperada {lector.fieldnames}")
        return [{c: fila[c] for c in COLUMNAS_FUENTE_COMPLETA} for fila in filas_de(lector, COMPLETA)]


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


def ids_de(campo):
    return [i for i in campo.split(";") if i]


def marcar_referencias(lista, ids, usadas, errores, archivo, n):
    """Marca cada id de `lista` como usada y reporta en `errores` los que no estén en `ids`."""
    for i in lista:
        usadas.add(i)
        if i not in ids:
            errores.append(f"{archivo} línea {n}: referencia desconocida {i!r}")


def validar_referencias(filas, errores):
    ids = []
    for n, fila in enumerate(filas, start=2):
        i = fila["id"]
        if not RE_ID_REFERENCIA.fullmatch(i):
            errores.append(f"referencias.csv línea {n}: id no válido {i!r}")
        if i in ids:
            errores.append(f"referencias.csv línea {n}: id repetido {i!r}")
        ids.append(i)
        if fila["tipo"] not in TIPOS_REFERENCIA or fila["verificacion"] not in VERIFICACION:
            errores.append(f"referencias.csv línea {n}: tipo o verificacion no válidos ({fila['tipo']}, {fila['verificacion']})")
        if not texto_valido(fila["cita"]):
            errores.append(f"referencias.csv línea {n}: cita vacía o con '|' o saltos de línea")
        if fila["url"] and not fila["url"].startswith("https://"):
            errores.append(f"referencias.csv línea {n}: la url debe empezar por https://")
        if fila["tipo"] == "web" and not fila["url"]:
            errores.append(f"referencias.csv línea {n}: una página web necesita url")
    if ids != sorted(ids):
        errores.append("referencias.csv: las filas deben ir ordenadas por id")
    return set(ids)


def validar_evidencia(filas, anotada, ids, errores, usadas):
    if [f["nombre_ingles"] for f in filas] != [f["nombre_ingles"] for f in anotada]:
        errores.append("evidencia.csv: debe tener una fila por color de colores.csv, en el mismo orden")
        return
    con_estudios = set()
    for n, fila in enumerate(filas, start=2):
        nombre = fila["nombre_ingles"]
        if fila["base_psicologico"] not in BASE_PSICOLOGICO or fila["base_mistico"] not in BASE_MISTICO:
            errores.append(f"evidencia.csv línea {n}: base no válida ({fila['base_psicologico']}, {fila['base_mistico']})")
        if fila["base_psicologico"] == "hay_estudios":
            con_estudios.add(nombre)
        for campo in ("referencias_psicologico", "referencias_mistico"):
            lista = ids_de(fila[campo])
            if lista != sorted(set(lista)):
                errores.append(f"evidencia.csv línea {n}: {campo} debe ir ordenado y sin repetidos")
            marcar_referencias(lista, ids, usadas, errores, "evidencia.csv", n)
        if fila["base_psicologico"] == "hay_estudios" and "jonauskaite2020" not in ids_de(fila["referencias_psicologico"]):
            errores.append(f"evidencia.csv línea {n}: {nombre} tiene estudios pero no cita jonauskaite2020")
        if fila["base_psicologico"] == "derivado" and fila["referencias_psicologico"]:
            errores.append(f"evidencia.csv línea {n}: un significado derivado no lleva referencias psicológicas")
        if fila["base_mistico"] == "tradicion_moderna" and "leland2016" not in ids_de(fila["referencias_mistico"]):
            errores.append(f"evidencia.csv línea {n}: {nombre} es tradición moderna pero no cita leland2016")
        if fila["base_mistico"] == "derivado" and fila["referencias_mistico"]:
            errores.append(f"evidencia.csv línea {n}: un significado derivado no lleva referencias místicas")
    if con_estudios != COLORES_CON_ESTUDIOS:
        errores.append(f"evidencia.csv: los colores con estudios deben ser exactamente {sorted(COLORES_CON_ESTUDIOS)}")


def validar_culturales(filas, anotada, ids, errores, usadas):
    orden = {f["nombre_ingles"]: n for n, f in enumerate(anotada)}
    claves = []
    for n, fila in enumerate(filas, start=2):
        if fila["color"] not in orden or fila["ambito"] not in AMBITOS or fila["solidez"] not in SOLIDEZ:
            errores.append(f"significados-culturales.csv línea {n}: color, ámbito o solidez no válidos ({fila['color']}, {fila['ambito']}, {fila['solidez']})")
            continue
        claves.append((orden[fila["color"]], fila["ambito"]))
        if not texto_valido(fila["matiz"]):
            errores.append(f"significados-culturales.csv línea {n}: matiz vacío o con '|' o saltos de línea")
        lista = ids_de(fila["referencias"])
        if not lista:
            errores.append(f"significados-culturales.csv línea {n}: cada matiz necesita al menos una referencia")
        marcar_referencias(lista, ids, usadas, errores, "significados-culturales.csv", n)
    if claves != sorted(claves):
        errores.append("significados-culturales.csv: las filas deben ir ordenadas por color (orden de colores.csv) y ámbito")


def hex_de_concepto(concepto, anotada, completa):
    """Hex de referencia de un concepto: el del color CSS o, si no lo hay, el del nombre de la lista completa."""
    for f in anotada:
        if f["nombre_ingles"] == concepto:
            return f["hex"]
    for f in completa:
        if f["nombre_ingles"].lower() == concepto:
            return f["hex"]
    return None


def hex_cotidiano_de_concepto(concepto, completa, anotada):
    """Hex del nombre de uso corriente (la entrada curada de la lista completa con el mismo nombre) o "" si no hay.

    No sustituye al hex de referencia: el color CSS `lime` es verde puro y `khaki` un amarillo pálido, y quien dice «lima» o
    «caqui» piensa en otro matiz. Se descarta (queda "") si la entrada es el hex de *otro* color CSS: en la lista completa
    `green` es #00FF00, que es `lime` de CSS, y eso no es un verde de uso corriente sino otro color con el mismo nombre.
    """
    for f in completa:
        if f["nombre_ingles"].lower() == concepto and f["curada"] == "x":
            de_otro = any(c["hex"] == f["hex"] and c["nombre_ingles"] != concepto for c in anotada)
            return "" if de_otro else f["hex"]
    return ""


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
        if not texto_valido(forma, '|\n"', sin_espacios_sobrantes=True):
            errores.append(f"lexico-basico.csv línea {n}: forma vacía, con espacios sobrantes o con '|', comillas o saltos de línea")
        if idioma in SIN_LETRAS_LATINAS and re.search(r"[A-Za-z]", forma):
            errores.append(f"lexico-basico.csv línea {n}: letras latinas en {idioma}: {forma!r}")
        if tipo == "flexion" and rasgos not in RASGOS_FLEXION:
            errores.append(f"lexico-basico.csv línea {n}: rasgos de flexión no válidos: {rasgos!r}")
        if tipo == "lema" and rasgos not in ("", ORIGEN_IA):
            errores.append(f"lexico-basico.csv línea {n}: un lema no lleva rasgos (solo {ORIGEN_IA!r} si es una propuesta sin revisar)")
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


def validar_colisiones(revisadas, anotada, errores):
    """Un idioma que llama igual a dos colores de distinto hex tiene que estar en colisiones-revisadas.csv, y esa lista no puede
    guardar casos que ya no existen: así una colisión nueva (o una corregida) no pasa sin que alguien la vea."""
    actuales = {(codigo, nombre.casefold(), "|".join(colores))
                for codigo, casos in cobertura.colisiones_nombres(IDIOMAS, anotada).items() for nombre, colores in casos
                if nombre.strip()}  # un nombre vacío ya lo marca validar_anotada
    listadas = {(f["idioma"], f["nombre"].casefold(), f["colores"]) for f in revisadas}
    if len(listadas) != len(revisadas):
        errores.append("colisiones-revisadas.csv: hay filas repetidas")
    for codigo, nombre, colores in sorted(actuales - listadas):
        errores.append(f"colisión nueva sin revisar: en {codigo}, «{nombre}» nombra a {colores.replace('|', ', ')} (añádela a colisiones-revisadas.csv si es correcta)")
    for codigo, nombre, colores in sorted(listadas - actuales):
        errores.append(f"colisiones-revisadas.csv: ya no existe el caso {codigo} «{nombre}» ({colores.replace('|', ', ')}); quítalo")


def validar_alias(anotada, traducidos, errores, alias=None):
    """Dos colores con el mismo hex (aqua y cyan, gray y grey) dicen lo mismo: los dos significados, en español y en cada idioma."""
    significados = {(f["idioma"], f["nombre_ingles"]): (f["significado_psicologico"], f["significado_mistico"]) for f in traducidos}
    for nombre, otros in (alias_de(anotada) if alias is None else alias).items():
        for otro, _ in otros:
            if nombre >= otro:  # cada par una vez
                continue
            propio = next(f for f in anotada if f["nombre_ingles"] == nombre)
            ajeno = next(f for f in anotada if f["nombre_ingles"] == otro)
            if (propio["significado_psicologico"], propio["significado_mistico"]) != (ajeno["significado_psicologico"], ajeno["significado_mistico"]):
                errores.append(f"colores.csv: {nombre} y {otro} tienen el mismo hex y deben tener los mismos significados en español")
            distintos = sorted(i for (i, n), par in significados.items() if n == nombre and par != significados.get((i, otro)))
            if distintos:
                errores.append(f"significados-idiomas.csv: {nombre} y {otro} tienen el mismo hex y deben tener los mismos significados; difieren en {', '.join(distintos)}")


def cercania_de(delta, mismo_hex):
    if mismo_hex:
        return "exacto"
    for banda, tope in BANDAS_CERCANIA:
        if delta <= tope:
            return banda
    return "baja"


# CIEDE2000 nunca es menor que |ΔL| / S_L, y S_L = 1 + 0,015 (L̄-50)² / raíz(20 + (L̄-50)²) vale a lo sumo 1,7471 para L entre 0
# y 100; con 1,75 el tope es seguro. Sirve para no calcular la distancia a los colores CSS que ya no pueden ganar.
COTA_SL = 1.75


def color_css_mas_cercano(lab, referencias):
    """(distancia redondeada a 9 decimales, nombre, hex) del color CSS más cercano a `lab`.

    `referencias` va ordenada por luminosidad: [(L, nombre, hex, lab)]. Se recorre desde la luminosidad más parecida y se
    para cuando |ΔL| / COTA_SL ya supera la mejor distancia: el resultado es el de calcular las 148, sin ese trabajo.
    """
    luminosidades = [r[0] for r in referencias]
    abajo = bisect.bisect_left(luminosidades, lab[0]) - 1
    arriba = abajo + 1
    mejor = None
    while abajo >= 0 or arriba < len(referencias):
        paso_abajo = abajo >= 0 and (arriba >= len(referencias) or lab[0] - luminosidades[abajo] <= luminosidades[arriba] - lab[0])
        indice = abajo if paso_abajo else arriba
        if mejor is not None and abs(luminosidades[indice] - lab[0]) / COTA_SL > mejor[0] + 1e-6:
            break  # esta y todas las demás están más lejos en luminosidad: ninguna puede ganar ni empatar
        if paso_abajo:
            abajo -= 1
        else:
            arriba += 1
        _, nombre, hex_, lab_ref = referencias[indice]
        candidato = (round(colorimetria.ciede2000(lab, lab_ref), 9), nombre, hex_)
        if mejor is None or candidato < mejor:
            mejor = candidato
    return mejor


def calcular_cercanias(completa, anotada):
    """Para cada nombre de la lista completa: color CSS más cercano (CIEDE2000), distancia y banda.

    Si dos colores CSS están igual de cerca (por ejemplo gray y grey, que comparten hex), gana el
    primero por orden alfabético. La comparación redondea a 9 decimales para que el desempate no
    dependa del ruido de coma flotante entre plataformas.
    """
    referencias = sorted((colorimetria.hex_a_lab(f["hex"])[0], f["nombre_ingles"], f["hex"], colorimetria.hex_a_lab(f["hex"]))
                         for f in anotada)
    resultado = []
    for fila in completa:
        distancia, nombre, color = color_css_mas_cercano(colorimetria.hex_a_lab(fila["hex"]), referencias)
        resultado.append((nombre, distancia, cercania_de(distancia, color == fila["hex"])))
    return resultado


def texto_completa(completa, cercanias):
    salida = io.StringIO()
    escritor = csv.writer(salida, lineterminator="\n")
    escritor.writerow(COLUMNAS_COMPLETA)
    for fila, (css, distancia, cercania) in zip(completa, cercanias):
        escritor.writerow([fila["nombre_ingles"], fila["hex"], fila["curada"], css, f"{distancia:.2f}", cercania])
    return salida.getvalue()


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


def comprobar_o_escribir(base, archivos, comprobar, sobrantes=()):
    """Compara contra el disco o escribe los archivos generados (ruta, contenido) de este script.

    Con comprobar=True no escribe nada: devuelve los nombres (relativos a `base`) que no coinciden
    con lo que hay en disco o que faltan, más los de `sobrantes` con el sufijo " (sobra)"; vacía si
    todo está al día. Con comprobar=False escribe cada archivo (creando sus carpetas) y borra los
    sobrantes, y devuelve [].
    """
    archivos = list(archivos)
    if comprobar:
        desfasados = [ruta.relative_to(base).as_posix() for ruta, contenido in archivos
                      if not ruta.exists() or ruta.read_text(encoding="utf-8") != contenido]
        desfasados += [f"{ruta.relative_to(base).as_posix()} (sobra)" for ruta in sobrantes]
        return desfasados
    for ruta, contenido in archivos:
        ruta.parent.mkdir(parents=True, exist_ok=True)
        ruta.write_text(contenido, encoding="utf-8")
    for ruta in sobrantes:
        ruta.unlink()
    return []


def construir_datos(anotada, completa, traducidos, lexico, referencias, evidencia_filas, culturales, gramaticales, alias, cercanias):
    """El diccionario `d` que reciben exportes.py, revision.py y los informes, a partir de los CSV ya leídos y validados.

    Un solo lugar: pruebas.py lo usa también, para no armar una copia que se desfase de la de main().
    """
    return {
        "idiomas": IDIOMAS, "anotada": anotada, "completa": completa, "cercanias": cercanias,
        "significados": {(f["idioma"], f["nombre_ingles"]): (f["significado_psicologico"], f["significado_mistico"])
                         for f in traducidos},
        "lexico": lexico, "referencias": referencias, "evidencia": {f["nombre_ingles"]: f for f in evidencia_filas},
        "culturales": culturales, "categorias": gramaticales, "alias": alias,
        "hex_concepto": {c: hex_de_concepto(c, anotada, completa) for c in CONCEPTOS},
        "hex_cotidiano": {c: hex_cotidiano_de_concepto(c, completa, anotada) for c in CONCEPTOS},
    }


def ruta_de(bandera, que):
    """La ruta que sigue a `bandera` en la línea de comandos, o None si no se pasó la bandera."""
    if bandera not in sys.argv:
        return None
    indice = sys.argv.index(bandera)
    if indice + 1 >= len(sys.argv):
        sys.exit(f"{bandera} necesita la ruta {que}: python3 generar-tabla.py {bandera} ruta")
    return sys.argv[indice + 1]


def leer_todos():
    """Lee los diez CSV. Si varios están mal (cabecera inesperada, fila con campos de más o de menos), los cuenta todos."""
    fallos, leidos = [], {}
    pedidos = (("anotada", leer, (ANOTADA, COLUMNAS_ANOTADA)), ("completa", leer_completa, ()),
               ("traducidos", leer, (SIGNIFICADOS, COLUMNAS_SIGNIFICADOS)), ("lexico", leer, (LEXICO, COLUMNAS_LEXICO)),
               ("referencias", leer, (REFERENCIAS, COLUMNAS_REFERENCIAS)), ("evidencia", leer, (EVIDENCIA, COLUMNAS_EVIDENCIA)),
               ("culturales", leer, (CULTURALES, COLUMNAS_CULTURALES)), ("gramaticales", leer, (CATEGORIAS, categorias.COLUMNAS)),
               ("revisiones", leer, (REVISIONES, revision.COLUMNAS)),
               ("colisiones_revisadas", leer, (COLISIONES_REVISADAS, COLUMNAS_COLISIONES)))
    for nombre, lector, argumentos in pedidos:
        try:
            leidos[nombre] = lector(*argumentos)
        except SystemExit as salida:
            fallos.append(str(salida.code))
    if fallos:
        sys.exit("No se pudieron leer los CSV:\n  " + "\n  ".join(fallos))
    return leidos


def main():
    leidos = leer_todos()
    anotada, completa, traducidos, lexico = leidos["anotada"], leidos["completa"], leidos["traducidos"], leidos["lexico"]
    referencias, evidencia_filas, culturales = leidos["referencias"], leidos["evidencia"], leidos["culturales"]
    gramaticales, revisiones = leidos["gramaticales"], leidos["revisiones"]
    fallos = colorimetria.autoprueba()
    if fallos:
        sys.exit(f"colorimetria.py: CIEDE2000 no coincide con {len(fallos)} vectores de Sharma et al.: {fallos[:2]}")
    errores = []
    validar_anotada(anotada, errores)
    validar_completa(completa, errores)
    validar_significados(traducidos, anotada, errores)
    alias = alias_de(anotada)  # una vez: lo usan la validación, los datos y las 30 páginas por idioma
    validar_alias(anotada, traducidos, errores, alias)
    validar_colisiones(leidos["colisiones_revisadas"], anotada, errores)
    validar_lexico(lexico, anotada, completa, errores)
    ids = validar_referencias(referencias, errores)
    usadas = set()
    validar_evidencia(evidencia_filas, anotada, ids, errores, usadas)
    validar_culturales(culturales, anotada, ids, errores, usadas)
    for i in sorted(ids - usadas):
        errores.append(f"referencias.csv: la referencia {i!r} no la usa ningún otro CSV")
    categorias.validar(gramaticales, anotada, lexico, [c for c, _ in IDIOMAS], CONCEPTOS, errores)
    revisable = revision.universo({"idiomas": IDIOMAS, "anotada": anotada, "lexico": lexico, "categorias": gramaticales})
    revision.validar(revisiones, [c for c, _ in IDIOMAS], errores)
    version = None
    if CHANGELOG.exists():
        version = manifiesto.leer_changelog(CHANGELOG.read_text(encoding="utf-8"), errores)
    else:
        errores.append("CHANGELOG.md: no existe")
    if errores:
        sobran = f"\n  ... y {len(errores) - 30} errores más" if len(errores) > 30 else ""
        sys.exit("Errores en los CSV:\n  " + "\n  ".join(errores[:30]) + sobran)

    texto = README.read_text(encoding="utf-8")
    # Cada marcador debe ocupar una línea entera: así no coincide con menciones en la prosa.
    patron = re.compile(r"^" + re.escape(INICIO) + r"$.*?^" + re.escape(FIN) + r"$", re.S | re.M)
    if not patron.search(texto):
        sys.exit(f"README.md: faltan los marcadores {INICIO} y {FIN}")

    cercanias = calcular_cercanias(completa, anotada)
    datos = construir_datos(anotada, completa, traducidos, lexico, referencias, evidencia_filas, culturales, gramaticales, alias, cercanias)
    significados, evidencia = datos["significados"], datos["evidencia"]
    # Lo que se construye a petición no se guarda en el repositorio: se deriva sin pérdida de los CSV.
    if ruta := ruta_de("--sqlite", "de la base"):
        conexion = exportes.construir_sqlite(ruta, datos)
        tablas = [fila[0] for fila in conexion.execute("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name")]
        conexion.close()
        print(f"Base SQLite en {ruta} con las tablas: {', '.join(tablas)}.")
        return 0
    if ruta := ruta_de("--conllu", "del archivo CoNLL-U"):
        Path(ruta).write_text(exportes.ejemplos_conllu(datos), encoding="utf-8")
        print(f"{len(datos['categorias'])} frases de ejemplo en CoNLL-U en {ruta}.")
        return 0
    if ruta := ruta_de("--visor", "del archivo HTML"):
        Path(ruta).write_text(revision.visor_html(datos, VISOR_PLANTILLA.read_text(encoding="utf-8")), encoding="utf-8")
        print(f"Visor de revisión en {ruta}: ábrelo en un navegador.")
        return 0
    completa_texto = texto_completa(completa, cercanias)
    huellas_actuales = revision.huellas(datos)
    filas_gazetteer = exportes.filas_gazetteer(datos)  # ordenarlas cuesta: una vez para el TSV, los patrones y el informe
    jsonl, tsv = exportes.colores_jsonl(datos), exportes.gazetteer_tsv(datos, filas_gazetteer)
    patrones, lexico_ud = exportes.spacy_patrones(datos, filas_gazetteer), exportes.lexico_ud(datos)
    # Las entradas del manifiesto, en el orden del README. nombres-completos.csv va con el texto que genera este programa.
    entradas = [(ruta.name, filas, ruta.read_text(encoding="utf-8"), None) for ruta, filas in (
        (ANOTADA, len(anotada)), (SIGNIFICADOS, len(traducidos)), (LEXICO, len(lexico)), (EVIDENCIA, len(evidencia_filas)),
        (CULTURALES, len(culturales)), (CATEGORIAS, len(gramaticales)), (REFERENCIAS, len(referencias)))]
    entradas.append((COMPLETA.name, len(completa), completa_texto, None))
    exportes_manifiesto = [("exportes/colores.jsonl", jsonl.count("\n"), jsonl, None),
                           ("exportes/gazetteer.tsv", tsv.count("\n") - 1, tsv, exportes.COLUMNAS_GAZETTEER)]
    exportes_manifiesto += [(f"exportes/spacy/{codigo}.jsonl", texto.count("\n"), texto, None) for codigo, texto in patrones.items()]
    exportes_manifiesto += [("exportes/lexico-ud.tsv", lexico_ud.count("\n") - 1, lexico_ud, exportes.COLUMNAS_LEXICO_UD)]
    generados = {
        README: patron.sub(lambda _: f"{INICIO}\n{tabla_readme(anotada, evidencia)}\n{FIN}", texto),
        INDICE: indice_idiomas(anotada),
        REFS_MD: referencias_md(referencias),
        AMBIGUEDAD: ambiguedad.informe(filas_gazetteer, IDIOMAS),
        AUDITORIA: auditoria.informe(completa),
        CATEGORIAS_MD: categorias.informe(gramaticales, IDIOMAS),
        COBERTURA: cobertura.informe(IDIOMAS, anotada, lexico, gramaticales, CONCEPTOS, datos["hex_concepto"]),
        REVISIONES_MD: revision.informe(revisiones, revisable, IDIOMAS, huellas_actuales),
        COMPLETA: completa_texto,
        DIR_EXPORTES / "colores.jsonl": jsonl,
        DIR_EXPORTES / "gazetteer.tsv": tsv,
        DIR_EXPORTES / "lexico-ud.tsv": lexico_ud,
        DIR_EXPORTES / "manifiesto.json": manifiesto.manifiesto(version, [c for c, _ in IDIOMAS], entradas, exportes_manifiesto),
    }
    for codigo, texto in patrones.items():
        generados[DIR_EXPORTES / "spacy" / f"{codigo}.jsonl"] = texto
    for codigo, etiqueta in IDIOMAS:
        generados[DIR_IDIOMAS / f"{codigo}.md"] = pagina_idioma(
            codigo, etiqueta, anotada, significados, lexico, completa, evidencia, culturales, alias)
    sobrantes = sorted(p for carpeta in (DIR_IDIOMAS, DIR_EXPORTES) if carpeta.exists()
                       for p in carpeta.rglob("*") if p.is_file() and p not in generados)

    resumen = (f"{len(anotada)} colores anotados en {len(IDIOMAS)} idiomas, "
               f"{len(traducidos)} significados traducidos, {len(lexico)} formas de léxico básico, "
               f"{len(culturales)} matices culturales, {len(referencias)} referencias y {len(completa)} nombres completos")
    comprobar = "--comprobar" in sys.argv
    if comprobar:
        fallos_sqlite = exportes.comprobar_sqlite(datos)
        if fallos_sqlite:
            print("La base SQLite no coincide con los CSV: " + "; ".join(fallos_sqlite), file=sys.stderr)
            return 1
    atencion_revisiones, _, _ = revision.situacion(revisiones, huellas_actuales)
    if revision.aviso(atencion_revisiones):  # solo avisa: una revisión huérfana no rompe la generación, pero tampoco debe pasar callada
        print(revision.aviso(atencion_revisiones), file=sys.stderr)
    desfasados = comprobar_o_escribir(CARPETA, generados.items(), comprobar, sobrantes)
    if comprobar:
        if desfasados:
            print(f"No coinciden con los CSV: {', '.join(desfasados)}. "
                  "Ejecuta python3 generar-tabla.py", file=sys.stderr)
            return 1
        print(f"OK: {resumen}; archivos generados al día.")
        return 0

    print(f"Generados README.md, nombres-completos.csv, colores-por-idioma.md y {len(IDIOMAS)} páginas en por-idioma/: {resumen}.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
