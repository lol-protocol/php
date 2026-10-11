"""Tests del generador. Ejecutar desde la raíz: python -m pytest tests

Los hashes de referencia (tests/hashes_referencia.json) fijan la salida byte a byte.
Si cambias una plantilla a propósito, regenera con:
    python tests/test_generador.py --actualizar
"""
import hashlib
import json
import sys
from pathlib import Path

import numpy as np
import yaml

RAIZ = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(RAIZ / "generator"))

import build  # noqa: E402
from qa import UMBRAL_DUPLICADO, huella_visual, validar_jpg, validar_svg  # noqa: E402
from render_raster import a_imagen  # noqa: E402
from render_svg import a_svg  # noqa: E402

REFERENCIA = Path(__file__).with_name("hashes_referencia.json")
CONFIG = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))


def _piezas():
    """Reproduce la generación completa en memoria, con la misma lógica de duplicados."""
    huellas, salida = {}, {}
    for mov, autor, v in build.recorrer(CONFIG):
        previas = {k: h for k, h in huellas.items() if k.startswith(mov["id"] + "-")}
        for intento in range(build.MAX_INTENTOS + 1):
            entrada, esc = build.construir(CONFIG, mov, autor, v, intento)
            h = huella_visual(a_imagen(esc))
            if build.parecido_a(h, previas) is None:
                break
        huellas[entrada["id"]] = h
        salida[entrada["id"]] = (entrada, esc)
    return salida, huellas


def _hash_pieza(entrada, esc):
    if entrada["formato"] == "svg":
        return hashlib.sha256(a_svg(esc).encode("utf-8")).hexdigest()
    return hashlib.sha256(a_imagen(esc).tobytes()).hexdigest()  # píxeles, no el códec JPEG


def test_determinista():
    a, _ = _piezas()
    b, _ = _piezas()
    assert {k: _hash_pieza(*v) for k, v in a.items()} == {k: _hash_pieza(*v) for k, v in b.items()}


def test_hashes_de_referencia():
    piezas, _ = _piezas()
    esperado = json.loads(REFERENCIA.read_text(encoding="utf-8"))
    obtenido = {k: _hash_pieza(*v) for k, v in piezas.items()}
    assert obtenido == esperado, "la salida cambió: revisa y regenera con --actualizar si es intencional"


def test_sin_duplicados_visuales():
    _, huellas = _piezas()
    ids = list(huellas)
    for i, a in enumerate(ids):
        for b in ids[i + 1:]:
            if a.split("-")[0] == b.split("-")[0]:
                assert np.abs(huellas[a] - huellas[b]).mean() >= UMBRAL_DUPLICADO, f"{a} ≈ {b}"


def test_pares_arquetipo_paleta_unicos_por_autor():
    for mov in CONFIG["movimientos"]:
        for autor in mov["autores"]:
            pares = [build.combinacion(mov, autor, v) for v in range(1, autor["variantes"] + 1)]
            assert len(set(pares)) == len(pares)


def test_archivos_del_catalogo_validos():
    manifiesto = json.loads((RAIZ / "catalog" / "manifest.json").read_text(encoding="utf-8"))
    for e in manifiesto["entradas"]:
        ruta = RAIZ / e["archivo"]
        assert hashlib.sha256(ruta.read_bytes()).hexdigest() == e["sha256"], e["id"]
        assert not (validar_svg(ruta) if e["formato"] == "svg" else validar_jpg(ruta)), e["id"]


if __name__ == "__main__":
    if "--actualizar" in sys.argv:
        piezas, _ = _piezas()
        REFERENCIA.write_text(
            json.dumps({k: _hash_pieza(*v) for k, v in sorted(piezas.items())}, indent=2) + "\n",
            encoding="utf-8")
        print(f"escrito {REFERENCIA} ({len(piezas)} piezas)")
