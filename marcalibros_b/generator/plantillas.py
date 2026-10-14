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
    "tormenta": {
        "cielo_alto": "#2A2F3D", "cielo_bajo": "#6C7388", "sol": "#D8DCE6",
        "lejos": "#4B5265", "cerca": "#1D212C", "figura": "#0E1016",
    },
}


def _colina(rng, base, amp):
    return {
        "base": (base + rng.nivel) * ALTO,
        "amp": amp,
        "fase": rng.uniform(0, 2 * math.pi),
        "frec": rng.uniform(1.5, 3.0),
    }


def _altura(c, x):
    t = 2 * math.pi * x / ANCHO
    return (c["base"]
            + c["amp"] * math.sin(c["frec"] * t + c["fase"])
            + 0.5 * c["amp"] * math.sin(3.7 * c["frec"] * t))


def _puntos_colina(c):
    puntos = [(x, round(_altura(c, x), 1)) for x in range(0, ANCHO + 1, 14)]
    return puntos + [(ANCHO, ALTO), (0, ALTO)]


def _torre(esc, rng, p, lejos):
    """Campanario de iglesia con cuerpo, campanario escalonado y chapitel."""
    x = rng.uniform(0.62, 0.86) * ANCHO
    y = lejos(x)
    esc.poligono([
        (x - 9, y), (x - 9, y - 50), (x - 12, y - 50), (x - 12, y - 64),
        (x - 4, y - 64), (x - 4, y - 72), (x, y - 86), (x + 4, y - 72),
        (x + 4, y - 64), (x + 12, y - 64), (x + 12, y - 50), (x + 9, y - 50),
        (x + 9, y),
    ], p["figura"])


def _golondrinas(esc, rng, p, lejos):
    """Tres a cinco golondrinas en el cielo, guiño a las Rimas."""
    for _ in range(rng.randint(3, 5)):
        x = rng.uniform(0.1, 0.9) * ANCHO
        y = rng.uniform(0.12, 0.38) * ALTO
        s = rng.uniform(10, 16)
        esc.poligono([(x - s, y - s * 0.45), (x, y), (x + s, y - s * 0.45), (x, y - s * 0.25)], p["figura"])


def _rio(esc, rng, p, lejos):
    """Banda de orilla de río en la base, con borde ondulado y grosor fijo."""
    fase = rng.uniform(0, 2 * math.pi)
    arriba = [(x, round(0.93 * ALTO + 2.5 * math.sin(x / 30 + fase), 1)) for x in range(0, ANCHO + 1, 35)]
    abajo = [(x, round(y + 7, 1)) for x, y in reversed(arriba)]
    esc.poligono(arriba + abajo, p["lejos"])


MOTIVOS = {"torre": _torre, "golondrinas": _golondrinas, "rio": _rio}


def _cuervo(esc, rng, p, altura):
    """Cuervo grande en el cielo, con las alas abiertas."""
    x, y, s = rng.uniform(0.2, 0.8) * ANCHO, rng.uniform(0.2, 0.4) * ALTO, rng.uniform(26, 34)
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
    for _ in range(rng.randint(14, 24)):
        esc.circulo(round(rng.uniform(0.02, 0.98) * ANCHO, 1), round(rng.uniform(0.05, 0.5) * ALTO, 1),
                    round(rng.uniform(0.9, 2.1), 1), p["sol"])


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
    x, y, s = rng.uniform(0.2, 0.8) * ANCHO, rng.uniform(0.28, 0.4) * ALTO, 16
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
    x0 = rng.uniform(0.1, 0.7) * ANCHO
    for k in range(rng.randint(5, 7)):
        x, w, h = x0 + k * rng.uniform(22, 30), rng.uniform(10, 14), rng.uniform(14, 28)
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
    """Crestas de ola rizadas en la parte baja (solo en escenas marina)."""
    x = rng.uniform(0, 0.15) * ANCHO
    while x < ANCHO:
        y = rng.uniform(0.8, 0.92) * ALTO
        w = rng.uniform(46, 80)
        esc.poligono([(x, y), (x + w * 0.35, y - 16), (x + w * 0.62, y - 22), (x + w * 0.8, y - 14),
                      (x + w * 0.68, y - 15), (x + w * 0.52, y - 9), (x + w * 0.3, y - 2)], p["sol"])
        x += w + rng.uniform(30, 90)


def _nube(esc, rng, p, altura):
    """Nube de bordes redondeados."""
    x, y = rng.uniform(0.15, 0.85) * ANCHO, rng.uniform(0.2, 0.32) * ALTO
    for dx, dy, r in ((-26, 4, 12), (-10, -6, 16), (10, -4, 18), (28, 4, 13), (0, 6, 14)):
        esc.circulo(round(x + dx, 1), round(y + dy, 1), r, p["sol"])
    esc.poligono([(x - 36, y + 12), (x - 36, y + 18), (x + 40, y + 18), (x + 40, y + 12)], p["sol"])


def _narcisos(esc, rng, p, altura):
    """Narcisos agrupados al pie del paisaje."""
    x0 = rng.uniform(0.05, 0.7) * ANCHO
    for _ in range(rng.randint(9, 14)):
        x = x0 + rng.uniform(0, 260)
        y, h = ALTO - 1, rng.uniform(22, 40)
        esc.poligono([(x - 0.9, y), (x - 0.9, y - h), (x + 0.9, y - h), (x + 0.9, y)], p["figura"])
        for k in range(6):
            a = 2 * math.pi * k / 6
            esc.circulo(round(x + 5 * math.cos(a), 1), round(y - h + 5 * math.sin(a), 1), 3.6, p["sol"])
        esc.circulo(x, y - h, 3, p["figura"])


def _hojas(esc, rng, p, altura):
    """Hojas que caen."""
    for _ in range(rng.randint(16, 24)):
        x, y, s = rng.uniform(0.02, 0.98) * ANCHO, rng.uniform(0.05, 0.8) * ALTO, rng.uniform(4, 7)
        a = rng.uniform(0, math.pi)
        c, sn = math.cos(a), math.sin(a)
        esc.poligono([(round(x + c * s * 1.4, 1), round(y + sn * s * 1.4, 1)),
                      (round(x - sn * s * 0.7, 1), round(y + c * s * 0.7, 1)),
                      (round(x - c * s * 1.4, 1), round(y - sn * s * 1.4, 1)),
                      (round(x + sn * s * 0.7, 1), round(y - c * s * 0.7, 1))], p["sol"])


def _serpiente(esc, rng, p, altura):
    """Serpiente ondulante sobre el suelo."""
    x0 = rng.uniform(0.1, 0.7) * ANCHO
    base = _suelo(altura, x0) - 4
    arriba, abajo = [], []
    for k in range(0, 41):
        x = x0 + k * 3.5
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
    x, y = rng.uniform(0.2, 0.8) * ANCHO, rng.uniform(0.3, 0.42) * ALTO
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


# --- Arquetipos de composición -------------------------------------------------
# Cada uno dibuja el paisaje y devuelve la función altura(x) de la capa lejana,
# donde se apoyan los motivos (torre, etc.).

def _arq_colinas(esc, rng, p):
    lejos = _colina(rng, 0.66, 14)
    cerca = _colina(rng, 0.80, 18)
    esc.poligono(_puntos_colina(lejos), p["lejos"])
    esc.poligono(_puntos_colina(cerca), p["cerca"])
    return lambda x: _altura(lejos, x)


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


def _arq_montanas(esc, rng, p):
    lejos = _picos(rng, 0.78 * ALTO, 40, 95, 90, 190)
    cerca = _picos(rng, 0.92 * ALTO, 14, 40, 60, 140)
    esc.poligono(lejos + [(lejos[-1][0], ALTO), (0, ALTO)], p["lejos"])
    esc.poligono(cerca + [(cerca[-1][0], ALTO), (0, ALTO)], p["cerca"])
    return lambda x: _interp(lejos, x)


def _arq_llanura(esc, rng, p):
    lejos = _colina(rng, 0.74, 3)
    cerca = _colina(rng, 0.90, 5)
    esc.poligono(_puntos_colina(lejos), p["lejos"])
    esc.poligono(_puntos_colina(cerca), p["cerca"])
    # línea de álamos en el horizonte
    for _ in range(rng.randint(5, 9)):
        x = rng.uniform(0.04, 0.96) * ANCHO
        y, h = _altura(lejos, x), rng.uniform(16, 30)
        esc.poligono([(x - 3, y), (x - 4, y - h * 0.5), (x, y - h), (x + 4, y - h * 0.5), (x + 3, y)], p["cerca"])
    return lambda x: _altura(lejos, x)


def _arq_marina(esc, rng, p):
    cabo = _colina(rng, 0.54, 7)
    linea = (0.62 + rng.nivel) * ALTO
    rng.linea = linea
    esc.poligono([(0, linea), (ANCHO, linea), (ANCHO, ALTO), (0, ALTO)], p["lejos"])
    esc.poligono(_puntos_colina(cabo)[:-2] + [(ANCHO, linea), (0, linea)], p["cerca"])
    # reflejo del sol en el agua: rayas de largo decreciente
    cx = rng.uniform(0.3, 0.7) * ANCHO
    for k in range(5):
        y = linea + (0.06 + 0.055 * k) * ALTO
        w = 70 - 10 * k
        esc.poligono([(cx - w, y), (cx + w, y), (cx + w, y + 3), (cx - w, y + 3)], p["sol"])
    return lambda x: _altura(cabo, x)


def _arq_bosque(esc, rng, p):
    lejos = _colina(rng, 0.70, 12)
    esc.poligono(_puntos_colina(lejos), p["lejos"])
    x = -10.0
    puntos = [(0, ALTO)]
    while x < ANCHO + 10:
        h = rng.uniform(32, 74)
        a = rng.uniform(9, 16)
        base = (0.93 + rng.nivel / 2) * ALTO
        puntos += [(x - a, base), (x, base - h), (x + a, base)]
        x += rng.uniform(14, 30)
    puntos.append((ANCHO, ALTO))
    esc.poligono(puntos, p["cerca"])
    return lambda x: _altura(lejos, x)


ARQUETIPOS = {
    "colinas": _arq_colinas,
    "montanas": _arq_montanas,
    "llanura": _arq_llanura,
    "marina": _arq_marina,
    "bosque": _arq_bosque,
}
NOMBRES_ARQUETIPOS = list(ARQUETIPOS)


def _romanticismo(semilla, paleta, motivo, grano=0.0, arquetipo="colinas"):
    rng = random.Random(semilla)
    rng.nivel = rng.uniform(-0.16, 0.16)  # desplaza todo el horizonte: cambia la silueta global
    p = PALETAS[paleta]
    esc = Escena(semilla=semilla, grano=grano)
    # Cielo claro arriba o abajo: cambia mucho la huella visual sin cambiar la paleta.
    if rng.random() < 0.4:
        esc.degradado(p["cielo_bajo"], p["cielo_alto"])
    else:
        esc.degradado(p["cielo_alto"], p["cielo_bajo"])

    if motivo == "luna":
        esc.circulo(rng.uniform(0.2, 0.35) * ANCHO, rng.uniform(0.2, 0.3) * ALTO, 34, p["sol"])
    else:
        esc.circulo(rng.uniform(0.3, 0.7) * ANCHO, rng.uniform(0.3, 0.45) * ALTO, rng.uniform(14, 20), p["sol"])

    altura = ARQUETIPOS[arquetipo](esc, rng, p)

    if motivo in MOTIVOS:
        MOTIVOS[motivo](esc, rng, p, altura)
    return esc


CONSTRUCTORES = {"romanticismo": _romanticismo}
