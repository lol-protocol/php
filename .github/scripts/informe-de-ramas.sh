#!/usr/bin/env bash
# Lista las ramas remotas que ya no aportan nada, para que una persona decida si las borra.
# SOLO INFORMA: no borra ni toca ninguna rama.
#
#   - vacía:      no tiene ningún commit que master no tenga.
#   - fusionada:  tiene commits, pero los archivos que cambió están idénticos en master (los PR se
#                 fusionan con squash, así que `git branch --merged` no las reconoce).
#
# No lista las ramas con un PR abierto, ni las que tienen un último commit de menos de MIN_DIAS días
# (una sesión puede estar empezando a trabajar en ellas). Una rama con commits propios que no
# aparece aquí puede tener trabajo pendiente o haber sido superada por master: hay que mirarla.
#
# Necesita un clon con todas las ramas (fetch-depth: 0) y `gh` con permiso de leer pull requests.
# Variables: BASE_REF (origin/master), MIN_DIAS (3), GITHUB_REPOSITORY (lol-protocol/php).
set -euo pipefail

base="${BASE_REF:-origin/master}"
min_dias="${MIN_DIAS:-3}"
repo="${GITHUB_REPOSITORY:-lol-protocol/php}"
ahora=$(date +%s)

abiertas=$(gh api "repos/$repo/pulls?state=open&per_page=100" --paginate --jq '.[].head.ref')

vacias=""
fusionadas=""
total=0
while IFS='|' read -r rama fecha; do
  case "$rama" in HEAD | master) continue ;; esac
  if grep -qxF -- "$rama" <<<"$abiertas"; then continue; fi
  if [ $(( (ahora - fecha) / 86400 )) -lt "$min_dias" ]; then continue; fi
  total=$((total + 1))
  dia=$(date -u -d "@$fecha" +%F)
  if [ "$(git rev-list --count "$base..origin/$rama")" = 0 ]; then
    vacias+="- \`$rama\` (último commit: $dia)"$'\n'
    continue
  fi
  mb=$(git merge-base "$base" "origin/$rama")
  mapfile -t archivos < <(git diff --name-only "$mb" "origin/$rama")
  if [ "${#archivos[@]}" -gt 0 ] && git diff --quiet "$base" "origin/$rama" -- "${archivos[@]}"; then
    fusionadas+="- \`$rama\` (último commit: $dia, ${#archivos[@]} archivos)"$'\n'
  fi
done < <(git for-each-ref --format='%(refname:strip=3)|%(committerdate:unix)' refs/remotes/origin)

informe() {
  echo "### Ramas que ya no aportan nada"
  echo
  echo "Solo informa; borrarlas lo decide una persona (\`git push origin --delete <rama>\`, o desde GitHub, en Branches)."
  echo
  echo "**Vacías** (sin ningún commit que $base no tenga):"
  echo
  echo "${vacias:-_Ninguna._}"
  echo
  echo "**Fusionadas** (sus archivos ya están iguales en $base):"
  echo
  echo "${fusionadas:-_Ninguna._}"
  echo
  echo "Ramas revisadas: $total (sin contar las que tienen un PR abierto ni las de menos de $min_dias días)."
}

informe
if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then
  informe >>"$GITHUB_STEP_SUMMARY"
fi
