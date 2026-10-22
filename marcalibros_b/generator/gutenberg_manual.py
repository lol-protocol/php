#!/usr/bin/env python3
"""Verificación de ids de Project Gutenberg a mano (cuando no hay red desde el entorno).

    python generator/gutenberg_manual.py lista            # escribe REVISION-GUTENBERG.csv
    python generator/gutenberg_manual.py aplicar FILE.csv # vuelca lo rellenado en catalog/taxonomia.yaml

El CSV tiene una fila por autor (`pieza` vacía y `autor` con «movimiento/autor») y una por obra
(`pieza` = id de la pieza). Se rellenan solo `estado` (`verificado` o `no_disponible`) e `id`
(número de la URL gutenberg.org/ebooks/<id>; obligatorio con `verificado`, vacío con
`no_disponible`). Las filas sin `estado` no se tocan. `aplicar` edita solo esos bloques de la
taxonomía (el resto del archivo queda igual) y la valida antes de escribir.
"""
import argparse
import csv
import re
import sys
import urllib.parse
from pathlib import Path

import yaml

RAIZ = Path(__file__).resolve().parent.parent
TAXONOMIA = RAIZ / "catalog" / "taxonomia.yaml"
CSV_LISTA = RAIZ / "REVISION-GUTENBERG.csv"
CAMPOS = ["pieza", "autor", "obra", "buscar", "estado", "id"]
ESTADOS = {"verificado", "no_disponible"}


def _busqueda(autor, titulo=""):
    q = urllib.parse.urlencode({"query": f"{autor} {titulo}".strip()})
    return f"https://www.gutenberg.org/ebooks/search/?{q}"


def filas(config):
    out = []
    for mov in config["movimientos"]:
        for autor in mov["autores"]:
            out.append({"pieza": "", "autor": f"{mov['id']}/{autor['id']}", "obra": autor["nombre"],
                        "buscar": _busqueda(autor["nombre"]), "estado": "", "id": ""})
            for v, obra in enumerate(autor.get("obras", []), 1):
                out.append({"pieza": f"{mov['id']}-{autor['id']}-{v:02d}", "autor": autor["nombre"], "obra": obra["titulo"],
                            "buscar": _busqueda(autor["nombre"], obra.get("de") or obra["titulo"]), "estado": "", "id": ""})
    return out


def validar_filas(rellenas, config):
    """Errores de las filas rellenadas (las que tienen `estado`)."""
    piezas = {f"{m['id']}-{a['id']}-{v:02d}" for m in config["movimientos"] for a in m["autores"]
              for v in range(1, len(a.get("obras", [])) + 1)}
    autores = {f"{m['id']}/{a['id']}" for m in config["movimientos"] for a in m["autores"]}
    errores = []
    for n, f in enumerate(rellenas, 2):
        est, ident = f["estado"].strip(), f["id"].strip()
        donde = f"fila {n} ({f['pieza'] or f['autor']})"
        if f["pieza"] and f["pieza"] not in piezas:
            errores.append(f"{donde}: pieza desconocida")
        if not f["pieza"] and f["autor"] not in autores:
            errores.append(f"{donde}: autor desconocido")
        if est not in ESTADOS:
            errores.append(f"{donde}: estado «{est}» no válido (verificado | no_disponible)")
        elif est == "verificado" and not re.fullmatch(r"[1-9][0-9]{0,6}", ident):
            errores.append(f"{donde}: «verificado» necesita un id numérico")
        elif est == "no_disponible" and ident:
            errores.append(f"{donde}: «no_disponible» no lleva id")
    return errores


def aplicar_texto(texto, cambios):
    """Sustituye en el texto de la taxonomía los bloques `gutenberg` indicados.
    `cambios`: {clave: (estado, id)} con clave `mov/autor` o la id de la pieza."""
    lineas, mov, autor, v, salida, i = texto.split("\n"), None, None, 0, [], 0
    hechos = set()
    while i < len(lineas):
        l = lineas[i]
        if (m := re.match(r"^- id: (\S+)", l)):
            mov = m.group(1)
        elif (m := re.match(r"^  - id: (\S+)", l)):
            autor, v = m.group(1), 0
        elif re.match(r"^    - id: ", l):
            v += 1
        sangria = "      " if l.startswith("    gutenberg_autor:") else "        " if l.startswith("      gutenberg:") else None
        clave = f"{mov}/{autor}" if l.startswith("    gutenberg_autor:") else f"{mov}-{autor}-{v:02d}"
        salida.append(l)
        if sangria and clave in cambios and l.rstrip().endswith(":"):
            estado, ident = cambios[clave]
            salida += [f"{sangria}id: {ident if ident else 'null'}", f"{sangria}estado: {estado}"]
            i += 2  # se descartan las dos líneas antiguas (id y estado)
            hechos.add(clave)
        i += 1
    faltan = set(cambios) - hechos
    if faltan:
        raise ValueError(f"no se encontró el bloque gutenberg de: {sorted(faltan)}")
    return "\n".join(salida)


def aplicar(ruta_csv, taxonomia=TAXONOMIA, escribir=True):
    config = yaml.safe_load(taxonomia.read_text(encoding="utf-8"))
    with open(ruta_csv, newline="", encoding="utf-8") as f:
        todas = list(csv.DictReader(f))
    rellenas = [r for r in todas if r["estado"].strip()]
    errores = validar_filas(rellenas, config)
    if errores:
        return errores
    cambios = {(r["pieza"] or r["autor"]): (r["estado"].strip(), r["id"].strip()) for r in rellenas}
    nuevo = aplicar_texto(taxonomia.read_text(encoding="utf-8"), cambios)
    yaml.safe_load(nuevo)  # sigue siendo YAML válido
    if escribir:
        taxonomia.write_text(nuevo, encoding="utf-8")
    return []


def main(argv=None):
    ap = argparse.ArgumentParser()
    ap.add_argument("orden", choices=["lista", "aplicar"])
    ap.add_argument("archivo", nargs="?")
    args = ap.parse_args(argv)
    if args.orden == "lista":
        config = yaml.safe_load(TAXONOMIA.read_text(encoding="utf-8"))
        with open(CSV_LISTA, "w", newline="", encoding="utf-8") as f:
            w = csv.DictWriter(f, CAMPOS)
            w.writeheader()
            w.writerows(filas(config))
        print(f"{CSV_LISTA.relative_to(RAIZ)}: rellena `estado` e `id` y aplica con: aplicar <archivo>")
        return 0
    if not args.archivo:
        ap.error("indica el CSV rellenado")
    errores = aplicar(args.archivo)
    for e in errores:
        print("✗", e)
    if not errores:
        print("taxonomía actualizada; reconstruye con: python generator/build.py --all")
    return 1 if errores else 0


if __name__ == "__main__":
    sys.exit(main())
