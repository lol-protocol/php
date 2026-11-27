"""Generación en paralelo: el bloqueo de los archivos compartidos y el orquestador (rápidas, sin generar piezas)."""
import os
import subprocess
import sys
import threading
import time
from pathlib import Path

import pytest

RAIZ = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(RAIZ / "generator"))

import build  # noqa: E402
import build_paralelo  # noqa: E402


@pytest.fixture
def catalogo(tmp_path, monkeypatch):
    monkeypatch.setattr(build, "CATALOGO", tmp_path)
    return tmp_path


def _pieza(movimiento, n):
    return {"clave": f"k{n}", "huella": "h", "entrada": {"movimiento": movimiento, "id": f"{movimiento}-{n}"}}


def test_la_cache_mezclada_no_pisa_los_otros_movimientos(catalogo):
    build.guardar_cache({"a-1": _pieza("a", 1), "a-2": _pieza("a", 2), "b-1": _pieza("b", 1)})
    build.guardar_cache_mezclando({"a-3": _pieza("a", 3)}, {"a"})  # a se regenera: a-1 y a-2 ya no valen
    assert sorted(build.cargar_cache()) == ["a-3", "b-1"]
    build.guardar_cache_mezclando({"b-2": _pieza("b", 2)}, {"b"})  # otro proceso, otro movimiento
    assert sorted(build.cargar_cache()) == ["a-3", "b-2"]


def test_el_bloqueo_evita_que_dos_hilos_se_pisen(catalogo):
    contador = catalogo / "contador.txt"
    contador.write_text("0")

    def sumar():
        for _ in range(15):
            with build._bloqueo(espera=0.001):
                n = int(contador.read_text())  # leer-modificar-escribir: sin bloqueo se pierden sumas
                time.sleep(0.0005)
                contador.write_text(str(n + 1))

    hilos = [threading.Thread(target=sumar) for _ in range(6)]
    [h.start() for h in hilos]
    [h.join() for h in hilos]
    assert int(contador.read_text()) == 90
    assert not (catalogo / ".cache" / "catalogo.lock").exists()  # se libera siempre


def test_un_bloqueo_viejo_de_un_proceso_caido_se_retira(catalogo):
    lock = catalogo / ".cache" / "catalogo.lock"
    lock.parent.mkdir(parents=True)
    lock.write_text("")
    viejo = time.time() - 3600
    os.utime(lock, (viejo, viejo))
    with build._bloqueo(espera=0.001, caduca=60):
        assert lock.exists()  # ahora es el nuestro
    assert not lock.exists()


def test_las_ordenes_son_una_por_movimiento():
    ids = build_paralelo.movimientos()
    assert len(ids) == 15 and ids[0] == "romanticismo"
    ordenes = build_paralelo.ordenes(ids)
    assert all(o[-2:] == ["--movimiento", i] for i, o in ordenes.items())
    assert "--forzar" in build_paralelo.ordenes(["gotico"], forzar=True)["gotico"]
    assert "--forzar" not in ordenes["gotico"]


def test_si_un_movimiento_falla_el_orquestador_devuelve_error(monkeypatch, capsys):
    def falso(orden, **_):
        return subprocess.CompletedProcess(orden, 1 if orden[-1] == "gotico" else 0, stdout="salida", stderr="")

    monkeypatch.setattr(build_paralelo.subprocess, "run", falso)
    assert build_paralelo.main(["--procesos", "3"]) == 1
    salida = capsys.readouterr().out
    assert "con errores: gotico" in salida and "14 de 15" in salida
    monkeypatch.setattr(build_paralelo.subprocess, "run", lambda o, **_: subprocess.CompletedProcess(o, 0, "", ""))
    assert build_paralelo.main(["--procesos", "3"]) == 0


def test_leer_la_cache_mientras_otro_proceso_la_escribe_nunca_falla(catalogo):
    """Regresión de la CI: un proceso que arrancaba leía piezas.json justo cuando otro lo reescribía y veía el
    archivo vacío (JSONDecodeError). La escritura es atómica: se lee siempre el contenido viejo o el nuevo."""
    grande = {f"m-{n}": _pieza("m", n) for n in range(4000)}
    build.guardar_cache(grande)
    fin = threading.Event()
    errores = []

    def escribir():
        while not fin.is_set():
            build.guardar_cache(grande)

    def leer():
        for _ in range(300):
            try:
                assert len(build.cargar_cache()) == 4000
            except Exception as e:  # noqa: BLE001
                errores.append(repr(e))
                break

    escritor = threading.Thread(target=escribir)
    escritor.start()
    leer()
    fin.set()
    escritor.join()
    assert errores == []
    assert not list(catalogo.glob(".cache/*.tmp"))  # no quedan temporales
