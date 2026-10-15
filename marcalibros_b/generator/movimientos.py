"""Paletas, motivos y adornos de los siete movimientos de la tercera tanda: Realismo,
Naturalismo, Modernismo, Vanguardias, Barroco, Clasicismo y Simbolismo.

Se mantiene aparte de plantillas.py, que lo importa y lo mezcla con lo suyo. Este módulo
solo depende de scene.py, así que no hay importaciones circulares.

Cada motivo recibe (esc, rng, p, altura): la escena, el azar, la paleta y la función
altura(x) del suelo sobre el que se apoya. Sus coordenadas están pensadas para un lienzo
de 200×1400; plantillas.py los escala y los encaja en el ancho.
"""
import math

from scene import ALTO, ANCHO

# --- Paletas -----------------------------------------------------------------------------
# `figura` es el color nominal de las siluetas; plantillas.paleta_legible lo ajusta si no
# llega a 3:1 contra el cielo bajo y la capa lejana.

PALETAS_NUEVAS = {
    # Realismo
    "hollin": {"cielo_alto": "#2B2824", "cielo_bajo": "#6E685E", "sol": "#E8E0D0",
               "lejos": "#4A453E", "cerca": "#1E1B18", "figura": "#0F0D0B"},
    "papel": {"cielo_alto": "#A68A64", "cielo_bajo": "#F1E6CC", "sol": "#FFF8E5",
              "lejos": "#C9B48B", "cerca": "#6E5A3D", "figura": "#2B2114"},
    # Naturalismo
    "barro": {"cielo_alto": "#4A3B2A", "cielo_bajo": "#B58B4E", "sol": "#F0DDB0",
              "lejos": "#8A6A3C", "cerca": "#3A2B1C", "figura": "#1C140B"},
    "sulfuro": {"cielo_alto": "#4B4A1E", "cielo_bajo": "#D6CC5A", "sol": "#FFFBD0",
                "lejos": "#8D8A33", "cerca": "#35340F", "figura": "#1B1A07"},
    "fango": {"cielo_alto": "#2E3A33", "cielo_bajo": "#8C9A78", "sol": "#EEF3DC",
              "lejos": "#657A5C", "cerca": "#1E2A22", "figura": "#0E1611"},
    # Modernismo
    "jade": {"cielo_alto": "#0F4C45", "cielo_bajo": "#B7E3D0", "sol": "#FFF6D8",
             "lejos": "#3C9B86", "cerca": "#123D38", "figura": "#082521"},
    "malva": {"cielo_alto": "#4A2A5A", "cielo_bajo": "#E2B7E8", "sol": "#FFF0FA",
              "lejos": "#8F5DA3", "cerca": "#2B1636", "figura": "#170A1E"},
    "pavo": {"cielo_alto": "#08305A", "cielo_bajo": "#3FBFAE", "sol": "#F7D774",
             "lejos": "#1F7F8C", "cerca": "#0A2438", "figura": "#041320"},
    # Vanguardias
    "primarios": {"cielo_alto": "#1B1B7A", "cielo_bajo": "#F2D21B", "sol": "#FF3B30",
                  "lejos": "#1F5FD1", "cerca": "#101010", "figura": "#000000"},
    "rojonegro": {"cielo_alto": "#0A0A0A", "cielo_bajo": "#D62828", "sol": "#F7F3E8",
                  "lejos": "#6B0F0F", "cerca": "#000000", "figura": "#000000"},
    "dada": {"cielo_alto": "#00A6A6", "cielo_bajo": "#FF4DA6", "sol": "#FFF200",
             "lejos": "#7B22FF", "cerca": "#1A1A1A", "figura": "#000000"},
    # Barroco
    "orooscuro": {"cielo_alto": "#0B0705", "cielo_bajo": "#6B4A12", "sol": "#F2C75C",
                  "lejos": "#3B2A0C", "cerca": "#120C03", "figura": "#050301"},
    "carmesi": {"cielo_alto": "#2A0510", "cielo_bajo": "#9C1C3B", "sol": "#F2D9A0",
                "lejos": "#5E1226", "cerca": "#14040A", "figura": "#080206"},
    "azulreal": {"cielo_alto": "#07123A", "cielo_bajo": "#2E56B8", "sol": "#F4D88A",
                 "lejos": "#1B3786", "cerca": "#060E2A", "figura": "#030718"},
    # Clasicismo
    "marmol": {"cielo_alto": "#7FA6D8", "cielo_bajo": "#F4F6F8", "sol": "#FFF6D0",
               "lejos": "#C8D1DC", "cerca": "#8896A8", "figura": "#2A3340"},
    "terracota": {"cielo_alto": "#7A2F1E", "cielo_bajo": "#F0B88A", "sol": "#FFF1D6",
                  "lejos": "#C4693F", "cerca": "#5A2416", "figura": "#2A100A"},
    "laurel": {"cielo_alto": "#2F5A3A", "cielo_bajo": "#D9E6C3", "sol": "#FFF7DA",
               "lejos": "#7DA06A", "cerca": "#2A4A32", "figura": "#10261A"},
    "egeo": {"cielo_alto": "#0E5FA3", "cielo_bajo": "#CDE8F5", "sol": "#FFF3C4",
             "lejos": "#4FA3C7", "cerca": "#1B5E86", "figura": "#08283E"},
    # Simbolismo
    "mistico": {"cielo_alto": "#1B1040", "cielo_bajo": "#8E6BC4", "sol": "#F2E6FF",
                "lejos": "#4D3A8F", "cerca": "#0F0A2A", "figura": "#07051A"},
    "ambar": {"cielo_alto": "#3A1F0B", "cielo_bajo": "#E0A458", "sol": "#FFF1D0",
              "lejos": "#9A5B26", "cerca": "#1C0F05", "figura": "#0E0702"},
    "opalo": {"cielo_alto": "#5C7C8A", "cielo_bajo": "#E4D7E8", "sol": "#FFFFFF",
              "lejos": "#9DB1BE", "cerca": "#3B4F5A", "figura": "#1B2830"},
}


def _suelo(altura, x):
    return altura(x) + 3


def _x(rng, a=0.3, b=0.7):
    return rng.uniform(a, b) * ANCHO


# --- Realismo ---------------------------------------------------------------------------

def _chimeneas(esc, rng, p, altura):
    """Fábrica: tres chimeneas altas con humo, sobre una nave baja."""
    x0 = _x(rng, 0.3, 0.6)
    y = _suelo(altura, x0)
    esc.poligono([(x0 - 34, y), (x0 - 34, y - 22), (x0 - 22, y - 30), (x0 - 10, y - 22), (x0 + 6, y - 30),
                  (x0 + 22, y - 22), (x0 + 40, y - 30), (x0 + 40, y)], p["figura"])
    for k, h in enumerate((84, 100, 70)):
        x = x0 - 22 + k * 24
        esc.poligono([(x - 4, y - 22), (x - 3, y - h), (x + 3, y - h), (x + 4, y - 22)], p["figura"])
        for dy, r in ((h + 8, 6), (h + 20, 8), (h + 34, 10)):
            esc.circulo(round(x + 4 * (dy - h) / 20, 1), round(y - dy, 1), r, p["sol"])


def _farola(esc, rng, p, altura):
    """Farola de gas con el cristal encendido."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 6, y), (x - 3, y - 10), (x - 1.5, y - 90), (x + 1.5, y - 90), (x + 3, y - 10), (x + 6, y)], p["figura"])
    esc.poligono([(x - 9, y - 90), (x - 7, y - 112), (x, y - 120), (x + 7, y - 112), (x + 9, y - 90)], p["figura"])
    esc.poligono([(x - 6, y - 93), (x - 5, y - 109), (x + 5, y - 109), (x + 6, y - 93)], p["sol"])


def _carruaje(esc, rng, p, altura):
    """Coche de caballos de perfil: caja, capota y dos ruedas."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 34, y - 14), (x - 34, y - 46), (x - 28, y - 56), (x + 14, y - 56), (x + 20, y - 46),
                  (x + 34, y - 46), (x + 34, y - 14)], p["figura"])
    esc.poligono([(x - 26, y - 44), (x - 26, y - 52), (x + 10, y - 52), (x + 10, y - 44)], p["sol"])
    for dx in (-20, 20):
        esc.circulo(x + dx, y - 10, 13, p["figura"])
        esc.circulo(x + dx, y - 10, 5, p["sol"])
    esc.poligono([(x + 34, y - 36), (x + 58, y - 26), (x + 56, y - 22), (x + 34, y - 30)], p["figura"])


def _skyline(esc, rng, p, altura):
    """Línea de edificios de una ciudad, con ventanas encendidas."""
    x0 = rng.uniform(6, 40)
    x = x0
    while x < ANCHO - 12:
        w, h = rng.uniform(16, 30), rng.uniform(46, 120)
        y = _suelo(altura, x + w / 2)
        esc.poligono([(x, y), (x, y - h), (x + w, y - h), (x + w, y)], p["figura"])
        for fila in range(int(h // 22)):
            if rng.random() < 0.6:
                vx = x + rng.uniform(3, w - 8)
                esc.poligono([(vx, y - 10 - fila * 22), (vx, y - 17 - fila * 22), (vx + 4, y - 17 - fila * 22),
                              (vx + 4, y - 10 - fila * 22)], p["sol"])
        x += w + rng.uniform(2, 8)


def _tren(esc, rng, p, altura):
    """Locomotora de vapor de perfil con su penacho de humo."""
    x = _x(rng, 0.35, 0.65)
    y = _suelo(altura, x)
    esc.poligono([(x - 40, y - 12), (x - 40, y - 46), (x - 20, y - 46), (x - 20, y - 36), (x + 34, y - 36),
                  (x + 34, y - 12)], p["figura"])
    esc.poligono([(x + 18, y - 36), (x + 18, y - 56), (x + 26, y - 56), (x + 28, y - 62), (x + 14, y - 62),
                  (x + 16, y - 56)], p["figura"])
    esc.poligono([(x + 34, y - 12), (x + 46, y - 6), (x + 46, y)], p["figura"])
    for dx in (-26, -6, 14, 28):
        esc.circulo(x + dx, y - 8, 7, p["figura"])
    for dx, dy, r in ((20, -76, 7), (28, -90, 10), (40, -108, 13)):
        esc.circulo(round(x + dx, 1), round(y + dy, 1), r, p["sol"])


def _molino(esc, rng, p, altura):
    """Molino de viento: torre troncocónica y cuatro aspas."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 14, y), (x - 8, y - 56), (x + 8, y - 56), (x + 14, y)], p["figura"])
    esc.poligono([(x - 10, y - 56), (x, y - 70), (x + 10, y - 56)], p["figura"])
    cx, cy = x, y - 58
    for k in range(4):
        a = math.radians(45 + 90 * k)
        c, s = math.cos(a), math.sin(a)
        esc.poligono([(round(cx + c * 6 - s * 2, 1), round(cy + s * 6 + c * 2, 1)),
                      (round(cx + c * 52 - s * 2, 1), round(cy + s * 52 + c * 2, 1)),
                      (round(cx + c * 52 + s * 6, 1), round(cy + s * 52 - c * 6, 1)),
                      (round(cx + c * 6 + s * 6, 1), round(cy + s * 6 - c * 6, 1))], p["figura"])


# --- Naturalismo ------------------------------------------------------------------------

def _mina(esc, rng, p, altura):
    """Castillete de mina: torre en A con polea, y la casa de máquinas."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 20, y), (x - 3, y - 80), (x + 3, y - 80), (x + 20, y), (x + 14, y), (x, y - 62), (x - 14, y)], p["figura"])
    esc.poligono([(x - 14, y - 36), (x + 14, y - 36), (x + 14, y - 32), (x - 14, y - 32)], p["figura"])
    esc.circulo(x, y - 86, 11, p["figura"])
    esc.circulo(x, y - 86, 4, p["sol"])
    esc.poligono([(x + 24, y), (x + 24, y - 26), (x + 40, y - 34), (x + 58, y - 26), (x + 58, y)], p["figura"])


def _surcos(esc, rng, p, altura):
    """Surcos de un campo arado que convergen hacia el horizonte (ambiente)."""
    base = rng.uniform(0.88, 0.9) * ALTO
    fin = ALTO - 100  # los estratos de tierra ocupan los últimos 96 px
    for k in range(7):
        x = -30 + k * 40
        esc.poligono([(x, fin), (x + 4, fin), (ANCHO / 2 + (x - ANCHO / 2) * 0.12 + 1, base),
                      (ANCHO / 2 + (x - ANCHO / 2) * 0.12 - 1, base)], p["figura"])


def _trigal(esc, rng, p, altura):
    """Espigas de trigo al pie del paisaje (ambiente). Cada espiga es un solo polígono en
    zigzag, para que la pieza no pese de más."""
    for _ in range(rng.randint(8, 11)):
        x = rng.uniform(8, ANCHO - 8)
        h = rng.uniform(46, 78)
        y = ALTO - 94
        esc.poligono([(x - 1, y), (x - 1, y - h), (x + 1, y - h), (x + 1, y)], p["figura"])
        izq = [(round(x - 5.5, 1), round(y - h + k * 5.5 + 1, 1)) for k in range(5)]
        der = [(round(x + 5.5, 1), round(y - h + k * 5.5 + 1, 1)) for k in range(5)]
        zig = []
        for k in range(5):
            zig += [(izq[k][0], izq[k][1]), (round(x - 1.5, 1), round(izq[k][1] + 2.5, 1))]
        zag = []
        for k in range(5):
            zag += [(der[k][0], der[k][1]), (round(x + 1.5, 1), round(der[k][1] + 2.5, 1))]
        esc.poligono([(x, round(y - h - 6, 1))] + zig + [(round(x, 1), round(y - h + 28, 1))] + zag[::-1], p["sol"])


def _pozo(esc, rng, p, altura):
    """Pozo de piedra con tejadillo y cubo."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 16, y), (x - 16, y - 20), (x + 16, y - 20), (x + 16, y)], p["figura"])
    esc.poligono([(x - 18, y - 56), (x, y - 70), (x + 18, y - 56)], p["figura"])
    for sgn in (-1, 1):
        esc.poligono([(x + sgn * 14 - 1.5, y - 20), (x + sgn * 14 - 1.5, y - 56), (x + sgn * 14 + 1.5, y - 56),
                      (x + sgn * 14 + 1.5, y - 20)], p["figura"])
    esc.poligono([(x - 5, y - 40), (x + 5, y - 40), (x + 4, y - 30), (x - 4, y - 30)], p["sol"])


# --- Modernismo -------------------------------------------------------------------------

def _cisne(esc, rng, p, altura):
    """Cisne sobre el agua: cuello en S, cuerpo y pico."""
    x = _x(rng)
    y = _suelo(altura, x)
    cuello = [(x - 22, y - 16), (x - 24, y - 30), (x - 18, y - 44), (x - 22, y - 58), (x - 14, y - 68), (x - 6, y - 64),
              (x - 12, y - 56), (x - 10, y - 44), (x - 16, y - 30), (x - 12, y - 18)]
    esc.poligono(cuello, p["figura"])
    esc.poligono([(x - 30, y - 12), (x - 20, y - 24), (x + 10, y - 26), (x + 34, y - 36), (x + 28, y - 14), (x + 10, y - 4),
                  (x - 18, y - 4)], p["figura"])
    esc.poligono([(x - 14, y - 68), (x - 3, y - 66), (x - 14, y - 63)], p["sol"])


def _nenufar(esc, rng, p, altura):
    """Hojas de nenúfar y una flor, en el agua del primer término (ambiente)."""
    for _ in range(rng.randint(3, 5)):
        x, y = rng.uniform(26, ANCHO - 26), rng.uniform(0.93, 0.99) * ALTO
        esc.poligono([(round(x + 22 * math.cos(a), 1), round(y + 6 * math.sin(a), 1))
                      for a in [i * math.pi / 8 for i in range(16)]], p["figura"])
    x, y = rng.uniform(40, ANCHO - 40), rng.uniform(0.94, 0.98) * ALTO
    for dx, dy in ((-7, 0), (7, 0), (-3.5, -6), (3.5, -6), (0, -9)):
        esc.circulo(round(x + dx, 1), round(y + dy, 1), 4.6, p["sol"])


def _libelula(esc, rng, p, altura):
    """Libélula en vuelo, con sus cuatro alas."""
    x, y = _x(rng, 0.3, 0.7), rng.uniform(0.1, 0.24) * ALTO
    esc.poligono([(x - 2, y - 14), (x + 2, y - 14), (x + 1.5, y + 28), (x, y + 34), (x - 1.5, y + 28)], p["figura"])
    for sgn in (-1, 1):
        for dy, ln in ((-8, 30), (2, 26)):
            esc.poligono([(x, y + dy), (round(x + sgn * ln, 1), round(y + dy - 6, 1)), (round(x + sgn * ln * 0.9, 1), round(y + dy + 4, 1))], p["sol"])


def _lirio(esc, rng, p, altura):
    """Lirio (iris): tallo, hojas en espada y tres pétalos."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 1.5, y), (x - 1.5, y - 70), (x + 1.5, y - 70), (x + 1.5, y)], p["figura"])
    esc.poligono([(x - 2, y), (x - 20, y - 46), (x - 14, y - 44), (x, y - 8)], p["figura"])
    esc.poligono([(x + 2, y), (x + 20, y - 40), (x + 14, y - 38), (x, y - 8)], p["figura"])
    esc.poligono([(x, y - 70), (x - 14, y - 96), (x - 6, y - 100), (x, y - 84)], p["sol"])
    esc.poligono([(x, y - 70), (x + 14, y - 96), (x + 6, y - 100), (x, y - 84)], p["sol"])
    esc.poligono([(x, y - 70), (x - 6, y - 88), (x, y - 104), (x + 6, y - 88)], p["sol"])


def _pavo_real(esc, rng, p, altura):
    """Pavo real con la cola abierta en abanico y los ocelos."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    for k in range(9):
        a = math.radians(200 + k * 17.5)
        c, s = math.cos(a), math.sin(a)
        esc.poligono([(round(x - s * 3, 1), round(y - 24 + c * 3, 1)), (round(x + c * 70 - s * 5, 1), round(y - 24 + s * 70 + c * 5, 1)),
                      (round(x + c * 70 + s * 5, 1), round(y - 24 + s * 70 - c * 5, 1)), (round(x + s * 3, 1), round(y - 24 - c * 3, 1))], p["figura"])
        esc.circulo(round(x + c * 64, 1), round(y - 24 + s * 64, 1), 3.4, p["sol"])
    esc.poligono([(x - 8, y), (x - 6, y - 26), (x, y - 36), (x + 6, y - 26), (x + 8, y)], p["figura"])
    esc.circulo(x, y - 40, 4.5, p["figura"])


# --- Vanguardias ------------------------------------------------------------------------

def _avion(esc, rng, p, altura):
    """Biplano visto de lado, en el cielo."""
    x, y = _x(rng, 0.3, 0.7), rng.uniform(0.12, 0.24) * ALTO
    esc.poligono([(x - 36, y), (x - 30, y - 6), (x + 30, y - 4), (x + 44, y), (x + 30, y + 4), (x - 30, y + 6)], p["figura"])
    for dy in (-12, 12):
        esc.poligono([(x - 20, y + dy), (x + 20, y + dy), (x + 20, y + dy + 3), (x - 20, y + dy + 3)], p["figura"])
    for dx in (-14, 14):
        esc.poligono([(x + dx - 1, y - 12), (x + dx + 1, y - 12), (x + dx + 1, y + 14), (x + dx - 1, y + 14)], p["figura"])
    esc.poligono([(x - 36, y - 2), (x - 46, y - 14), (x - 40, y - 2)], p["figura"])
    esc.poligono([(x + 44, y - 12), (x + 46, y - 12), (x + 46, y + 12), (x + 44, y + 12)], p["sol"])


def _torre_eiffel(esc, rng, p, altura):
    """Torre Eiffel: perfil de celosía con el arco de la base."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 30, y), (x - 24, y - 14), (x - 16, y - 60), (x - 8, y - 110), (x - 3, y - 150), (x - 1, y - 176),
                  (x + 1, y - 176), (x + 3, y - 150), (x + 8, y - 110), (x + 16, y - 60), (x + 24, y - 14), (x + 30, y),
                  (x + 20, y), (x + 14, y - 22), (x, y - 36), (x - 14, y - 22), (x - 20, y)], p["figura"])
    for dy, w in ((60, 17), (110, 9)):
        esc.poligono([(x - w, y - dy), (x + w, y - dy), (x + w, y - dy - 3), (x - w, y - dy - 3)], p["figura"])


def _guitarra(esc, rng, p, altura):
    """Guitarra cubista: dos lóbulos, boca y mástil quebrado."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.circulo(x, y - 26, 20, p["figura"])
    esc.circulo(x, y - 62, 15, p["figura"])
    esc.poligono([(x - 4, y - 74), (x - 3, y - 128), (x + 5, y - 132), (x + 5, y - 74)], p["figura"])
    esc.poligono([(x - 6, y - 132), (x + 8, y - 134), (x + 8, y - 142), (x - 6, y - 140)], p["figura"])
    esc.circulo(x, y - 40, 7, p["sol"])


def _rascacielos(esc, rng, p, altura):
    """Tres rascacielos escalonados con antena."""
    x0 = _x(rng, 0.25, 0.6)
    y = _suelo(altura, x0)
    for dx, w, h in ((-28, 22, 90), (0, 26, 140), (30, 22, 70)):
        x = x0 + dx
        esc.poligono([(x - w / 2, y), (x - w / 2, y - h), (x - w / 4, y - h), (x - w / 4, y - h - 10), (x + w / 4, y - h - 10),
                      (x + w / 4, y - h), (x + w / 2, y - h), (x + w / 2, y)], p["figura"])
        esc.poligono([(x - 0.8, y - h - 10), (x - 0.8, y - h - 26), (x + 0.8, y - h - 26), (x + 0.8, y - h - 10)], p["figura"])
        for k in range(int(h // 16)):
            esc.poligono([(x - 3, y - 8 - k * 16), (x - 3, y - 13 - k * 16), (x + 3, y - 13 - k * 16), (x + 3, y - 8 - k * 16)], p["sol"])


def _ojo(esc, rng, p, altura):
    """Ojo grande en el cielo: párpados en almendra, iris y pupila."""
    x, y = _x(rng, 0.35, 0.65), rng.uniform(0.12, 0.24) * ALTO
    arriba = [(round(x + u * 36, 1), round(y - 16 * (1 - u * u), 1)) for u in [i / 6 - 1 for i in range(13)]]
    abajo = [(round(x + u * 36, 1), round(y + 14 * (1 - u * u), 1)) for u in [1 - i / 6 for i in range(13)]]
    esc.poligono(arriba + abajo, p["sol"])
    esc.circulo(x, y, 11, p["figura"])
    esc.circulo(x, y, 5, p["sol"])
    esc.circulo(x - 3, y - 3, 2, p["figura"])


def _engranaje(esc, rng, p, altura):
    """Engranaje grande: disco, dientes y buje."""
    x, y = _x(rng, 0.35, 0.65), rng.uniform(0.12, 0.24) * ALTO
    r = 26
    for k in range(10):
        a = 2 * math.pi * k / 10
        c, s = math.cos(a), math.sin(a)
        esc.poligono([(round(x + c * r - s * 5, 1), round(y + s * r + c * 5, 1)), (round(x + c * (r + 11) - s * 4, 1), round(y + s * (r + 11) + c * 4, 1)),
                      (round(x + c * (r + 11) + s * 4, 1), round(y + s * (r + 11) - c * 4, 1)), (round(x + c * r + s * 5, 1), round(y + s * r - c * 5, 1))], p["figura"])
    esc.circulo(x, y, r, p["figura"])
    esc.circulo(x, y, 11, p["sol"])


# --- Barroco ----------------------------------------------------------------------------

def _calavera(esc, rng, p, altura):
    """Calavera de vanitas: cráneo, cuencas, nariz y mandíbula."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.circulo(x, y - 40, 24, p["sol"])
    esc.poligono([(x - 14, y - 26), (x - 14, y - 6), (x + 14, y - 6), (x + 14, y - 26)], p["sol"])
    for dx in (-9, 9):
        esc.circulo(x + dx, y - 40, 6.5, p["figura"])
    esc.poligono([(x, y - 32), (x - 3.5, y - 24), (x + 3.5, y - 24)], p["figura"])
    for k in range(-2, 3):
        esc.poligono([(x + k * 5 - 0.8, y - 16), (x + k * 5 - 0.8, y - 7), (x + k * 5 + 0.8, y - 7), (x + k * 5 + 0.8, y - 16)], p["figura"])


def _reloj_arena(esc, rng, p, altura):
    """Reloj de arena entre dos bases."""
    x = _x(rng)
    y = _suelo(altura, x)
    for yy in (y - 3, y - 76):
        esc.poligono([(x - 18, yy), (x - 18, yy - 5), (x + 18, yy - 5), (x + 18, yy)], p["figura"])
    esc.poligono([(x - 14, y - 8), (x + 14, y - 8), (x + 2, y - 40), (x + 14, y - 71), (x - 14, y - 71), (x - 2, y - 40)], p["sol"])
    esc.poligono([(x - 11, y - 8), (x + 11, y - 8), (x + 5, y - 22), (x - 5, y - 22)], p["figura"])
    for sgn in (-1, 1):
        esc.poligono([(x + sgn * 17 - 1, y - 8), (x + sgn * 17 - 1, y - 71), (x + sgn * 17 + 1, y - 71), (x + sgn * 17 + 1, y - 8)], p["figura"])


def _concha(esc, rng, p, altura):
    """Concha de vieira, símbolo barroco, con sus costillas."""
    x = _x(rng)
    y = _suelo(altura, x)
    pts = [(x, y)]
    for k in range(0, 13):
        a = math.radians(200 + k * 11.67)
        pts.append((round(x + 36 * math.cos(a), 1), round(y - 4 + 36 * math.sin(a) * 1.1, 1)))
    esc.poligono(pts, p["figura"])
    for k in range(1, 12):
        a = math.radians(200 + k * 11.67)
        esc.poligono([(x, y - 2), (round(x + 34 * math.cos(a) - 0.8, 1), round(y - 4 + 34 * math.sin(a) * 1.1, 1)),
                      (round(x + 34 * math.cos(a) + 0.8, 1), round(y - 4 + 34 * math.sin(a) * 1.1, 1))], p["sol"])


def _laud(esc, rng, p, altura):
    """Laúd: caja ovalada, mástil largo, clavijero doblado y roseta."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.circulo(x, y - 24, 22, p["figura"])
    esc.circulo(x, y - 14, 20, p["figura"])
    esc.poligono([(x - 4, y - 40), (x - 3, y - 108), (x + 3, y - 108), (x + 4, y - 40)], p["figura"])
    esc.poligono([(x - 5, y - 108), (x + 5, y - 108), (x + 14, y - 124), (x + 8, y - 128), (x, y - 114)], p["figura"])
    esc.circulo(x, y - 24, 7, p["sol"])


def _cortina(esc, rng, p, altura):
    """Cortinaje de teatro barroco a los lados y festón arriba (ambiente)."""
    for sgn, x0 in ((-1, 0), (1, ANCHO)):
        pts = [(x0, 0)]
        for k in range(0, 13):
            y = k * 20
            pts.append((round(x0 - sgn * (26 - k * 1.6 + 4 * math.sin(k * 1.3)), 1), y))
        pts += [(x0 - sgn * 4, 260), (x0, 260)]
        esc.poligono(pts, p["cerca"])
    feston = [(0, 0), (ANCHO, 0)] + [(round(ANCHO - k * ANCHO / 8, 1), round(18 + 8 * math.sin(k * math.pi), 1)) for k in range(0, 9)]
    esc.poligono(feston, p["cerca"])


def _columna(esc, rng, p, altura):
    """Columna salomónica: fuste helicoidal con capitel y basa."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 14, y), (x - 14, y - 8), (x + 14, y - 8), (x + 14, y)], p["figura"])
    esc.poligono([(x - 9, y - 8), (x - 9, y - 104), (x + 9, y - 104), (x + 9, y - 8)], p["figura"])
    for k in range(9):
        yy = y - 12 - k * 10
        esc.poligono([(x - 9, yy), (x + 9, yy - 7), (x + 9, yy - 10), (x - 9, yy - 3)], p["sol"])
    esc.poligono([(x - 14, y - 104), (x - 16, y - 112), (x + 16, y - 112), (x + 14, y - 104)], p["figura"])


# --- Clasicismo -------------------------------------------------------------------------

def _templo(esc, rng, p, altura):
    """Templo griego: escalinata, seis columnas, arquitrabe y frontón."""
    x = _x(rng, 0.4, 0.6)
    y = _suelo(altura, x)
    esc.poligono([(x - 44, y), (x - 44, y - 6), (x + 44, y - 6), (x + 44, y)], p["figura"])
    esc.poligono([(x - 40, y - 6), (x - 40, y - 12), (x + 40, y - 12), (x + 40, y - 6)], p["figura"])
    for k in range(6):
        cx = x - 33 + k * 13.2
        esc.poligono([(cx - 3, y - 12), (cx - 3, y - 62), (cx + 3, y - 62), (cx + 3, y - 12)], p["figura"])
    esc.poligono([(x - 42, y - 62), (x - 42, y - 70), (x + 42, y - 70), (x + 42, y - 62)], p["figura"])
    esc.poligono([(x - 44, y - 70), (x, y - 96), (x + 44, y - 70)], p["figura"])
    esc.poligono([(x - 20, y - 72), (x, y - 88), (x + 20, y - 72)], p["sol"])


def _laurel(esc, rng, p, altura):
    """Corona de laurel: anillo de hojas, en el cielo."""
    x, y = _x(rng, 0.35, 0.65), rng.uniform(0.12, 0.24) * ALTO
    r = 30
    for k in range(18):
        a = 2 * math.pi * k / 18
        for lado in (-1, 1):
            cx, cy = x + r * math.cos(a), y + r * math.sin(a)
            t = a + math.pi / 2 + lado * 0.5
            esc.poligono([(round(cx, 1), round(cy, 1)), (round(cx + 12 * math.cos(t) - 3 * math.sin(t), 1), round(cy + 12 * math.sin(t) + 3 * math.cos(t), 1)),
                          (round(cx + 16 * math.cos(t), 1), round(cy + 16 * math.sin(t), 1)), (round(cx + 12 * math.cos(t) + 3 * math.sin(t), 1), round(cy + 12 * math.sin(t) - 3 * math.cos(t), 1))], p["figura"] if k % 2 else p["sol"])


def _caballo(esc, rng, p, altura):
    """Caballo de pie, de perfil (el de Troya, en silueta)."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 30, y), (x - 28, y - 30), (x - 34, y - 52), (x - 26, y - 58), (x - 14, y - 52), (x - 4, y - 68), (x + 4, y - 96),
                  (x + 16, y - 100), (x + 22, y - 90), (x + 14, y - 86), (x + 10, y - 66), (x + 24, y - 52), (x + 30, y - 30),
                  (x + 28, y), (x + 20, y), (x + 20, y - 24), (x + 4, y - 30), (x - 8, y - 30), (x - 10, y), (x - 20, y), (x - 22, y - 28)], p["figura"])


def _escudo(esc, rng, p, altura):
    """Escudo redondo (áspide) con embrazadura y ombligo, sobre un soporte."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 2, y), (x - 2, y - 24), (x + 2, y - 24), (x + 2, y)], p["figura"])
    esc.circulo(x, y - 52, 30, p["figura"])
    esc.circulo(x, y - 52, 22, p["sol"])
    esc.circulo(x, y - 52, 14, p["figura"])
    esc.circulo(x, y - 52, 5, p["sol"])


# --- Simbolismo -------------------------------------------------------------------------

def _esfinge(esc, rng, p, altura):
    """Esfinge tendida, de perfil."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 44, y), (x - 44, y - 16), (x - 30, y - 26), (x - 6, y - 28), (x - 4, y - 44), (x - 2, y - 56), (x + 6, y - 64),
                  (x + 16, y - 60), (x + 18, y - 48), (x + 14, y - 38), (x + 18, y - 28), (x + 34, y - 18), (x + 40, y - 6), (x + 40, y)], p["figura"])


def _espejo(esc, rng, p, altura):
    """Espejo ovalado sobre pie: el marco oscuro y el reflejo claro."""
    x = _x(rng)
    y = _suelo(altura, x)
    contorno = lambda rx, ry, cy: [(round(x + rx * math.cos(a), 1), round(cy + ry * math.sin(a), 1)) for a in [i * math.pi / 10 for i in range(20)]]
    esc.poligono([(x - 2, y), (x - 2, y - 30), (x + 2, y - 30), (x + 2, y)], p["figura"])
    esc.poligono(contorno(24, 40, y - 68), p["figura"])
    esc.poligono(contorno(19, 35, y - 68), p["sol"])
    esc.poligono([(x - 8, y - 84), (x - 3, y - 90), (x + 6, y - 66), (x + 1, y - 60)], p["figura"])


def _mano(esc, rng, p, altura):
    """Mano abierta que emerge del suelo."""
    x = _x(rng)
    y = _suelo(altura, x)
    esc.poligono([(x - 10, y), (x - 8, y - 30), (x - 14, y - 36), (x - 13, y - 60), (x - 9, y - 60), (x - 8, y - 46), (x - 7, y - 78),
                  (x - 3, y - 78), (x - 2, y - 50), (x, y - 84), (x + 4, y - 84), (x + 4, y - 52), (x + 8, y - 76), (x + 12, y - 74),
                  (x + 10, y - 46), (x + 18, y - 56), (x + 20, y - 52), (x + 12, y - 34), (x + 10, y)], p["figura"])


def _creciente(esc, rng, p, altura):
    """Luna creciente grande, como una hoz en el cielo."""
    x, y = _x(rng, 0.4, 0.6), rng.uniform(0.1, 0.22) * ALTO
    r = 40
    ext = [(round(x - r * math.cos(a), 1), round(y + r * math.sin(a), 1)) for a in [math.radians(-70 + k * 140 / 12) for k in range(13)]]
    inte = [(round(x - 14 - (r - 4) * math.cos(a), 1), round(y + (r - 4) * math.sin(a), 1)) for a in [math.radians(70 - k * 140 / 12) for k in range(13)]]
    esc.poligono(ext + inte, p["sol"])


def _escalera(esc, rng, p, altura):
    """Escalera que sube hacia ninguna parte."""
    x = _x(rng, 0.25, 0.55)
    y = _suelo(altura, x)
    pts = [(x, y)]
    for k in range(8):
        pts += [(x + k * 10, y - k * 11 - 11), (x + k * 10 + 10, y - k * 11 - 11)]
    pts += [(x + 80, y)]
    esc.poligono(pts, p["figura"])


MOTIVOS_NUEVOS = {
    "chimeneas": _chimeneas, "farola": _farola, "carruaje": _carruaje, "skyline": _skyline, "tren": _tren, "molino": _molino,
    "mina": _mina, "surcos": _surcos, "trigal": _trigal, "pozo": _pozo,
    "cisne": _cisne, "nenufar": _nenufar, "libelula": _libelula, "lirio": _lirio, "pavo_real": _pavo_real,
    "avion": _avion, "torre_eiffel": _torre_eiffel, "guitarra": _guitarra, "rascacielos": _rascacielos, "ojo": _ojo,
    "engranaje": _engranaje,
    "calavera": _calavera, "reloj_arena": _reloj_arena, "concha": _concha, "laud": _laud, "cortina": _cortina,
    "columna": _columna,
    "templo": _templo, "laurel": _laurel, "caballo": _caballo, "escudo": _escudo,
    "esfinge": _esfinge, "espejo": _espejo, "mano": _mano, "creciente": _creciente, "escalera": _escalera,
}

ESCALAS_NUEVAS = {
    "chimeneas": (1.3, "suelo"), "farola": (1.3, "suelo"), "carruaje": (1.5, "suelo"), "skyline": (1.0, "suelo"),
    "tren": (1.5, "suelo"), "molino": (1.4, "suelo"), "mina": (1.5, "suelo"), "pozo": (1.7, "suelo"),
    "cisne": (1.7, "suelo"), "libelula": (1.6, "centro"), "lirio": (1.6, "suelo"), "pavo_real": (1.1, "suelo"),
    "avion": (1.7, "centro"), "torre_eiffel": (1.1, "suelo"), "guitarra": (1.3, "suelo"), "rascacielos": (1.1, "suelo"),
    "ojo": (1.7, "centro"), "engranaje": (1.5, "centro"),
    "calavera": (1.8, "suelo"), "reloj_arena": (1.8, "suelo"), "concha": (2.0, "suelo"), "laud": (1.4, "suelo"),
    "columna": (1.6, "suelo"),
    "templo": (1.6, "suelo"), "laurel": (1.5, "centro"), "caballo": (1.3, "suelo"), "escudo": (1.6, "suelo"),
    "esfinge": (1.6, "suelo"), "espejo": (1.3, "suelo"), "mano": (1.7, "suelo"), "creciente": (1.5, "centro"),
    "escalera": (1.5, "suelo"),
}

AMBIENTALES_NUEVOS = {"surcos", "trigal", "nenufar", "cortina"}
FRENTE_NUEVOS = {"surcos", "trigal", "nenufar"}


# --- Adornos propios de cada movimiento ---------------------------------------------------
# Se dibujan al final, ya elegida la franja de texto (esc.franja), y la respetan.

def _borde(esc, inset, grosor, color, opacidad):
    for (x0, y0, x1, y1) in ((inset, inset, ANCHO - inset, inset + grosor),
                             (inset, ALTO - inset - grosor, ANCHO - inset, ALTO - inset),
                             (inset, inset, inset + grosor, ALTO - inset),
                             (ANCHO - inset - grosor, inset, ANCHO - inset, ALTO - inset)):
        esc.velo(x0, y0, x1, y1, color, opacidad, 0)


def _adorno_realismo(esc, rng, p, franja, motivo):
    """Doble filete fino, como el marco de un grabado de libro."""
    _borde(esc, 6, 2, p["sol"], 0.6)
    _borde(esc, 11, 1, p["sol"], 0.45)


def _adorno_naturalismo(esc, rng, p, franja, motivo):
    """Estratos de tierra al pie de la pieza: el medio que determina a los personajes."""
    from_y = ALTO - 96
    for k in range(4):
        y0 = from_y + k * 24
        ondas = [(x, round(y0 + 5 * math.sin(x / 26 + k * 1.7 + rng.uniform(0, 1)), 1)) for x in range(0, ANCHO + 1, 20)]
        esc.poligono(ondas + [(ANCHO, ALTO), (0, ALTO)], p["cerca"] if k % 2 == 0 else p["lejos"])
    for _ in range(14):
        esc.circulo(round(rng.uniform(6, ANCHO - 6), 1), round(rng.uniform(from_y + 20, ALTO - 6), 1), round(rng.uniform(1.4, 3.2), 1), p["figura"])


def _adorno_modernismo(esc, rng, p, franja, motivo):
    """Sarmientos de línea de látigo (art nouveau) por los bordes, con hojas."""
    for lado, x0 in ((-1, 8), (1, ANCHO - 8)):
        fase = rng.uniform(0, math.pi)
        y, izq, der = 40.0, [], []
        while y < 0.62 * ALTO:
            x = x0 + lado * 0 + 5 * math.sin(y / 38 + fase)
            g = 1.6 + 1.2 * math.sin(y / 70 + 1)
            izq.append((round(x - g, 1), round(y, 1)))
            der.append((round(x + g, 1), round(y, 1)))
            y += 12
        esc.poligono(izq + der[::-1], p["sol"])
        for k in range(1, len(izq), 3):
            xh, yh = izq[k]
            esc.circulo(round(xh - lado * 6, 1), yh, 3.4, p["sol"])


def _adorno_vanguardias(esc, rng, p, franja, motivo):
    """Formas geométricas planas superpuestas, como un collage, fuera de la franja de texto."""
    y0, y1 = (franja[1] - 14, franja[3] + 14) if franja else (-1, -1)
    colores = [p["sol"], p["cerca"], p["lejos"], p["figura"]]
    for _ in range(rng.randint(5, 8)):
        cx, cy = rng.uniform(10, ANCHO - 10), rng.uniform(40, ALTO - 60)
        s = rng.uniform(10, 28)
        if y0 - s <= cy <= y1 + s:
            continue
        c = rng.choice(colores)
        forma = rng.choice(("circulo", "triangulo", "barra", "diagonal"))
        if forma == "circulo":
            esc.circulo(round(cx, 1), round(cy, 1), round(s, 1), c)
        elif forma == "triangulo":
            esc.poligono([(round(cx - s, 1), round(cy + s, 1)), (round(cx + s, 1), round(cy + s, 1)), (round(cx, 1), round(cy - s, 1))], c)
        elif forma == "barra":
            esc.poligono([(0, round(cy, 1)), (ANCHO, round(cy, 1)), (ANCHO, round(cy + s / 3, 1)), (0, round(cy + s / 3, 1))], c)
        else:
            esc.poligono([(round(cx - s * 2, 1), round(cy + s, 1)), (round(cx - s * 2 + 6, 1), round(cy + s, 1)),
                          (round(cx + s * 2, 1), round(cy - s, 1)), (round(cx + s * 2 - 6, 1), round(cy - s, 1))], c)


def _arco_grueso(esc, cx, cy, r, w, a0, a1, color, n=14):
    ext = [(round(cx + r * math.cos(math.radians(a0 + (a1 - a0) * k / n)), 1), round(cy + r * math.sin(math.radians(a0 + (a1 - a0) * k / n)), 1)) for k in range(n + 1)]
    inte = [(round(cx + (r - w) * math.cos(math.radians(a0 + (a1 - a0) * k / n)), 1), round(cy + (r - w) * math.sin(math.radians(a0 + (a1 - a0) * k / n)), 1)) for k in range(n, -1, -1)]
    esc.poligono(ext + inte, color)


def _adorno_barroco(esc, rng, p, franja, motivo):
    """Marco dorado con volutas en las esquinas, como una cartela."""
    _borde(esc, 7, 3, p["sol"], 0.85)
    _borde(esc, 14, 1, p["sol"], 0.6)
    for (cx, cy, a0, a1) in ((26, 26, 180, 450), (ANCHO - 26, 26, 270, 540), (26, ALTO - 26, 90, 360), (ANCHO - 26, ALTO - 26, 0, 270)):
        _arco_grueso(esc, cx, cy, 14, 3, a0, a1, p["sol"])
        esc.circulo(cx, cy, 3, p["sol"])


def _adorno_clasicismo(esc, rng, p, franja, motivo):
    """Grecas (meandro simplificado) en dos bandas, arriba y abajo, y filetes."""
    for y in (24, ALTO - 48):
        esc.velo(10, y, ANCHO - 10, y + 3, p["figura"], 0.7, 0)
        esc.velo(10, y + 21, ANCHO - 10, y + 24, p["figura"], 0.7, 0)
        x = 14
        while x < ANCHO - 24:
            esc.poligono([(x, y + 19), (x, y + 5), (x + 14, y + 5), (x + 14, y + 14), (x + 7, y + 14), (x + 7, y + 10)], p["figura"])
            x += 22


def _adorno_simbolismo(esc, rng, p, franja, motivo):
    """Aureola de círculos translúcidos alrededor del sol y filamentos de bruma verticales."""
    if esc.sol:
        cx, cy, r = esc.sol
        for k, op in ((6.0, 0.07), (4.4, 0.09), (3.0, 0.12), (1.9, 0.16)):
            esc.velo(round(cx - r * k, 1), round(cy - r * k, 1), round(cx + r * k, 1), round(cy + r * k, 1), p["sol"], op, round(r * k, 1))
    y0, y1 = (franja[1] - 10, franja[3] + 10) if franja else (-1, -1)
    for _ in range(rng.randint(5, 8)):
        x, y, largo = rng.uniform(14, ANCHO - 14), rng.uniform(0.05, 0.85) * ALTO, rng.uniform(60, 160)
        if y0 - largo <= y <= y1:
            continue
        esc.velo(round(x, 1), round(y, 1), round(x + 2, 1), round(y + largo, 1), p["sol"], round(rng.uniform(0.12, 0.22), 2), 1)


ADORNOS_NUEVOS = {
    "realismo": _adorno_realismo, "naturalismo": _adorno_naturalismo, "modernismo": _adorno_modernismo,
    "vanguardias": _adorno_vanguardias, "barroco": _adorno_barroco, "clasicismo": _adorno_clasicismo,
    "simbolismo": _adorno_simbolismo,
}
