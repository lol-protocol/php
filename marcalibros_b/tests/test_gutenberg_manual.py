"""Verificación manual de Gutenberg: lista, validación de filas y edición de la taxonomía."""
import csv
import shutil
import sys
from pathlib import Path

import yaml

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "generator"))
import gutenberg_manual as gm  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
CONFIG = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))


def _csv(tmp_path, filas):
    ruta = tmp_path / "r.csv"
    with open(ruta, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, gm.CAMPOS)
        w.writeheader()
        w.writerows(filas)
    return ruta


def _fila(**kw):
    base = {"pieza": "", "autor": "", "obra": "", "buscar": "", "estado": "", "id": ""}
    base.update(kw)
    return base


def test_lista_tiene_una_fila_por_autor_y_por_obra():
    f = gm.filas(CONFIG)
    obras = sum(len(a.get("obras", [])) for m in CONFIG["movimientos"] for a in m["autores"])
    autores = sum(len(m["autores"]) for m in CONFIG["movimientos"])
    assert len(f) == obras + autores and obras == 1000
    assert all(r["buscar"].startswith("https://www.gutenberg.org/ebooks/search/?query=") for r in f)


def test_aplica_a_una_obra_y_a_un_autor_sin_tocar_lo_demas(tmp_path):
    tax = tmp_path / "taxonomia.yaml"
    shutil.copy(RAIZ / "catalog" / "taxonomia.yaml", tax)
    antes = tax.read_text(encoding="utf-8")
    r = _csv(tmp_path, [_fila(pieza="romanticismo-becquer-02", estado="verificado", id="12345"),
                        _fila(autor="realismo-magico/rulfo", estado="no_disponible"),
                        _fila(pieza="gotico-stoker-01")])  # sin estado: no se toca
    assert gm.aplicar(r, tax) == []
    nuevo = yaml.safe_load(tax.read_text(encoding="utf-8"))
    mov = {m["id"]: m for m in nuevo["movimientos"]}
    assert mov["romanticismo"]["autores"][0]["obras"][1]["gutenberg"] == {"id": 12345, "estado": "verificado"}
    rulfo = next(a for a in mov["realismo-magico"]["autores"] if a["id"] == "rulfo")
    assert rulfo["gutenberg_autor"] == {"id": None, "estado": "no_disponible"}
    # solo cambian esos dos bloques
    d = [(a, b) for a, b in zip(antes.split("\n"), tax.read_text(encoding="utf-8").split("\n")) if a != b]
    assert len(d) == 3  # id y estado de la obra, estado del autor
    orig = yaml.safe_load(antes)
    nuevo["movimientos"][0]["autores"][0]["obras"][1]["gutenberg"] = {"id": None, "estado": "sin_verificar"}
    rulfo["gutenberg_autor"] = {"id": None, "estado": "sin_verificar"}
    assert nuevo == orig


def test_filas_invalidas_se_rechazan_y_no_escriben(tmp_path):
    tax = tmp_path / "taxonomia.yaml"
    shutil.copy(RAIZ / "catalog" / "taxonomia.yaml", tax)
    antes = tax.read_text(encoding="utf-8")
    for fila in (_fila(pieza="romanticismo-becquer-02", estado="verificado"),  # sin id
                 _fila(pieza="romanticismo-becquer-02", estado="verificado", id="abc"),
                 _fila(pieza="romanticismo-becquer-02", estado="no_disponible", id="1"),
                 _fila(pieza="romanticismo-becquer-02", estado="quizá"),
                 _fila(pieza="no-existe-01", estado="no_disponible"),
                 _fila(autor="x/y", estado="no_disponible")):
        assert gm.aplicar(_csv(tmp_path, [fila]), tax), fila
    assert tax.read_text(encoding="utf-8") == antes
