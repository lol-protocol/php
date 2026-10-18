"""Cálculo de color para generar-tabla.py: sRGB a CIELAB y diferencia de color CIEDE2000.

Solo biblioteca estándar de Python. La fórmula sigue las notas de implementación de
Sharma, Wu y Dalal (2005), "The CIEDE2000 color-difference formula: Implementation
notes, supplementary test data, and mathematical observations", Color Research &
Application 30(1), 21-30. VECTORES_SHARMA son pares de su tabla de datos de prueba;
autoprueba() los comprueba y generar-tabla.py la corre antes de calcular nada.
"""
import math

# Blanco de referencia D65 (2°) para pasar de XYZ a CIELAB.
_BLANCO = (0.95047, 1.0, 1.08883)

# (Lab 1, Lab 2, ΔE00 esperado a 4 decimales)
VECTORES_SHARMA = [
    ((50.0000, 2.6772, -79.7751), (50.0000, 0.0000, -82.7485), 2.0425),
    ((50.0000, 3.1571, -77.2803), (50.0000, 0.0000, -82.7485), 2.8615),
    ((50.0000, 2.8361, -74.0200), (50.0000, 0.0000, -82.7485), 3.4412),
    ((50.0000, -1.3802, -84.2814), (50.0000, 0.0000, -82.7485), 1.0000),
    ((50.0000, 0.0000, 0.0000), (50.0000, -1.0000, 2.0000), 2.3669),
    ((50.0000, 2.4900, -0.0010), (50.0000, -2.4900, 0.0009), 7.1792),
    ((50.0000, 2.5000, 0.0000), (50.0000, 0.0000, -2.5000), 4.3065),
    ((50.0000, 2.5000, 0.0000), (73.0000, 25.0000, -18.0000), 27.1492),
    ((50.0000, 2.5000, 0.0000), (61.0000, -5.0000, 29.0000), 22.8977),
    ((60.2574, -34.0099, 36.2677), (60.4626, -34.1751, 39.4387), 1.2644),
    ((63.0109, -31.0961, -5.8663), (62.8187, -29.7946, -4.0864), 1.2630),
    ((35.0831, -44.1164, 3.7933), (35.0232, -40.0716, 1.5901), 1.8645),
    ((22.7233, 20.0904, -46.6940), (23.0331, 14.9730, -42.5619), 2.0373),
    ((36.4612, 47.8580, 18.3852), (36.2715, 50.5065, 21.2231), 1.4146),
    ((90.8027, -2.0831, 1.4410), (91.1528, -1.6435, 0.0447), 1.4441),
    ((6.7747, -0.2908, -2.4247), (5.8714, -0.0985, -2.2286), 0.6377),
]


def hex_a_lab(color):
    """'#RRGGBB' (sRGB) a CIELAB (L, a, b) con iluminante D65."""
    r, g, b = (int(color[i:i + 2], 16) / 255 for i in (1, 3, 5))

    def lineal(c):
        return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4

    r, g, b = lineal(r), lineal(g), lineal(b)
    x = 0.4124564 * r + 0.3575761 * g + 0.1804375 * b
    y = 0.2126729 * r + 0.7151522 * g + 0.0721750 * b
    z = 0.0193339 * r + 0.1191920 * g + 0.9503041 * b

    def f(t):
        return t ** (1 / 3) if t > 216 / 24389 else (24389 / 27 * t + 16) / 116

    fx, fy, fz = f(x / _BLANCO[0]), f(y / _BLANCO[1]), f(z / _BLANCO[2])
    return 116 * fy - 16, 500 * (fx - fy), 200 * (fy - fz)


def ciede2000(lab1, lab2):
    """Diferencia de color CIEDE2000 (ΔE00) entre dos colores CIELAB."""
    l1, a1, b1 = lab1
    l2, a2, b2 = lab2
    c1, c2 = math.hypot(a1, b1), math.hypot(a2, b2)
    cm7 = ((c1 + c2) / 2) ** 7
    g = 0.5 * (1 - math.sqrt(cm7 / (cm7 + 25 ** 7)))
    a1p, a2p = (1 + g) * a1, (1 + g) * a2
    c1p, c2p = math.hypot(a1p, b1), math.hypot(a2p, b2)
    h1p = math.degrees(math.atan2(b1, a1p)) % 360 if c1p else 0.0
    h2p = math.degrees(math.atan2(b2, a2p)) % 360 if c2p else 0.0

    dl, dc = l2 - l1, c2p - c1p
    if c1p * c2p == 0:
        dh = 0.0
    else:
        dh = h2p - h1p
        if dh > 180:
            dh -= 360
        elif dh < -180:
            dh += 360
    dh_grande = 2 * math.sqrt(c1p * c2p) * math.sin(math.radians(dh / 2))

    lpm, cpm = (l1 + l2) / 2, (c1p + c2p) / 2
    if c1p * c2p == 0:
        hpm = h1p + h2p
    elif abs(h1p - h2p) <= 180:
        hpm = (h1p + h2p) / 2
    elif h1p + h2p < 360:
        hpm = (h1p + h2p + 360) / 2
    else:
        hpm = (h1p + h2p - 360) / 2

    t = (1 - 0.17 * math.cos(math.radians(hpm - 30)) + 0.24 * math.cos(math.radians(2 * hpm))
         + 0.32 * math.cos(math.radians(3 * hpm + 6)) - 0.20 * math.cos(math.radians(4 * hpm - 63)))
    dtheta = 30 * math.exp(-(((hpm - 275) / 25) ** 2))
    rc = 2 * math.sqrt(cpm ** 7 / (cpm ** 7 + 25 ** 7))
    sl = 1 + 0.015 * (lpm - 50) ** 2 / math.sqrt(20 + (lpm - 50) ** 2)
    sc = 1 + 0.045 * cpm
    sh = 1 + 0.015 * cpm * t
    rt = -math.sin(math.radians(2 * dtheta)) * rc
    x, y = dc / sc, dh_grande / sh
    return math.sqrt((dl / sl) ** 2 + x ** 2 + y ** 2 + rt * x * y)


def autoprueba():
    """Devuelve la lista de vectores de Sharma et al. que no coinciden (vacía si todo está bien)."""
    return [
        (lab1, lab2, esperado, round(ciede2000(lab1, lab2), 4))
        for lab1, lab2, esperado in VECTORES_SHARMA
        if abs(ciede2000(lab1, lab2) - esperado) > 1e-4
    ]
