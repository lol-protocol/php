#!/usr/bin/env python3
"""Genera las ilustraciones SVG y las fichas por idioma desde datos/signos.json.

Uso:
  python3 generar.py              escribe constelaciones/, constelaciones-sin-fondo/ y por-idioma/
  python3 generar.py --comprobar  falla si lo committeado no está al día
"""
import datetime
import hashlib
import json
import re
import sys
from pathlib import Path
from xml.etree import ElementTree

import zodiaco

RAIZ = Path(__file__).resolve().parent
DATOS = RAIZ / "datos" / "signos.json"
CHANGELOG = RAIZ / "CHANGELOG.md"
VECTORES = RAIZ / "datos" / "vectores.json"
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

# Cada tipo de signo y la clave de "terminos" con el título de su sección, traducido.
GRUPOS = {"occidental": "zodiaco_occidental", "chino": "horoscopo_chino"}

# Textos de la galería y de las fichas, también en "terminos".
TERMINOS_DE_INTERFAZ = {"titulo", "zodiaco_occidental", "horoscopo_chino", "idioma", "ilustracion",
                        "con_fondo", "sin_fondo", "decanatos"}

# Idiomas que se escriben de derecha a izquierda: su ficha va dentro de <div dir="rtl">.
DERECHA_A_IZQUIERDA = {"ar", "he", "fa", "ur"}

ELEMENTOS_OCCIDENTALES = {"fuego", "tierra", "aire", "agua"}
ELEMENTOS_CHINOS = {"madera", "fuego", "tierra", "metal", "agua"}
MODALIDADES = {"cardinal", "fijo", "mutable"}
POLARIDADES = {"yang", "yin"}
ESTADOS_DE_REVISION = ("sin_revisar", "revisado")

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


def _fecha_iso_valida(texto):
    if not isinstance(texto, str) or not re.fullmatch(r"\d{4}-\d{2}-\d{2}", texto):
        return False
    try:
        datetime.date.fromisoformat(texto)
    except ValueError:
        return False
    return True


def validar_changelog(texto, version):
    """Problemas de CHANGELOG.md respecto de la versión de los datos (lista vacía si está bien)."""
    entradas = re.findall(r"^## \[(\d+\.\d+\.\d+)\] - (\d{4}-\d{2}-\d{2})$", texto, re.MULTILINE)
    if not entradas:
        return ["CHANGELOG.md: no tiene ninguna entrada «## [X.Y.Z] - AAAA-MM-DD»"]
    errores = []
    if entradas[0][0] != version:
        errores.append(f"CHANGELOG.md: la entrada más reciente es {entradas[0][0]} y datos/signos.json dice {version}")
    versiones = [tuple(map(int, v.split("."))) for v, _ in entradas]
    if versiones != sorted(versiones, reverse=True) or len(set(versiones)) != len(versiones):
        errores.append("CHANGELOG.md: las versiones tienen que ir de la más nueva a la más vieja, sin repetir")
    for _, fecha in entradas:
        if not _fecha_iso_valida(fecha):
            errores.append(f"CHANGELOG.md: la fecha {fecha} no existe")
    return errores


def validar(datos):
    """Devuelve la lista de problemas de datos/signos.json (vacía si está bien)."""
    errores = []
    esperados_idiomas = set(IDIOMAS)

    if not re.fullmatch(r"\d+\.\d+\.\d+", str(datos.get("version", ""))):
        errores.append("version: tiene que ser X.Y.Z (versionado semántico)")

    revision = datos.get("revision", {})
    if set(revision) != esperados_idiomas:
        errores.append(f"revision: no tiene exactamente los {len(IDIOMAS)} idiomas")
    for codigo, estado in revision.items():
        if estado.get("estado") not in ESTADOS_DE_REVISION:
            errores.append(f"revision/{codigo}: estado tiene que ser uno de {ESTADOS_DE_REVISION}")
        elif estado["estado"] == "revisado":
            if not str(estado.get("por", "")).strip() or not _fecha_iso_valida(estado.get("fecha")):
                errores.append(f"revision/{codigo}: un idioma revisado necesita «por» (quién) y «fecha» (AAAA-MM-DD)")
        elif set(estado) != {"estado"}:
            errores.append(f"revision/{codigo}: sin_revisar no lleva más campos")

    terminos = datos.get("terminos", {})
    necesarios = (ELEMENTOS_OCCIDENTALES | ELEMENTOS_CHINOS | MODALIDADES | POLARIDADES
                  | TERMINOS_DE_INTERFAZ | set(zodiaco.ORDEN_CALDEO))
    for t in sorted(necesarios):
        if set(terminos.get(t, {})) != esperados_idiomas:
            errores.append(f"terminos/{t}: no tiene exactamente los {len(IDIOMAS)} idiomas")

    ciclo = datos.get("nombres_del_ciclo", {})
    if set(ciclo) != esperados_idiomas:
        errores.append(f"nombres_del_ciclo: no tiene exactamente los {len(IDIOMAS)} idiomas")
    for codigo, regla in ciclo.items():
        plantilla = regla.get("plantilla", "")
        if "{elemento}" not in plantilla or not ("{animal}" in plantilla or "{animal_minusculas}" in plantilla):
            errores.append(f"nombres_del_ciclo/{codigo}: la plantilla necesita {{elemento}} y {{animal}}")
        if not isinstance(regla.get("idiomatica"), bool):
            errores.append(f"nombres_del_ciclo/{codigo}: idiomatica tiene que ser true o false")
        if not set(regla.get("elementos", {})) <= ELEMENTOS_CHINOS:
            errores.append(f"nombres_del_ciclo/{codigo}: elementos solo puede cambiar elementos chinos")

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
            errores += _errores_de_figura(sid, s)
        for codigo, variante in s.get("variantes", {}).items():
            if codigo not in IDIOMAS:
                errores.append(f"{sid}: variante para un idioma desconocido: {codigo!r}")
            errores += _errores_de_figura(f"{sid}/variantes/{codigo}", variante)
    return errores


def _errores_de_figura(donde, s):
    """Problemas de la constelación y la figura de un signo o de una variante."""
    errores = []
    if "constelacion" not in s:
        return [f"{donde}: falta la constelación"]
    estrellas = s["constelacion"]["estrellas"]
    for a, b in s["constelacion"]["lineas"]:
        if not (0 <= a < len(estrellas) and 0 <= b < len(estrellas)):
            errores.append(f"{donde}: una línea apunta a una estrella que no existe")
    for x, y in estrellas:
        if not (0 <= x <= 200 and 0 <= y <= 200):
            errores.append(f"{donde}: una estrella cae fuera del cuadro de 200×200")
    if not s.get("figura") or any(not d.strip() for d in s["figura"]):
        errores.append(f"{donde}: falta la figura (lista de trazos) o tiene un trazo vacío")
    return errores


def ilustraciones(signo):
    """(nombre de archivo, signo a dibujar) del signo y de cada variante por idioma (aries, conejo-vi…)."""
    yield signo["id"], signo
    for codigo, variante in sorted(signo.get("variantes", {}).items()):
        ident = f"{signo['id']}-{codigo}"
        yield ident, {**signo, "id": ident, "figura": variante["figura"], "constelacion": variante["constelacion"]}


def archivo_svg(signo, codigo):
    """Nombre del SVG que corresponde a un signo en un idioma (con su variante, si tiene)."""
    return f"{signo['id']}-{codigo}" if codigo in signo.get("variantes", {}) else signo["id"]


def validar_anio_nuevo(fechas):
    """Problemas de datos/anio-nuevo-lunar.json: {año: "AAAA-MM-DD"} (lista vacía si está bien)."""
    errores = []
    try:
        anios = sorted(int(a) for a in fechas)
    except ValueError:
        return ["anio-nuevo-lunar: hay un año que no es un número"]
    if anios != list(range(anios[0], anios[-1] + 1)):
        errores.append("anio-nuevo-lunar: faltan años en el medio")
    anterior = None
    for anio in anios:
        try:
            dia = datetime.date.fromisoformat(fechas[str(anio)])
        except ValueError:
            errores.append(f"anio-nuevo-lunar/{anio}: la fecha no es AAAA-MM-DD")
            continue
        if not (dia.year == anio and datetime.date(anio, 1, 21) <= dia <= datetime.date(anio, 2, 20)):
            errores.append(f"anio-nuevo-lunar/{anio}: {dia} no cae entre el 21/01 y el 20/02 de su año")
        if anterior and not 353 <= (dia - anterior).days <= 385:
            errores.append(f"anio-nuevo-lunar/{anio}: un año lunar dura entre 353 y 385 días")
        anterior = dia
    return errores


def cargar():
    with DATOS.open(encoding="utf-8") as f:
        datos = json.load(f)
    with zodiaco.ANIO_NUEVO.open(encoding="utf-8") as f:
        anio_nuevo = json.load(f)["fechas"]
    errores = (validar(datos) + validar_anio_nuevo(anio_nuevo)
               + validar_changelog(CHANGELOG.read_text(encoding="utf-8") if CHANGELOG.exists() else "", datos.get("version")))
    if errores:
        sys.exit("Los datos no son válidos:\n  " + "\n  ".join(errores))
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
    ]
    # Las 45 estrellas de fondo son puntos («M x y h0» con trazo redondo), agrupados por brillo y
    # tamaño en unos pocos trazos, en lugar de un <circle> por estrella: pesan una cuarta parte.
    grupos = {}
    for _ in range(45):
        x, y = entre(azar, 8, 192), entre(azar, 8, 192)
        r, o = entre(azar, 0.4, 1.1), entre(azar, 0.25, 0.8)
        brillo = min((0.3, 0.5, 0.75), key=lambda b: abs(b - o))
        grosor = min((1.0, 1.5, 2.0), key=lambda g: abs(g - 2 * r))
        grupos.setdefault((brillo, grosor), []).append(f"M{x:.1f} {y:.1f}h0")
    for (brillo, grosor), puntos in sorted(grupos.items()):
        partes.append(
            f'  <path stroke="#ffffff" stroke-opacity="{brillo}" stroke-width="{grosor}" '
            f'stroke-linecap="round" d="{"".join(puntos)}"/>'
        )
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


def decanatos(signo, datos, codigo):
    """Los tres decanatos de un signo con su regente, por ejemplo «Decanatos: 21/03 – 30/03 Marte · …»."""
    terminos = datos["terminos"]
    tramos = zodiaco.tramos_de_decanatos(signo)
    regentes = zodiaco.regentes_de_decanatos(signo["id"], datos)
    partes = [f"{_dia(a)} – {_dia(b)} {terminos[r][codigo]}" for (a, b), r in zip(tramos, regentes)]
    return f"{terminos['decanatos'][codigo]}: " + " · ".join(partes)


def texto_de_revision(estado):
    if estado["estado"] == "revisado":
        return f"Traducción revisada por {estado['por']} el {estado['fecha']}."
    return "Traducción sin revisar por una persona nativa."


def ficha_de(datos, codigo):
    signos, terminos = datos["signos"], datos["terminos"]
    lineas = [
        f"# {IDIOMAS[codigo]} (`{codigo}`)",
        "",
        "> Generado por `generar.py` desde `datos/signos.json`. No editar a mano.",
        "",
        f"> {texto_de_revision(datos['revision'][codigo])}",
        "",
    ]
    rtl = codigo in DERECHA_A_IZQUIERDA
    if rtl:
        # La línea en blanco después del <div> hace que GitHub siga leyendo Markdown adentro.
        lineas += [f'<div dir="rtl" lang="{codigo}">', ""]
    for tipo, clave in GRUPOS.items():
        lineas += [f"## {terminos[clave][codigo]}", ""]
        for s in signos:
            if s["tipo"] != tipo:
                continue
            encabezado = s["nombres"][codigo]
            if tipo == "occidental":
                encabezado = f"{s['simbolo']} {encabezado}"
            lineas += [f"### {encabezado}", "", f"*{rasgos(s, terminos, codigo)}*", ""]
            if tipo == "occidental":
                lineas += [decanatos(s, datos, codigo), ""]
            if tipo == "chino":
                lineas += [anios(s, datos, codigo), ""]
            lineas += [s["descripciones"][codigo], "", s["descripciones_largas"][codigo], ""]
    if rtl:
        lineas += ["</div>", ""]
    return "\n".join(lineas)


def indice_de(datos):
    lineas = [
        "# Fichas por idioma",
        "",
        "> Generado por `generar.py` desde `datos/signos.json`. No editar a mano.",
        "",
        f"Versión de los datos: {datos['version']}. La columna «Revisión» sale de `revision` en `datos/signos.json`.",
        "",
        "| Idioma | Código | Ficha | Revisión |",
        "| --- | --- | --- | --- |",
    ]
    for codigo, nombre in IDIOMAS.items():
        estado = datos["revision"][codigo]
        revision = f"Revisada por {estado['por']} ({estado['fecha']})" if estado["estado"] == "revisado" else "Sin revisar"
        lineas.append(f"| {nombre} | `{codigo}` | [{codigo}.md]({codigo}.md) | {revision} |")
    lineas.append("")
    return "\n".join(lineas)


GALERIA = RAIZ / "galeria.html"

_PLANTILLA_GALERIA = """<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Signos del zodiaco y horóscopo chino</title>
<!-- Generado por generar.py desde datos/signos.json. No editar a mano. -->
<style>
  :root { --fondo: #f4f4f6; --tarjeta: #ffffff; --texto: #1f2a44; --suave: #5b6478; --borde: #dde1ea; }
  @media (prefers-color-scheme: dark) {
    :root { --fondo: #10131a; --tarjeta: #181c26; --texto: #e8edf7; --suave: #a3abbd; --borde: #2a3040; }
  }
  body { margin: 0; background: var(--fondo); color: var(--texto); font: 15px/1.45 system-ui, sans-serif; }
  header { display: flex; flex-wrap: wrap; gap: 12px 20px; align-items: center; padding: 16px; }
  h1 { font-size: 1.2rem; margin: 0; flex: 1 1 100%; }
  label { color: var(--suave); }
  select { font: inherit; padding: 4px 6px; }
  h2 { font-size: 1rem; margin: 8px 16px; color: var(--suave); }
  .rejilla { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; padding: 0 16px 16px; }
  figure { margin: 0; background: var(--tarjeta); border: 1px solid var(--borde); border-radius: 12px; padding: 10px; }
  img { width: 100%; height: auto; display: block; border-radius: 8px; }
  .sin-fondo img { background: #ffffff; }
  figcaption { margin-top: 8px; }
  figcaption b { display: block; }
  figcaption span { color: var(--suave); font-size: .9em; }
</style>
</head>
<body>
<header>
  <h1 id="titulo">Signos del zodiaco y horóscopo chino</h1>
  <label><span id="et-idioma">Idioma</span> <select id="idioma"></select></label>
  <label><span id="et-ilustracion">Ilustración</span>
    <select id="variante">
      <option value="constelaciones" id="op-con-fondo">Con fondo</option>
      <option value="constelaciones-sin-fondo" id="op-sin-fondo">Sin fondo</option>
    </select>
  </label>
</header>
<main id="contenido"></main>
<script>
const DATOS = __DATOS__;
const idioma = document.getElementById("idioma");
const variante = document.getElementById("variante");
for (const [codigo, nombre] of DATOS.idiomas) idioma.add(new Option(nombre + " (" + codigo + ")", codigo));
function pintar() {
  const codigo = idioma.value, carpeta = variante.value, ui = DATOS.ui[codigo];
  const main = document.getElementById("contenido");
  // Toda la página en el idioma elegido: textos, lang y dirección.
  document.documentElement.lang = codigo;
  document.documentElement.dir = DATOS.rtl.includes(codigo) ? "rtl" : "ltr";
  document.title = ui.titulo;
  document.getElementById("titulo").textContent = ui.titulo;
  document.getElementById("et-idioma").textContent = ui.idioma;
  document.getElementById("et-ilustracion").textContent = ui.ilustracion;
  document.getElementById("op-con-fondo").textContent = ui.con_fondo;
  document.getElementById("op-sin-fondo").textContent = ui.sin_fondo;
  main.lang = codigo;
  main.dir = document.documentElement.dir;
  main.className = carpeta === "constelaciones" ? "" : "sin-fondo";
  main.replaceChildren();
  for (const [tipo, clave] of DATOS.grupos) {
    const h2 = document.createElement("h2");
    h2.textContent = ui[clave];
    const rejilla = document.createElement("div");
    rejilla.className = "rejilla";
    for (const s of DATOS.signos.filter(s => s.tipo === tipo)) {
      const fig = document.createElement("figure");
      const img = document.createElement("img");
      img.src = carpeta + "/" + (s.archivos[codigo] || s.id) + ".svg";
      img.alt = s.nombres[codigo];
      img.width = 200; img.height = 200;
      const pie = document.createElement("figcaption");
      const b = document.createElement("b");
      // U+FE0E pide el símbolo como texto; sin él, muchos sistemas pintan ♈ como emoji de color.
      b.textContent = (s.simbolo ? s.simbolo + "\\uFE0E " : "") + s.nombres[codigo];
      const span = document.createElement("span");
      span.textContent = s.descripciones[codigo];
      pie.append(b, span);
      fig.append(img, pie);
      rejilla.append(fig);
    }
    main.append(h2, rejilla);
  }
}
idioma.addEventListener("change", pintar);
variante.addEventListener("change", pintar);
pintar();
</script>
</body>
</html>
"""


def galeria_de(datos):
    """Página HTML autocontenida para ver las ilustraciones con su nombre en cada idioma."""
    signos = []
    for s in datos["signos"]:
        signos.append({
            "id": s["id"],
            "tipo": s["tipo"],
            "simbolo": s.get("simbolo", ""),
            "archivos": {c: archivo_svg(s, c) for c in s.get("variantes", {})},
            "nombres": s["nombres"],
            "descripciones": s["descripciones"],
        })
    contenido = {
        "idiomas": list(IDIOMAS.items()),
        "rtl": sorted(DERECHA_A_IZQUIERDA),
        "grupos": list(GRUPOS.items()),
        "ui": {c: {t: datos["terminos"][t][c] for t in sorted(TERMINOS_DE_INTERFAZ)} for c in IDIOMAS},
        "signos": signos,
    }
    # «</» no puede aparecer dentro de <script>: cerraría el bloque antes de tiempo.
    json_seguro = json.dumps(contenido, ensure_ascii=False, separators=(",", ":")).replace("</", "<\\/")
    return _PLANTILLA_GALERIA.replace("__DATOS__", json_seguro)


# Casos que tienen que fallar, para que cualquier port compruebe que también lanza excepción. «args» usa
# fechas como texto AAAA-MM-DD; el tipo de error es el de Python (TypeError o ValueError) y en un port
# basta con que lance cualquier excepción.
CASOS_DE_ERROR = (
    ("signo_occidental", ["1990-04-03", "marciano"]),
    ("cuspide", ["1990-04-03", "tropical", -1]),
    ("animal_chino", ["2026"]),
    ("animal_chino", [True]),
    ("animal_chino_de_fecha", ["2100-01-01"]),
    ("animal_chino_de_fecha", ["1899-12-31"]),
    ("nombre_del_anio", [2026, "xx"]),
    ("compatibilidad", ["rata", "aries"]),
    ("compatibilidad", ["rata", "no-existe"]),
    ("regentes_de_decanatos", ["rata"]),
    ("anios_del_animal", ["aries", 2000, 2030]),
    ("revision", ["xx"]),
)

# Instantes con huso horario: animal_chino_de_instante los pasa a la hora de China (UTC+8).
INSTANTES = (
    "2026-02-16T20:00:00+00:00",   # ya es 17/02 en China: Caballo
    "2026-02-16T15:59:59+00:00",   # 16/02 23:59:59 en China: Serpiente
    "2026-02-16T16:00:00+00:00",   # 17/02 00:00 en China: Caballo
    "2026-02-17T07:00:00-08:00",   # 17/02 23:00 en China: Caballo
    "2026-02-16T22:00:00-05:00",   # 17/02 11:00 en China: Caballo
    "2000-02-04T23:30:00-05:00",   # 05/02 12:30 en China: Dragón (Año Nuevo 05/02)
    "2000-02-05T00:30:00+09:00",   # 04/02 23:30 en China: Conejo
)


def _dias_del_anio_bisiesto():
    inicio = datetime.date(2024, 1, 1)
    return [inicio + datetime.timedelta(days=n) for n in range(366)]


def vectores_de(datos):
    """datos/vectores.json: las respuestas de zodiaco.py a un conjunto fijo de entradas.

    Es la referencia que comparten todos los ports (php/ hoy): cada uno los lee y comprueba que da lo
    mismo. Lo genera esta función con zodiaco.py, así que Python manda y --comprobar avisa si cambia.
    """
    dias = _dias_del_anio_bisiesto()
    ids = lambda signos: None if signos is None else [s["id"] for s in signos]
    cuerpo = {
        "version": datos["version"],
        "signo_occidental": {
            sistema: [[d.strftime("%m-%d"), zodiaco.signo_occidental(d, sistema, datos)["id"]] for d in dias]
            for sistema in zodiaco.SISTEMAS
        },
        "cuspide": [[d.strftime("%m-%d"), ids(zodiaco.cuspide(d, "tropical", 1, datos))] for d in dias],
        "animal_chino": [],
        "animal_chino_de_fecha": [],
        "animal_chino_de_instante": [],
        "nombre_del_anio": {},
        "compatibilidad": [],
        "decanato": {
            sistema: [
                [d.strftime("%m-%d"), x["signo"]["id"], x["numero"], x["regente"], x["signo_regente"]["id"]]
                for d in dias for x in [zodiaco.decanato(d, sistema, datos)]
            ]
            for sistema in zodiaco.SISTEMAS
        },
        "regentes_de_decanatos": {
            s["id"]: zodiaco.regentes_de_decanatos(s["id"], datos) for s in datos["signos"] if s["tipo"] == "occidental"
        },
        "tramos_de_decanatos": {
            sistema: {s["id"]: zodiaco.tramos_de_decanatos(s, sistema) for s in datos["signos"] if s["tipo"] == "occidental"}
            for sistema in zodiaco.SISTEMAS
        },
        "anios_del_animal": {
            s["id"]: zodiaco.anios_del_animal(s["id"], 1900, 2099, datos) for s in datos["signos"] if s["tipo"] == "chino"
        },
        "revision": {c: datos["revision"][c] for c in IDIOMAS},
        "errores": [{"funcion": f, "args": a} for f, a in CASOS_DE_ERROR],
    }
    for anio in range(1900, 2100):
        a = zodiaco.animal_chino(anio, datos)
        cuerpo["animal_chino"].append([anio, a["signo"]["id"], a["tronco"]["hanzi"], a["elemento_del_anio"], a["polaridad"]])
    for anio, dia in sorted(zodiaco.fechas_anio_nuevo().items()):
        for fecha in (dia - datetime.timedelta(days=1), dia):
            cuerpo["animal_chino_de_fecha"].append([fecha.isoformat(), zodiaco.animal_chino_de_fecha(fecha, datos)["anio_lunar"]])
    for texto in INSTANTES:
        x = zodiaco.animal_chino_de_fecha(datetime.datetime.fromisoformat(texto), datos)
        cuerpo["animal_chino_de_instante"].append([texto, x["anio_lunar"], x["signo"]["id"]])
    for codigo in IDIOMAS:
        cuerpo["nombre_del_anio"][codigo] = [zodiaco.nombre_del_anio(a, codigo, datos) for a in range(2020, 2036)]
    for tipo in ("occidental", "chino"):
        del_tipo = [s["id"] for s in datos["signos"] if s["tipo"] == tipo]
        for a in del_tipo:
            for b in del_tipo:
                c = zodiaco.compatibilidad(a, b, datos)
                cuerpo["compatibilidad"].append([a, b, c["relaciones"], c["afinidad"]])
    # Una línea por entrada de primer nivel y cada vector en una línea: se lee y el diff es corto.
    lineas = []
    for clave, valor in cuerpo.items():
        if isinstance(valor, dict) and clave != "revision":
            sub = []
            for k, v in valor.items():
                if isinstance(v, list) and len(v) > 40:  # una línea por entrada, como las listas de primer nivel
                    v = "[\n" + ",\n".join("      " + json.dumps(x, ensure_ascii=False, separators=(",", ":")) for x in v) + "\n    ]"
                else:
                    v = json.dumps(v, ensure_ascii=False, separators=(",", ":"))
                sub.append(f"    {json.dumps(k, ensure_ascii=False)}: {v}")
            texto = "{\n" + ",\n".join(sub) + "\n  }"
        elif isinstance(valor, list):
            texto = "[\n" + ",\n".join("    " + json.dumps(v, ensure_ascii=False, separators=(",", ":")) for v in valor) + "\n  ]"
        else:
            texto = json.dumps(valor, ensure_ascii=False, separators=(",", ":"))
        lineas.append(f"  {json.dumps(clave)}: {texto}")
    return "{\n" + ",\n".join(lineas) + "\n}\n"


def esperados(datos):
    archivos = {SALIDA_MD / "README.md": indice_de(datos), VECTORES: vectores_de(datos), GALERIA: galeria_de(datos)}
    for codigo in IDIOMAS:
        archivos[SALIDA_MD / f"{codigo}.md"] = ficha_de(datos, codigo)
    for s in datos["signos"]:
        if "constelacion" not in s:
            continue
        for ident, dibujo in ilustraciones(s):
            archivos[SALIDA_SVG / f"{ident}.svg"] = svg_de(dibujo)
            archivos[SALIDA_SVG_SIN_FONDO / f"{ident}.svg"] = svg_sin_fondo(dibujo)
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
