#!/usr/bin/env python3
"""Genera las constelaciones SVG y la tabla por idioma desde datos/signos.json.

Uso:
  python3 generar.py              escribe constelaciones/*.svg y signos-por-idioma.md
  python3 generar.py --comprobar  falla si lo committeado no está al día
"""
import json
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
}

GRUPOS = {"occidental": "Zodiaco occidental", "chino": "Horóscopo chino"}


def cargar():
    with DATOS.open(encoding="utf-8") as f:
        signos = json.load(f)["signos"]
    for s in signos:
        for campo in ("nombres", "descripciones"):
            if set(s[campo]) != set(IDIOMAS):
                sys.exit(f"{s['id']}: {campo} no tiene exactamente los 20 idiomas")
        if s["tipo"] == "occidental":
            estrellas = s["constelacion"]["estrellas"]
            for a, b in s["constelacion"]["lineas"]:
                if not (0 <= a < len(estrellas) and 0 <= b < len(estrellas)):
                    sys.exit(f"{s['id']}: una línea apunta a una estrella que no existe")
    return signos


def svg_de(signo):
    nombre = signo["nombres"]["es"]
    estrellas = signo["constelacion"]["estrellas"]
    partes = [
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" role="img">',
        f"  <title>Constelación de {nombre} (esquema, no a escala)</title>",
        '  <rect width="200" height="200" rx="12" fill="#0b1026"/>',
        '  <g stroke="#8fa3d9" stroke-width="1.5" stroke-linecap="round">',
    ]
    for a, b in signo["constelacion"]["lineas"]:
        (x1, y1), (x2, y2) = estrellas[a], estrellas[b]
        partes.append(f'    <line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}"/>')
    partes.append("  </g>")
    partes.append('  <g fill="#ffffff">')
    for x, y in estrellas:
        partes.append(f'    <circle cx="{x}" cy="{y}" r="2.8"/>')
    partes.append("  </g>")
    partes.append(
        f'  <text x="100" y="192" text-anchor="middle" font-family="sans-serif" '
        f'font-size="11" fill="#c9d3f0">{nombre}</text>'
    )
    partes.append("</svg>")
    return "\n".join(partes) + "\n"


def markdown_de(signos):
    lineas = [
        "# Signos del zodiaco y del horóscopo chino en 20 idiomas",
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
