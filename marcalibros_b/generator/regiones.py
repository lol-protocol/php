"""Región y lengua de un autor (catalog/regiones.yaml). Solo depende de PyYAML: lo usa build.py para el manifest."""
from functools import lru_cache
from pathlib import Path

import yaml

RUTA = Path(__file__).resolve().parent.parent / "catalog" / "regiones.yaml"


@lru_cache(maxsize=1)
def cargar_regiones():
    return yaml.safe_load(RUTA.read_text(encoding="utf-8"))


def zona_de_autor(autor, regiones=None):
    """Id de la zona (Europa, América Latina…) de un autor, según su `region`. ValueError si la región no está listada."""
    regiones = regiones or cargar_regiones()
    for z in regiones["zonas"]:
        if autor["region"] in z["regiones"]:
            return z["id"]
    raise ValueError(f"región sin zona en regiones.yaml: {autor['region']!r} ({autor['id']})")


def lengua_de_autor(autor, regiones=None):
    """Código de la lengua en que escribe un autor: la de su región, salvo `lengua_por_autor`. ValueError si no hay."""
    regiones = regiones or cargar_regiones()
    lengua = regiones["lengua_por_autor"].get(autor["id"]) or regiones["lengua_por_region"].get(autor["region"])
    if lengua not in regiones["lenguas"]:
        raise ValueError(f"autor sin lengua en regiones.yaml: {autor['id']} ({autor['region']!r})")
    return lengua
