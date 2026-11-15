"""Rehace los subconjuntos de las fuentes Noto (SIL OFL) de generator/fuentes/.

Las fuentes completas pesan decenas de MB (CJK); a las piezas solo les hacen falta los glifos de
las frases. Cada subconjunto lleva los caracteres de la `muestra` de catalog/escrituras.yaml y los de
las frases de ese idioma en catalog/frases.yaml, más los signos básicos, y conserva las tablas de
composición (GSUB/GPOS) que HarfBuzz necesita para ligaduras árabes y conjuntos devanagari.

Uso: python generator/subconjuntos.py --origen <carpeta con las fuentes completas> [--comprobar]
Las fuentes completas se bajan de https://github.com/notofonts y https://github.com/notofonts/noto-cjk.
Tras añadir una frase en otro carácter, vuelve a correrlo y commitea lo que cambie.
"""
import argparse
import sys
from pathlib import Path

import yaml
from fontTools import subset
from fontTools.ttLib import TTFont
from fontTools.ttLib.tables import otBase

RAIZ = Path(__file__).resolve().parent.parent
BASICOS = " ,.;:!?-—()«»“”‘’、。，．；：！？「」『』（）·"


def caracteres(idioma, fuente_clave, esc, frases):
    """Caracteres que debe tener la fuente `fuente_clave`: muestra + frases de los idiomas que la usan."""
    idiomas = [i for i, f in esc["idiomas"].items() if f == fuente_clave]
    texto = BASICOS + "".join(esc["muestras"].get(i, "") for i in idiomas)
    for lista in frases["movimientos"].values():
        texto += "".join(q["texto"] for q in lista if q.get("idioma") in idiomas)
    return "".join(sorted(set(texto)))


def construir(origen, destino, texto):
    opciones = subset.Options()
    opciones.layout_features = ["*"]
    opciones.notdef_outline = True
    opciones.name_IDs = ["*"]
    opciones.hinting = False
    opciones.desubroutinize = True
    fuente = subset.load_font(str(origen), opciones)
    otBase.have_uharfbuzz = False  # el reempaquetado de uharfbuzz 0.43 no cuadra con fontTools 4.68
    sub = subset.Subsetter(opciones)
    sub.populate(text=texto)
    sub.subset(fuente)
    subset.save_font(fuente, str(destino), opciones)


def main(argv=None):
    ap = argparse.ArgumentParser(description=__doc__.split("\n")[0])
    ap.add_argument("--origen", required=True, type=Path)
    ap.add_argument("--comprobar", action="store_true", help="solo verifica que cada subconjunto cubre sus caracteres")
    a = ap.parse_args(argv)
    esc = yaml.safe_load((RAIZ / "catalog" / "escrituras.yaml").read_text(encoding="utf-8"))
    frases = yaml.safe_load((RAIZ / "catalog" / "frases.yaml").read_text(encoding="utf-8"))
    carpeta = Path(__file__).with_name("fuentes")
    mal = 0
    for clave, datos in esc["fuentes"].items():
        texto = caracteres(None, clave, esc, frases)
        destino = carpeta / datos["archivo"]
        if a.comprobar:
            cmap = TTFont(destino).getBestCmap()
            # los signos básicos son «lo que haya»: solo se exige lo que escriben las frases y muestras
            exigido = set(texto) - set(BASICOS)
            faltan = [c for c in sorted(exigido) if ord(c) not in cmap and not c.isspace()]
            if faltan:
                print(f"{datos['archivo']}: faltan {faltan}")
                mal += 1
            continue
        construir(a.origen / datos["origen"], destino, texto)
        print(f"{destino.name}: {destino.stat().st_size / 1024:.0f} KB, {len(texto)} caracteres")
    return 1 if mal else 0


if __name__ == "__main__":
    sys.exit(main())
