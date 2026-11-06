"""El hash de la caché cubre lo que dibuja las piezas y nada más (rápido, sin Chromium)."""
import ast
import shutil
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(RAIZ / "generator"))

import build  # noqa: E402

GENERADOR = RAIZ / "generator"


def _importados(modulo):
    """Módulos locales (de generator/) que importa un módulo."""
    arbol = ast.parse((GENERADOR / f"{modulo}.py").read_text(encoding="utf-8"))
    nombres = set()
    for nodo in ast.walk(arbol):
        if isinstance(nodo, ast.Import):
            nombres |= {a.name.split(".")[0] for a in nodo.names}
        elif isinstance(nodo, ast.ImportFrom) and nodo.module:
            nombres.add(nodo.module.split(".")[0])
    return {n for n in nombres if (GENERADOR / f"{n}.py").exists()}


def test_la_lista_cubre_todo_lo_que_build_importa():
    visto, pendientes = set(), ["build"]
    while pendientes:
        m = pendientes.pop()
        if m not in visto:
            visto.add(m)
            pendientes += sorted(_importados(m))
    assert visto == set(build.MODULOS_DE_RENDER), (
        f"MODULOS_DE_RENDER no coincide con lo que build.py importa: sobran {set(build.MODULOS_DE_RENDER) - visto}, faltan {visto - set(build.MODULOS_DE_RENDER)}")


def test_solo_los_modulos_de_render_cambian_el_hash(tmp_path, monkeypatch):
    shutil.copytree(GENERADOR, tmp_path / "generator", ignore=shutil.ignore_patterns("__pycache__"))
    (tmp_path / "catalog").mkdir()
    for nombre in ("animos.yaml", "motivos.yaml", "frases.yaml"):
        shutil.copy(RAIZ / "catalog" / nombre, tmp_path / "catalog" / nombre)
    monkeypatch.setattr(build, "RAIZ", tmp_path)
    monkeypatch.setattr(build, "CATALOGO", tmp_path / "catalog")
    base = build.hash_generador()
    for ajeno in ("editor", "modelo", "marca", "gutenberg_manual", "revision_web", "verificar_gutenberg"):
        with open(tmp_path / "generator" / f"{ajeno}.py", "a", encoding="utf-8") as f:
            f.write("\n# cambio sin efecto en las piezas\n")
    assert build.hash_generador() == base
    for propio in build.MODULOS_DE_RENDER:
        ruta = tmp_path / "generator" / f"{propio}.py"
        antes = ruta.read_text(encoding="utf-8")
        ruta.write_text(antes + "\n# cambio\n", encoding="utf-8")
        assert build.hash_generador() != base, propio
        ruta.write_text(antes, encoding="utf-8")
