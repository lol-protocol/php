"""Línea de marca: SVG bien formados, sin referencias externas, iconos 24×24, tokens con contraste."""
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "generator"))
import marca  # noqa: E402
from plantillas import contraste  # noqa: E402

PIEZAS = marca.todo()
SVGS = {r: c for r, c in PIEZAS.items() if r.endswith(".svg")}


def test_svg_bien_formados_y_sin_referencias_externas():
    for ruta, svg in SVGS.items():
        ET.fromstring(svg)
        assert "http://" not in svg.replace("http://www.w3.org/2000/svg", ""), ruta
        assert "<script" not in svg and "href=\"http" not in svg, ruta


def test_tamanos_de_cada_familia():
    esperado = {"iconos/": (24, 24), "estados-vacios/": (480, 320), "cabeceras/": (1920, 1080), "previews/": (1200, 630)}
    for ruta, svg in SVGS.items():
        for prefijo, (w, h) in esperado.items():
            if ruta.startswith(prefijo) and ruta != "iconos/sprite.svg":
                assert f'width="{w}" height="{h}"' in svg, ruta


def test_peso_razonable():
    for ruta, svg in SVGS.items():
        assert len(svg.encode()) <= 20 * 1024, ruta


def test_tokens_con_contraste():
    t = marca.tokens()
    assert contraste(t["base"]["tinta"], t["base"]["papel"]) >= 7
    for mov, d in t["movimientos"].items():
        assert contraste(d["acento"], t["base"]["papel"]) >= marca.MIN_ACENTO, mov
        assert contraste(d["acento"], d["sobre_acento"]) >= 4.5, mov


def test_determinista():
    assert marca.todo() == PIEZAS
