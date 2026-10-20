#!/usr/bin/env python3
"""Las rutas que cita CLAUDE.md tienen que existir.

CLAUDE.md es lo primero que lee quien trabaja en el repo y manda a correr cosas por su
ruta: «php bin/recrear_con_datos_de_ejemplo.php», «./pruebas/ejecutar-e2e.sh». Si un
script se mueve o se renombra y el texto no se entera, la próxima sesión copia un comando
que ya no existe (pasó con el de cobros-ingresos-funnels/ tras #98; se corrigió en #106).

Qué comprueba:
  1. La tabla «Proyecto | Pruebas»: cada carpeta de la primera columna existe en la raíz, y
     cada ruta que la segunda columna pone entre comillas invertidas existe, contada desde
     la carpeta de la fila (o desde la raíz, que es como una fila nombra a otro proyecto) o,
     si la fila dice «desde la raíz», solo desde la raíz. Acepta comodines (vps-setup/*.sh)
     y NNN por tres dígitos; ignora vendor/ y node_modules/, que no están en el repo.
  2. En el resto del archivo, toda ruta entre comillas invertidas que empiece por una
     carpeta de la raíz (cobros-ingresos-funnels/src/Dinero.php), y todo workflow nombrado a
     secas (estructura.yml), que tiene que estar en .github/workflows/.

Falla también si no encuentra la tabla o si no comprobó ninguna ruta: un chequeo que no
encuentra qué mirar no debe pasar en silencio.

Si una fila cita algo que no está en el repo porque se genera al correr (un .env, una base),
no lo pongas entre comillas invertidas, o agrégalo a NO_ESTAN_EN_EL_REPO.

Uso: python3 .github/scripts/comprobar-rutas-de-claude-md.py [CLAUDE.md]
(sin argumento comprueba el CLAUDE.md de la raíz; con uno, otra copia, para probar el chequeo).
Con VERBOSO=1 lista cada ruta que comprueba.
"""
import glob
import os
import re
import sys

RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
EXTENSIONES = r'\.(php|py|sh|js|mjs|json|md|csv|yml|yaml|html|sql|neon|dist|txt|toml)$'
NO_ESTAN_EN_EL_REPO = ('vendor/', 'node_modules/')


def entre_comillas_invertidas(linea):
    return re.findall(r'`([^`]+)`', linea)


def rutas_en(texto):
    """Las palabras de `texto` que parecen una ruta (tienen «/» o terminan en una extensión conocida)."""
    for palabra in texto.split():
        palabra = palabra.strip('\'",;()')
        if palabra.startswith('./'):
            palabra = palabra[2:]
        if not palabra or palabra.startswith(('-', '/', '~', 'http')) or re.search(r'[=<>$:&|]', palabra):
            continue  # opciones, rutas absolutas, enlaces, variables, marcadores como <proyecto>, operadores
        if any(fragmento in palabra for fragmento in NO_ESTAN_EN_EL_REPO):
            continue
        if '/' in palabra or re.search(EXTENSIONES, palabra):
            yield palabra


def existe(base, ruta):
    return bool(glob.glob(os.path.join(base, ruta.replace('NNN', '[0-9][0-9][0-9]'))))


def main():
    claude_md = sys.argv[1] if len(sys.argv) > 1 else os.path.join(RAIZ, 'CLAUDE.md')
    nombre = os.path.basename(claude_md)
    with open(claude_md, encoding='utf-8') as archivo:
        lineas = archivo.read().splitlines()

    carpetas = {d for d in os.listdir(RAIZ) if os.path.isdir(os.path.join(RAIZ, d)) and d != '.git'}
    revisadas = 0
    faltan = []

    def comprobar(numero, ruta, bases):
        nonlocal revisadas
        revisadas += 1
        if os.environ.get('VERBOSO'):
            print(f'  {nombre}:{numero}: {ruta}')
        if not any(existe(base, ruta) for base in bases):
            donde = ' ni en '.join(f'«{os.path.relpath(base, RAIZ)}/»' if base != RAIZ else 'la raíz' for base in bases)
            faltan.append((numero, f'«{ruta}» no existe en {donde}'))

    primera = next((i for i, linea in enumerate(lineas)
                    if re.match(r'\|\s*Proyecto\s*\|\s*Pruebas\s*\|', linea)), None)
    if primera is None:
        print(f'::error file={nombre}::No encuentro la tabla «Proyecto | Pruebas»; si cambió de forma, ajusta este chequeo')
        return 1

    fin = primera + 1
    while fin < len(lineas) and lineas[fin].startswith('|'):
        numero = fin + 1
        celdas = [c.strip() for c in lineas[fin].strip().strip('|').split('|')]
        fin += 1
        if re.fullmatch(r'[-: |]+', lineas[fin - 1]):
            continue  # la línea de guiones que separa el encabezado
        if len(celdas) != 2:
            faltan.append((numero, f'la fila de la tabla tiene {len(celdas)} celdas y se esperaban 2'))
            continue
        proyectos = [c for texto in entre_comillas_invertidas(celdas[0]) for c in rutas_en(texto)]
        for proyecto in proyectos:
            comprobar(numero, proyecto, [RAIZ])
        desde_la_raiz = 'desde la raíz' in celdas[1].lower()
        # Una fila también nombra a otros proyectos por su carpeta («depende de ../defamatory-content-review»).
        bases = [RAIZ] if desde_la_raiz or not proyectos else [os.path.join(RAIZ, proyectos[0]), RAIZ]
        for texto in entre_comillas_invertidas(celdas[1]):
            for ruta in rutas_en(texto):
                comprobar(numero, ruta, bases)

    tabla = range(primera, fin)
    for i, linea in enumerate(lineas):
        if i in tabla:
            continue
        for texto in entre_comillas_invertidas(linea):
            if re.fullmatch(r'[\w.\-/*]+', texto) and texto.split('/')[0] in carpetas and '/' in texto:
                comprobar(i + 1, texto, [RAIZ])
            elif re.fullmatch(r'[\w.\-]+\.ya?ml', texto):
                comprobar(i + 1, texto, [os.path.join(RAIZ, '.github', 'workflows')])

    for numero, problema in faltan:
        print(f'::error file={nombre},line={numero}::{problema}')
    if faltan:
        print(f'\n{len(faltan)} de {revisadas} rutas que cita {nombre} no existen. '
              'Corrige el texto o la ruta (si el script se movió, lo que está mal es el texto).')
        return 1
    if revisadas == 0:
        print(f'::error file={nombre}::No comprobé ninguna ruta; este chequeo ya no mira lo que debe')
        return 1
    print(f'{revisadas} rutas citadas en {nombre}, todas existen')
    return 0


if __name__ == '__main__':
    sys.exit(main())
