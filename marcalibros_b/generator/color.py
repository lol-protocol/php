"""Color: conversión, luminancia y contraste WCAG, una sola vez para el generador, el QA, la marca y el editor."""
import numpy as np


def rgb(hex_color):
    """(r, g, b) enteros de un color #RRGGBB."""
    h = hex_color.lstrip("#")
    return tuple(int(h[i:i + 2], 16) for i in (0, 2, 4))


def _lineal(v):
    """Canal sRGB 0–1 a lineal; sirve para un número y para un array de numpy."""
    v = np.asarray(v, dtype=float)
    return np.where(v <= 0.03928, v / 12.92, ((v + 0.055) / 1.055) ** 2.4)


def luminancia_rgb(a):
    """Luminancia relativa WCAG de un array (..., 3) con canales 0–255."""
    c = _lineal(np.asarray(a, dtype=float) / 255)
    return 0.2126 * c[..., 0] + 0.7152 * c[..., 1] + 0.0722 * c[..., 2]


def luminancia(hex_color):
    """Luminancia relativa WCAG de un color #RRGGBB."""
    return float(luminancia_rgb(np.array(rgb(hex_color))))


def razon(l1, l2):
    """Razón de contraste de dos luminancias; con arrays, elemento a elemento."""
    hi, lo = np.maximum(l1, l2), np.minimum(l1, l2)
    return (hi + 0.05) / (lo + 0.05)


def contraste(a, b):
    """Razón de contraste WCAG entre dos colores #RRGGBB."""
    return float(razon(luminancia(a), luminancia(b)))


def mezclar(a, b, t):
    """Mezcla lineal de dos colores #RRGGBB: a con t = 0, b con t = 1."""
    ca, cb = rgb(a), rgb(b)
    return "#" + "".join(f"{round(x + (y - x) * t):02X}" for x, y in zip(ca, cb))
