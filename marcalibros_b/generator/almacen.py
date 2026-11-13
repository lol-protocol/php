"""Almacén de documentos JSON con exclusión y escritura atómica, compartido por las subidas y los diseños.

Un `Documentos` es un diccionario id → documento guardado en un archivo. `editar()` da el diccionario para
leer-modificar-escribir sin que otro proceso o hilo se cuele en medio (flock en Linux y macOS, msvcrt en
Windows; sin ninguno, al menos exclusión entre hilos). El bloqueo es por archivo y reentrante, así que una
operación puede tomar el de dos almacenes distintos sin riesgo de interbloqueo consigo misma.
"""
import contextlib
import json
import os
import threading
from pathlib import Path

try:  # exclusión entre procesos
    import fcntl
    msvcrt = None
except ImportError:
    fcntl = None
    try:
        import msvcrt
    except ImportError:
        msvcrt = None

_CERROJOS, _GUARDA = {}, threading.Lock()


def _cerrojo(ruta):
    with _GUARDA:
        return _CERROJOS.setdefault(str(ruta), threading.RLock())


@contextlib.contextmanager
def exclusion(ruta):
    """Bloqueo exclusivo sobre un archivo: entre procesos si el sistema lo permite, y siempre entre hilos."""
    with _cerrojo(ruta):
        with open(ruta, "a+b") as f:
            if fcntl:
                fcntl.flock(f, fcntl.LOCK_EX)
            elif msvcrt:
                f.seek(0)
                msvcrt.locking(f.fileno(), msvcrt.LK_LOCK, 1)
            try:
                yield
            finally:
                if fcntl:
                    fcntl.flock(f, fcntl.LOCK_UN)
                elif msvcrt:
                    f.seek(0)
                    msvcrt.locking(f.fileno(), msvcrt.LK_UNLCK, 1)


class Documentos:
    def __init__(self, ruta):
        self.ruta = Path(ruta)
        self.ruta.parent.mkdir(parents=True, exist_ok=True)

    def leer(self):
        return json.loads(self.ruta.read_text(encoding="utf-8")) if self.ruta.exists() else {}

    def escribir(self, datos):
        tmp = self.ruta.with_suffix(".tmp")
        tmp.write_text(json.dumps(datos, ensure_ascii=False), encoding="utf-8")
        os.replace(tmp, self.ruta)  # atómico: nunca queda un JSON a medias

    @contextlib.contextmanager
    def editar(self):
        """Diccionario para modificar bajo bloqueo; se escribe al salir sin error."""
        with exclusion(self.ruta.with_suffix(".lock")):
            datos = self.leer()
            yield datos
            self.escribir(datos)
