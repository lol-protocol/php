#!/usr/bin/env python3
"""Genera los dos sets de iconos y la galería.

    python3 iconos-tools/build.py

Escribe iconos-genealogia/ e iconos-biblioteca/ (variantes color, lineas y gris, una carpeta por
prefijo) y galeria-iconos.html. Los SVG son salida generada: se editan en genealogia.py y biblioteca.py.
"""
import json
import shutil
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
sys.path.insert(0, str(HERE))

import biblioteca  # noqa: E402
import genealogia  # noqa: E402
from lib import VARIANTS, write  # noqa: E402

SETS = (
    ('genealogia', 'iconos-genealogia', 'Árbol genealógico', genealogia.ICONS),
    ('biblioteca', 'iconos-biblioteca', 'Biblioteca virtual', biblioteca.ICONS),
)

GROUPS = {
    'person': 'Personas y parentescos',
    'relationship': 'Relaciones',
    'state': 'Estados',
    'view': 'Vistas',
    'action': 'Acciones',
    'ui': 'Interfaz',
    'book': 'Libros',
    'genre': 'Géneros',
    'format': 'Ediciones y formatos',
    'status': 'Disponibilidad y lectura',
    'attr': 'Atributos',
}


def prefix(stem):
    return stem.split('_')[0]


def build_set(folder, icons):
    base = ROOT / folder
    for variant in VARIANTS:
        shutil.rmtree(base / variant, ignore_errors=True)
    stems = [i.stem for i in icons]
    repeated = sorted({s for s in stems if stems.count(s) > 1})
    if repeated:
        raise SystemExit(f'{folder}: nombres repetidos {repeated}')
    for icon in icons:
        write(icon, base, prefix(icon.stem))


def gallery_data():
    data = []
    for key, folder, title, icons in SETS:
        groups = {}
        for icon in sorted(icons, key=lambda i: i.stem):
            groups.setdefault(prefix(icon.stem), []).append([prefix(icon.stem), icon.stem])
        ordered = [{'id': g, 'label': GROUPS[g], 'prefix': g + '_*', 'items': items}
                   for g, items in sorted(groups.items(), key=lambda kv: list(GROUPS).index(kv[0]))]
        data.append({'key': key, 'dir': folder, 'title': title, 'groups': ordered})
    return data


TEMPLATE = (HERE / 'galeria.template.html').read_text(encoding='utf-8')


def build_gallery():
    html = TEMPLATE.replace('__DATA__', json.dumps(gallery_data(), ensure_ascii=False, separators=(',', ':')))
    (ROOT / 'galeria-iconos.html').write_text(html, encoding='utf-8')


def main():
    for _, folder, title, icons in SETS:
        build_set(folder, icons)
        print(f'{folder}: {len(icons)} iconos x {len(VARIANTS)} variantes')
    build_gallery()
    print('galeria-iconos.html')


if __name__ == '__main__':
    main()
