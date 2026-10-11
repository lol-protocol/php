#!/usr/bin/env python3
"""Genera el catálogo de marcalibros y los paisajes JPG de la marca.

Cada marcalibro se define como una lista de figuras. Esa misma lista se
escribe como SVG y se rasteriza como vista previa JPG, así ambas versiones
siempre coinciden.

Uso:
    python3 generator/generar_marcalibros.py                        # 100 marcalibros
    python3 generator/generar_marcalibros.py --por-movimiento 100   # 1000 marcalibros
"""
import argparse
import html
import json
import math
import random
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw

W, H = 1400, 200          # tamaño de un marcalibro
SUPERSAMPLE = 2           # se rasteriza al doble y se reduce para suavizar bordes
CALIDAD_JPG = 85

PALETA = {
    "tinta": "#1E2A38",
    "papel": "#F4EEE1",
    "ocre": "#C98B3A",
    "verde": "#3E5C4A",
    "niebla": "#7A9CB0",
}
RGB = {k: tuple(int(v[i:i + 2], 16) for i in (1, 3, 5)) for k, v in PALETA.items()}

MOVIMIENTOS = [
    {"slug": "romanticismo", "nombre": "Romanticismo", "motivo": "amanecer",
     "autores": ["Lord Byron", "John Keats", "Gustavo Adolfo Bécquer"]},
    {"slug": "realismo", "nombre": "Realismo", "motivo": "ciudad",
     "autores": ["Gustave Flaubert", "Honoré de Balzac", "Benito Pérez Galdós"]},
    {"slug": "naturalismo", "nombre": "Naturalismo", "motivo": "bosque",
     "autores": ["Émile Zola", "Guy de Maupassant", "Horacio Quiroga"]},
    {"slug": "modernismo", "nombre": "Modernismo", "motivo": "ondas",
     "autores": ["Rubén Darío", "Virginia Woolf", "James Joyce"]},
    {"slug": "vanguardias", "nombre": "Vanguardias", "motivo": "geometria",
     "autores": ["Guillaume Apollinaire", "Vicente Huidobro", "Tristan Tzara"]},
    {"slug": "gotico", "nombre": "Gótico", "motivo": "arcos",
     "autores": ["Mary Shelley", "Edgar Allan Poe", "Bram Stoker"]},
    {"slug": "barroco", "nombre": "Barroco", "motivo": "ornamento",
     "autores": ["Luis de Góngora", "Francisco de Quevedo", "Pedro Calderón de la Barca"]},
    {"slug": "clasicismo", "nombre": "Clasicismo", "motivo": "columnas",
     "autores": ["Jean Racine", "Molière", "Pierre Corneille"]},
    {"slug": "simbolismo", "nombre": "Simbolismo", "motivo": "luna",
     "autores": ["Stéphane Mallarmé", "Paul Verlaine", "Arthur Rimbaud"]},
    {"slug": "realismo-magico", "nombre": "Realismo mágico", "motivo": "espiral",
     "autores": ["Gabriel García Márquez", "Juan Rulfo", "Alejo Carpentier"]},
]

ESCENAS = [
    {"slug": "amanecer", "cielo": ("niebla", "ocre"), "sol": "papel",
     "capas": ["verde", "tinta"], "montanas": False},
    {"slug": "bosque", "cielo": ("papel", "niebla"), "sol": None,
     "capas": ["verde", "verde", "tinta"], "montanas": False},
    {"slug": "costa", "cielo": ("niebla", "papel"), "sol": "ocre",
     "capas": ["niebla", "verde", "tinta"], "montanas": False},
    {"slug": "montana", "cielo": ("tinta", "niebla"), "sol": "papel",
     "capas": ["niebla", "verde", "tinta"], "montanas": True},
]
TAMANOS_PAISAJE = [(1920, 1080), (1200, 630)]


def _rng(*claves):
    """Generador determinista: el mismo marcalibro sale igual en cada ejecución."""
    return random.Random("-".join(str(c) for c in claves))


def _rotar(cx, cy, dx, dy, ang):
    return (cx + dx * math.cos(ang) - dy * math.sin(ang),
            cy + dx * math.sin(ang) + dy * math.cos(ang))


# Cada figura es una tupla con el tipo en la primera posición. Los colores son
# nombres de la paleta, nunca valores hex sueltos.
#   ("rect", x, y, ancho, alto, color)
#   ("circle", cx, cy, radio, color)
#   ("poly", [(x, y), ...], color)
#   ("line", [(x, y), ...], color, grosor)
#   ("ring", cx, cy, radio, color, grosor)

def motivo_amanecer(r, c1, c2, fondo):
    figuras = [("circle", r.uniform(300, 1100), H * 0.62, r.uniform(40, 70), c1)]
    for alto_rel, color in [(0.45, c2), (0.25, c1)]:
        fase = r.uniform(0, 2 * math.pi)
        amp = r.uniform(10, 22)
        puntos = [(0, H)]
        for x in range(0, W + 1, 20):
            y = H * (1 - alto_rel) + amp * math.sin(x / 180 + fase) + 8 * math.sin(x / 67 + 2 * fase)
            puntos.append((x, y))
        puntos.append((W, H))
        figuras.append(("poly", puntos, color))
    return figuras


def motivo_ciudad(r, c1, c2, fondo):
    figuras = []
    x = 0.0
    while x < W:
        ancho = r.uniform(40, 110)
        alto = r.uniform(50, 160)
        figuras.append(("rect", x, H - alto, ancho - 6, alto, c2))
        for vx in range(int(x + 8), int(x + ancho - 14), 16):
            for vy in range(int(H - alto + 12), H - 14, 18):
                if r.random() < 0.55:
                    figuras.append(("rect", vx, vy, 6, 8, fondo))
        x += ancho
    return figuras


def motivo_bosque(r, c1, c2, fondo):
    figuras = []
    for color, alto_min, alto_max in [(c1, 50, 110), (c2, 90, 170)]:
        x = -20.0
        while x < W + 20:
            alto = r.uniform(alto_min, alto_max)
            ancho = alto * r.uniform(0.38, 0.5)
            cx = x + ancho / 2
            figuras.append(("poly", [(cx, H - alto), (x + ancho, H), (x, H)], color))
            x += ancho * r.uniform(0.6, 0.9)
    return figuras


def motivo_ondas(r, c1, c2, fondo):
    figuras = []
    n = r.randint(4, 6)
    for i in range(n):
        y0 = H * (0.15 + 0.7 * i / max(n - 1, 1))
        amp = r.uniform(6, 16)
        lam = r.uniform(220, 420)
        fase = r.uniform(0, 2 * math.pi)
        pts = [(x, y0 + amp * math.sin(2 * math.pi * x / lam + fase))
               for x in range(-10, W + 11, 14)]
        figuras.append(("line", pts, c1 if i % 2 == 0 else c2, 3.0))
    return figuras


def motivo_geometria(r, c1, c2, fondo):
    figuras = []
    for _ in range(r.randint(7, 10)):
        cx, cy = r.uniform(60, W - 60), r.uniform(40, H - 40)
        tam = r.uniform(18, 46)
        color = r.choice([c1, c2])
        forma = r.choice(["circle", "rect", "tri"])
        if forma == "circle":
            figuras.append(("circle", cx, cy, tam / 2, color))
        elif forma == "rect":
            ang = r.uniform(0, math.pi / 2)
            esquinas = [(-1, -1), (1, -1), (1, 1), (-1, 1)]
            pts = [_rotar(cx, cy, dx * tam / 2, dy * tam / 2, ang) for dx, dy in esquinas]
            figuras.append(("poly", pts, color))
        else:
            ang = r.uniform(0, 2 * math.pi)
            pts = [(cx + tam * math.cos(ang + k * 2 * math.pi / 3),
                    cy + tam * math.sin(ang + k * 2 * math.pi / 3)) for k in range(3)]
            figuras.append(("poly", pts, color))
    return figuras


def motivo_arcos(r, c1, c2, fondo):
    figuras = []
    x = r.uniform(0, 30)
    while x < W:
        ancho = r.uniform(70, 110)
        hombro = H * r.uniform(0.35, 0.5)
        cima = r.uniform(-10, 20)
        cx = x + ancho / 2
        figuras.append(("poly", [(x, H), (x, hombro), (cx, cima), (x + ancho, hombro), (x + ancho, H)], c2))
        m = 12
        figuras.append(("poly", [(x + m, H), (x + m, hombro + 12), (cx, cima + m * 1.8),
                                 (x + ancho - m, hombro + 12), (x + ancho - m, H)], fondo))
        x += ancho + r.uniform(14, 28)
    return figuras


def motivo_ornamento(r, c1, c2, fondo):
    figuras = []
    for _ in range(r.randint(4, 7)):
        cx, cy = r.uniform(40, W - 40), r.uniform(30, H - 30)
        radio = r.uniform(18, 48)
        for k in range(r.randint(2, 4)):
            rad_k = radio - k * 7
            if rad_k < 4:
                break
            color = c1 if k % 2 == 0 else c2
            figuras.append(("ring", cx, cy, rad_k, color, 2.5))
        figuras.append(("circle", cx, cy, 4, c2))
    for _ in range(r.randint(20, 30)):
        figuras.append(("circle", r.uniform(0, W), r.uniform(0, H), r.uniform(1.5, 3), c2))
    return figuras


def motivo_columnas(r, c1, c2, fondo):
    figuras = []
    espacio = r.uniform(150, 190)
    base = H - 14
    x = r.uniform(10, 60)
    while x + 44 < W:
        figuras.append(("rect", x + 8, 52, 28, base - 52 - 12, c2))   # fuste
        figuras.append(("rect", x, 42, 44, 12, c1))                    # capitel
        figuras.append(("rect", x, base - 12, 44, 12, c1))             # base
        x += espacio
    figuras.append(("rect", 0, H - 6, W, 6, c1))                       # estilóbato
    return figuras


def motivo_luna(r, c1, c2, fondo):
    cx, cy = r.uniform(180, W - 180), r.uniform(60, 110)
    rad = r.uniform(42, 58)
    figuras = [("circle", cx, cy, rad, c1),
               ("circle", cx + rad * 0.45, cy - rad * 0.2, rad * 0.9, fondo)]  # media luna
    for _ in range(r.randint(14, 22)):
        x, y = r.uniform(0, W), r.uniform(0, H * 0.9)
        if abs(x - cx) < rad * 2 and abs(y - cy) < rad * 2:
            continue
        s = r.uniform(3, 7)
        pts = []
        for k in range(8):   # estrella de 8 puntas
            ang = k * math.pi / 4
            radio = s if k % 2 == 0 else s * 0.3
            pts.append((x + radio * math.cos(ang), y + radio * math.sin(ang)))
        figuras.append(("poly", pts, c2))
    return figuras


def motivo_espiral(r, c1, c2, fondo):
    cx, cy = r.uniform(200, W - 200), r.uniform(70, 130)
    rmax = min(cy, H - cy) - 6
    pts = []
    for k in range(400):
        t = k * 0.12
        rad = 2 + t * 2.2
        if rad > rmax:
            break
        pts.append((cx + rad * math.cos(t), cy + rad * math.sin(t)))
    figuras = [("line", pts, c1, 2.5)]
    onda = [(x, H - 30 + 8 * math.sin(x / 90)) for x in range(-10, W + 11, 14)]
    figuras.append(("line", onda, c2, 2.5))
    for _ in range(r.randint(3, 6)):
        figuras.append(("circle", r.uniform(0, W), r.uniform(0, H), 2.5, c2))
    return figuras


MOTIVOS = {
    "amanecer": motivo_amanecer, "ciudad": motivo_ciudad, "bosque": motivo_bosque,
    "ondas": motivo_ondas, "geometria": motivo_geometria, "arcos": motivo_arcos,
    "ornamento": motivo_ornamento, "columnas": motivo_columnas, "luna": motivo_luna,
    "espiral": motivo_espiral,
}


def figuras_variante(mov, i):
    """Devuelve las figuras y los colores de la variante i de un movimiento."""
    r = _rng(mov["slug"], i)
    fondo = r.choice(["papel", "papel", "papel", "tinta", "tinta"])
    acentos = ["tinta", "verde", "ocre", "niebla"] if fondo == "papel" else ["papel", "ocre", "niebla"]
    c1, c2 = r.sample(acentos, 2)
    figuras = [("rect", 0, 0, W, H, fondo)]
    figuras += MOTIVOS[mov["motivo"]](r, c1, c2, fondo)
    return figuras, {"fondo": fondo, "acentos": [c1, c2]}


def _pts(pts):
    return " ".join(f"{x:.1f},{y:.1f}" for x, y in pts)


def _svg_figura(f):
    k = f[0]
    if k == "rect":
        _, x, y, w, h, c = f
        return f'<rect x="{x:.1f}" y="{y:.1f}" width="{w:.1f}" height="{h:.1f}" fill="{PALETA[c]}"/>'
    if k == "circle":
        _, cx, cy, rad, c = f
        return f'<circle cx="{cx:.1f}" cy="{cy:.1f}" r="{rad:.1f}" fill="{PALETA[c]}"/>'
    if k == "poly":
        _, pts, c = f
        return f'<polygon points="{_pts(pts)}" fill="{PALETA[c]}"/>'
    if k == "line":
        _, pts, c, grosor = f
        return (f'<polyline points="{_pts(pts)}" fill="none" stroke="{PALETA[c]}" '
                f'stroke-width="{grosor:.1f}" stroke-linecap="round" stroke-linejoin="round"/>')
    if k == "ring":
        _, cx, cy, rad, c, grosor = f
        return (f'<circle cx="{cx:.1f}" cy="{cy:.1f}" r="{rad:.1f}" fill="none" '
                f'stroke="{PALETA[c]}" stroke-width="{grosor:.1f}"/>')
    raise ValueError(f"figura desconocida: {k}")


def svg_marcalibro(figuras, titulo):
    cuerpo = "\n  ".join(_svg_figura(f) for f in figuras)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">\n'
            f'  <title>{html.escape(titulo)}</title>\n  {cuerpo}\n</svg>\n')


def jpg_marcalibro(figuras, ruta):
    s = SUPERSAMPLE
    img = Image.new("RGB", (W * s, H * s), RGB["papel"])
    d = ImageDraw.Draw(img)
    for f in figuras:
        k = f[0]
        if k == "rect":
            _, x, y, w, h, c = f
            d.rectangle([x * s, y * s, (x + w) * s - 1, (y + h) * s - 1], fill=RGB[c])
        elif k == "circle":
            _, cx, cy, rad, c = f
            d.ellipse([(cx - rad) * s, (cy - rad) * s, (cx + rad) * s, (cy + rad) * s], fill=RGB[c])
        elif k == "poly":
            _, pts, c = f
            d.polygon([(x * s, y * s) for x, y in pts], fill=RGB[c])
        elif k == "line":
            _, pts, c, grosor = f
            d.line([(x * s, y * s) for x, y in pts], fill=RGB[c],
                   width=max(1, int(round(grosor * s))), joint="curve")
        elif k == "ring":
            _, cx, cy, rad, c, grosor = f
            d.ellipse([(cx - rad) * s, (cy - rad) * s, (cx + rad) * s, (cy + rad) * s],
                      outline=RGB[c], width=max(1, int(round(grosor * s))))
    img = img.resize((W, H), Image.Resampling.LANCZOS)
    img.save(ruta, "JPEG", quality=CALIDAD_JPG, optimize=True)


def generar_marcalibros(raiz, por_movimiento):
    carpeta_svg = raiz / "catalog" / "svg"
    carpeta_prev = raiz / "catalog" / "preview"
    carpeta_svg.mkdir(parents=True, exist_ok=True)
    carpeta_prev.mkdir(parents=True, exist_ok=True)

    entradas = []
    for mov in MOVIMIENTOS:
        for i in range(1, por_movimiento + 1):
            id_ = f"mb-{mov['slug']}-{i:02d}"
            figuras, colores = figuras_variante(mov, i)
            svg = svg_marcalibro(figuras, f"Marcalibro {mov['nombre']} {i:02d}")
            (carpeta_svg / f"{id_}.svg").write_text(svg, encoding="utf-8")
            jpg_marcalibro(figuras, carpeta_prev / f"{id_}.jpg")
            entradas.append({
                "id": id_,
                "movimiento": mov["nombre"],
                "motivo": mov["motivo"],
                "fondo": PALETA[colores["fondo"]],
                "colores": [PALETA[c] for c in colores["acentos"]],
                "autores_inspiracion": mov["autores"],
                "svg": f"catalog/svg/{id_}.svg",
                "vista_previa": f"catalog/preview/{id_}.jpg",
            })

    manifest = {
        "total": len(entradas),
        "tamano": {"ancho": W, "alto": H},
        "marcalibros": entradas,
    }
    (raiz / "catalog" / "manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return len(entradas)


def _perfil(r, n):
    """Curva del horizonte en [-1, 1], compuesta por ondas de distinta frecuencia."""
    coef = [(r.uniform(0.3, 1.0) / (k + 1), k + 1 + r.uniform(0, 1.5), r.uniform(0, 2 * math.pi))
            for k in range(4)]
    u = np.linspace(0, 1, n)
    s = sum(a * np.sin(2 * math.pi * f * u + p) for a, f, p in coef)
    return s / sum(a for a, _, _ in coef)


def paisaje(ancho, alto, escena):
    r = _rng("paisaje", escena["slug"])
    yy = np.arange(alto)[:, None]
    xx = np.arange(ancho)[None, :]

    t = np.linspace(0, 1, alto)[:, None, None]
    arriba = np.array(RGB[escena["cielo"][0]], float)
    horizonte = np.array(RGB[escena["cielo"][1]], float)
    img = (arriba * (1 - t) + horizonte * t) * np.ones((1, ancho, 1))

    if escena.get("sol"):
        cx, cy, rad = 0.72 * ancho, 0.52 * alto, 0.09 * alto
        img[(xx - cx) ** 2 + (yy - cy) ** 2 <= rad ** 2] = RGB[escena["sol"]]

    capas = escena["capas"]
    n = len(capas)
    for i, nombre in enumerate(capas):
        base = 0.55 + 0.28 * i / max(n - 1, 1)     # las capas cercanas quedan más abajo
        s = _perfil(r, ancho)
        if escena["montanas"]:
            perfil = base * alto - alto * 0.35 * np.abs(s)
        else:
            perfil = base * alto - alto * 0.12 * s
        mezcla = 0.35 * (1 - i / max(n - 1, 1))    # las capas lejanas se funden con el horizonte
        color = np.array(RGB[nombre], float) * (1 - mezcla) + horizonte * mezcla
        img[yy >= perfil[None, :]] = color
    return img


def generar_paisajes(raiz):
    carpeta = raiz / "images"
    carpeta.mkdir(parents=True, exist_ok=True)
    generados = []
    for escena in ESCENAS:
        for ancho, alto in TAMANOS_PAISAJE:
            ruta = carpeta / f"paisaje-{escena['slug']}-{ancho}x{alto}.jpg"
            img = paisaje(ancho, alto, escena)
            Image.fromarray(np.clip(img, 0, 255).astype(np.uint8)).save(
                ruta, "JPEG", quality=CALIDAD_JPG, optimize=True)
            generados.append(ruta.name)
    return generados


def main():
    ap = argparse.ArgumentParser(description="Genera marcalibros y paisajes de la marca.")
    ap.add_argument("--por-movimiento", type=int, default=10,
                    help="variantes por movimiento (10 da 100 marcalibros; 100 da 1000)")
    args = ap.parse_args()

    raiz = Path(__file__).resolve().parent.parent
    total = generar_marcalibros(raiz, args.por_movimiento)
    paisajes = generar_paisajes(raiz)
    print(f"Marcalibros generados: {total}")
    print(f"Paisajes generados: {len(paisajes)}")


if __name__ == "__main__":
    main()
