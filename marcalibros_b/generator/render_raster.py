"""Renderer raster: convierte una Escena en una imagen de 200×1400.

Dibuja al doble de resolución y reduce con LANCZOS para suavizar bordes.
Las variantes con grano añaden textura de papel, algo que el SVG no hace bien
y por eso esas piezas van en JPG.
"""
import numpy as np
from PIL import Image, ImageDraw

from scene import ALTO, ANCHO

ESCALA = 2


def _rgb(hex_color):
    h = hex_color.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def _degradado(arriba, abajo, w, h):
    a = np.array(_rgb(arriba), dtype=float)
    b = np.array(_rgb(abajo), dtype=float)
    t = np.linspace(0, 1, h)[:, None, None]
    fila = a + (b - a) * t  # (h, 1, 3)
    return np.broadcast_to(fila, (h, w, 3)).copy()


def a_imagen(esc, escala=1.0):
    """Imagen de la escena. Con `escala` menor que 1 devuelve una miniatura dibujada
    directamente a esa resolución (mucho más barata que reducir la imagen completa)."""
    f = escala
    w, h = round(ANCHO * ESCALA * f), round(ALTO * ESCALA * f)
    base = None
    for prim in esc.primitivas:
        if prim["tipo"] == "degradado":
            base = _degradado(prim["arriba"], prim["abajo"], w, h)
            break
    if base is None:
        raise ValueError("la escena necesita un degradado de fondo")

    lienzo = Image.fromarray(base.astype(np.uint8)).convert("RGBA")
    dibujo = ImageDraw.Draw(lienzo)
    for prim in esc.primitivas:
        tipo = prim["tipo"]
        if tipo == "circulo":
            cx, cy, r = prim["cx"] * ESCALA * f, prim["cy"] * ESCALA * f, prim["r"] * ESCALA * f
            dibujo.ellipse([cx - r, cy - r, cx + r, cy + r], fill=_rgb(prim["color"]))
        elif tipo == "velo":
            capa = Image.new("RGBA", lienzo.size, (0, 0, 0, 0))
            e = ESCALA * f
            # Pillow incluye la última coordenada: se resta un píxel para que una línea de
            # 1 px mida 1 px, como en el SVG (si no, los filetes finos salen más gruesos).
            ImageDraw.Draw(capa).rounded_rectangle(
                [prim["x0"] * e, prim["y0"] * e, max(prim["x0"] * e, prim["x1"] * e - 1), max(prim["y0"] * e, prim["y1"] * e - 1)],
                radius=prim["radio"] * ESCALA * f, fill=_rgb(prim["color"]) + (round(255 * prim["opacidad"]),))
            lienzo = Image.alpha_composite(lienzo, capa)
            dibujo = ImageDraw.Draw(lienzo)
        elif tipo == "poligono":
            dibujo.polygon([(x * ESCALA * f, y * ESCALA * f) for x, y in prim["puntos"]], fill=_rgb(prim["color"]))

    img = lienzo.convert("RGB").resize((round(ANCHO * f), round(ALTO * f)), Image.Resampling.LANCZOS)

    if esc.grano and escala == 1.0:
        rng = np.random.default_rng(esc.semilla)
        arr = np.asarray(img, dtype=float)
        arr += rng.normal(0, esc.grano * 255, (ALTO, ANCHO))[..., None]
        img = Image.fromarray(np.clip(arr, 0, 255).astype(np.uint8))
    return img
