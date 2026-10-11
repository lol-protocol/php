#!/usr/bin/env python3
"""Genera el catálogo de marcalibros y los paisajes JPG de la marca.

Cada marcalibro se define como una lista de figuras (ver `motivos.py`). Esa
misma lista se escribe como SVG y se rasteriza como vista previa JPG, así
ambas versiones siempre coinciden.

Uso:
    python3 generator/generar_marcalibros.py                        # 100 marcalibros
    python3 generator/generar_marcalibros.py --por-movimiento 100   # 1000 marcalibros

El script termina con error si algún SVG pesa más de 10 KB.
"""
import argparse
import html
import json
import math
import random
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw

from motivos import MOTIVOS, H, W

SUPERSAMPLE = 2           # se rasteriza al doble y se reduce para suavizar bordes
CALIDAD_JPG = 85
LIMITE_SVG = 10 * 1024    # bytes

PALETA = {
    "tinta": "#1E2A38",
    "papel": "#F4EEE1",
    "ocre": "#C98B3A",
    "verde": "#3E5C4A",
    "niebla": "#7A9CB0",
}
RGB = {k: tuple(int(v[i:i + 2], 16) for i in (1, 3, 5)) for k, v in PALETA.items()}

MOVIMIENTOS = [
    {"slug": "romanticismo", "nombre": "Romanticismo",
     "motivos": ["amanecer", "arbol_solitario", "nubes", "acantilado_mar"],
     "autores": ["Lord Byron", "John Keats", "Gustavo Adolfo Bécquer"]},
    {"slug": "realismo", "nombre": "Realismo",
     "motivos": ["ciudad", "puente", "farolas", "chimeneas"],
     "autores": ["Gustave Flaubert", "Honoré de Balzac", "Benito Pérez Galdós"]},
    {"slug": "naturalismo", "nombre": "Naturalismo",
     "motivos": ["bosque", "juncos", "trigo", "rio"],
     "autores": ["Émile Zola", "Guy de Maupassant", "Horacio Quiroga"]},
    {"slug": "modernismo", "nombre": "Modernismo",
     "motivos": ["ondas", "ondas_concentricas", "nouveau", "mosaico"],
     "autores": ["Rubén Darío", "Virginia Woolf", "James Joyce"]},
    {"slug": "vanguardias", "nombre": "Vanguardias",
     "motivos": ["geometria", "constructivista", "caligrama", "collage"],
     "autores": ["Guillaume Apollinaire", "Vicente Huidobro", "Tristan Tzara"]},
    {"slug": "gotico", "nombre": "Gótico",
     "motivos": ["arcos_ojivales", "roseton", "torres", "cementerio"],
     "autores": ["Mary Shelley", "Edgar Allan Poe", "Bram Stoker"]},
    {"slug": "barroco", "nombre": "Barroco",
     "motivos": ["ornamento", "volutas", "drapeado", "perlas"],
     "autores": ["Luis de Góngora", "Francisco de Quevedo", "Pedro Calderón de la Barca"]},
    {"slug": "clasicismo", "nombre": "Clasicismo",
     "motivos": ["columnas", "fronton", "laurel", "meandro"],
     "autores": ["Jean Racine", "Molière", "Pierre Corneille"]},
    {"slug": "simbolismo", "nombre": "Simbolismo",
     "motivos": ["luna", "reflejo_lunar", "constelaciones", "lirio"],
     "autores": ["Stéphane Mallarmé", "Paul Verlaine", "Arthur Rimbaud"]},
    {"slug": "realismo-magico", "nombre": "Realismo mágico",
     "motivos": ["espiral", "mariposas", "flores_cayendo", "selva"],
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


# ------------------------------------------------------ agrupar y escribir

def compactar(figuras):
    """Une las figuras vecinas del mismo tipo y color para que el SVG pese menos.

    Solo se unen figuras contiguas, así el orden de apilado no cambia.
    """
    tipos = {"rect": "rects", "circle": "dots", "poly": "polys", "line": "lines", "ring": "rings"}
    grupos = []
    for g in figuras:
        k = g[0]
        if k == "rect":
            item, color, grosor = tuple(g[1:5]), g[5], None
        elif k == "circle":
            item, color, grosor = tuple(g[1:4]), g[4], None
        elif k == "poly":
            item, color, grosor = g[1], g[2], None
        elif k == "line":
            item, color, grosor = g[1], g[2], g[3]
        elif k == "ring":
            item, color, grosor = tuple(g[1:4]), g[4], g[5]
        else:
            raise ValueError(f"figura desconocida: {k}")
        tipo = tipos[k]
        if grupos and grupos[-1][0] == tipo and grupos[-1][2] == color and grupos[-1][3] == grosor:
            grupos[-1][1].append(item)
        else:
            grupos.append((tipo, [item], color, grosor))
    return grupos


def _n(v):
    s = f"{v:.1f}"
    if s.endswith(".0"):
        s = s[:-2]
    return "0" if s == "-0" else s


def _pts(pts):
    return " ".join(f"{_n(x)} {_n(y)}" for x, y in pts)


def _d_circulo(cx, cy, rad):
    return (f"M{_n(cx - rad)} {_n(cy)}a{_n(rad)} {_n(rad)} 0 1 0 {_n(2 * rad)} 0"
            f"a{_n(rad)} {_n(rad)} 0 1 0 {_n(-2 * rad)} 0z")


def _svg_grupo(g):
    tipo, items, color, grosor = g
    col = PALETA[color]
    trazo = (f'fill="none" stroke="{col}" stroke-width="{_n(grosor)}" '
             f'stroke-linecap="round" stroke-linejoin="round"') if grosor else f'fill="{col}"'
    if tipo == "rects":
        d = "".join(f"M{_n(x)} {_n(y)}h{_n(w)}v{_n(h)}h{_n(-w)}z" for x, y, w, h in items)
    elif tipo == "dots" or tipo == "rings":
        d = "".join(_d_circulo(*c) for c in items)
    elif tipo == "polys":
        d = "".join("M" + _pts(p) + "z" for p in items)
    else:  # lines
        d = "".join("M" + _pts(p) for p in items)
    return f'<path {trazo} d="{d}"/>'


def svg_marcalibro(grupos, titulo):
    cuerpo = "\n".join(_svg_grupo(g) for g in grupos)
    return (f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {W} {H}" width="{W}" height="{H}">\n'
            f'<title>{html.escape(titulo)}</title>\n{cuerpo}\n</svg>\n')


def jpg_marcalibro(grupos, ruta):
    s = SUPERSAMPLE
    img = Image.new("RGB", (W * s, H * s), RGB["papel"])
    d = ImageDraw.Draw(img)
    for tipo, items, color, grosor in grupos:
        col = RGB[color]
        ancho = max(1, int(round(grosor * s))) if grosor else 0
        if tipo == "rects":
            for x, y, w, h in items:
                d.rectangle([x * s, y * s, (x + w) * s - 1, (y + h) * s - 1], fill=col)
        elif tipo == "dots":
            for cx, cy, rad in items:
                d.ellipse([(cx - rad) * s, (cy - rad) * s, (cx + rad) * s, (cy + rad) * s], fill=col)
        elif tipo == "rings":
            for cx, cy, rad in items:
                # En SVG el trazo se centra sobre el radio; PIL lo dibuja hacia adentro del borde exterior.
                ro = rad + grosor / 2
                d.ellipse([(cx - ro) * s, (cy - ro) * s, (cx + ro) * s, (cy + ro) * s],
                          outline=col, width=ancho)
        elif tipo == "polys":
            for pts in items:
                d.polygon([(x * s, y * s) for x, y in pts], fill=col)
        else:  # lines
            for pts in items:
                d.line([(x * s, y * s) for x, y in pts], fill=col, width=ancho, joint="curve")
                for x, y in (pts[0], pts[-1]):   # extremos redondeados, como en el SVG
                    rr = grosor * s / 2
                    d.ellipse([x * s - rr, y * s - rr, x * s + rr, y * s + rr], fill=col)
    img = img.resize((W, H), Image.Resampling.LANCZOS)
    img.save(ruta, "JPEG", quality=CALIDAD_JPG, optimize=True)


def figuras_variante(mov, i):
    """Devuelve las figuras agrupadas y los datos de la variante i de un movimiento."""
    r = _rng(mov["slug"], i)
    motivo = mov["motivos"][(i - 1) % len(mov["motivos"])]
    fondo = r.choice(["papel", "papel", "papel", "tinta", "tinta"])
    # El color principal siempre contrasta bien con el fondo (razón mayor a 5:1).
    if fondo == "papel":
        c1 = r.choice(["tinta", "verde"])
        c2 = r.choice([c for c in ("ocre", "niebla", "verde", "tinta") if c != c1])
    else:
        c1 = r.choice(["papel", "ocre"])
        c2 = r.choice([c for c in ("niebla", "ocre", "papel") if c != c1])
    figuras = [("rect", 0, 0, W, H, fondo)] + MOTIVOS[motivo](r, c1, c2, fondo)
    return compactar(figuras), {"fondo": fondo, "acentos": [c1, c2], "motivo": motivo}


def generar_marcalibros(raiz, por_movimiento):
    carpeta_svg = raiz / "catalog" / "svg"
    carpeta_prev = raiz / "catalog" / "preview"
    carpeta_svg.mkdir(parents=True, exist_ok=True)
    carpeta_prev.mkdir(parents=True, exist_ok=True)

    entradas, pesos, excedidos = [], [], []
    for mov in MOVIMIENTOS:
        for i in range(1, por_movimiento + 1):
            id_ = f"mb-{mov['slug']}-{i:02d}"
            grupos, datos = figuras_variante(mov, i)
            svg = svg_marcalibro(grupos, f"Marcalibro {mov['nombre']} {i:02d}")
            (carpeta_svg / f"{id_}.svg").write_text(svg, encoding="utf-8")
            jpg_marcalibro(grupos, carpeta_prev / f"{id_}.jpg")
            peso = len(svg.encode("utf-8"))
            pesos.append(peso)
            if peso > LIMITE_SVG:
                excedidos.append((id_, peso))
            entradas.append({
                "id": id_,
                "movimiento": mov["nombre"],
                "motivo": datos["motivo"],
                "fondo": PALETA[datos["fondo"]],
                "colores": [PALETA[c] for c in datos["acentos"]],
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
    return len(entradas), pesos, excedidos


# ------------------------------------------------------------------ paisajes

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
    total, pesos, excedidos = generar_marcalibros(raiz, args.por_movimiento)
    paisajes = generar_paisajes(raiz)
    print(f"Marcalibros generados: {total}")
    print(f"Paisajes generados: {len(paisajes)}")
    print(f"Peso de los SVG: máximo {max(pesos) / 1024:.1f} KB, mediana {sorted(pesos)[len(pesos) // 2] / 1024:.1f} KB "
          f"(límite {LIMITE_SVG // 1024} KB)")
    if excedidos:
        print(f"ERROR: {len(excedidos)} SVG superan el límite:", file=sys.stderr)
        for id_, peso in excedidos[:15]:
            print(f"  {id_}: {peso / 1024:.1f} KB", file=sys.stderr)
        sys.exit(1)


if __name__ == "__main__":
    main()
