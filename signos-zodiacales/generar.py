#!/usr/bin/env python3
"""Genera las constelaciones SVG y la tabla por idioma desde datos/signos.json.

Uso:
  python3 generar.py              escribe constelaciones/*.svg y signos-por-idioma.md
  python3 generar.py --comprobar  falla si lo committeado no está al día
"""
import json
import random
import sys
from pathlib import Path
from xml.etree import ElementTree

RAIZ = Path(__file__).resolve().parent
DATOS = RAIZ / "datos" / "signos.json"
SALIDA_SVG = RAIZ / "constelaciones"
SALIDA_MD = RAIZ / "signos-por-idioma.md"

IDIOMAS = {
    "es": "Español", "en": "English", "pt": "Português", "fr": "Français",
    "it": "Italiano", "de": "Deutsch", "nl": "Nederlands", "sv": "Svenska",
    "pl": "Polski", "ru": "Русский", "uk": "Українська", "tr": "Türkçe",
    "el": "Ελληνικά", "ar": "العربية", "he": "עברית", "hi": "हिन्दी",
    "zh": "中文", "ja": "日本語", "ko": "한국어", "vi": "Tiếng Việt",
    "th": "ไทย", "id": "Bahasa Indonesia", "fa": "فارسی", "ur": "اردو",
    "bn": "বাংলা", "ro": "Română", "cs": "Čeština", "fi": "Suomi",
    "sw": "Kiswahili", "da": "Dansk",
}

GRUPOS = {"occidental": "Zodiaco occidental", "chino": "Horóscopo chino"}


def cargar():
    with DATOS.open(encoding="utf-8") as f:
        signos = json.load(f)["signos"]
    for s in signos:
        for campo in ("nombres", "descripciones"):
            if set(s[campo]) != set(IDIOMAS):
                sys.exit(f"{s['id']}: {campo} no tiene exactamente los 30 idiomas")
        if s["tipo"] == "occidental":
            estrellas = s["constelacion"]["estrellas"]
            for a, b in s["constelacion"]["lineas"]:
                if not (0 <= a < len(estrellas) and 0 <= b < len(estrellas)):
                    sys.exit(f"{s['id']}: una línea apunta a una estrella que no existe")
    return signos


def svg_de(signo):
    nombre = signo["nombres"]["es"]
    ident = signo["id"]
    estrellas = signo["constelacion"]["estrellas"]
    # Semilla fija por signo: el fondo y los tamaños salen igual en cada ejecución.
    azar = random.Random(ident)
    fondo = [
        (azar.uniform(8, 192), azar.uniform(8, 168), azar.uniform(0.4, 1.1), azar.uniform(0.25, 0.8))
        for _ in range(45)
    ]
    radios = [2.8] + [azar.choice([1.6, 2.0, 2.4]) for _ in estrellas[1:]]
    partes = [
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" role="img"',
        f'     aria-labelledby="t-{ident} d-{ident}">',
        f'  <title id="t-{ident}">Constelación de {nombre}</title>',
        f'  <desc id="d-{ident}">Esquema de la constelación, no a escala.</desc>',
        "  <defs>",
        f'    <radialGradient id="fondo-{ident}" cx="50%" cy="42%" r="70%">',
        '      <stop offset="0" stop-color="#1c2656"/>',
        '      <stop offset="1" stop-color="#050818"/>',
        "    </radialGradient>",
        f'    <filter id="brillo-{ident}" x="-300%" y="-300%" width="700%" height="700%">',
        '      <feGaussianBlur stdDeviation="1.8" result="halo"/>',
        '      <feMerge><feMergeNode in="halo"/><feMergeNode in="SourceGraphic"/></feMerge>',
        "    </filter>",
        "  </defs>",
        f'  <rect width="200" height="200" rx="14" fill="url(#fondo-{ident})"/>',
        '  <rect x="7" y="7" width="186" height="186" rx="10" fill="none" stroke="#ffffff" stroke-opacity="0.14"/>',
        '  <g fill="#ffffff">',
    ]
    for x, y, r, o in fondo:
        partes.append(f'    <circle cx="{x:.1f}" cy="{y:.1f}" r="{r:.2f}" fill-opacity="{o:.2f}"/>')
    partes.append("  </g>")
    partes.append('  <g stroke="#a9bcff" stroke-width="1" stroke-opacity="0.6" stroke-linecap="round">')
    for a, b in signo["constelacion"]["lineas"]:
        (x1, y1), (x2, y2) = estrellas[a], estrellas[b]
        partes.append(f'    <line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}"/>')
    partes.append("  </g>")
    partes.append(f'  <g fill="#ffffff" filter="url(#brillo-{ident})">')
    for (x, y), r in zip(estrellas, radios):
        partes.append(f'    <circle cx="{x}" cy="{y}" r="{r:.1f}"/>')
    partes.append("  </g>")
    partes.append(
        f'  <text x="100" y="184" text-anchor="middle" font-family="Georgia, serif" '
        f'font-size="10" letter-spacing="3" fill="#dfe6ff" fill-opacity="0.9">{nombre.upper()}</text>'
    )
    partes.append("</svg>")
    return "\n".join(partes) + "\n"


def markdown_de(signos):
    lineas = [
        "# Signos del zodiaco y del horóscopo chino en 30 idiomas",
        "",
        "> Generado por `generar.py` desde `datos/signos.json`. No editar a mano.",
        "",
    ]
    for codigo, nombre_idioma in IDIOMAS.items():
        lineas += [f"## {nombre_idioma} (`{codigo}`)", ""]
        for tipo, titulo in GRUPOS.items():
            lineas += [f"### {titulo}", "", "| Signo | Descripción |", "| --- | --- |"]
            for s in signos:
                if s["tipo"] == tipo:
                    lineas.append(f"| {s['nombres'][codigo]} | {s['descripciones'][codigo]} |")
            lineas.append("")
    return "\n".join(lineas)


def esperados(signos):
    archivos = {SALIDA_MD: markdown_de(signos)}
    for s in signos:
        if s["tipo"] == "occidental":
            archivos[SALIDA_SVG / f"{s['id']}.svg"] = svg_de(s)
    return archivos


def main():
    archivos = esperados(cargar())
    if "--comprobar" in sys.argv:
        for ruta, texto in archivos.items():
            if ruta.suffix == ".svg":
                ElementTree.fromstring(texto)
        malos = [
            str(ruta.relative_to(RAIZ))
            for ruta, texto in archivos.items()
            if not ruta.exists() or ruta.read_text(encoding="utf-8") != texto
        ]
        if malos:
            print("Desactualizados (ejecuta python3 generar.py):")
            for ruta in malos:
                print(f"  {ruta}")
            return 1
        print("Todo al día.")
        return 0
    SALIDA_SVG.mkdir(exist_ok=True)
    for ruta, texto in archivos.items():
        ruta.write_text(texto, encoding="utf-8")
    print(f"Escritos {len(archivos)} archivos.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
