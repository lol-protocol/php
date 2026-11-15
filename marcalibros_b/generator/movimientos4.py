"""Paletas, motivos y adornos de los cinco movimientos de la cuarta tanda: Renacimiento,
Ilustración, Expresionismo, Surrealismo y Literaturas de Oriente.

Mismo contrato que movimientos.py: cada motivo recibe (esc, rng, p, altura) y dibuja con
las primitivas de scene.py sobre un lienzo de 200×1400; plantillas.py los escala y encaja.
Solo depende de scene.py y de movimientos.py (sus ayudas), así que no hay ciclos.
"""
import math

from movimientos import _arco_grueso, _borde, _suelo, _x
from scene import ALTO, ANCHO, suave

PALETAS_NUEVAS = {
    # Renacimiento
    "toscana": {"cielo_alto": "#5C7FA8", "cielo_bajo": "#F2D9A6", "sol": "#FFF3D2",
                "lejos": "#A6A877", "cerca": "#5A6B3C", "figura": "#2A2A14"},
    "lapislazuli": {"cielo_alto": "#14306E", "cielo_bajo": "#7E9CD6", "sol": "#F5E3A5",
                    "lejos": "#3C5FA8", "cerca": "#0E2050", "figura": "#060F2E"},
    "fresco": {"cielo_alto": "#8C6B4A", "cielo_bajo": "#EBD3B0", "sol": "#FFF5E0",
               "lejos": "#C29E78", "cerca": "#6B4A32", "figura": "#2E1C10"},
    # Ilustración
    "peluca": {"cielo_alto": "#59657A", "cielo_bajo": "#E3E6EC", "sol": "#FFFFFF",
               "lejos": "#9AA5B8", "cerca": "#3B4658", "figura": "#1A212E"},
    "salon": {"cielo_alto": "#B8809A", "cielo_bajo": "#F7E6DC", "sol": "#FFF8EE",
              "lejos": "#D9AFA9", "cerca": "#7A4E62", "figura": "#3A1F2E"},
    "razon": {"cielo_alto": "#183B6B", "cielo_bajo": "#CFE3F4", "sol": "#FFFBE0",
              "lejos": "#5C8CBF", "cerca": "#1B4170", "figura": "#0B1E3A"},
    # Expresionismo
    "grito": {"cielo_alto": "#B53A12", "cielo_bajo": "#F2A31B", "sol": "#FFF1B8",
              "lejos": "#A24585", "cerca": "#1E1A5E", "figura": "#0B0A2E"},
    "bilis": {"cielo_alto": "#26421F", "cielo_bajo": "#B8CC4A", "sol": "#F2F7C0",
              "lejos": "#5E7A2A", "cerca": "#1A2A14", "figura": "#0A1408"},
    "brasa": {"cielo_alto": "#2A0A0A", "cielo_bajo": "#E0431F", "sol": "#FFE08A",
              "lejos": "#B52E20", "cerca": "#120505", "figura": "#050202"},
    # Surrealismo
    "delirio": {"cielo_alto": "#B5431A", "cielo_bajo": "#F6C36B", "sol": "#FFF6D6",
                "lejos": "#C9803C", "cerca": "#6B3414", "figura": "#2A1206"},
    "vigilia": {"cielo_alto": "#2F6FB5", "cielo_bajo": "#CDE6F7", "sol": "#FFFFFF",
                "lejos": "#7FA9D1", "cerca": "#3B5A3C", "figura": "#10202A"},
    "metamorfosis": {"cielo_alto": "#3A1A5E", "cielo_bajo": "#F29A8A", "sol": "#FFE9C4",
                     "lejos": "#8A4A8E", "cerca": "#1E0E36", "figura": "#0E061C"},
    # Literaturas de Oriente
    "sumi": {"cielo_alto": "#8C8C84", "cielo_bajo": "#F0ECDF", "sol": "#FFFFFF",
             "lejos": "#B8B6AA", "cerca": "#5A5A54", "figura": "#14140F"},
    "bermellon": {"cielo_alto": "#8A1410", "cielo_bajo": "#F0C27A", "sol": "#FFF2CF",
                  "lejos": "#C0392B", "cerca": "#3E0A08", "figura": "#1A0403"},
    "celadon": {"cielo_alto": "#4F7A6B", "cielo_bajo": "#E3F0E4", "sol": "#FFFFF0",
                "lejos": "#8FB8A4", "cerca": "#2E5A4B", "figura": "#10281F"},
    "indigo": {"cielo_alto": "#101C4A", "cielo_bajo": "#6E86C8", "sol": "#F4EBC8",
               "lejos": "#5C74C4", "cerca": "#0A1236", "figura": "#040920"},
}


def _p(pts):
    return [(round(x, 1), round(y, 1)) for x, y in pts]


# --- Renacimiento -------------------------------------------------------------------------

def _cupula(esc, rng, p, altura):
    """Cúpula con linterna sobre tambor y base: el Duomo, en silueta."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    esc.poligono([(x - 34, y), (x - 34, y - 26), (x + 34, y - 26), (x + 34, y)], p["figura"])
    esc.poligono([(x - 26, y - 26), (x - 26, y - 44), (x + 26, y - 44), (x + 26, y - 26)], p["figura"])
    arco = [(x - 26 + 52 * k / 16, y - 44 - 34 * math.sin(math.pi * k / 16)) for k in range(17)]
    esc.poligono(_p(arco), p["figura"])
    for k in (-14, 0, 14):
        esc.poligono([(x + k - 3, y - 30), (x + k - 3, y - 40), (x + k + 3, y - 40), (x + k + 3, y - 30)], p["sol"])
    esc.poligono([(x - 5, y - 78), (x - 5, y - 92), (x + 5, y - 92), (x + 5, y - 78)], p["figura"])
    esc.poligono([(x - 5, y - 92), (x, y - 104), (x + 5, y - 92)], p["figura"])
    esc.poligono([(x - 0.8, y - 104), (x - 0.8, y - 114), (x + 0.8, y - 114), (x + 0.8, y - 104)], p["figura"])


def _compas(esc, rng, p, altura):
    """Compás abierto sobre un círculo: la geometría del hombre de Vitruvio."""
    x, y = _x(rng, 0.4, 0.6), rng.uniform(0.16, 0.3) * ALTO
    esc.poligono([(x - 2, y - 40), (x - 24, y + 42), (x - 18, y + 44), (x + 2, y - 30)], p["figura"])
    esc.poligono([(x + 2, y - 40), (x + 24, y + 42), (x + 18, y + 44), (x - 2, y - 30)], p["figura"])
    esc.circulo(x, y - 40, 5, p["figura"])
    esc.circulo(x, y - 40, 2, p["sol"])
    _arco_grueso(esc, x, y + 44, 26, 2.4, 190, 350, p["sol"])


def _hombre_vitruvio(esc, rng, p, altura):
    """Figura humana con brazos abiertos inscrita en un círculo."""
    x, y = _x(rng, 0.4, 0.6), rng.uniform(0.16, 0.3) * ALTO
    _arco_grueso(esc, x, y, 44, 2.4, 0, 360, p["figura"], 28)
    esc.circulo(x, y - 24, 6, p["figura"])
    esc.poligono([(x - 5, y - 18), (x + 5, y - 18), (x + 7, y + 8), (x + 10, y + 36), (x + 3, y + 36), (x, y + 14),
                  (x - 3, y + 36), (x - 10, y + 36), (x - 7, y + 8)], p["figura"])
    esc.poligono([(x - 40, y - 8), (x - 5, y - 16), (x + 5, y - 16), (x + 40, y - 8), (x + 40, y - 4), (x + 5, y - 10),
                  (x - 5, y - 10), (x - 40, y - 4)], p["figura"])


# --- Ilustración --------------------------------------------------------------------------

def _globo(esc, rng, p, altura):
    """Globo aerostático (los Montgolfier, 1783) con su cesta."""
    x, y = _x(rng, 0.35, 0.65), rng.uniform(0.14, 0.3) * ALTO
    r = 26
    esc.circulo(x, y, r, p["figura"])
    esc.poligono([(x - r + 4, y + 12), (x - 7, y + 40), (x + 7, y + 40), (x + r - 4, y + 12)], p["figura"])
    esc.poligono([(x - 5, y - r), (x - 12, y - 2), (x - 5, y + 20), (x + 5, y + 20), (x + 12, y - 2), (x + 5, y - r)], p["sol"])
    esc.poligono([(x - 7, y + 40), (x - 5, y + 52), (x + 5, y + 52), (x + 7, y + 40)], p["figura"])
    esc.poligono([(x - 7, y + 52), (x - 7, y + 62), (x + 7, y + 62), (x + 7, y + 52)], p["figura"])


def _balanza(esc, rng, p, altura):
    """Balanza de platillos: la justicia y la razón."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    esc.poligono([(x - 14, y), (x - 10, y - 6), (x + 10, y - 6), (x + 14, y)], p["figura"])
    esc.poligono([(x - 2, y - 6), (x - 2, y - 70), (x + 2, y - 70), (x + 2, y - 6)], p["figura"])
    esc.poligono([(x - 36, y - 74), (x + 36, y - 74), (x + 36, y - 70), (x - 36, y - 70)], p["figura"])
    esc.circulo(x, y - 72, 4, p["figura"])
    for sx in (-36, 36):
        esc.poligono([(x + sx - 0.8, y - 72), (x + sx - 14, y - 40), (x + sx - 12, y - 40), (x + sx, y - 72)], p["figura"])
        esc.poligono([(x + sx + 0.8, y - 72), (x + sx + 14, y - 40), (x + sx + 12, y - 40), (x + sx, y - 72)], p["figura"])
        pl = [(x + sx - 15 + 30 * k / 10, y - 40 + 8 * math.sin(math.pi * k / 10)) for k in range(11)]
        esc.poligono(_p(pl), p["figura"])


def _telescopio(esc, rng, p, altura):
    """Telescopio sobre trípode, apuntado al cielo."""
    x = _x(rng, 0.35, 0.65)
    y = _suelo(altura, x)
    for dx in (-20, 0, 20):
        esc.poligono([(x - 1.5, y - 40), (x + 1.5, y - 40), (x + dx + 1.5, y), (x + dx - 1.5, y)], p["figura"])
    esc.poligono([(x - 18, y - 36), (x - 14, y - 44), (x + 40, y - 84), (x + 46, y - 74)], p["figura"])
    esc.poligono([(x + 34, y - 88), (x + 50, y - 78), (x + 46, y - 70), (x + 30, y - 80)], p["figura"])


# --- Expresionismo ------------------------------------------------------------------------

def _grito(esc, rng, p, altura):
    """Figura ondulante con las manos en la cara y la boca abierta."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    cuerpo = suave([(x - 4, y), (x - 8, y - 30), (x - 2, y - 56), (x - 10, y - 74), (x - 4, y - 90)], 5)
    der = suave([(x + 6, y - 90), (x + 12, y - 74), (x + 4, y - 56), (x + 12, y - 30), (x + 8, y)], 5)
    esc.poligono(_p(cuerpo + der), p["figura"])
    esc.circulo(x + 1, y - 104, 15, p["figura"])
    esc.poligono([(x - 6, y - 98), (x + 4, y - 98), (x + 4, y - 92), (x - 6, y - 92)], p["sol"])  # boca abierta
    esc.circulo(x - 5, y - 108, 2.6, p["sol"])
    esc.circulo(x + 6, y - 108, 2.6, p["sol"])
    esc.poligono([(x - 14, y - 94), (x - 22, y - 106), (x - 18, y - 110), (x - 8, y - 98)], p["figura"])
    esc.poligono([(x + 14, y - 94), (x + 22, y - 106), (x + 18, y - 110), (x + 8, y - 98)], p["figura"])


def _casas_torcidas(esc, rng, p, altura):
    """Casas inclinadas, de paredes y tejados que no cuadran (el Caligari)."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    for dx, h, inc in ((-42, 66, 9), (-8, 94, -7), (28, 74, 12)):
        b = x + dx
        esc.poligono(_p([(b - 14, y), (b - 14 + inc, y - h), (b + 14 + inc, y - h), (b + 14, y)]), p["figura"])
        esc.poligono(_p([(b - 18 + inc, y - h), (b + inc, y - h - 22), (b + 18 + inc, y - h)]), p["figura"])
        esc.poligono(_p([(b - 4 + inc / 2, y - h * 0.45), (b - 4 + inc / 2, y - h * 0.3),
                         (b + 4 + inc / 2, y - h * 0.3), (b + 4 + inc / 2, y - h * 0.45)]), p["sol"])


# --- Surrealismo --------------------------------------------------------------------------

def _reloj_blando(esc, rng, p, altura):
    """Reloj derretido, colgando de una rama como una tela."""
    x = _x(rng, 0.35, 0.65)
    y = _suelo(altura, x)
    esc.poligono([(x - 3, y), (x - 3, y - 70), (x + 3, y - 70), (x + 3, y)], p["figura"])
    esc.poligono([(x - 3, y - 70), (x + 26, y - 74), (x + 28, y - 70), (x + 3, y - 66)], p["figura"])
    borde = suave([(x + 6, y - 72), (x + 30, y - 60), (x + 46, y - 66), (x + 52, y - 44), (x + 44, y - 26),
                   (x + 28, y - 34), (x + 14, y - 22), (x + 4, y - 44), (x + 6, y - 72)], 4)
    esc.poligono(_p(borde), p["figura"])
    esc.circulo(x + 28, y - 48, 3, p["sol"])
    esc.poligono([(x + 27, y - 48), (x + 27, y - 60), (x + 29, y - 60), (x + 29, y - 48)], p["sol"])
    esc.poligono([(x + 28, y - 49), (x + 38, y - 44), (x + 37, y - 42), (x + 27, y - 47)], p["sol"])


def _puerta(esc, rng, p, altura):
    """Puerta suelta en mitad del paisaje, abierta a otro cielo."""
    x = _x(rng, 0.35, 0.65)
    y = _suelo(altura, x)
    esc.poligono([(x - 24, y), (x - 24, y - 96), (x + 24, y - 96), (x + 24, y)], p["figura"])
    esc.poligono([(x - 18, y), (x - 18, y - 90), (x + 18, y - 90), (x + 18, y)], p["sol"])
    esc.poligono([(x - 18, y), (x - 18, y - 90), (x - 34, y - 84), (x - 34, y + 6)], p["figura"])
    esc.circulo(x - 30, y - 44, 2, p["sol"])


def _bombin(esc, rng, p, altura):
    """Sombrero hongo flotando, como el de Magritte."""
    x, y = _x(rng, 0.35, 0.65), rng.uniform(0.14, 0.3) * ALTO
    esc.poligono(_p([(x - 28, y + 14), (x - 28, y + 10), (x - 18, y + 6), (x - 16, y - 8), (x - 8, y - 20), (x + 8, y - 20),
                     (x + 16, y - 8), (x + 18, y + 6), (x + 28, y + 10), (x + 28, y + 14)]), p["figura"])
    esc.poligono([(x - 17, y + 4), (x + 17, y + 4), (x + 17, y + 0), (x - 17, y + 0)], p["sol"])


# --- Literaturas de Oriente ---------------------------------------------------------------

def _pagoda(esc, rng, p, altura):
    """Pagoda de cinco pisos con aleros curvos."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    base = y
    for k in range(5):
        w = 30 - k * 4
        h = 16
        top = base - h
        esc.poligono([(x - w + 6, base), (x - w + 6, top), (x + w - 6, top), (x + w - 6, base)], p["figura"])
        alero = [(x - w - 6, top + 2), (x - w + 4, top - 2), (x, top - 8), (x + w - 4, top - 2), (x + w + 6, top + 2),
                 (x + w - 4, top + 5), (x - w + 4, top + 5)]
        esc.poligono(_p(alero), p["figura"])
        esc.poligono([(x - 3, base - 2), (x - 3, top + 3), (x + 3, top + 3), (x + 3, base - 2)], p["sol"])
        base = top - 6
    esc.poligono([(x - 1, base), (x - 1, base - 18), (x + 1, base - 18), (x + 1, base)], p["figura"])


def _grulla(esc, rng, p, altura):
    """Grulla en vuelo, de alas desplegadas y cuello largo."""
    x, y = _x(rng, 0.35, 0.65), rng.uniform(0.14, 0.3) * ALTO
    esc.poligono(_p([(x - 10, y + 2), (x + 8, y - 2), (x + 30, y - 10), (x + 44, y - 4), (x + 28, y + 4), (x + 8, y + 8)]), p["figura"])
    esc.poligono(_p([(x - 4, y), (x - 20, y - 30), (x - 44, y - 40), (x - 22, y - 22), (x + 4, y - 4)]), p["figura"])
    esc.poligono(_p([(x - 4, y + 2), (x - 20, y + 30), (x - 40, y + 36), (x - 18, y + 20), (x + 4, y + 6)]), p["figura"])
    esc.poligono(_p([(x + 30, y - 10), (x + 56, y - 20), (x + 70, y - 18), (x + 56, y - 14), (x + 38, y - 6)]), p["figura"])
    esc.poligono(_p([(x + 70, y - 18), (x + 82, y - 16), (x + 70, y - 15)]), p["figura"])
    esc.poligono(_p([(x - 10, y + 2), (x - 30, y + 6), (x - 42, y + 12), (x - 30, y + 8), (x - 8, y + 6)]), p["figura"])


def _farolillo(esc, rng, p, altura):
    """Farolillo de papel colgado de un cordel, con aros y borla."""
    x, y = _x(rng, 0.35, 0.65), rng.uniform(0.12, 0.3) * ALTO
    esc.poligono([(x - 0.8, y - 40), (x - 0.8, y), (x + 0.8, y), (x + 0.8, y - 40)], p["figura"])
    esc.poligono([(x - 10, y), (x + 10, y), (x + 10, y + 4), (x - 10, y + 4)], p["figura"])
    esc.poligono(_p([(x - 10, y + 4), (x + 10, y + 4), (x + 18, y + 14), (x + 20, y + 32), (x + 18, y + 50), (x + 10, y + 60),
                     (x - 10, y + 60), (x - 18, y + 50), (x - 20, y + 32), (x - 18, y + 14)]), p["figura"])
    for dy in (14, 24, 34, 44):
        esc.poligono([(x - 19, y + dy), (x + 19, y + dy), (x + 19, y + dy + 1.4), (x - 19, y + dy + 1.4)], p["sol"])
    esc.poligono([(x - 10, y + 60), (x + 10, y + 60), (x + 10, y + 64), (x - 10, y + 64)], p["figura"])
    esc.poligono([(x - 1, y + 64), (x - 3, y + 84), (x + 3, y + 84), (x + 1, y + 64)], p["figura"])


def _bambu(esc, rng, p, altura):
    """Tres cañas de bambú con nudos y hojas lanceoladas."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    for dx, h in ((-14, 120), (0, 150), (14, 108)):
        esc.poligono([(x + dx - 3, y), (x + dx - 3, y - h), (x + dx + 3, y - h), (x + dx + 3, y)], p["figura"])
        for k in range(1, int(h // 24) + 1):
            esc.poligono([(x + dx - 4.5, y - k * 24), (x + dx - 4.5, y - k * 24 - 2.6), (x + dx + 4.5, y - k * 24 - 2.6), (x + dx + 4.5, y - k * 24)], p["sol"])
        for k, lado in ((2, 1), (3, -1), (4, 1)):
            yy = y - k * 24
            if yy > y - h + 10:
                esc.poligono(_p([(x + dx, yy), (x + dx + lado * 26, yy - 12), (x + dx + lado * 8, yy + 2)]), p["figura"])


def _minarete(esc, rng, p, altura):
    """Minarete esbelto con balcón y cúpula, junto a una cúpula pequeña."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    esc.poligono([(x - 7, y), (x - 5, y - 104), (x + 5, y - 104), (x + 7, y)], p["figura"])
    esc.poligono([(x - 11, y - 104), (x - 11, y - 110), (x + 11, y - 110), (x + 11, y - 104)], p["figura"])
    esc.poligono([(x - 6, y - 110), (x - 6, y - 128), (x + 6, y - 128), (x + 6, y - 110)], p["figura"])
    arco = [(x - 6 + 12 * k / 10, y - 128 - 14 * math.sin(math.pi * k / 10)) for k in range(11)]
    esc.poligono(_p(arco), p["figura"])
    esc.poligono([(x - 0.8, y - 142), (x - 0.8, y - 152), (x + 0.8, y - 152), (x + 0.8, y - 142)], p["figura"])
    esc.poligono(_p([(x + 12, y), (x + 12, y - 22), (x + 40, y - 22), (x + 40, y)]), p["figura"])
    cup = [(x + 12 + 28 * k / 14, y - 22 - 22 * math.sin(math.pi * k / 14)) for k in range(15)]
    esc.poligono(_p(cup), p["figura"])
    esc.poligono([(x + 24, y), (x + 24, y - 12), (x + 28, y - 12), (x + 28, y)], p["sol"])


def _torii(esc, rng, p, altura):
    """Torii: dos postes y dos travesaños de remate curvo."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    for dx in (-24, 24):
        esc.poligono([(x + dx - 4, y), (x + dx - 3, y - 84), (x + dx + 3, y - 84), (x + dx + 4, y)], p["figura"])
    esc.poligono(_p([(x - 40, y - 88), (x - 30, y - 94), (x + 30, y - 94), (x + 40, y - 88), (x + 40, y - 84), (x - 40, y - 84)]), p["figura"])
    esc.poligono([(x - 30, y - 74), (x + 30, y - 74), (x + 30, y - 68), (x - 30, y - 68)], p["figura"])
    esc.poligono([(x - 3, y - 84), (x + 3, y - 84), (x + 3, y - 74), (x - 3, y - 74)], p["figura"])


MOTIVOS_NUEVOS = {
    "cupula": _cupula, "compas": _compas, "hombre_vitruvio": _hombre_vitruvio,
    "globo": _globo, "balanza": _balanza, "telescopio": _telescopio,
    "grito": _grito, "casas_torcidas": _casas_torcidas,
    "reloj_blando": _reloj_blando, "puerta": _puerta, "bombin": _bombin,
    "pagoda": _pagoda, "grulla": _grulla, "farolillo": _farolillo, "bambu": _bambu, "minarete": _minarete, "torii": _torii,
}

ESCALAS_NUEVAS = {
    "cupula": (1.5, "suelo"), "compas": (1.4, "centro"), "hombre_vitruvio": (1.4, "centro"),
    "globo": (1.4, "centro"), "balanza": (1.6, "suelo"), "telescopio": (1.4, "suelo"),
    "grito": (1.6, "suelo"), "casas_torcidas": (1.4, "suelo"),
    "reloj_blando": (2.0, "suelo"), "puerta": (1.5, "suelo"), "bombin": (1.6, "centro"),
    "pagoda": (1.5, "suelo"), "grulla": (1.3, "centro"), "farolillo": (1.6, "centro"), "bambu": (1.5, "suelo"),
    "minarete": (1.3, "suelo"), "torii": (1.6, "suelo"),
}


# --- Adornos --------------------------------------------------------------------------------

def _adorno_renacimiento(esc, rng, p, franja, motivo):
    """Marco de pilastras con arco de medio punto, como una hornacina."""
    esc.velo(8, 8, 18, ALTO - 8, p["figura"], 0.45, 0)
    esc.velo(ANCHO - 18, 8, ANCHO - 8, ALTO - 8, p["figura"], 0.45, 0)
    for y in (8, ALTO - 24):
        esc.velo(6, y, 20, y + 16, p["figura"], 0.55, 2)
        esc.velo(ANCHO - 20, y, ANCHO - 6, y + 16, p["figura"], 0.55, 2)
    _arco_grueso(esc, ANCHO / 2, 92, 82, 6, 180, 360, p["figura"], 20)


def _adorno_ilustracion(esc, rng, p, franja, motivo):
    """Orla de perlas y filete fino, como la portada de una Enciclopedia."""
    _borde(esc, 7, 1, p["figura"], 0.7)
    for lado in (12, ALTO - 12):
        for x in range(18, ANCHO - 12, 12):
            esc.circulo(x, lado, 2, p["figura"])
    for lado in (12, ANCHO - 12):
        for y in range(30, ALTO - 20, 24):
            esc.circulo(lado, y, 1.6, p["figura"])


def _adorno_expresionismo(esc, rng, p, franja, motivo):
    """Trazos diagonales gruesos y nerviosos, como grabado en madera (xilografía)."""
    y0, y1 = (franja[1] - 10, franja[3] + 10) if franja else (-1, -1)
    for _ in range(rng.randint(9, 14)):
        x, y = rng.uniform(-10, ANCHO), rng.uniform(0.03, 0.97) * ALTO
        if y0 - 40 <= y <= y1:
            continue
        largo, g = rng.uniform(26, 70), rng.uniform(2, 5)
        esc.poligono(_p([(x, y), (x + g, y), (x + largo * 0.55 + g, y + largo), (x + largo * 0.55, y + largo)]), p["figura"])


def _adorno_surrealismo(esc, rng, p, franja, motivo):
    """Nubes de borde duro, planas, y una sombra larga y proyectada por el motivo."""
    y0, y1 = (franja[1] - 14, franja[3] + 14) if franja else (-1, -1)
    for _ in range(rng.randint(2, 4)):
        x, y, r = rng.uniform(30, ANCHO - 30), rng.uniform(0.05, 0.8) * ALTO, rng.uniform(10, 18)
        if y0 - 30 <= y <= y1 + 10:
            continue
        for dx, dr in ((-r * 0.9, 0.8), (0, 1.0), (r * 0.9, 0.75)):
            esc.circulo(round(x + dx, 1), round(y, 1), round(r * dr, 1), p["sol"])
        esc.poligono(_p([(x - r * 1.5, y + 2), (x + r * 1.6, y + 2), (x + r * 1.6, y + r * 0.7), (x - r * 1.5, y + r * 0.7)]), p["sol"])


def _adorno_oriente(esc, rng, p, franja, motivo):
    """Sello rojo (hanko) con marco de nubes en las esquinas y columna vertical de filete."""
    _borde(esc, 9, 1, p["figura"], 0.6)
    esc.poligono([(ANCHO - 36, ALTO - 64), (ANCHO - 16, ALTO - 64), (ANCHO - 16, ALTO - 44), (ANCHO - 36, ALTO - 44)], p["figura"])
    esc.poligono([(ANCHO - 31, ALTO - 59), (ANCHO - 21, ALTO - 59), (ANCHO - 21, ALTO - 54), (ANCHO - 31, ALTO - 54)], p["sol"])
    esc.poligono([(ANCHO - 28, ALTO - 54), (ANCHO - 24, ALTO - 54), (ANCHO - 24, ALTO - 49), (ANCHO - 28, ALTO - 49)], p["sol"])
    for cx, cy in ((22, 24), (ANCHO - 22, 24)):
        for dx, r in ((-8, 5), (0, 7), (8, 5)):
            esc.circulo(cx + dx, cy, r, p["figura"])


ADORNOS_NUEVOS = {
    "renacimiento": _adorno_renacimiento, "ilustracion": _adorno_ilustracion, "expresionismo": _adorno_expresionismo,
    "surrealismo": _adorno_surrealismo, "oriente": _adorno_oriente,
}
