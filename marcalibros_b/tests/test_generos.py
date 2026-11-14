"""Géneros como eje: cada obra del catálogo tiene uno, y los temas heredan el de su obra base."""
import collections
import sys
from pathlib import Path

import pytest
import yaml

RAIZ = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(RAIZ / "generator"))

import modelo  # noqa: E402

CONFIG = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
GENEROS = modelo.cargar_generos()


def test_todas_las_obras_tienen_genero():
    cuenta = collections.Counter()
    for m in CONFIG["movimientos"]:
        for a in m["autores"]:
            for o in a["obras"]:
                cuenta[modelo.genero_de_obra(o, a)] += 1  # falla con un mensaje claro si falta
    assert set(cuenta) == {g["id"] for g in GENEROS["generos"]}
    assert sum(cuenta.values()) == sum(len(a["obras"]) for m in CONFIG["movimientos"] for a in m["autores"])


def test_un_tipo_nuevo_sin_genero_falla_con_claridad():
    autor = {"obras": []}
    with pytest.raises(ValueError, match="no tiene género"):
        modelo.genero_de_obra({"tipo": "haiku", "titulo": "Un haiku"}, autor)


def test_los_temas_heredan_el_genero_de_su_obra_base():
    gotico = next(m for m in CONFIG["movimientos"] if m["id"] == "gotico")
    mary = next(a for a in gotico["autores"] if a["id"] == "shelley-mary")
    criatura = next(o for o in mary["obras"] if o["titulo"] == "La criatura en el bosque")
    assert criatura["tipo"] == "tema" and modelo.genero_de_obra(criatura, mary) == "narrativa"
    clasicismo = next(m for m in CONFIG["movimientos"] if m["id"] == "clasicismo")
    homero = next(a for a in clasicismo["autores"] if a["id"] == "homero")
    assert modelo.genero_de_obra(next(o for o in homero["obras"] if o["id"] == "iliada"), homero) == "epica"
    # los temas sacados de una colección sin obra base usan la tabla de colecciones
    realismo = next(m for m in CONFIG["movimientos"] if m["id"] == "realismo")
    flaubert = next(a for a in realismo["autores"] if a["id"] == "flaubert")
    herodias = next(o for o in flaubert["obras"] if o["titulo"] == "Herodías")
    assert modelo.genero_de_obra(herodias, flaubert) == "narrativa"


def test_los_tipos_de_generos_no_se_repiten():
    todos = [t for g in GENEROS["generos"] for t in g["tipos"]]
    assert len(todos) == len(set(todos))
