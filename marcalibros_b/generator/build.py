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
import io
import json
import subprocess
from pathlib import Path

import yaml
from PIL import Image

from plantillas import CONSTRUCTORES
from qa import hoja_de_contacto, huella_visual, revisar_duplicados, validar_jpg, validar_svg
from render_raster import a_imagen
from render_svg import a_svg
from scene import ALTO, ANCHO

VERSION_GENERADOR = "0.1.0"
RAIZ = Path(__file__).resolve().parent.parent
CATALOGO = RAIZ / "catalog"
GRANO_JPG = 0.018  # textura suave: sin grano, la variante JPG se confunde con las SVG


def semilla(movimiento, autor, variante):
    clave = f"{movimiento}/{autor}/{variante:02d}"
    return int(hashlib.sha256(clave.encode("utf-8")).hexdigest()[:12], 16)


def recorrer(config, movimiento=None):
    for mov in config["movimientos"]:
        if movimiento and mov["id"] != movimiento:
            continue
        for autor in mov["autores"]:
            for v in range(1, autor["variantes"] + 1):
                yield mov, autor, v


def construir(config, mov, autor, v):
    s = semilla(mov["id"], autor["id"], v)
    paleta = mov["paletas"][(v - 1) % len(mov["paletas"])]
    motivo = autor["motivos"][(v - 1) % len(autor["motivos"])]
    formato = "jpg" if v == config["variante_jpg"] else "svg"
    grano = GRANO_JPG if formato == "jpg" else 0.0
    esc = CONSTRUCTORES[mov["id"]](s, paleta, motivo, grano)
    ident = f"{mov['id']}-{autor['id']}-{v:02d}"
    entrada = {
        "id": ident,
        "movimiento": mov["id"],
        "autor": autor["nombre"],
        "autor_id": autor["id"],
        "epoca": autor["epoca"],
        "region": autor["region"],
        "variante": v,
        "formato": formato,
        "motivo": motivo,
        "paleta": paleta,
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


def imagen_para_revision(entrada, esc, ruta):
    """JPG: se abre el archivo tal cual. SVG: se rasteriza con ImageMagick para revisar
    el archivo real; si no está disponible, se usa el renderer raster como respaldo."""
    if entrada["formato"] == "jpg":
        return Image.open(ruta).convert("RGB"), "archivo"
    try:
        png = subprocess.run(
            ["convert", "-background", "none", str(ruta), "-resize", f"{ANCHO}x{ALTO}!", "png:-"],
            capture_output=True, check=True, timeout=60,
        ).stdout
        return Image.open(io.BytesIO(png)).convert("RGB"), "svg vía ImageMagick"
    except (OSError, subprocess.SubprocessError):
        return a_imagen(esc), "svg vía respaldo raster"


def main():
    ap = argparse.ArgumentParser(description="Genera marcalibros desde la taxonomía.")
    ap.add_argument("--all", action="store_true", help="genera todos los movimientos")
    ap.add_argument("--movimiento", help="genera solo un movimiento, por id")
    args = ap.parse_args()
    if not args.all and not args.movimiento:
        ap.error("indica --all o --movimiento <id>")

    config = yaml.safe_load((CATALOGO / "taxonomia.yaml").read_text(encoding="utf-8"))
    filtro = None if args.all else args.movimiento

    generados, esc_por_id, rutas = [], {}, {}
    for mov, autor, v in recorrer(config, filtro):
        entrada, esc = construir(config, mov, autor, v)
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

    huellas = {eid: huella_visual(a_imagen(esc)) for eid, esc in esc_por_id.items()}
    for aviso in revisar_duplicados(huellas):
        print(f"  ⚠ posible duplicado: {aviso}")

    # Hojas de contacto para revisión humana: una por movimiento
    for mov_id in sorted(rehechos):
        piezas = []
        for e in generados:
            if e["movimiento"] != mov_id:
                continue
            img, metodo = imagen_para_revision(e, esc_por_id[e["id"]], rutas[e["id"]])
            piezas.append((f"{e['id']}  [{e['formato']}]", img))
            print(f"  · {e['id']}: revisado con {metodo}")
        hoja = CATALOGO / "revision" / f"hoja-{mov_id}.jpg"
        hoja_de_contacto(piezas, hoja)
        print(f"  hoja de contacto: {hoja.relative_to(RAIZ)}")

    jpg = sum(1 for e in generados if e["formato"] == "jpg")
    print(f"\nGenerados: {len(generados)} (jpg: {jpg}, svg: {len(generados) - jpg}). "
          f"Errores de validación: {errores}. Manifest total: {len(entradas)}.")
    return 1 if errores else 0


if __name__ == "__main__":
    raise SystemExit(main())
