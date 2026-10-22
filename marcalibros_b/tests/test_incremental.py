"""Pruebas de la compilación incremental, con un catálogo pequeño en un directorio temporal.

Necesitan Chromium (el build lo usa para el QA de los SVG); sin él se omiten.
"""
import copy
import shutil
import sys
from pathlib import Path

import pytest
import yaml

RAIZ = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(RAIZ / "generator"))

import build  # noqa: E402

pytestmark = pytest.mark.skipif(build.chromium() is None, reason="hace falta Chromium")

MOV = "romanticismo"


@pytest.fixture
def catalogo(tmp_path, monkeypatch):
    """Raíz temporal con la taxonomía reducida a un autor de 4 obras."""
    (tmp_path / "catalog").mkdir()
    (tmp_path / "generator").symlink_to(RAIZ / "generator", target_is_directory=True)
    for nombre in ("motivos.yaml", "animos.yaml", "frases.yaml"):
        shutil.copy(RAIZ / "catalog" / nombre, tmp_path / "catalog" / nombre)
    config = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    mov = next(m for m in config["movimientos"] if m["id"] == MOV)
    autor = copy.deepcopy(mov["autores"][0])
    autor["obras"] = autor["obras"][:4]
    mov["autores"] = [autor]
    config["movimientos"] = [mov]
    monkeypatch.setattr(build, "RAIZ", tmp_path)
    monkeypatch.setattr(build, "CATALOGO", tmp_path / "catalog")
    build._ASIGNACIONES.clear()
    build.cargar_frases.cache_clear()  # apunta al catálogo temporal, no al real

    def guardar(cfg):
        (tmp_path / "catalog" / "taxonomia.yaml").write_text(yaml.safe_dump(cfg, allow_unicode=True, sort_keys=False),
                                                           encoding="utf-8")
    guardar(config)
    yield tmp_path, config, guardar
    build.cargar_frases.cache_clear()


def _ejecutar(*extra):
    assert build.main(["--movimiento", MOV, *extra]) == 0
    return dict(build.RESUMEN)


def test_segunda_pasada_reutiliza_todo(catalogo):
    raiz, _, _ = catalogo
    assert _ejecutar()["rehechas"] == 4
    archivos = sorted((raiz / "catalog").glob("svg/*")) + sorted((raiz / "catalog").glob("jpg/*"))
    antes = {f: f.stat().st_mtime_ns for f in archivos}
    resumen = _ejecutar()
    assert (resumen["rehechas"], resumen["reutilizadas"], resumen["hojas"]) == (0, 4, 0)
    assert {f: f.stat().st_mtime_ns for f in archivos} == antes, "no debe tocar archivos reutilizados"


def test_cambiar_una_obra_rehace_solo_desde_ella(catalogo):
    _, config, guardar = catalogo
    _ejecutar()
    config["movimientos"][0]["autores"][0]["obras"][2]["motivo"] = "cruz"
    guardar(config)
    resumen = _ejecutar()
    assert f"{MOV}-becquer-03" in resumen["ids_rehechas"]
    assert f"{MOV}-becquer-01" not in resumen["ids_rehechas"]
    assert f"{MOV}-becquer-02" not in resumen["ids_rehechas"]
    assert resumen["rehechas"] < 4 or resumen["reutilizadas"] == 2


def test_quitar_una_obra_borra_su_archivo(catalogo):
    raiz, config, guardar = catalogo
    _ejecutar()
    ultimo = next((raiz / "catalog").glob("svg/*-04.svg"))
    config["movimientos"][0]["autores"][0]["obras"].pop()
    guardar(config)
    _ejecutar()
    assert not ultimo.exists()


def test_archivo_borrado_se_rehace_y_forzar_rehace_todo(catalogo):
    raiz, _, _ = catalogo
    _ejecutar()
    next((raiz / "catalog").glob("svg/*-02.svg")).unlink()
    assert _ejecutar()["ids_rehechas"][0] == f"{MOV}-becquer-02"
    assert _ejecutar("--forzar")["rehechas"] == 4


def test_la_clave_cambia_con_el_codigo_del_generador(catalogo):
    _, config, _ = catalogo
    mov = config["movimientos"][0]
    autor = mov["autores"][0]
    animos = build.cargar_animos()
    base = build.clave_pieza("gen-a", config, mov, autor, 1, animos, {})
    assert base == build.clave_pieza("gen-a", config, mov, autor, 1, animos, {})
    assert base != build.clave_pieza("gen-b", config, mov, autor, 1, animos, {})


def test_movimiento_de_no_confunde_prefijos():
    ids = ["realismo", "realismo-magico"]
    assert build._movimiento_de("realismo-magico-garro-01", ids) == "realismo-magico"
    assert build._movimiento_de("realismo-galdos-01", ids) == "realismo"
    assert build._movimiento_de("otro-x-01", ids) is None
