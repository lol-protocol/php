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

from modelo import autor_del_libro, obras_del_libro

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
