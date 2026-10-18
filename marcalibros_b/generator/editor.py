"""Editor de marcalibros de usuario: subidas seguras y renderizado de recetas.

Dos piezas, pensadas para ejecutarse en el servidor (nunca en el navegador del usuario):

* `AlmacenSubidas.guardar(...)`: acepta solo JPG, PNG y WebP (por contenido, no por nombre ni
  tipo declarado), limita bytes y píxeles antes de decodificar, aplica la orientación EXIF,
  descarta metadatos y animación, y **recodifica siempre a JPEG** con un nombre aleatorio.
  Nunca se conserva el archivo original ni se acepta SVG.
* `renderizar_receta(receta, ...)`: convierte una receta (ver datos/diseno.schema.json) en una
  imagen 200×1400 (o 400×2800) con el mismo motor que el catálogo. El texto solo se pinta si
  llega a 4,5:1 de contraste; si no, se añade un velo translúcido en la franja.

Cómo servirlo (fuera de este módulo): los archivos de `AlmacenSubidas` se publican desde otro
origen (dominio sin cookies), solo como `<img>`, con `Content-Type: image/jpeg`,
`X-Content-Type-Options: nosniff` y `Content-Disposition: inline`.
"""
import io
import json
import os
import random
import secrets
from pathlib import Path

import yaml
from PIL import Image, ImageOps

import texto as tx
from plantillas import CONSTRUCTORES, NOMBRES_ARQUETIPOS, PALETAS
from qa import MIN_CONTRASTE_TEXTO, OPACIDADES_VELO, TONOS_TEXTO, contraste_texto, mejor_texto
from render_raster import ESCALA, _pintar_texto, a_imagen
from scene import ALTO, ANCHO, Escena

RAIZ = Path(__file__).resolve().parent.parent

MAX_BYTES = 5 * 1024 * 1024          # tamaño del archivo subido
MAX_PIXELES = 24_000_000             # ancho × alto, comprobado antes de decodificar
MAX_LADO = 8000
LADO_MAXIMO_GUARDADO = 4000          # se reduce al recodificar
CUOTA_IMAGENES = 50                  # por usuario
CUOTA_BYTES = 100 * 1024 * 1024      # por usuario, ya recodificado
FORMATOS = {"JPEG", "PNG", "WEBP"}
FRANJA = (16, 440, 184, 860)         # misma franja de texto que el catálogo (168×420)

Image.MAX_IMAGE_PIXELS = MAX_PIXELES  # Pillow avisa y falla ante bombas de descompresión


class ErrorEditor(ValueError):
    """Entrada rechazada; el mensaje se puede mostrar al usuario."""


def _firma(datos):
    if datos[:3] == b"\xff\xd8\xff":
        return "JPEG"
    if datos[:8] == b"\x89PNG\r\n\x1a\n":
        return "PNG"
    if datos[:4] == b"RIFF" and datos[8:12] == b"WEBP":
        return "WEBP"
    return None


def recodificar(datos):
    """Bytes subidos → bytes JPEG limpios. Lanza ErrorEditor si algo no cuadra."""
    if len(datos) > MAX_BYTES:
        raise ErrorEditor(f"el archivo pesa más de {MAX_BYTES // (1024 * 1024)} MB")
    firma = _firma(datos)
    if firma not in FORMATOS:
        raise ErrorEditor("solo se aceptan imágenes JPG, PNG o WebP")
    try:
        im = Image.open(io.BytesIO(datos))
        if im.format != firma:
            raise ErrorEditor("el contenido no coincide con su formato")
        ancho, alto = im.size
        if ancho * alto > MAX_PIXELES or max(ancho, alto) > MAX_LADO:
            raise ErrorEditor("la imagen es demasiado grande")
        if ancho < 50 or alto < 350:
            raise ErrorEditor("la imagen es demasiado pequeña (mínimo 50×350)")
        if getattr(im, "n_frames", 1) > 1:
            raise ErrorEditor("no se aceptan imágenes animadas")
        im.load()
    except ErrorEditor:
        raise
    except Exception as e:  # archivo corrupto, truncado o bomba de descompresión
        raise ErrorEditor("no se pudo leer la imagen") from e
    im = ImageOps.exif_transpose(im)  # aplica la orientación y descarta el resto de metadatos
    if im.mode in ("RGBA", "LA", "P"):
        im = im.convert("RGBA")
        fondo = Image.new("RGB", im.size, (255, 255, 255))
        fondo.paste(im, mask=im.getchannel("A"))
        im = fondo
    else:
        im = im.convert("RGB")
    im.thumbnail((LADO_MAXIMO_GUARDADO, LADO_MAXIMO_GUARDADO), Image.Resampling.LANCZOS)
    salida = io.BytesIO()
    im.save(salida, "JPEG", quality=88, optimize=True)  # sin exif, sin icc, sin comentarios
    return salida.getvalue()


class AlmacenSubidas:
    """Directorio de imágenes ya recodificadas, con nombre aleatorio y cuotas por usuario.
    El índice (`indice.json`) asocia cada id a su propietario y tamaño."""

    def __init__(self, directorio):
        self.dir = Path(directorio)
        self.dir.mkdir(parents=True, exist_ok=True)
        self._indice = self.dir / "indice.json"

    def _leer(self):
        return json.loads(self._indice.read_text(encoding="utf-8")) if self._indice.exists() else {}

    def uso(self, usuario):
        mias = [v for v in self._leer().values() if v["usuario"] == usuario]
        return len(mias), sum(v["bytes"] for v in mias)

    def guardar(self, usuario, datos):
        """Devuelve el id (32 hex) de la imagen guardada."""
        jpeg = recodificar(datos)
        n, usados = self.uso(usuario)
        if n >= CUOTA_IMAGENES:
            raise ErrorEditor(f"has llegado al máximo de {CUOTA_IMAGENES} imágenes")
        if usados + len(jpeg) > CUOTA_BYTES:
            raise ErrorEditor("has agotado tu espacio de imágenes")
        ident = secrets.token_hex(16)
        ruta = self.dir / f"{ident}.jpg"
        tmp = ruta.with_suffix(".tmp")
        tmp.write_bytes(jpeg)
        os.replace(tmp, ruta)
        indice = self._leer()
        indice[ident] = {"usuario": usuario, "bytes": len(jpeg)}
        self._indice.write_text(json.dumps(indice), encoding="utf-8")
        return ident

    def ruta(self, ident, usuario=None):
        """Ruta de una imagen. El id debe ser hexadecimal: nunca se compone una ruta con entrada libre."""
        if len(ident) != 32 or any(c not in "0123456789abcdef" for c in ident):
            raise ErrorEditor("imagen desconocida")
        info = self._leer().get(ident)
        if not info or (usuario is not None and info["usuario"] != usuario):
            raise ErrorEditor("imagen desconocida")
        return self.dir / f"{ident}.jpg"

    def borrar(self, ident, usuario):
        ruta = self.ruta(ident, usuario)
        ruta.unlink(missing_ok=True)
        indice = self._leer()
        indice.pop(ident, None)
        self._indice.write_text(json.dumps(indice), encoding="utf-8")


# --------------------------------------------------------------------------- recetas

def _motivos_validos():
    datos = yaml.safe_load((RAIZ / "catalog" / "motivos.yaml").read_text(encoding="utf-8"))
    return {m["id"] for m in datos["motivos"]}


def _fondo_generado(movimiento, paleta, arquetipo, motivo, semilla):
    if paleta not in PALETAS:
        raise ErrorEditor("paleta desconocida")
    if arquetipo not in NOMBRES_ARQUETIPOS:
        raise ErrorEditor("arquetipo desconocido")
    if motivo not in _motivos_validos():
        raise ErrorEditor("motivo desconocido")
    return CONSTRUCTORES[movimiento](semilla, paleta, motivo, 0.0, arquetipo, False)


def _fondo_de_pieza(pieza, manifiesto):
    e = next((x for x in manifiesto["entradas"] if x["id"] == pieza), None)
    if e is None:
        raise ErrorEditor("plantilla desconocida")
    return CONSTRUCTORES[e["movimiento"]](e["semilla"], e["paleta"], e["motivo"], 0.0, e["arquetipo"], False)


def _recorte_imagen(ruta, rec):
    im = Image.open(ruta).convert("RGB")
    x, y, w, h = rec["x"], rec["y"], rec["ancho"], rec["alto"]
    if x + w > im.width or y + h > im.height:
        raise ErrorEditor("el recorte se sale de la imagen")
    if abs(h - 7 * w) > 1:
        raise ErrorEditor("el recorte debe ser 1:7")
    return im.crop((x, y, x + w, y + h))


def renderizar_receta(receta, manifiesto, almacen=None, usuario=None, densidad=1):
    """Imagen PIL (200×1400 × densidad) de una receta ya validada con modelo.validar_diseno.
    Devuelve (imagen, info) con info = {"tono", "contraste", "velo"} si hay texto."""
    fondo = receta["fondo"]
    if fondo["tipo"] == "imagen":
        if almacen is None:
            raise ErrorEditor("hace falta el almacén de imágenes")
        base = _recorte_imagen(almacen.ruta(fondo["imagen"], usuario), fondo["recorte"])
        base = base.resize((ANCHO * densidad, ALTO * densidad), Image.Resampling.LANCZOS)
    else:
        esc = (_fondo_de_pieza(fondo["pieza"], manifiesto) if fondo["tipo"] == "plantilla" else
               _fondo_generado("romanticismo", fondo["paleta"], fondo["arquetipo"], fondo.get("motivo", "ninguno"),
                               fondo.get("semilla", 0)))
        base = a_imagen(esc, densidad)
    info = {}
    t = receta.get("texto")
    if t:
        x0, y0, x1, y1 = FRANJA
        caja = base.crop((x0 * densidad, y0 * densidad, x1 * densidad, y1 * densidad))
        tono = t.get("tono") or mejor_texto(caja)[0]
        ratio, velo = contraste_texto(caja, tono), 0.0
        if ratio < MIN_CONTRASTE_TEXTO:  # velo del color contrario hasta llegar al contraste
            contrario = TONOS_TEXTO["oscuro" if tono == "claro" else "claro"]
            for op in OPACIDADES_VELO:
                prueba = Image.blend(caja, Image.new("RGB", caja.size, contrario), op)
                velo, ratio = op, contraste_texto(prueba, tono)
                if ratio >= MIN_CONTRASTE_TEXTO:
                    break
            if velo:
                base.paste(Image.blend(caja, Image.new("RGB", caja.size, contrario), velo), (x0 * densidad, y0 * densidad))
        bloques, _ = tx.componer(t["frase"], t.get("autor", ""), FRANJA)
        if not t.get("autor"):
            bloques = bloques[:1]
        lienzo = _pintar_texto(base.convert("RGBA"), {"bloques": bloques, "color": TONOS_TEXTO[tono]}, densidad / ESCALA)
        base = lienzo.convert("RGB")
        info = {"tono": tono, "contraste": round(ratio, 2), "velo": velo}
    return base, info


def a_jpeg(img):
    salida = io.BytesIO()
    img.save(salida, "JPEG", quality=85, optimize=True)  # sin metadatos
    return salida.getvalue()
