#!/usr/bin/env python3
"""Genera los marcalibros a partir de catalog/taxonomia.yaml.

Uso, desde la raíz del repo:
    python generator/build.py --all
    python generator/build.py --movimiento romanticismo

Reglas de formato:
- Cada marcalibro sale en un solo formato.
- La variante 01 de cada autor es JPG (paisaje rasterizado con grano).
- El resto son SVG.
"""
import argparse
import hashlib
import json
import os
import random
import shutil
import subprocess
import tempfile
from pathlib import Path

import numpy as np
import yaml
from PIL import Image

from plantillas import CONSTRUCTORES, NOMBRES_ARQUETIPOS, PALETAS
from qa import (ANCHO_MINIATURA, MIN_CONTRASTE_TEXTO, MIN_PIXELES_MOTIVO, UMBRAL_DUPLICADO, hoja_de_contacto,
                hoja_de_miniaturas, huella_visual, mejor_texto, parecido_a, pixeles_de_motivo, validar_jpg,
                validar_svg, validar_taxonomia)
from render_raster import a_imagen
from render_svg import a_svg
from scene import ALTO, ANCHO

VERSION_GENERADOR = "0.6.0"
MAX_INTENTOS = 30  # reintentos de semilla para evitar duplicados visuales
RAIZ = Path(__file__).resolve().parent.parent
CATALOGO = RAIZ / "catalog"
MAX_DIF_CHROMIUM = 3.0  # diferencia media tolerada entre Chromium y el renderer raster
GRANO_JPG = 0.018  # textura suave: sin grano, la variante JPG se confunde con las SVG


def semilla(movimiento, autor, variante, intento=0):
    clave = f"{movimiento}/{autor}/{variante:02d}"
    if intento:
        clave += f"#{intento}"
    return int(hashlib.sha256(clave.encode("utf-8")).hexdigest()[:12], 16)


def cargar_animos():
    return yaml.safe_load((CATALOGO / "animos.yaml").read_text(encoding="utf-8"))["animos"]


def recorrer(config, movimiento=None):
    for mov in config["movimientos"]:
        if movimiento and mov["id"] != movimiento:
            continue
        for autor in mov["autores"]:
            for v in range(1, len(autor.get("obras", [])) + 1):
                yield mov, autor, v


def asignar_combinaciones(mov, animos):
    """{(autor_id, variante): (arquetipo, paleta)} para todo un movimiento.
    La paleta sale del ánimo de la obra y el arquetipo de su `escena` (o, si no la
    fija, de una ordenación determinista). Se evita repetir el par arquetipo×paleta
    dentro de un autor (obligatorio si hay hueco) y se prefiere el menos usado
    del movimiento, para que obras de distintos autores no salgan casi iguales."""
    uso, resultado = {}, {}
    for autor in mov["autores"]:
        propios = set()
        for v, obra in enumerate(autor.get("obras", []), 1):
            escenas = [obra["escena"]] if obra.get("escena") else list(NOMBRES_ARQUETIPOS)
            if not obra.get("escena"):
                random.Random(f"{mov['id']}/{autor['id']}/{obra['id']}").shuffle(escenas)
            paletas = list(animos[obra["animo"]])
            giro = int(hashlib.sha256(f"{mov['id']}/{autor['id']}/{obra['id']}".encode()).hexdigest()[:6], 16) % len(paletas)
            paletas = paletas[giro:] + paletas[:giro]  # rota para que no todas empiecen por la misma
            pares = [(a, p) for a in escenas for p in paletas]
            libres = [par for par in pares if par not in propios] or pares
            elegido = min(libres, key=lambda par: uso.get(par, 0))  # min es estable: gana el primero
            uso[elegido] = uso.get(elegido, 0) + 1
            propios.add(elegido)
            resultado[(autor["id"], v)] = elegido
    return resultado


def combinacion(mov, autor, v, animos):
    return asignar_combinaciones(mov, animos)[(autor["id"], v)]


def construir(config, mov, autor, v, intento=0, animos=None, ocultar_motivo=False):
    s = semilla(mov["id"], autor["id"], v, intento)
    arquetipo, paleta = combinacion(mov, autor, v, animos)
    obra = autor["obras"][v - 1]
    motivo = obra["motivo"]
    formato = "jpg" if v == config["variante_jpg"] else "svg"
    grano = GRANO_JPG if formato == "jpg" else 0.0
    esc = CONSTRUCTORES[mov["id"]](s, paleta, motivo, grano, arquetipo, ocultar_motivo)
    ident = f"{mov['id']}-{autor['id']}-{v:02d}"
    entrada = {
        "id": ident,
        "movimiento": mov["id"],
        "autor": autor["nombre"],
        "autor_id": autor["id"],
        "epoca": autor["epoca"],
        "region": autor["region"],
        "variante": v,
        "obra_id": obra["id"],
        "obra": obra["titulo"],
        "animo": obra["animo"],
        "gutenberg_id": obra["gutenberg"]["id"],
        "gutenberg_estado": obra["gutenberg"]["estado"],
        "formato": formato,
        "motivo": motivo,
        "paleta": paleta,
        "arquetipo": arquetipo,
        "intento": intento,
        "semilla": s,
        "ancho": ANCHO,
        "alto": ALTO,
        "archivo": f"catalog/{formato}/{ident}.{formato}",
        "version_generador": VERSION_GENERADOR,
    }
    return entrada, esc


def escribir(entrada, esc):
    ruta = RAIZ / entrada["archivo"]
    ruta.parent.mkdir(parents=True, exist_ok=True)
    if entrada["formato"] == "svg":
        ruta.write_text(a_svg(esc), encoding="utf-8")
    else:
        a_imagen(esc).save(ruta, "JPEG", quality=85, optimize=True)
    entrada["sha256"] = hashlib.sha256(ruta.read_bytes()).hexdigest()
    return ruta


def chromium():
    """Ruta del binario de Chromium: variable CHROMIUM o los nombres habituales."""
    candidatos = [os.environ.get("CHROMIUM"), "/opt/pw-browsers/chromium"]
    candidatos += [shutil.which(n) for n in ("chromium", "chromium-browser", "google-chrome", "chrome")]
    return next((c for c in candidatos if c and Path(c).exists()), None)


def rasterizar_svg(ruta):
    """Rasteriza el SVG real con Chromium headless a ANCHO×ALTO exactos."""
    binario = chromium()
    if not binario:
        raise RuntimeError("no se encontró Chromium (define la variable CHROMIUM)")
    # El viewport headless es más bajo que --window-size: se pide una ventana mayor
    # y se recorta. El SVG se carga como <img>, igual que lo servirá el lector.
    with tempfile.TemporaryDirectory() as tmp:
        salida = Path(tmp) / "r.png"
        pagina = Path(tmp) / "p.html"
        pagina.write_text(
            '<!doctype html><body style="margin:0;background:#f0f">'
            f'<img src="{ruta.resolve().as_uri()}" width="{ANCHO}" height="{ALTO}" style="display:block">',
            encoding="utf-8")
        subprocess.run(
            [binario, "--headless", "--no-sandbox", "--disable-gpu", "--hide-scrollbars",
             f"--window-size={ANCHO},{ALTO + 300}", f"--screenshot={salida}", pagina.as_uri()],
            capture_output=True, check=True, timeout=60,
        )
        img = Image.open(salida).convert("RGB").crop((0, 0, ANCHO, ALTO))
    if img.size != (ANCHO, ALTO):
        raise RuntimeError(f"Chromium devolvió {img.size}, esperado {(ANCHO, ALTO)}")
    return img


def imagen_para_revision(entrada, esc, ruta):
    """JPG: se abre el archivo tal cual. SVG: se rasteriza con Chromium (QA fijo).
    Si Chromium no está, el build falla: el renderer raster ya no sirve de respaldo
    silencioso porque no verifica el archivo SVG real."""
    if entrada["formato"] == "jpg":
        return Image.open(ruta).convert("RGB"), "archivo"
    img = rasterizar_svg(ruta)
    # El SVG real debe parecerse al renderer raster; si no, algo diverge.
    dif = float(np.abs(np.asarray(img, float) - np.asarray(a_imagen(esc).convert("RGB"), float)).mean())
    if dif > MAX_DIF_CHROMIUM:
        raise RuntimeError(f"{entrada['id']}: SVG y renderer raster difieren {dif:.1f}/255")
    return img, f"svg vía Chromium (dif. {dif:.1f})"


SIN_COMPROBAR_VISIBILIDAD = {"ninguno", "luna"}  # sin motivo, o la luna: siempre grande


def elegir_pieza(config, mov, autor, v, animos, previas):
    """Primera semilla (intento 0, 1, 2…) cuya pieza no se parezca a las `previas` y cuyo
    motivo se vea en miniatura. Devuelve (entrada, escena, huella, intento, problema);
    `problema` es None si salió bien."""
    problema = None
    for intento in range(MAX_INTENTOS + 1):
        entrada, esc = construir(config, mov, autor, v, intento, animos)
        h = huella_visual(a_imagen(esc))
        parecido = parecido_a(h, previas)
        if parecido is not None:
            problema = f"sigue casi idéntico a {parecido[0]}"
            continue
        if entrada["motivo"] not in SIN_COMPROBAR_VISIBILIDAD:
            _, sin = construir(config, mov, autor, v, intento, animos, ocultar_motivo=True)
            px = pixeles_de_motivo(a_imagen(esc, ANCHO_MINIATURA), a_imagen(sin, ANCHO_MINIATURA))
            if px < MIN_PIXELES_MOTIVO:
                problema = f"el motivo '{entrada['motivo']}' casi no se ve en miniatura ({px} px)"
                continue
            entrada["pixeles_motivo"] = px
        return entrada, esc, h, intento, None
    return entrada, esc, h, intento, problema


def main():
    ap = argparse.ArgumentParser(description="Genera marcalibros desde la taxonomía.")
    ap.add_argument("--all", action="store_true", help="genera todos los movimientos")
    ap.add_argument("--movimiento", help="genera solo un movimiento, por id")
    args = ap.parse_args()
    if not args.all and not args.movimiento:
        ap.error("indica --all o --movimiento <id>")

    config = yaml.safe_load((CATALOGO / "taxonomia.yaml").read_text(encoding="utf-8"))
    motivos = yaml.safe_load((CATALOGO / "motivos.yaml").read_text(encoding="utf-8"))
    animos = cargar_animos()
    problemas = validar_taxonomia(config, motivos, animos, set(NOMBRES_ARQUETIPOS), PALETAS)
    for p in problemas:
        print(f"  ✗ taxonomía: {p}")
    if problemas:
        return 1
    filtro = None if args.all else args.movimiento

    generados, esc_por_id, rutas, huellas = [], {}, {}, {}
    fallos = 0
    avisos_texto, miniaturas = [], {}
    for mov, autor, v in recorrer(config, filtro):
        # Los duplicados se comparan dentro de cada movimiento, así el resultado
        # no depende de qué movimientos se generen en la misma ejecución.
        previas = {k: h for k, h in huellas.items() if k.startswith(mov["id"] + "-")}
        entrada, esc, h, intento, problema = elegir_pieza(config, mov, autor, v, animos, previas)
        if problema:
            fallos += 1
            print(f"  ✗ {entrada['id']}: {problema} tras {MAX_INTENTOS} reintentos de semilla")
        if intento:
            print(f"  ↻ {entrada['id']}: semilla cambiada {intento}× (duplicado visual o motivo invisible)")
        mini = a_imagen(esc, ANCHO_MINIATURA)
        texto, ratio = mejor_texto(mini)
        entrada["texto_recomendado"], entrada["contraste_texto"] = texto, round(ratio, 1)
        entrada["requiere_velo"] = ratio < MIN_CONTRASTE_TEXTO  # texto sobre la imagen solo con velo detrás
        if ratio < MIN_CONTRASTE_TEXTO:
            avisos_texto.append(f"{entrada['id']}: el mejor texto ({texto}) solo llega a {ratio:.1f}:1")
        miniaturas[entrada["id"]] = mini
        huellas[entrada["id"]] = h
        rutas[entrada["id"]] = escribir(entrada, esc)
        generados.append(entrada)
        esc_por_id[entrada["id"]] = esc

    if not generados:
        ap.error(f"no hay movimientos con id '{args.movimiento}'")

    # El manifest conserva los movimientos que no se regeneraron.
    manifiesto = CATALOGO / "manifest.json"
    previas = []
    if manifiesto.exists():
        previas = json.loads(manifiesto.read_text(encoding="utf-8"))["entradas"]
    rehechos = {e["movimiento"] for e in generados}
    entradas = sorted(
        [e for e in previas if e["movimiento"] not in rehechos] + generados,
        key=lambda e: e["id"],
    )
    manifiesto.write_text(
        json.dumps({"version_generador": VERSION_GENERADOR, "total": len(entradas), "entradas": entradas},
                   ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )

    # Validaciones
    errores = 0
    for e in generados:
        ruta = rutas[e["id"]]
        for problema in (validar_svg(ruta) if e["formato"] == "svg" else validar_jpg(ruta)):
            errores += 1
            print(f"  ✗ {e['id']}: {problema}")

    errores += fallos

    # Hojas de contacto para revisión humana: una por autor (10 piezas), no una de 100
    por_autor = {}
    for e in generados:
        por_autor.setdefault((e["movimiento"], e["autor_id"]), []).append(e)
    for (mov_id, autor_id), grupo in sorted(por_autor.items()):
        piezas = []
        for e in grupo:
            img, metodo = imagen_para_revision(e, esc_por_id[e["id"]], rutas[e["id"]])
            piezas.append((f"{e['id']}  [{e['formato']}]  {e['obra']}", img))
        hoja = CATALOGO / "revision" / f"hoja-{mov_id}-{autor_id}.jpg"
        hoja_de_contacto(piezas, hoja)
        print(f"  hoja de contacto: {hoja.relative_to(RAIZ)}")

    # Miniaturas a tamaño de lectura (350×50), una hoja por movimiento
    for mov_id in sorted(rehechos):
        hoja = CATALOGO / "revision" / f"miniaturas-{mov_id}.jpg"
        hoja_de_miniaturas([(f"{e['id']} · {e['obra']}", miniaturas[e["id"]])
                            for e in generados if e["movimiento"] == mov_id], hoja)
        print(f"  miniaturas: {hoja.relative_to(RAIZ)}")
    if avisos_texto:
        print(f"  ({len(avisos_texto)} de {len(generados)} piezas no llegan a {MIN_CONTRASTE_TEXTO}:1 con ningún tono de texto: "
              "el lector debe poner un velo detrás del texto)")
    for aviso in avisos_texto[:0]:
        print(f"  ⚠ requiere velo para texto: {aviso}")

    jpg = sum(1 for e in generados if e["formato"] == "jpg")
    print(f"\nGenerados: {len(generados)} (jpg: {jpg}, svg: {len(generados) - jpg}). "
          f"Errores de validación: {errores}. Manifest total: {len(entradas)}.")
    return 1 if errores else 0


if __name__ == "__main__":
    raise SystemExit(main())
