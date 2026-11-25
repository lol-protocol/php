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


import pytest  # noqa: E402

import build  # noqa: E402


@pytest.mark.lento
@pytest.mark.skipif(build.chromium() is None, reason="hace falta Chromium")
def test_los_filtros_de_region_y_lengua_acotan_la_lista_de_autores():
    from playwright.sync_api import sync_playwright

    cat = editor_datos.catalogo()
    nombre_zona = {z["nombre"]: i for i, z in enumerate(cat["zonas"])}
    nombre_len = {le["nombre"]: i for i, le in enumerate(cat["lenguas"])}

    def autores(page, **seleccion):
        for campo, valor in seleccion.items():
            page.evaluate("([id, v]) => { var s = document.getElementById(id); s.value = v; s.dispatchEvent(new Event('change')); }", [campo, valor])
        return page.evaluate("() => Array.from(document.querySelectorAll('#f-autor option')).slice(1).map(o => o.textContent)")

    with sync_playwright() as p:
        nav = p.chromium.launch(executable_path=build.chromium(), args=["--no-sandbox"])
        page = nav.new_page()
        page.goto((RAIZ / "editor" / "index.html").as_uri())
        todos = autores(page)
        assert len(todos) == len(cat["autores"])
        asia = autores(page, **{"f-zona": str(nombre_zona["Asia"])})
        esperado = sorted({a["nombre"] for a in cat["autores"] if a["z"] == nombre_zona["Asia"]})
        assert sorted(asia) == esperado and "Matsuo Bashō" in asia and "Charles Dickens" not in asia
        japones = autores(page, **{"f-zona": "", "f-len": str(nombre_len["Japonés"])})
        assert sorted(japones) == ["Haruki Murakami", "Matsuo Bashō", "Murasaki Shikibu"]
        persa = autores(page, **{"f-mov": "oriente", "f-len": str(nombre_len["Persa"])})
        assert sorted(persa) == ["Omar Khayyam", "Yalal ad-Din Rumi"]
        assert len(autores(page, **{"f-mov": "", "f-len": ""})) == len(todos)
        nav.close()
