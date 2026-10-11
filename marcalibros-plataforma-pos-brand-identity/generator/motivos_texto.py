"""Motivos visuales de los tipos de texto (lienzo de 1400 x 200).

Usa las mismas reglas que `motivos.py`: cada motivo recibe un generador aleatorio
determinista `r` y los colores `c1` (principal, con buen contraste sobre el
fondo), `c2` (secundario) y `fondo`, y devuelve figuras en el mismo formato.

Ningún motivo incluye texto legible ni símbolos de una religión, institución o
marca concretas: los libros sagrados, por ejemplo, se representan con luz,
geometría y rollos, que sirven para cualquier tradición.
"""
import math

from motivos import (H, TAU, W, _colocar, colinas, elipse, espiral, estrella, flor, hoja,
                     rect_girado)


# ---------------------------------------------------------------- utilidades

def _cerrada(pts):
    return list(pts) + [pts[0]]


def _elipse_linea(cx, cy, rx, ry, ang=0.0, n=24):
    """Contorno de una elipse como línea cerrada."""
    pts = [(rx * math.cos(TAU * k / n), ry * math.sin(TAU * k / n)) for k in range(n)]
    return _cerrada(_colocar(pts, cx, cy, ang))


def _rect_pts(x, y, w, h):
    return [(x, y), (x + w, y), (x + w, y + h), (x, y + h)]


def _arco(cx, cy, rad, a0, a1, n=8):
    return [(cx + rad * math.cos(a0 + (a1 - a0) * k / n), cy + rad * math.sin(a0 + (a1 - a0) * k / n))
            for k in range(n + 1)]


def _puntos_repartidos(r, n, margen=40):
    """n posiciones x repartidas a lo ancho, con un poco de desorden."""
    paso = (W - 2 * margen) / n
    return [margen + (k + 0.5) * paso + r.uniform(-0.12, 0.12) * paso for k in range(n)], paso


# ------------------------------------------------------------------ Manuales

def _engranaje(cx, cy, rad, dientes, ang, color, fondo, diente):
    paso = TAU / dientes
    raiz = rad - diente
    pts = []
    for k in range(dientes):
        a = ang + k * paso
        for frac, rr in ((-0.5, raiz), (-0.17, rad), (0.17, rad)):
            pts.append((cx + rr * math.cos(a + frac * paso), cy + rr * math.sin(a + frac * paso)))
    return [("poly", pts, color), ("circle", cx, cy, raiz * 0.42, fondo), ("circle", cx, cy, raiz * 0.14, color)]


def engranajes(r, c1, c2, fondo):
    """Cadena de engranajes que encajan entre sí (mismo paso de diente)."""
    paso_diente = r.uniform(30, 36)
    profundidad = paso_diente * 0.3

    def dientes_de(rad):
        return max(7, round(TAU * rad / paso_diente))

    rad = r.uniform(52, 76)
    dientes = dientes_de(rad)
    rad = dientes * paso_diente / TAU
    x, y, ang = r.uniform(0, 40), r.uniform(80, 120), r.uniform(0, TAU)
    figs, k = [], 0
    while True:
        figs += _engranaje(x, y, rad, dientes, ang, c1 if k % 2 == 0 else c2, fondo, profundidad)
        if x - rad > W:
            break
        rad2 = r.uniform(50, 80)
        dientes2 = dientes_de(rad2)
        rad2 = dientes2 * paso_diente / TAU
        dist = rad + rad2 - profundidad * 0.9
        theta = r.uniform(-0.7, 0.7)
        if not 28 < y + dist * math.sin(theta) < 172:
            theta = -theta
        # El diente de un engranaje entra en el hueco del siguiente.
        fase1 = ((theta - ang) / (TAU / dientes)) % 1
        ang2 = theta + math.pi - (TAU / dientes2) * ((0.5 - fase1) % 1)
        x, y = x + dist * math.cos(theta), y + dist * math.sin(theta)
        rad, dientes, ang, k = rad2, dientes2, ang2, k + 1
    return figs


def plano_tecnico(r, c1, c2, fondo):
    """Cuadrícula de plano con vistas acotadas: círculos, piezas y líneas de cota."""
    figs = []
    paso = r.choice([25, 40, 50])
    for x in range(0, W + 1, paso):
        figs.append(("line", [(x, 0), (x, H)], c2, 1.2))
    for y in range(0, H + 1, paso):
        figs.append(("line", [(0, y), (W, y)], c2, 1.2))
    n = r.randint(3, 4)
    centros, ancho = _puntos_repartidos(r, n, 90)
    cotas, formas = [], []
    for cx in centros:
        s = r.uniform(34, 56)
        cy = 84 + r.uniform(-8, 8)
        tipo = r.choice(["circulo", "rect", "ele"])
        if tipo == "circulo":
            formas.append(("ring", cx, cy, s, c1, 2.5))
            formas.append(("ring", cx, cy, s * 0.5, c1, 2.5))
            formas.append(("line", [(cx - s - 12, cy), (cx + s + 12, cy)], c1, 1.5))
            formas.append(("line", [(cx, cy - s - 12), (cx, cy + s + 12)], c1, 1.5))
        elif tipo == "rect":
            formas.append(("line", _cerrada(_rect_pts(cx - s, cy - s * 0.7, 2 * s, 1.4 * s)), c1, 2.5))
            formas.append(("line", [(cx - s, cy - s * 0.7), (cx + s, cy + s * 0.7)], c1, 1.5))
            formas.append(("ring", cx, cy, s * 0.28, c1, 2.5))
        else:
            pts = [(cx - s, cy - s), (cx - s * 0.35, cy - s), (cx - s * 0.35, cy + s * 0.35),
                   (cx + s, cy + s * 0.35), (cx + s, cy + s), (cx - s, cy + s)]
            formas.append(("line", _cerrada(pts), c1, 2.5))
            formas.append(("ring", cx - s * 0.68, cy - s * 0.4, s * 0.14, c1, 2.5))
        y_cota = 168
        x0, x1 = cx - s, cx + s
        cotas.append(("line", [(x0, y_cota), (x1, y_cota)], c1, 1.8))
        cotas.append(("line", [(x0, y_cota - 10), (x0, y_cota + 10)], c1, 1.8))
        cotas.append(("line", [(x1, y_cota - 10), (x1, y_cota + 10)], c1, 1.8))
        cotas.append(("poly", [(x0, y_cota), (x0 + 10, y_cota - 3.5), (x0 + 10, y_cota + 3.5)], c1))
        cotas.append(("poly", [(x1, y_cota), (x1 - 10, y_cota - 3.5), (x1 - 10, y_cota + 3.5)], c1))
    return figs + formas + cotas


def regla(r, c1, c2, fondo):
    """Dos reglas graduadas y una barra de escala, como en un manual de medidas."""
    figs = []
    paso = r.choice([8, 10, 12])
    for i in range(W // paso + 1):
        largo = 36 if i % 10 == 0 else 24 if i % 5 == 0 else 13
        figs.append(("line", [(i * paso, 0), (i * paso, largo)], c1, 2))
    paso2 = r.choice([14, 16, 20])
    for i in range(W // paso2 + 1):
        largo = 30 if i % 4 == 0 else 15
        figs.append(("line", [(i * paso2, H), (i * paso2, H - largo)], c2, 2))
    # barra de escala: bloques alternados
    seg = r.choice([50, 70, 100])
    y, alto = 92, 14
    x = r.uniform(20, 80)
    k = 0
    while x + seg < W - 20:
        if k % 2 == 0:
            figs.append(("rect", x, y, seg, alto, c1))
        else:
            figs.append(("line", _cerrada(_rect_pts(x, y, seg, alto)), c1, 2))
        x += seg
        k += 1
    for j in range(r.randint(4, 6)):
        cx = r.uniform(60, W - 60)
        figs.append(("poly", [(cx - 8, 66), (cx + 8, 66), (cx, 82)], c2))
    return figs


def circuito(r, c1, c2, fondo):
    """Pistas de un circuito con giros a 45 grados, nodos y microchips."""
    pistas, nodos, chips, patas = [], [], [], []
    filas = 7
    for k in range(filas):
        y = 16 + 28 * k
        x, dy = r.uniform(-10, 50), 0
        pts = [(x, y)]
        nodos.append(("ring", x + 4, y, 5, c1, 2.2))
        while x < W + 5:
            x += r.uniform(70, 200)
            pts.append((x, y + dy))
            if r.random() < 0.65:
                nuevo = r.choice([-10, 0, 10])
                if nuevo != dy:
                    x += abs(nuevo - dy)
                    pts.append((x, y + nuevo))
                    dy = nuevo
                    if r.random() < 0.5:
                        nodos.append(("circle", x, y + nuevo, 3.5, c1))
        pistas.append(("line", pts, c1, 2.5))
    puestos = []
    for _ in range(r.randint(3, 4)):
        w, h = r.uniform(80, 120), r.uniform(50, 76)
        for _intento in range(30):
            x, y = r.uniform(60, W - 60 - w), r.uniform(20, H - 20 - h)
            if all(x > px + pw + 30 or px > x + w + 30 for px, pw in puestos):
                break
        else:
            continue
        puestos.append((x, w))
        chips.append(("rect", x, y, w, h, c2))
        chips.append(("circle", x + 9, y + 9, 3.5, fondo))
        for px in range(int(x + 14), int(x + w - 8), 15):
            patas.append(("line", [(px, y - 7), (px, y)], c1, 2.5))
            patas.append(("line", [(px, y + h), (px, y + h + 7)], c1, 2.5))
    return pistas + nodos + chips + patas


# ------------------------------------------------------------ Libros sagrados

def rayos_de_luz(r, c1, c2, fondo):
    """Rayos que salen de detrás de una colina, con halos alrededor del sol."""
    cx, cy = r.uniform(350, 1050), r.uniform(110, 140)
    n = r.randint(12, 18)
    giro = r.uniform(0, 1)
    figs = []
    for k in range(n):
        a = math.pi + (k + giro) * math.pi / n
        w = math.pi / n * 0.26
        figs.append(("poly", [(cx, cy), (cx + 2400 * math.cos(a - w), cy + 2400 * math.sin(a - w)),
                              (cx + 2400 * math.cos(a + w), cy + 2400 * math.sin(a + w))], c2))
    figs += [("ring", cx, cy, 64, c1, 1.6), ("ring", cx, cy, 46, c1, 2), ("circle", cx, cy, 30, c1)]
    figs.append(colinas(r, 168, 12, c1))
    return figs


def _mandala(cx, cy, rad, c1, c2, fondo, fase):
    s = rad * 1.02
    figs = [("ring", cx, cy, rad, c1, 3), ("ring", cx, cy, rad * 0.8, c2, 2)]
    for giro in (0, math.pi / 4):
        figs.append(rect_girado(cx, cy, s, s, fase + giro, c1))
    for giro in (0, math.pi / 4):
        figs.append(rect_girado(cx, cy, s * 0.62, s * 0.62, fase + giro, fondo))
    figs.append(estrella(cx, cy, rad * 0.4, c2, 8, 0.5))
    figs.append(("circle", cx, cy, rad * 0.1, c1))
    for k in range(10):
        a = TAU * k / 10 + fase
        figs.append(("circle", cx + rad * 0.9 * math.cos(a), cy + rad * 0.9 * math.sin(a), 2.6, c1))
    return figs


def mandala(r, c1, c2, fondo):
    """Mandalas de ocho puntas unidos por un hilo: geometría sin símbolos de ninguna religión."""
    n = r.randint(3, 5)
    centros, paso = _puntos_repartidos(r, n, 90)
    cy = 100
    figs = [("line", [(0, cy), (W, cy)], c2, 2)]
    for k in range(n + 1):
        x = k * (W / n)
        figs.append(rect_girado(x, cy, 12, 12, math.pi / 4, c2))
    for cx in centros:
        figs += _mandala(cx, cy, min(88, paso * 0.4) * r.uniform(0.85, 1.0), c1, c2, fondo, r.uniform(0, math.pi / 4))
    return figs


def rollos(r, c1, c2, fondo):
    """Hileras de rollos de pergamino con líneas de escritura."""
    n = r.randint(6, 8)
    slot = W / n
    cuerpos, rodillos, lineas = [], [], []
    for k in range(n):
        w = r.uniform(0.55, 0.76) * slot
        h = r.uniform(70, 130)
        cx = (k + 0.5) * slot + r.uniform(-0.08, 0.08) * slot
        x0, y = cx - w / 2, r.uniform(16, H - 16 - h)
        cuerpos.append(("rect", x0, y, w, h, c1))
        for xr in (x0, x0 + w):
            rodillos.append(("rect", xr - 7, y - 8, 14, h + 16, c2))
            rodillos.append(("circle", xr, y - 8, 7, c2))
            rodillos.append(("circle", xr, y + h + 8, 7, c2))
        for j in range(int((h - 14) // 13)):
            largo = (w - 36) * r.uniform(0.45, 1.0)
            lineas.append(("line", [(x0 + 18, y + 12 + j * 13), (x0 + 18 + largo, y + 12 + j * 13)], fondo, 3))
    return cuerpos + rodillos + lineas


def velas(r, c1, c2, fondo):
    """Velas encendidas de distintas alturas, con halo de luz."""
    cuerpos, llamas, halos = [], [], []
    x = r.uniform(10, 40)
    while x < W - 30:
        w, alto = r.uniform(12, 22), r.uniform(40, 115)
        cx, yl = x + w / 2, H - alto
        cuerpos.append(("rect", x, yl, w, alto + 1, c1))
        llamas.append(hoja(cx, yl - 4, r.uniform(22, 32), r.uniform(9, 13), -math.pi / 2, c2, 2))
        if r.random() < 0.55:
            halos.append(("ring", cx, yl - 19, r.uniform(20, 28), c2, 1.6))
        x += w + r.uniform(16, 50)
    mechas = [("line", [(g[1] + g[3] / 2, g[2] - 6), (g[1] + g[3] / 2, g[2])], c1, 2) for g in cuerpos]
    return cuerpos + mechas + llamas + halos


# ------------------------------------------------------------------- Leyes

def _balanza(cx, s, incl, c1, c2):
    figs = []
    ycentro = 42
    largo = 92 * s
    ex, ey = largo * math.cos(incl), largo * math.sin(incl)
    figs.append(("line", [(cx, ycentro), (cx, 180)], c1, 5))
    figs.append(("poly", [(cx - 44 * s, 192), (cx + 44 * s, 192), (cx + 24 * s, 176), (cx - 24 * s, 176)], c1))
    figs.append(("line", [(cx - ex, ycentro - ey), (cx + ex, ycentro + ey)], c1, 5))
    figs.append(("circle", cx, ycentro - 7 * s, 7 * s, c1))
    for lado, (bx, by) in ((-1, (cx - ex, ycentro - ey)), (1, (cx + ex, ycentro + ey))):
        py = by + 64 * s
        figs.append(("line", [(bx - 34 * s, py), (bx, by), (bx + 34 * s, py)], c2, 1.8))
        figs.append(("poly", _arco(bx, py, 36 * s, 0, math.pi, 8), c1))
        figs.append(("circle", bx, by, 4.5 * s, c1))
    return figs


def balanza(r, c1, c2, fondo):
    """Balanzas de la justicia, cada una con una inclinación distinta."""
    n = r.choice([3, 3, 4])
    centros, _ = _puntos_repartidos(r, n, 150)
    figs = []
    for cx in centros:
        figs += _balanza(cx, r.uniform(0.82, 0.98), r.uniform(-0.2, 0.2), c1, c2)
    return figs


def sellos(r, c1, c2, fondo):
    """Sellos oficiales de borde dentado con su cinta, sin ningún emblema real."""
    n = r.randint(6, 8)
    centros, paso = _puntos_repartidos(r, n, 50)
    cintas, cuerpos, detalles = [], [], []
    for cx in centros:
        rad = min(r.uniform(34, 46), paso * 0.42)
        cy = r.uniform(78, 98)
        for lado in (-1, 1):
            cintas.append(("poly", [(cx + lado * rad * 0.15, cy + rad * 0.6), (cx + lado * rad * 0.75, cy + rad * 0.75),
                                    (cx + lado * rad * 0.95, cy + rad * 1.75), (cx + lado * rad * 0.5, cy + rad * 1.45),
                                    (cx + lado * rad * 0.1, cy + rad * 1.75)], c2))
        pts = [(cx + rad * (1 if k % 2 else 0.92) * math.cos(TAU * k / 22), cy + rad * (1 if k % 2 else 0.92) * math.sin(TAU * k / 22))
               for k in range(22)]
        cuerpos.append(("poly", pts, c1))
        detalles.append(("ring", cx, cy, rad * 0.7, fondo, 2.4))
        detalles.append(estrella(cx, cy, rad * 0.46, fondo, 5, 0.45))
    return cintas + cuerpos + detalles


def articulos(r, c1, c2, fondo):
    """Columnas de artículos numerados: encabezado, viñetas y párrafos."""
    n = r.randint(7, 9)
    ancho = W / n
    figs, lineas, separadores = [], [], []
    for k in range(n):
        x0, w = k * ancho + 16, ancho - 32
        figs.append(("rect", x0, 16, w * r.uniform(0.35, 0.6), 9, c1))
        y = 42
        while y < H - 22:
            figs.append(("circle", x0 + 4, y + 1, 3.6, c1))
            for _ in range(r.randint(1, 3)):
                if y > H - 16:
                    break
                lineas.append(("line", [(x0 + 16, y), (x0 + 16 + (w - 16) * r.uniform(0.55, 1.0), y)], c2, 3))
                y += 12
            y += 8
        if k:
            separadores.append(("line", [(k * ancho, 10), (k * ancho, H - 10)], c2, 1.4))
    return figs + lineas + separadores


def mazo(r, c1, c2, fondo):
    """Mazos de juez a punto de golpear su base; arcos de sonido marcan el golpe."""
    n = r.randint(4, 6)
    centros, paso = _puntos_repartidos(r, n, 90)
    bases, mazos, sonido = [], [], []
    for cx in centros:
        s = r.uniform(1.0, 1.25)
        bases.append(("rect", cx - 34 * s, 166, 68 * s, 14, c1))
        bases.append(("rect", cx - 46 * s, 180, 92 * s, 10, c2))
        ang = r.choice([-1, 1]) * r.uniform(0.55, 1.1)
        hx, hy = cx + r.uniform(-8, 8), 62 + r.uniform(-4, 8)

        def loc(pts):
            return _colocar([(x * s, y * s) for x, y in pts], hx, hy, ang)
        mazos.append(("poly", loc(_rect_pts(-27, -15, 54, 30)), c1))
        mazos.append(("poly", loc(_rect_pts(-34, -18, 8, 36)), c2))
        mazos.append(("poly", loc(_rect_pts(26, -18, 8, 36)), c2))
        mazos.append(("poly", loc(_rect_pts(-4.5, 15, 9, 70)), c1))
        gx, gy = loc([(0, 88)])[0]
        mazos.append(("circle", gx, gy, 8 * s, c2))
        for k, rad in enumerate((16, 27)):
            sonido.append(("line", _arco(cx, 164, rad * s, math.pi * 1.12, math.pi * 1.88, 6), c2, 2.2 - 0.4 * k))
    return bases + mazos + sonido


# ------------------------------------------------------------------ Cartas

def sobres(r, c1, c2, fondo):
    """Sobres ligeramente girados: unos de frente, con estampilla y dirección; otros por detrás, con solapa y lacre."""
    n = r.randint(6, 7)
    slot = W / n
    cuerpos, contornos, detalles, adornos = [], [], [], []
    for k in range(n):
        cx, cy = (k + 0.5) * slot + r.uniform(-4, 4), r.uniform(92, 108)
        w = r.uniform(0.66, 0.8) * slot
        h = w * r.uniform(0.62, 0.7)
        ang = r.uniform(-0.28, 0.28)
        cuerpos.append(("poly", _colocar(_rect_pts(-w / 2, -h / 2, w, h), cx, cy, ang), c1))
        contornos.append(("line", _colocar(_cerrada(_rect_pts(-w / 2, -h / 2, w, h)), cx, cy, ang), fondo, 2.4))
        if r.random() < 0.5:
            adornos.append(("poly", _colocar(_rect_pts(w / 2 - 32, -h / 2 + 8, 22, 26), cx, cy, ang), c2))
            for j in range(3):
                detalles.append(("line", _colocar([(-w / 2 + 14, h / 2 - 34 + j * 11), (-w / 2 + 14 + (w * 0.42) * (1 - 0.18 * j), h / 2 - 34 + j * 11)],
                                                  cx, cy, ang), fondo, 3))
        else:
            detalles.append(("line", _colocar([(-w / 2, -h / 2), (0, h * 0.08), (w / 2, -h / 2)], cx, cy, ang), fondo, 2.4))
            gx, gy = _colocar([(0, h * 0.08)], cx, cy, ang)[0]
            adornos.append(("circle", gx, gy, 9, c2))
    return cuerpos + contornos + detalles + adornos


def estampillas(r, c1, c2, fondo):
    """Estampillas de bordes perforados con imágenes sencillas."""
    n = r.randint(5, 7)
    slot = W / n
    cuerpos, perforaciones, marcos, imagenes = [], [], [], []
    for k in range(n):
        w, h = r.uniform(0.62, 0.8) * slot, r.uniform(100, 156)
        x, y = (k + 0.5) * slot - w / 2, r.uniform(10, H - 10 - h)
        cuerpos.append(("rect", x, y, w, h, c1))
        for px in range(int(x + 8), int(x + w - 4), 16):
            perforaciones += [("rect", px - 3, y - 3, 6, 6, fondo), ("rect", px - 3, y + h - 3, 6, 6, fondo)]
        for py in range(int(y + 8), int(y + h - 4), 16):
            perforaciones += [("rect", x - 3, py - 3, 6, 6, fondo), ("rect", x + w - 3, py - 3, 6, 6, fondo)]
        marcos.append(("line", _cerrada(_rect_pts(x + 10, y + 10, w - 20, h - 20)), fondo, 2))
        marcos.append(("line", [(x + 18, y + h - 22), (x + w - 18, y + h - 22)], fondo, 3))
        cx, cy = x + w / 2, y + 10 + (h - 40) / 2 + 4
        tipo = r.randint(0, 2)
        if tipo == 0:
            imagenes.append(("circle", cx - w * 0.12, cy - h * 0.1, w * 0.12, c2))
            imagenes.append(("poly", [(x + 14, y + h - 30), (cx - 4, cy), (cx + w * 0.2, y + h - 30)], c2))
        elif tipo == 1:
            imagenes.append(estrella(cx, cy, min(w, h) * 0.26, c2, 5, 0.45))
        else:
            for j in range(3):
                imagenes.append(("rect", cx - w * 0.24 + j * w * 0.17, cy + h * 0.1 - (j + 1) * h * 0.07, w * 0.12, (j + 1) * h * 0.14, c2))
    return cuerpos + perforaciones + marcos + imagenes


def lacre(r, c1, c2, fondo):
    """Sellos de lacre irregulares sobre una cinta ondulada, con gotas de cera."""
    y0, amp = r.uniform(88, 112), r.uniform(10, 24)
    lam, fase = r.uniform(500, 900), r.uniform(0, TAU)

    def cinta(x):
        return y0 + amp * math.sin(TAU * x / lam + fase)

    xs = list(range(-10, W + 30, 30))
    figs = [("poly", [(x, cinta(x) - 11) for x in xs] + [(x, cinta(x) + 11) for x in reversed(xs)], c2)]
    n = r.randint(4, 6)
    centros, paso = _puntos_repartidos(r, n, 70)
    for cx in centros:
        rad = min(r.uniform(30, 48), paso * 0.4)
        cy = cinta(cx)
        pts = [(cx + rad * (1 + r.uniform(-0.15, 0.15)) * math.cos(TAU * k / 16),
                cy + rad * (1 + r.uniform(-0.15, 0.15)) * math.sin(TAU * k / 16)) for k in range(16)]
        figs.append(("poly", pts, c1))
        if r.random() < 0.7:
            gx = cx + r.uniform(-0.5, 0.5) * rad
            figs.append(("line", [(gx, cy + rad * 0.8), (gx, cy + rad * 0.8 + r.uniform(14, 30))], c1, 5))
            figs.append(("circle", gx, cy + rad * 0.8 + r.uniform(30, 40), r.uniform(4, 6), c1))
        figs.append(("ring", cx, cy, rad * 0.62, fondo, 2.4))
        figs.append(hoja(cx, cy + rad * 0.3, rad * 0.55, rad * 0.3, -math.pi / 2 - 0.5, fondo, 2))
        figs.append(hoja(cx, cy + rad * 0.3, rad * 0.55, rad * 0.3, -math.pi / 2 + 0.5, fondo, 2))
    return figs


def plumas(r, c1, c2, fondo):
    """Plumas de escribir que dejan líneas de letra cursiva."""
    figs, plumas_ = [], []
    for y0 in (104 + r.uniform(-6, 6), 168 + r.uniform(-4, 4)):
        a, b = r.uniform(6.5, 8), r.uniform(13, 16)
        x0 = r.uniform(20, 90)
        fin = r.uniform(0.4, 0.8) * W
        pts, t = [], 0.0
        while x0 + a * t - b * math.sin(t) < fin:
            pts.append((x0 + a * t - b * math.sin(t), y0 + 11 * math.cos(t)))
            t += 0.55
        figs.append(("line", pts, c2, 2.4))
        xe, ye = pts[-1]
        largo = r.uniform(120, 160)
        ang = -r.uniform(0.45, 0.6)
        plumas_.append(hoja(xe, ye, largo, largo * 0.2, ang, c1, 4))
        plumas_.append(("line", [(xe, ye), (xe + largo * 0.97 * math.cos(ang), ye + largo * 0.97 * math.sin(ang))], fondo, 1.8))
    return figs + plumas_


# ------------------------------------------------------- Diarios y memorias

def calendario(r, c1, c2, fondo):
    """Cuadrícula de días con algunos marcados: círculos, cruces y puntos."""
    celda = 40
    figs = []
    for x in range(0, W + 1, celda):
        figs.append(("line", [(x, 0), (x, H)], c2, 1.5))
    for y in range(0, H + 1, celda):
        figs.append(("line", [(0, y), (W, y)], c2, 1.5))
    llenos, anillos, cruces, puntos = [], [], [], []
    for i in range(W // celda):
        for j in range(H // celda):
            cx, cy = i * celda + celda / 2, j * celda + celda / 2
            v = r.random()
            if v < 0.2:
                llenos.append(("circle", cx, cy, 11, c1))
            elif v < 0.3:
                anillos.append(("ring", cx, cy, 13, c1, 2.5))
            elif v < 0.4:
                cruces.append(("line", [(cx - 8, cy - 8), (cx + 8, cy + 8)], c1, 3))
                cruces.append(("line", [(cx - 8, cy + 8), (cx + 8, cy - 8)], c1, 3))
            elif v < 0.55:
                puntos.append(("circle", cx, cy, 2.8, c2))
    return figs + llenos + anillos + cruces + puntos


def _luna_fase(cx, cy, rad, fase, c1):
    """Luna en la fase `fase` (0 nueva, 0.5 llena, 1 nueva otra vez)."""
    creciente = fase <= 0.5
    p = fase if creciente else 1 - fase
    k = math.cos(TAU * p)
    signo = 1 if creciente else -1
    borde = [(signo * rad * math.sin(t), -rad * math.cos(t)) for t in [math.pi * j / 10 for j in range(11)]]
    terminador = [(signo * k * rad * math.sin(t), -rad * math.cos(t)) for t in [math.pi - math.pi * j / 10 for j in range(11)]]
    pts = [(cx + x, cy + y) for x, y in borde + terminador]
    return [("ring", cx, cy, rad, c1, 2.2), ("poly", pts, c1)]


def fases_lunares(r, c1, c2, fondo):
    """Ciclo de fases de la luna, de izquierda a derecha, con estrellas pequeñas."""
    n = r.randint(9, 12)
    centros, paso = _puntos_repartidos(r, n, 50)
    rad = min(34, paso * 0.36)
    desfase = r.uniform(0, 1)
    figs, estrellas = [], []
    for k, cx in enumerate(centros):
        cy = 100 + 22 * math.sin(k * 0.9 + desfase * TAU)
        figs += _luna_fase(cx, cy, rad, (k / n + desfase) % 1, c1)
    for _ in range(r.randint(14, 20)):
        estrellas.append(estrella(r.uniform(10, W - 10), r.uniform(12, H - 12), r.uniform(3, 6), c2, 4, 0.3))
    return estrellas + figs


def ventana_lluvia(r, c1, c2, fondo):
    """Ventanas con marcos de madera y gotas de lluvia resbalando por el vidrio."""
    n = r.randint(3, 4)
    centros, paso = _puntos_repartidos(r, n, 20)
    marcos, travesanos, lluvia = [], [], []
    for cx in centros:
        w, h = min(r.uniform(150, 220), paso * 0.8), r.uniform(150, 176)
        x0, y0 = cx - w / 2, (H - h) / 2
        marcos.append(("line", _cerrada(_rect_pts(x0, y0, w, h)), c1, 6))
        travesanos.append(("line", [(cx, y0), (cx, y0 + h)], c1, 3.5))
        for f in (1 / 3, 2 / 3) if h > 160 else (0.5,):
            travesanos.append(("line", [(x0, y0 + h * f), (x0 + w, y0 + h * f)], c1, 3.5))
        for _ in range(r.randint(4, 6)):
            gx = r.uniform(x0 + 8, x0 + w - 8)
            gy = r.uniform(y0 + 12, y0 + h * 0.6)
            lluvia.append(("line", [(gx, gy), (gx + r.uniform(-3, 3), gy + r.uniform(18, 40))], c2, 2))
            lluvia.append(("circle", gx, gy, 3.4, c2))
    for _ in range(r.randint(50, 70)):
        x, y = r.uniform(0, W), r.uniform(0, H - 10)
        lluvia.append(("line", [(x, y), (x - 4, y + 13)], c2, 1.8))
    return marcos + travesanos + lluvia


def herbario(r, c1, c2, fondo):
    """Plantas prensadas, cada una con su etiqueta, como en un cuaderno de campo."""
    n = r.randint(4, 6)
    centros, paso = _puntos_repartidos(r, n, 50)
    tallos, hojas, flores, etiquetas = [], [], [], []
    for cx in centros:
        base = (cx + r.uniform(-14, 14), H - 34)
        alto = r.uniform(110, 150)
        ang = -math.pi / 2 + r.uniform(-0.18, 0.18)
        punta = (base[0] + alto * math.cos(ang), base[1] + alto * math.sin(ang))
        tallos.append(("line", [base, punta], c1, 2.5))
        for j in range(1, r.randint(5, 7)):
            t = j / 8 + 0.05
            px, py = base[0] + (punta[0] - base[0]) * t, base[1] + (punta[1] - base[1]) * t
            largo = 38 * (1 - 0.55 * t) + 8
            hojas.append(hoja(px, py, largo, largo * 0.4, ang - 0.9, c1, 2))
            hojas.append(hoja(px, py, largo, largo * 0.4, ang + 0.9, c2, 2))
        petalos, centro = flor(punta[0], punta[1] - 4, r.uniform(9, 13), 5, c2, c1, r.uniform(0, TAU))
        flores += petalos + [centro]
        etiquetas.append(("rect", cx - 18, H - 24, 36, 16, c2))
        etiquetas.append(("line", [(cx - 11, H - 16), (cx + 11, H - 16)], fondo, 2))
    return tallos + hojas + flores + etiquetas


# ------------------------------------------------------ Tratados científicos

def orbitas(r, c1, c2, fondo):
    """Sistema de órbitas elípticas alrededor de una estrella, con planetas."""
    cx, cy = r.uniform(500, 900), 100
    figs, planetas = [], []
    n = r.randint(5, 7)
    for k in range(n):
        rx = 90 + k * (640 - 90) / (n - 1) * r.uniform(0.9, 1.05)
        ry = min(92, 20 + k * 15 * r.uniform(0.9, 1.1))
        ang = r.uniform(-0.1, 0.1)
        figs.append(("line", _elipse_linea(cx, cy, rx, ry, ang, max(24, int(rx / 11))), c2 if k % 2 else c1, 1.8))
        t = r.uniform(0, TAU)
        px, py = _colocar([(rx * math.cos(t), ry * math.sin(t))], cx, cy, ang)[0]
        planetas.append(("circle", px, py, r.uniform(4, 9), c1 if k % 2 else c2))
    estrellas = [estrella(r.uniform(10, W - 10), r.uniform(10, H - 10), r.uniform(2.5, 5), c2, 4, 0.3)
                 for _ in range(r.randint(10, 16))]
    return estrellas + figs + [("circle", cx, cy, 17, c1), ("ring", cx, cy, 25, c1, 1.6)] + planetas


def hexagonos(r, c1, c2, fondo):
    """Red de hexágonos como una molécula: algunos contornos, algunos rellenos y átomos en los vértices."""
    rad = 30
    ancho, alto = math.sqrt(3) * rad, 1.5 * rad
    contornos, rellenos, atomos = [], [], []
    for j in range(-1, int(H / alto) + 2):
        for i in range(-1, int(W / ancho) + 2):
            cx = i * ancho + (ancho / 2 if j % 2 else 0)
            cy = j * alto + 10
            v = r.random()
            if v > 0.42:
                continue
            pts = [(cx + rad * math.cos(math.pi / 6 + TAU * k / 6), cy + rad * math.sin(math.pi / 6 + TAU * k / 6))
                   for k in range(6)]
            if v < 0.08:
                rellenos.append(("poly", pts, c2))
            else:
                contornos.append(("line", _cerrada(pts), c1, 2.4))
            if v < 0.16:
                px, py = pts[r.randrange(6)]
                atomos.append(("circle", px, py, 6, c1))
    return rellenos + contornos + atomos


def grafica_datos(r, c1, c2, fondo):
    """Barras, curva de tendencia con marcadores y ejes: un gráfico de resultados."""
    figs, curva, marcas = [], [], []
    base, izq, der = 168, 36, W - 30
    for y in (48, 88, 128):
        figs.append(("line", [(izq, y), (der, y)], c2, 1.2))
    f1, f2, f3 = r.uniform(0.004, 0.009), r.uniform(0.012, 0.02), r.uniform(0, TAU)
    barras = []
    x = izq + 12
    while x < der - 16:
        v = 0.55 + 0.3 * math.sin(f1 * x + f3) + 0.12 * math.sin(f2 * x) + r.uniform(-0.05, 0.05)
        alto = max(10, v * 110)
        barras.append(("rect", x, base - alto, 18, alto, c2))
        curva.append((x + 9, base - alto - 14 - 8 * math.sin(f1 * x * 1.7)))
        x += 32
    for i in range(len(curva)):
        if i % 2 == 0:
            marcas.append(("ring", curva[i][0], curva[i][1], 5, c1, 2.4))
    ejes = [("line", [(izq, 24), (izq, base), (der, base)], c1, 3)]
    return figs + barras + [("line", curva, c1, 2.8)] + marcas + ejes


def _dendrograma(r, ys, x0, xfin, figs, puntas, c1, c2):
    """Dibuja un árbol de ramas en ángulo recto; devuelve la y de su raíz."""
    if len(ys) == 1:
        figs.append(("line", [(x0, ys[0]), (xfin, ys[0])], c1, 2.2))
        puntas.append(("circle", xfin, ys[0], 4, c2))
        return ys[0]
    k = r.randint(1, len(ys) - 1)
    xn = x0 + (xfin - x0) * r.uniform(0.18, 0.42)
    ya = _dendrograma(r, ys[:k], xn, xfin, figs, puntas, c1, c2)
    yb = _dendrograma(r, ys[k:], xn, xfin, figs, puntas, c1, c2)
    y = (ya + yb) / 2
    figs.append(("line", [(x0, y), (xn, y)], c1, 2.2))
    figs.append(("line", [(xn, ya), (xn, yb)], c1, 2.2))
    return y


def arbol_filogenetico(r, c1, c2, fondo):
    """Árboles de ramas en ángulo recto, como los de clasificación de especies."""
    n = r.randint(2, 3)
    ancho = W / n
    figs, puntas = [], []
    for k in range(n):
        hojas_ = r.randint(7, 10)
        ys = [16 + i * (H - 32) / (hojas_ - 1) for i in range(hojas_)]
        _dendrograma(r, ys, k * ancho + 20, (k + 1) * ancho - 30, figs, puntas, c1, c2)
    return figs + puntas


# ------------------------------------------------------------ Recetas y cocina

def especias(r, c1, c2, fondo):
    """Cuencos con montoncitos de especias sobre la mesa, y algún anís estrellado suelto."""
    n = r.randint(6, 8)
    centros, paso = _puntos_repartidos(r, n, 60)
    mesa = [("rect", 0, 182, W, 18, c1)]
    cuencos, montones, motas, anises = [], [], [], []
    for cx in centros:
        w = min(r.uniform(104, 142), paso * 0.86)
        borde = 182 - w * 0.4
        cuencos.append(("poly", [(cx + w / 2 * math.cos(t), borde + w * 0.4 * math.sin(t))
                                 for t in [math.pi * j / 8 for j in range(9)]], c1))
        alto = r.uniform(0.18, 0.34) * w
        montones.append(("poly", [(cx + w * 0.42 * math.cos(t), borde - alto * math.sin(t))
                                  for t in [math.pi * j / 8 for j in range(9)]], c2))
        for _ in range(r.randint(2, 4)):
            motas.append(("circle", cx + r.uniform(-0.28, 0.28) * w, borde - r.uniform(0.2, 0.7) * alto, 2.6, fondo))
    ristras = []
    for k in range(len(centros) - 1):
        x = (centros[k] + centros[k + 1]) / 2
        if r.random() < 0.7:
            anises.append(estrella(x, r.uniform(150, 168), r.uniform(8, 12), c1, 8, 0.5))
        if r.random() < 0.65:
            largo = r.uniform(50, 90)
            ristras.append(("line", [(x, 0), (x, largo)], c1, 2))
            for j in range(int(largo // 22)):
                anises.append(elipse(x + (4 if j % 2 else -4), 18 + j * 22, 7, 12, 0.1 if j % 2 else -0.1, c2, 8))
    return mesa + ristras + cuencos + montones + motas + anises


def _tenedor(x, y0, y1, c):
    return [("line", [(x, y1), (x, y0 + 30)], c, 3.2),
            ("line", [(x - 8, y0), (x - 8, y0 + 28), (x + 8, y0 + 28), (x + 8, y0)], c, 2.6),
            ("line", [(x, y0), (x, y0 + 28)], c, 2.6)]


def platos(r, c1, c2, fondo):
    """Platos vistos desde arriba, cada uno con su comida y sus cubiertos."""
    n = r.randint(4, 5)
    centros, paso = _puntos_repartidos(r, n, 70)
    cy = 100
    base, interior, comida, cubiertos = [], [], [], []
    for cx in centros:
        rad = min(r.uniform(66, 82), paso * 0.36)
        base.append(("circle", cx, cy, rad, c1))
        interior.append(("circle", cx, cy, rad * 0.84, fondo))
        interior.append(("ring", cx, cy, rad * 0.62, c2, 2))
        tipo = r.randint(0, 2)
        if tipo == 0:
            for _ in range(6):
                a, d = r.uniform(0, TAU), r.uniform(0, rad * 0.4)
                comida.append(("circle", cx + d * math.cos(a), cy + d * math.sin(a), r.uniform(5, 8), c2))
        elif tipo == 1:
            for k in range(3):
                comida.append(hoja(cx, cy, rad * 0.5, rad * 0.22, TAU * k / 3 + r.uniform(0, 1), c1, 2))
        else:
            comida.append(("line", espiral(cx, cy, 3, rad * 0.46, 2.5, r.uniform(0, TAU), 1, 36), c1, 4))
        cubiertos += _tenedor(cx - rad - 20, cy - 52, cy + 56, c1)
        cubiertos.append(("poly", [(cx + rad + 14, cy + 56), (cx + rad + 14, cy - 52), (cx + rad + 26, cy - 36),
                                   (cx + rad + 26, cy + 56)], c1))
    return base + interior + comida + cubiertos


def frutas(r, c1, c2, fondo):
    """Manzanas, peras, limones y cerezas repartidas en dos hileras."""
    n = r.randint(10, 13)
    xs, paso = _puntos_repartidos(r, n, 30)
    cuerpos1, cuerpos2, tallos, hojas, brillos = [], [], [], [], []
    for k, cx in enumerate(xs):
        rad = r.uniform(22, 32)
        cy = (58 if k % 2 == 0 else 142) + r.uniform(-10, 10)
        tipo = r.choice(["manzana", "pera", "limon", "cerezas"])
        cuerpos = cuerpos1 if (tipo != "limon" and r.random() < 0.8) else cuerpos2
        if tipo == "manzana":
            cuerpos += [("circle", cx - 0.3 * rad, cy, 0.78 * rad, c1 if cuerpos is cuerpos1 else c2),
                        ("circle", cx + 0.3 * rad, cy, 0.78 * rad, c1 if cuerpos is cuerpos1 else c2)]
            tope = (cx, cy - 0.7 * rad)
        elif tipo == "pera":
            col = c1 if cuerpos is cuerpos1 else c2
            cuerpos += [("circle", cx, cy + 0.3 * rad, 0.82 * rad, col), ("circle", cx, cy - 0.5 * rad, 0.5 * rad, col)]
            tope = (cx, cy - 1.0 * rad)
        elif tipo == "limon":
            cuerpos2.append(elipse(cx, cy, rad * 1.15, rad * 0.78, r.uniform(-0.5, 0.5), c2, 10))
            tope = (cx, cy - 0.8 * rad)
        else:
            col = c1 if cuerpos is cuerpos1 else c2
            cuerpos += [("circle", cx - 0.55 * rad, cy + 0.5 * rad, 0.55 * rad, col),
                        ("circle", cx + 0.55 * rad, cy + 0.6 * rad, 0.55 * rad, col)]
            tallos += [("line", [(cx - 0.55 * rad, cy + 0.5 * rad), (cx, cy - 0.8 * rad)], c1, 2),
                       ("line", [(cx + 0.55 * rad, cy + 0.6 * rad), (cx, cy - 0.8 * rad)], c1, 2)]
            tope = (cx, cy - 0.8 * rad)
        if tipo != "limon":
            brillos.append(("circle", cx - 0.45 * rad, cy - 0.15 * rad, 0.11 * rad + 1, fondo))
        if tipo in ("manzana", "pera"):
            tallos.append(("line", [tope, (tope[0] + 3, tope[1] - 9)], c1, 3))
        hojas.append(hoja(tope[0] + 2, tope[1] - 5, rad * 0.8, rad * 0.4, -0.5 - r.uniform(0, 0.6), c2, 2))
    return cuerpos1 + cuerpos2 + tallos + hojas + brillos


def ollas_y_vapor(r, c1, c2, fondo):
    """Ollas con tapa sobre el fuego y columnas de vapor que suben."""
    n = r.randint(4, 6)
    centros, paso = _puntos_repartidos(r, n, 70)
    cuerpos, tapas, vapor, asas = [], [], [], []
    base = 190
    for cx in centros:
        w, h = min(r.uniform(90, 140), paso * 0.78), r.uniform(46, 76)
        cuerpos.append(("poly", [(cx - w / 2, base - h), (cx + w / 2, base - h), (cx + w / 2 - 8, base), (cx - w / 2 + 8, base)], c1))
        asas.append(("rect", cx - w / 2 - 16, base - h + 10, 18, 8, c1))
        asas.append(("rect", cx + w / 2 - 2, base - h + 10, 18, 8, c1))
        tapas.append(("poly", [(cx + 0.47 * w * math.cos(t), base - h - 0.2 * w * math.sin(t))
                               for t in [math.pi * j / 8 for j in range(9)]], c2))
        tapas.append(("circle", cx, base - h - 0.2 * w - 4, 6, c2))
        ytope = base - h - 0.2 * w - 16
        for dx in (-w * 0.16, 0, w * 0.16):
            fase = r.uniform(0, TAU)
            vapor.append(("line", [(cx + dx + 7 * math.sin(fase + k * 1.1), ytope - k * 13) for k in range(5)], c2, 3))
    return cuerpos + asas + tapas + vapor


# -------------------------------------------------------- Filosofía y ensayo

def laberinto(r, c1, c2, fondo):
    """Laberinto de muros rectos con la ruta de salida resaltada."""
    celda = 40
    cols, filas = W // celda, H // celda
    inicio_j, fin_j = r.randrange(filas), r.randrange(filas)
    visitado = {(0, inicio_j)}
    pila = [(0, inicio_j)]
    abierto, padre = set(), {}
    while pila:
        i, j = pila[-1]
        vecinos = [(i + di, j + dj) for di, dj in ((1, 0), (-1, 0), (0, 1), (0, -1))
                   if 0 <= i + di < cols and 0 <= j + dj < filas and (i + di, j + dj) not in visitado]
        if not vecinos:
            pila.pop()
            continue
        nxt = r.choice(vecinos)
        abierto.add((min((i, j), nxt), max((i, j), nxt)))
        padre[nxt] = (i, j)
        visitado.add(nxt)
        pila.append(nxt)
    ruta, c = [], (cols - 1, fin_j)
    while c != (0, inicio_j):
        ruta.append(((c[0] + 0.5) * celda, (c[1] + 0.5) * celda))
        c = padre[c]
    ruta += [((c[0] + 0.5) * celda, (c[1] + 0.5) * celda), (-celda / 2, (inicio_j + 0.5) * celda)]
    ruta.insert(0, (W + celda / 2, (fin_j + 0.5) * celda))
    muros = []
    for i in range(cols - 1):                       # muros verticales
        j = 0
        while j < filas:
            if ((i, j), (i + 1, j)) in abierto:
                j += 1
                continue
            j0 = j
            while j < filas and ((i, j), (i + 1, j)) not in abierto:
                j += 1
            muros.append(("line", [((i + 1) * celda, j0 * celda), ((i + 1) * celda, j * celda)], c1, 3))
    for j in range(filas - 1):                      # muros horizontales
        i = 0
        while i < cols:
            if ((i, j), (i, j + 1)) in abierto:
                i += 1
                continue
            i0 = i
            while i < cols and ((i, j), (i, j + 1)) not in abierto:
                i += 1
            muros.append(("line", [(i0 * celda, (j + 1) * celda), (i * celda, (j + 1) * celda)], c1, 3))
    marco = [("line", [(0, 1.5), (W, 1.5)], c1, 3), ("line", [(0, H - 1.5), (W, H - 1.5)], c1, 3)]
    for x, j in ((1.5, inicio_j), (W - 1.5, fin_j)):
        if j > 0:
            marco.append(("line", [(x, 0), (x, j * celda)], c1, 3))
        if j < filas - 1:
            marco.append(("line", [(x, (j + 1) * celda), (x, H)], c1, 3))
    meta = [("circle", celda / 2, (inicio_j + 0.5) * celda, 8, c1), estrella(W - celda / 2, (fin_j + 0.5) * celda, 13, c1, 5, 0.45)]
    return [("line", ruta, c2, 8)] + muros + marco + meta


def _esfera_armilar(cx, cy, rad, ang, c1, c2):
    figs = [("ring", cx, cy, rad, c1, 2.6)]
    for k in (0.38, 0.72):
        figs.append(("line", _elipse_linea(cx, cy, rad * k, rad, ang, 18), c2, 2))
    figs.append(("line", _elipse_linea(cx, cy, rad, rad * 0.28, ang, 20), c1, 2.4))
    figs.append(("line", _elipse_linea(cx, cy, rad, rad * 0.28, ang + 0.5, 20), c2, 2))
    figs.append(("line", _colocar([(0, -rad * 1.18), (0, rad * 1.18)], cx, cy, ang), c1, 2.4))
    figs.append(("circle", cx, cy, rad * 0.13, c1))
    return figs


def esfera_armilar(r, c1, c2, fondo):
    """Esferas armilares: anillos cruzados que representan el cielo, unidos por un hilo."""
    n = r.randint(3, 4)
    centros, paso = _puntos_repartidos(r, n, 90)
    figs = [("line", [(0, 100), (W, 100)], c2, 1.6)]
    for cx in centros:
        figs += _esfera_armilar(cx, 100, min(r.uniform(58, 80), paso * 0.4), r.uniform(-0.45, 0.45), c1, c2)
    return figs


def puertas(r, c1, c2, fondo):
    """Puertas con arco redondo, cada una dentro de otra, que invitan a seguir pasando."""
    n = r.randint(3, 5)
    centros, paso = _puntos_repartidos(r, n, 70)
    figs = []
    for cx in centros:
        w0, h0 = min(r.uniform(120, 170), paso * 0.8), r.uniform(170, 192)
        k = r.randint(4, 6)
        dx = r.uniform(-4, 4)
        for i in range(k):
            f = 1 - i * 0.17
            rr = w0 * f / 2
            xc = cx + dx * i
            yc = H - h0 * f + rr
            pts = [(xc - rr, H + 2), (xc - rr, yc)] + _arco(xc, yc, rr, math.pi, TAU, 10) + [(xc + rr, H + 2)]
            if i == k - 1:
                figs.append(("poly", pts, c1))
            else:
                figs.append(("line", pts, c1 if i % 2 == 0 else c2, 3 if i % 2 == 0 else 2.2))
    return figs


def red_de_ideas(r, c1, c2, fondo):
    """Red de ideas: nodos de distinto peso unidos por líneas entre los más cercanos."""
    pts = []
    for _ in range(4000):
        if len(pts) >= r.randint(30, 36):
            break
        p = (r.uniform(14, W - 14), r.uniform(14, H - 14))
        if all((p[0] - q[0]) ** 2 + (p[1] - q[1]) ** 2 > 58 ** 2 for q in pts):
            pts.append(p)
    aristas, grado = set(), [0] * len(pts)
    for i, p in enumerate(pts):
        cerca = sorted(range(len(pts)), key=lambda j: (pts[j][0] - p[0]) ** 2 + (pts[j][1] - p[1]) ** 2)[1:r.randint(3, 4)]
        for j in cerca:
            if (pts[j][0] - p[0]) ** 2 + (pts[j][1] - p[1]) ** 2 < 150 ** 2:
                aristas.add((min(i, j), max(i, j)))
    figs = [("line", [pts[a], pts[b]], c2, 1.6) for a, b in sorted(aristas)]
    for a, b in aristas:
        grado[a] += 1
        grado[b] += 1
    nodos, aros = [], []
    for i, (x, y) in enumerate(pts):
        nodos.append(("circle", x, y, 3.5 + min(grado[i], 5) * 1.1, c1 if i % 3 else c2))
        if grado[i] >= 4:
            aros.append(("ring", x, y, 15, c1, 2))
    return figs + nodos + aros


# -------------------------------------------------------------------- Teatro

def cortinas(r, c1, c2, fondo):
    """Telón abierto: cortinas con pliegues recogidas a los lados, cenefa arriba y un foco en el escenario."""
    ancho = r.uniform(360, 480)
    recogido = r.uniform(20, 70)

    def borde(y):
        return ancho - recogido * math.sin(math.pi * y / 180) ** 1.2

    figs = [("circle", W / 2, 128, r.uniform(58, 78), c2), ("ring", W / 2, 128, r.uniform(92, 110), c2, 1.6),
            ("rect", 0, 178, W, 22, c1)]
    for lado in (1, -1):
        def x_(v):
            return v if lado == 1 else W - v
        pts = [(x_(0), 0), (x_(ancho), 0)] + [(x_(borde(y)), y) for y in range(10, 181, 15)] + [(x_(borde(180)), 180), (x_(0), 180)]
        figs.append(("poly", pts, c1))
    for lado in (1, -1):
        for k in range(1, 9):
            x = k * (ancho - recogido) / 9
            figs.append(("line", [(x if lado == 1 else W - x, 30), (x if lado == 1 else W - x, 174)], fondo, 2.4))
    n = r.randint(6, 8)
    ancho_g = W / n
    figs.append(("rect", 0, 0, W, 22, c1))
    for k in range(n):
        figs.append(("poly", [(k * ancho_g + ancho_g / 2 + ancho_g / 2 * math.cos(t), 22 + 27 * math.sin(t))
                              for t in [math.pi * j / 8 for j in range(9)]], c1))
        figs.append(("circle", (k + 1) * ancho_g, 26, 5, c2))
    return figs


def _mascara(cx, cy, s, ang, comedia, c1, fondo):
    figs = [elipse(cx, cy, s * 0.8, s, ang, c1, 12)]
    signo = -1 if comedia else 1
    for lado in (-1, 1):
        ex, ey = _colocar([(lado * s * 0.34, -s * 0.18)], cx, cy, ang)[0]
        figs.append(elipse(ex, ey, s * 0.2, s * 0.085, ang + signo * lado * 0.4, fondo, 6))
        bx, by = _colocar([(lado * s * 0.34, -s * 0.46)], cx, cy, ang)[0]
        figs.append(("line", _colocar([(-s * 0.2, signo * lado * 0.08 * s), (s * 0.2, -signo * lado * 0.08 * s)], bx, by, ang), fondo, 3))
    amplitud = s * 0.2
    boca = [(t * s * 0.38, s * 0.42 + (amplitud * (1 - t * t) if comedia else amplitud * t * t))
            for t in [-1 + 2 * j / 8 for j in range(9)]]
    figs.append(("line", _colocar(boca, cx, cy, ang), fondo, 3.4))
    return figs


def mascaras(r, c1, c2, fondo):
    """Máscaras de comedia y de tragedia, alternadas y ligeramente inclinadas."""
    n = r.randint(6, 8)
    centros, paso = _puntos_repartidos(r, n, 50)
    figs, cintas = [], []
    for k, cx in enumerate(centros):
        s = min(r.uniform(40, 56), paso * 0.4)
        cy = 100 + r.uniform(-14, 14)
        ang = r.uniform(-0.3, 0.3)
        cintas.append(("line", [(cx - s * 0.75, cy - s * 0.6), (cx - s * 1.25, cy - s * 0.2), (cx - s * 1.1, cy + s * 0.5)], c2, 2.2))
        figs += _mascara(cx, cy, s, ang, k % 2 == 0, c1, fondo)
    return cintas + figs


def focos(r, c1, c2, fondo):
    """Focos que bajan del techo y dejan charcos de luz en el escenario, a veces con una silueta."""
    n = r.randint(4, 6)
    xs, paso = _puntos_repartidos(r, n, 90)
    conos, charcos, siluetas, lamparas = [], [], [], []
    for x in xs:
        xt = x + r.uniform(-50, 50)
        mitad = r.uniform(34, 58)
        conos.append(("poly", [(xt - 6, 16), (xt + 6, 16), (x + mitad, 178), (x - mitad, 178)], c2))
        charcos.append(elipse(x, 180, mitad, 9, 0, c2, 14))
        lamparas.append(("poly", [(xt - 15, 0), (xt + 15, 0), (xt + 9, 20), (xt - 9, 20)], c1))
        if r.random() < 0.65:
            siluetas.append(("circle", x, 104, 10, c1))
            siluetas.append(("poly", [(x - 14, 180), (x - 14, 128), (x - 7, 118), (x + 7, 118), (x + 14, 128), (x + 14, 180)], c1))
    return conos + [("rect", 0, 176, W, 24, c1)] + charcos + siluetas + lamparas


def platea(r, c1, c2, fondo):
    """Filas de butacas vistas desde el escenario, con algunos espectadores."""
    asientos, cabezas = [], []
    cx = W / 2
    pasillos = [W * r.uniform(0.28, 0.36), W * r.uniform(0.64, 0.72)]
    for fila in range(4):
        y0 = 40 + fila * 42
        x = 14 + (17 if fila % 2 else 0)
        while x < W - 14:
            if all(abs(x - p) > 20 for p in pasillos):
                y = y0 - 0.00005 * (x - cx) ** 2
                asientos.append(("rect", x - 12, y, 24, 18, c1))
                if r.random() < 0.55:
                    cabezas.append(("circle", x, y - 7, 8, c2))
            x += 34
    return asientos + cabezas


# ----------------------------------------------------------- Viajes y crónicas

def _rosa(cx, cy, rad, ang, c1, c2, fondo):
    figs = [("ring", cx, cy, rad * 0.72, c1, 2), ("ring", cx, cy, rad * 0.8, c2, 1.5)]
    for k in range(12):
        a = ang + TAU * k / 12
        figs.append(("line", [(cx + rad * 0.85 * math.cos(a), cy + rad * 0.85 * math.sin(a)),
                              (cx + rad * 0.92 * math.cos(a), cy + rad * 0.92 * math.sin(a))], c1, 1.6))
    for largo, giro in ((0.64, math.pi / 4), (1.0, 0.0)):
        for k in range(4):
            a = ang + giro + k * math.pi / 2
            punta = (cx + rad * largo * math.cos(a), cy + rad * largo * math.sin(a))
            izq = (cx + rad * 0.2 * math.cos(a - math.pi / 4), cy + rad * 0.2 * math.sin(a - math.pi / 4))
            der = (cx + rad * 0.2 * math.cos(a + math.pi / 4), cy + rad * 0.2 * math.sin(a + math.pi / 4))
            figs.append(("poly", [(cx, cy), punta, izq], c1 if largo == 1.0 else c2))
            figs.append(("poly", [(cx, cy), punta, der], c2 if largo == 1.0 else c1))
    figs.append(("circle", cx, cy, rad * 0.07, fondo))
    return figs


def brujula(r, c1, c2, fondo):
    """Rosas de los vientos con rumbos que cruzan el mapa, como en las cartas de navegación antiguas."""
    n = r.randint(3, 4)
    centros, paso = _puntos_repartidos(r, n, 100)
    figs, rosas = [], []
    for cx in centros:
        rad = min(r.uniform(62, 86), paso * 0.4)
        ang0 = r.uniform(0, math.pi / 8)
        for k in range(5):
            a = ang0 + k * math.pi / 5 + math.pi / 10
            figs.append(("line", [(cx - 800 * math.cos(a), 100 - 800 * math.sin(a)),
                                  (cx + 800 * math.cos(a), 100 + 800 * math.sin(a))], c2, 1))
        rosas.append((cx, rad, ang0))
    for cx, rad, ang0 in rosas:
        figs.append(("circle", cx, 100, rad * 0.9, fondo))
        figs += _rosa(cx, 100, rad, ang0 - math.pi / 2, c1, c2, fondo)
    return figs


def mapa_y_ruta(r, c1, c2, fondo):
    """Curvas de nivel de un terreno, con una ruta punteada que va de un círculo a una cruz."""
    figs = []
    n = r.randint(3, 4)
    centros, paso = _puntos_repartidos(r, n, 120)
    for cx in centros:
        cy = r.uniform(60, 140)
        f1, f2 = r.uniform(0, TAU), r.uniform(0, TAU)
        rmax = min(80, paso * 0.2)
        for k in range(4):
            rad = rmax * (k + 1) / 4
            pts = []
            for j in range(20):
                t = TAU * j / 20
                m = rad * (1 + 0.16 * math.sin(3 * t + f1) + 0.09 * math.sin(5 * t + f2))
                pts.append((cx + m * 1.9 * math.cos(t), cy + m * 0.95 * math.sin(t)))
            figs.append(("line", _cerrada(pts), c2, 1.6))
    fase, lam = r.uniform(0, TAU), r.uniform(300, 420)
    muestras = [(x, 100 + 52 * math.sin(TAU * x / lam + fase) + 14 * math.sin(TAU * x / (lam * 0.37))) for x in range(40, W - 39, 6)]
    ruta = [("line", muestras[i:i + 4], c1, 3.2) for i in range(0, len(muestras) - 4, 7)]
    x0, y0 = muestras[0]
    x1, y1 = muestras[-1]
    marcas = [("ring", x0, y0, 8, c1, 3),
              ("line", [(x1 - 9, y1 - 9), (x1 + 9, y1 + 9)], c1, 4.5), ("line", [(x1 - 9, y1 + 9), (x1 + 9, y1 - 9)], c1, 4.5)]
    return figs + ruta + marcas


def _velero(cx, base, s, c1, c2):
    return [("poly", [(cx - 0.55 * s, base - 0.18 * s), (cx + 0.6 * s, base - 0.18 * s), (cx + 0.4 * s, base), (cx - 0.4 * s, base)], c1),
            ("line", [(cx, base - 0.18 * s), (cx, base - 1.25 * s)], c1, 2.2),
            ("poly", [(cx + 3, base - 1.15 * s), (cx + 3, base - 0.28 * s), (cx + 0.52 * s, base - 0.28 * s)], c2),
            ("poly", [(cx - 3, base - 1.0 * s), (cx - 3, base - 0.28 * s), (cx - 0.5 * s, base - 0.28 * s)], c1),
            ("poly", [(cx, base - 1.25 * s), (cx + 0.2 * s, base - 1.19 * s), (cx, base - 1.13 * s)], c1)]


def veleros(r, c1, c2, fondo):
    """Veleros de distinto tamaño sobre un mar de olas, con aves en el cielo."""
    figs = []
    for k in range(4):
        figs.append(("line", onda_mar(r, 128 + k * 20, k), c2, 2.2))
    n = r.randint(4, 6)
    xs, paso = _puntos_repartidos(r, n, 70)
    barcos = []
    for cx in xs:
        s = r.uniform(34, 70)
        barcos += _velero(cx, 118 + (s - 34) * 1.1, s, c1, c2)
    pajaros = []
    for _ in range(r.randint(4, 7)):
        x, y = r.uniform(30, W - 30), r.uniform(14, 50)
        pajaros.append(("line", [(x - 9, y - 4), (x, y), (x + 9, y - 4)], c1, 2.2))
    return figs + barcos + pajaros


def onda_mar(r, y0, k):
    lam, fase = r.uniform(110, 190), r.uniform(0, TAU)
    return [(x, y0 + 4 * math.sin(TAU * x / lam + fase)) for x in range(-10, W + 11, 18)]


def cordillera(r, c1, c2, fondo):
    """Dos cadenas de montañas (la lejana solo de contorno), caras iluminadas y una bandera en la cumbre más alta."""
    def cadena(base, hmin, hmax, wmin, wmax):
        pts, picos, x = [], [], -r.uniform(20, 80)
        valle_ant = (x, base - r.uniform(0, 12))
        while x < W + 60:
            w, h = r.uniform(wmin, wmax), r.uniform(hmin, hmax)
            valle_sig = (x + w, base - r.uniform(0, 12))
            pico = (x + w * r.uniform(0.38, 0.62), base - h)
            pts += [valle_ant, pico]
            picos.append((pico, valle_ant, valle_sig))
            valle_ant, x = valle_sig, x + w
        pts.append(valle_ant)
        return pts, picos

    atras, _ = cadena(150, 45, 95, 130, 230)
    frente, picos = cadena(192, 80, 140, 150, 270)
    figs = [("line", atras, c2, 2.4), ("poly", [(-60, H + 2)] + frente + [(W + 60, H + 2)], c1)]
    for (px, py), (xl, yl), (xr, yr) in picos:
        if px < -40 or px > W + 40:
            continue
        figs.append(("poly", [(px, py), (xr, yr), (px + (xr - px) * 0.18, max(yl, yr) + 4), (px, py + (max(yl, yr) - py) * 0.62)], c2))
    visibles = [p for p in picos if 80 < p[0][0] < W - 80]
    if visibles:
        px, py = min(visibles, key=lambda p: p[0][1])[0]
        figs.append(("line", [(px, py), (px, py - 36)], c2, 2.4))
        figs.append(("poly", [(px, py - 36), (px + 24, py - 29), (px, py - 22)], c2))
    return figs


MOTIVOS_TEXTO = {
    "engranajes": engranajes, "plano_tecnico": plano_tecnico, "regla": regla, "circuito": circuito,
    "rayos_de_luz": rayos_de_luz, "mandala": mandala, "rollos": rollos, "velas": velas,
    "balanza": balanza, "sellos": sellos, "articulos": articulos, "mazo": mazo,
    "sobres": sobres, "estampillas": estampillas, "lacre": lacre, "plumas": plumas,
    "calendario": calendario, "fases_lunares": fases_lunares, "ventana_lluvia": ventana_lluvia, "herbario": herbario,
    "orbitas": orbitas, "hexagonos": hexagonos, "grafica_datos": grafica_datos, "arbol_filogenetico": arbol_filogenetico,
    "especias": especias, "platos": platos, "frutas": frutas, "ollas_y_vapor": ollas_y_vapor,
    "laberinto": laberinto, "esfera_armilar": esfera_armilar, "puertas": puertas, "red_de_ideas": red_de_ideas,
    "cortinas": cortinas, "mascaras": mascaras, "focos": focos, "platea": platea,
    "brujula": brujula, "mapa_y_ruta": mapa_y_ruta, "veleros": veleros, "cordillera": cordillera,
}
