"""Moderación de los diseños de usuario y borrado de imágenes en cascada.

Un diseño nace `privado`. Para compartirlo, el dueño lo pide (`pedir_publicacion`): las reglas automáticas
(`evaluar`) lo rechazan, lo aprueban o lo mandan a la cola de revisión humana (`cola`, `aprobar`, `rechazar`).
Solo `aprobar` lo deja `compartido`, y rechazarlo lo devuelve a `privado` con el motivo. Los diseños no se
editan: si se corrige algo, se crea otro.

Las imágenes subidas pueden estar en uso por diseños (propios o compartidos): `borrar_imagen` avisa de cuáles
antes de borrar y, con `forzar`, los borra también (las marcas guardadas en un texto no se tocan: solo
referencian el diseño por id, y `resolver_diseno` las resuelve a un diseño existente o a ninguno).
"""
import re
from datetime import datetime, timezone
from functools import lru_cache
from pathlib import Path

import yaml

import modelo
from almacen import Documentos

RAIZ = Path(__file__).resolve().parent.parent


class ErrorModeracion(ValueError):
    """Operación no permitida; el mensaje se puede mostrar a quien la pide."""


@lru_cache(maxsize=1)
def reglas():
    return yaml.safe_load((RAIZ / "catalog" / "moderacion.yaml").read_text(encoding="utf-8"))


ENLACE = re.compile(r"(https?://|www\.|\b[\w-]+\.(com|net|org|es|io|co|mx|ar|cl|me|ly)\b)", re.I)
CORREO = re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+")
TELEFONO = re.compile(r"(?:\+?\d[\s().-]?){8,}")


def _ahora():
    return datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def revisar_texto(texto):
    """Motivos (ids de regla) por los que un texto del diseño levanta una bandera."""
    r, motivos = reglas(), []
    if ENLACE.search(CORREO.sub(" ", texto)):  # la parte de dominio de un correo no cuenta como enlace
        motivos.append("enlace")
    if CORREO.search(texto):
        motivos.append("correo")
    if TELEFONO.search(texto):
        motivos.append("telefono")
    plano = re.sub(r"\s+", " ", texto.lower())
    if any(n in plano for n in r["nombres_protegidos"]):
        motivos.append("suplantacion")
    if any(re.search(rf"\b{re.escape(p.lower())}\b", plano) for p in r["palabras_bloqueadas"]):
        motivos.append("palabra_bloqueada")
    letras = [c for c in texto if c.isalpha()]
    if len(letras) >= r["mayusculas_minimo"] and all(c.isupper() for c in letras):
        motivos.append("mayusculas")
    if re.search(r"(.)\1{%d,}" % r["repeticion_maxima"], texto):
        motivos.append("repeticion")
    return motivos


def evaluar(doc, clasificador=None):
    """Veredicto automático de un diseño de usuario: {"veredicto": aprobar | revision | rechazar, "motivos": [...]}.
    `clasificador(doc)` es un gancho opcional (p. ej. un modelo de imágenes) que devuelve motivos extra; sus
    resultados mandan a revisión humana, nunca aprueban ni rechazan solos."""
    r = reglas()
    receta = doc["receta"]
    motivos = []
    t = receta.get("texto")
    if t:
        for campo in (t["frase"], t.get("autor", "")):  # por separado: «Yo» no debe disimular una frase en mayúsculas
            motivos += revisar_texto(campo)
    if receta["fondo"]["tipo"] == "imagen":
        motivos.append("imagen_propia")
    motivos += [m for m in (clasificador(doc) if clasificador else []) if m not in motivos]
    motivos = list(dict.fromkeys(motivos))
    if any(m in r["duras"] for m in motivos):
        return {"veredicto": "rechazar", "motivos": motivos}
    if motivos:  # lo que no es una regla dura (blandas, imagen propia, gancho) lo decide una persona
        return {"veredicto": "revision", "motivos": motivos}
    return {"veredicto": "aprobar", "motivos": []}


class AlmacenDisenos:
    """Diseños de usuario guardados (id → documento), validados con el esquema al guardarlos."""

    def __init__(self, ruta, piezas=None):
        self._docs = Documentos(ruta)
        self.piezas = piezas  # ids del manifest, para validar las plantillas

    def guardar(self, doc):
        errores = modelo.validar_diseno(doc, self.piezas)
        if errores:
            raise ErrorModeracion("diseño no válido: " + "; ".join(errores))
        if doc["origen"] != "usuario":
            raise ErrorModeracion("solo se guardan diseños de usuario (los del catálogo vienen del manifest)")
        with self._docs.editar() as docs:
            docs[doc["id"]] = doc

    def obtener(self, ident):
        return self._docs.leer().get(ident)

    def todos(self):
        return list(self._docs.leer().values())

    def borrar(self, ident):
        with self._docs.editar() as docs:
            return docs.pop(ident, None) is not None

    def _cambiar(self, ident, cambios):
        with self._docs.editar() as docs:
            if ident not in docs:
                raise ErrorModeracion("diseño desconocido")
            nuevo = {**docs[ident], **cambios}
            errores = modelo.validar_diseno(nuevo, self.piezas)
            if errores:
                raise ErrorModeracion("el cambio deja el diseño no válido: " + "; ".join(errores))
            docs[ident] = nuevo
            return nuevo


def pedir_publicacion(almacen, ident, usuario, clasificador=None):
    """El dueño pide compartir un diseño privado. Devuelve el diseño y el veredicto de las reglas:
    rechazar → queda rechazado con los motivos; aprobar → queda compartido; revisión → pendiente de una persona."""
    doc = almacen.obtener(ident)
    if doc is None or doc["propietario"] != usuario:
        raise ErrorModeracion("diseño desconocido")
    if doc.get("moderacion") in ("pendiente", "aprobado"):
        raise ErrorModeracion("el diseño ya está " + ("pendiente de revisión" if doc["moderacion"] == "pendiente" else "compartido"))
    veredicto = evaluar(doc, clasificador)
    if veredicto["veredicto"] == "rechazar":
        nuevo = almacen._cambiar(ident, {"moderacion": "rechazado", "visibilidad": "privado", "revisado": _ahora(),
                                         "moderacion_motivo": ", ".join(veredicto["motivos"])})
    elif veredicto["veredicto"] == "aprobar":
        nuevo = almacen._cambiar(ident, {"moderacion": "aprobado", "visibilidad": "compartido", "revisado": _ahora()})
    else:
        nuevo = almacen._cambiar(ident, {"moderacion": "pendiente", "visibilidad": "privado",
                                         "moderacion_motivo": ", ".join(veredicto["motivos"])})
    return nuevo, veredicto


def cola(almacen):
    """Diseños pendientes de revisión humana, del más antiguo al más reciente."""
    return sorted((d for d in almacen.todos() if d.get("moderacion") == "pendiente"), key=lambda d: d["creado"])


def aprobar(almacen, ident):
    doc = almacen.obtener(ident)
    if doc is None or doc.get("moderacion") != "pendiente":
        raise ErrorModeracion("solo se aprueba un diseño pendiente de revisión")
    return almacen._cambiar(ident, {"moderacion": "aprobado", "visibilidad": "compartido", "revisado": _ahora()})


def rechazar(almacen, ident, motivo):
    doc = almacen.obtener(ident)
    if doc is None or doc.get("moderacion") != "pendiente":
        raise ErrorModeracion("solo se rechaza un diseño pendiente de revisión")
    if not motivo.strip():
        raise ErrorModeracion("hace falta un motivo para rechazar")
    return almacen._cambiar(ident, {"moderacion": "rechazado", "visibilidad": "privado", "revisado": _ahora(),
                                    "moderacion_motivo": motivo.strip()[:200]})


# --- Imágenes en uso y borrado en cascada ----------------------------------------------------------

def usos_de_imagen(almacen, imagen):
    """Diseños (de cualquier usuario) cuya receta usa esa imagen."""
    return [d for d in almacen.todos() if d["receta"]["fondo"].get("tipo") == "imagen" and d["receta"]["fondo"]["imagen"] == imagen]


def borrar_imagen(subidas, almacen, imagen, usuario, forzar=False):
    """Borra una imagen subida. Si hay diseños que la usan avisa de cuáles (ErrorModeracion con sus ids); con
    `forzar` los borra también. Devuelve los ids de los diseños borrados. Solo su dueño puede borrarla."""
    subidas.ruta(imagen, usuario)  # comprueba que existe y es suya (ErrorEditor si no)
    usos = usos_de_imagen(almacen, imagen)
    if usos and not forzar:
        ids = ", ".join(d["id"] for d in usos)
        compartidos = sum(1 for d in usos if d.get("visibilidad") == "compartido")
        raise ErrorModeracion(f"la imagen la usan {len(usos)} diseño(s) ({compartidos} compartido(s)): {ids}")
    for d in usos:
        almacen.borrar(d["id"])
    subidas.borrar(imagen, usuario)
    return [d["id"] for d in usos]


def resolver_diseno(ident, almacen, piezas):
    """Diseño al que apunta una marca: el de usuario guardado, la pieza del catálogo si existe, o None
    (el diseño se borró). La marca no cambia: quien la muestra elige qué enseñar cuando devuelve None."""
    if ident.startswith("cat:"):
        return {"id": ident, "origen": "catalogo", "version": 1, "pieza": ident[4:]} if ident[4:] in piezas else None
    return almacen.obtener(ident)
