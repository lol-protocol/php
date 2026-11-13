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

import yaml
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

PALABRAS_VACIAS = {"de", "del", "la", "los", "las", "el", "y", "e", "van", "von", "da", "di", "le", "les", "l", "d", "du", "des",
                   "un", "une", "et", "the", "of", "a", "an", "or", "and", "der", "die", "das"}


def _tokens(nombre):
    """Palabras significativas, sin tildes ni mayúsculas. «Apellido, Nombre» queda igual que «Nombre Apellido»."""
    t = unicodedata.normalize("NFKD", nombre.lower())
    t = "".join(c for c in t if not unicodedata.combining(c))
    return [w for w in re.findall(r"[a-z]+", t) if w not in PALABRAS_VACIAS]


def _mismo_token(a, b):
    return a == b or (min(len(a), len(b)) >= 4 and (a.startswith(b) or b.startswith(a)))  # Homer / Homero


def anios_de_vida(epoca):
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


def reconoce_autor(nombre_cat, epoca_cat, nombre_libro, nac=None, mue=None):
    """¿El autor de un libro (nombre y años como los da Gutendex) es este autor del catálogo?
    Exige que cada palabra del nombre más corto aparezca en el otro (con grafías cercanas) y, si hay años
    a ambos lados, que coincidan con un año de margen. Sin años, un nombre de una sola palabra no basta."""
    a, b = _tokens(nombre_cat), _tokens(nombre_libro)
    if not a or not b:
        return False
    corto, largo = (a, b) if len(a) <= len(b) else (b, a)
    comunes = sum(1 for t in corto if any(_mismo_token(t, u) for u in largo))
    if comunes < len(corto):
        return False
    anios = anios_de_vida(epoca_cat)
    if anios and nac is not None and mue is not None:
        return abs(anios[0] - nac) <= 1 and abs(anios[1] - mue) <= 1
    return comunes >= 2


@lru_cache(maxsize=1)
def cargar_titulos_originales():
    """{id de autor: {id de obra: [títulos en el idioma original]}} (catalog/titulos-originales.yaml, opcional)."""
    ruta = RAIZ / "catalog" / "titulos-originales.yaml"
    if not ruta.exists():
        return {}
    return (yaml.safe_load(ruta.read_text(encoding="utf-8")) or {}).get("autores", {})


def _parecido(referencias, titulo):
    """¿Alguna referencia (título en español u original) está contenida, casi entera, en el título del libro?"""
    for ref in referencias:
        r = _tokens(ref)
        if not r:
            continue
        comunes = sum(1 for t in r if any(_mismo_token(t, u) for u in titulo))
        if comunes >= min(2, len(r)) and comunes / len(r) >= 0.6:
            return True
    return False


def autor_del_libro(libro, autor):
    """¿Alguno de los autores del libro (metadatos de Gutendex) es este autor del catálogo?"""
    return any(reconoce_autor(autor["nombre"], autor.get("epoca"), a.get("name", ""), a.get("birth_year"), a.get("death_year"))
               for a in libro.get("authors", []))


def obra_base(autor, de):
    """Índice (desde 0) de la obra de un autor de la que sale un tema «de»: la de título igual y, si no, la única obra
    sin `de` cuyo título contiene todas las palabras de `de` («Frankenstein» → «Frankenstein o el moderno Prometeo»).
    None si no hay una sola."""
    obras = autor.get("obras", [])
    exactas = [i for i, o in enumerate(obras) if o["titulo"] == de and not o.get("de")]
    if exactas:
        return exactas[0]
    palabras = set(_tokens(de))
    contienen = [i for i, o in enumerate(obras) if not o.get("de") and palabras and palabras <= set(_tokens(o["titulo"]))]
    return contienen[0] if len(contienen) == 1 else None


def obras_del_libro(libro, autor, titulos_originales=None):
    """Para cada obra del autor (en orden de variante), True si el título del libro se parece a su título en
    español, al original (catalog/titulos-originales.yaml) o, en un tema «de» otra obra, al de esa obra."""
    originales = cargar_titulos_originales() if titulos_originales is None else titulos_originales
    titulo = _tokens(libro.get("title", ""))
    propios = originales.get(autor["id"], {})
    salida = []
    for obra in autor.get("obras", []):
        refs = [obra["titulo"], *propios.get(obra["id"], [])]
        if obra.get("de"):  # tema sacado de otra obra: también cuenta el título de esa obra y sus títulos originales
            base = obra_base(autor, obra["de"])
            refs += [obra["de"]] + (propios.get(autor["obras"][base]["id"], []) if base is not None else [])
        salida.append(_parecido(refs, titulo))
    return salida


def sugerencias_por_metadatos(libro, config, titulos_originales=None):
    """Piezas del catálogo sugeribles para un libro de Gutenberg, a partir de sus metadatos
    (`{"title": ..., "authors": [{"name": "Bécquer, Gustavo Adolfo", "birth_year": 1836, "death_year": 1870}]}`,
    como lo da Gutendex). Devuelve [{"diseno": "cat:<pieza>", "nivel": "obra"|"autor"}]: primero las piezas
    cuya obra o tema se parece al título del libro (en español o en el idioma original, si está en
    catalog/titulos-originales.yaml), luego las demás del mismo autor, en orden de variante."""
    salida = []
    for mov in config["movimientos"]:
        for autor in mov["autores"]:
            if not autor_del_libro(libro, autor):
                continue
            propias = [{"diseno": f"cat:{mov['id']}-{autor['id']}-{v:02d}", "nivel": "obra" if coincide else "autor"}
                       for v, coincide in enumerate(obras_del_libro(libro, autor, titulos_originales), 1)]
            salida += sorted(propias, key=lambda x: x["nivel"] != "obra")
    return salida


# --- Géneros (catalog/generos.yaml) ------------------------------------------------------------

@lru_cache(maxsize=1)
def cargar_generos():
    return yaml.safe_load((RAIZ / "catalog" / "generos.yaml").read_text(encoding="utf-8"))


def genero_de_obra(obra, autor, generos=None):
    """Id del género de una obra del catálogo. Un tema («tipo: tema») hereda el de su obra base; si sale de una
    colección que no es una obra del catálogo, se usa `temas_de_colecciones`; un tema suelto, `temas_sueltos`. ValueError si el tipo no está listado."""
    g = generos or cargar_generos()
    por_tipo = {t: fam["id"] for fam in g["generos"] for t in fam["tipos"]}
    if obra["tipo"] != "tema":
        if obra["tipo"] not in por_tipo:
            raise ValueError(f"el tipo «{obra['tipo']}» de «{obra['titulo']}» no tiene género en catalog/generos.yaml")
        return por_tipo[obra["tipo"]]
    if obra["id"] in g.get("temas_sueltos", {}):
        return g["temas_sueltos"][obra["id"]]
    base = obra_base(autor, obra.get("de", ""))
    if base is not None:
        return genero_de_obra(autor["obras"][base], autor, g)
    if obra.get("de") in g["temas_de_colecciones"]:
        return g["temas_de_colecciones"][obra["de"]]
    raise ValueError(f"el tema «{obra['titulo']}» (de «{obra.get('de')}») no tiene obra base ni colección en catalog/generos.yaml")
