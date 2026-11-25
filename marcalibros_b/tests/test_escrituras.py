"""Escrituras no latinas: fuentes Noto en subconjunto, composición con HarfBuzz y los dos renderers."""
import sys
from pathlib import Path

import pytest
import yaml

RAIZ = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(RAIZ / "generator"))

import subconjuntos  # noqa: E402
import texto as tx  # noqa: E402
from render_raster import a_imagen  # noqa: E402
from render_svg import a_svg  # noqa: E402
from scene import Escena  # noqa: E402

MUESTRAS = tx.escrituras()["muestras"]
CAJA = (0, 0, 200, 200)


def _escena(idioma):
    bloques, _ = tx.componer(MUESTRAS[idioma], "Autor", CAJA, idioma=idioma)
    esc = Escena(semilla=1)
    esc.degradado("#ffffff", "#ffffff")
    esc.texto(bloques, "#000000")
    return esc, bloques


@pytest.mark.parametrize("idioma", sorted(MUESTRAS))
def test_la_muestra_se_compone_y_se_dibuja(idioma):
    esc, bloques = _escena(idioma)
    assert all(tx.soporta(c, idioma) for c in MUESTRAS[idioma] if not c.isspace())
    assert bloques[0]["glifos"] and all(isinstance(c, tuple) for c, _, _ in bloques[0]["glifos"])
    assert all(isinstance(c, str) for c, _, _ in bloques[1]["glifos"])  # la autoría sigue en la fuente latina
    svg = a_svg(esc)
    assert f'id="{idioma}-' in svg and 'id="g' in svg
    assert (a_imagen(esc, 1).convert("L").getextrema()[0]) < 128  # el raster también pinta tinta


@pytest.mark.parametrize("idioma", sorted(MUESTRAS))
def test_las_lineas_caben_en_la_caja(idioma):
    bloques, alto = tx.componer(MUESTRAS[idioma], "", CAJA, idioma=idioma)
    xs = [x for _, x, _ in bloques[0]["glifos"]]
    assert min(xs) >= 0 and max(xs) <= 200 and alto < 200


def test_el_arabe_se_escribe_de_derecha_a_izquierda():
    # orden visual: la primera letra lógica (alef, que no se une a la siguiente) queda a la derecha
    glifos = tx._componer_linea("اب", "ar")[0]
    alef = tx._componer_linea("ا", "ar")[0][0][0]
    assert max(glifos, key=lambda g: g[1])[0] == alef  # el punto de la «ب» es otro glifo: por eso no son 2


def test_cjk_parte_por_caracteres_y_no_deja_signos_al_principio():
    lineas = tx._partir("床前明月光，疑是地上霜。" * 3, 15.0, 60, "zh")
    assert len(lineas) > 1 and all(l[0] not in "，。" for l in lineas)
    assert "".join(lineas) == "床前明月光，疑是地上霜。" * 3


def test_el_latin_no_cambia():
    assert tx.id_glifo("a") == "g61" and tx.fuente_de_idioma("es") is None and tx.fuente_de_idioma(None) is None
    bloques, _ = tx.componer("Hola", "", CAJA)
    assert all(isinstance(c, str) for c, _, _ in bloques[0]["glifos"])


def test_los_subconjuntos_cubren_las_frases():
    frases = yaml.safe_load((RAIZ / "catalog" / "frases.yaml").read_text(encoding="utf-8"))
    for q in (q for lista in frases["movimientos"].values() for q in lista):
        if tx.fuente_de_idioma(q.get("idioma")):
            assert all(tx.soporta(c, q["idioma"]) for c in q["texto"] if not c.isspace()), q["texto"]
    assert subconjuntos.main(["--origen", ".", "--comprobar"]) == 0


def test_los_subconjuntos_son_pequenos_y_llevan_licencia():
    carpeta = RAIZ / "generator" / "fuentes"
    for datos in tx.escrituras()["fuentes"].values():
        assert (carpeta / datos["archivo"]).stat().st_size < 150_000
    assert (carpeta / "LICENCIA-Noto-OFL.txt").exists()


def test_el_cjk_se_compone_mas_grande_y_el_resto_a_15():
    for idioma in ("ja", "zh"):
        bloques, _ = tx.componer(MUESTRAS[idioma], "", CAJA, idioma=idioma)
        assert bloques[0]["tam"] == 17.0
    for idioma in ("ar", "hi", None):
        bloques, _ = tx.componer(MUESTRAS.get(idioma, "Hola"), "", CAJA, idioma=idioma)
        assert bloques[0]["tam"] == 15.0
    bloques, _ = tx.componer(MUESTRAS["ja"], "", CAJA, tam=12.0, idioma="ja")
    assert bloques[0]["tam"] == 12.0  # un tamaño explícito manda
