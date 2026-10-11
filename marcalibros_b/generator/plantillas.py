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
    rng.nivel = rng.uniform(-0.12, 0.12)  # desplaza todo el horizonte: cambia la silueta global
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
