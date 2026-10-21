"""Forma normalizada de un nombre de color, para buscarlo en texto ruidoso (mayúsculas, sin tildes, guiones).

Solo biblioteca estándar. La usan exportes.py (columna forma_normalizada del gazetteer y de la base SQLite)
y generar-tabla.py (informes de ambigüedad y de auditoría).

Es una aproximación para casar texto, no una equivalencia lingüística: «azul-marino», «Azul marino» y
«azul   marino» dan la misma forma, y «Grün» y «grun» también, pero dos palabras distintas pueden acabar iguales.

Qué se hace con cada forma:
- NFKC y minúsculas (casefold: «ß» pasa a «ss» y la sigma final del griego a «σ»).
- Todas las variantes de apóstrofo pasan a «'» y se conserva (en ucraniano es parte de la palabra).
- Espacios, guiones, rayas, el signo menos y el guion bajo se unifican en un solo espacio.
- Se quitan el guion blando, el espacio de ancho cero y el BOM. Se conservan el ZWNJ y el ZWJ: en persa
  forman parte de la ortografía.
- Solo en escritura latina y griega se quitan las marcas combinantes (tildes, diéresis, cedillas…) y se pliegan
  a ASCII las letras que no se descomponen (ł, đ, ı, ø, æ, œ). En el resto de escrituras no se quita nada: allí las
  marcas son parte de la letra (la dakuten del japonés, la «й» del ruso y del ucraniano, las vocales del tailandés,
  el hindi y el bengalí), y quitarlas daría otra palabra.
"""
import re
import unicodedata

# Idiomas de escritura latina. generar-tabla.py tiene la lista complementaria (SIN_LETRAS_LATINAS) y una prueba
# comprueba que entre las dos cubren los 30 idiomas sin solaparse.
LATINOS = {"en", "es", "fr", "pt", "it", "de", "nl", "sv", "da", "fi", "pl", "cs", "ro", "hu", "tr", "vi", "id", "sw"}
# Idiomas en los que se quitan las marcas combinantes: en griego solo son acentos (tonos y diéresis).
QUITAR_MARCAS = LATINOS | {"el"}

_APOSTROFOS = str.maketrans({c: "'" for c in "\u2018\u2019\u02bc\u2032`\u00b4\u05f3"})
_INVISIBLES = re.compile("[\u00ad\u200b\ufeff]")
_SEPARADORES = re.compile(r"[\s\u2010-\u2015\u2212\-_]+")
_PLEGADO_LATINO = str.maketrans({"ł": "l", "đ": "d", "ı": "i", "ø": "o", "æ": "ae", "œ": "oe"})


def normalizar(forma, idioma):
    """La forma normalizada de `forma`, escrita en el idioma de código `idioma` (ver el módulo)."""
    texto = unicodedata.normalize("NFKC", unicodedata.normalize("NFKC", forma).casefold())
    texto = _INVISIBLES.sub("", texto.translate(_APOSTROFOS))
    if idioma in QUITAR_MARCAS:
        texto = unicodedata.normalize("NFD", texto)
        texto = "".join(c for c in texto if unicodedata.category(c) != "Mn")
        if idioma in LATINOS:
            texto = texto.translate(_PLEGADO_LATINO)
        texto = unicodedata.normalize("NFC", texto)
    return _SEPARADORES.sub(" ", texto).strip()
