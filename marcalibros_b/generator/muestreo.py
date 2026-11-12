"""Muestreo de una escena sin rasterizarla.

Las decisiones del generador (¿se parece a otra pieza?, ¿se ve el motivo en miniatura?, ¿se lee el texto?)
se toman aquí, calculando el color de la escena en puntos concretos con aritmética simple (comparaciones,
interpolación lineal y cruces de rayo). No usa Pillow: el antialiasing, el remuestreo y el grano de un
rasterizador cambian entre versiones y, con ellos, qué semilla sale en cada pieza. Con este muestreo la
elección solo depende de la geometría de la escena.
"""
import numpy as np

from color import rgb
from scene import ALTO, ANCHO

PASO_HUELLA = 5  # separación de las muestras dentro de cada celda de 25 px de la huella
PASO_MINIATURA = 4  # una muestra por píxel de la miniatura de 50×350
PASO_FRANJA = 4  # una muestra por píxel de la miniatura de la franja de texto


def _dentro(px, py, puntos):
    """Máscara de los puntos dentro de un polígono (regla par-impar, como el relleno de los renderers)."""
    xs, ys = [p[0] for p in puntos], [p[1] for p in puntos]
    caja = (px >= min(xs)) & (px <= max(xs)) & (py >= min(ys)) & (py <= max(ys))
    idx = np.nonzero(caja)[0]
    salida = np.zeros(px.shape, dtype=bool)
    if idx.size == 0:
        return salida
    x, y = px[idx], py[idx]
    par = np.zeros(idx.size, dtype=bool)
    n = len(puntos)
    for i in range(n):
        (x0, y0), (x1, y1) = puntos[i], puntos[(i + 1) % n]
        if y0 == y1:
            continue
        par ^= ((y0 > y) != (y1 > y)) & (x < (x1 - x0) * (y - y0) / (y1 - y0) + x0)
    salida[idx] = par
    return salida


def colores(esc, xs, ys):
    """Color (r, g, b) de la escena en cada punto (xs[i], ys[i]), pintando las primitivas en orden.
    El texto no cuenta: se añade a la pieza después de las decisiones."""
    xs, ys = np.asarray(xs, dtype=float), np.asarray(ys, dtype=float)
    col = None
    for prim in esc.primitivas:
        tipo = prim["tipo"]
        if tipo == "degradado":
            pos = [0.0] + [p for p, _ in prim.get("paradas", ())] + [1.0]
            cols = np.array([rgb(prim["arriba"])] + [rgb(c) for _, c in prim.get("paradas", ())] + [rgb(prim["abajo"])], dtype=float)
            t = ys / ALTO
            col = np.stack([np.interp(t, pos, cols[:, k]) for k in range(3)], axis=1)
        elif col is None:
            raise ValueError("la escena necesita un degradado de fondo")
        elif tipo == "circulo":
            m = (xs - prim["cx"]) ** 2 + (ys - prim["cy"]) ** 2 <= prim["r"] ** 2
            col[m] = rgb(prim["color"])
        elif tipo == "poligono":
            col[_dentro(xs, ys, prim["puntos"])] = rgb(prim["color"])
        elif tipo == "velo":
            m = (xs >= prim["x0"]) & (xs < prim["x1"]) & (ys >= prim["y0"]) & (ys < prim["y1"])
            col[m] = col[m] * (1 - prim["opacidad"]) + np.array(rgb(prim["color"]), dtype=float) * prim["opacidad"]
    return col


def _rejilla(x0, y0, x1, y1, paso):
    """Centros de una rejilla regular sobre [x0, x1) × [y0, y1), como arrays planos (fila a fila)."""
    gx = x0 + (np.arange(int(round((x1 - x0) / paso))) + 0.5) * paso
    gy = y0 + (np.arange(int(round((y1 - y0) / paso))) + 0.5) * paso
    X, Y = np.meshgrid(gx, gy)
    return X.ravel(), Y.ravel(), (gy.size, gx.size)


def huella(esc):
    """Miniatura en color de 8×56 como vector, estirada a 0–255 (ver qa.estirar_huella)."""
    from qa import estirar_huella  # qa importa muestreo: se evita el ciclo
    x, y, (alto, ancho) = _rejilla(0, 0, ANCHO, ALTO, PASO_HUELLA)
    c = colores(esc, x, y).reshape(alto, ancho, 3)
    k = 25 // PASO_HUELLA  # muestras por celda en cada eje
    celdas = c.reshape(alto // k, k, ancho // k, k, 3).mean(axis=(1, 3))
    return estirar_huella(celdas.ravel())


def pixeles_de_motivo(esc, sin_motivo, umbral_cambio):
    """Puntos de la miniatura de 50×350 cuyo color cambia más de `umbral_cambio` (en algún canal) al quitar el motivo."""
    x, y, _ = _rejilla(0, 0, ANCHO, ALTO, PASO_MINIATURA)
    d = np.abs(colores(esc, x, y) - colores(sin_motivo, x, y))
    return int((d.max(axis=1) > umbral_cambio).sum())


def franja_colores(esc, margen=10):
    """Colores muestreados en la franja de texto, sin el margen (las esquinas redondeadas del velo). Array (n, 3)."""
    x0, y0, x1, y1 = esc.franja
    x, y, _ = _rejilla(x0 + margen, y0 + margen, x1 - margen, y1 - margen, PASO_FRANJA)
    return colores(esc, x, y)
