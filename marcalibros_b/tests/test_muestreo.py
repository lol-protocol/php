"""El muestreo de escenas (sin Pillow) decide como el raster: mismos colores, mismas decisiones."""
import ast
import sys
from pathlib import Path

import numpy as np
import yaml

RAIZ = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(RAIZ / "generator"))

import build  # noqa: E402
import muestreo  # noqa: E402
import qa  # noqa: E402
from render_raster import a_imagen  # noqa: E402
from scene import ALTO, ANCHO, Escena  # noqa: E402

CONFIG = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
ANIMOS = build.cargar_animos()


def _escenas(n=12):
    """Escenas de movimientos y autores distintos, con la semilla de partida."""
    salida = []
    for mov in CONFIG["movimientos"]:
        autor = mov["autores"][len(salida) % len(mov["autores"])]
        for v in (2, 5):
            salida.append(build.construir(CONFIG, mov, autor, v, 0, ANIMOS)[1])
    return salida[:n]


def test_muestreo_no_importa_pillow():
    arbol = ast.parse((Path(muestreo.__file__)).read_text(encoding="utf-8"))
    importados = {a.name.split(".")[0] for n in ast.walk(arbol) if isinstance(n, ast.Import) for a in n.names}
    importados |= {n.module.split(".")[0] for n in ast.walk(arbol) if isinstance(n, ast.ImportFrom) and n.module}
    assert "PIL" not in importados and "render_raster" not in importados


def test_los_colores_muestreados_coinciden_con_el_raster():
    # el raster antialiasa los bordes y el muestreo no: se compara la media y que casi todos los puntos coincidan
    x, y = np.meshgrid(np.arange(0, ANCHO, 5) + 0.5, np.arange(0, ALTO, 5) + 0.5)
    for esc in _escenas():
        raster = np.asarray(a_imagen(esc).convert("RGB"), dtype=float)[y.astype(int), x.astype(int)].reshape(-1, 3)
        m = muestreo.colores(esc, x.ravel(), y.ravel())
        d = np.abs(raster - m).max(axis=1)
        assert d.mean() < 4, d.mean()
        assert (d < 30).mean() > 0.97, (d < 30).mean()


def test_pixeles_de_motivo_ve_el_motivo():
    mov = next(m for m in CONFIG["movimientos"] if m["id"] == "romanticismo")
    autor = next(a for a in mov["autores"] if a["id"] == "poe")
    esc = build.construir(CONFIG, mov, autor, 3, 0, ANIMOS)[1]
    sin = build.construir(CONFIG, mov, autor, 3, 0, ANIMOS, ocultar_motivo=True)[1]
    assert muestreo.pixeles_de_motivo(esc, sin, qa.UMBRAL_CAMBIO) > qa.MIN_PIXELES_MOTIVO
    assert muestreo.pixeles_de_motivo(esc, esc, qa.UMBRAL_CAMBIO) == 0


def test_huella_y_franja_son_deterministas_y_con_la_forma_esperada():
    esc = _escenas(1)[0]
    h = muestreo.huella(esc)
    assert h.shape == (8 * 56 * 3,) and h.min() == 0 and h.max() < 255.001
    assert np.array_equal(h, muestreo.huella(esc))
    assert muestreo.franja_colores(esc).shape[1] == 3


def test_el_velo_y_el_cielo_calmado_cambian_los_colores_de_la_franja():
    esc = _escenas(1)[0]
    antes = muestreo.franja_colores(esc).mean()
    x0, y0, x1, y1 = esc.franja
    esc.velo(x0, y0, x1, y1, "#000000", 0.8)
    assert muestreo.franja_colores(esc).mean() < antes * 0.5
    esc.primitivas.pop()
    build._calmar_cielo(esc, 0.6, "claro")
    assert muestreo.franja_colores(esc).mean() < antes


def test_una_escena_sin_degradado_falla():
    e = Escena(semilla=1)
    e.circulo(10, 10, 5, "#FFFFFF")
    try:
        muestreo.colores(e, [1.0], [1.0])
    except ValueError:
        return
    raise AssertionError("debía fallar")
