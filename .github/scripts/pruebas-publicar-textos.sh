#!/usr/bin/env bash
# Prueba publicar-textos.sh contra un remoto de juguete (git init --bare), con un repositorio de mentira
# como raíz: no toca el repositorio real. Comprueba que publica solo los textos (nunca HTML u otros
# archivos), que la rama queda sin historia común con master, que rechaza ramas y módulos raros y que no
# publica si no hay textos.
set -euo pipefail

real="$(cd "$(dirname "$0")" && pwd)/publicar-textos.sh"
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
fallos=0
ok() { echo "ok   - $1"; }
mal() { echo "FALLA - $1"; fallos=$((fallos + 1)); }

# Raíz de mentira: el script calcula la raíz como ../.. de donde está.
mkdir -p "$tmp/raiz/.github/scripts" "$tmp/raiz/privacy-laws/countries/zz/texts" "$tmp/raiz/privacy-laws/countries/zz/otros" "$tmp/raiz/copyright-laws"
cp "$real" "$tmp/raiz/.github/scripts/publicar-textos.sh"
git init -q --bare "$tmp/remoto.git"
printf -- '---\nsource: https://ejemplo\n---\ntexto de la ley\n' >"$tmp/raiz/privacy-laws/countries/zz/texts/ley.md"
echo '{}' >"$tmp/raiz/privacy-laws/countries/zz/texts/index.json"
echo '<html>nunca</html>' >"$tmp/raiz/privacy-laws/countries/zz/texts/pagina.html"
echo 'otro archivo' >"$tmp/raiz/privacy-laws/countries/zz/otros/dato.md"
s="$tmp/raiz/.github/scripts/publicar-textos.sh"
archivos() { git --git-dir="$tmp/remoto.git" ls-tree -r --name-only "$1" 2>/dev/null | sort | tr '\n' ' '; }

GITHUB_RUN_ID=123 bash "$s" privacy-laws "$tmp/remoto.git" datos/leyes-textos-privacy-laws >/dev/null 2>&1
[ "$(archivos datos/leyes-textos-privacy-laws)" = "LEEME.md countries/zz/texts/index.json countries/zz/texts/ley.md " ] \
  && ok "publica solo los textos y su índice (ni el HTML ni lo que no está en texts/)" || mal "archivos publicados: $(archivos datos/leyes-textos-privacy-laws)"
git --git-dir="$tmp/remoto.git" show datos/leyes-textos-privacy-laws:LEEME.md | grep -q "corrida 123" && ok "el LEEME dice de qué corrida es" || mal "LEEME sin la corrida"
[ "$(git --git-dir="$tmp/remoto.git" rev-list --count datos/leyes-textos-privacy-laws)" = 1 ] && ok "una sola confirmación, sin historia previa" || mal "historia inesperada"

# Segunda corrida con otro contenido: la rama se reescribe entera
rm "$tmp/raiz/privacy-laws/countries/zz/texts/ley.md"; echo 'nuevo' >"$tmp/raiz/privacy-laws/countries/zz/texts/otra.md"
bash "$s" privacy-laws "$tmp/remoto.git" datos/leyes-textos-privacy-laws >/dev/null 2>&1
[ "$(archivos datos/leyes-textos-privacy-laws)" = "LEEME.md countries/zz/texts/index.json countries/zz/texts/otra.md " ] \
  && ok "una corrida nueva reemplaza la anterior" || mal "no se reescribió: $(archivos datos/leyes-textos-privacy-laws)"

if bash "$s" privacy-laws "$tmp/remoto.git" master >/dev/null 2>&1; then mal "debía rechazar una rama que no es datos/…"; else ok "rechaza una rama que no es datos/…"; fi
mkdir -p "$tmp/raiz/otro-modulo/countries/zz/texts" && echo "texto" >"$tmp/raiz/otro-modulo/countries/zz/texts/x.md"  # con textos: solo la validación puede rechazarlo
if bash "$s" otro-modulo "$tmp/remoto.git" datos/x >/dev/null 2>&1; then mal "debía rechazar un módulo raro"; else ok "rechaza un módulo raro"; fi
if bash "$s" copyright-laws "$tmp/remoto.git" datos/leyes-textos-copyright-laws >/dev/null 2>&1; then mal "sin textos debía fallar"; else ok "sin textos no publica"; fi
[ -z "$(archivos datos/leyes-textos-copyright-laws)" ] && ok "…y no crea la rama" || mal "creó una rama vacía"

echo
if [ "$fallos" -gt 0 ]; then echo "$fallos fallo(s)"; exit 1; fi
echo "todo bien"
