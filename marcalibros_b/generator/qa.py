"""Validaciones automáticas, detección de duplicados y hojas de contacto."""
import xml.etree.ElementTree as ET
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw, ImageFont

from scene import ALTO, ANCHO

UMBRAL_DUPLICADO = 6.0  # diferencia media (0–255) bajo la cual dos piezas se consideran casi iguales
MAX_SVG_BYTES = 20 * 1024  # 10 KB sin frase; con frase, los contornos de los glifos añaden unos 5 KB
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


# no_disponible: la obra no está en Gutenberg (p. ej. autor bajo derechos de autor). Como
# `verificado`, solo se marca tras comprobarlo; mientras tanto, sin_verificar.
ESTADOS_GUTENBERG = {"sin_verificar", "verificado", "no_disponible"}


def validar_taxonomia(config, motivos, animos, escenas, paletas):
    """Coherencia de taxonomia.yaml con motivos, ánimos, escenas y estado de Gutenberg."""
    biblioteca = {m["id"]: m for m in motivos["motivos"] if m.get("implementado")}
    problemas, vistos = [], set()

    def revisar_gutenberg(donde, g):
        if g.get("estado") not in ESTADOS_GUTENBERG:
            problemas.append(f"{donde}: estado de Gutenberg inválido: {g.get('estado')!r}")
        elif g["estado"] == "no_disponible" and g.get("id") is not None:
            problemas.append(f"{donde}: 'no_disponible' no puede llevar id")
        elif g["estado"] != "no_disponible" and (g["estado"] == "verificado") != (g.get("id") is not None):
            problemas.append(f"{donde}: 'verificado' exige id y un id exige 'verificado'")

    for nombre, lista in animos.items():
        for p in lista:
            if p not in paletas:
                problemas.append(f"ánimo {nombre}: paleta '{p}' no existe")

    for mov in config["movimientos"]:
        for p in mov["paletas"]:
            if p not in paletas:
                problemas.append(f"{mov['id']}: paleta '{p}' no existe")
        for autor in mov["autores"]:
            base = f"{mov['id']}/{autor['id']}"
            revisar_gutenberg(base, autor["gutenberg_autor"])
            for n, obra in enumerate(autor.get("obras", []), 1):
                donde = f"{base}/{n:02d} {obra['id']}"
                if f"{base}/{obra['id']}" in vistos:
                    problemas.append(f"{donde}: obra repetida")
                vistos.add(f"{base}/{obra['id']}")
                motivo = biblioteca.get(obra["motivo"])
                if motivo is None:
                    problemas.append(f"{donde}: motivo '{obra['motivo']}' no existe o no está implementado")
                if obra.get("animo") not in animos:
                    problemas.append(f"{donde}: ánimo '{obra.get('animo')}' no existe")
                if obra.get("escena") and obra["escena"] not in escenas:
                    problemas.append(f"{donde}: escena '{obra['escena']}' no existe")
                req = (motivo or {}).get("requiere", {})
                if "animos" in req and obra.get("animo") not in req["animos"]:
                    problemas.append(f"{donde}: el motivo '{obra['motivo']}' exige ánimo {req['animos']}")
                if "escena" in req and obra.get("escena") != req["escena"]:
                    problemas.append(f"{donde}: el motivo '{obra['motivo']}' exige escena '{req['escena']}'")
                revisar_gutenberg(donde, obra["gutenberg"])
    return problemas


ESCALA_MINIATURA = 0.25  # tamaño de lectura aproximado: 50×350
MIN_PIXELES_MOTIVO = 30  # píxeles de la miniatura que el motivo debe cambiar
UMBRAL_CAMBIO = 40  # diferencia por canal (0–255) para contar un píxel como cambiado
# Blanco y casi negro: con los tonos crema y azul de marca hacían falta 121 velos más.
TONOS_TEXTO = {"claro": "#FFFFFF", "oscuro": "#111111"}
MIN_CONTRASTE_TEXTO = 4.5  # texto normal WCAG AA, medido solo dentro de la franja reservada
OPACIDADES_VELO = (0.35, 0.5, 0.65, 0.8)


def pixeles_de_motivo(con, sin):
    """Píxeles que cambia el motivo en una miniatura: compara la misma escena dibujada
    con y sin él. Un motivo con pocos píxeles se pierde al reducir el marcalibros."""
    d = np.abs(np.asarray(con.convert("RGB"), dtype=int) - np.asarray(sin.convert("RGB"), dtype=int))
    return int((d.max(axis=2) > UMBRAL_CAMBIO).sum())


def _luminancias(img):
    a = np.asarray(img.convert("RGB"), dtype=float) / 255
    a = np.where(a <= 0.03928, a / 12.92, ((a + 0.055) / 1.055) ** 2.4)
    return 0.2126 * a[..., 0] + 0.7152 * a[..., 1] + 0.0722 * a[..., 2]


def contraste_texto(img, tono):
    """Contraste que alcanza el 90 % de los píxeles (percentil 10) de `img` con el tono de
    texto dado. Se usa el percentil, no la media: un solo tono medio ya vuelve ilegible
    el texto en esa zona."""
    lum = _luminancias(img)
    h = TONOS_TEXTO[tono].lstrip("#")
    c = [int(h[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    c = [v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4 for v in c]
    lt = 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]
    hi, lo = np.maximum(lum, lt), np.minimum(lum, lt)
    return float(np.percentile((hi + 0.05) / (lo + 0.05), 10))


def mejor_texto(img):
    """(tono, contraste) del tono de texto, 'claro' u 'oscuro', que mejor se lee sobre `img`."""
    return max(((n, contraste_texto(img, n)) for n in TONOS_TEXTO), key=lambda t: t[1])


def hoja_de_miniaturas(piezas, salida: Path, columnas: int = 20):
    """Tamaño real de lectura (50×350): para juzgar a ojo qué sobrevive al reducir.
    piezas: lista de (etiqueta corta, imagen)."""
    margen, etiqueta = 8, 14
    ancho_i, alto_i = piezas[0][1].size
    filas = (len(piezas) + columnas - 1) // columnas
    hoja = Image.new("RGB", (columnas * (ancho_i + margen) + margen, filas * (alto_i + etiqueta + margen) + margen),
                     (244, 238, 225))
    dibujo = ImageDraw.Draw(hoja)
    try:
        fuente = ImageFont.truetype("DejaVuSans.ttf", 9)
    except OSError:
        fuente = ImageFont.load_default()
    for k, (titulo, img) in enumerate(piezas):
        x = margen + (k % columnas) * (ancho_i + margen)
        y = margen + (k // columnas) * (alto_i + etiqueta + margen)
        dibujo.text((x, y), titulo, fill=(30, 42, 56), font=fuente)
        hoja.paste(img.convert("RGB"), (x, y + etiqueta))
    salida.parent.mkdir(parents=True, exist_ok=True)
    hoja.save(salida, "JPEG", quality=90)


MUERTE_MAXIMA = 1955  # vida + 70 años: dominio público en 2026


def validar_frases(datos, soporta):
    """Frases: autores en dominio público, caracteres dibujables y estado válido."""
    problemas = []
    for mov, lista in datos["movimientos"].items():
        for q in lista:
            donde = f"{mov}: «{q['texto'][:32]}»"
            if q["muerte"] > MUERTE_MAXIMA:
                problemas.append(f"{donde}: {q['autor']} murió en {q['muerte']}; sigue con derechos")
            if q["estado"] not in ("sin_verificar", "verificada"):
                problemas.append(f"{donde}: estado inválido {q['estado']!r}")
            falta = sorted({c for c in q["texto"] + q["autor"] + "—" if not soporta(c)})
            if falta:
                problemas.append(f"{donde}: la fuente no tiene {falta}")
    return problemas


def huella_visual(img: Image.Image):
    """Miniatura en color 8×56 como vector, para comparar parecido visual.
    Con gris, las piezas oscuras de paletas distintas se confunden entre sí."""
    miniatura = img.convert("RGB").resize((8, 56), Image.Resampling.BILINEAR)
    v = np.asarray(miniatura, dtype=float).ravel()
    # Se estira el rango a 0–255: en paletas oscuras todas las capas caen en pocos
    # valores y, sin esto, dos composiciones distintas parecerían iguales.
    return (v - v.min()) * (255.0 / max(v.max() - v.min(), 1.0))


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


def hoja_de_contacto(piezas, salida: Path, columnas: int = 10, escala: float = 0.4):
    """piezas: lista de (título, imagen completa). Una hoja por autor para revisión humana:
    las piezas en fila (numeradas) y, debajo, la leyenda con el título de cada una."""
    margen, etiqueta, linea = 12, 18, 16
    ancho_i, alto_i = round(ANCHO * escala), round(ALTO * escala)
    filas = (len(piezas) + columnas - 1) // columnas
    leyenda = (len(piezas) + 1) // 2
    ancho = columnas * (ancho_i + margen) + margen
    alto = filas * (alto_i + etiqueta + margen) + margen + leyenda * linea + margen
    hoja = Image.new("RGB", (ancho, alto), (244, 238, 225))
    dibujo = ImageDraw.Draw(hoja)
    try:  # la fuente por defecto de Pillow no dibuja bien las tildes
        fuente = ImageFont.truetype("DejaVuSans.ttf", 12)
    except OSError:
        fuente = ImageFont.load_default()
    for k, (titulo, img) in enumerate(piezas):
        x = margen + (k % columnas) * (ancho_i + margen)
        y = margen + (k // columnas) * (alto_i + etiqueta + margen)
        dibujo.text((x + ancho_i // 2 - 6, y), f"{k + 1:02d}", fill=(30, 42, 56), font=fuente)
        hoja.paste(img.convert("RGB").resize((ancho_i, alto_i), Image.Resampling.LANCZOS), (x, y + etiqueta))
        col, fila = divmod(k, leyenda)
        dibujo.text((margen + col * ancho // 2, filas * (alto_i + etiqueta + margen) + margen + fila * linea),
                    f"{k + 1:02d}  {titulo}", fill=(30, 42, 56), font=fuente)
    salida.parent.mkdir(parents=True, exist_ok=True)
    hoja.save(salida, "JPEG", quality=90)
