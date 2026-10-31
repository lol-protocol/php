"""Modelo de datos del lector: ejemplos válidos, casos inválidos y sugerencias por id de Gutenberg."""
import copy
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "generator"))
import modelo  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
EJ = RAIZ / "datos" / "ejemplos"
cargar = lambda n: json.loads((EJ / f"{n}.json").read_text(encoding="utf-8"))
MANIFIESTO = json.loads((RAIZ / "catalog" / "manifest.json").read_text(encoding="utf-8"))
PIEZAS = {e["id"] for e in MANIFIESTO["entradas"]}


def test_ejemplos_validos():
    assert modelo.validar_diseno(cargar("diseno-catalogo"), PIEZAS) == []
    assert modelo.validar_diseno(cargar("diseno-usuario"), PIEZAS) == []
    assert modelo.validar_marca(cargar("marca")) == []


def test_marca_solo_referencia_al_diseno_por_id():
    m = cargar("marca")
    assert isinstance(m["diseno"], str)
    m["receta"] = {}
    assert modelo.validar_marca(m)  # nada del diseño cabe dentro de la marca


def test_disenos_invalidos():
    base = cargar("diseno-usuario")
    casos = {
        "imagen como URL": lambda d: d["receta"]["fondo"].update(imagen="http://x/y.png"),
        "svg en el fondo": lambda d: d["receta"]["fondo"].update(tipo="svg"),
        "recorte no 1:7": lambda d: d["receta"]["fondo"]["recorte"].update(alto=1000),
        "campo extra": lambda d: d.update(html="<b>"),
        "frase vacía": lambda d: d["receta"]["texto"].update(frase=""),
        "compartido sin moderar": lambda d: d.update(visibilidad="compartido"),
        "usuario con pieza": lambda d: d.update(pieza="romanticismo-becquer-01"),
    }
    for nombre, f in casos.items():
        d = copy.deepcopy(base)
        f(d)
        assert modelo.validar_diseno(d, PIEZAS), nombre
    c = cargar("diseno-catalogo")
    c["pieza"] = "no-existe-01"
    c["id"] = "cat:no-existe-01"
    assert modelo.validar_diseno(c, PIEZAS)
    for mal_id in ("cat:otra-02", "usr:" + "ab" * 16):  # el id debe corresponder al origen y a la pieza
        c = cargar("diseno-catalogo")
        c["id"] = mal_id
        assert modelo.validar_diseno(c), mal_id
    c = cargar("diseno-catalogo")
    c["id"] = "cat:romanticismo-becquer-02"
    assert modelo.validar_diseno(c)  # sin pasar `piezas` también se comprueba
    u = cargar("diseno-usuario")
    u["id"] = "cat:romanticismo-becquer-01"  # suplantar un id de catálogo
    assert modelo.validar_diseno(u)
    c = cargar("diseno-catalogo")
    c["propietario"] = "u-1"
    assert modelo.validar_diseno(c, PIEZAS)


def test_compartido_aprobado_vale():
    d = cargar("diseno-usuario")
    d.update(visibilidad="compartido", moderacion="aprobado")
    assert modelo.validar_diseno(d, PIEZAS) == []


def test_marcas_invalidas():
    base = cargar("marca")
    for nombre, f in {
        "fin antes de inicio": lambda m: m["posicion"].update(inicio=10, fin=5),
        "huella corta": lambda m: m["texto"].update(huella="abc"),
        "id de texto con letras": lambda m: m["texto"].update(id="abc"),
        "etiqueta enorme": lambda m: m.update(etiqueta="x" * 81),
        "sin diseño": lambda m: m.pop("diseno"),
    }.items():
        m = copy.deepcopy(base)
        f(m)
        assert modelo.validar_marca(m), nombre


def test_sugerencias_solo_con_id_verificado():
    # hoy todo está sin_verificar: el lector no sugiere nada hasta comprobar los ids
    assert modelo.sugerencias_por_gutenberg(MANIFIESTO) == {}
    falso = {"entradas": [{"id": "romanticismo-becquer-01", "gutenberg_estado": "verificado", "gutenberg_id": 1234},
                          {"id": "romanticismo-becquer-02", "gutenberg_estado": "sin_verificar", "gutenberg_id": 99},
                          {"id": "realismo-magico-rulfo-01", "gutenberg_estado": "no_disponible", "gutenberg_id": None}]}
    assert modelo.sugerencias_por_gutenberg(falso) == {"1234": ["cat:romanticismo-becquer-01"]}


def test_sugerencias_por_metadatos_reconocen_autor_y_obra():
    config = __import__("yaml").safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    libro = {"title": "Rimas", "authors": [{"name": "Bécquer, Gustavo Adolfo", "birth_year": 1836, "death_year": 1870}]}
    s = modelo.sugerencias_por_metadatos(libro, config)
    assert s and all(x["diseno"].startswith("cat:romanticismo-becquer-") for x in s)
    assert len(s) == 10
    # el título que se parece a una obra del catálogo pone esa pieza primero
    libro["title"] = "El monte de las ánimas"
    primero = modelo.sugerencias_por_metadatos(libro, config)[0]
    assert primero["nivel"] == "obra" and primero["diseno"] == "cat:romanticismo-becquer-02"
    # los diseños sugeridos existen en el manifest
    assert all(x["diseno"][4:] in PIEZAS for x in s)


def test_sugerencias_por_metadatos_no_confunden_autores():
    config = __import__("yaml").safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    # mismo apellido, otros años: no es Poe ni Dickens
    assert modelo.sugerencias_por_metadatos({"title": "x", "authors": [{"name": "Poe, Alguien", "birth_year": 1950, "death_year": 2010}]}, config) == []
    assert modelo.sugerencias_por_metadatos({"title": "x", "authors": [{"name": "Anónimo"}]}, config) == []
    # Poe, en el orden de Gutendex
    poe = modelo.sugerencias_por_metadatos({"title": "x", "authors": [{"name": "Poe, Edgar Allan", "birth_year": 1809, "death_year": 1849}]}, config)
    assert poe and all("-poe-" in x["diseno"] for x in poe)
    # otra grafía (Virgil / Virgilio) se reconoce por prefijo y años a. C.; un nombre suelto sin años no basta (Homer)
    homero = modelo.sugerencias_por_metadatos({"title": "The Iliad", "authors": [{"name": "Homer"}]}, config)
    assert homero == []  # sin años ni nombre completo: un solo nombre no basta
    virgilio = modelo.sugerencias_por_metadatos({"title": "Aeneid", "authors": [{"name": "Virgil", "birth_year": -70, "death_year": -19}]}, config)
    assert len(virgilio) == 10 and all("-virgilio-" in x["diseno"] for x in virgilio)  # Virgil ~ Virgilio, y los años a. C. coinciden
