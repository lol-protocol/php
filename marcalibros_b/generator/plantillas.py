"""Plantillas por movimiento literario.

Cada constructor recibe semilla, paleta y motivo, y devuelve una Escena.
Todo el azar sale de random.Random(semilla): misma entrada, misma salida.
"""
import math
import random

from scene import ALTO, ANCHO, Escena

PALETAS = {
    "atardecer": {
        "cielo_alto": "#1E2A38", "cielo_bajo": "#C98B3A", "sol": "#F4EEE1",
        "lejos": "#7A9CB0", "cerca": "#3E5C4A", "figura": "#1E2A38",
    },
    "amanecer": {
        "cielo_alto": "#7A9CB0", "cielo_bajo": "#F4EEE1", "sol": "#C98B3A",
        "lejos": "#A7BCC7", "cerca": "#3E5C4A", "figura": "#1E2A38",
    },
    "noche": {
        "cielo_alto": "#0F1822", "cielo_bajo": "#2B3D52", "sol": "#F4EEE1",
        "lejos": "#3A4D63", "cerca": "#16202B", "figura": "#080D13",
    },
    "niebla": {
        "cielo_alto": "#B9C4C6", "cielo_bajo": "#E8E6DD", "sol": "#FFFFFF",
        "lejos": "#9AA9AB", "cerca": "#5E7172", "figura": "#2F3C3D",
    },
    "otono": {
        "cielo_alto": "#5B3A29", "cielo_bajo": "#E3A857", "sol": "#FBE9C6",
        "lejos": "#A8683A", "cerca": "#4A2E1F", "figura": "#26150D",
    },
    "rosa": {
        "cielo_alto": "#4B2E4F", "cielo_bajo": "#E9A5A0", "sol": "#FFF1DC",
        "lejos": "#8C5A7A", "cerca": "#2E1B38", "figura": "#170D1E",
    },
    "esmeralda": {
        "cielo_alto": "#14332F", "cielo_bajo": "#9FC9A2", "sol": "#F6F1C9",
        "lejos": "#4F8C73", "cerca": "#1B4237", "figura": "#0B1F1A",
    },
    "medianoche": {
        "cielo_alto": "#120E2B", "cielo_bajo": "#4A3F7A", "sol": "#EDE7FF",
        "lejos": "#2E2A5E", "cerca": "#0F0B22", "figura": "#06040F",
    },
    "ceniza": {
        "cielo_alto": "#6E6A66", "cielo_bajo": "#D9D3C7", "sol": "#FBF7EE",
        "lejos": "#8F8A82", "cerca": "#4D4944", "figura": "#1F1D1A",
    },
    "dorada": {
        "cielo_alto": "#F2C879", "cielo_bajo": "#FBEFD5", "sol": "#D9772B",
        "lejos": "#C9A15E", "cerca": "#6B4E2A", "figura": "#2E1F0F",
    },
    "tempestad": {
        "cielo_alto": "#1B2B2A", "cielo_bajo": "#5E7F7A", "sol": "#E6F2EE",
        "lejos": "#36524F", "cerca": "#12201F", "figura": "#070F0E",
    },
    "cripta": {
        "cielo_alto": "#060A0D", "cielo_bajo": "#1F3A3A", "sol": "#D6EBDD",
        "lejos": "#14292A", "cerca": "#070F10", "figura": "#030708",
    },
    "sangre": {
        "cielo_alto": "#14040A", "cielo_bajo": "#7A1E2E", "sol": "#F2D8C9",
        "lejos": "#4A1220", "cerca": "#1A060C", "figura": "#0A0204",
    },
    "lunafria": {
        "cielo_alto": "#1B2740", "cielo_bajo": "#A9BCD6", "sol": "#F4F8FF",
        "lejos": "#5C7396", "cerca": "#1D2A44", "figura": "#0C1424",
    },
    "tropico": {
        "cielo_alto": "#0E5E5A", "cielo_bajo": "#F4C95D", "sol": "#FFF1C2",
        "lejos": "#2E9E73", "cerca": "#0B4D3A", "figura": "#062A21",
    },
    "cobre": {
        "cielo_alto": "#5A1E3B", "cielo_bajo": "#F28C4B", "sol": "#FFE9B8",
        "lejos": "#B5532F", "cerca": "#4A1F2A", "figura": "#230E15",
    },
    "polvo": {
        "cielo_alto": "#8A6A4A", "cielo_bajo": "#EBD5A8", "sol": "#FFF6DD",
        "lejos": "#B99468", "cerca": "#6B4A2E", "figura": "#2C1B0F",
    },
    "aguamarina": {
        "cielo_alto": "#0B3C5D", "cielo_bajo": "#8ED6CF", "sol": "#FFFBE6",
        "lejos": "#3A8FA0", "cerca": "#0F4B5E", "figura": "#06222E",
    },
    "tormenta": {
        "cielo_alto": "#2A2F3D", "cielo_bajo": "#6C7388", "sol": "#D8DCE6",
        "lejos": "#4B5265", "cerca": "#1D212C", "figura": "#0E1016",
    },
}


def luminancia(hex_color):
    """Luminancia relativa WCAG de un color #RRGGBB."""
    def canal(v):
        v /= 255
        return v / 12.92 if v <= 0.03928 else ((v + 0.055) / 1.055) ** 2.4
    h = hex_color.lstrip("#")
    r, g, b = (canal(int(h[i:i + 2], 16)) for i in (0, 2, 4))
    return 0.2126 * r + 0.7152 * g + 0.0722 * b


def contraste(a, b):
    la, lb = sorted((luminancia(a), luminancia(b)), reverse=True)
    return (la + 0.05) / (lb + 0.05)


MIN_CONTRASTE_FIGURA = 3.0


def paleta_legible(nombre):
    """Paleta con el color de las siluetas (`figura`) ajustado para que se distinga del
    cielo bajo y de la capa lejana, donde se apoyan los motivos. Si la figura oscura no
    llega a 3:1 (paletas nocturnas), las siluetas pasan al tono claro de la paleta."""
    p = dict(PALETAS[nombre])
    fondo = (p["cielo_bajo"], p["lejos"])
    peor = lambda c: min(contraste(c, f) for f in fondo)
    if peor(p["figura"]) < MIN_CONTRASTE_FIGURA and peor(p["sol"]) > peor(p["figura"]):
        p["figura"] = p["sol"]
    return p


def _colina(rng, base, amp):
    return {
        "base": (base + rng.nivel) * ALTO,
        "amp": amp,
        "fase": rng.uniform(0, 2 * math.pi),
        "frec": rng.uniform(0.8, 1.6),
    }


def _altura(c, x):
    t = 2 * math.pi * x / ANCHO
    return (c["base"]
            + c["amp"] * math.sin(c["frec"] * t + c["fase"])
            + 0.4 * c["amp"] * math.sin(2.3 * c["frec"] * t))


def _puntos_colina(c):
    puntos = [(x, round(_altura(c, x), 1)) for x in range(0, ANCHO + 1, 5)]
    return puntos + [(ANCHO, ALTO), (0, ALTO)]


def _torre(esc, rng, p, lejos):
    """Campanario de iglesia con cuerpo, campanario escalonado y chapitel."""
    x = rng.uniform(0.25, 0.75) * ANCHO
    y = lejos(x)
    esc.poligono([
        (x - 9, y), (x - 9, y - 50), (x - 12, y - 50), (x - 12, y - 64),
        (x - 4, y - 64), (x - 4, y - 72), (x, y - 86), (x + 4, y - 72),
        (x + 4, y - 64), (x + 12, y - 64), (x + 12, y - 50), (x + 9, y - 50),
        (x + 9, y),
    ], p["figura"])


def _golondrinas(esc, rng, p, lejos):
    """Tres a cinco golondrinas en el cielo, guiño a las Rimas."""
    for _ in range(rng.randint(4, 7)):
        x = rng.uniform(0.15, 0.85) * ANCHO
        y = rng.uniform(0.12, 0.45) * ALTO
        s = rng.uniform(14, 22)
        esc.poligono([(x - s, y - s * 0.45), (x, y), (x + s, y - s * 0.45), (x, y - s * 0.25)], p["figura"])


def _rio(esc, rng, p, lejos):
    """Banda de orilla de río en la base, con borde ondulado y grosor fijo."""
    fase = rng.uniform(0, 2 * math.pi)
    arriba = [(x, round(0.93 * ALTO + 5 * math.sin(x / 20 + fase), 1)) for x in range(0, ANCHO + 1, 20)]
    abajo = [(x, round(y + 14, 1)) for x, y in reversed(arriba)]
    esc.poligono(arriba + abajo, p["lejos"])


MOTIVOS = {"torre": _torre, "golondrinas": _golondrinas, "rio": _rio}


def _cuervo(esc, rng, p, altura):
    """Cuervo grande en el cielo, con las alas abiertas."""
    x, y, s = rng.uniform(0.2, 0.8) * ANCHO, rng.uniform(0.10, 0.24) * ALTO, rng.uniform(26, 34)
    esc.poligono([(x - s, y - s * 0.55), (x - s * 0.4, y - s * 0.2), (x - s * 0.15, y - s * 0.5),
                  (x, y - s * 0.05), (x + s * 0.15, y - s * 0.5), (x + s * 0.4, y - s * 0.2),
                  (x + s, y - s * 0.55), (x + s * 0.55, y + s * 0.1), (x + s * 0.12, y + s * 0.18),
                  (x + s * 0.2, y + s * 0.32), (x - s * 0.2, y + s * 0.32), (x - s * 0.12, y + s * 0.18),
                  (x - s * 0.55, y + s * 0.1)], p["figura"])


def _cipres(esc, rng, p, altura):
    """Grupo de cipreses altos y estrechos."""
    x0 = rng.uniform(0.15, 0.8) * ANCHO
    for _ in range(rng.randint(2, 4)):
        x = x0 + rng.uniform(-40, 40)
        y, h = altura(x) + 6, rng.uniform(48, 70)
        esc.poligono([(x, y), (x - 6, y - h * 0.4), (x - 4, y - h * 0.8), (x, y - h),
                      (x + 4, y - h * 0.8), (x + 6, y - h * 0.4)], p["figura"])


def _ruinas(esc, rng, p, altura):
    """Columnas rotas de distinta altura y un resto de dintel."""
    x0 = rng.uniform(0.2, 0.75) * ANCHO
    for k in range(4):
        x, h = x0 + k * 26, rng.uniform(22, 52)
        y = altura(x) + 4
        esc.poligono([(x - 6, y), (x - 6, y - h), (x - 3, y - h - 4), (x + 4, y - h + 2), (x + 6, y - h), (x + 6, y)], p["figura"])
    y = altura(x0 + 39) + 4
    esc.poligono([(x0 - 8, y - 60), (x0 + 60, y - 62), (x0 + 60, y - 54), (x0 - 8, y - 52)], p["figura"])


def _velero(esc, rng, p, altura):
    """Velero en el agua (solo en escenas marina)."""
    x = rng.uniform(0.15, 0.85) * ANCHO
    y = rng.linea + 22
    esc.poligono([(x - 26, y), (x + 26, y), (x + 18, y + 9), (x - 18, y + 9)], p["figura"])
    esc.poligono([(x - 1, y - 2), (x - 1, y - 48), (x - 20, y - 4)], p["figura"])
    esc.poligono([(x + 3, y - 2), (x + 3, y - 38), (x + 20, y - 4)], p["figura"])


def _castillo(esc, rng, p, altura):
    """Castillo con muralla almenada y dos torres."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = altura(x) + 3
    pts = [(x - 44, y), (x - 44, y - 40), (x - 38, y - 40), (x - 38, y - 34), (x - 32, y - 34), (x - 32, y - 40),
           (x - 26, y - 40), (x - 26, y - 28), (x - 14, y - 28), (x - 14, y - 58), (x - 8, y - 58), (x - 8, y - 52),
           (x - 2, y - 52), (x - 2, y - 58), (x + 4, y - 58), (x + 4, y - 28), (x + 26, y - 28), (x + 26, y - 40),
           (x + 32, y - 40), (x + 32, y - 34), (x + 38, y - 34), (x + 38, y - 40), (x + 44, y - 40), (x + 44, y)]
    esc.poligono(pts, p["figura"])


def _arbol(esc, rng, p, altura):
    """Árbol solitario de copa redonda."""
    x = rng.uniform(0.15, 0.85) * ANCHO
    y, r = altura(x) + 3, rng.uniform(20, 28)
    esc.poligono([(x - 3, y), (x - 2, y - r * 1.2), (x + 2, y - r * 1.2), (x + 3, y)], p["figura"])
    esc.circulo(x, y - r * 1.7, r, p["figura"])


def _catedral(esc, rng, p, altura):
    """Fachada gótica con dos torres de remate puntiagudo."""
    x = rng.uniform(0.25, 0.8) * ANCHO
    y = altura(x) + 3
    esc.poligono([(x - 30, y), (x - 30, y - 54), (x - 24, y - 54), (x - 19, y - 78), (x - 14, y - 54),
                  (x - 8, y - 54), (x - 8, y - 44), (x + 8, y - 44), (x + 8, y - 54), (x + 14, y - 54),
                  (x + 19, y - 78), (x + 24, y - 54), (x + 30, y - 54), (x + 30, y)], p["figura"])


def _rayo(esc, rng, p, altura):
    """Rayo en zigzag que cae del cielo."""
    x = rng.uniform(0.15, 0.85) * ANCHO
    esc.poligono([(x, 0), (x + 10, 0), (x + 3, 34), (x + 14, 34), (x - 8, 92), (x - 2, 46), (x - 12, 46)], p["sol"])


def _cruz(esc, rng, p, altura):
    """Cruz sobre un montículo."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = altura(x) + 3
    esc.poligono([(x - 2, y), (x - 2, y - 30), (x - 9, y - 30), (x - 9, y - 35), (x - 2, y - 35), (x - 2, y - 46),
                  (x + 2, y - 46), (x + 2, y - 35), (x + 9, y - 35), (x + 9, y - 30), (x + 2, y - 30), (x + 2, y)], p["figura"])


def _estrellas(esc, rng, p, altura):
    """Estrellas dispersas por el cielo."""
    for _ in range(rng.randint(30, 46)):
        esc.circulo(round(rng.uniform(0.04, 0.96) * ANCHO, 1), round(rng.uniform(0.04, 0.55) * ALTO, 1),
                    round(rng.uniform(1.2, 2.8), 1), p["sol"])


MOTIVOS.update({
    "cuervo": _cuervo, "cipres": _cipres, "ruinas": _ruinas, "velero": _velero, "castillo": _castillo,
    "arbol": _arbol, "catedral": _catedral, "rayo": _rayo, "cruz": _cruz, "estrellas": _estrellas,
})


# --- Biblioteca ampliada (obra a obra) ------------------------------------------

def _suelo(altura, x):
    return altura(x) + 3


def _organo(esc, rng, p, altura):
    """Tubos de órgano de distinta altura sobre una caja."""
    x0, y = rng.uniform(0.2, 0.75) * ANCHO, _suelo(altura, 0)
    y = _suelo(altura, x0)
    esc.poligono([(x0 - 4, y), (x0 - 4, y - 8), (x0 + 66, y - 8), (x0 + 66, y)], p["figura"])
    for k in range(8):
        h = 26 + 7 * abs(3.5 - k) * 1.3 + rng.uniform(0, 6)
        x = x0 + k * 8.5
        esc.poligono([(x, y - 8), (x, y - 8 - h), (x + 3.5, y - 12 - h), (x + 7, y - 8 - h), (x + 7, y - 8)], p["figura"])


def _fuente(esc, rng, p, altura):
    """Fuente de pilón con surtidor."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 22, y - 12), (x + 22, y - 12), (x + 14, y - 3), (x + 6, y - 3), (x + 6, y - 14), (x - 6, y - 14),
                  (x - 6, y - 3), (x - 14, y - 3)], p["figura"])
    esc.poligono([(x - 1.5, y - 14), (x - 7, y - 44), (x, y - 52), (x + 7, y - 44), (x + 1.5, y - 14)], p["sol"])


def _puente(esc, rng, p, altura):
    """Puente de piedra de tres arcos."""
    xc = rng.uniform(0.25, 0.75) * ANCHO
    r, pila, n = 17, 8, 3
    ancho = n * 2 * r + (n + 1) * pila
    x0, x1 = xc - ancho / 2, xc + ancho / 2
    yb = _suelo(altura, xc) + 6
    yd = yb - 36
    pts = [(x0, yb), (x0, yd), (x1, yd), (x1, yb)]
    for k in range(n - 1, -1, -1):
        cx = x0 + pila + r + k * (2 * r + pila)
        pts += [(round(cx + r * math.cos(a * math.pi / 8), 1), round(yb - r * 1.1 * math.sin(a * math.pi / 8), 1))
                for a in range(0, 9)]
    esc.poligono(pts, p["figura"])


def _faro(esc, rng, p, altura):
    """Faro con haz de luz (solo en escenas marina)."""
    x = rng.uniform(0.15, 0.85) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 8, y), (x - 5, y - 52), (x + 5, y - 52), (x + 8, y)], p["figura"])
    esc.poligono([(x - 7, y - 52), (x - 7, y - 60), (x + 7, y - 60), (x + 7, y - 52)], p["sol"])
    esc.poligono([(x - 9, y - 60), (x, y - 70), (x + 9, y - 60)], p["figura"])
    esc.poligono([(x + 8, y - 57), (x + 130, y - 76), (x + 130, y - 38)], p["sol"])


def _rosa(esc, rng, p, altura):
    """Rosa de cinco pétalos sobre un tallo."""
    x = rng.uniform(0.15, 0.85) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 1.2, y), (x - 1.2, y - 40), (x + 1.2, y - 40), (x + 1.2, y)], p["figura"])
    esc.poligono([(x, y - 18), (x + 14, y - 28), (x + 4, y - 14)], p["figura"])
    for k in range(5):
        a = 2 * math.pi * k / 5
        esc.circulo(round(x + 7 * math.cos(a), 1), round(y - 48 + 7 * math.sin(a), 1), 6, p["sol"])
    esc.circulo(x, y - 48, 4, p["figura"])


def _lira(esc, rng, p, altura):
    """Lira: dos brazos curvos, travesaño y base."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = _suelo(altura, x)
    brazo = [(x - 5 - 13 * math.sin(t / 8 * math.pi), y - 8 - t * 6) for t in range(0, 9)]
    esc.poligono([(x - 11, y), (x - 11, y - 8), (x + 11, y - 8), (x + 11, y)], p["figura"])
    for sgn in (-1, 1):
        ext = [(round(a if sgn < 0 else 2 * x - a, 1), round(b, 1)) for a, b in brazo]
        inte = [(round(a - sgn * 2.6, 1), b) for a, b in ext]
        esc.poligono(ext + inte[::-1], p["figura"])
    esc.poligono([(x - 14, y - 52), (x + 14, y - 52), (x + 14, y - 49), (x - 14, y - 49)], p["figura"])
    for k in (-6, -2, 2, 6):
        esc.poligono([(x + k - 0.5, y - 10), (x + k - 0.5, y - 50), (x + k + 0.5, y - 50), (x + k + 0.5, y - 10)], p["figura"])


def _urna(esc, rng, p, altura):
    """Ánfora griega."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 8, y), (x - 13, y - 14), (x - 15, y - 28), (x - 9, y - 40), (x - 5, y - 46), (x - 8, y - 52),
                  (x + 8, y - 52), (x + 5, y - 46), (x + 9, y - 40), (x + 15, y - 28), (x + 13, y - 14), (x + 8, y)], p["figura"])
    for sgn in (-1, 1):
        esc.poligono([(x + sgn * 7, y - 46), (x + sgn * 20, y - 40), (x + sgn * 20, y - 30), (x + sgn * 14, y - 28),
                      (x + sgn * 17, y - 34), (x + sgn * 16, y - 38), (x + sgn * 8, y - 42)], p["figura"])


def _estatua(esc, rng, p, altura):
    """Dos piernas de piedra sin tronco, con la base, como un coloso caído."""
    x = rng.uniform(0.25, 0.75) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 30, y), (x - 30, y - 6), (x + 30, y - 6), (x + 30, y)], p["figura"])
    for sgn in (-1, 1):
        cx = x + sgn * 12
        esc.poligono([(cx - 8, y - 6), (cx - 7, y - 50), (cx - 3, y - 56), (cx + 7, y - 52), (cx + 8, y - 6)], p["figura"])
        esc.poligono([(cx - 12, y - 6), (cx - 12, y - 11), (cx + 12, y - 11), (cx + 12, y - 6)], p["figura"])


def _horca(esc, rng, p, altura):
    """Horca con soga."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 2, y), (x - 2, y - 64), (x + 38, y - 64), (x + 38, y - 60), (x + 2, y - 60), (x + 2, y)], p["figura"])
    esc.poligono([(x + 2, y - 50), (x + 16, y - 61), (x + 16, y - 64), (x + 2, y - 55)], p["figura"])
    esc.poligono([(x + 33, y - 60), (x + 33, y - 40), (x + 34.5, y - 40), (x + 34.5, y - 60)], p["figura"])
    esc.circulo(x + 33.8, y - 38, 3.5, p["figura"])


def _gato(esc, rng, p, altura):
    """Gato negro sentado, de perfil."""
    x = rng.uniform(0.15, 0.85) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 14, y), (x - 11, y - 18), (x - 6, y - 28), (x - 8, y - 38), (x - 5, y - 34), (x - 1, y - 36),
                  (x + 2, y - 38), (x + 3, y - 32), (x + 3, y - 26), (x + 8, y - 14), (x + 12, y - 6), (x + 22, y - 4),
                  (x + 24, y - 9), (x + 20, y - 12), (x + 14, y - 14), (x + 12, y)], p["figura"])


def _corazon(esc, rng, p, altura):
    """Corazón grande en el cielo."""
    x, y, s = rng.uniform(0.2, 0.8) * ANCHO, rng.uniform(0.12, 0.24) * ALTO, 16
    esc.circulo(x - s * 0.5, y - s * 0.35, s * 0.6, p["sol"])
    esc.circulo(x + s * 0.5, y - s * 0.35, s * 0.6, p["sol"])
    esc.poligono([(x - s * 1.06, y - s * 0.1), (x, y + s * 1.2), (x + s * 1.06, y - s * 0.1)], p["sol"])


def _reloj(esc, rng, p, altura):
    """Torre con esfera de reloj."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 14, y), (x - 14, y - 70), (x, y - 82), (x + 14, y - 70), (x + 14, y)], p["figura"])
    esc.circulo(x, y - 54, 9, p["sol"])
    esc.poligono([(x - 0.8, y - 54), (x - 0.8, y - 61), (x + 0.8, y - 61), (x + 0.8, y - 54)], p["figura"])
    esc.poligono([(x, y - 55), (x + 5, y - 52), (x + 5, y - 53.5), (x, y - 54)], p["figura"])


def _ataud(esc, rng, p, altura):
    """Ataúd hexagonal."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 26, y - 8), (x - 20, y - 20), (x + 20, y - 20), (x + 30, y - 12), (x + 20, y - 2), (x - 20, y - 2)], p["figura"])


def _lapidas(esc, rng, p, altura):
    """Hilera de lápidas de cabeza redondeada."""
    x0 = rng.uniform(10, 40)
    for k in range(rng.randint(4, 5)):
        x, w, h = x0 + k * rng.uniform(22, 30), rng.uniform(12, 16), rng.uniform(16, 30)
        y = _suelo(altura, x)
        esc.poligono([(x - w / 2, y), (x - w / 2, y - h + 4), (x - w / 4, y - h), (x + w / 4, y - h),
                      (x + w / 2, y - h + 4), (x + w / 2, y)], p["figura"])


def _vela(esc, rng, p, altura):
    """Vela con llama y candelero."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 14, y), (x - 10, y - 5), (x + 10, y - 5), (x + 14, y)], p["figura"])
    esc.poligono([(x - 5, y - 5), (x - 5, y - 44), (x + 5, y - 44), (x + 5, y - 5)], p["figura"])
    esc.poligono([(x, y - 66), (x + 6, y - 52), (x + 3, y - 45), (x - 3, y - 45), (x - 6, y - 52)], p["sol"])


def _olas(esc, rng, p, altura):
    """Crestas de ola rizadas apiladas en el mar (solo en escenas marina)."""
    y = rng.linea + rng.uniform(60, 110)
    while y < ALTO - 30:
        w = rng.uniform(60, 110)
        x = rng.uniform(4, ANCHO - w - 4)
        esc.poligono([(x, y), (x + w * 0.35, y - 16), (x + w * 0.62, y - 22), (x + w * 0.8, y - 14),
                      (x + w * 0.68, y - 15), (x + w * 0.52, y - 9), (x + w * 0.3, y - 2)], p["sol"])
        y += rng.uniform(70, 120)


def _nube(esc, rng, p, altura):
    """Nube de bordes redondeados."""
    x, y = rng.uniform(0.15, 0.85) * ANCHO, rng.uniform(0.10, 0.24) * ALTO
    for dx, dy, r in ((-26, 4, 12), (-10, -6, 16), (10, -4, 18), (28, 4, 13), (0, 6, 14)):
        esc.circulo(round(x + dx, 1), round(y + dy, 1), r, p["sol"])
    esc.poligono([(x - 36, y + 12), (x - 36, y + 18), (x + 40, y + 18), (x + 40, y + 12)], p["sol"])


def _narcisos(esc, rng, p, altura):
    """Narcisos al pie del paisaje."""
    for _ in range(rng.randint(5, 8)):
        x = rng.uniform(12, ANCHO - 12)
        y, h = ALTO - 1, rng.uniform(40, 80)
        esc.poligono([(x - 1.2, y), (x - 1.2, y - h), (x + 1.2, y - h), (x + 1.2, y)], p["figura"])
        for k in range(6):
            a = 2 * math.pi * k / 6
            esc.circulo(round(x + 6.5 * math.cos(a), 1), round(y - h + 6.5 * math.sin(a), 1), 4.6, p["sol"])
        esc.circulo(x, y - h, 3.8, p["figura"])


def _hojas(esc, rng, p, altura):
    """Hojas que caen."""
    for _ in range(rng.randint(26, 38)):
        x, y, s = rng.uniform(0.04, 0.96) * ANCHO, rng.uniform(0.05, 0.85) * ALTO, rng.uniform(5, 9)
        a = rng.uniform(0, math.pi)
        c, sn = math.cos(a), math.sin(a)
        esc.poligono([(round(x + c * s * 1.4, 1), round(y + sn * s * 1.4, 1)),
                      (round(x - sn * s * 0.7, 1), round(y + c * s * 0.7, 1)),
                      (round(x - c * s * 1.4, 1), round(y - sn * s * 1.4, 1)),
                      (round(x + sn * s * 0.7, 1), round(y - c * s * 0.7, 1))], p["sol"])


def _serpiente(esc, rng, p, altura):
    """Serpiente ondulante sobre el suelo."""
    x0 = rng.uniform(12, 70)
    base = _suelo(altura, x0) - 4
    arriba, abajo = [], []
    for k in range(0, 41):
        x = x0 + k * 2.2
        y = base - 9 * math.sin(k / 3.2) - (7 if k > 36 else 0) * (k - 36) / 4
        g = 4.2 * (1 - k / 48) + 0.8
        arriba.append((round(x, 1), round(y - g, 1)))
        abajo.append((round(x, 1), round(y + g, 1)))
    esc.poligono(arriba + abajo[::-1], p["figura"])


def _espada(esc, rng, p, altura):
    """Espada clavada en el suelo."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 3, y - 36), (x, y + 2), (x + 3, y - 36)], p["figura"])
    esc.poligono([(x - 11, y - 36), (x - 11, y - 40), (x + 11, y - 40), (x + 11, y - 36)], p["figura"])
    esc.poligono([(x - 2, y - 40), (x - 2, y - 56), (x + 2, y - 56), (x + 2, y - 40)], p["figura"])
    esc.circulo(x, y - 58, 3.4, p["figura"])


def _corona(esc, rng, p, altura):
    """Corona de cinco puntas en el cielo."""
    x, y = rng.uniform(0.2, 0.8) * ANCHO, rng.uniform(0.12, 0.24) * ALTO
    esc.poligono([(x - 24, y + 14), (x - 28, y - 14), (x - 14, y - 2), (x - 7, y - 20), (x, y - 4), (x + 7, y - 20),
                  (x + 14, y - 2), (x + 28, y - 14), (x + 24, y + 14)], p["sol"])


def _barca(esc, rng, p, altura):
    """Barca de remos en el agua (solo en escenas marina)."""
    x = rng.uniform(0.15, 0.85) * ANCHO
    y = rng.linea + 24
    esc.poligono([(x - 26, y - 8), (x + 26, y - 8), (x + 16, y + 2), (x - 14, y + 2)], p["figura"])
    esc.poligono([(x - 3, y - 6), (x + 20, y - 28), (x + 22, y - 26), (x - 1, y - 4)], p["figura"])


def _arco(esc, rng, p, altura):
    """Arco gótico en ruinas."""
    x = rng.uniform(0.2, 0.8) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 32, y), (x - 32, y - 52), (x - 24, y - 70), (x - 10, y - 82), (x, y - 86), (x + 10, y - 82),
                  (x + 24, y - 70), (x + 32, y - 52), (x + 32, y), (x + 20, y), (x + 20, y - 50), (x + 12, y - 64),
                  (x, y - 72), (x - 12, y - 64), (x - 20, y - 50), (x - 20, y)], p["figura"])


def _horreo(esc, rng, p, altura):
    """Hórreo gallego: caja alargada sobre pies, tejado a dos aguas y cruz."""
    x = rng.uniform(0.15, 0.8) * ANCHO
    y = _suelo(altura, x)
    for dx in (-30, -12, 12, 30):
        esc.poligono([(x + dx - 2, y), (x + dx - 2, y - 12), (x + dx + 2, y - 12), (x + dx + 2, y)], p["figura"])
    esc.poligono([(x - 38, y - 12), (x - 38, y - 30), (x + 38, y - 30), (x + 38, y - 12)], p["figura"])
    esc.poligono([(x - 42, y - 30), (x, y - 46), (x + 42, y - 30)], p["figura"])
    esc.poligono([(x + 40, y - 38), (x + 40, y - 56), (x + 42, y - 56), (x + 42, y - 38)], p["figura"])
    esc.poligono([(x + 36, y - 50), (x + 36, y - 52), (x + 46, y - 52), (x + 46, y - 50)], p["figura"])


def _barricada(esc, rng, p, altura):
    """Barricada de tablones y ruedas con una bandera."""
    x0 = rng.uniform(0.2, 0.7) * ANCHO
    y = _suelo(altura, x0)
    pts, x = [(x0, y)], x0
    while x < x0 + 120:
        x += rng.uniform(8, 16)
        pts.append((round(x, 1), round(y - rng.uniform(16, 34), 1)))
    pts.append((x, y))
    esc.poligono(pts, p["figura"])
    px = x0 + 60
    esc.poligono([(px, y - 30), (px, y - 74), (px + 1.8, y - 74), (px + 1.8, y - 30)], p["figura"])
    esc.poligono([(px + 1.8, y - 74), (px + 26, y - 68), (px + 1.8, y - 60)], p["sol"])


MOTIVOS.update({
    "organo": _organo, "fuente": _fuente, "puente": _puente, "faro": _faro, "rosa": _rosa, "lira": _lira,
    "urna": _urna, "estatua": _estatua, "horca": _horca, "gato": _gato, "corazon": _corazon, "reloj": _reloj,
    "ataud": _ataud, "lapidas": _lapidas, "vela": _vela, "olas": _olas, "nube": _nube, "narcisos": _narcisos,
    "hojas": _hojas, "serpiente": _serpiente, "espada": _espada, "corona": _corona, "barca": _barca,
    "arco": _arco, "horreo": _horreo, "barricada": _barricada,
})


# --- Motivos del Gótico y del Realismo mágico ------------------------------------------

def _murcielago(esc, x, y, s, color):
    mitad = [(0, 0), (0.3, -0.35), (0.6, -0.2), (1.0, -0.55), (0.85, 0), (0.6, 0.1), (0.35, 0.05), (0.15, 0.25)]
    izq = [(round(x - dx * s, 1), round(y + dy * s, 1)) for dx, dy in mitad]
    der = [(round(x + dx * s, 1), round(y + dy * s, 1)) for dx, dy in reversed(mitad)]
    esc.poligono(izq + [(x, round(y + 0.1 * s, 1))] + der, color)


def _murcielagos(esc, rng, p, altura):
    """Bandada de murciélagos en el cielo alto."""
    for _ in range(rng.randint(5, 8)):
        _murcielago(esc, rng.uniform(0.12, 0.88) * ANCHO, rng.uniform(0.06, 0.27) * ALTO, rng.uniform(13, 24), p["figura"])


def _gargola(esc, rng, p, altura):
    """Gárgola agazapada sobre un pedestal de piedra."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 24, y), (x - 24, y - 12), (x + 24, y - 12), (x + 24, y)], p["figura"])
    esc.poligono([(x - 18, y - 12), (x - 20, y - 30), (x - 34, y - 46), (x - 22, y - 40), (x - 12, y - 52), (x - 6, y - 40),
                  (x - 8, y - 56), (x + 2, y - 50), (x + 10, y - 56), (x + 12, y - 44), (x + 22, y - 36), (x + 36, y - 44),
                  (x + 26, y - 28), (x + 18, y - 12)], p["figura"])


def _mansion(esc, rng, p, altura):
    """Casa encantada: cuerpo con tejado empinado, torre lateral y ventanas encendidas."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 36, y), (x - 36, y - 46), (x - 40, y - 46), (x, y - 92), (x + 40, y - 46), (x + 36, y - 46), (x + 36, y)], p["figura"])
    esc.poligono([(x - 54, y), (x - 54, y - 70), (x - 60, y - 70), (x - 45, y - 100), (x - 30, y - 70), (x - 36, y - 70), (x - 36, y)], p["figura"])
    for dx, dy in ((-20, 24), (6, 24), (-48, 48), (-2, 62)):
        esc.poligono([(x + dx, y - dy), (x + dx, y - dy - 10), (x + dx + 7, y - dy - 10), (x + dx + 7, y - dy)], p["sol"])


def _ventana_gotica(esc, rng, p, altura):
    """Ventana ojival iluminada, con parteluz y rosetón."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 24, y), (x - 24, y - 70), (x - 14, y - 96), (x, y - 112), (x + 14, y - 96), (x + 24, y - 70), (x + 24, y)], p["figura"])
    esc.poligono([(x - 16, y - 6), (x - 16, y - 68), (x - 9, y - 88), (x, y - 100), (x + 9, y - 88), (x + 16, y - 68), (x + 16, y - 6)], p["sol"])
    esc.poligono([(x - 1.5, y - 6), (x - 1.5, y - 100), (x + 1.5, y - 100), (x + 1.5, y - 6)], p["figura"])
    esc.poligono([(x - 16, y - 50), (x + 16, y - 50), (x + 16, y - 47), (x - 16, y - 47)], p["figura"])


def _lobo(esc, rng, p, altura):
    """Lobo aullando a la luna sobre una loma."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 34, y), (x - 30, y - 8), (x - 24, y - 22), (x - 10, y - 28), (x - 2, y - 38), (x + 2, y - 52),
                  (x + 10, y - 62), (x + 22, y - 66), (x + 12, y - 56), (x + 12, y - 46), (x + 6, y - 36), (x + 8, y - 22),
                  (x + 10, y), (x + 4, y), (x + 2, y - 12), (x - 6, y - 10), (x - 14, y - 6), (x - 16, y)], p["figura"])


def _encapuchado(esc, rng, p, altura):
    """Figura envuelta en una capa con capucha."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 20, y), (x - 14, y - 34), (x - 11, y - 56), (x - 6, y - 68), (x, y - 72), (x + 6, y - 68),
                  (x + 11, y - 56), (x + 14, y - 34), (x + 20, y)], p["figura"])


def _mariposa(esc, x, y, s, color):
    esc.poligono([(x, y), (x - s, y - s * 0.9), (x - s * 0.9, y + s * 0.1)], color)
    esc.poligono([(x, y), (x - s * 0.7, y + s * 0.2), (x - s * 0.5, y + s * 0.9)], color)
    esc.poligono([(x, y), (x + s, y - s * 0.9), (x + s * 0.9, y + s * 0.1)], color)
    esc.poligono([(x, y), (x + s * 0.7, y + s * 0.2), (x + s * 0.5, y + s * 0.9)], color)


ORO = "#F2C94C"  # el amarillo de las mariposas de Macondo


def color_oro(p):
    """Oro de las mariposas y los pétalos si se distingue del cielo; si no, la silueta."""
    return ORO if contraste(ORO, p["cielo_bajo"]) >= 1.8 and contraste(ORO, p["cielo_alto"]) >= 1.8 else p["figura"]


def _mariposas(esc, rng, p, altura):
    """Mariposas amarillas a la deriva (ambiente)."""
    oro = color_oro(p)
    for _ in range(rng.randint(6, 9)):
        y = rng.uniform(0.06, 0.28) * ALTO if rng.random() < 0.6 else rng.uniform(0.62, 0.8) * ALTO
        _mariposa(esc, rng.uniform(0.12, 0.88) * ANCHO, y, rng.uniform(8, 14), oro)


def _petalos(esc, rng, p, altura):
    """Lluvia de flores amarillas (ambiente)."""
    oro = color_oro(p)
    for _ in range(rng.randint(34, 48)):
        x, y, s = rng.uniform(0.04, 0.96) * ANCHO, rng.uniform(0.04, 0.9) * ALTO, rng.uniform(4, 8)
        a = rng.uniform(0, math.pi)
        c, sn = math.cos(a), math.sin(a)
        esc.poligono([(round(x + c * s * 1.5, 1), round(y + sn * s * 1.5, 1)), (round(x - sn * s * 0.8, 1), round(y + c * s * 0.8, 1)),
                      (round(x - c * s * 1.5, 1), round(y - sn * s * 1.5, 1)), (round(x + sn * s * 0.8, 1), round(y - c * s * 0.8, 1))], oro)


def _ceiba(esc, rng, p, altura):
    """Ceiba: tronco con raíces tabulares y copa ancha y plana."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 30, y), (x - 14, y - 22), (x - 8, y - 60), (x - 7, y - 80), (x + 7, y - 80), (x + 8, y - 60),
                  (x + 14, y - 22), (x + 30, y)], p["figura"])
    for dx, dy, r in ((-36, 0, 22), (-14, -10, 26), (12, -8, 26), (34, 2, 22), (0, 8, 24)):
        esc.circulo(round(x + dx, 1), round(y - 100 + dy, 1), r, p["figura"])


def _casa_colonial(esc, rng, p, altura):
    """Casa colonial baja con tejado, espadaña y portal encendido."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 46, y), (x - 46, y - 34), (x + 46, y - 34), (x + 46, y)], p["figura"])
    esc.poligono([(x - 54, y - 34), (x - 38, y - 50), (x + 38, y - 50), (x + 54, y - 34)], p["figura"])
    esc.poligono([(x - 6, y - 50), (x - 6, y - 66), (x - 2, y - 72), (x + 2, y - 72), (x + 6, y - 66), (x + 6, y - 50)], p["figura"])
    esc.poligono([(x - 8, y), (x - 8, y - 20), (x - 4, y - 25), (x + 4, y - 25), (x + 8, y - 20), (x + 8, y)], p["sol"])
    for dx in (-30, 24):
        esc.poligono([(x + dx, y - 14), (x + dx, y - 26), (x + dx + 8, y - 26), (x + dx + 8, y - 14)], p["sol"])


def _volcan(esc, rng, p, altura):
    """Volcán de cono perfecto con un penacho de humo."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 84, y), (x - 14, y - 92), (x - 6, y - 98), (x + 6, y - 98), (x + 14, y - 92), (x + 84, y)], p["figura"])
    for dx, dy, r in ((4, -112, 8), (-4, -126, 11), (8, -142, 13)):
        esc.circulo(round(x + dx, 1), round(y + dy, 1), r, p["sol"])


def _maiz(esc, rng, p, altura):
    """Cañas de maíz con mazorcas."""
    x0 = rng.uniform(0.25, 0.6) * ANCHO
    for k in range(rng.randint(3, 4)):
        x, h = x0 + k * rng.uniform(18, 26), rng.uniform(70, 104)
        y = _suelo(altura, x)
        esc.poligono([(x - 1.5, y), (x - 1.5, y - h), (x + 1.5, y - h), (x + 1.5, y)], p["figura"])
        for sgn, dy in ((-1, 0.45), (1, 0.62), (-1, 0.78)):
            esc.poligono([(x, y - h * dy), (x + sgn * 22, y - h * dy - 14), (x + sgn * 24, y - h * dy - 10)], p["figura"])
        esc.poligono([(x + 1.5, y - h * 0.55), (x + 8, y - h * 0.62), (x + 5, y - h * 0.4)], color_oro(p))


def _pez_dorado(esc, rng, p, altura):
    """Tres pececillos de oro flotando en el aire."""
    oro = color_oro(p)
    x0, y0 = rng.uniform(0.3, 0.7) * ANCHO, rng.uniform(0.14, 0.24) * ALTO
    for dx, dy, s in ((0, 0, 11), (26, -22, 8), (-24, 20, 9)):
        x, y = x0 + dx, y0 + dy
        esc.poligono([(x - s, y), (x - s * 0.2, y - s * 0.5), (x + s * 0.8, y), (x - s * 0.2, y + s * 0.5)], oro)
        esc.poligono([(x + s * 0.7, y), (x + s * 1.4, y - s * 0.5), (x + s * 1.4, y + s * 0.5)], oro)


def _gallo(esc, rng, p, altura):
    """Gallo sobre un poste."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    esc.poligono([(x - 2, y), (x - 2, y - 24), (x + 2, y - 24), (x + 2, y)], p["figura"])
    esc.poligono([(x - 22, y - 30), (x - 30, y - 56), (x - 20, y - 48), (x - 20, y - 66), (x - 12, y - 50), (x - 8, y - 38),
                  (x - 2, y - 42), (x + 2, y - 58), (x + 6, y - 62), (x + 8, y - 70), (x + 12, y - 62), (x + 18, y - 60),
                  (x + 12, y - 54), (x + 10, y - 44), (x + 14, y - 34), (x + 8, y - 24), (x - 6, y - 24)], p["figura"])


def _lluvia(esc, rng, p, altura):
    """Lluvia oblicua (ambiente)."""
    for _ in range(rng.randint(60, 84)):
        x, y, l = rng.uniform(-10, ANCHO) , rng.uniform(0.04, 0.92) * ALTO, rng.uniform(14, 24)
        esc.poligono([(round(x, 1), round(y, 1)), (round(x + 1.6, 1), round(y, 1)), (round(x + 1.6 - l * 0.25, 1), round(y + l, 1)),
                      (round(x - l * 0.25, 1), round(y + l, 1))], p["sol"])


def _fantasma(esc, rng, p, altura):
    """Aparecido de sábana ondulante, con ojos."""
    x = rng.uniform(0.3, 0.7) * ANCHO
    y = _suelo(altura, x)
    pts = [(x - 20, y), (x - 20, y - 40), (x - 14, y - 60), (x, y - 70), (x + 14, y - 60), (x + 20, y - 40), (x + 20, y)]
    pts += [(x + 20 - k * 8, y + (6 if k % 2 else -4)) for k in range(1, 5)]
    esc.poligono(pts, p["sol"])
    esc.circulo(x - 6, y - 50, 3.2, p["figura"])
    esc.circulo(x + 6, y - 50, 3.2, p["figura"])


def _alas(esc, rng, p, altura):
    """Par de alas enormes de plumas, en el cielo."""
    x, y = rng.uniform(0.35, 0.65) * ANCHO, rng.uniform(0.12, 0.22) * ALTO
    for sgn in (-1, 1):
        pts = [(x + sgn * 4, y + 10), (x + sgn * 20, y - 10)]
        for k in range(1, 6):
            pts += [(x + sgn * (20 + 16 * k), y - 12 + 7 * k), (x + sgn * (14 + 16 * k), y - 2 + 7 * k)]
        pts += [(x + sgn * 6, y + 30)]
        esc.poligono([(round(a, 1), round(b, 1)) for a, b in pts], p["sol"])


MOTIVOS.update({
    "murcielagos": _murcielagos, "gargola": _gargola, "mansion": _mansion, "ventana_gotica": _ventana_gotica,
    "lobo": _lobo, "encapuchado": _encapuchado, "mariposas": _mariposas, "petalos": _petalos, "ceiba": _ceiba,
    "casa_colonial": _casa_colonial, "volcan": _volcan, "maiz": _maiz, "pez_dorado": _pez_dorado, "gallo": _gallo,
    "lluvia": _lluvia, "fantasma": _fantasma, "alas": _alas,
})


# --- Escalado de motivos ---------------------------------------------------------
# (factor, ancla): 'suelo' conserva la base del motivo, 'centro' su centro y 'arriba'
# su borde superior. Sin entrada = tamaño original. Los motivos se diseñaron pequeños
# (30–80 px sobre 200) y así se pierden al reducir el marcalibros.
ESCALAS = {
    "torre": (1.2, "suelo"), "catedral": (1.3, "suelo"), "arco": (1.25, "suelo"), "faro": (1.2, "suelo"),
    "reloj": (1.2, "suelo"), "horca": (1.4, "suelo"), "castillo": (1.5, "suelo"), "organo": (1.4, "suelo"),
    "horreo": (1.5, "suelo"), "puente": (1.5, "suelo"), "fuente": (1.6, "suelo"), "rosa": (1.6, "suelo"),
    "lira": (1.6, "suelo"), "urna": (1.6, "suelo"), "gato": (1.7, "suelo"), "vela": (1.5, "suelo"),
    "ataud": (1.8, "suelo"), "lapidas": (1.7, "suelo"), "serpiente": (1.8, "suelo"), "espada": (1.7, "suelo"),
    "estatua": (1.6, "suelo"), "ruinas": (1.5, "suelo"), "cruz": (1.7, "suelo"), "cipres": (1.3, "suelo"),
    "arbol": (1.6, "suelo"), "barricada": (1.5, "suelo"), "velero": (1.6, "suelo"), "barca": (1.8, "suelo"),
    "nube": (1.4, "centro"), "cuervo": (1.5, "centro"), "corazon": (1.6, "centro"), "corona": (1.6, "centro"),
    "rayo": (1.3, "arriba"),
    "gargola": (1.7, "suelo"), "mansion": (1.3, "suelo"), "ventana_gotica": (1.5, "suelo"), "lobo": (1.8, "suelo"),
    "encapuchado": (1.6, "suelo"), "ceiba": (1.2, "suelo"), "casa_colonial": (1.5, "suelo"), "volcan": (1.2, "suelo"),
    "maiz": (1.3, "suelo"), "pez_dorado": (1.6, "centro"), "gallo": (1.7, "suelo"), "fantasma": (1.7, "suelo"), "alas": (1.5, "centro"),
}
# Se dibujan por delante de todas las capas (los demás, entre la lejana y la cercana,
# de modo que la capa cercana tape su base y queden apoyados en el suelo).
FRENTE = {"olas", "narcisos", "hojas", "rio", "petalos", "lluvia"}  # el río es una franja baja: la capa cercana lo taparía


def _limites(prims):
    xs, ys = [], []
    for q in prims:
        if q["tipo"] == "poligono":
            xs += [x for x, _ in q["puntos"]]
            ys += [y for _, y in q["puntos"]]
        elif q["tipo"] == "circulo":
            xs += [q["cx"] - q["r"], q["cx"] + q["r"]]
            ys += [q["cy"] - q["r"], q["cy"] + q["r"]]
    return min(xs), min(ys), max(xs), max(ys)


def _escalar(prims, k, ancla):
    x0, y0, x1, y1 = _limites(prims)
    ax = (x0 + x1) / 2
    ay = {"suelo": y1, "centro": (y0 + y1) / 2, "arriba": y0}[ancla]
    for q in prims:
        if q["tipo"] == "poligono":
            q["puntos"] = [(round(ax + (x - ax) * k, 1), round(ay + (y - ay) * k, 1)) for x, y in q["puntos"]]
        elif q["tipo"] == "circulo":
            q["cx"], q["cy"] = round(ax + (q["cx"] - ax) * k, 1), round(ay + (q["cy"] - ay) * k, 1)
            q["r"] = round(q["r"] * k, 1)


# Motivos repartidos por todo el lienzo: ni se escalan ni se ajustan al ancho.
AMBIENTALES = {"estrellas", "golondrinas", "hojas", "olas", "rio", "narcisos", "murcielagos", "mariposas", "petalos", "lluvia"}
MARGEN_LATERAL = 8


def _encajar_en_ancho(prims, ancla):
    """Si el motivo no cabe en el ancho (200 px) se reduce; después se desplaza para que no se corte."""
    x0, _, x1, _ = _limites(prims)
    maximo = ANCHO - 2 * MARGEN_LATERAL
    if x1 - x0 > maximo:
        _escalar(prims, maximo / (x1 - x0), ancla)
        x0, _, x1, _ = _limites(prims)
    dx = MARGEN_LATERAL - x0 if x0 < MARGEN_LATERAL else (ANCHO - MARGEN_LATERAL - x1 if x1 > ANCHO - MARGEN_LATERAL else 0)
    if dx:
        for q in prims:
            if q["tipo"] == "poligono":
                q["puntos"] = [(round(x + dx, 1), y) for x, y in q["puntos"]]
            elif q["tipo"] == "circulo":
                q["cx"] = round(q["cx"] + dx, 1)


def _poner_motivo(esc, rng, p, altura, nombre):
    if nombre not in MOTIVOS:
        return
    antes = len(esc.primitivas)
    MOTIVOS[nombre](esc, rng, p, altura)
    if getattr(rng, "ocultar", False):
        del esc.primitivas[antes:]  # el motivo consume el azar igual, pero no se dibuja
        return
    prims = esc.primitivas[antes:]
    if prims and nombre not in AMBIENTALES:
        if nombre in ESCALAS:
            _escalar(prims, *ESCALAS[nombre])
        _encajar_en_ancho(prims, ESCALAS.get(nombre, (1, "suelo"))[1])
    if prims:
        rng.bbox_motivo = _limites(prims)


# --- Arquetipos de composición -------------------------------------------------
# Cada uno dibuja el paisaje y llama a colocar(altura) entre la capa lejana y la
# cercana. altura(x) es el suelo sobre el que se apoya el motivo: la capa lejana, o
# en montañas la cordillera cercana, para que no quede sobre una cima.

def _arq_colinas(esc, rng, p, colocar):
    lejos = _colina(rng, 0.76, 34)
    cerca = _colina(rng, 0.86, 40)
    esc.poligono(_puntos_colina(lejos), p["lejos"])
    altura = lambda x: _altura(lejos, x)
    colocar(altura)
    esc.poligono(_puntos_colina(cerca), p["cerca"])


def _picos(rng, base, alto_min, alto_max, ancho_min, ancho_max):
    """Cordillera como lista de vértices (zigzag) cerrada por la base."""
    base += rng.nivel * ALTO
    puntos, x = [(0, base)], 0.0
    while x < ANCHO:
        x += rng.uniform(ancho_min, ancho_max)
        puntos.append((round(x, 1), round(base - rng.uniform(alto_min, alto_max), 1)))
        x += rng.uniform(ancho_min, ancho_max) * 0.6
        puntos.append((round(x, 1), round(base - rng.uniform(0, alto_min * 0.4), 1)))
    return puntos


def _interp(puntos, x):
    for (x0, y0), (x1, y1) in zip(puntos, puntos[1:]):
        if x0 <= x <= x1 and x1 > x0:
            return y0 + (y1 - y0) * (x - x0) / (x1 - x0)
    return puntos[-1][1]


def _arq_montanas(esc, rng, p, colocar):
    lejos = _picos(rng, 0.82 * ALTO, 110, 260, 50, 100)
    cerca = _picos(rng, 0.93 * ALTO, 40, 110, 40, 80)
    esc.poligono(lejos + [(lejos[-1][0], ALTO), (0, ALTO)], p["lejos"])
    colocar(lambda x: _interp(cerca, x))
    esc.poligono(cerca + [(cerca[-1][0], ALTO), (0, ALTO)], p["cerca"])


def _arq_llanura(esc, rng, p, colocar):
    lejos = _colina(rng, 0.80, 8)
    cerca = _colina(rng, 0.92, 12)
    esc.poligono(_puntos_colina(lejos), p["lejos"])
    # línea de álamos en el horizonte
    for _ in range(rng.randint(3, 5)):
        x = rng.uniform(0.1, 0.9) * ANCHO
        y, h = _altura(lejos, x), rng.uniform(46, 86)
        esc.poligono([(x - 5, y), (x - 7, y - h * 0.5), (x, y - h), (x + 7, y - h * 0.5), (x + 5, y)], p["cerca"])
    colocar(lambda x: _altura(lejos, x))
    esc.poligono(_puntos_colina(cerca), p["cerca"])


def _arq_marina(esc, rng, p, colocar):
    cabo = _colina(rng, 0.72, 20)
    linea = (0.79 + rng.nivel * 0.5) * ALTO
    rng.linea = linea
    esc.poligono([(0, linea), (ANCHO, linea), (ANCHO, ALTO), (0, ALTO)], p["lejos"])
    colocar(lambda x: _altura(cabo, x))
    esc.poligono(_puntos_colina(cabo)[:-2] + [(ANCHO, linea), (0, linea)], p["cerca"])
    # reflejo del sol en el agua: rayas de largo decreciente
    cx = rng.uniform(0.3, 0.7) * ANCHO
    for k in range(5):
        y = linea + 50 + 52 * k
        w = 56 - 9 * k
        esc.poligono([(cx - w, y), (cx + w, y), (cx + w, y + 4), (cx - w, y + 4)], p["sol"])


def _arq_bosque(esc, rng, p, colocar):
    lejos = _colina(rng, 0.78, 26)
    esc.poligono(_puntos_colina(lejos), p["lejos"])
    base = (0.94 + rng.nivel / 4) * ALTO
    # El motivo se apoya al pie del bosque: los árboles de delante tapan su base.
    colocar(lambda x: base - 60)
    x = -12.0
    puntos = [(0, ALTO)]
    while x < ANCHO + 12:
        h = rng.uniform(80, 190)
        a = rng.uniform(13, 24)
        puntos += [(x - a, base), (x, base - h), (x + a, base)]
        x += rng.uniform(22, 40)
    puntos.append((ANCHO, ALTO))
    esc.poligono(puntos, p["cerca"])


ARQUETIPOS = {
    "colinas": _arq_colinas,
    "montanas": _arq_montanas,
    "llanura": _arq_llanura,
    "marina": _arq_marina,
    "bosque": _arq_bosque,
}
NOMBRES_ARQUETIPOS = list(ARQUETIPOS)


# --- Franja reservada para texto -------------------------------------------------
FRANJA_X = (16, ANCHO - 16)  # 168 px de ancho
FRANJA_ALTO = 420
FRANJAS_Y = ((440, "medio"), (110, "arriba"), (700, "abajo"))  # orden de preferencia
MARGEN_FRANJA = 20


def elegir_franja(obstaculos):
    """Primera franja vertical, por orden de preferencia, que no toca ningún obstáculo
    (y0, y1). Casi siempre es la central: el cielo y los motivos de cielo quedan arriba y
    los de suelo abajo. None si no cabe."""
    for y0, _ in FRANJAS_Y:
        y1 = y0 + FRANJA_ALTO
        if all(o1 < y0 - MARGEN_FRANJA or o0 > y1 + MARGEN_FRANJA for o0, o1 in obstaculos):
            return (FRANJA_X[0], y0, FRANJA_X[1], y1)
    return None


# --- Adornos propios de cada movimiento -----------------------------------------------
# Se dibujan al final, ya elegida la franja de texto, y la respetan.

def _adorno_gotico(esc, rng, p, franja, motivo):
    """Niebla en bandas bajas y arco ojival que enmarca la parte alta, como una ventana."""
    y = rng.uniform(0.64, 0.7) * ALTO
    while y < 0.92 * ALTO:
        alto = rng.uniform(22, 44)
        esc.velo(0, round(y, 1), ANCHO, round(y + alto, 1), p["sol"], round(rng.uniform(0.10, 0.2), 2), 0)
        y += alto + rng.uniform(40, 90)
    # Arco ojival equilátero: dos arcos de radio igual al ancho, con centros en los arranques
    # opuestos; la clave queda a 173 px sobre el arranque. Se rellenan las enjutas.
    r, arranque, n = ANCHO, 220, 14
    izq = [(round(r - r * math.cos(math.radians(60 * k / n)), 1), round(arranque - r * math.sin(math.radians(60 * k / n)), 1))
           for k in range(n + 1)]  # del arranque izquierdo a la clave
    der = [(round(ANCHO - x, 1), y) for x, y in izq]  # del arranque derecho a la clave
    esc.poligono([(0, 0), (ANCHO, 0), (ANCHO, arranque)] + der + list(reversed(izq)), p["cerca"])


def _adorno_magico(esc, rng, p, franja, motivo):
    """Mariposas amarillas que acompañan a la pieza, fuera de la franja de texto."""
    if motivo in ("mariposas", "petalos"):
        return
    oro = color_oro(p)
    y0, y1 = (franja[1] - 24, franja[3] + 24) if franja else (-1, -1)
    for _ in range(rng.randint(4, 6)):
        y = rng.uniform(0.04, 0.9) * ALTO
        x = rng.uniform(0.12, 0.88) * ANCHO
        if y0 <= y <= y1:
            continue
        _mariposa(esc, x, y, rng.uniform(8, 13), oro)


ADORNOS = {"romanticismo": None, "gotico": _adorno_gotico, "realismo-magico": _adorno_magico}


def _paisaje(movimiento):
    def construir(semilla, paleta, motivo, grano=0.0, arquetipo="colinas", ocultar_motivo=False):
        rng = random.Random(semilla)
        rng.nivel = rng.uniform(-0.07, 0.07)  # desplaza todo el horizonte: cambia la silueta global
        rng.ocultar = ocultar_motivo
        p = paleta_legible(paleta)
        esc = Escena(semilla=semilla, grano=grano)
        # Cielo claro arriba o abajo: cambia mucho la huella visual sin cambiar la paleta.
        if rng.random() < 0.4:
            esc.degradado(p["cielo_bajo"], p["cielo_alto"])
        else:
            esc.degradado(p["cielo_alto"], p["cielo_bajo"])

        if motivo == "luna":
            cx, cy, r = rng.uniform(0.3, 0.6) * ANCHO, rng.uniform(0.10, 0.22) * ALTO, 46
        else:
            cx, cy, r = rng.uniform(0.3, 0.7) * ANCHO, rng.uniform(0.08, 0.24) * ALTO, rng.uniform(20, 30)
        esc.circulo(cx, cy, r, p["sol"])
        obstaculos = [(cy - r, cy + r)]

        suelos = []
        entre_capas = (lambda altura: suelos.append(altura)) if motivo in FRENTE else (
            lambda altura: (suelos.append(altura), _poner_motivo(esc, rng, p, altura, motivo)))
        ARQUETIPOS[arquetipo](esc, rng, p, entre_capas)
        if motivo in FRENTE:
            _poner_motivo(esc, rng, p, suelos[0], motivo)
        bbox = getattr(rng, "bbox_motivo", None)
        esc.bbox_motivo = bbox
        if bbox and motivo not in AMBIENTALES:
            obstaculos.append((bbox[1], bbox[3]))
        esc.franja = elegir_franja(obstaculos)
        if ADORNOS[movimiento]:
            ADORNOS[movimiento](esc, rng, p, esc.franja, motivo)
        return esc
    return construir


CONSTRUCTORES = {m: _paisaje(m) for m in ADORNOS}
