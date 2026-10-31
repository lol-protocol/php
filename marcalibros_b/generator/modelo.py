"""Modelo de datos del lector: diseño del marcalibros y marca guardada en un texto.

El diseño (catálogo o receta de usuario) y la marca (id de texto, rango de caracteres,
etiqueta, id del diseño) son documentos separados; la marca solo referencia al diseño por id.
Los esquemas están en datos/*.schema.json.

    from modelo import validar_diseno, validar_marca, sugerencias_por_gutenberg
"""
import json
import re
import unicodedata
from functools import lru_cache
from pathlib import Path

from jsonschema import Draft202012Validator, FormatChecker

RAIZ = Path(__file__).resolve().parent.parent
DATOS = RAIZ / "datos"


@lru_cache(maxsize=None)
def _validador(nombre):
    esquema = json.loads((DATOS / f"{nombre}.schema.json").read_text(encoding="utf-8"))
    return Draft202012Validator(esquema, format_checker=FormatChecker())


def _errores(nombre, doc):
    return [f"{'/'.join(map(str, e.absolute_path)) or '(raíz)'}: {e.message}"
            for e in sorted(_validador(nombre).iter_errors(doc), key=lambda e: list(map(str, e.absolute_path)))]


def validar_diseno(doc, piezas=None):
    """Errores del diseño. Con `piezas` (ids del manifest) también comprueba que las
    referencias a piezas existan y que los recortes de imagen sean 1:7."""
    errores = _errores("diseno", doc)
    if errores:
        return errores
    if doc["origen"] == "catalogo" and doc["id"] != f"cat:{doc['pieza']}":
        errores.append("el id no coincide con la pieza")
    if piezas is not None:
        refs = [doc["pieza"]] if doc["origen"] == "catalogo" else []
        fondo = doc.get("receta", {}).get("fondo", {})
        if fondo.get("tipo") == "plantilla":
            refs.append(fondo["pieza"])
        errores += [f"pieza inexistente: {r}" for r in refs if r not in piezas]
    rec = doc.get("receta", {}).get("fondo", {}).get("recorte")
    if rec and abs(rec["alto"] - 7 * rec["ancho"]) > 1:
        errores.append(f"el recorte no es 1:7 ({rec['ancho']}×{rec['alto']})")
    return errores


def validar_marca(doc):
    errores = _errores("marca", doc)
    if not errores and doc["posicion"]["fin"] < doc["posicion"]["inicio"]:
        errores.append("posicion: fin anterior a inicio")
    return errores


def sugerencias_por_gutenberg(manifiesto):
    """Id de Gutenberg → ids de diseño de catálogo sugeribles al abrir ese texto.
    Solo cuenta lo `verificado`: un id sin comprobar no debe mover al lector."""
    out = {}
    for e in manifiesto["entradas"]:
        if e["gutenberg_estado"] == "verificado" and e["gutenberg_id"]:
            out.setdefault(str(e["gutenberg_id"]), []).append(f"cat:{e['id']}")
    return out


# --- Sugerencias a partir de los metadatos del texto abierto (sin ids verificados) -------------
# Gutendex (y el propio texto de Gutenberg) dan autor, años y título de cada libro. Con eso se puede
# reconocer al autor del catálogo en el momento de abrir el texto, sin tener que verificar antes
# los ids de Gutenberg de 1000 obras: basta el nombre y los años de vida.

def _tokens(nombre):
    """Palabras significativas de un nombre, sin tildes ni mayúsculas. «Apellido, Nombre» se ordena igual."""
    t = unicodedata.normalize("NFKD", nombre.lower())
    t = "".join(c for c in t if not unicodedata.combining(c))
    return [w for w in re.findall(r"[a-z]+", t) if w not in {"de", "del", "la", "los", "las", "y", "van", "von", "da", "di"}]


def _mismo_token(a, b):
    return a == b or (min(len(a), len(b)) >= 4 and (a.startswith(b) or b.startswith(a)))  # Homer / Homero


def _anios(epoca):
    """(nacimiento, muerte) como enteros, con años a. C. negativos; None si no se puede leer."""
    if not epoca or "tradición" in str(epoca):
        return None
    ac = "a. C." in str(epoca)
    nums = [int(n) for n in re.findall(r"\d{1,4}", str(epoca))]
    if len(nums) != 2:
        return None
    a, b = nums
    if ac:
        return (-a, -b if "d. C." not in str(epoca) else b)
    return (a, b)


def _mismo_autor(nombre_cat, epoca_cat, nombre_libro, nac, mue):
    a, b = _tokens(nombre_cat), _tokens(nombre_libro)
    if not a or not b:
        return False
    corto, largo = (a, b) if len(a) <= len(b) else (b, a)
    comunes = sum(1 for t in corto if any(_mismo_token(t, u) for u in largo))
    if comunes < len(corto):
        return False
    anios = _anios(epoca_cat)
    if anios and nac is not None and mue is not None:
        return abs(anios[0] - nac) <= 1 and abs(anios[1] - mue) <= 1
    return comunes >= 2  # sin años que comparar, un solo apellido no basta


def sugerencias_por_metadatos(libro, config):
    """Piezas del catálogo sugeribles para un libro de Gutenberg, a partir de sus metadatos
    (`{"title": ..., "authors": [{"name": "Bécquer, Gustavo Adolfo", "birth_year": 1836, "death_year": 1870}]}`,
    como lo da Gutendex). Devuelve [{"diseno": "cat:<pieza>", "nivel": "obra"|"autor"}]: primero las piezas
    cuya obra o tema se parece al título del libro, luego las demás del mismo autor, en orden de variante."""
    titulo = set(_tokens(libro.get("title", "")))
    salida = []
    for mov in config["movimientos"]:
        for autor in mov["autores"]:
            if not any(_mismo_autor(autor["nombre"], autor.get("epoca"), a.get("name", ""), a.get("birth_year"), a.get("death_year"))
                       for a in libro.get("authors", [])):
                continue
            propias = []
            for v, obra in enumerate(autor.get("obras", []), 1):
                ref = set(_tokens(obra.get("de") or obra["titulo"]))
                coincide = len(ref & titulo) >= min(2, len(ref)) and bool(ref & titulo) and len(ref & titulo) / len(ref) >= 0.5
                propias.append({"diseno": f"cat:{mov['id']}-{autor['id']}-{v:02d}", "nivel": "obra" if coincide else "autor"})
            salida += sorted(propias, key=lambda x: x["nivel"] != "obra")
    return salida
