"""Validaciones automáticas, detección de duplicados y hojas de contacto."""
import xml.etree.ElementTree as ET
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFont

from scene import ALTO, ANCHO

UMBRAL_DUPLICADO = 6.0  # diferencia media (0–255) bajo la cual dos piezas se consideran casi iguales
MAX_SVG_BYTES = 10 * 1024
MAX_JPG_BYTES = 150 * 1024
NS_SVG = "{http://www.w3.org/2000/svg}"
XMLNS = 'xmlns="http://www.w3.org/2000/svg"'


def validar_svg(ruta: Path):
    errores = []
    if ruta.stat().st_size > MAX_SVG_BYTES:
        errores.append(f"pesa más de {MAX_SVG_BYTES // 1024} KB")
    try:
        raiz = ET.parse(ruta).getroot()
    except ET.ParseError as e:
        return [f"XML inválido: {e}"]
    if raiz.tag != NS_SVG + "svg":
        errores.append("la raíz no es <svg>")
    if raiz.get("viewBox") != f"0 0 {ANCHO} {ALTO}":
        errores.append(f"viewBox incorrecto: {raiz.get('viewBox')}")
    if "http" in ruta.read_text(encoding="utf-8").replace(XMLNS, ""):
        errores.append("contiene una referencia externa")
    return errores


def validar_jpg(ruta: Path):
    errores = []
    if ruta.stat().st_size > MAX_JPG_BYTES:
        errores.append(f"pesa más de {MAX_JPG_BYTES // 1024} KB")
    with Image.open(ruta) as im:
        if im.format != "JPEG":
            errores.append(f"formato {im.format}, esperado JPEG")
        if im.size != (ANCHO, ALTO):
            errores.append(f"tamaño {im.size}, esperado {(ANCHO, ALTO)}")
        if im.info.get("exif"):
            errores.append("contiene metadatos EXIF")
    return errores


ESTADOS_GUTENBERG = {"sin_verificar", "verificado"}


def validar_taxonomia(config, motivos):
    """Coherencia de taxonomia.yaml con la biblioteca de motivos y el estado de Gutenberg."""
    usables = {m["id"] for m in motivos["motivos"] if m.get("implementado")}
    problemas, vistos = [], set()

    def revisar_gutenberg(donde, g):
        if g.get("estado") not in ESTADOS_GUTENBERG:
            problemas.append(f"{donde}: estado de Gutenberg inválido: {g.get('estado')!r}")
        elif (g["estado"] == "verificado") != (g.get("id") is not None):
            problemas.append(f"{donde}: 'verificado' exige id y un id exige 'verificado'")

    for mov in config["movimientos"]:
        for autor in mov["autores"]:
            base = f"{mov['id']}/{autor['id']}"
            revisar_gutenberg(base, autor["gutenberg_autor"])
            for n, obra in enumerate(autor.get("obras", []), 1):
                donde = f"{base}/{n:02d} {obra['id']}"
                if f"{base}/{obra['id']}" in vistos:
                    problemas.append(f"{donde}: obra repetida")
                vistos.add(f"{base}/{obra['id']}")
                if obra["motivo"] not in usables:
                    problemas.append(f"{donde}: motivo '{obra['motivo']}' no existe o no está implementado")
                revisar_gutenberg(donde, obra["gutenberg"])
    return problemas


def huella_visual(img: Image.Image):
    """Miniatura en gris 56×8 como vector, para comparar parecido visual."""
    miniatura = img.convert("L").resize((56, 8), Image.Resampling.BILINEAR)
    return np.asarray(miniatura, dtype=float).ravel()


def parecido_a(huella, otras: dict, umbral: float = UMBRAL_DUPLICADO):
    """(id, diferencia) de la pieza más parecida si cae bajo el umbral; si no, None."""
    if not otras:
        return None
    ids = list(otras)
    d = np.abs(np.stack([otras[i] for i in ids]) - huella).mean(axis=1)
    k = int(d.argmin())
    return (ids[k], float(d[k])) if d[k] < umbral else None


def revisar_duplicados(huellas: dict, umbral: float = UMBRAL_DUPLICADO):
    ids = list(huellas)
    if len(ids) < 2:
        return []
    M = np.stack([huellas[i] for i in ids])
    avisos = []
    for i in range(len(ids) - 1):
        d = np.abs(M[i + 1:] - M[i]).mean(axis=1)
        for j in np.nonzero(d < umbral)[0]:
            avisos.append(f"{ids[i]} ≈ {ids[i + 1 + j]} (diferencia {d[j]:.1f})")
    return avisos


def hoja_de_contacto(piezas, salida: Path, columnas: int = 2):
    """piezas: lista de (título, imagen). Una hoja por movimiento para revisión humana."""
    etiqueta, margen = 22, 12
    filas = (len(piezas) + columnas - 1) // columnas
    ancho = columnas * ANCHO + (columnas + 1) * margen
    alto = filas * (ALTO + etiqueta + margen) + margen
    hoja = Image.new("RGB", (ancho, alto), (244, 238, 225))
    dibujo = ImageDraw.Draw(hoja)
    fuente = ImageFont.load_default()
    for k, (titulo, img) in enumerate(piezas):
        x = margen + (k % columnas) * (ANCHO + margen)
        y = margen + (k // columnas) * (ALTO + etiqueta + margen)
        dibujo.text((x, y + 4), titulo, fill=(30, 42, 56), font=fuente)
        hoja.paste(img.convert("RGB"), (x, y + etiqueta))
    salida.parent.mkdir(parents=True, exist_ok=True)
    hoja.save(salida, "JPEG", quality=90)
