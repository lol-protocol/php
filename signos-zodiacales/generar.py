#!/usr/bin/env python3
"""Genera las ilustraciones SVG y las fichas por idioma desde datos/signos.json.

Uso:
  python3 generar.py              escribe constelaciones/, constelaciones-sin-fondo/ y por-idioma/
  python3 generar.py --comprobar  falla si lo committeado no está al día
"""
import datetime
import hashlib
import json
import sys
from pathlib import Path
from xml.etree import ElementTree

import zodiaco

RAIZ = Path(__file__).resolve().parent
DATOS = RAIZ / "datos" / "signos.json"
SALIDA_SVG = RAIZ / "constelaciones"
SALIDA_SVG_SIN_FONDO = RAIZ / "constelaciones-sin-fondo"
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

# Idiomas donde se usa el zodiaco sideral (astrología védica): su ficha muestra las dos fechas,
# con el nombre local de cada sistema (sāyana = tropical, nirayana = sideral).
SISTEMAS_LOCALES = {
    "hi": {"tropical": "सायन", "sideral": "निरयण"},
    "bn": {"tropical": "সায়ন", "sideral": "নিরয়ণ"},
}

GRUPOS = {"occidental": "Zodiaco occidental", "chino": "Horóscopo chino"}

ELEMENTOS_OCCIDENTALES = {"fuego", "tierra", "aire", "agua"}
ELEMENTOS_CHINOS = {"madera", "fuego", "tierra", "metal", "agua"}
MODALIDADES = {"cardinal", "fijo", "mutable"}
POLARIDADES = {"yang", "yin"}

# Años de cada animal que muestra la ficha. zodiaco.animal_chino() sirve para cualquier año.
ANIOS_FICHA = (1924, 2043)


def _fecha_valida(par):
    """Si [mes, día] es un día que existe (se prueba en 2024, bisiesto, para admitir el 29/02)."""
    try:
        mes, dia = par
        datetime.date(2024, mes, dia)
        return True
    except (TypeError, ValueError):
        return False


def validar(datos):
    """Devuelve la lista de problemas de datos/signos.json (vacía si está bien)."""
    errores = []
    esperados_idiomas = set(IDIOMAS)

    terminos = datos.get("terminos", {})
    necesarios = ELEMENTOS_OCCIDENTALES | ELEMENTOS_CHINOS | MODALIDADES | POLARIDADES
    for t in sorted(necesarios):
        if set(terminos.get(t, {})) != esperados_idiomas:
            errores.append(f"terminos/{t}: no tiene exactamente los {len(IDIOMAS)} idiomas")

    troncos = datos.get("troncos", [])
    if len(troncos) != 10:
        errores.append("troncos: tienen que ser los 10 troncos celestes")
    else:
        for i, t in enumerate(troncos):
            if t.get("elemento") not in ELEMENTOS_CHINOS or not t.get("hanzi") or not t.get("pinyin"):
                errores.append(f"troncos/{i}: falta hanzi o pinyin, o el elemento no es válido")
            if t.get("polaridad") != ("yang" if i % 2 == 0 else "yin"):
                errores.append(f"troncos/{i}: la polaridad tiene que alternar yang y yin")
            if i % 2 == 1 and t.get("elemento") != troncos[i - 1].get("elemento"):
                errores.append(f"troncos/{i}: cada elemento ocupa dos troncos seguidos")

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
            if len(s.get("simbolo", "")) != 1:
                errores.append(f"{sid}: simbolo tiene que ser un solo carácter")
            for sistema in zodiaco.SISTEMAS:
                rango = s.get(sistema, {})
                if not (_fecha_valida(rango.get("desde")) and _fecha_valida(rango.get("hasta"))):
                    errores.append(f"{sid}: {sistema} necesita desde y hasta como [mes, día] válidos")
        elif s["tipo"] == "chino":
            if s.get("elemento") not in ELEMENTOS_CHINOS:
                errores.append(f"{sid}: elemento chino no válido")
            if s.get("polaridad") not in POLARIDADES:
                errores.append(f"{sid}: polaridad no válida")
            rama = s.get("rama", {})
            if not isinstance(rama, dict) or len(rama.get("hanzi", "")) != 1 or not rama.get("pinyin"):
                errores.append(f"{sid}: rama necesita hanzi (un carácter) y pinyin")
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


def numeros(semilla):
    """Números en [0, 1) que dependen solo de la semilla, no de la versión de Python.

    Cada número sale de SHA-256(semilla:n). random.Random no garantiza la misma secuencia entre
    versiones, y entonces --comprobar fallaría en la CI sin que nadie haya tocado nada.
    """
    n = 0
    while True:
        resumen = hashlib.sha256(f"{semilla}:{n}".encode("utf-8")).digest()
        yield int.from_bytes(resumen[:8], "big") / 2**64
        n += 1


def entre(azar, minimo, maximo):
    return minimo + next(azar) * (maximo - minimo)


def _figura_y_estrellas(signo, color_figura, color_lineas, color_estrellas, radios, filtro=""):
    estrellas = signo["constelacion"]["estrellas"]
    partes = [
        f'  <g fill="none" stroke="{color_figura}" stroke-opacity="0.7" '
        'stroke-width="1.1" stroke-linecap="round" stroke-linejoin="round">'
    ]
    for d in signo["figura"]:
        # Solo los trazos cerrados (terminan en Z) llevan relleno; uno abierto se rellenaría en cuña.
        relleno = f' fill="{color_figura}" fill-opacity="0.07"' if d.strip().endswith("Z") else ""
        partes.append(f'    <path d="{d}"{relleno}/>')
    partes.append("  </g>")
    partes.append(f'  <g stroke="{color_lineas}" stroke-width="1" stroke-opacity="0.5" stroke-linecap="round">')
    for a, b in signo["constelacion"]["lineas"]:
        (x1, y1), (x2, y2) = estrellas[a], estrellas[b]
        partes.append(f'    <line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}"/>')
    partes.append("  </g>")
    partes.append(f'  <g fill="{color_estrellas}"{filtro}>')
    for (x, y), r in zip(estrellas, radios):
        partes.append(f'    <circle cx="{x}" cy="{y}" r="{r:.1f}"/>')
    partes.append("  </g>")
    return partes


def _radios(signo):
    azar = numeros(f"{signo['id']}:radios")
    estrellas = signo["constelacion"]["estrellas"]
    return [2.8] + [(1.6, 2.0, 2.4)[int(next(azar) * 3)] for _ in estrellas[1:]]


def svg_de(signo):
    """Ilustración sobre fondo de cielo nocturno. Sin texto: el nombre lo pone quien la muestra."""
    ident = signo["id"]
    azar = numeros(f"{ident}:fondo")
    partes = [
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">',
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
    for _ in range(45):
        x, y = entre(azar, 8, 192), entre(azar, 8, 192)
        r, o = entre(azar, 0.4, 1.1), entre(azar, 0.25, 0.8)
        partes.append(f'    <circle cx="{x:.1f}" cy="{y:.1f}" r="{r:.2f}" fill-opacity="{o:.2f}"/>')
    partes.append("  </g>")
    partes += _figura_y_estrellas(signo, "#e6c98b", "#a9bcff", "#ffffff", _radios(signo),
                                  filtro=f' filter="url(#brillo-{ident})"')
    partes.append("</svg>")
    return "\n".join(partes) + "\n"


def svg_sin_fondo(signo):
    """La misma figura, transparente y en currentColor: toma el color del texto que la rodea."""
    partes = ['<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">']
    partes += _figura_y_estrellas(signo, "currentColor", "currentColor", "currentColor", _radios(signo))
    partes.append("</svg>")
    return "\n".join(partes) + "\n"


def _dia(par):
    mes, dia = par
    return f"{dia:02d}/{mes:02d}"


def _rango(signo, sistema):
    return f"{_dia(signo[sistema]['desde'])} – {_dia(signo[sistema]['hasta'])}"


def rasgos(signo, terminos, codigo):
    """Línea de rasgos de un signo en un idioma, separada por «·»."""
    t = lambda clave: terminos[clave][codigo]  # noqa: E731
    if signo["tipo"] == "occidental":
        if codigo in SISTEMAS_LOCALES:
            nombres = SISTEMAS_LOCALES[codigo]
            fechas = f"{nombres['tropical']} {_rango(signo, 'tropical')} · {nombres['sideral']} {_rango(signo, 'sideral')}"
        else:
            fechas = _rango(signo, "tropical")
        return f"{fechas} · {t(signo['elemento'])} · {t(signo['modalidad'])}"
    rama = signo["rama"]
    return f"{rama['hanzi']} {rama['pinyin']} · {t(signo['elemento'])} · {t(signo['polaridad'])}"


def anios(signo, datos, codigo):
    """Años del animal con el elemento de cada año, por ejemplo «1924 Madera · 1936 Fuego · …»."""
    lista = zodiaco.anios_del_animal(signo["id"], *ANIOS_FICHA, datos=datos)
    terminos = datos["terminos"]
    return " · ".join(
        f"{a} {terminos[zodiaco.animal_chino(a, datos=datos)['elemento_del_anio']][codigo]}" for a in lista
    )


def ficha_de(datos, codigo):
    signos, terminos = datos["signos"], datos["terminos"]
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
            encabezado = s["nombres"][codigo]
            if tipo == "occidental":
                encabezado = f"{s['simbolo']} {encabezado}"
            lineas += [f"### {encabezado}", "", f"*{rasgos(s, terminos, codigo)}*", ""]
            if tipo == "chino":
                lineas += [anios(s, datos, codigo), ""]
            lineas += [s["descripciones"][codigo], "", s["descripciones_largas"][codigo], ""]
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
    archivos = {SALIDA_MD / "README.md": indice_de()}
    for codigo in IDIOMAS:
        archivos[SALIDA_MD / f"{codigo}.md"] = ficha_de(datos, codigo)
    for s in datos["signos"]:
        if "constelacion" in s:
            archivos[SALIDA_SVG / f"{s['id']}.svg"] = svg_de(s)
            archivos[SALIDA_SVG_SIN_FONDO / f"{s['id']}.svg"] = svg_sin_fondo(s)
    return archivos


def sobrantes(archivos):
    """Archivos de las carpetas generadas que ya nadie genera."""
    return sorted(
        str(p.relative_to(RAIZ))
        for carpeta in (SALIDA_SVG, SALIDA_SVG_SIN_FONDO, SALIDA_MD)
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
    for carpeta in (SALIDA_SVG, SALIDA_SVG_SIN_FONDO, SALIDA_MD):
        carpeta.mkdir(exist_ok=True)
    for ruta, texto in archivos.items():
        ruta.write_text(texto, encoding="utf-8")
    print(f"Escritos {len(archivos)} archivos.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
