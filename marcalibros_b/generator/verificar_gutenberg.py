#!/usr/bin/env python3
"""Busca candidatos de Project Gutenberg para cada autor de la taxonomía (API Gutendex).

No modifica taxonomia.yaml: escribe catalog/revision/gutenberg-candidatos.md con los
resultados (id, título, idiomas, autores con fechas) para decidir a mano qué ids se
marcan como `verificado`. Requiere acceso de red a gutendex.com.

    python generator/verificar_gutenberg.py [--movimiento romanticismo]
    python generator/verificar_gutenberg.py --csv    # prellena catalog/revision/gutenberg-prellenado.csv

Con `--csv` además prerrellena, para `gutenberg_manual.py aplicar`, los ids que Gutendex da sin ambigüedad (ver
`prellenar`): se mira el CSV (la columna `nota` dice de dónde sale cada fila) y se aplica con
`python generator/gutenberg_manual.py aplicar catalog/revision/gutenberg-prellenado.csv`.
"""
import argparse
import csv
import json
import urllib.parse
import urllib.request
from pathlib import Path

import yaml

import gutenberg_manual
from modelo import anios_de_vida, autor_del_libro, obra_base, obras_del_libro

RAIZ = Path(__file__).resolve().parent.parent
API = "https://gutendex.com/books/"


def consultar(busqueda):
    """Todas las páginas de resultados de Gutendex para una búsqueda."""
    url, libros = f"{API}?{urllib.parse.urlencode({'search': busqueda})}", []
    while url:
        with urllib.request.urlopen(url, timeout=30) as r:
            datos = json.load(r)
        libros += datos["results"]
        url = datos.get("next")
    return libros


def _fila(libro, autor):
    """Fila del informe. «Coincide con» lista las obras del catálogo cuyo título se parece al del libro."""
    autores = "; ".join(f"{a['name']} ({a.get('birth_year')}–{a.get('death_year')})" for a in libro["authors"])
    obras = ", ".join(o["titulo"] for o, ok in zip(autor["obras"], obras_del_libro(libro, autor)) if ok)
    return f"| {libro['id']} | {libro['title']} | {', '.join(libro['languages'])} | {autores} | {obras} |"


def informe(config, movimiento=None, buscar=consultar):
    lineas = ["# Candidatos de Gutenberg (sin verificar)", "",
              "Generado por `generator/verificar_gutenberg.py`. Marcar `verificado` en la taxonomía "
              "solo con id comprobado a mano contra gutenberg.org/ebooks/<id>.", ""]
    for mov in config["movimientos"]:
        if movimiento and mov["id"] != movimiento:
            continue
        for autor in mov["autores"]:
            todos = buscar(autor["nombre"])
            libros = [b for b in todos if autor_del_libro(b, autor)]  # la búsqueda por nombre trae también a otros
            lineas += [f"## {autor['nombre']} ({mov['id']}/{autor['id']}) — {len(libros)} del autor, {len(todos) - len(libros)} descartados", "",
                       "Obras de la taxonomía: " + (", ".join(o["titulo"] for o in autor["obras"]) or "(ninguna aún)"),
                       "", "| id | título | idiomas | autores | coincide con |", "|---|---|---|---|---|"]
            lineas += [_fila(b, autor) for b in libros] + [""]
    return "\n".join(lineas) + "\n"


MUERTE_CON_DERECHOS = 1955  # como en qa.MUERTE_MAXIMA: un autor muerto después no está en Gutenberg salvo excepciones


def _elegir(candidatos):
    """El libro de una obra entre varios candidatos (Gutendex repite ediciones): el más descargado si todos
    comparten título; None si hay títulos distintos (ambiguo)."""
    titulos = {c["title"].strip().lower() for c in candidatos}
    if len(titulos) != 1:
        return None
    return max(candidatos, key=lambda c: (c.get("download_count", 0), -c["id"]))


def prellenar(config, buscar=consultar, movimiento=None):
    """Filas para `gutenberg_manual.py aplicar` con lo que Gutendex permite afirmar:
    - obra: `verificado` con el id del libro si su autor y su título coinciden con una sola edición (o varias con el
      mismo título: se toma la más descargada); sin candidatos o con títulos distintos, se deja en blanco;
    - tema «de» otra obra: el id de esa obra, solo si ella quedó verificada;
    - autor sin ningún libro y muerto después de 1955: `no_disponible`. Gutendex no da ids de autor: los demás quedan en blanco."""
    filas = {f["pieza"] or f["autor"]: f for f in gutenberg_manual.filas(config)}
    for mov in config["movimientos"]:
        if movimiento and mov["id"] != movimiento:
            continue
        for autor in mov["autores"]:
            libros = [b for b in buscar(autor["nombre"]) if autor_del_libro(b, autor)]
            por_obra = {}
            for libro in libros:
                for v, coincide in enumerate(obras_del_libro(libro, autor), 1):
                    if coincide and not autor["obras"][v - 1].get("de"):
                        por_obra.setdefault(v, []).append(libro)
            ids_base = {}
            for v, obra in enumerate(autor["obras"], 1):
                fila = filas[f"{mov['id']}-{autor['id']}-{v:02d}"]
                if v in por_obra:
                    elegido = _elegir(por_obra[v])
                    if elegido:
                        fila.update(estado="verificado", id=str(elegido["id"]),
                                    nota=f"Gutendex: «{elegido['title']}», {len(por_obra[v])} edición(es)")
                        ids_base[v - 1] = fila
                    else:
                        fila["nota"] = "ambiguo: " + "; ".join(sorted({f"{c['id']} «{c['title']}»" for c in por_obra[v]}))
            for v, obra in enumerate(autor["obras"], 1):  # los temas «de» una obra heredan su id
                base_i = obra_base(autor, obra["de"]) if obra.get("de") else None
                if base_i in ids_base:
                    base = ids_base[base_i]
                    filas[f"{mov['id']}-{autor['id']}-{v:02d}"].update(
                        estado="verificado", id=base["id"], nota=f"tema de «{obra['de']}» (id de la obra)")
            anios = anios_de_vida(autor.get("epoca"))
            if not libros and anios and anios[1] > MUERTE_CON_DERECHOS:
                filas[f"{mov['id']}/{autor['id']}"].update(estado="no_disponible", nota=f"sin libros en Gutendex; murió en {anios[1]}")
    return list(filas.values())


def escribir_csv(filas, ruta):
    ruta.parent.mkdir(parents=True, exist_ok=True)
    with open(ruta, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, gutenberg_manual.CAMPOS)
        w.writeheader()
        w.writerows(filas)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--movimiento")
    ap.add_argument("--csv", action="store_true", help="prellena catalog/revision/gutenberg-prellenado.csv")
    args = ap.parse_args()
    config = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    if args.csv:
        destino = RAIZ / "catalog" / "revision" / "gutenberg-prellenado.csv"
        filas = prellenar(config, movimiento=args.movimiento)
        escribir_csv(filas, destino)
        hechas = sum(1 for f in filas if f["estado"])
        print(f"escrito {destino.relative_to(RAIZ)}: {hechas} de {len(filas)} filas prellenadas; revísalo y aplícalo con "
              f"python generator/gutenberg_manual.py aplicar {destino.relative_to(RAIZ)}")
        return
    salida = RAIZ / "catalog" / "revision" / "gutenberg-candidatos.md"
    salida.parent.mkdir(parents=True, exist_ok=True)
    salida.write_text(informe(config, args.movimiento), encoding="utf-8")
    print(f"escrito {salida.relative_to(RAIZ)}")


if __name__ == "__main__":
    main()
