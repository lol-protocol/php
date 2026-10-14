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
from qa import UMBRAL_DUPLICADO, validar_taxonomia, huella_visual, validar_jpg, validar_svg  # noqa: E402
from plantillas import NOMBRES_ARQUETIPOS, PALETAS  # noqa: E402
from render_raster import a_imagen  # noqa: E402
from render_svg import a_svg  # noqa: E402

REFERENCIA = Path(__file__).with_name("hashes_referencia.json")
MOTIVOS = yaml.safe_load((RAIZ / "catalog" / "motivos.yaml").read_text(encoding="utf-8"))
ANIMOS = build.cargar_animos()
ESCENAS = set(NOMBRES_ARQUETIPOS)
CONFIG = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))


def _piezas():
    """Reproduce la generación completa en memoria, con la misma lógica que el build."""
    huellas, salida = {}, {}
    for mov, autor, v in build.recorrer(CONFIG):
        previas = {k: h for k, h in huellas.items() if k.startswith(mov["id"] + "-")}
        entrada, esc, h, _, problema = build.elegir_pieza(CONFIG, mov, autor, v, ANIMOS, previas)
        assert problema is None, f"{entrada['id']}: {problema}"
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
            pares = [build.combinacion(mov, autor, v, ANIMOS) for v in range(1, len(autor["obras"]) + 1)]
            assert len(set(pares)) == len(pares)


def test_taxonomia_coherente():
    assert validar_taxonomia(CONFIG, MOTIVOS, ANIMOS, ESCENAS, PALETAS) == []


def test_taxonomia_rechaza_verificado_sin_id_y_motivo_inexistente():
    import copy
    malo = copy.deepcopy(CONFIG)
    obra = malo["movimientos"][0]["autores"][0]["obras"][0]
    obra["gutenberg"]["estado"] = "verificado"
    obra["motivo"] = "dragon"
    assert len(validar_taxonomia(malo, MOTIVOS, ANIMOS, ESCENAS, PALETAS)) == 2


def test_taxonomia_rechaza_motivos_incoherentes():
    import copy
    malo = copy.deepcopy(CONFIG)
    obra = malo["movimientos"][0]["autores"][0]["obras"][0]
    obra.update(motivo="velero", animo="pastoral")  # velero exige escena marina
    assert any("exige escena" in p for p in validar_taxonomia(malo, MOTIVOS, ANIMOS, ESCENAS, PALETAS))
    obra.update(motivo="luna", animo="auroral")  # luna exige ánimo nocturno
    assert any("exige ánimo" in p for p in validar_taxonomia(malo, MOTIVOS, ANIMOS, ESCENAS, PALETAS))


def test_la_paleta_respeta_el_animo():
    for mov in CONFIG["movimientos"]:
        for autor in mov["autores"]:
            for v, obra in enumerate(autor["obras"], 1):
                assert build.combinacion(mov, autor, v, ANIMOS)[1] in ANIMOS[obra["animo"]]


def test_informe_gutenberg_con_respuesta_simulada():
    import verificar_gutenberg as vg
    falso = [{"id": 1, "title": "Rimas", "languages": ["es"],
              "authors": [{"name": "Bécquer, Gustavo Adolfo", "birth_year": 1836, "death_year": 1870}]}]
    texto = vg.informe(CONFIG, "romanticismo", buscar=lambda nombre: falso)
    assert "| 1 | Rimas | es | Bécquer, Gustavo Adolfo (1836–1870) |" in texto


def test_paletas_con_siluetas_legibles():
    import plantillas
    for nombre in plantillas.PALETAS:
        p = plantillas.paleta_legible(nombre)
        peor = min(plantillas.contraste(p["figura"], p[k]) for k in ("cielo_bajo", "lejos"))
        assert peor >= plantillas.MIN_CONTRASTE_FIGURA, f"{nombre}: figura {peor:.1f}:1"


def test_motivos_visibles_en_miniatura():
    from qa import MIN_PIXELES_MOTIVO
    manifiesto = json.loads((RAIZ / "catalog" / "manifest.json").read_text(encoding="utf-8"))
    for e in manifiesto["entradas"]:
        if e["motivo"] not in build.SIN_COMPROBAR_VISIBILIDAD:
            assert e["pixeles_motivo"] >= MIN_PIXELES_MOTIVO, f"{e['id']}: {e['motivo']}"


def test_franja_de_texto_en_el_manifest():
    from qa import MIN_CONTRASTE_TEXTO, OPACIDADES_VELO
    manifiesto = json.loads((RAIZ / "catalog" / "manifest.json").read_text(encoding="utf-8"))
    for e in manifiesto["entradas"]:
        assert e["texto_recomendado"] in ("claro", "oscuro")
        assert e["velo"] in (0.0, *OPACIDADES_VELO)
        assert len(e["franja"]) == 4
        if e["velo"] < OPACIDADES_VELO[-1]:  # solo el velo más fuerte puede quedarse corto
            assert e["contraste_texto"] >= MIN_CONTRASTE_TEXTO, e["id"]


def test_motivo_no_invade_la_franja_de_texto():
    import plantillas
    piezas, _ = _piezas()
    for ident, (entrada, esc) in piezas.items():
        assert esc.franja is not None, ident
        if esc.bbox_motivo and entrada["motivo"] not in plantillas.AMBIENTALES:
            y0, y1 = esc.franja[1], esc.franja[3]
            assert esc.bbox_motivo[3] < y0 or esc.bbox_motivo[1] > y1, f"{ident}: el motivo pisa la franja"


def test_formato_vertical_1_a_7():
    from scene import ALTO, ANCHO
    assert (ANCHO, ALTO) == (200, 1400) and ALTO == 7 * ANCHO


def test_motivos_dentro_del_ancho():
    import plantillas
    piezas, _ = _piezas()
    for ident, (entrada, esc) in piezas.items():
        if esc.bbox_motivo and entrada["motivo"] not in plantillas.AMBIENTALES:
            assert esc.bbox_motivo[0] >= 0 and esc.bbox_motivo[2] <= 200, f"{ident}: motivo cortado"


def test_gutenberg_no_disponible_no_lleva_id():
    import copy
    malo = copy.deepcopy(CONFIG)
    obra = malo["movimientos"][0]["autores"][0]["obras"][0]
    obra["gutenberg"] = {"id": None, "estado": "no_disponible"}
    assert validar_taxonomia(malo, MOTIVOS, ANIMOS, ESCENAS, PALETAS) == []
    obra["gutenberg"] = {"id": 123, "estado": "no_disponible"}
    assert any("no_disponible" in p for p in validar_taxonomia(malo, MOTIVOS, ANIMOS, ESCENAS, PALETAS))


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
