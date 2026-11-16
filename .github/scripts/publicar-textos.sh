#!/usr/bin/env bash
# Publica en una rama de datos los textos que dejó cache-texts.php, para poder leerlos con git fetch desde
# donde no se llega a las fuentes oficiales (el contenedor de las sesiones en la nube no sale a esos sitios;
# un runner de GitHub Actions sí).
#
#   publicar-textos.sh <módulo> <remoto> <rama>
#
#   módulo   privacy-laws o copyright-laws (se leen sus carpetas <código>/texts/)
#   remoto   URL del repositorio al que empujar (el workflow pasa la del repositorio con el token)
#   rama     rama de datos, p. ej. datos/leyes-textos-privacy-laws. Se crea SIN historia común con master
#            (nunca se fusiona) y se reescribe en cada corrida, pero conserva las carpetas <código>/texts de
#            las corridas anteriores que esta no tocó: así una corrida de «ec,ve» no borra lo que bajó otra de
#            «ca,cl,br». Una carpeta que sí está en esta corrida se reemplaza entera.
#
# Solo copia lo que cache-texts.php escribió (texts/*.md|*.txt y su index.json): nunca HTML. Falla si no hay
# ningún texto, para no empujar una rama vacía.
set -euo pipefail

modulo="${1:?uso: publicar-textos.sh <módulo> <remoto> <rama>}"
remoto="${2:?falta el remoto}"
rama="${3:?falta la rama}"
raiz="$(cd "$(dirname "$0")/../.." && pwd)"

case "$modulo" in privacy-laws | copyright-laws) ;; *) echo "módulo no válido: $modulo" >&2; exit 2 ;; esac
[[ "$rama" =~ ^datos/[A-Za-z0-9._-]+$ ]] || { echo "la rama debe ser datos/<nombre>, llegó «$rama»" >&2; exit 2; }

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
n=0
while IFS= read -r -d '' f; do
  rel="${f#"$raiz/$modulo/"}"
  mkdir -p "$tmp/$(dirname "$rel")"
  cp "$f" "$tmp/$rel"
  n=$((n + 1))
done < <(find "$raiz/$modulo" -path '*/node_modules' -prune -o -path '*/texts/*' -type f \( -name '*.md' -o -name '*.txt' -o -name 'index.json' \) -print0)

if [ "$n" -eq 0 ]; then
  echo "No hay ningún texto en $modulo (¿cache-texts.php no descargó nada?): no publico." >&2
  exit 1
fi

# Conserva de la rama anterior las carpetas <dir>/<código>/texts que esta corrida no trae.
previa=$(mktemp -d)
trap 'rm -rf "$tmp" "$previa"' EXIT
if git ls-remote --exit-code --heads "$remoto" "refs/heads/$rama" >/dev/null 2>&1; then
  git -C "$previa" init -q
  if git -C "$previa" fetch -q --depth=1 "$remoto" "refs/heads/$rama" 2>/dev/null && git -C "$previa" checkout -q FETCH_HEAD 2>/dev/null; then
    while IFS= read -r -d '' d; do
      rel="${d#"$previa/"}"
      if [ ! -e "$tmp/$rel" ]; then
        mkdir -p "$tmp/$(dirname "$rel")"
        cp -R "$d" "$tmp/$rel"
        n=$((n + $(find "$d" -type f | wc -l)))
      fi
    done < <(find "$previa" -path "$previa/.git" -prune -o -mindepth 3 -maxdepth 3 -type d -name texts -print0)
  fi
fi

cat >"$tmp/LEEME.md" <<TXT
# Textos de las fuentes de $modulo

Foto de la última corrida de \`laws-textos-oficiales.yml\` (${GITHUB_RUN_ID:+corrida $GITHUB_RUN_ID, }$(date -u +%F)):
el texto legible de la página de referencia de cada ley (Markdown o texto plano, nunca HTML), con su origen y
la fecha de descarga en la cabecera de cada archivo. **No se fusiona**: es una rama de datos sin historia común
con master, que se reescribe en cada corrida (conserva las carpetas que la última no tocó; la fecha de cada
archivo dice de cuándo es). Para leerla: \`git fetch origin $rama\`.

Son páginas de terceros, a veces guías de despachos y no el texto oficial: sirven para comprobar un dato,
no son una fuente por sí solas. Los archivos son $n.
TXT

cd "$tmp"
git init -q
git config user.name "laws-textos-oficiales"
git config user.email "noreply@github.com"
git checkout -q -b "$rama"
git add -A
git commit -q -m "Textos de las fuentes de $modulo ($n archivos)"
git push -q --force "$remoto" "HEAD:refs/heads/$rama"
echo "Publicados $n archivos en $rama"
