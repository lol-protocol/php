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
}


def _colina(rng, base, amp):
    return {
        "base": base * ALTO,
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
    y = _altura(lejos, x)
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


def _romanticismo(semilla, paleta, motivo, grano=0.0):
    rng = random.Random(semilla)
    p = PALETAS[paleta]
    esc = Escena(semilla=semilla, grano=grano)
    esc.degradado(p["cielo_alto"], p["cielo_bajo"])

    if motivo == "luna":
        esc.circulo(rng.uniform(0.2, 0.35) * ANCHO, rng.uniform(0.2, 0.3) * ALTO, 34, p["sol"])
    else:
        esc.circulo(rng.uniform(0.3, 0.7) * ANCHO, rng.uniform(0.3, 0.45) * ALTO, rng.uniform(14, 20), p["sol"])

    lejos = _colina(rng, 0.66, 14)
    cerca = _colina(rng, 0.80, 18)
    esc.poligono(_puntos_colina(lejos), p["lejos"])
    esc.poligono(_puntos_colina(cerca), p["cerca"])

    if motivo in MOTIVOS:
        MOTIVOS[motivo](esc, rng, p, lejos)
    return esc


CONSTRUCTORES = {"romanticismo": _romanticismo}
