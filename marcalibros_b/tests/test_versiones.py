"""Las versiones instaladas son las fijadas: de ellas depende la salida del generador."""
import re
from importlib.metadata import version
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent


def test_las_versiones_instaladas_son_las_fijadas():
    fijadas = dict(re.findall(r"^([A-Za-z0-9_.-]+)==([^\s#]+)", (RAIZ / "generator" / "requirements.txt").read_text(encoding="utf-8"), re.M))
    assert {"Pillow", "numpy", "PyYAML", "fonttools"} <= set(fijadas)
    distintas = {n: (v, version(n)) for n, v in fijadas.items() if version(n) != v}
    assert not distintas, (
        f"Versiones distintas de las fijadas en generator/requirements.txt (fijada, instalada): {distintas}. "
        "La rasterización de Pillow, numpy y fonttools decide qué semilla sale en cada pieza, así que con otra versión "
        "los hashes de referencia pueden cambiar sin que el código lo haya hecho. Instala las fijadas "
        "(pip install -r generator/requirements.txt) o, si las subes a propósito, regenera con "
        "`python generator/build.py --all --forzar` y `python tests/test_generador.py --actualizar`.")
