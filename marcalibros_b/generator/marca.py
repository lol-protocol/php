#!/usr/bin/env python3
"""Línea de marca de Plataforma POS y Marcalibros (marca/).

Símbolo de Marcalibros: una tira vertical 1:7 con un paisaje mínimo (las mismas paletas que
los marcalibros). Símbolo de Plataforma POS: un libro abierto con esa tira como cinta del
lomo. Todo sale de este script, determinista, sin dependencias nuevas (usa texto.py para el
texto vectorial).

    python generator/marca.py          # SVG en marca/
    python generator/marca.py --png    # además, PNG de los previews sociales (necesita Chromium)

Salida: marca/brand-tokens.yaml, logos/, iconos/, estados-vacios/, cabeceras/, previews/.
"""
import argparse
import math
import os
import random
import subprocess
import sys
import tempfile
from pathlib import Path

import yaml

sys.path.insert(0, str(Path(__file__).resolve().parent))
import texto as tx  # noqa: E402
from plantillas import PALETAS, contraste  # noqa: E402

RAIZ = Path(__file__).resolve().parent.parent
SALIDA = RAIZ / "marca"

TINTA, PAPEL, ACENTO = "#1E2A38", "#F4EEE1", "#C98B3A"
PALETA_DE = {
    "romanticismo": "atardecer", "gotico": "cripta", "realismo-magico": "tropico", "realismo": "ceniza",
    "naturalismo": "barro", "modernismo": "jade", "vanguardias": "primarios", "barroco": "carmesi",
    "clasicismo": "marmol", "simbolismo": "mistico",
}
MIN_ACENTO = 4.5  # contraste mínimo del acento sobre papel (texto de interfaz)


def _n(v):
    return f"{v:.1f}".rstrip("0").rstrip(".")


def _mezcla(a, b, t):
    ca = [int(a[i:i + 2], 16) for i in (1, 3, 5)]
    cb = [int(b[i:i + 2], 16) for i in (1, 3, 5)]
    return "#" + "".join(f"{round(x + (y - x) * t):02X}" for x, y in zip(ca, cb))


def acento_sobre_papel(pal):
    """Primer color de la paleta con contraste suficiente sobre papel; si ninguno, oscurece el de la cerca."""
    for clave in ("cerca", "lejos", "cielo_alto"):
        if contraste(pal[clave], PAPEL) >= MIN_ACENTO:
            return pal[clave]
    color = pal["cerca"]
    for k in range(1, 21):
        color = _mezcla(pal["cerca"], "#000000", k / 20)
        if contraste(color, PAPEL) >= MIN_ACENTO:
            return color
    return color


def tokens():
    movs = {}
    for mov, nombre in PALETA_DE.items():
        pal = PALETAS[nombre]
        ac = acento_sobre_papel(pal)
        movs[mov] = {"paleta": nombre, "acento": ac, "sobre_acento": "#FFFFFF" if contraste(ac, "#FFFFFF") >= contraste(ac, TINTA) else TINTA,
                     "cielo_alto": pal["cielo_alto"], "cielo_bajo": pal["cielo_bajo"]}
    return {
        "version": 1,
        "base": {"tinta": TINTA, "papel": PAPEL, "acento": ACENTO, "tinta_suave": _mezcla(TINTA, PAPEL, 0.35)},
        "tipografia": {"titulo": "Liberation Serif Italic (OFL)", "interfaz": "sistema (system-ui)"},
        "contraste_minimo_acento_sobre_papel": MIN_ACENTO,
        "movimientos": movs,
    }


# ---------------------------------------------------------------- piezas SVG

def _svg(w, h, cuerpo, titulo, vb=None):
    vb = vb or f"0 0 {w} {h}"
    return (f'<svg xmlns="http://www.w3.org/2000/svg" width="{w}" height="{h}" viewBox="{vb}" role="img">'
            f'<title>{titulo}</title>{cuerpo}</svg>\n')


def texto_svg(txt, x, y, tam, color, ancla="start"):
    ancho = sum(tx.avance(c) for c in txt) * tam / 100
    x0 = x - (ancho if ancla == "end" else ancho / 2 if ancla == "middle" else 0)
    k, cur, partes = tam / 100, 0, []
    for c in txt:
        if c != " ":
            partes.append(f'<path transform="translate({_n(cur)} 0)" d="{tx.trazo(c)}"/>')
        cur += tx.avance(c)
    return (f'<g transform="translate({_n(x0)} {_n(y)}) scale({k:.4f})" fill="{color}" fill-rule="evenodd">'
            + "".join(partes) + "</g>")


def _colinas(rng, x0, x1, base, amp, color, paso=12):
    f = [(rng.uniform(0.8, 1.6), rng.uniform(0, 6.28), rng.uniform(0.35, 1)) for _ in range(3)]
    pts = []
    x = x0
    while x <= x1 + 0.01:
        t = (x - x0) / max(1, x1 - x0)
        y = base - amp * sum(a * (0.5 + 0.5 * math.sin(2 * math.pi * fr * t + ph)) for fr, ph, a in f) / sum(a for *_, a in f)
        pts.append((x, y))
        x += paso
    return pts


def _poli(pts, y_fondo, color):
    d = "M" + "L".join(f"{_n(x)} {_n(y)}" for x, y in pts) + f"L{_n(pts[-1][0])} {_n(y_fondo)}L{_n(pts[0][0])} {_n(y_fondo)}Z"
    return f'<path d="{d}" fill="{color}"/>'


def _cipres(x, y, h, color):
    w = h * 0.16
    return f'<path d="M{_n(x)} {_n(y - h)}Q{_n(x + w)} {_n(y - h * 0.4)} {_n(x + w * 0.7)} {_n(y)}H{_n(x - w * 0.7)}Q{_n(x - w)} {_n(y - h * 0.4)} {_n(x)} {_n(y - h)}Z" fill="{color}"/>'


def paisaje(ident, x, y, w, h, pal, semilla, redondeo=0, sol_rel=(0.7, 0.28), arboles=1):
    """Paisaje mínimo (cielo, sol, dos capas de colinas y árboles) en el rectángulo dado."""
    rng = random.Random(semilla)
    p = PALETAS[pal]
    ref = min(w, h)
    cielo = (f'<linearGradient id="{ident}-c" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="{p["cielo_alto"]}"/>'
             f'<stop offset="1" stop-color="{p["cielo_bajo"]}"/></linearGradient>'
             f'<clipPath id="{ident}-k"><rect x="{_n(x)}" y="{_n(y)}" width="{_n(w)}" height="{_n(h)}" rx="{_n(redondeo)}"/></clipPath>')
    cuerpo = [f'<defs>{cielo}</defs><g clip-path="url(#{ident}-k)">',
              f'<rect x="{_n(x)}" y="{_n(y)}" width="{_n(w)}" height="{_n(h)}" fill="url(#{ident}-c)"/>',
              f'<circle cx="{_n(x + w * sol_rel[0])}" cy="{_n(y + h * sol_rel[1])}" r="{_n(ref * 0.14 if w < h else h * 0.09)}" fill="{p["sol"]}"/>']
    lejos = _colinas(rng, x, x + w, y + h * 0.66, h * 0.16, p["lejos"], paso=max(4, w / 24))
    cerca = _colinas(rng, x, x + w, y + h * 0.84, h * 0.12, p["cerca"], paso=max(4, w / 24))
    cuerpo.append(_poli(lejos, y + h, p["lejos"]))
    for k in range(arboles):
        px = x + w * (0.2 + 0.5 * (k + rng.random() * 0.3) / max(1, arboles))
        py = min(cerca, key=lambda q: abs(q[0] - px))[1] + 2
        cuerpo.append(_cipres(px, py, h * 0.13, p["figura"]))
    cuerpo.append(_poli(cerca, y + h, p["cerca"]))
    cuerpo.append("</g>")
    return "".join(cuerpo)


def tira(ident, x, y, ancho, pal, semilla=7, borde=None):
    """Símbolo de Marcalibros: tira 1:7 con paisaje."""
    h = ancho * 7
    s = paisaje(ident, x, y, ancho, h, pal, semilla, redondeo=ancho * 0.16, sol_rel=(0.68, 0.2), arboles=2)
    if borde:
        s += (f'<rect x="{_n(x)}" y="{_n(y)}" width="{_n(ancho)}" height="{_n(h)}" rx="{_n(ancho * 0.16)}" '
              f'fill="none" stroke="{borde}" stroke-width="{_n(max(1, ancho * 0.05))}"/>')
    return s


def libro(ident, x, y, w, pal="atardecer", tinta=TINTA, papel=PAPEL):
    """Símbolo de Plataforma POS: libro abierto con la tira como cinta del lomo. Caja w × 1.17w."""
    k = w / 240
    g = [f'<g transform="translate({_n(x)} {_n(y)}) scale({k:.4f})">',
         f'<path d="M120 66Q66 40 10 54V186Q66 172 120 198Q174 172 230 186V54Q174 40 120 66Z" fill="{tinta}"/>',
         f'<path d="M120 74Q70 52 22 64V176Q70 164 120 188Q170 164 218 176V64Q170 52 120 74Z" fill="{papel}"/>']
    for dy in (92, 112, 132):
        g.append(f'<path d="M36 {dy - 10}Q70 {dy - 16} 100 {dy - 3}M140 {dy - 3}Q170 {dy - 16} 204 {dy - 10}" stroke="{tinta}" stroke-opacity=".35" stroke-width="3" fill="none" stroke-linecap="round"/>')
    g.append(tira(ident, 104, 70, 32, pal, 11, borde=papel))
    g.append("</g>")
    return "".join(g)


def etiqueta(x, y, w, color, ojal):
    k = w / 24
    return (f'<g transform="translate({_n(x)} {_n(y)}) scale({k:.4f})"><path d="M3 12V4h8l10 10-8 8z" fill="{color}"/>'
            f'<circle cx="7.5" cy="8.5" r="1.4" fill="{ojal}"/></g>')


# ---------------------------------------------------------------- logos

def logo_simbolo_marcalibros():
    return _svg(40, 280, tira("m", 0, 0, 40, "atardecer"), "Marcalibros")


def logo_simbolo_plataforma():
    return _svg(240, 280, libro("p", 0, 0, 240), "Plataforma POS")


def logo_horizontal(variante, claro=True):
    tinta, papel = (TINTA, PAPEL) if claro else (PAPEL, TINTA)
    fondo = f'<rect width="760" height="200" fill="{papel}"/>' if claro else f'<rect width="760" height="200" fill="{papel}"/>'
    if variante == "marcalibros":
        sim = tira("m", 50, 9, 26, "atardecer")
        cuerpo = (texto_svg("Marcalibros", 130, 112, 78, tinta) + texto_svg("de Plataforma POS", 134, 156, 32, _mezcla(tinta, papel, 0.35)))
        titulo = "Marcalibros, de Plataforma POS"
    else:
        sim = libro("p", 20, 12, 132, tinta=tinta, papel=papel)
        cuerpo = (texto_svg("Plataforma POS", 180, 106, 66, tinta) + texto_svg("con Marcalibros", 184, 156, 32, _mezcla(tinta, papel, 0.35)))
        titulo = "Plataforma POS con Marcalibros"
    return _svg(760, 200, fondo + sim + cuerpo, titulo)


# ---------------------------------------------------------------- iconos 24x24

ICONOS = {
    "marcalibro": '<rect x="9" y="2" width="6" height="20" rx="2"/><path d="M9 16l3-3 3 3"/><circle cx="13" cy="6.5" r="1" />',
    "libro": '<path d="M12 6c-2-1.5-5-2-8-1.5v13c3-.5 6 0 8 1.5 2-1.5 5-2 8-1.5v-13C17 4 14 4.5 12 6z"/><path d="M12 6v13"/>',
    "etiqueta": '<path d="M3 12V4h8l10 10-8 8z"/><circle cx="7.5" cy="8.5" r="1"/>',
    "buscar": '<circle cx="11" cy="11" r="6"/><path d="M20 20l-4.2-4.2"/>',
    "filtro": '<path d="M4 6h16M7 12h10M10 18h4"/>',
    "anadir": '<path d="M12 5v14M5 12h14"/>',
    "comprobar": '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
    "editar": '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="M13.5 6.5l4 4"/>',
    "descargar": '<path d="M12 4v11M7 11l5 5 5-5M5 20h14"/>',
    "compartir": '<circle cx="6" cy="12" r="2"/><circle cx="18" cy="6" r="2"/><circle cx="18" cy="18" r="2"/><path d="M8 11l8-4M8 13l8 4"/>',
    "papelera": '<path d="M5 7h14M10 7V4h4v3M7 7l1 13h8l1-13"/>',
    "resaltar": '<path d="M4 20h6M14 4l6 6-8 8-6-6z"/>',
    "cerrar": '<path d="M6 6l12 12M18 6L6 18"/>',
    "luna": '<path d="M20 14.5A8 8 0 019.5 4a8 8 0 1010.5 10.5z"/>',
    "tira": '<rect x="8" y="1" width="8" height="22" rx="2.5"/><path d="M8 17c2-2 3.5-2 4-1.5s2 .5 4-1"/>',
}


def icono(nombre):
    return _svg(24, 24, f'<g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">{ICONOS[nombre]}</g>', nombre)


def sprite():
    s = "".join(f'<symbol id="{n}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">{c}</symbol>'
                for n, c in ICONOS.items())
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="0" height="0" style="display:none">{s}</svg>\n'


# ---------------------------------------------------------------- estados vacíos (480×320)

def _suelo(color):
    return f'<path d="M30 292H450" stroke="{color}" stroke-width="3" stroke-linecap="round"/>'


def _punteada(x, y, w, color):
    return (f'<rect x="{x}" y="{y}" width="{w}" height="{w * 7}" rx="{_n(w * 0.16)}" fill="none" stroke="{color}" '
            f'stroke-width="2.5" stroke-dasharray="7 7" stroke-linecap="round"/>')


def estado(nombre):
    t, ts = TINTA, _mezcla(TINTA, PAPEL, 0.55)
    fondo = f'<rect width="480" height="320" fill="{PAPEL}"/>'
    if nombre == "biblioteca-vacia":
        c = _punteada(126, 82, 30, ts) + _punteada(225, 82, 30, ts) + _punteada(324, 82, 30, ts)
        c += (f'<circle cx="240" cy="122" r="8" fill="{ACENTO}"/>' + _suelo(t))
        tit = "Biblioteca vacía"
    elif nombre == "sin-resultados":
        c = tira("a", 96, 82, 30, "atardecer") + tira("b", 142, 82, 30, "noche", 3)
        c += (f'<circle cx="290" cy="150" r="52" fill="{PAPEL}" fill-opacity=".85" stroke="{t}" stroke-width="7"/>'
              f'<path d="M327 187L392 252" stroke="{t}" stroke-width="12" stroke-linecap="round"/>'
              f'<path d="M270 152h40" stroke="{ACENTO}" stroke-width="6" stroke-linecap="round"/>' + _suelo(t))
        tit = "Sin resultados"
    elif nombre == "sin-conexion":
        c = (f'<g transform="translate(0 -10)">{tira("a", 222, 50, 36, "niebla", 5)}</g>'
             f'<rect x="212" y="146" width="56" height="22" fill="{PAPEL}"/>'
             f'<path d="M206 150l16 10-16 10 16 10" fill="none" stroke="{ACENTO}" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>'
             f'<path d="M274 150l-16 10 16 10-16 10" fill="none" stroke="{ACENTO}" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>'
             + _suelo(t))
        tit = "Sin conexión"
    else:  # sin-marcas
        c = (libro("l", 130, 30, 220) + _punteada(380, 138, 22, ts) + _suelo(t))
        tit = "Sin marcas en este texto"
    return _svg(480, 320, fondo + c, tit)


# ---------------------------------------------------------------- cabeceras y previews

CABECERAS = {"inicio": ("atardecer", 1), "biblioteca": ("esmeralda", 2), "lector": ("noche", 3), "editor": ("amanecer", 4)}


def cabecera(nombre):
    pal, sem = CABECERAS[nombre]
    escena = paisaje("h", 0, 0, 1920, 1080, pal, sem, sol_rel=(0.16, 0.3), arboles=5)
    # la tira vuelve a aparecer a la derecha, a modo de marcalibros sobre el paisaje
    escena += tira("t", 1560, 180, 128, pal, sem + 10, borde=PAPEL)
    return _svg(1920, 1080, escena, f"Cabecera {nombre}")


PREVIEWS = {
    "marcalibros": ("Marcalibros", "Marcadores de lectura con paisaje propio", "atardecer", "tira"),
    "plataforma-pos": ("Plataforma POS", "Etiqueta textos y lee con tus marcas", "noche", "libro"),
    "lector": ("Tu lugar en cada libro", "Lector de Plataforma POS con Marcalibros", "esmeralda", "tira"),
}


def preview(nombre):
    titulo, sub, pal, simbolo = PREVIEWS[nombre]
    escena = paisaje("v", 0, 0, 1200, 630, pal, 20 + len(nombre), sol_rel=(0.86, 0.25), arboles=4)
    panel = f'<rect x="60" y="150" width="760" height="330" rx="22" fill="{PAPEL}" fill-opacity=".94"/>'
    if simbolo == "tira":
        sim = tira("s", 96, 176, 40, pal, 9)
        x_txt = 176
    else:
        sim = libro("s", 92, 176, 124, pal="noche")
        x_txt = 244
    cuerpo = texto_svg(titulo, x_txt, 295, 66 if len(titulo) < 16 else 52, TINTA)
    cuerpo += texto_svg(sub, x_txt + 2, 355, 24, _mezcla(TINTA, PAPEL, 0.3))
    cuerpo += f'<rect x="{x_txt + 2}" y="392" width="90" height="6" rx="3" fill="{ACENTO}"/>'
    return _svg(1200, 630, escena + panel + sim + cuerpo, titulo)


# ---------------------------------------------------------------- salida

def todo():
    """Diccionario ruta relativa → contenido."""
    out = {"brand-tokens.yaml": yaml.safe_dump(tokens(), allow_unicode=True, sort_keys=False)}
    out["logos/marcalibros-simbolo.svg"] = logo_simbolo_marcalibros()
    out["logos/plataforma-pos-simbolo.svg"] = logo_simbolo_plataforma()
    for v in ("marcalibros", "plataforma-pos"):
        out[f"logos/{v}-horizontal.svg"] = logo_horizontal("marcalibros" if v == "marcalibros" else "plataforma")
        out[f"logos/{v}-horizontal-oscuro.svg"] = logo_horizontal("marcalibros" if v == "marcalibros" else "plataforma", claro=False)
    for n in ICONOS:
        out[f"iconos/{n}.svg"] = icono(n)
    out["iconos/sprite.svg"] = sprite()
    for n in ("biblioteca-vacia", "sin-resultados", "sin-conexion", "sin-marcas"):
        out[f"estados-vacios/{n}.svg"] = estado(n)
    for n in CABECERAS:
        out[f"cabeceras/{n}.svg"] = cabecera(n)
    for n in PREVIEWS:
        out[f"previews/{n}.svg"] = preview(n)
    return out


def a_png(svg, destino, w, h):
    from PIL import Image
    chromium = os.environ.get("CHROMIUM", "/opt/pw-browsers/chromium")
    with tempfile.TemporaryDirectory() as d:
        pagina = Path(d) / "p.html"
        pagina.write_text(f'<body style="margin:0"><img src="{svg.resolve().as_uri()}" width="{w}" height="{h}"></body>')
        crudo = Path(d) / "c.png"
        subprocess.run([chromium, "--headless", "--no-sandbox", "--disable-gpu", f"--screenshot={crudo}",
                        f"--window-size={w},{h + 300}", pagina.as_uri()], check=True, capture_output=True)
        Image.open(crudo).convert("RGB").crop((0, 0, w, h)).save(destino, optimize=True)


def main(argv=None):
    ap = argparse.ArgumentParser()
    ap.add_argument("--png", action="store_true", help="exporta los previews sociales a PNG con Chromium")
    args = ap.parse_args(argv)
    piezas = todo()
    for ruta, contenido in piezas.items():
        destino = SALIDA / ruta
        destino.parent.mkdir(parents=True, exist_ok=True)
        destino.write_text(contenido, encoding="utf-8")
    if args.png:
        for n in PREVIEWS:
            a_png(SALIDA / f"previews/{n}.svg", SALIDA / f"previews/{n}.png", 1200, 630)
    print(f"marca: {len(piezas)} archivos en {SALIDA.relative_to(RAIZ)}/")


if __name__ == "__main__":
    main()
