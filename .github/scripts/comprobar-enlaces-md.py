#!/usr/bin/env python3
"""Los enlaces relativos de los .md del repo tienen que apuntar a algo que existe.

Una carpeta que se mueve o un documento que se renombra deja enlaces muertos en los README y en
los docs de otros proyectos, y nadie lo nota hasta que alguien hace clic. Esto lo comprueba en CI.

Qué comprueba: en cada .md versionado, los enlaces y las imágenes `[texto](ruta)` / `![](ruta)`,
las definiciones `[id]: ruta` y los `href=` / `src=` de HTML incrustado. Cada ruta relativa
(`../docs/X.md`, `docs/`, `/README.md` desde la raíz del repo) tiene que existir, sea archivo o
carpeta. Ignora lo que está dentro de bloques de código y de `código en línea`, los enlaces con
esquema (https:, mailto:), los que empiezan con # o // y la parte `#ancla` / `?consulta` de los
demás. NO comprueba que el ancla exista dentro del archivo.

Excepción: las carpetas `_Garbage/` se conservan tal cual (CLAUDE.md) y no se revisan.

Falla también si no encontró ningún .md o ningún enlace relativo que mirar: un chequeo que no
encuentra qué comprobar no debe pasar en silencio.

Uso: python3 .github/scripts/comprobar-enlaces-md.py [carpeta]
(sin argumento comprueba el repo; con una carpeta, otra copia del árbol, para probar el chequeo).
Con VERBOSO=1 lista cada enlace que comprueba.
"""
import os
import re
import subprocess
import sys
from urllib.parse import unquote

RAIZ = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..'))
SALTAR = ('_Garbage',)


def archivos_md(raiz):
    """Los .md versionados; si la carpeta no es un repo git, todos los que haya."""
    if os.path.isdir(os.path.join(raiz, '.git')):
        salida = subprocess.run(['git', 'ls-files', '-z', '*.md'], cwd=raiz, capture_output=True, check=True).stdout
        rutas = [r for r in salida.decode('utf-8').split('\0') if r]
    else:
        rutas = []
        for base, carpetas, nombres in os.walk(raiz):
            carpetas[:] = [c for c in carpetas if c not in ('node_modules', 'vendor', '.git')]
            rutas += [os.path.relpath(os.path.join(base, n), raiz) for n in nombres if n.endswith('.md')]
    return sorted(r for r in rutas if not any(p in SALTAR for p in r.split('/')))


def sin_codigo(texto):
    texto = re.sub(r'^(```|~~~).*?^\1[^\n]*$', '', texto, flags=re.S | re.M)  # bloques cercados
    return re.sub(r'`[^`\n]*`', '', texto)  # código en línea


def destinos(texto):
    texto = sin_codigo(texto)
    for m in re.finditer(r'!?\[[^\]\n]*\]\(\s*(<[^>\n]+>|[^)\s]+)', texto):
        yield m.group(1).strip('<>')
    for m in re.finditer(r'^\s{0,3}\[[^\]\n]+\]:\s*(<[^>\n]+>|\S+)', texto, flags=re.M):
        yield m.group(1).strip('<>')
    for m in re.finditer(r'\b(?:href|src)\s*=\s*"([^"]+)"', texto):
        yield m.group(1)


def es_relativo(destino):
    return bool(destino) and not re.match(r'^([A-Za-z][A-Za-z0-9+.-]*:|#|//)', destino)


def main(raiz):
    verboso = bool(os.environ.get('VERBOSO'))
    archivos = archivos_md(raiz)
    comprobados = 0
    rotos = []
    for rel in archivos:
        with open(os.path.join(raiz, rel), encoding='utf-8', errors='replace') as f:
            texto = f.read()
        for destino in destinos(texto):
            if not es_relativo(destino):
                continue
            ruta = unquote(re.split(r'[#?]', destino, maxsplit=1)[0])
            if not ruta:
                continue
            base = raiz if ruta.startswith('/') else os.path.dirname(os.path.join(raiz, rel))
            objetivo = os.path.normpath(os.path.join(base, ruta.lstrip('/')))
            comprobados += 1
            existe = os.path.exists(objetivo)
            if verboso:
                print(('ok    ' if existe else 'ROTO  ') + f'{rel} -> {destino}')
            if not existe:
                rotos.append((rel, destino))
    if not archivos or not comprobados:
        print(f'::error::No encontré qué comprobar ({len(archivos)} .md, {comprobados} enlaces relativos): el chequeo no sirve así.')
        return 1
    for rel, destino in rotos:
        print(f'::error file={rel}::El enlace «{destino}» no apunta a nada que exista.')
    print(f'{comprobados} enlaces relativos en {len(archivos)} archivos .md: {len(rotos)} rotos.')
    return 1 if rotos else 0


if __name__ == '__main__':
    sys.exit(main(os.path.abspath(sys.argv[1]) if len(sys.argv) > 1 else RAIZ))
