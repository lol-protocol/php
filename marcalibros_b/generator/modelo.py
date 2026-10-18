"""Modelo de datos del lector: diseño del marcalibros y marca guardada en un texto.

El diseño (catálogo o receta de usuario) y la marca (id de texto, rango de caracteres,
etiqueta, id del diseño) son documentos separados; la marca solo referencia al diseño por id.
Los esquemas están en datos/*.schema.json.

    from modelo import validar_diseno, validar_marca, sugerencias_por_gutenberg
"""
import json
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
    if piezas is not None:
        refs = [doc["pieza"]] if doc["origen"] == "catalogo" else []
        fondo = doc.get("receta", {}).get("fondo", {})
        if fondo.get("tipo") == "plantilla":
            refs.append(fondo["pieza"])
        errores += [f"pieza inexistente: {r}" for r in refs if r not in piezas]
        if doc["origen"] == "catalogo" and doc["id"] != f"cat:{doc['pieza']}":
            errores.append("el id no coincide con la pieza")
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
