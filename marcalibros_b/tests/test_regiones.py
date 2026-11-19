"""Región y lengua como eje: cada autor tiene zona y lengua, y el manifest y el editor las llevan."""
import json
import sys
from pathlib import Path

import yaml

RAIZ = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(RAIZ / "generator"))

import editor_datos  # noqa: E402
import regiones  # noqa: E402

CONFIG = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
REG = regiones.cargar_regiones()
AUTORES = [(m["id"], a) for m in CONFIG["movimientos"] for a in m["autores"]]


def test_todo_autor_tiene_zona_y_lengua():
    for _, a in AUTORES:
        assert regiones.zona_de_autor(a) in {z["id"] for z in REG["zonas"]}
        assert regiones.lengua_de_autor(a) in REG["lenguas"]


def test_una_region_esta_en_una_sola_zona_y_no_sobra_nada():
    todas = [r for z in REG["zonas"] for r in z["regiones"]]
    assert len(todas) == len(set(todas))
    usadas = {a["region"] for _, a in AUTORES}
    assert usadas <= set(todas) and set(todas) <= usadas, "regiones.yaml lista una región que ningún autor usa (o le falta una)"
    assert set(REG["lengua_por_region"]) == usadas
    assert {a["id"] for _, a in AUTORES} >= set(REG["lengua_por_autor"])


def test_las_excepciones_de_lengua():
    por_id = {a["id"]: a for _, a in AUTORES}
    assert regiones.lengua_de_autor(por_id["tagore"]) == "bn" and regiones.lengua_de_autor(por_id["rushdie"]) == "en"
    assert regiones.lengua_de_autor(por_id["kafka"]) == "de" and regiones.lengua_de_autor(por_id["rousseau"]) == "fr"


def test_una_region_desconocida_se_rechaza():
    import pytest
    with pytest.raises(ValueError):
        regiones.zona_de_autor({"id": "x", "region": "Atlántida"})
    with pytest.raises(ValueError):
        regiones.lengua_de_autor({"id": "x", "region": "Atlántida"})


def test_el_manifest_lleva_zona_y_lengua():
    entradas = json.loads((RAIZ / "catalog" / "manifest.json").read_text(encoding="utf-8"))["entradas"]
    por_autor = {(m, a["id"]): a for m, a in AUTORES}
    for e in entradas:
        a = por_autor[(e["movimiento"], e["autor_id"])]
        assert e["zona"] == regiones.zona_de_autor(a) and e["lengua"] == regiones.lengua_de_autor(a), e["id"]


def test_el_editor_ofrece_los_filtros_de_region_y_lengua():
    cat = editor_datos.catalogo()
    assert cat["zonas"] and cat["lenguas"]
    for a in cat["autores"]:
        assert 0 <= a["z"] < len(cat["zonas"]) and 0 <= a["l"] < len(cat["lenguas"])
    html = (RAIZ / "editor" / "index.html").read_text(encoding="utf-8")
    assert 'id="f-zona"' in html and 'id="f-len"' in html
