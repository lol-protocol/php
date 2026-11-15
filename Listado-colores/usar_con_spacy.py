#!/usr/bin/env python3
"""Ejemplo: reconocer los colores de un texto con los patrones de exportes/spacy/<código>.jsonl.

Uso, desde esta carpeta:
  python3 usar_con_spacy.py es "Una bufanda verde lima."
  python3 usar_con_spacy.py en "She wore a light blue dress."

Imprime una línea por color: el texto, el `id` del patrón (los candidatos separados por `;`) y la posición.
Necesita spaCy (`pip install spacy`) y nada más de este proyecto: copia `reconocedor()` a tu código. Etiqueta toda aparición de la forma aunque no sea un color (`rosa`, `lima`, `orange`) y no
desambigua: ver «spaCy y Universal Dependencies» en el README.
"""
import sys
from pathlib import Path

CARPETA = Path(__file__).resolve().parent


def reconocedor(codigo):
    """Un `Language` de spaCy con el `EntityRuler` de los colores del idioma `codigo`."""
    import spacy  # noqa: PLC0415  (opcional: solo hace falta para este ejemplo)
    try:
        nlp = spacy.blank(codigo)
    except ImportError:  # ja, ko, th, vi y sw: sin tokenizador propio instalado; el multilingüe parte por espacios
        nlp = spacy.blank("xx")
    ruler = nlp.add_pipe("entity_ruler", config={"phrase_matcher_attr": "LOWER"})
    ruler.from_disk(CARPETA / "exportes" / "spacy" / f"{codigo}.jsonl")
    return nlp


def colores(codigo, texto):
    """[(texto, id, inicio, fin)] de cada color que reconoce el idioma `codigo` en `texto`."""
    return [(e.text, e.ent_id_, e.start_char, e.end_char) for e in reconocedor(codigo)(texto).ents]


def main(argumentos):
    if len(argumentos) != 2 or not (CARPETA / "exportes" / "spacy" / f"{argumentos[0]}.jsonl").exists():
        print(__doc__, file=sys.stderr)
        return 2
    try:
        encontrados = colores(*argumentos)
    except ImportError as error:
        if error.name != "spacy":
            raise
        print("Falta spaCy: pip install spacy", file=sys.stderr)
        return 2
    for texto, ident, inicio, fin in encontrados:
        print(f"{texto}\t{ident}\t{inicio}-{fin}")
    return 0


if __name__ == "__main__":
    sys.exit(main(sys.argv[1:]))
