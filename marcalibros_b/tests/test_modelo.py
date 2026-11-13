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


def test_titulos_en_idioma_original_reconocen_la_obra():
    config = __import__("yaml").safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    poe = {"name": "Poe, Edgar Allan", "birth_year": 1809, "death_year": 1849}
    primero = modelo.sugerencias_por_metadatos({"title": "The Raven", "authors": [poe]}, config)[0]
    assert primero == {"diseno": "cat:romanticismo-poe-01", "nivel": "obra"}
    usher = modelo.sugerencias_por_metadatos({"title": "The Fall of the House of Usher", "authors": [poe]}, config)[0]
    assert usher["diseno"] == "cat:romanticismo-poe-03" and usher["nivel"] == "obra"
    # un título con subtítulo y un tema «de» la obra (La criatura en el bosque, de Frankenstein) también
    shelley = {"name": "Shelley, Mary Wollstonecraft", "birth_year": 1797, "death_year": 1851}
    s = modelo.sugerencias_por_metadatos({"title": "Frankenstein; Or, The Modern Prometheus", "authors": [shelley]}, config)
    assert [x["nivel"] for x in s[:5]] == ["obra"] * 5 and s[0]["diseno"] == "cat:gotico-shelley-mary-01"
    # Dracula (sin tilde) ~ Drácula; un título cualquiera no sube ninguna obra
    stoker = {"name": "Stoker, Bram", "birth_year": 1847, "death_year": 1912}
    assert modelo.sugerencias_por_metadatos({"title": "Dracula", "authors": [stoker]}, config)[0]["nivel"] == "obra"
    assert all(x["nivel"] == "autor" for x in modelo.sugerencias_por_metadatos({"title": "A Different Book", "authors": [stoker]}, config))


def test_los_titulos_originales_apuntan_a_obras_que_existen():
    config = __import__("yaml").safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    ids = {a["id"]: {o["id"] for o in a["obras"]} for m in config["movimientos"] for a in m["autores"]}
    for autor, obras in modelo.cargar_titulos_originales().items():
        assert autor in ids, autor
        for obra, titulos in obras.items():
            assert obra in ids[autor], f"{autor}/{obra}"
            assert titulos and all(isinstance(t, str) and t for t in titulos), f"{autor}/{obra}"


def test_informe_de_candidatos_filtra_por_autor_y_marca_las_obras():
    import verificar_gutenberg
    config = __import__("yaml").safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    poe = {"id": 2147, "title": "The Raven", "languages": ["en"], "authors": [{"name": "Poe, Edgar Allan", "birth_year": 1809, "death_year": 1849}]}
    otro = {"id": 99, "title": "Poe Studies", "languages": ["en"], "authors": [{"name": "Smith, Jane", "birth_year": 1900, "death_year": 1990}]}
    informe = verificar_gutenberg.informe(config, "romanticismo", buscar=lambda nombre: [poe, otro] if "Poe" in nombre else [])
    bloque = informe.split("## Edgar Allan Poe")[1].split("##")[0]
    assert "1 del autor, 1 descartados" in bloque
    assert "| 2147 | The Raven | en | Poe, Edgar Allan (1809–1849) | El cuervo |" in bloque
    assert "Poe Studies" not in bloque


def _libro(ident, titulo, autor, nac, mue, descargas=0):
    return {"id": ident, "title": titulo, "languages": ["en"], "download_count": descargas,
            "authors": [{"name": autor, "birth_year": nac, "death_year": mue}]}


def test_prellenado_de_gutenberg_solo_con_lo_que_gutendex_afirma(tmp_path):
    import csv
    import verificar_gutenberg
    config = __import__("yaml").safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    poe = ("Poe, Edgar Allan", 1809, 1849)
    shelley = ("Shelley, Mary Wollstonecraft", 1797, 1851)
    respuestas = {
        "Edgar Allan Poe": [_libro(2147, "The Raven", *poe, descargas=500), _libro(17192, "The Raven", *poe, descargas=900),
                            _libro(1065, "The Black Cat", *poe),
                            _libro(99, "Poe Studies", "Smith, Jane", 1900, 1990)],  # otro autor: se descarta
        "Mary Shelley": [_libro(84, "Frankenstein; Or, The Modern Prometheus", *shelley, descargas=100),
                                        _libro(85, "The Last Man", *shelley), _libro(86, "The Last Man (illustrated)", *shelley)],
    }
    filas = {(f["pieza"] or f["autor"]): f for f in verificar_gutenberg.prellenar(config, buscar=lambda n: respuestas.get(n, []),
                                                                                  movimiento="romanticismo")}
    cuervo = filas["romanticismo-poe-01"]  # dos ediciones con el mismo título: la más descargada
    assert (cuervo["estado"], cuervo["id"]) == ("verificado", "17192") and "2 edición" in cuervo["nota"]
    assert filas["romanticismo-poe-05"]["id"] == "1065"
    assert filas["romanticismo-poe-02"]["estado"] == ""  # Annabel Lee: sin candidatos, en blanco
    # el filtro por movimiento deja fuera al resto
    assert filas["gotico-shelley-mary-01"]["estado"] == ""
    todas = verificar_gutenberg.prellenar(config, buscar=lambda n: respuestas.get(n, []), movimiento="gotico")
    f = {(x["pieza"] or x["autor"]): x for x in todas}
    assert f["gotico-shelley-mary-01"]["id"] == "84"
    assert f["gotico-shelley-mary-02"]["id"] == "84" and "tema de" in f["gotico-shelley-mary-02"]["nota"]  # tema «de» Frankenstein
    assert f["gotico-shelley-mary-06"]["estado"] == ""  # «The Last Man» y su ilustrada: títulos distintos → ambiguo
    assert "ambiguo" in f["gotico-shelley-mary-06"]["nota"]
    # el CSV resultante lo acepta gutenberg_manual.aplicar
    import shutil
    import gutenberg_manual
    ruta = tmp_path / "pre.csv"
    verificar_gutenberg.escribir_csv(todas, ruta)
    tax = tmp_path / "taxonomia.yaml"
    shutil.copy(RAIZ / "catalog" / "taxonomia.yaml", tax)
    assert gutenberg_manual.aplicar(ruta, tax) == []
    nueva = __import__("yaml").safe_load(tax.read_text(encoding="utf-8"))
    mary = next(a for m in nueva["movimientos"] if m["id"] == "gotico" for a in m["autores"] if a["id"] == "shelley-mary")
    assert mary["obras"][0]["gutenberg"] == {"id": 84, "estado": "verificado"}


def test_un_autor_vivo_o_reciente_sin_libros_queda_no_disponible():
    import verificar_gutenberg
    config = __import__("yaml").safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    filas = {(f["pieza"] or f["autor"]): f for f in verificar_gutenberg.prellenar(config, buscar=lambda n: [])}
    assert filas["realismo-magico/rulfo"]["estado"] == "no_disponible" and "1986" in filas["realismo-magico/rulfo"]["nota"]
    assert filas["romanticismo/poe"]["estado"] == ""  # murió en 1849: que no haya resultados no basta para decir no_disponible


def test_un_tema_de_una_obra_encuentra_su_obra_base():
    config = __import__("yaml").safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    mary = next(a for m in config["movimientos"] if m["id"] == "gotico" for a in m["autores"] if a["id"] == "shelley-mary")
    assert mary["obras"][modelo.obra_base(mary, "Frankenstein")]["id"] == "frankenstein-o-el-moderno-prometeo"
    stoker = next(a for m in config["movimientos"] if m["id"] == "gotico" for a in m["autores"] if a["id"] == "stoker")
    assert stoker["obras"][modelo.obra_base(stoker, "Drácula")]["id"] == "dracula"  # título igual: gana la exacta
    assert modelo.obra_base(stoker, "Una obra inexistente") is None
    # los temas «de» del catálogo tienen obra base salvo los sacados de una colección que no es una obra del catálogo
    sin_base = {(a["id"], o["de"]) for m in config["movimientos"] for a in m["autores"] for o in a["obras"]
                if o.get("de") and modelo.obra_base(a, o["de"]) is None}
    assert sin_base == {("galdos", "Episodios nacionales"), ("flaubert", "Tres cuentos")}
