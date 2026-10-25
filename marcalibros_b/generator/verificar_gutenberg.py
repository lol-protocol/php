#!/usr/bin/env python3
"""Busca candidatos de Project Gutenberg para cada autor de la taxonomía (API Gutendex).

No modifica taxonomia.yaml: escribe catalog/revision/gutenberg-candidatos.md con los
resultados (id, título, idiomas, autores con fechas) para decidir a mano qué ids se
marcan como `verificado`. Requiere acceso de red a gutendex.com.

    python generator/verificar_gutenberg.py [--movimiento romanticismo]
"""
import argparse
import json
import urllib.parse
import urllib.request
from pathlib import Path

import yaml

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


def _fila(libro):
    autores = "; ".join(f"{a['name']} ({a.get('birth_year')}–{a.get('death_year')})" for a in libro["authors"])
    return f"| {libro['id']} | {libro['title']} | {', '.join(libro['languages'])} | {autores} |"


def informe(config, movimiento=None, buscar=consultar):
    lineas = ["# Candidatos de Gutenberg (sin verificar)", "",
              "Generado por `generator/verificar_gutenberg.py`. Marcar `verificado` en la taxonomía "
              "solo con id comprobado a mano contra gutenberg.org/ebooks/<id>.", ""]
    for mov in config["movimientos"]:
        if movimiento and mov["id"] != movimiento:
            continue
        for autor in mov["autores"]:
            libros = buscar(autor["nombre"])
            lineas += [f"## {autor['nombre']} ({mov['id']}/{autor['id']}) — {len(libros)} resultados", "",
                       "Obras de la taxonomía: " + (", ".join(o["titulo"] for o in autor["obras"]) or "(ninguna aún)"),
                       "", "| id | título | idiomas | autores |", "|---|---|---|---|"]
            lineas += [_fila(b) for b in libros] + [""]
    return "\n".join(lineas) + "\n"


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--movimiento")
    args = ap.parse_args()
    config = yaml.safe_load((RAIZ / "catalog" / "taxonomia.yaml").read_text(encoding="utf-8"))
    salida = RAIZ / "catalog" / "revision" / "gutenberg-candidatos.md"
    salida.parent.mkdir(parents=True, exist_ok=True)
    salida.write_text(informe(config, args.movimiento), encoding="utf-8")
    print(f"escrito {salida.relative_to(RAIZ)}")


if __name__ == "__main__":
    main()
