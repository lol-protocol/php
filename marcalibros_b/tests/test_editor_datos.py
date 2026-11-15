"""La página del editor lleva los datos del generador al día."""
import json
import re
import shutil
import subprocess
import sys
from pathlib import Path

import pytest

RAIZ = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(RAIZ / "generator"))

import editor_datos  # noqa: E402
import plantillas  # noqa: E402


def test_la_pagina_esta_al_dia():
    assert editor_datos.main(["--comprobar"]) == 0


def test_las_paletas_de_la_pagina_son_las_legibles_del_catalogo():
    pagina = editor_datos.PAGINA.read_text(encoding="utf-8")
    d = json.loads(re.search(re.escape(editor_datos.INICIO) + r"(.*?)" + re.escape(editor_datos.FIN), pagina, re.S).group(1))
    assert set(d["paletas"]) == set(editor_datos.PALETAS_EDITOR)
    for nombre, p in d["paletas"].items():
        legible = plantillas.paleta_legible(nombre)
        assert {k: p[c] for k, c in editor_datos.CLAVES.items()} == {k: legible[k] for k in editor_datos.CLAVES}
    assert d["franja"] == {"x0": 16, "y0": 440, "x1": 184, "y1": 860} and (d["ancho"], d["alto"]) == (200, 1400)


def test_un_bloque_desactualizado_se_detecta(tmp_path, monkeypatch):
    copia = tmp_path / "index.html"
    shutil.copy(editor_datos.PAGINA, copia)
    copia.write_text(copia.read_text(encoding="utf-8").replace('"minContraste": 4.5', '"minContraste": 3'), encoding="utf-8")
    monkeypatch.setattr(editor_datos, "PAGINA", copia)
    assert editor_datos.main(["--comprobar"]) == 1
    assert editor_datos.main([]) == 0 and editor_datos.main(["--comprobar"]) == 0


@pytest.mark.skipif(shutil.which("node") is None, reason="hace falta node")
def test_el_script_de_la_pagina_tiene_sintaxis_valida(tmp_path):
    js = re.search(r"<script>(.*)</script>", editor_datos.PAGINA.read_text(encoding="utf-8"), re.S).group(1)
    (tmp_path / "pagina.js").write_text(js, encoding="utf-8")
    r = subprocess.run(["node", "--check", str(tmp_path / "pagina.js")], capture_output=True, text=True)
    assert r.returncode == 0, r.stderr


def test_el_catalogo_de_la_pagina_son_las_piezas_del_manifest():
    import json
    cat = editor_datos.catalogo()
    manifiesto = json.loads((RAIZ / "catalog" / "manifest.json").read_text(encoding="utf-8"))["entradas"]
    assert [p[0] for p in cat["piezas"]] == [e["id"] for e in manifiesto] and len(cat["piezas"]) == 1500
    assert len(cat["movs"]) == 10
    for p in cat["piezas"]:
        autor = cat["autores"][p[1]]
        assert p[0].startswith(autor["m"] + "-" + autor["id"] + "-")
    datos = editor_datos.datos()
    assert {p[3] for p in cat["piezas"]} <= set(datos["paletas"]) | set(datos["paletasCatalogo"])


def test_los_sprites_de_un_movimiento_tienen_el_tamano_de_la_rejilla(tmp_path, monkeypatch):
    from PIL import Image
    monkeypatch.setattr(editor_datos, "SPRITES", tmp_path / "plantillas")
    assert editor_datos.sprites(["romanticismo"]) == ["romanticismo"]
    ancho, alto = editor_datos.CELDA_REVISION
    hoja = Image.open(tmp_path / "plantillas" / "romanticismo.jpg")
    assert hoja.size == (editor_datos.COLUMNAS * ancho, 8 * alto)  # 150 piezas en filas de 20
    assert not (tmp_path / "plantillas" / "gotico.jpg").exists()


def _escena_desde_compacta(compacta):
    from scene import Escena
    e = Escena(semilla=0)
    for p in compacta:
        if p[0] == "g":
            e.degradado(p[1], p[2])
            if p[3]:
                e.primitivas[-1]["paradas"] = [tuple(x) for x in p[3]]
        elif p[0] == "c":
            e.circulo(p[1], p[2], p[3], p[4])
        elif p[0] == "p":
            e.poligono(list(zip(p[2][0::2], p[2][1::2])), p[1])
        elif p[0] == "v":
            e.velo(p[1], p[2], p[3], p[4], p[5], p[6])
    return e


def test_las_escenas_compactas_dibujan_lo_mismo_que_la_receta():
    import json as _json
    import numpy as np
    import editor
    import muestreo
    from scene import ALTO, ANCHO
    manifiesto = {"entradas": editor_datos._manifiesto()}
    x, y = np.meshgrid(np.arange(0, ANCHO, 4) + 0.5, np.arange(0, ALTO, 4) + 0.5)
    vistos = set()
    for e in manifiesto["entradas"]:
        if e["movimiento"] in vistos:
            continue
        vistos.add(e["movimiento"])
        esc = editor._fondo_de_pieza(e["id"], manifiesto)
        compacta = _json.loads(_json.dumps(editor_datos.escena_compacta(esc)))  # lo que viaja en el JSON
        a = muestreo.colores(esc, x.ravel(), y.ravel())
        b = muestreo.colores(_escena_desde_compacta(compacta), x.ravel(), y.ravel())
        d = np.abs(a - b).max(axis=1)
        assert d.mean() < 1 and (d < 30).mean() > 0.995, (e["id"], d.mean())
    assert len(vistos) == 10


def test_los_sprites_traen_tambien_las_escenas_en_json(tmp_path, monkeypatch):
    import json as _json
    monkeypatch.setattr(editor_datos, "SPRITES", tmp_path / "plantillas")
    editor_datos.sprites(["romanticismo"])
    escenas = _json.loads((tmp_path / "plantillas" / "romanticismo.json").read_text(encoding="utf-8"))
    assert len(escenas) == 150 and all(e[0][0] == "g" for e in escenas)


def test_cada_plantilla_lleva_su_genero():
    cat = editor_datos.catalogo()
    assert [g["id"] for g in cat["generos"]] == ["poesia", "epica", "narrativa", "teatro", "ensayo"]
    assert all(0 <= p[6] < len(cat["generos"]) for p in cat["piezas"])
    assert len({p[6] for p in cat["piezas"]}) == len(cat["generos"])  # los cinco géneros aparecen
