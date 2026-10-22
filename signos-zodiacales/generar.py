#!/usr/bin/env python3
"""Genera las constelaciones SVG y las fichas por idioma desde datos/signos.json.

Uso:
  python3 generar.py              escribe constelaciones/*.svg y por-idioma/*.md
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
SALIDA_MD = RAIZ / "por-idioma"

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

ELEMENTOS_OCCIDENTALES = {"fuego", "tierra", "aire", "agua"}
ELEMENTOS_CHINOS = {"madera", "fuego", "tierra", "metal", "agua"}
MODALIDADES = {"cardinal", "fijo", "mutable"}
POLARIDADES = {"yang", "yin"}


def validar(datos):
    """Devuelve la lista de problemas de datos/signos.json (vacía si está bien)."""
    errores = []
    esperados_idiomas = set(IDIOMAS)

    terminos = datos.get("terminos", {})
    necesarios = ELEMENTOS_OCCIDENTALES | ELEMENTOS_CHINOS | MODALIDADES | POLARIDADES
    for t in sorted(necesarios):
        if set(terminos.get(t, {})) != esperados_idiomas:
            errores.append(f"terminos/{t}: no tiene exactamente los {len(IDIOMAS)} idiomas")

    ids = [s["id"] for s in datos["signos"]]
    if len(ids) != len(set(ids)):
        errores.append("hay ids de signo repetidos")

    for s in datos["signos"]:
        sid = s["id"]
        for campo in ("nombres", "descripciones", "descripciones_largas"):
            if set(s.get(campo, {})) != esperados_idiomas:
                errores.append(f"{sid}: {campo} no tiene exactamente los {len(IDIOMAS)} idiomas")
            elif any(not texto.strip() for texto in s[campo].values()):
                errores.append(f"{sid}: {campo} tiene un texto vacío")
        if s["tipo"] == "occidental":
            if s.get("elemento") not in ELEMENTOS_OCCIDENTALES:
                errores.append(f"{sid}: elemento occidental no válido")
            if s.get("modalidad") not in MODALIDADES:
                errores.append(f"{sid}: modalidad no válida")
            if not s.get("fechas"):
                errores.append(f"{sid}: falta fechas")
        elif s["tipo"] == "chino":
            if s.get("elemento") not in ELEMENTOS_CHINOS:
                errores.append(f"{sid}: elemento chino no válido")
            if s.get("polaridad") not in POLARIDADES:
                errores.append(f"{sid}: polaridad no válida")
            if not s.get("rama"):
                errores.append(f"{sid}: falta rama")
            anios = s.get("anios", [])
            if not anios or any(b - a != 12 for a, b in zip(anios, anios[1:])):
                errores.append(f"{sid}: los años deben seguir el ciclo de 12")
        else:
            errores.append(f"{sid}: tipo desconocido {s['tipo']!r}")
        if "constelacion" in s:
            estrellas = s["constelacion"]["estrellas"]
            for a, b in s["constelacion"]["lineas"]:
                if not (0 <= a < len(estrellas) and 0 <= b < len(estrellas)):
                    errores.append(f"{sid}: una línea apunta a una estrella que no existe")
            for x, y in estrellas:
                if not (0 <= x <= 200 and 0 <= y <= 200):
                    errores.append(f"{sid}: una estrella cae fuera del cuadro de 200×200")
            if not s.get("figura") or any(not d.strip() for d in s["figura"]):
                errores.append(f"{sid}: falta la figura (lista de trazos) o tiene un trazo vacío")
    return errores


def cargar():
    with DATOS.open(encoding="utf-8") as f:
        datos = json.load(f)
    errores = validar(datos)
    if errores:
        sys.exit("datos/signos.json no es válido:\n  " + "\n  ".join(errores))
    return datos


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
    if signo["tipo"] == "occidental":
        titulo = f"Constelación de {nombre}"
        descripcion = "Esquema de la constelación con su figura, no a escala."
    else:
        titulo = f"Figura estelar: {nombre}"
        descripcion = "Figura de estrellas inventada; el horóscopo chino no tiene constelaciones astronómicas."
    partes = [
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" role="img"',
        f'     aria-labelledby="t-{ident} d-{ident}">',
        f'  <title id="t-{ident}">{titulo}</title>',
        f'  <desc id="d-{ident}">{descripcion}</desc>',
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
    if signo.get("figura"):
        partes.append(
            '  <g fill="none" stroke="#e6c98b" stroke-opacity="0.7" '
            'stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round">'
        )
        for d in signo["figura"]:
            # Solo los trazos cerrados (terminan en Z) llevan relleno; uno abierto se rellenaría en cuña.
            relleno = ' fill="#e6c98b" fill-opacity="0.07"' if d.strip().endswith("Z") else ""
            partes.append(f'    <path d="{d}"{relleno}/>')
        partes.append("  </g>")
    partes.append('  <g stroke="#a9bcff" stroke-width="1" stroke-opacity="0.5" stroke-linecap="round">')
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


def rasgos(signo, terminos, codigo):
    """Línea de rasgos de un signo en un idioma, separada por «·»."""
    t = lambda clave: terminos[clave][codigo]  # noqa: E731
    if signo["tipo"] == "occidental":
        return f"{signo['fechas']} · {t(signo['elemento'])} · {t(signo['modalidad'])}"
    anios = ", ".join(str(a) for a in signo["anios"])
    return f"{signo['rama']} · {t(signo['elemento'])} · {t(signo['polaridad'])} · {anios}"


def ficha_de(signos, terminos, codigo):
    lineas = [
        f"# {IDIOMAS[codigo]} (`{codigo}`)",
        "",
        "> Generado por `generar.py` desde `datos/signos.json`. No editar a mano.",
        "",
    ]
    for tipo, titulo in GRUPOS.items():
        lineas += [f"## {titulo}", ""]
        for s in signos:
            if s["tipo"] != tipo:
                continue
            lineas += [
                f"### {s['nombres'][codigo]}",
                "",
                f"*{rasgos(s, terminos, codigo)}*",
                "",
                s["descripciones"][codigo],
                "",
                s["descripciones_largas"][codigo],
                "",
            ]
    return "\n".join(lineas)


def indice_de():
    lineas = [
        "# Fichas por idioma",
        "",
        "> Generado por `generar.py` desde `datos/signos.json`. No editar a mano.",
        "",
        "| Idioma | Código | Ficha |",
        "| --- | --- | --- |",
    ]
    for codigo, nombre in IDIOMAS.items():
        lineas.append(f"| {nombre} | `{codigo}` | [{codigo}.md]({codigo}.md) |")
    lineas.append("")
    return "\n".join(lineas)


def esperados(datos):
    signos, terminos = datos["signos"], datos["terminos"]
    archivos = {SALIDA_MD / "README.md": indice_de()}
    for codigo in IDIOMAS:
        archivos[SALIDA_MD / f"{codigo}.md"] = ficha_de(signos, terminos, codigo)
    for s in signos:
        if "constelacion" in s:
            archivos[SALIDA_SVG / f"{s['id']}.svg"] = svg_de(s)
    return archivos


def sobrantes(archivos):
    """Archivos de las carpetas generadas que ya nadie genera."""
    return sorted(
        str(p.relative_to(RAIZ))
        for carpeta in (SALIDA_SVG, SALIDA_MD)
        if carpeta.exists()
        for p in carpeta.iterdir()
        if p.is_file() and p not in archivos
    )


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
        sobra = sobrantes(archivos)
        if malos or sobra:
            if malos:
                print("Desactualizados (ejecuta python3 generar.py):")
                for ruta in malos:
                    print(f"  {ruta}")
            if sobra:
                print("Sobran (ya no los genera generar.py; bórralos):")
                for ruta in sobra:
                    print(f"  {ruta}")
            return 1
        print("Todo al día.")
        return 0
    SALIDA_SVG.mkdir(exist_ok=True)
    SALIDA_MD.mkdir(exist_ok=True)
    for ruta, texto in archivos.items():
        ruta.write_text(texto, encoding="utf-8")
    print(f"Escritos {len(archivos)} archivos.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
