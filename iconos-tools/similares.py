#!/usr/bin/env python3
"""Busca iconos casi indistinguibles al verlos pequeños. Código de salida 1 si encuentra alguno.

    python3 iconos-tools/similares.py [--minimo 0.8] [--lista 10] [--raiz RUTA]

Cada icono de la variante gris se dibuja a 96x96 sobre blanco y se reduce a 24x24 promediando por áreas.
La distancia entre dos iconos es la diferencia media por píxel, en una escala de 0 a 255: 1,0 equivale a
unos dos píxeles de los 576 totalmente distintos. Se compara dentro de cada set, nunca entre sets.

Necesita Pillow y resvg-py (pip install -r iconos-tools/requirements.txt). Las versiones están fijadas
porque la distancia depende del renderizador.
"""
import argparse
import io
import itertools
import operator
import sys
from pathlib import Path

try:
    import resvg_py
    from PIL import Image
except ImportError:
    sys.exit('Faltan dependencias: pip install -r iconos-tools/requirements.txt')

ROOT = Path(__file__).resolve().parent.parent
SETS = ('iconos-genealogia', 'iconos-biblioteca')
RENDER, THUMB = 96, 24

# Pares que ya estaban por debajo del mínimo al añadir esta comprobación. Cada uno se distingue solo por un
# detalle de unos pocos píxeles; lo ideal es rediseñarlos y quitarlos de aquí.
ACEPTADOS = {
    frozenset(pair): reason for pair, reason in (
        (('person_second_cousin', 'person_second_cousin_f'), 'solo cambia la forma de un nodo pequeño'),
        (('person_great_aunt', 'person_great_uncle'), 'solo cambia la forma de un nodo pequeño'),
        (('person_foster_mother', 'person_godmother'), 'solo cambia una marca de unos 4 px sobre un enlace corto'),
        (('person_foster_father', 'person_godfather'), 'solo cambia una marca de unos 4 px sobre un enlace corto'),
        (('person_foster_son', 'person_godson'), 'solo cambia una marca de unos 4 px sobre un enlace corto'),
        (('person_foster_daughter', 'person_goddaughter'), 'solo cambia una marca de unos 4 px sobre un enlace corto'),
    )
}


def thumbnail(path):
    png = resvg_py.svg_to_bytes(svg_path=str(path), width=RENDER, height=RENDER, background='#ffffff')
    return Image.open(io.BytesIO(png)).convert('L').reduce(RENDER // THUMB).tobytes()


def distance(a, b):
    return sum(map(abs, map(operator.sub, a, b))) / len(a)


def check_set(root, folder, minimum, listed):
    files = sorted((root / folder / 'gris').rglob('*.svg'))
    if not files:
        print(f'{folder}: no hay iconos en gris/')
        return 1
    thumbs = {p.stem: thumbnail(p) for p in files}
    pairs = sorted((distance(thumbs[a], thumbs[b]), a, b) for a, b in itertools.combinations(thumbs, 2))
    print(f'{folder}: {len(thumbs)} iconos, {len(pairs)} pares; los {listed} más cercanos:')
    for d, a, b in pairs[:listed]:
        print(f'  {d:5.2f}  {a}  ~  {b}')
    close = [p for p in pairs if p[0] < minimum]
    failures = 0
    for d, a, b in close:
        reason = ACEPTADOS.get(frozenset((a, b)))
        if reason:
            print(f'  aceptado {d:5.2f}: {a} y {b} ({reason})')
        else:
            print(f'  FALLO {d:5.2f} < {minimum}: {a} y {b} casi no se distinguen a 24 px')
            failures += 1
    stale = [pair for pair in ACEPTADOS if all(n in thumbs for n in pair) and not any(frozenset(p[1:]) == pair for p in close)]
    for pair in sorted(sorted(p) for p in stale):
        print(f'  ya no hace falta aceptar {pair[0]} y {pair[1]}: quítalos de ACEPTADOS')
    return failures


def main():
    parser = argparse.ArgumentParser(description=__doc__.split('\n')[0])
    parser.add_argument('--minimo', type=float, default=0.8, help='distancia por debajo de la cual falla (0,8 por defecto)')
    parser.add_argument('--lista', type=int, default=10, help='cuántos pares cercanos se muestran por set')
    parser.add_argument('--raiz', type=Path, default=ROOT, help='carpeta que contiene los sets (la raíz del repositorio)')
    args = parser.parse_args()
    failures = sum(check_set(args.raiz, folder, args.minimo, args.lista) for folder in SETS)
    if failures:
        print(f'\n{failures} pares demasiado parecidos')
        sys.exit(1)
    print('TODO OK')


if __name__ == '__main__':
    main()
