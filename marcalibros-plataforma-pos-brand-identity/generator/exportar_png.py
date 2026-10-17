#!/usr/bin/env python3
"""Genera las versiones PNG de los marcalibros del catálogo, sin guardarlas en git.

Los PNG salen de las mismas figuras que el SVG y la vista previa, así que coinciden
con ellos. Se escriben en `catalog/png/` (ignorada por git), en dos tamaños:
`<id>.png` (1400 x 200) y `<id>@2x.png` (2800 x 400). La aplicación ofrece la
descarga en PNG solo de los marcalibros que tengan su archivo ahí.

Uso:
    python3 generator/exportar_png.py                          # todos (unos minutos)
    python3 generator/exportar_png.py --categoria poesia       # una categoría
    python3 generator/exportar_png.py --id mb-leyes-07 --id mb-teatro-03
    python3 generator/exportar_png.py --faltantes              # solo los que no existan todavía

Hay que generar antes el catálogo (`generar_marcalibros.py`): se usa su manifiesto
para saber qué marcalibros existen.
"""
import argparse
import json
import re
import sys
from pathlib import Path

from generar_marcalibros import CATEGORIAS, rasterizar, figuras_variante

TAMANOS = {"": 1400, "@2x": 2800}
SUPERSAMPLE_PNG = 2   # basta para bordes suaves a este tamaño y es bastante más rápido que 4


def variantes(manifiesto):
    """(id, categoría, número) de cada marcalibro del manifiesto."""
    por_slug = {c["slug"]: c for c in CATEGORIAS}
    for entrada in manifiesto["marcalibros"]:
        m = re.fullmatch(r"mb-(.+)-(\d+)", entrada["id"])
        if not m or m.group(1) not in por_slug:
            raise SystemExit(f"El manifiesto tiene un identificador que el generador no conoce: {entrada['id']}")
        yield entrada["id"], por_slug[m.group(1)], int(m.group(2))


def main():
    ap = argparse.ArgumentParser(description="Genera los PNG de los marcalibros del catálogo.")
    ap.add_argument("--id", action="append", default=[], help="identificador de un marcalibro (se puede repetir)")
    ap.add_argument("--categoria", action="append", default=[], help="slug de una categoría (se puede repetir)")
    ap.add_argument("--faltantes", action="store_true", help="omitir los que ya tienen sus dos PNG")
    ap.add_argument("--salida", type=Path, help="carpeta de salida (por defecto, catalog/png)")
    args = ap.parse_args()

    raiz = Path(__file__).resolve().parent.parent
    manifiesto = json.loads((raiz / "catalog" / "manifest.json").read_text(encoding="utf-8"))
    salida = args.salida or raiz / "catalog" / "png"
    salida.mkdir(parents=True, exist_ok=True)

    todas = list(variantes(manifiesto))
    ids_validos = {id_ for id_, _, _ in todas}
    desconocidos = [i for i in args.id if i not in ids_validos]
    slugs = {c["slug"] for c in CATEGORIAS}
    desconocidos += [c for c in args.categoria if c not in slugs]
    if desconocidos:
        ap.error("no existen: " + ", ".join(desconocidos))

    elegidas = [v for v in todas
                if (not args.id and not args.categoria) or v[0] in args.id or v[1]["slug"] in args.categoria]
    hechos = 0
    for id_, cat, i in elegidas:
        rutas = {sufijo: salida / f"{id_}{sufijo}.png" for sufijo in TAMANOS}
        if args.faltantes and all(r.exists() for r in rutas.values()):
            continue
        grupos, _ = figuras_variante(cat, i)
        for sufijo, ancho in TAMANOS.items():
            tmp = rutas[sufijo].with_suffix(".tmp")
            rasterizar(grupos, ancho, SUPERSAMPLE_PNG).save(tmp, "PNG", optimize=True)
            tmp.replace(rutas[sufijo])   # la aplicación nunca ve un PNG a medio escribir
        hechos += 1
        if hechos % 100 == 0:
            print(f"  {hechos} de {len(elegidas)}…", file=sys.stderr)
    print(f"PNG generados: {hechos} marcalibros ({hechos * len(TAMANOS)} archivos) en {salida}")


if __name__ == "__main__":
    main()
