"""Moderación de diseños de usuario y borrado de imágenes en cascada."""
import copy
import io
import json
import sys
from pathlib import Path

import pytest
from PIL import Image

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "generator"))
import editor  # noqa: E402
import moderacion as mod  # noqa: E402
import modelo  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
MANIFIESTO = json.loads((RAIZ / "catalog" / "manifest.json").read_text(encoding="utf-8"))
PIEZAS = {e["id"] for e in MANIFIESTO["entradas"]}


def _diseno(ident="ab" * 16, frase="Lee despacio.", autor="Yo", fondo=None, usuario="u1", creado="2026-10-18T09:30:00Z"):
    receta = {"fondo": fondo or {"tipo": "generado", "paleta": "noche", "arquetipo": "colinas"},
              "texto": {"frase": frase, "autor": autor}}
    return {"id": "usr:" + ident, "origen": "usuario", "version": 1, "propietario": usuario, "creado": creado, "receta": receta}


@pytest.fixture
def almacen(tmp_path):
    return mod.AlmacenDisenos(tmp_path / "disenos.json", PIEZAS)


def _jpeg():
    b = io.BytesIO()
    Image.new("RGB", (300, 800), (90, 120, 150)).save(b, "JPEG")
    return b.getvalue()


@pytest.mark.parametrize("texto,esperado", [
    ("Lee despacio.", []),
    ("Visita www.ejemplo.com", ["enlace"]),
    ("https://x.org/a", ["enlace"]),
    ("escríbeme a yo@correo.com", ["correo"]),
    ("llama al +34 612 345 678", ["telefono"]),
    ("una de MARCALIBROS", ["suplantacion"]),
    ("Plataforma  POS oficial", ["suplantacion"]),
    ("HOLA A TODOS LOS AMIGOS", ["mayusculas"]),
    ("holaaaaaaaaaaa", ["repeticion"]),
    ("Año 1984, página 12 y 15", []),  # números sueltos no son un teléfono
])
def test_reglas_de_texto(texto, esperado):
    assert mod.revisar_texto(texto) == esperado


def test_veredictos(almacen):
    assert mod.evaluar(_diseno()) == {"veredicto": "aprobar", "motivos": []}
    v = mod.evaluar(_diseno(frase="mira www.x.com"))
    assert v == {"veredicto": "rechazar", "motivos": ["enlace"]}
    v = mod.evaluar(_diseno(fondo={"tipo": "imagen", "imagen": "a" * 32, "recorte": {"x": 0, "y": 0, "ancho": 100, "alto": 700}}))
    assert v == {"veredicto": "revision", "motivos": ["imagen_propia"]}
    assert mod.evaluar(_diseno(frase="TODO EN MAYÚSCULAS AQUÍ"))["veredicto"] == "revision"
    # el gancho solo manda a revisión; no aprueba ni rechaza solo
    assert mod.evaluar(_diseno(), clasificador=lambda d: ["contenido_sensible"]) == {"veredicto": "revision", "motivos": ["contenido_sensible"]}
    assert mod.evaluar(_diseno(frase="www.x.com"), clasificador=lambda d: ["x"])["veredicto"] == "rechazar"


def test_el_almacen_valida_y_solo_guarda_disenos_de_usuario(almacen):
    almacen.guardar(_diseno())
    assert almacen.obtener("usr:" + "ab" * 16)["propietario"] == "u1"
    with pytest.raises(mod.ErrorModeracion, match="no válido"):
        almacen.guardar({**_diseno(), "html": "<b>"})
    with pytest.raises(mod.ErrorModeracion, match="solo se guardan"):
        almacen.guardar({"id": "cat:romanticismo-becquer-01", "origen": "catalogo", "version": 1, "pieza": "romanticismo-becquer-01"})


def test_publicacion_automatica_y_rechazo_automatico(almacen):
    almacen.guardar(_diseno("11" * 16))
    doc, v = mod.pedir_publicacion(almacen, "usr:" + "11" * 16, "u1")
    assert v["veredicto"] == "aprobar" and doc["visibilidad"] == "compartido" and doc["moderacion"] == "aprobado"
    almacen.guardar(_diseno("22" * 16, frase="escríbeme a yo@correo.com"))
    doc, v = mod.pedir_publicacion(almacen, "usr:" + "22" * 16, "u1")
    assert doc["moderacion"] == "rechazado" and doc["visibilidad"] == "privado" and doc["moderacion_motivo"] == "correo"
    assert modelo.validar_diseno(doc, PIEZAS) == []
    with pytest.raises(mod.ErrorModeracion, match="ya está"):
        mod.pedir_publicacion(almacen, "usr:" + "11" * 16, "u1")
    with pytest.raises(mod.ErrorModeracion, match="desconocido"):
        mod.pedir_publicacion(almacen, "usr:" + "11" * 16, "otra")  # no es suyo


def test_cola_de_revision_humana(almacen):
    img = {"tipo": "imagen", "imagen": "a" * 32, "recorte": {"x": 0, "y": 0, "ancho": 100, "alto": 700}}
    almacen.guardar(_diseno("33" * 16, fondo=img, creado="2026-10-18T10:00:00Z"))
    almacen.guardar(_diseno("44" * 16, fondo=img, creado="2026-10-18T09:00:00Z"))
    for n in ("33", "44"):
        doc, v = mod.pedir_publicacion(almacen, f"usr:{n * 16}", "u1")
        assert doc["moderacion"] == "pendiente" and doc["visibilidad"] == "privado" and v["veredicto"] == "revision"
    assert [d["id"][4:6] for d in mod.cola(almacen)] == ["44", "33"]  # el más antiguo primero
    aprobado = mod.aprobar(almacen, "usr:" + "44" * 16)
    assert aprobado["visibilidad"] == "compartido" and aprobado["moderacion"] == "aprobado" and "revisado" in aprobado
    with pytest.raises(mod.ErrorModeracion, match="motivo"):
        mod.rechazar(almacen, "usr:" + "33" * 16, "  ")
    r = mod.rechazar(almacen, "usr:" + "33" * 16, "Foto de una persona identificable")
    assert r["moderacion"] == "rechazado" and r["visibilidad"] == "privado" and modelo.validar_diseno(r, PIEZAS) == []
    assert mod.cola(almacen) == []
    with pytest.raises(mod.ErrorModeracion, match="pendiente"):
        mod.aprobar(almacen, "usr:" + "44" * 16)  # ya resuelto


def test_el_esquema_no_deja_compartir_sin_aprobar_ni_rechazar_sin_motivo():
    d = _diseno()
    assert modelo.validar_diseno({**d, "visibilidad": "compartido"}, PIEZAS)
    assert modelo.validar_diseno({**d, "visibilidad": "compartido", "moderacion": "pendiente"}, PIEZAS)
    assert modelo.validar_diseno({**d, "moderacion": "rechazado"}, PIEZAS)  # sin motivo
    assert modelo.validar_diseno({**d, "moderacion": "rechazado", "moderacion_motivo": "x", "visibilidad": "compartido"}, PIEZAS)
    assert modelo.validar_diseno({**d, "moderacion": "rechazado", "moderacion_motivo": "x"}, PIEZAS) == []
    catalogo = {"id": "cat:romanticismo-becquer-01", "origen": "catalogo", "version": 1, "pieza": "romanticismo-becquer-01"}
    assert modelo.validar_diseno({**catalogo, "moderacion_motivo": "x"}, PIEZAS)


def test_borrar_una_imagen_avisa_de_los_disenos_que_la_usan(tmp_path, almacen):
    subidas = editor.AlmacenSubidas(tmp_path / "subidas")
    img = subidas.guardar("u1", _jpeg())
    fondo = {"tipo": "imagen", "imagen": img, "recorte": {"x": 0, "y": 0, "ancho": 100, "alto": 700}}
    almacen.guardar(_diseno("55" * 16, fondo=fondo))
    almacen.guardar(_diseno("66" * 16, fondo=copy.deepcopy(fondo), usuario="u1"))
    almacen.guardar(_diseno("77" * 16))  # no la usa
    assert {d["id"][4:6] for d in mod.usos_de_imagen(almacen, img)} == {"55", "66"}
    with pytest.raises(mod.ErrorModeracion, match="2 diseño"):
        mod.borrar_imagen(subidas, almacen, img, "u1")
    assert subidas.ruta(img, "u1").exists() and len(almacen.todos()) == 3  # no se tocó nada
    with pytest.raises(editor.ErrorEditor):
        mod.borrar_imagen(subidas, almacen, img, "otro", forzar=True)  # no es suya
    borrados = mod.borrar_imagen(subidas, almacen, img, "u1", forzar=True)
    assert sorted(b[4:6] for b in borrados) == ["55", "66"]
    assert [d["id"][4:6] for d in almacen.todos()] == ["77"]
    with pytest.raises(editor.ErrorEditor):
        subidas.ruta(img, "u1")


def test_una_imagen_sin_usos_se_borra_sin_forzar(tmp_path, almacen):
    subidas = editor.AlmacenSubidas(tmp_path / "subidas")
    img = subidas.guardar("u1", _jpeg())
    assert mod.borrar_imagen(subidas, almacen, img, "u1") == []


def test_una_marca_resuelve_su_diseno_o_ninguno(almacen):
    almacen.guardar(_diseno("88" * 16))
    assert mod.resolver_diseno("usr:" + "88" * 16, almacen, PIEZAS)["propietario"] == "u1"
    assert mod.resolver_diseno("cat:romanticismo-becquer-01", almacen, PIEZAS)["pieza"] == "romanticismo-becquer-01"
    assert mod.resolver_diseno("usr:" + "99" * 16, almacen, PIEZAS) is None  # se borró
    assert mod.resolver_diseno("cat:no-existe-01", almacen, PIEZAS) is None
