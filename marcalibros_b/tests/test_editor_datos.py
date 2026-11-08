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
