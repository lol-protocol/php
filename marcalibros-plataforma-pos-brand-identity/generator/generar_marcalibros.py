#!/usr/bin/env python3
"""Genera el catálogo de marcalibros y los paisajes JPG de la marca.

Cada marcalibro se define como una lista de figuras (ver `motivos.py`). Esa
misma lista se escribe como SVG y se rasteriza como vista previa JPG, así
ambas versiones siempre coinciden.

Hay 27 categorías: 11 movimientos literarios y 16 tipos de texto. Cada una tiene
4 motivos visuales y se reparte en variantes (50 por categoría dan 1350
marcalibros). Cada variante depende solo de su categoría y su número, así que
agregar categorías no cambia las que ya existen.

Uso:
    python3 generator/generar_marcalibros.py                      # 1350 marcalibros
    python3 generator/generar_marcalibros.py --por-categoria 10   # 270 marcalibros

Las versiones PNG no se guardan en el repositorio: se generan con
`generator/exportar_png.py`.

El script termina con error si algún SVG pesa más de 10 KB o si dos marcalibros
salen idénticos.
"""
import argparse
import hashlib
import html
import json
import math
import random
import sys
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw

from motivos import MOTIVOS as MOTIVOS_LITERARIOS, H, W, espejar
from motivos_ampliacion import MOTIVOS_AMPLIACION
from motivos_texto import MOTIVOS_TEXTO

_todos = [MOTIVOS_LITERARIOS, MOTIVOS_TEXTO, MOTIVOS_AMPLIACION]
assert sum(map(len, _todos)) == len(set().union(*_todos)), "un motivo está definido dos veces"
MOTIVOS = {**MOTIVOS_LITERARIOS, **MOTIVOS_TEXTO, **MOTIVOS_AMPLIACION}

SUPERSAMPLE = 4           # se rasteriza al cuádruple del tamaño de la vista previa y se reduce
VISTA_PREVIA = (700, 100)  # la mitad del lienzo: basta para galerías y pesa unos 11 KB
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

MOVIMIENTO, TIPO_TEXTO = "movimiento literario", "tipo de texto"

CATEGORIAS = [
    # Movimientos literarios: los autores se guardan solo como metadato de inspiración.
    {"slug": "romanticismo", "nombre": "Romanticismo", "familia": MOVIMIENTO,
     "motivos": ["amanecer", "arbol_solitario", "nubes", "acantilado_mar"],
     "autores": ["Lord Byron", "John Keats", "Gustavo Adolfo Bécquer"]},
    {"slug": "realismo", "nombre": "Realismo", "familia": MOVIMIENTO,
     "motivos": ["ciudad", "puente", "farolas", "chimeneas"],
     "autores": ["Gustave Flaubert", "Honoré de Balzac", "Benito Pérez Galdós"]},
    {"slug": "naturalismo", "nombre": "Naturalismo", "familia": MOVIMIENTO,
     "motivos": ["bosque", "juncos", "trigo", "rio"],
     "autores": ["Émile Zola", "Guy de Maupassant", "Horacio Quiroga"]},
    # El slug "modernismo" se conserva para que los enlaces de antes sigan sirviendo.
    {"slug": "modernismo", "nombre": "Modernismo hispanoamericano", "familia": MOVIMIENTO,
     "motivos": ["ondas", "ondas_concentricas", "nouveau", "mosaico"],
     "autores": ["Rubén Darío", "José Martí", "Leopoldo Lugones"]},
    {"slug": "modernismo-anglosajon", "nombre": "Modernismo anglosajón", "familia": MOVIMIENTO,
     "motivos": ["faro", "relojes", "tierra_baldia", "fragmentos"],
     "autores": ["Virginia Woolf", "James Joyce", "T. S. Eliot"]},
    {"slug": "vanguardias", "nombre": "Vanguardias", "familia": MOVIMIENTO,
     "motivos": ["geometria", "constructivista", "caligrama", "collage"],
     "autores": ["Guillaume Apollinaire", "Vicente Huidobro", "Tristan Tzara"]},
    {"slug": "gotico", "nombre": "Gótico", "familia": MOVIMIENTO,
     "motivos": ["arcos_ojivales", "roseton", "torres", "cementerio"],
     "autores": ["Mary Shelley", "Edgar Allan Poe", "Bram Stoker"]},
    {"slug": "barroco", "nombre": "Barroco", "familia": MOVIMIENTO,
     "motivos": ["ornamento", "volutas", "drapeado", "perlas"],
     "autores": ["Luis de Góngora", "Francisco de Quevedo", "Pedro Calderón de la Barca"]},
    {"slug": "clasicismo", "nombre": "Clasicismo", "familia": MOVIMIENTO,
     "motivos": ["columnas", "fronton", "laurel", "meandro"],
     "autores": ["Jean Racine", "Molière", "Pierre Corneille"]},
    {"slug": "simbolismo", "nombre": "Simbolismo", "familia": MOVIMIENTO,
     "motivos": ["luna", "reflejo_lunar", "constelaciones", "lirio"],
     "autores": ["Stéphane Mallarmé", "Paul Verlaine", "Arthur Rimbaud"]},
    {"slug": "realismo-magico", "nombre": "Realismo mágico", "familia": MOVIMIENTO,
     "motivos": ["espiral", "mariposas", "flores_cayendo", "selva"],
     "autores": ["Gabriel García Márquez", "Juan Rulfo", "Alejo Carpentier"]},
    # Tipos de texto: no llevan autores, solo el motivo que evoca ese tipo de escrito.
    {"slug": "manuales", "nombre": "Manuales", "familia": TIPO_TEXTO,
     "motivos": ["engranajes", "plano_tecnico", "regla", "circuito"]},
    {"slug": "libros-sagrados", "nombre": "Libros sagrados", "familia": TIPO_TEXTO,
     "motivos": ["rayos_de_luz", "mandala", "rollos", "velas"]},
    {"slug": "leyes", "nombre": "Leyes y reglamentos", "familia": TIPO_TEXTO,
     "motivos": ["balanza", "sellos", "articulos", "mazo"]},
    {"slug": "cartas", "nombre": "Cartas y epistolares", "familia": TIPO_TEXTO,
     "motivos": ["sobres", "estampillas", "lacre", "plumas"]},
    {"slug": "diarios", "nombre": "Diarios y memorias", "familia": TIPO_TEXTO,
     "motivos": ["calendario", "fases_lunares", "ventana_lluvia", "herbario"]},
    {"slug": "ciencia", "nombre": "Tratados científicos", "familia": TIPO_TEXTO,
     "motivos": ["orbitas", "hexagonos", "grafica_datos", "arbol_filogenetico"]},
    {"slug": "cocina", "nombre": "Recetas y cocina", "familia": TIPO_TEXTO,
     "motivos": ["especias", "platos", "frutas", "ollas_y_vapor"]},
    {"slug": "filosofia", "nombre": "Filosofía y ensayo", "familia": TIPO_TEXTO,
     "motivos": ["laberinto", "esfera_armilar", "puertas", "red_de_ideas"]},
    {"slug": "teatro", "nombre": "Teatro", "familia": TIPO_TEXTO,
     "motivos": ["cortinas", "mascaras", "focos", "platea"]},
    {"slug": "viajes", "nombre": "Viajes y crónicas", "familia": TIPO_TEXTO,
     "motivos": ["brujula", "mapa_y_ruta", "veleros", "cordillera"]},
    {"slug": "cuentos", "nombre": "Cuentos y leyendas", "familia": TIPO_TEXTO,
     "motivos": ["castillo", "hongos", "llaves", "coronas"]},
    {"slug": "poesia", "nombre": "Poesía", "familia": TIPO_TEXTO,
     "motivos": ["versos", "pajaros_en_cable", "petalos", "lira"]},
    {"slug": "historia", "nombre": "Historia", "familia": TIPO_TEXTO,
     "motivos": ["linea_de_tiempo", "piramides", "monedas", "reloj_de_arena"]},
    {"slug": "periodismo", "nombre": "Periodismo", "familia": TIPO_TEXTO,
     "motivos": ["columnas_de_periodico", "titulares", "microfonos", "teclas"]},
    {"slug": "diccionarios", "nombre": "Diccionarios y enciclopedias", "familia": TIPO_TEXTO,
     "motivos": ["lomos_de_libros", "pestanas_indice", "lupas", "fichero"]},
    {"slug": "discursos", "nombre": "Discursos", "familia": TIPO_TEXTO,
     "motivos": ["atril", "ondas_de_voz", "multitud", "megafonos"]},
]

# Motivos con sentido de lectura (texto, letra manuscrita, sellos en una esquina): no se reflejan.
SIN_ESPEJO = {"plumas", "articulos", "sobres", "rollos",
              "versos", "linea_de_tiempo", "columnas_de_periodico", "titulares", "pestanas_indice", "lupas"}

# Nombre de cada motivo tal como se muestra a las personas.
NOMBRES_MOTIVO = {
    "amanecer": "Amanecer", "arbol_solitario": "Árbol solitario", "nubes": "Nubes", "acantilado_mar": "Acantilado y mar",
    "ciudad": "Ciudad", "puente": "Puente", "farolas": "Farolas", "chimeneas": "Chimeneas",
    "bosque": "Bosque", "juncos": "Juncos", "trigo": "Trigo", "rio": "Río",
    "ondas": "Ondas", "ondas_concentricas": "Ondas concéntricas", "nouveau": "Art nouveau", "mosaico": "Mosaico",
    "geometria": "Geometría", "constructivista": "Constructivista", "caligrama": "Caligrama", "collage": "Collage",
    "arcos_ojivales": "Arcos ojivales", "roseton": "Rosetón", "torres": "Torres", "cementerio": "Cementerio",
    "ornamento": "Ornamento", "volutas": "Volutas", "drapeado": "Drapeado", "perlas": "Perlas",
    "columnas": "Columnas", "fronton": "Frontón", "laurel": "Laurel", "meandro": "Meandro",
    "luna": "Luna", "reflejo_lunar": "Reflejo lunar", "constelaciones": "Constelaciones", "lirio": "Lirio",
    "espiral": "Espiral", "mariposas": "Mariposas", "flores_cayendo": "Flores cayendo", "selva": "Selva",
    "engranajes": "Engranajes", "plano_tecnico": "Plano técnico", "regla": "Regla", "circuito": "Circuito",
    "rayos_de_luz": "Rayos de luz", "mandala": "Mandala", "rollos": "Rollos", "velas": "Velas",
    "balanza": "Balanza", "sellos": "Sellos", "articulos": "Artículos", "mazo": "Mazo",
    "sobres": "Sobres", "estampillas": "Estampillas", "lacre": "Lacre", "plumas": "Plumas",
    "calendario": "Calendario", "fases_lunares": "Fases lunares", "ventana_lluvia": "Ventana con lluvia", "herbario": "Herbario",
    "orbitas": "Órbitas", "hexagonos": "Hexágonos", "grafica_datos": "Gráfica de datos", "arbol_filogenetico": "Árbol filogenético",
    "especias": "Especias", "platos": "Platos", "frutas": "Frutas", "ollas_y_vapor": "Ollas y vapor",
    "laberinto": "Laberinto", "esfera_armilar": "Esfera armilar", "puertas": "Puertas", "red_de_ideas": "Red de ideas",
    "cortinas": "Cortinas", "mascaras": "Máscaras", "focos": "Focos", "platea": "Platea",
    "brujula": "Brújula", "mapa_y_ruta": "Mapa y ruta", "veleros": "Veleros", "cordillera": "Cordillera",
    "faro": "Faro", "relojes": "Relojes", "tierra_baldia": "Tierra baldía", "fragmentos": "Fragmentos",
    "castillo": "Castillo", "hongos": "Hongos", "llaves": "Llaves", "coronas": "Coronas",
    "versos": "Versos", "pajaros_en_cable": "Pájaros en el cable", "petalos": "Pétalos", "lira": "Lira",
    "linea_de_tiempo": "Línea de tiempo", "piramides": "Pirámides", "monedas": "Monedas", "reloj_de_arena": "Reloj de arena",
    "columnas_de_periodico": "Columnas de periódico", "titulares": "Titulares", "microfonos": "Micrófonos", "teclas": "Teclas",
    "lomos_de_libros": "Lomos de libros", "pestanas_indice": "Pestañas de índice", "lupas": "Lupas", "fichero": "Fichero",
    "atril": "Atril", "ondas_de_voz": "Ondas de voz", "multitud": "Multitud", "megafonos": "Megáfonos",
}
assert set(NOMBRES_MOTIVO) == set(MOTIVOS), "cada motivo necesita su nombre (y solo los motivos existentes)"
assert all(m in MOTIVOS for c in CATEGORIAS for m in c["motivos"]), "una categoría usa un motivo que no existe"

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


def rasterizar(grupos, ancho_final, supersample=SUPERSAMPLE):
    """Dibuja las figuras en una imagen de `ancho_final` px (alto proporcional, 7:1).

    Se dibuja a `supersample` veces el tamaño final y se reduce, para suavizar los bordes.
    """
    s = supersample * ancho_final / W          # escala de dibujo respecto del lienzo de 1400 x 200
    img = Image.new("RGB", (round(W * s), round(H * s)), RGB["papel"])
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
    return img.resize((ancho_final, round(ancho_final * H / W)), Image.Resampling.LANCZOS)


def jpg_marcalibro(grupos, ruta):
    rasterizar(grupos, VISTA_PREVIA[0]).save(ruta, "JPEG", quality=CALIDAD_JPG, optimize=True)


def figuras_variante(cat, i):
    """Devuelve las figuras agrupadas y los datos de la variante i de una categoría."""
    r = _rng(cat["slug"], i)
    motivo = cat["motivos"][(i - 1) % len(cat["motivos"])]
    fondo = r.choice(["papel", "papel", "papel", "tinta", "tinta"])
    # El color principal siempre contrasta bien con el fondo (razón mayor a 5:1).
    if fondo == "papel":
        c1 = r.choice(["tinta", "verde"])
        c2 = r.choice([c for c in ("ocre", "niebla", "verde", "tinta") if c != c1])
    else:
        c1 = r.choice(["papel", "ocre"])
        c2 = r.choice([c for c in ("niebla", "ocre", "papel") if c != c1])
    figuras = MOTIVOS[motivo](r, c1, c2, fondo)
    espejado = motivo not in SIN_ESPEJO and _rng(cat["slug"], i, "espejo").random() < 0.5
    if espejado:
        figuras = espejar(figuras)
    figuras = [("rect", 0, 0, W, H, fondo)] + figuras
    return compactar(figuras), {"fondo": fondo, "acentos": [c1, c2], "motivo": motivo}


def generar_marcalibros(raiz, por_categoria):
    """Escribe SVG, vista previa y manifiesto. Devuelve (total, pesos, excedidos, repetidos)."""
    carpeta_svg = raiz / "catalog" / "svg"
    carpeta_prev = raiz / "catalog" / "preview"
    carpeta_svg.mkdir(parents=True, exist_ok=True)
    carpeta_prev.mkdir(parents=True, exist_ok=True)
    for carpeta, ext in ((carpeta_svg, "*.svg"), (carpeta_prev, "*.jpg")):   # sin restos de ejecuciones anteriores
        for viejo in carpeta.glob(ext):
            viejo.unlink()

    digitos = max(2, len(str(por_categoria)))
    entradas, pesos, excedidos, vistos, repetidos = [], [], [], {}, []
    for cat in CATEGORIAS:
        for i in range(1, por_categoria + 1):
            id_ = f"mb-{cat['slug']}-{i:0{digitos}d}"
            grupos, datos = figuras_variante(cat, i)
            svg = svg_marcalibro(grupos, f"Marcalibro {cat['nombre']} {i:0{digitos}d}")
            huella = hashlib.sha256(svg.split("</title>", 1)[1].encode("utf-8")).hexdigest()
            if huella in vistos:
                repetidos.append((id_, vistos[huella]))
            vistos[huella] = id_
            (carpeta_svg / f"{id_}.svg").write_text(svg, encoding="utf-8")
            jpg_marcalibro(grupos, carpeta_prev / f"{id_}.jpg")
            peso = len(svg.encode("utf-8"))
            pesos.append(peso)
            if peso > LIMITE_SVG:
                excedidos.append((id_, peso))
            entrada = {
                "id": id_,
                "categoria": cat["nombre"],
                "categoria_slug": cat["slug"],
                "familia": cat["familia"],
                "motivo": datos["motivo"],
                "fondo": PALETA[datos["fondo"]],
                "colores": [PALETA[c] for c in datos["acentos"]],
            }
            if "autores" in cat:
                entrada["autores_inspiracion"] = cat["autores"]
            entrada["svg"] = f"catalog/svg/{id_}.svg"
            entrada["vista_previa"] = f"catalog/preview/{id_}.jpg"
            entradas.append(entrada)

    manifest = {
        "total": len(entradas),
        "tamano": {"ancho": W, "alto": H},
        "vista_previa": {"ancho": VISTA_PREVIA[0], "alto": VISTA_PREVIA[1]},
        "motivos": NOMBRES_MOTIVO,
        "categorias": [{"slug": c["slug"], "nombre": c["nombre"], "familia": c["familia"],
                        "motivos": c["motivos"], "total": por_categoria} for c in CATEGORIAS],
        "marcalibros": entradas,
    }
    (raiz / "catalog" / "manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return len(entradas), pesos, excedidos, repetidos


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
    ap.add_argument("--por-categoria", type=int, default=50,
                    help=f"variantes por categoría (hay {len(CATEGORIAS)}: 50 da {50 * len(CATEGORIAS)} marcalibros)")
    ap.add_argument("--salida", type=Path,
                    help="carpeta donde escribir catalog/ e images/ (por defecto, la del proyecto: reescribe lo versionado)")
    args = ap.parse_args()
    if args.por_categoria < 1:
        ap.error("--por-categoria debe ser 1 o más")

    raiz = args.salida or Path(__file__).resolve().parent.parent
    total, pesos, excedidos, repetidos = generar_marcalibros(raiz, args.por_categoria)
    paisajes = generar_paisajes(raiz)
    print(f"Marcalibros generados: {total} ({len(CATEGORIAS)} categorías x {args.por_categoria})")
    print(f"Paisajes generados: {len(paisajes)}")
    print(f"Peso de los SVG: máximo {max(pesos) / 1024:.1f} KB, mediana {sorted(pesos)[len(pesos) // 2] / 1024:.1f} KB "
          f"(límite {LIMITE_SVG // 1024} KB)")
    error = False
    if excedidos:
        error = True
        print(f"ERROR: {len(excedidos)} SVG superan el límite:", file=sys.stderr)
        for id_, peso in excedidos[:15]:
            print(f"  {id_}: {peso / 1024:.1f} KB", file=sys.stderr)
    if repetidos:
        error = True
        print(f"ERROR: {len(repetidos)} marcalibros son idénticos a otro:", file=sys.stderr)
        for id_, otro in repetidos[:15]:
            print(f"  {id_} = {otro}", file=sys.stderr)
    if error:
        sys.exit(1)


if __name__ == "__main__":
    main()
