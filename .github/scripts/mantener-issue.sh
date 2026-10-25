#!/usr/bin/env bash
# Mantiene UNA issue abierta mientras algo falle, y la cierra cuando deja de fallar.
#
# Un workflow que solo se pone en rojo se queda en Actions, donde nadie mira. Con esto, el rojo llega
# a una persona: una issue que se crea al primer fallo, se actualiza mientras siga, y se cierra sola
# cuando todo vuelve a la normalidad. La issue se busca por su título exacto, así que hay una por tema.
#
#   mantener-issue.sh fallo "<título>" <archivo-con-el-cuerpo>
#       crea la issue si no existe; si existe, le reemplaza el cuerpo (no acumula comentarios)
#   mantener-issue.sh ok    "<título>" ""  ["<comentario de cierre>"]
#       cierra la issue si está abierta; si no hay, no hace nada
#
# Necesita `gh` con GH_TOKEN, GH_REPO (o correr dentro de un clon) y permiso `issues: write`.
# La usan laws-links.yml, laws-freshness.yml e informe-de-ramas.yml.
set -euo pipefail

estado="${1:-}"
titulo="${2:-}"
cuerpo="${3:-}"
cierre="${4:-Ya no hay nada que avisar.}"

uso() {
  echo "Uso: $0 fallo|ok \"<título>\" <archivo-cuerpo> [\"<comentario de cierre>\"]" >&2
  exit 2
}
[ -n "$titulo" ] || uso

numero=$(TITLE="$titulo" gh issue list --state open --search "$titulo in:title" --json number,title \
  --jq '[.[] | select(.title == env.TITLE)][0].number // empty')

case "$estado" in
  fallo)
    [ -f "$cuerpo" ] || { echo "No existe el archivo del cuerpo: «$cuerpo»" >&2; exit 2; }
    if [ -n "$numero" ]; then
      echo "Actualizo la issue #$numero."
      gh issue edit "$numero" --body-file "$cuerpo"
    else
      echo "Abro la issue «$titulo»."
      gh issue create --title "$titulo" --body-file "$cuerpo"
    fi
    ;;
  ok)
    if [ -n "$numero" ]; then
      echo "Cierro la issue #$numero."
      gh issue close "$numero" --comment "$cierre"
    else
      echo "No hay issue abierta «$titulo»: nada que cerrar."
    fi
    ;;
  *) uso ;;
esac
