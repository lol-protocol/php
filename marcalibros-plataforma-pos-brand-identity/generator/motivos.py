"""Motivos visuales de los marcalibros (lienzo de 1400 x 200).

Cada motivo recibe un generador aleatorio determinista `r` y tres colores de la
paleta: `c1` (principal, siempre con buen contraste sobre el fondo), `c2`
(secundario) y `fondo`. Devuelve una lista de figuras en este formato:

    ("rect", x, y, ancho, alto, color)
    ("circle", cx, cy, radio, color)
    ("poly", [(x, y), ...], color)
    ("line", [(x, y), ...], color, grosor)
    ("ring", cx, cy, radio, color, grosor)

Los colores son nombres de la paleta, nunca valores hex. El generador agrupa las
figuras vecinas del mismo tipo y color para que el SVG pese poco, así que
conviene ordenar las figuras por color cuando el orden de apilado no importa.
"""
import math

W, H = 1400, 200
TAU = 2 * math.pi


# ---------------------------------------------------------------- utilidades

def _girar(x, y, ang):
    c, s = math.cos(ang), math.sin(ang)
    return x * c - y * s, x * s + y * c


def _colocar(pts, cx, cy, ang=0.0):
    out = []
    for x, y in pts:
        gx, gy = _girar(x, y, ang)
        out.append((cx + gx, cy + gy))
    return out


def hoja(cx, cy, largo, ancho, ang, color, n=3):
    """Hoja o pétalo que nace en (cx, cy) y apunta hacia `ang`."""
    sup = [(largo * k / n, (ancho / 2) * math.sin(math.pi * k / n)) for k in range(n + 1)]
    inf = [(x, -y) for x, y in reversed(sup)][1:-1]
    return ("poly", _colocar(sup + inf, cx, cy, ang), color)


def elipse(cx, cy, rx, ry, ang, color, n=12):
    pts = [(rx * math.cos(TAU * k / n), ry * math.sin(TAU * k / n)) for k in range(n)]
    return ("poly", _colocar(pts, cx, cy, ang), color)


def rect_girado(cx, cy, ancho, alto, ang, color):
    a, b = ancho / 2, alto / 2
    return ("poly", _colocar([(-a, -b), (a, -b), (a, b), (-a, b)], cx, cy, ang), color)


def estrella(cx, cy, s, color, puntas=4, k=0.3):
    pts = []
    for j in range(puntas * 2):
        rad = s if j % 2 == 0 else s * k
        a = -math.pi / 2 + j * math.pi / puntas
        pts.append((cx + rad * math.cos(a), cy + rad * math.sin(a)))
    return ("poly", pts, color)


def flor(cx, cy, rad, petalos, c_petalo, c_centro, fase=0.0):
    """Flor de pétalos redondos. Devuelve (pétalos, centro) para ordenar por color."""
    pet = [("circle", cx + 0.62 * rad * math.cos(fase + TAU * k / petalos),
            cy + 0.62 * rad * math.sin(fase + TAU * k / petalos), rad * 0.4, c_petalo)
           for k in range(petalos)]
    return pet, ("circle", cx, cy, rad * 0.26, c_centro)


def mariposa(cx, cy, s, ang, c_alas, c_cuerpo):
    figs = []
    for lado in (1, -1):
        for ox, oy, rx, ry, rot in ((0.55, -0.35, 0.6, 0.38, -0.5), (0.42, 0.42, 0.38, 0.3, 0.4)):
            pts = []
            for k in range(10):
                t = TAU * k / 10
                qx, qy = _girar(rx * math.cos(t), ry * math.sin(t), rot)
                pts.append((lado * (ox + qx) * s, (oy + qy) * s))
            figs.append(("poly", _colocar(pts, cx, cy, ang), c_alas))
    figs.append(("line", _colocar([(0, -0.5 * s), (0, 0.6 * s)], cx, cy, ang), c_cuerpo, 2))
    return figs


def murcielago(cx, cy, s, color):
    pts = [(0, 0), (-0.4, -0.5), (-1.0, -0.3), (-0.8, 0.2), (-0.5, 0.0), (-0.25, 0.3), (0, 0.1),
           (0.25, 0.3), (0.5, 0.0), (0.8, 0.2), (1.0, -0.3), (0.4, -0.5)]
    return ("poly", [(cx + x * s, cy + y * s) for x, y in pts], color)


def colinas(r, base, amp, color, paso=20):
    fase = r.uniform(0, TAU)
    pts = [(0, H)]
    for x in range(0, W + 1, paso):
        pts.append((x, base + amp * math.sin(x / 180 + fase) + amp * 0.45 * math.sin(x / 67 + 2 * fase)))
    pts.append((W, H))
    return ("poly", pts, color)


def onda(y0, amp, lam, fase, paso=14):
    return [(x, y0 + amp * math.sin(TAU * x / lam + fase)) for x in range(-10, W + 11, paso)]


def espiral(cx, cy, r0, r1, vueltas, ang0=0.0, sentido=1, n=28):
    pts = []
    for k in range(n + 1):
        t = k / n
        rad = r0 + (r1 - r0) * t
        a = ang0 + sentido * TAU * vueltas * t
        pts.append((cx + rad * math.cos(a), cy + rad * math.sin(a)))
    return pts


def ogiva(x, ancho, y_base, y_arranque, n=5):
    """Arco apuntado equilátero entre x y x + ancho, con arranque en y_arranque."""
    pts = [(x, y_base), (x, y_arranque)]
    for k in range(1, n + 1):
        a = math.pi - (math.pi / 3) * k / n
        pts.append((x + ancho + ancho * math.cos(a), y_arranque - ancho * math.sin(a)))
    for k in range(1, n + 1):
        a = math.pi / 3 - (math.pi / 3) * k / n
        pts.append((x + ancho * math.cos(a), y_arranque - ancho * math.sin(a)))
    pts.append((x + ancho, y_base))
    return pts


def espejar(figs):
    """Refleja las figuras de izquierda a derecha."""
    out = []
    for g in figs:
        k = g[0]
        if k == "rect":
            out.append(("rect", W - g[1] - g[3], g[2], g[3], g[4], g[5]))
        elif k == "circle":
            out.append(("circle", W - g[1], g[2], g[3], g[4]))
        elif k == "poly":
            out.append(("poly", [(W - x, y) for x, y in g[1]], g[2]))
        elif k == "line":
            out.append(("line", [(W - x, y) for x, y in g[1]], g[2], g[3]))
        elif k == "ring":
            out.append(("ring", W - g[1], g[2], g[3], g[4], g[5]))
    return out


# ------------------------------------------------------------- Romanticismo

def amanecer(r, c1, c2, fondo):
    figs = [("circle", r.uniform(300, 1100), H * 0.62, r.uniform(40, 70), c1)]
    for alto_rel, color in ((0.45, c2), (0.25, c1)):
        fase = r.uniform(0, TAU)
        amp = r.uniform(10, 22)
        pts = [(0, H)]
        for x in range(0, W + 1, 20):
            pts.append((x, H * (1 - alto_rel) + amp * math.sin(x / 180 + fase) + 8 * math.sin(x / 67 + 2 * fase)))
        pts.append((W, H))
        figs.append(("poly", pts, color))
    return figs


def arbol_solitario(r, c1, c2, fondo):
    figs = [("circle", r.uniform(200, 1200), r.uniform(55, 90), r.uniform(26, 42), c2)]
    fase = r.uniform(0, TAU)
    figs.append(("poly", [(0, H)] + [(x, 146 + 14 * math.sin(x / 230 + fase)) for x in range(0, W + 1, 20)] + [(W, H)], c2))
    cx = r.uniform(250, 1150)

    def suelo(x):
        return 168 - 34 * math.exp(-((x - cx) / 240) ** 2) + 5 * math.sin(x / 90 + fase)

    figs.append(("poly", [(0, H)] + [(x, suelo(x)) for x in range(0, W + 1, 20)] + [(W, H)], c1))
    y0 = suelo(cx)
    alto = r.uniform(46, 70)
    figs.append(("rect", cx - 3, y0 - alto, 6, alto + 4, c1))
    for _ in range(r.randint(5, 7)):
        figs.append(("circle", cx + r.uniform(-26, 26), y0 - alto - r.uniform(0, 22), r.uniform(15, 24), c1))
    for _ in range(3):
        bx, by = r.uniform(80, W - 80), r.uniform(25, 85)
        figs.append(("line", [(bx - 9, by - 5), (bx, by), (bx + 9, by - 5)], c1, 2.2))
    return figs


def nubes(r, c1, c2, fondo):
    figs = [("circle", r.uniform(250, 1150), 108, r.uniform(34, 52), c1)]
    for y0, color, rmin, rmax in ((138, c2, 16, 30), (176, c1, 20, 36)):
        x = -30.0
        while x < W + 40:
            rad = r.uniform(rmin, rmax)
            figs.append(("circle", x, y0 - r.uniform(0, 12), rad, color))
            x += rad * r.uniform(0.9, 1.4)
        figs.append(("rect", 0, y0, W, H - y0, color))
    for _ in range(r.randint(5, 8)):
        x, y, largo = r.uniform(0, W - 150), r.uniform(18, 90), r.uniform(70, 220)
        figs.append(("line", [(x, y), (x + largo, y)], r.choice([c1, c2]), 3))
    return figs


def acantilado_mar(r, c1, c2, fondo):
    figs = [("circle", r.uniform(500, 1250), r.uniform(45, 85), r.uniform(26, 38), c2)]
    for k in range(4):
        figs.append(("line", onda(126 + k * 20, 4, r.uniform(120, 200), r.uniform(0, TAU), 16),
                     c2 if k % 2 == 0 else c1, 2.5))
    cw = r.uniform(300, 520)
    pts = [(0, H)]
    for x in range(0, int(cw) + 1, 20):
        pts.append((x, 50 + ((x / cw) ** 1.6) * (H - 50) + r.uniform(-6, 6)))
    pts.append((int(cw), H))
    figs.append(("poly", pts, c1))
    fx = 60
    yf = 50 + ((fx / cw) ** 1.6) * (H - 50) - 4
    figs.append(("poly", [(fx - 9, yf), (fx + 9, yf), (fx + 5, yf - 46), (fx - 5, yf - 46)], c1))
    figs.append(("poly", [(fx - 8, yf - 46), (fx, yf - 60), (fx + 8, yf - 46)], c1))
    figs.append(("circle", fx, yf - 52, 6, c2))
    return espejar(figs) if r.random() < 0.5 else figs


# ----------------------------------------------------------------- Realismo

def ciudad(r, c1, c2, fondo):
    figs, ventanas = [], []
    x = 0.0
    while x < W:
        ancho, alto = r.uniform(50, 110), r.uniform(50, 160)
        figs.append(("rect", x, H - alto, ancho - 6, alto, c2 if r.random() < 0.35 else c1))
        for vx in range(int(x + 8), int(x + ancho - 14), 16):
            for vy in range(int(H - alto + 12), H - 14, 18):
                if r.random() < 0.5:
                    ventanas.append(("rect", vx, vy, 6, 8, fondo))
        x += ancho
    return figs + ventanas


def puente(r, c1, c2, fondo):
    figs = [("circle", r.uniform(150, 1250), r.uniform(40, 70), r.uniform(24, 36), c2)]
    agua = 150
    figs.append(("rect", 0, 88, W, agua - 88, c1))
    ancho = r.uniform(100, 150)
    x = -r.uniform(0, ancho)
    while x < W:
        cx = x + ancho / 2
        figs.append(("poly", [(cx + 0.4 * ancho * math.cos(math.pi * k / 12), agua - 38 * math.sin(math.pi * k / 12))
                              for k in range(13)], fondo))
        x += ancho
    figs.append(("rect", 0, 76, W, 3, c1))
    for px in range(0, W, 14):
        figs.append(("rect", px, 79, 3, 9, c1))
    for k in range(3):
        figs.append(("line", onda(162 + k * 14, 3, r.uniform(90, 160), r.uniform(0, TAU), 12),
                     c2 if k != 1 else c1, 2.5))
    return figs


def farolas(r, c1, c2, fondo):
    figs, ventanas = [], []
    x = 0.0
    while x < W:
        ancho, alto = r.uniform(60, 120), r.uniform(70, 125)
        figs.append(("rect", x, H - alto, ancho - 4, alto, c2))
        for vx in range(int(x + 8), int(x + ancho - 16), 18):
            for vy in range(int(H - alto + 10), H - 30, 22):
                if r.random() < 0.45:
                    ventanas.append(("rect", vx, vy, 7, 10, fondo))
        x += ancho
    figs += ventanas
    figs.append(("rect", 0, H - 16, W, 16, c1))
    paso = r.uniform(130, 190)
    x = r.uniform(30, paso)
    postes, lamparas, brillos = [], [], []
    while x < W:
        postes.append(("rect", x - 2, 56, 4, H - 16 - 56, c1))
        postes.append(("rect", x - 7, H - 30, 14, 14, c1))
        lamparas.append(("circle", x, 48, 10, fondo))
        brillos.append(("ring", x, 48, 10, c1, 3))
        brillos.append(("ring", x, 48, 22, c2, 2))
        x += paso
    return figs + postes + lamparas + brillos


def chimeneas(r, c1, c2, fondo):
    figs, humo = [], []
    x = 0.0
    while x < W:
        ancho, alto = r.uniform(90, 170), r.uniform(40, 70)
        figs.append(("rect", x, H - alto, ancho - 5, alto, c1))
        for k in range(int(ancho // 30)):
            figs.append(("poly", [(x + k * 30, H - alto), (x + k * 30, H - alto - 16), (x + k * 30 + 26, H - alto)], c1))
        if r.random() < 0.6:
            cx, ch = x + r.uniform(15, ancho - 20), r.uniform(55, 95)
            figs.append(("rect", cx, H - alto - ch, 12, ch, c1))
            fase = r.uniform(0, TAU)
            humo.append(("line", [(cx + 6 + 8 * math.sin(k / 1.6 + fase) + k * 3, H - alto - ch - k * 6)
                                  for k in range(8)], c2, 4))
        x += ancho
    return figs + humo


# --------------------------------------------------------------- Naturalismo

def bosque(r, c1, c2, fondo):
    figs = [("circle", r.uniform(200, 1200), 60, r.uniform(22, 34), c2)]
    for color, hmin, hmax in ((c2, 50, 110), (c1, 90, 170)):
        x = -20.0
        while x < W + 20:
            alto = r.uniform(hmin, hmax)
            ancho = alto * r.uniform(0.38, 0.5)
            figs.append(("poly", [(x + ancho / 2, H - alto), (x + ancho, H), (x, H)], color))
            x += ancho * r.uniform(0.6, 0.9)
    return figs


def juncos(r, c1, c2, fondo):
    agua = H - 30
    figs = [("rect", 0, agua, W, 30, c2)]
    tallos, espigas = [], []
    for _ in range(r.randint(34, 44)):
        x, alto, inc = r.uniform(0, W), r.uniform(70, 170), r.uniform(-25, 25)
        pts = [(x + inc * (t / 6) ** 2, agua - alto * t / 6) for t in range(7)]
        tallos.append(("line", pts, c1, 2.5))
        if r.random() < 0.45:
            ang = math.atan2(pts[5][1] - pts[4][1], pts[5][0] - pts[4][0])
            espigas.append(elipse(pts[5][0], pts[5][1], 11, 3.5, ang, c1, 8))
    for k in range(3):
        figs.append(("line", onda(agua + 8 + k * 8, 1.5, r.uniform(80, 140), r.uniform(0, TAU), 20), fondo, 2))
    return figs + tallos + espigas


def trigo(r, c1, c2, fondo):
    figs = [("circle", r.uniform(150, 1250), 66, r.uniform(28, 40), c2), ("rect", 0, 140, W, 60, c2)]
    for y0, color, hmin, hmax in ((150, c2, 40, 80), (H + 6, c1, 60, 115)):
        tallos, granos = [], []
        x = r.uniform(0, 12)
        while x < W:
            alto, inc = r.uniform(hmin, hmax), r.uniform(-12, 12)
            punta = (x + inc, y0 - alto)
            tallos.append(("line", [(x, y0), (x + inc * 0.3, y0 - alto * 0.5), punta], color, 2))
            granos.append(hoja(punta[0], punta[1] + 18, 24, 8, -math.pi / 2 + inc / 90, color, 2))
            x += r.uniform(24, 32)
        figs += tallos + granos
    return figs


def rio(r, c1, c2, fondo):
    fase, amp, lam = r.uniform(0, TAU), r.uniform(35, 55), r.uniform(380, 560)
    ancho = r.uniform(26, 38)

    def eje(x):
        return 100 + amp * math.sin(TAU * x / lam + fase)

    sup = [(x, eje(x) - ancho / 2) for x in range(-20, W + 21, 20)]
    inf = [(x, eje(x) + ancho / 2) for x in range(W + 20, -21, -20)]
    figs = [("poly", sup + inf, c2)]
    x = 10
    while x < W:
        lado = r.choice([-1, 1])
        figs.append(("circle", x, eje(x) + lado * (ancho / 2 + r.uniform(14, 55)), r.uniform(5, 11), c1))
        x += r.randint(22, 32)
    return figs


# ---------------------------------------------------------------- Modernismo

def ondas(r, c1, c2, fondo):
    figs = []
    n = r.randint(4, 6)
    for i in range(n):
        figs.append(("line", onda(H * (0.15 + 0.7 * i / max(n - 1, 1)), r.uniform(6, 16), r.uniform(220, 420),
                                  r.uniform(0, TAU)), c1 if i % 2 == 0 else c2, 3))
    return figs


def ondas_concentricas(r, c1, c2, fondo):
    figs = []
    n = r.randint(3, 5)
    for k in range(n):
        cx, cy = (k + 0.5) * W / n + r.uniform(-60, 60), r.uniform(60, 140)
        color = c1 if k % 2 == 0 else c2
        paso = r.uniform(14, 18)
        for j in range(1, r.randint(6, 9)):
            figs.append(("ring", cx, cy, j * paso, color, 2.2))
    return figs


def nouveau(r, c1, c2, fondo):
    fase, lam, amp = r.uniform(0, TAU), r.uniform(300, 420), r.uniform(35, 55)

    def eje(x):
        return 100 + amp * math.sin(TAU * x / lam + fase)

    figs = [("line", [(x, eje(x)) for x in range(-10, W + 11, 10)], c1, 3.5)]
    hojas, petalos, centros = [], [], []
    x = r.uniform(40, 120)
    while x < W - 30:
        y = eje(x)
        lado = -1 if math.sin(TAU * x / lam + fase) > 0 else 1
        largo = r.uniform(34, 56)
        figs.append(("line", [(x, y), (x + 8, y + lado * largo)], c1, 2.5))
        p, c = flor(x + 8, y + lado * largo, r.uniform(9, 14), 5, c2, fondo, r.uniform(0, TAU))
        petalos += p
        centros.append(c)
        hojas.append(hoja(x, y, 26, 11, (-math.pi / 2 if lado == -1 else math.pi / 2) + r.uniform(0.6, 1.2), c1))
        x += r.uniform(70, 110)
    return figs + hojas + petalos + centros


def mosaico(r, c1, c2, fondo):
    a = r.choice([26, 30, 36])
    b = a * r.uniform(1.15, 1.45)
    desfase = r.randrange(3)
    l1, l2, puntos = [], [], []
    for j in range(-1, int(H / b) + 2):
        for i in range(-1, W // (2 * a) + 2):
            cx, cy = i * 2 * a + (j % 2) * a, j * b
            k = (i + j + desfase) % 3
            if k == 2:
                if r.random() < 0.5:
                    puntos.append(("circle", cx, cy, a * 0.16, c2))
                continue
            pts = [(cx, cy - b * 0.86), (cx + a * 0.86, cy), (cx, cy + b * 0.86), (cx - a * 0.86, cy)]
            (l1 if k == 0 else l2).append(("poly", pts, c1 if k == 0 else c2))
    return l1 + l2 + puntos


# --------------------------------------------------------------- Vanguardias

def geometria(r, c1, c2, fondo):
    figs = [("circle", r.uniform(150, 1250), r.uniform(30, 170), r.uniform(55, 90), c2)]
    for _ in range(r.randint(4, 6)):
        figs.append(rect_girado(r.uniform(0, W), r.uniform(60, 140), r.uniform(200, 380), r.uniform(16, 34),
                                r.choice([-1, 1]) * r.uniform(0.35, 0.8), c1))
    for _ in range(r.randint(3, 5)):
        cx, cy, t = r.uniform(60, W - 60), r.uniform(50, 150), r.uniform(26, 54)
        a = r.uniform(0, TAU)
        figs.append(("poly", [(cx + t * math.cos(a + k * TAU / 3), cy + t * math.sin(a + k * TAU / 3))
                              for k in range(3)], r.choice([c1, c2])))
    for _ in range(2):
        figs.append(("line", [(r.uniform(0, W), 0), (r.uniform(0, W), H)], c1, 2.5))
    return figs


def constructivista(r, c1, c2, fondo):
    ang = r.uniform(0.45, 0.75) * r.choice([-1, 1])
    n = r.randint(5, 8)
    figs = []
    for k in range(n):
        figs.append(rect_girado((k + 0.5) * W / n + r.uniform(-30, 30), 100, r.uniform(14, 44), 420, ang,
                                c1 if k % 2 == 0 else c2))
    cx = r.uniform(250, 1150)
    figs.append(("circle", cx, 100, r.uniform(40, 62), fondo))
    figs.append(("ring", cx, 100, r.uniform(48, 70), c1, 4))
    for _ in range(r.randint(4, 7)):
        figs.append(rect_girado(r.uniform(30, W - 30), r.uniform(30, 170), 16, 16, r.uniform(0, 1.5), c1))
    return figs


def caligrama(r, c1, c2, fondo):
    por_color = {c2: [], c1: []}
    n = r.randint(20, 26)
    for k in range(n):
        x, largo, y0 = (k + r.uniform(0.1, 0.9)) * W / n, r.uniform(60, 130), 0.0
        y0 = r.uniform(-10, H - largo * 0.6)
        color = c1 if r.random() < 0.7 else c2
        for j in range(int(largo // 14)):
            por_color[color].append(("circle", x + 0.34 * j * 14, y0 + 0.94 * j * 14, 3 if j % 3 == 0 else 2.2, color))
    return por_color[c2] + por_color[c1]


def collage(r, c1, c2, fondo):
    figs, sobre = [], []
    x = r.uniform(-20, 10)
    while x < W:
        ancho, alto = r.uniform(80, 220), r.uniform(60, 170)
        y = r.uniform(0, H - alto)
        color = r.choice([c1, c2, c1])
        figs.append(("rect", x, y, ancho, alto, color))
        if r.random() < 0.7:
            sobre.append(("rect", x + r.uniform(10, ancho * 0.5), y + r.uniform(8, alto * 0.5), ancho * 0.45,
                          alto * 0.45, c2 if color == c1 else c1))
        x += ancho * r.uniform(0.55, 0.9)
    tx, ty = r.uniform(100, W - 220), r.uniform(20, 90)
    for i in range(12):
        for j in range(7):
            sobre.append(("circle", tx + i * 10, ty + j * 10, 2.6, fondo))
    return figs + sobre


# -------------------------------------------------------------------- Gótico

def arcos_ojivales(r, c1, c2, fondo):
    figs = [("rect", 0, 0, W, H, c1)]
    ancho, hueco = r.uniform(54, 72), r.uniform(26, 40)
    arranque = r.uniform(112, 128)
    x = r.uniform(10, 30)
    marcos, aberturas, partes, rosetas = [], [], [], []
    while x + ancho < W:
        marcos.append(("poly", ogiva(x - 5, ancho + 10, H + 1, arranque + 2), c2))
        aberturas.append(("poly", ogiva(x, ancho, H + 1, arranque), fondo))
        partes.append(("rect", x + ancho / 2 - 2, arranque - ancho * 0.5, 4, H - arranque + ancho * 0.5, c1))
        rosetas.append(("circle", x + ancho / 2, arranque - ancho * 0.62, ancho * 0.1, c1))
        x += ancho + hueco
    return figs + marcos + aberturas + partes + rosetas


def roseton(r, c1, c2, fondo):
    figs, lobulos, radios = [], [], []
    n = r.randint(2, 3)
    for k in range(n):
        cx, cy, R = (k + 0.5) * W / n + r.uniform(-50, 50), 100, r.uniform(72, 90)
        figs.append(("circle", cx, cy, R, c1))
        figs.append(("circle", cx, cy, R - 8, fondo))
        petalos = r.choice([8, 10, 12])
        for j in range(petalos):
            a = TAU * j / petalos
            lobulos.append(("circle", cx + 0.62 * R * math.cos(a), cy + 0.62 * R * math.sin(a), R * 0.2, c2))
            radios.append(("line", [(cx, cy), (cx + (R - 8) * math.cos(a), cy + (R - 8) * math.sin(a))], c1, 3))
        radios.append(("ring", cx, cy, R * 0.42, c1, 3))
        radios.append(("circle", cx, cy, R * 0.22, c1))
    return figs + lobulos + radios


def torres(r, c1, c2, fondo):
    figs = [("circle", r.uniform(200, 1200), r.uniform(45, 90), r.uniform(26, 40), c2)]
    for _ in range(3):
        figs.append(murcielago(r.uniform(60, W - 60), r.uniform(20, 90), r.uniform(8, 13), c1))
    cuerpos, tejados, huecos = [], [], []
    x = 0.0
    while x < W:
        ancho, alto = r.uniform(36, 60), r.uniform(70, 150)
        y = H - alto
        cuerpos.append(("rect", x, y, ancho, alto, c1))
        tejados.append(("poly", [(x - 4, y), (x + ancho / 2, y - r.uniform(35, 70)), (x + ancho + 4, y)], c1))
        huecos.append(("poly", ogiva(x + ancho / 2 - 5, 10, y + 36, y + 30, 3), fondo))
        x += ancho + r.uniform(10, 60)
    figs += cuerpos + tejados + huecos
    figs.append(("rect", 0, H - 42, W, 42, c1))
    for ax in range(0, W, 28):
        figs.append(("rect", ax, H - 54, 16, 12, c1))
    return figs


def cementerio(r, c1, c2, fondo):
    figs = [("circle", r.uniform(250, 1150), r.uniform(45, 85), r.uniform(28, 40), c2)]
    fase = r.uniform(0, TAU)
    figs.append(("poly", [(0, H)] + [(x, 168 + 8 * math.sin(x / 120 + fase)) for x in range(0, W + 1, 20)] + [(W, H)], c2))
    gruesas, finas = [], []

    def ramas(x, y, largo, ang, prof):
        if prof == 0:
            return
        x2, y2 = x + largo * math.cos(ang), y + largo * math.sin(ang)
        (gruesas if prof > 2 else finas).append(("line", [(x, y), (x2, y2)], c1, 4.5 if prof > 2 else 2))
        for da in (-r.uniform(0.3, 0.6), r.uniform(0.3, 0.6)):
            ramas(x2, y2, largo * r.uniform(0.66, 0.8), ang + da, prof - 1)

    ramas(r.uniform(80, 320), 176, 44, -math.pi / 2, 5)
    x = r.uniform(380, 520)
    while x < W - 40:
        if r.random() < 0.75:
            h = r.uniform(34, 58)
            figs.append(("rect", x - 11, 176 - h, 22, h, c1))
            figs.append(("circle", x, 176 - h, 11, c1))
        else:
            h = r.uniform(44, 66)
            figs.append(("rect", x - 3, 176 - h, 6, h, c1))
            figs.append(("rect", x - 12, 176 - h * 0.78, 24, 6, c1))
        x += r.uniform(55, 110)
    return figs + gruesas + finas


# ------------------------------------------------------------------- Barroco

def ornamento(r, c1, c2, fondo):
    figs, anillos, puntos = [], [], []
    n = r.randint(5, 7)
    medallones = [((k + 0.5) * W / n + r.uniform(-25, 25), r.uniform(70, 130), r.uniform(30, 52)) for k in range(n)]
    for (x1, y1, r1), (x2, y2, r2) in zip(medallones, medallones[1:]):
        figs.append(("line", [(x1 + r1 + (x2 - r2 - x1 - r1) * t / 10,
                               y1 + (y2 - y1) * t / 10 + 16 * math.sin(math.pi * t / 10)) for t in range(11)], c2, 2.5))
    for cx, cy, radio in medallones:
        for k in range(r.randint(3, 4)):
            rad_k = radio - k * 8
            if rad_k < 5:
                break
            anillos.append(("ring", cx, cy, rad_k, c1 if k % 2 == 0 else c2, 3))
        puntos.append(("circle", cx, cy, 5, c1))
        for j in range(8):
            a = TAU * j / 8
            puntos.append(("circle", cx + (radio + 9) * math.cos(a), cy + (radio + 9) * math.sin(a), 2.4, c2))
    return figs + anillos + puntos


def volutas(r, c1, c2, fondo):
    figs, hojas, petalos, centros = [("line", [(0, 100), (W, 100)], c2, 2)], [], [], []
    paso = r.uniform(215, 250)
    x = paso / 2
    while x < W:
        for lado in (-1, 1):
            pts = espiral(x + lado * 52, 100, 44, 4, 2.6, math.pi if lado == 1 else 0.0, -lado, 36)
            figs.append(("line", pts, c1, 3))
            figs.append(("circle", pts[-1][0], pts[-1][1], 3.5, c1))
            hojas.append(hoja(x, 84, 34, 12, -math.pi / 2 + lado * 0.9, c2))
        p, c = flor(x, 62, 15, 5, c1, fondo)
        petalos += p
        centros.append(c)
        x += paso
    return figs + hojas + petalos + centros


def drapeado(r, c1, c2, fondo):
    figs, pliegues, borlas = [], [], []
    paso = r.uniform(120, 170)
    x0 = -r.uniform(0, paso)
    fondo_swag = r.uniform(42, 60)
    x = x0
    while x < W:
        arriba = [(x + paso * t / 12, 0) for t in range(13)]
        abajo = [(x + paso * t / 12, fondo_swag * math.sin(math.pi * t / 12)) for t in range(12, -1, -1)]
        figs.append(("poly", arriba + abajo, c1))
        for j in range(1, 7):
            u = j / 7
            yb = fondo_swag * math.sin(math.pi * u)
            xs = x + paso * u
            pliegues.append(("line", [(xs, yb), (xs + 5 * math.sin(u * 9), (yb + H) / 2), (xs - 3, H + 4)], c2, 3))
        borlas.append(("circle", x, 7, 6, c2))
        x += paso
    return figs + pliegues + borlas


def perlas(r, c1, c2, fondo):
    pequenas, grandes, borlas = [], [], []
    paso = r.uniform(150, 210)
    x0 = -r.uniform(0, paso)
    for cadena, (base, caida) in enumerate(((36, 40), (96, 46))):
        x = x0
        while x < W:
            for t in range(0, int(paso), 20):
                u = t / paso
                px, py = x + t, base + caida * math.sin(math.pi * u)
                (grandes if t % 80 == 0 else pequenas).append(("circle", px, py, 7 if t % 80 == 0 else 4.5,
                                                               c2 if t % 80 == 0 else c1))
            borlas.append(("poly", [(x, base + 4), (x - 5, base + 26), (x, base + 40), (x + 5, base + 26)], c2))
            x += paso
    return pequenas + grandes + borlas


# ----------------------------------------------------------------- Clasicismo

def columnas(r, c1, c2, fondo):
    figs, fustes, bordes = [], [], []
    espacio = r.uniform(150, 190)
    base = H - 14
    x = r.uniform(10, 60)
    while x + 44 < W:
        fustes.append(("rect", x + 8, 52, 28, base - 52 - 12, c2))
        bordes.append(("rect", x, 42, 44, 12, c1))
        bordes.append(("rect", x, base - 12, 44, 12, c1))
        x += espacio
    return fustes + bordes + [("rect", 0, 28, W, 8, c1), ("rect", 0, H - 6, W, 6, c1)]


def fronton(r, c1, c2, fondo):
    figs = []
    tw = r.uniform(380, 520)
    cx = r.uniform(tw / 2 + 40, W - tw / 2 - 40)
    x0 = cx - tw / 2
    if W - (cx + tw / 2) > cx - tw / 2:
        sol = r.uniform(cx + tw / 2 + 60, W - 40)
    else:
        sol = r.uniform(40, cx - tw / 2 - 60)
    figs.append(("circle", sol, r.uniform(50, 90), r.uniform(26, 38), c2))
    figs.append(("rect", x0 - 24, H - 9, tw + 48, 9, c1))
    figs.append(("rect", x0 - 12, H - 18, tw + 24, 9, c1))
    n = r.choice([6, 7, 8])
    for k in range(n):
        figs.append(("rect", x0 + k * (tw - 14) / (n - 1), 58, 14, H - 18 - 58, c1))
    figs.append(("rect", x0 - 8, 46, tw + 16, 12, c1))
    figs.append(("poly", [(x0 - 14, 46), (cx, 6), (x0 + tw + 14, 46)], c1))
    figs.append(("poly", [(x0 + 20, 41), (cx, 16), (x0 + tw - 20, 41)], fondo))
    return figs


def laurel(r, c1, c2, fondo):
    fase, lam, amp = r.uniform(0, TAU), r.uniform(380, 560), r.uniform(24, 40)

    def eje(x):
        return 100 + amp * math.sin(TAU * x / lam + fase)

    figs = [("line", [(x, eje(x)) for x in range(-10, W + 11, 14)], c1, 3)]
    h1, h2 = [], []
    x = 14.0
    while x < W:
        y = eje(x)
        pend = math.atan2(eje(x + 1) - eje(x - 1), 2)
        h1.append(hoja(x, y, r.uniform(28, 36), r.uniform(11, 14), pend - 0.75, c1))
        h2.append(hoja(x, y, r.uniform(28, 36), r.uniform(11, 14), pend + 0.75, c2))
        x += r.uniform(34, 42)
    return figs + h1 + h2


def meandro(r, c1, c2, fondo):
    a = 10
    figs = []
    for fila, color in enumerate((c1, c2, c1)):
        y0 = 12 + fila * (5 * a + 12)
        figs.append(("line", [(-10, y0 + 5 * a), (W + 10, y0 + 5 * a)], color, 4))
        x = r.uniform(-20, 0)
        while x < W:
            gancho = ((0, 5), (0, 0), (5, 0), (5, 4), (2, 4), (2, 2), (3, 2))
            figs.append(("line", [(x + gx * a, y0 + gy * a) for gx, gy in gancho], color, 4))
            x += 6 * a
    return figs


# ---------------------------------------------------------------- Simbolismo

def luna(r, c1, c2, fondo):
    cx, cy, rad = r.uniform(180, W - 180), r.uniform(60, 110), r.uniform(42, 58)
    figs = [("circle", cx, cy, rad, c1), ("circle", cx + rad * 0.45, cy - rad * 0.2, rad * 0.9, fondo)]
    for _ in range(r.randint(14, 22)):
        x, y = r.uniform(0, W), r.uniform(0, H * 0.9)
        if abs(x - cx) < rad * 2 and abs(y - cy) < rad * 2:
            continue
        figs.append(estrella(x, y, r.uniform(3, 7), c2))
    for k in range(3):
        x = r.uniform(0, W - 400)
        figs.append(("line", [(x, 160 + k * 12), (x + r.uniform(200, 500), 160 + k * 12)], c2, 3))
    return figs


def reflejo_lunar(r, c1, c2, fondo):
    cx, cy, R = r.uniform(300, 1100), 52, r.uniform(26, 36)
    figs = [("circle", cx, cy, R, c1)]
    for _ in range(r.randint(16, 24)):
        figs.append(estrella(r.uniform(0, W), r.uniform(8, 88), r.uniform(2.5, 5), c2))
    figs.append(("rect", 0, 100, W, 3, c1))
    x = 0.0
    while x < W:
        ancho = r.uniform(60, 160)
        figs.append(("poly", [(x, 100), (x + ancho * 0.5, 100 - r.uniform(6, 16)), (x + ancho, 100)], c1))
        x += ancho
    for j in range(8):
        ancho = 2 * R * (1 - j / 10) * r.uniform(0.8, 1.1)
        figs.append(("rect", cx - ancho / 2 + r.uniform(-6, 6), 112 + j * 10, ancho, 4, c1))
    return figs


def constelaciones(r, c1, c2, fondo):
    lineas, estrellas = [], []
    grupos = r.randint(4, 6)
    for g in range(grupos):
        cx, cy = (g + 0.5) * W / grupos, r.uniform(70, 130)
        pts = sorted((cx + r.uniform(-90, 90), cy + r.uniform(-60, 60)) for _ in range(r.randint(4, 7)))
        lineas.append(("line", pts, c2, 1.5))
        for x, y in pts:
            estrellas.append(estrella(x, y, r.uniform(5, 10), c1))
    polvo = [("circle", r.uniform(0, W), r.uniform(0, H), 1.3, c2) for _ in range(r.randint(30, 45))]
    return lineas + polvo + estrellas


def lirio(r, c1, c2, fondo):
    figs = [("circle", r.uniform(300, 1100), 100, r.uniform(55, 75), c2)]
    tallos, hojas, p1, p2 = [], [], [], []
    n = r.randint(5, 8)
    for k in range(n):
        x = (k + r.uniform(0.2, 0.8)) * W / n
        top = (x + r.uniform(-30, 30), r.uniform(50, 100))
        medio = (x + (top[0] - x) * 0.2 + r.uniform(-14, 14), (H + top[1]) / 2)
        tallos.append(("line", [(x, H + 4), medio, top], c1, 3))
        hojas.append(hoja(x, H - 10, r.uniform(55, 80), 13, -math.pi / 2 + r.choice([-1, 1]) * r.uniform(0.3, 0.7), c1))
        for da in (-0.6, 0, 0.6):
            p1.append(hoja(top[0], top[1], r.uniform(36, 46), 14, -math.pi / 2 + da, c1))
        for da in (-1.1, 1.1):
            p2.append(hoja(top[0], top[1], 28, 11, -math.pi / 2 + da, c2))
    return figs + tallos + hojas + p2 + p1


# ----------------------------------------------------------- Realismo mágico

def espiral_magica(r, c1, c2, fondo):
    figs = [("line", [(x, H - 22 + 7 * math.sin(x / 90)) for x in range(-10, W + 11, 14)], c2, 2.5)]
    n = r.randint(3, 4)
    for k in range(n):
        cx, cy = (k + r.uniform(0.3, 0.7)) * W / n, r.uniform(85, 112)
        figs.append(("line", espiral(cx, cy, 2, r.uniform(48, 72), r.uniform(3.2, 4.2), r.uniform(0, TAU),
                                     r.choice([-1, 1]), 48), c1 if k % 2 == 0 else c2, 2.5))
    for _ in range(r.randint(6, 10)):
        figs.append(("circle", r.uniform(0, W), r.uniform(10, 170), r.uniform(2, 3.5), c2))
    return figs


def mariposas(r, c1, c2, fondo):
    fase, lam = r.uniform(0, TAU), r.uniform(500, 800)

    def senda(x):
        return 100 + 45 * math.sin(TAU * x / lam + fase)

    figs = [("circle", x, senda(x), 1.6, c2) for x in range(0, W, 24)]
    n = r.randint(8, 11)
    for k in range(n):
        x = (k + 0.5) * W / n
        figs += mariposa(x, senda(x) + r.uniform(-28, 28), r.uniform(11, 26), r.uniform(-0.9, 0.9),
                         c2 if r.random() < 0.6 else c1, c1)
    return figs


def flores_cayendo(r, c1, c2, fondo):
    p1, p2, centros = [], [], []
    for _ in range(r.randint(26, 32)):
        color = c1 if r.random() < 0.55 else c2
        p, c = flor(r.uniform(10, W - 10), r.uniform(10, H - 10), r.uniform(8, 17), 5, color, fondo, r.uniform(0, TAU))
        (p1 if color == c1 else p2).extend(p)
        centros.append(c)
    return p2 + p1 + centros


def selva(r, c1, c2, fondo):
    figs = [("circle", r.uniform(300, 1100), r.uniform(60, 100), r.uniform(40, 60), c2)]
    nervios, h1, h2 = [], [], []
    for k in range(r.randint(6, 9)):
        bx = (k + r.uniform(0.2, 0.8)) * W / 8
        by = H + 8
        ang = -math.pi / 2 + r.uniform(-0.85, 0.85)
        largo = r.uniform(110, 175)
        nervios.append(("line", [(bx, by), (bx + largo * math.cos(ang), by + largo * math.sin(ang))], c1, 2.5))
        for j in range(1, 6):
            t = 0.2 + 0.78 * j / 5
            px, py = bx + largo * t * math.cos(ang), by + largo * t * math.sin(ang)
            ll = 34 * (1 - 0.55 * t) + 8
            h1.append(hoja(px, py, ll, 7, ang - 1.0, c1))
            h2.append(hoja(px, py, ll, 7, ang + 1.0, c2))
    return figs + nervios + h1 + h2


MOTIVOS = {
    "amanecer": amanecer, "arbol_solitario": arbol_solitario, "nubes": nubes, "acantilado_mar": acantilado_mar,
    "ciudad": ciudad, "puente": puente, "farolas": farolas, "chimeneas": chimeneas,
    "bosque": bosque, "juncos": juncos, "trigo": trigo, "rio": rio,
    "ondas": ondas, "ondas_concentricas": ondas_concentricas, "nouveau": nouveau, "mosaico": mosaico,
    "geometria": geometria, "constructivista": constructivista, "caligrama": caligrama, "collage": collage,
    "arcos_ojivales": arcos_ojivales, "roseton": roseton, "torres": torres, "cementerio": cementerio,
    "ornamento": ornamento, "volutas": volutas, "drapeado": drapeado, "perlas": perlas,
    "columnas": columnas, "fronton": fronton, "laurel": laurel, "meandro": meandro,
    "luna": luna, "reflejo_lunar": reflejo_lunar, "constelaciones": constelaciones, "lirio": lirio,
    "espiral": espiral_magica, "mariposas": mariposas, "flores_cayendo": flores_cayendo, "selva": selva,
}
