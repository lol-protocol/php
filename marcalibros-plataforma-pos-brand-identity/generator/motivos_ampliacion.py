"""Motivos de la ampliación del catálogo (lienzo de 1400 x 200).

Cubren el Modernismo anglosajón (que se separó del hispanoamericano) y los seis
tipos de texto agregados: cuentos y leyendas, poesía, historia, periodismo,
diccionarios y enciclopedias, y discursos.

Siguen las reglas de `motivos.py`: reciben `r`, `c1`, `c2` y `fondo`, devuelven
figuras y no dibujan texto legible.
"""
import math

from motivos import H, TAU, W, _colocar, colinas, elipse, estrella, flor, hoja, rect_girado
from motivos_texto import _arco, _cerrada, _puntos_repartidos, _rect_pts


# ------------------------------------------------------ Modernismo anglosajón

def faro(r, c1, c2, fondo):
    """Un faro sobre las rocas, con haces de luz que barren un mar de líneas."""
    figs = []
    x = r.uniform(180, W - 180)
    base, alto = 168, r.uniform(110, 130)
    for k in range(5):
        y0 = 150 + k * 10
        lam, fase = r.uniform(120, 200), r.uniform(0, TAU)
        figs.append(("line", [(t, y0 + 3 * math.sin(TAU * t / lam + fase)) for t in range(-10, W + 11, 24)], c2, 2))
    rocas = [(x - 90, H), (x - 70, base - 6), (x - 30, base - 14), (x + 30, base - 12), (x + 80, base - 2), (x + 100, H)]
    figs.append(("poly", rocas, c1))
    top = base - alto
    figs.append(("poly", [(x - 22, base - 10), (x + 22, base - 10), (x + 13, top + 18), (x - 13, top + 18)], c1))
    for k in range(1, 4):
        y = base - 10 - k * (alto - 28) / 4
        figs.append(("rect", x - 22 + k * 2.4, y - 5, 44 - k * 4.8, 7, fondo))
    figs.append(("rect", x - 15, top + 2, 30, 16, c2))
    figs.append(("poly", [(x - 18, top + 2), (x + 18, top + 2), (x, top - 14)], c1))
    lado = r.choice([-1, 1])
    for ang in (-0.18, 0.0, 0.16):
        largo = r.uniform(380, 620)
        a = ang if lado > 0 else math.pi - ang
        p1 = (x + 15 * lado, top + 6)
        figs.append(("poly", [p1, (x + 15 * lado + largo * math.cos(a), top + 6 + largo * math.sin(a) - 22),
                              (x + 15 * lado + largo * math.cos(a), top + 6 + largo * math.sin(a) + 22)], c2))
    for _ in range(r.randint(3, 6)):
        px, py = r.uniform(30, W - 30), r.uniform(18, 60)
        figs.append(("line", [(px - 8, py - 3), (px, py), (px + 8, py - 3)], c1, 2))
    return figs


def _reloj(cx, cy, rad, horas, minutos, c1, c2):
    figs = [("ring", cx, cy, rad, c1, 3)]
    for k in range(12):
        a = TAU * k / 12
        figs.append(("line", [(cx + 0.78 * rad * math.cos(a), cy + 0.78 * rad * math.sin(a)),
                              (cx + 0.9 * rad * math.cos(a), cy + 0.9 * rad * math.sin(a))], c2, 2))
    ah = TAU * (horas / 12) - math.pi / 2
    am = TAU * (minutos / 60) - math.pi / 2
    figs.append(("line", [(cx, cy), (cx + 0.5 * rad * math.cos(ah), cy + 0.5 * rad * math.sin(ah))], c1, 3.5))
    figs.append(("line", [(cx, cy), (cx + 0.78 * rad * math.cos(am), cy + 0.78 * rad * math.sin(am))], c1, 2.5))
    figs.append(("circle", cx, cy, 4, c1))
    return figs


def relojes(r, c1, c2, fondo):
    """Esferas de reloj que marcan horas distintas: el tiempo de un solo día, contado por varias voces."""
    figs = []
    n = r.randint(4, 6)
    xs, paso = _puntos_repartidos(r, n, 80)
    for cx in xs:
        rad = r.uniform(34, min(62, paso * 0.42))
        cy = r.uniform(70, 130)
        figs += _reloj(cx, cy, rad, r.uniform(0, 12), r.uniform(0, 60), c1, c2)
    for k in range(len(xs) - 1):
        figs.append(("line", [(xs[k] + 50, 100 + r.uniform(-30, 30)), (xs[k + 1] - 50, 100 + r.uniform(-30, 30))], c2, 1.5))
    return figs


def tierra_baldia(r, c1, c2, fondo):
    """Llanura seca con árboles sin hojas, piedras y una grieta que cruza el suelo."""
    figs = [colinas(r, 162, 6, c2, paso=40)]
    for _ in range(r.randint(3, 5)):
        x, base = r.uniform(60, W - 60), r.uniform(150, 160)
        alto = r.uniform(70, 120)
        figs.append(("line", [(x, base), (x + r.uniform(-6, 6), base - alto)], c1, 4))
        for _ in range(r.randint(3, 5)):
            y = base - alto * r.uniform(0.35, 0.9)
            lado = r.choice([-1, 1])
            largo = r.uniform(18, 40)
            figs.append(("line", [(x, y), (x + lado * largo, y - largo * r.uniform(0.4, 0.9))], c1, 2.2))
    grieta = [(r.uniform(0, 200), 182)]
    while grieta[-1][0] < W:
        grieta.append((grieta[-1][0] + r.uniform(40, 90), 176 + r.uniform(-6, 12)))
    figs.append(("line", grieta, c1, 2))
    for _ in range(r.randint(6, 10)):
        figs.append(elipse(r.uniform(20, W - 20), r.uniform(164, 190), r.uniform(6, 14), r.uniform(4, 7), r.uniform(-0.3, 0.3), c1, n=8))
    return figs


def fragmentos(r, c1, c2, fondo):
    """Esquirlas de vidrio que se separan: una imagen rota en piezas, como un poema hecho de citas."""
    figs = []
    x = 0.0
    while x < W:
        ancho = r.uniform(50, 120)
        pts = [(x + r.uniform(-8, 8), r.uniform(10, 40)), (x + ancho + r.uniform(-8, 8), r.uniform(10, 40)),
               (x + ancho + r.uniform(-14, 14), r.uniform(150, 190)), (x + r.uniform(-14, 14), r.uniform(150, 190))]
        cx = sum(p[0] for p in pts) / 4
        cy = sum(p[1] for p in pts) / 4
        esc = r.uniform(0.55, 0.85)
        dy = r.uniform(-12, 12)
        pts = [(cx + (px - cx) * esc, cy + (py - cy) * esc + dy) for px, py in pts]
        if r.random() < 0.5:
            figs.append(("poly", pts, r.choice([c1, c2])))
        else:
            figs.append(("line", _cerrada(pts), c1, 2.4))
        x += ancho + r.uniform(4, 16)
    return figs


# --------------------------------------------------------- Cuentos y leyendas

def _torre(x, base, ancho, alto, c, fondo):
    figs = [("rect", x - ancho / 2, base - alto, ancho, alto, c),
            ("poly", [(x - ancho / 2 - 6, base - alto), (x + ancho / 2 + 6, base - alto), (x, base - alto - ancho * 1.1)], c),
            ("rect", x - 4, base - alto * 0.7, 8, 12, fondo)]
    return figs


def castillo(r, c1, c2, fondo):
    """Castillos de cuento en lo alto de una colina, con torres puntiagudas y estandartes."""
    figs = [colinas(r, 170, 10, c2)]
    for _ in range(r.randint(1, 2)):
        x = r.uniform(200, W - 200)
        base = 166
        muro = r.uniform(120, 200)
        figs.append(("rect", x - muro / 2, base - 50, muro, 50, c1))
        for k in range(int(muro // 16)):
            figs.append(("rect", x - muro / 2 + k * 16, base - 58, 9, 8, c1))
        figs.append(("poly", [(x - 14, base), (x + 14, base), (x + 14, base - 22), (x, base - 32), (x - 14, base - 22)], fondo))
        for dx in (-muro / 2, muro / 2):
            figs += _torre(x + dx, base, 30, r.uniform(80, 100), c1, fondo)
        alto = r.uniform(105, 120)
        figs += _torre(x, base - 50, 34, alto - 50, c1, fondo)
        tope = base - alto - 34 * 1.1
        figs.append(("line", [(x, tope), (x, tope - 18)], c1, 2))
        figs.append(("poly", [(x, tope - 18), (x + 18, tope - 13), (x, tope - 8)], c2))
    for _ in range(r.randint(3, 6)):
        figs.append(estrella(r.uniform(20, W - 20), r.uniform(14, 50), r.uniform(5, 9), c2))
    return figs


def hongos(r, c1, c2, fondo):
    """Hongos de sombrero redondo y con lunares, entre la hierba de un bosque encantado."""
    figs = []
    n = r.randint(6, 9)
    xs, paso = _puntos_repartidos(r, n, 50)
    for x in xs:
        base = r.uniform(178, 190)
        alto = r.uniform(40, 110)
        rad = r.uniform(26, min(58, paso * 0.48))
        figs.append(("rect", x - rad * 0.22, base - alto, rad * 0.44, alto, c2))
        figs.append(("poly", _arco(x, base - alto + 4, rad, math.pi, TAU, n=10), c1))
        for _ in range(r.randint(2, 4)):
            a = r.uniform(math.pi * 1.15, math.pi * 1.85)
            d = rad * r.uniform(0.35, 0.7)
            figs.append(("circle", x + d * math.cos(a), base - alto + 4 + d * math.sin(a), rad * 0.12, fondo))
    for _ in range(r.randint(10, 16)):
        x = r.uniform(10, W - 10)
        figs.append(("line", [(x, 194), (x + r.uniform(-6, 6), 194 - r.uniform(10, 22))], c2, 2))
    return figs


def _llave(x, y, largo, ang, c, fondo):
    cuerpo = [("ring", x, y, largo * 0.16, c, 4)]
    cuerpo.append(("line", _colocar([(largo * 0.16, 0), (largo, 0)], x, y, ang), c, 4))
    for d in (0.78, 0.92):
        cuerpo.append(("line", _colocar([(largo * d, 0), (largo * d, largo * 0.14)], x, y, ang), c, 4))
    return cuerpo


def llaves(r, c1, c2, fondo):
    """Llaves antiguas de distintos tamaños colgadas de un hilo, como las de una puerta que esconde un secreto."""
    figs = [("line", [(0, 22), (W, 22)], c2, 2)]
    n = r.randint(6, 9)
    xs, paso = _puntos_repartidos(r, n, 60)
    for x in xs:
        largo = r.uniform(80, 140)
        ang = math.pi / 2 + r.uniform(-0.25, 0.25)
        figs.append(("line", [(x, 22), (x, 40)], c2, 1.5))
        figs += _llave(x, 40 + largo * 0.16, largo, ang, r.choice([c1, c1, c2]), fondo)
    return figs


def coronas(r, c1, c2, fondo):
    """Coronas de reyes de cuento, con picos, gemas redondas y una banda decorada."""
    figs = []
    n = r.randint(4, 6)
    xs, paso = _puntos_repartidos(r, n, 80)
    for x in xs:
        ancho = r.uniform(70, min(130, paso * 0.8))
        base = r.uniform(140, 165)
        alto = ancho * r.uniform(0.55, 0.75)
        picos = r.choice([3, 5])
        pts = [(x - ancho / 2, base)]
        for k in range(picos * 2 + 1):
            px = x - ancho / 2 + ancho * k / (picos * 2)
            py = base - alto if k % 2 == 0 else base - alto * 0.55
            pts.append((px, py))
        pts.append((x + ancho / 2, base))
        figs.append(("poly", pts, c1))
        figs.append(("rect", x - ancho / 2, base - alto * 0.3, ancho, alto * 0.14, c2))
        for k in range(0, picos * 2 + 1, 2):
            figs.append(("circle", x - ancho / 2 + ancho * k / (picos * 2), base - alto - 5, 5, c2))
    for _ in range(r.randint(4, 8)):
        figs.append(estrella(r.uniform(20, W - 20), r.uniform(14, 60), r.uniform(4, 8), c2))
    return figs


# ------------------------------------------------------------------- Poesía

def versos(r, c1, c2, fondo):
    """Estrofas como renglones de distinto largo, alineadas al centro, con una pluma que las escribe."""
    figs = []
    n = r.randint(3, 5)
    xs, paso = _puntos_repartidos(r, n, 70)
    for x in xs:
        renglones = r.choice([4, 5, 6])
        alto = 150 / (renglones + 1)
        for k in range(renglones):
            largo = paso * r.uniform(0.35, 0.8)
            y = 30 + k * alto
            figs.append(("line", [(x - largo / 2, y), (x + largo / 2, y)], c1 if k % 2 == 0 else c2, 4))
    xp = r.choice(xs) + paso * 0.35
    figs.append(hoja(xp, 180, 110, 26, -math.pi / 2 + 0.5, c2))
    figs.append(("line", [(xp, 180), (xp + 60, 82)], c1, 2))
    return figs


def pajaros_en_cable(r, c1, c2, fondo):
    """Pájaros posados en cables de luz, repartidos como notas en un pentagrama."""
    figs = []
    ys = [r.uniform(60, 80), r.uniform(110, 130)]
    for y in ys:
        figs.append(("line", [(t, y + 8 * math.sin(math.pi * t / W)) for t in range(0, W + 1, 70)], c2, 2))
    for y in ys:
        for _ in range(r.randint(5, 9)):
            x = r.uniform(30, W - 30)
            yy = y + 8 * math.sin(math.pi * x / W)
            s = r.uniform(9, 14)
            figs.append(elipse(x, yy - s * 0.9, s, s * 0.75, 0, c1, n=10))
            figs.append(("circle", x + s * 0.8 * r.choice([-1, 1]), yy - s * 1.6, s * 0.5, c1))
            figs.append(("line", [(x, yy - s * 0.2), (x, yy)], c1, 1.5))
    for _ in range(r.randint(2, 4)):
        x, y = r.uniform(30, W - 30), r.uniform(150, 185)
        figs.append(("line", [(x - 9, y - 4), (x, y), (x + 9, y - 4)], c1, 2))
    return figs


def petalos(r, c1, c2, fondo):
    """Pétalos sueltos que el viento lleva en diagonal, y una flor abierta de donde salen."""
    x0 = r.uniform(80, 240)
    pet, centro = flor(x0, r.uniform(80, 120), r.uniform(40, 56), r.choice([5, 6]), c1, c2, r.uniform(0, 1))
    figs = pet + [centro]
    for _ in range(r.randint(16, 26)):
        x = r.uniform(x0 + 60, W - 20)
        y = r.uniform(20, 180)
        figs.append(hoja(x, y, r.uniform(16, 28), r.uniform(8, 13), r.uniform(-0.6, 0.6), r.choice([c1, c2])))
    return figs


def lira(r, c1, c2, fondo):
    """Liras de brazos curvos y cuerdas tensas, el instrumento de la poesía antigua."""
    figs = []
    n = r.randint(3, 5)
    xs, paso = _puntos_repartidos(r, n, 90)
    for x in xs:
        s = r.uniform(60, 82)
        base = r.uniform(165, 180)
        figs.append(("line", [(x - s * 0.45, base - s * 1.7), (x - s * 0.6, base - s * 1.2), (x - s * 0.5, base - s * 0.55),
                              (x - s * 0.25, base)], c1, 5))
        figs.append(("line", [(x + s * 0.45, base - s * 1.7), (x + s * 0.6, base - s * 1.2), (x + s * 0.5, base - s * 0.55),
                              (x + s * 0.25, base)], c1, 5))
        figs.append(("line", [(x - s * 0.55, base - s * 1.5), (x + s * 0.55, base - s * 1.5)], c1, 4))
        figs.append(("line", [(x - s * 0.3, base), (x + s * 0.3, base)], c1, 5))
        for k in range(5):
            dx = -s * 0.24 + k * s * 0.12
            figs.append(("line", [(dx + x, base - s * 1.5), (dx + x, base)], c2, 1.5))
    for _ in range(r.randint(3, 6)):
        figs.append(estrella(r.uniform(20, W - 20), r.uniform(14, 40), r.uniform(4, 7), c2))
    return figs


# ------------------------------------------------------------------ Historia

def linea_de_tiempo(r, c1, c2, fondo):
    """Una línea de tiempo con hitos: círculos, banderas y tramos que marcan épocas."""
    y = r.uniform(95, 110)
    figs = [("line", [(20, y), (W - 20, y)], c1, 4)]
    x = 50.0
    arriba = r.random() < 0.5
    while x < W - 60:
        tramo = r.uniform(70, 160)
        if r.random() < 0.35:
            figs.append(("rect", x, y - 8 if arriba else y + 2, tramo, 6, c2))
        figs.append(("circle", x, y, r.uniform(7, 11), c1))
        largo = r.uniform(30, 60)
        y2 = y - largo if arriba else y + largo
        figs.append(("line", [(x, y), (x, y2)], c1, 2))
        if r.random() < 0.5:
            figs.append(("poly", [(x, y2), (x + 24, y2 + (6 if arriba else -6)), (x, y2 + (12 if arriba else -12))], c2))
        else:
            figs.append(("rect", x - 16, y2 - (12 if arriba else -2), 32, 10, c2))
        arriba = not arriba
        x += tramo
    figs.append(("poly", [(W - 20, y - 10), (W, y), (W - 20, y + 10)], c1))
    return figs


def piramides(r, c1, c2, fondo):
    """Pirámides de escalones y de caras lisas en el desierto, con el sol bajo."""
    figs = [("circle", r.uniform(200, W - 200), r.uniform(50, 80), r.uniform(20, 32), c2)]
    figs.append(colinas(r, 172, 4, c2, paso=60))
    n = r.randint(2, 4)
    xs, paso = _puntos_repartidos(r, n, 160)
    for x in xs:
        base = 176
        ancho = r.uniform(140, min(260, paso * 0.9))
        alto = ancho * r.uniform(0.45, 0.6)
        if r.random() < 0.5:
            figs.append(("poly", [(x - ancho / 2, base), (x + ancho / 2, base), (x, base - alto)], c1))
            figs.append(("poly", [(x, base - alto), (x + ancho / 2, base), (x + ancho * 0.12, base)], c2))
        else:
            escalones = r.randint(4, 6)
            for k in range(escalones):
                w = ancho * (1 - k / escalones)
                h = alto / escalones
                figs.append(("rect", x - w / 2, base - (k + 1) * h, w, h - 2, c1))
    return figs


def monedas(r, c1, c2, fondo):
    """Monedas antiguas apiladas y sueltas, con el canto estriado y un perfil geométrico."""
    figs = []
    n = r.randint(5, 8)
    xs, paso = _puntos_repartidos(r, n, 60)
    for x in xs:
        rad = r.uniform(24, min(42, paso * 0.4))
        if r.random() < 0.45:
            base = 180
            for k in range(r.randint(3, 7)):
                figs.append(elipse(x, base - k * 9, rad, rad * 0.32, 0, c1 if k % 2 == 0 else c2, n=14))
        else:
            cy = r.uniform(70, 140)
            figs.append(("circle", x, cy, rad, c1))
            figs.append(("ring", x, cy, rad * 0.8, fondo, 2))
            figs.append(estrella(x, cy, rad * 0.45, c2, puntas=r.choice([5, 6, 8]), k=0.45))
    return figs


def reloj_de_arena(r, c1, c2, fondo):
    """Relojes de arena con marco de madera: la arena que cae arriba y se amontona abajo."""
    figs = []
    n = r.randint(4, 6)
    xs, paso = _puntos_repartidos(r, n, 80)
    for x in xs:
        s = r.uniform(34, min(52, paso * 0.4))
        top, bot = r.uniform(20, 34), r.uniform(166, 182)
        mid = (top + bot) / 2
        figs.append(("rect", x - s - 6, top - 8, 2 * s + 12, 8, c1))
        figs.append(("rect", x - s - 6, bot, 2 * s + 12, 8, c1))
        figs.append(("line", [(x - s - 2, top), (x - s - 2, bot)], c1, 3))
        figs.append(("line", [(x + s + 2, top), (x + s + 2, bot)], c1, 3))
        figs.append(("line", [(x - s * 0.85, top), (x - 4, mid), (x - s * 0.85, bot)], c1, 2))
        figs.append(("line", [(x + s * 0.85, top), (x + 4, mid), (x + s * 0.85, bot)], c1, 2))
        lleno = r.uniform(0.2, 0.8)
        h = (mid - top) * (1 - lleno)          # alto de la arena que queda arriba
        w = s * 0.8 * h / (mid - top)           # el vidrio se angosta en línea recta hacia el centro
        figs.append(("poly", [(x - w, mid - h), (x + w, mid - h), (x, mid)], c2))
        figs.append(("poly", [(x - s * 0.8, bot - 1), (x + s * 0.8, bot - 1), (x, bot - (bot - mid) * lleno)], c2))
        figs.append(("line", [(x, mid), (x, bot - (bot - mid) * lleno)], c2, 1.5))
    return figs


# ---------------------------------------------------------------- Periodismo

def columnas_de_periodico(r, c1, c2, fondo):
    """Página de periódico: columnas de renglones, una foto, filetes y un titular en bloque."""
    figs = []
    x = 20.0
    while x < W - 40:
        ancho = r.uniform(90, 150)
        y = 20.0
        if r.random() < 0.3:
            alto = r.uniform(50, 90)
            figs.append(("rect", x, y, ancho, alto, c2))
            y += alto + 10
        if r.random() < 0.4:
            figs.append(("rect", x, y, ancho, 12, c1))
            y += 22
        while y < 178:
            largo = ancho if r.random() < 0.85 else ancho * r.uniform(0.4, 0.8)
            figs.append(("line", [(x, y), (x + largo, y)], c1, 3))
            y += 9
        x += ancho + 14
        if x < W - 40:
            figs.append(("line", [(x - 7, 20), (x - 7, 180)], c2, 1.2))
    return figs


def titulares(r, c1, c2, fondo):
    """Titulares en bloques gruesos sobre periódicos doblados, con la fecha arriba y una foto al lado."""
    figs = []
    n = r.randint(3, 4)
    xs, paso = _puntos_repartidos(r, n, 50)
    for x in xs:
        ancho = paso * r.uniform(0.75, 0.9)
        x0 = x - ancho / 2
        ang = r.uniform(-0.06, 0.06)
        figs.append(rect_girado(x, 100, ancho, 170, ang, c2))
        figs.append(rect_girado(x, 100, ancho - 10, 160, ang, fondo))
        y = 34
        figs.append(("line", [(x0 + 12, y), (x0 + ancho - 12, y)], c1, 1.5))
        y += 14
        for _ in range(r.randint(1, 2)):
            figs.append(("rect", x0 + 12, y, (ancho - 24) * r.uniform(0.6, 1), 16, c1))
            y += 24
        foto = r.random() < 0.6
        ancho_txt = (ancho - 24) * (0.55 if foto else 1)
        if foto:
            figs.append(("rect", x0 + 18 + ancho_txt, y, ancho - 30 - ancho_txt, 170 - y, c2))
        while y < 168:
            figs.append(("line", [(x0 + 12, y), (x0 + 12 + ancho_txt, y)], c1, 2.5))
            y += 8
    return figs


def _microfono(x, y, s, c1, c2):
    return [elipse(x, y, s * 0.42, s * 0.6, 0, c1, n=14),
            ("line", [(x - s * 0.42, y - s * 0.1), (x + s * 0.42, y - s * 0.1)], c2, 1.5),
            ("line", [(x - s * 0.42, y + s * 0.15), (x + s * 0.42, y + s * 0.15)], c2, 1.5),
            ("line", [(x - s * 0.6, y + s * 0.1), (x - s * 0.6, y + s * 0.55), (x + s * 0.6, y + s * 0.55),
                      (x + s * 0.6, y + s * 0.1)], c1, 3),
            ("line", [(x, y + s * 0.55), (x, y + s * 1.1)], c1, 3)]


def microfonos(r, c1, c2, fondo):
    """Micrófonos de prensa que se acercan desde los lados, como en una rueda de prensa."""
    figs = []
    n = r.randint(5, 8)
    xs, paso = _puntos_repartidos(r, n, 60)
    for x in xs:
        s = r.uniform(30, min(48, paso * 0.45))
        y = r.uniform(50, 110)
        figs += _microfono(x, y, s, c1, c2)
        figs.append(("line", [(x, y + s * 1.1), (x + r.uniform(-60, 60), H)], c1, 2.5))
    return figs


def teclas(r, c1, c2, fondo):
    """Teclas redondas de máquina de escribir en hileras escalonadas, con la barra espaciadora."""
    aros, centros, marcadas = [], [], []
    rad = r.uniform(17, 22)
    paso = rad * 2.6
    filas = [44, 44 + paso * 0.95, 44 + paso * 1.9]
    for k, y in enumerate(filas):
        x = 30 + k * paso * 0.45 + r.uniform(0, paso)
        while x < W - 30:
            aros.append(("ring", x, y, rad, c1, 3))
            # Algunas teclas resaltadas en el color secundario.
            if r.random() < 0.12:
                marcadas.append(("circle", x, y, rad * 0.55, c2))
            else:
                centros.append(("circle", x, y, rad * 0.55, c1))
            x += paso
    # Agrupadas por tipo y color, para que el SVG pese poco.
    return aros + centros + marcadas + [("rect", r.uniform(300, 600), filas[-1] + paso * 0.75, r.uniform(380, 520), 16, c1)]


# ------------------------------------------------- Diccionarios y enciclopedias

def lomos_de_libros(r, c1, c2, fondo):
    """Una estantería de tomos de enciclopedia del mismo alto, con bandas y números en los lomos."""
    figs = [("rect", 0, 182, W, 8, c1)]
    x = r.uniform(0, 30)
    while x < W:
        ancho = r.uniform(34, 52)
        alto = r.uniform(140, 160)
        color = r.choice([c1, c1, c2])
        figs.append(("rect", x, 182 - alto, ancho - 4, alto, color))
        for y in (182 - alto + 14, 182 - 22):
            figs.append(("rect", x + 4, y, ancho - 12, 4, fondo))
        figs.append(("rect", x + (ancho - 4) / 2 - 7, 182 - alto + 30, 14, 20, fondo))
        if r.random() < 0.15:
            x += ancho
            figs.append(rect_girado(x + 20, 182 - alto * 0.47, ancho - 4, alto, -0.2, c2))
            x += 30
        x += ancho
    return figs


def pestanas_indice(r, c1, c2, fondo):
    """Bordes de páginas con pestañas de índice escalonadas, como las de un diccionario grueso."""
    figs = []
    n = r.randint(2, 3)
    xs, paso = _puntos_repartidos(r, n, 140)
    for x in xs:
        ancho = paso * 0.75
        x0 = x - ancho / 2
        figs.append(("rect", x0, 30, ancho, 150, c1))
        for k in range(1, 9):
            figs.append(("line", [(x0 + ancho, 30 + k * 16), (x0 + ancho + 6, 30 + k * 16)], c2, 1))
        y = 34
        while y < 170:
            alto = r.uniform(12, 18)
            figs.append(("rect", x0 + ancho, y, r.uniform(14, 22), alto, r.choice([c1, c2])))
            y += alto + r.uniform(3, 8)
        for k in range(r.randint(6, 9)):
            figs.append(("line", [(x0 + 14, 46 + k * 15), (x0 + ancho * r.uniform(0.5, 0.92), 46 + k * 15)], fondo, 2.5))
    return figs


def _lupa(x, y, rad, ang, c1, c2):
    return [("ring", x, y, rad, c1, 5),
            ("line", _colocar([(rad + 2, 0), (rad * 2.1, 0)], x, y, ang), c1, 8)]


def lupas(r, c1, c2, fondo):
    """Lupas que se pasean sobre renglones de texto pequeño y lo agrandan donde caen."""
    figs = []
    for k in range(10):
        y = 24 + k * 17
        x = r.uniform(10, 40)
        while x < W - 30:
            largo = r.uniform(30, 90)
            figs.append(("line", [(x, y), (x + largo, y)], c2, 2))
            x += largo + r.uniform(10, 18)
    n = r.randint(3, 5)
    xs, _ = _puntos_repartidos(r, n, 90)
    for x in xs:
        rad = r.uniform(34, 50)
        y = r.uniform(70, 120)
        figs.append(("circle", x, y, rad, fondo))
        for k in range(3):
            yy = y - rad * 0.45 + k * rad * 0.45
            figs.append(("line", [(x - rad * 0.6, yy), (x + rad * 0.6, yy)], c1, 5))
        figs += _lupa(x, y, rad, r.uniform(0.4, 1.0), c1, c2)
    return figs


def fichero(r, c1, c2, fondo):
    """Cajones de fichero de biblioteca, con tiradores y tarjetas que asoman."""
    figs = []
    filas, cols = 2, r.randint(6, 9)
    margen = 30
    ancho = (W - 2 * margen) / cols
    alto = (H - 2 * 20) / filas
    for i in range(filas):
        for j in range(cols):
            x, y = margen + j * ancho, 20 + i * alto
            figs.append(("line", _cerrada(_rect_pts(x + 3, y + 3, ancho - 6, alto - 6)), c1, 3))
            figs.append(("rect", x + ancho / 2 - 16, y + alto * 0.55, 32, 9, c1))
            figs.append(("rect", x + ancho / 2 - 18, y + alto * 0.22, 36, 16, c2))
            if r.random() < 0.3:
                for k in range(r.randint(2, 4)):
                    figs.append(("rect", x + 10 + k * 7, y - 8 - k * 3, ancho - 26, 12, r.choice([c1, c2])))
    return figs


# ----------------------------------------------------------------- Discursos

def atril(r, c1, c2, fondo):
    """Un atril con micrófono frente a filas de cabezas que escuchan."""
    figs = []
    x = r.uniform(200, W - 200)
    figs.append(("poly", [(x - 60, 70), (x + 60, 70), (x + 44, 186), (x - 44, 186)], c1))
    figs.append(("rect", x - 72, 62, 144, 12, c1))
    figs.append(("line", [(x + 20, 62), (x + 28, 30)], c1, 3))
    figs.append(elipse(x + 30, 24, 7, 10, 0.3, c1, n=10))
    figs.append(estrella(x, 120, 18, fondo, puntas=r.choice([5, 8]), k=0.45))
    cabezas, hombros = [], []
    for k in range(2):
        y = 156 + k * 22
        cx = r.uniform(0, 40)
        while cx < W:
            if abs(cx - x) > 90:
                cabezas.append(("circle", cx, y, 11, c2))
                hombros.append(("rect", cx - 18, y + 14, 36, 30, c2))
            cx += r.uniform(36, 50)
    return figs + cabezas + hombros


def ondas_de_voz(r, c1, c2, fondo):
    """La forma de onda de una voz: barras verticales que suben y bajan con el ritmo de las frases."""
    figs = []
    x = 20.0
    fase = r.uniform(0, TAU)
    while x < W - 20:
        frase = r.uniform(120, 260)
        fin = min(x + frase, W - 20)
        while x < fin:
            env = math.sin(math.pi * (x - (fin - frase)) / frase)
            alto = max(4, env * r.uniform(40, 82) * (0.6 + 0.4 * math.sin(x / 23 + fase)))
            figs.append(("line", [(x, 100 - alto), (x, 100 + alto)], c1, 4))
            x += 9
        x += r.uniform(30, 60)
    figs.append(("line", [(0, 100), (W, 100)], c2, 1.5))
    return figs


def multitud(r, c1, c2, fondo):
    """Una multitud de espaldas que mira hacia el frente, con algunos brazos en alto."""
    figs = []
    for k, (base, color) in enumerate(((150, c2), (190, c1))):
        cabezas, cuerpos, brazos = [], [], []
        x = r.uniform(-10, 20)
        while x < W + 10:
            s = r.uniform(14, 18)
            cabezas.append(("circle", x, base - 46, s, color))
            cuerpos.append(("poly", _arco(x, base, s * 1.9, math.pi, TAU, n=5), color))
            if k > 0 and r.random() < 0.15:
                lado = r.choice([-1, 1])
                brazos.append(("line", [(x + lado * s, base - 34), (x + lado * s * 1.8, base - 84)], color, 6))
            x += s * 2.8 + r.uniform(0, 10)
        figs += cuerpos + cabezas + brazos
    return figs


def _megafono(x, y, s, ang, c1, c2):
    return [("poly", _colocar([(0, -s * 0.18), (s, -s * 0.5), (s, s * 0.5), (0, s * 0.18)], x, y, ang), c1),
            ("poly", _colocar([(-s * 0.25, -s * 0.16), (0, -s * 0.18), (0, s * 0.18), (-s * 0.25, s * 0.16)], x, y, ang), c1),
            ("line", _colocar([(-s * 0.1, s * 0.15), (-s * 0.05, s * 0.5)], x, y, ang), c1, 4)]


def megafonos(r, c1, c2, fondo):
    """Megáfonos que lanzan arcos de sonido en distintas direcciones."""
    figs = []
    n = r.randint(3, 5)
    xs, paso = _puntos_repartidos(r, n, 90)
    for x in xs:
        s = r.uniform(50, 72)
        y = r.uniform(70, 130)
        ang = r.uniform(-0.5, 0.3)
        figs += _megafono(x - s * 0.4, y, s, ang, c1, c2)
        boca = _colocar([(s, 0)], x - s * 0.4, y, ang)[0]
        for k in range(1, 4):
            figs.append(("line", _arco(boca[0], boca[1], 14 * k, ang - 0.6, ang + 0.6, n=6), c2, 2.5))
    return figs


MOTIVOS_AMPLIACION = {
    "faro": faro, "relojes": relojes, "tierra_baldia": tierra_baldia, "fragmentos": fragmentos,
    "castillo": castillo, "hongos": hongos, "llaves": llaves, "coronas": coronas,
    "versos": versos, "pajaros_en_cable": pajaros_en_cable, "petalos": petalos, "lira": lira,
    "linea_de_tiempo": linea_de_tiempo, "piramides": piramides, "monedas": monedas, "reloj_de_arena": reloj_de_arena,
    "columnas_de_periodico": columnas_de_periodico, "titulares": titulares, "microfonos": microfonos, "teclas": teclas,
    "lomos_de_libros": lomos_de_libros, "pestanas_indice": pestanas_indice, "lupas": lupas, "fichero": fichero,
    "atril": atril, "ondas_de_voz": ondas_de_voz, "multitud": multitud, "megafonos": megafonos,
}
