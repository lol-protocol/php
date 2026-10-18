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
from catalogo import GROUPS, VARIANTS  # noqa: E402

SETS = (
    ('genealogia', 'iconos-genealogia', 'Árbol genealógico', genealogia.ICONS),
    ('biblioteca', 'iconos-biblioteca', 'Biblioteca virtual', biblioteca.ICONS),
)


def prefix(stem):
    return stem.split('_')[0]


def plan_set(folder, icons):
    """Todo lo que se escribirá para un set, {ruta: texto}, comprobado antes de borrar nada."""
    stems = [i.stem for i in icons]
    repeated = sorted({s for s in stems if stems.count(s) > 1})
    if repeated:
        raise SystemExit(f'{folder}: nombres repetidos {repeated}')
    unlabeled = sorted({prefix(s) for s in stems} - set(GROUPS))
    if unlabeled:
        raise SystemExit(f'{folder}: prefijos sin etiqueta en catalogo.GROUPS {unlabeled}')
    base = ROOT / folder
    return {base / v / prefix(i.stem) / f'{i.stem}.svg': i.render(v) for i in icons for v in VARIANTS}


def write_set(folder, files):
    for variant in VARIANTS:
        shutil.rmtree(ROOT / folder / variant, ignore_errors=True)
    for path, text in files.items():
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(text, encoding='utf-8')


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


def gallery_html():
    return TEMPLATE.replace('__DATA__', json.dumps(gallery_data(), ensure_ascii=False, separators=(',', ':')))


def main():
    # primero se genera y comprueba todo; solo entonces se borra y se escribe, para que un error no deje los sets a medias
    plans = [(folder, icons, plan_set(folder, icons)) for _, folder, _, icons in SETS]
    gallery = gallery_html()
    for folder, icons, files in plans:
        write_set(folder, files)
        print(f'{folder}: {len(icons)} iconos x {len(VARIANTS)} variantes')
    (ROOT / 'galeria-iconos.html').write_text(gallery, encoding='utf-8')
    print('galeria-iconos.html')


if __name__ == '__main__':
    main()
