"""Constantes de Listado-colores: los idiomas, las columnas de cada CSV y los vocabularios cerrados que validan los datos.

Las rutas de los archivos siguen en generar-tabla.py (las pruebas las reemplazan ahí).
"""
import re

import cobertura



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
