"""Lo que comparten los scripts de mortalidad-historica: constantes, lectura de CSV y normalización.

Solo biblioteca estándar. Los CSV se leen con newline="" para conservarlos tal cual.
"""
import csv
import re
import unicodedata
from pathlib import Path

RAIZ = Path(__file__).resolve().parent
DATOS = RAIZ / "datos"
POR_IDIOMA = RAIZ / "por-idioma"

# Los 20 idiomas del glosario de causas históricas, con su código ISO 639-3, su nombre en español, su
# nombre en el propio idioma y si se escribe de derecha a izquierda. Para cambiar la lista, se edita
# solo esto: el validador, las páginas de por-idioma/ y las pruebas la leen de aquí.
IDIOMAS = {
    "spa": ("español", "español", False),
    "eng": ("inglés", "English", False),
    "por": ("portugués", "português", False),
    "fra": ("francés", "français", False),
    "ita": ("italiano", "italiano", False),
    "deu": ("alemán", "Deutsch", False),
    "lat": ("latín", "lingua latina", False),
    "nld": ("neerlandés", "Nederlands", False),
    "swe": ("sueco", "svenska", False),
    "dan": ("danés", "dansk", False),
    "pol": ("polaco", "polski", False),
    "rus": ("ruso", "русский", False),
    "ukr": ("ucraniano", "українська", False),
    "ces": ("checo", "čeština", False),
    "ell": ("griego", "Ελληνικά", False),
    "tur": ("turco", "Türkçe", False),
    "ara": ("árabe", "العربية", True),
    "heb": ("hebreo", "עברית", True),
    "zho": ("chino", "中文", False),
    "jpn": ("japonés", "日本語", False),
}

# Las mismas cinco regiones que Listado-paises/paises.csv (columna region). No se leen de ahí a propósito:
# este proyecto no depende de otra carpeta. Los códigos de país son los ISO alfa-2 de esa misma tabla.
REGIONES = ("África", "América", "Asia", "Europa", "Oceanía")

CONFIANZAS = ("alta", "media", "baja")
ESTADOS = ("borrador", "verificado")
GRUPOS_CAUSA = (
    "infecciosa", "cardiovascular", "respiratoria", "digestiva", "perinatal_infantil", "neurologica",
    "cancer", "externa", "materna", "desnutricion", "mental", "otra", "mal_definida",
)
HERENCIAS = (
    "autosómica recesiva", "autosómica dominante", "recesiva ligada al X", "dominante ligada al X",
    "mitocondrial", "multifactorial",
)
INICIOS = ("infancia", "adultez", "variable")
SEXOS = ("M", "F", "T")
CALIDADES = ("A", "B", "C")  # A: registro civil completo; B: reconstrucción parcial; C: estimación

RE_ID = re.compile(r"^[a-z0-9_]+$")
RE_CIE10 = re.compile(r"^[A-Z][0-9]{2}(\.[0-9])?$")
RE_PAIS = re.compile(r"^[A-Z]{2}$")
RE_MONDO = re.compile(r"^MONDO:[0-9]{7}$")


RESULTADOS_REVISION = ("correcto", "corregir", "dudoso", "corregido")
RE_FECHA = re.compile(r"^[0-9]{4}-[0-9]{2}-[0-9]{2}$")


def huella(termino, idioma, causa_id, confianza):
    """Huella de un término del glosario (FNV-1a de 32 bits, en hexadecimal): cambia si cambia el término, su
    idioma, su causa o su confianza. Sirve para que una revisión caduque cuando el dato cambia; no es criptográfica.
    La misma función está en js/mortalidad.js (la vista genera las filas de revisiones.csv con ella)."""
    h = 0x811C9DC5
    for byte in f"{termino}|{idioma}|{causa_id}|{confianza}".encode("utf-8"):
        h = ((h ^ byte) * 0x01000193) & 0xFFFFFFFF
    return f"{h:08x}"


def leer_csv(ruta):
    """(columnas, filas como dict) de un CSV."""
    with Path(ruta).open(encoding="utf-8", newline="") as f:
        lector = csv.DictReader(f)
        return lector.fieldnames, list(lector)


def filas_csv(nombre):
    """Las filas de datos/<nombre>.csv, como dict."""
    return leer_csv(DATOS / f"{nombre}.csv")[1]


# Escritura en que se escribe cada idioma del glosario: prefijos del nombre Unicode de sus letras. Una comprobación
# mecánica (no sustituye a una persona que hable el idioma): un término ruso escrito con letras latinas, o uno
# japonés en cirílico, es un error de etiquetado.
ESCRITURAS = {
    **{c: ("LATIN",) for c in ("spa", "eng", "por", "fra", "ita", "deu", "lat", "nld", "swe", "dan", "pol", "ces", "tur")},
    "rus": ("CYRILLIC",), "ukr": ("CYRILLIC",), "ell": ("GREEK",), "ara": ("ARABIC",), "heb": ("HEBREW",),
    "zho": ("CJK",), "jpn": ("CJK", "HIRAGANA", "KATAKANA"),
}
_SIGNOS_PERMITIDOS = " -'’.·"


def escritura_coherente(termino, idioma):
    """True si todas las letras de `termino` son de la escritura de `idioma` (los espacios, guiones y apóstrofos valen)."""
    escrituras = ESCRITURAS[idioma]
    for caracter in termino:
        if caracter in _SIGNOS_PERMITIDOS or unicodedata.category(caracter).startswith("M"):
            continue
        if not unicodedata.name(caracter, "").startswith(escrituras):
            return False
    return True


def normalizar(texto):
    """Forma para comparar términos: sin tildes ni diacríticos, en minúsculas y con espacios simples.

    Iguala «Schlagfluß» con «Schlagfluss» (casefold convierte la ß en ss), «apoplejía» con «apoplejia» y el
    árabe con y sin vocales cortas.
    """
    s = unicodedata.normalize("NFD", texto.casefold())
    s = "".join(c for c in s if unicodedata.category(c) != "Mn")
    return " ".join(unicodedata.normalize("NFC", s).split())
