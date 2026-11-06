#!/usr/bin/env bash
# Borra ramas remotas de una lista «rama sha» (una por línea), la que escribe informe-de-ramas.sh
# con LISTA_RAMAS. Lo lanza borrar-ramas-fusionadas.yml, a mano.
#
# Por defecto NO borra nada: muestra lo que borraría. Con BORRAR=true borra, y solo una rama que sigue
# en el mismo commit que cuando se hizo la lista (--force-with-lease con el sha): si alguien empujó
# algo entre medias, esa rama se salta.
#
# Nunca toca master/main ni la rama por defecto del remoto, ni nombres con caracteres raros. La rama
# por defecto sale de RAMA_POR_DEFECTO (el workflow la pasa desde el repositorio) o, si no, de
# refs/remotes/origin/HEAD, que actions/checkout no crea: sin ninguna de las dos, para borrar de
# verdad el script se niega en vez de fiarse solo de los nombres master y main.
# Se queda en MAXIMO ramas por corrida (100). Cada borrada deja su sha en la salida, para restaurarla:
#   git push origin <sha>:refs/heads/<rama>
#
# Variables: BORRAR (false), MAXIMO (100, un número), REMOTO (origin), RAMA_POR_DEFECTO,
# RESULTADO (archivo donde escribe «rama|sha|estado» por línea, para máquinas: borraria, borrada,
# saltada-formato, saltada-cambio o saltada-por-defecto).
# Con GITHUB_STEP_SUMMARY escribe también el resultado en el resumen de la corrida.
set -euo pipefail

lista="${1:?uso: borrar-ramas.sh <archivo con «rama sha» por línea>}"
borrar="${BORRAR:-false}"
maximo="${MAXIMO:-100}"
remoto="${REMOTO:-origin}"

if ! [[ "$maximo" =~ ^[0-9]+$ ]]; then
  echo "MAXIMO debe ser un número entero (llegó «$maximo»)" >&2
  exit 2
fi

por_defecto="${RAMA_POR_DEFECTO:-}"
if [ -z "$por_defecto" ]; then
  por_defecto=$(git symbolic-ref --short "refs/remotes/$remoto/HEAD" 2>/dev/null | sed "s#^$remoto/##" || true)
fi
if [ "$borrar" = true ] && [ -z "$por_defecto" ]; then
  echo "No sé cuál es la rama por defecto (RAMA_POR_DEFECTO vacía y sin $remoto/HEAD): no borro nada." >&2
  exit 2
fi

if [ -n "${RESULTADO:-}" ]; then : >"$RESULTADO"; fi
anotar() { # rama sha estado
  if [ -n "${RESULTADO:-}" ]; then echo "$1|$2|$3" >>"$RESULTADO"; fi
}

salida=""
n=0
borradas=0
saltadas=0
while read -r rama sha; do
  [ -n "${rama:-}" ] || continue
  case "$rama" in master | main | HEAD) continue ;; esac
  if [ -n "$por_defecto" ] && [ "$rama" = "$por_defecto" ]; then
    anotar "$rama" "${sha:-}" saltada-por-defecto
    continue
  fi
  if ! [[ "$rama" =~ ^[A-Za-z0-9._/-]+$ ]] || ! [[ "${sha:-}" =~ ^[0-9a-f]{40}$ ]]; then
    salida+="- saltada \`$rama\`: nombre o sha con formato raro"$'\n'
    anotar "$rama" "${sha:-}" saltada-formato
    saltadas=$((saltadas + 1))
    continue
  fi
  n=$((n + 1))
  if [ "$n" -gt "$maximo" ]; then
    salida+="- quedan más ramas en la lista; esta corrida se detiene en $maximo (MAXIMO)"$'\n'
    break
  fi
  if [ "$borrar" != true ]; then
    salida+="- borraría \`$rama\` ($sha)"$'\n'
    anotar "$rama" "$sha" borraria
    continue
  fi
  if git push --force-with-lease="refs/heads/$rama:$sha" "$remoto" ":refs/heads/$rama" >/dev/null 2>&1; then
    salida+="- borrada \`$rama\` — restaurar: \`git push $remoto $sha:refs/heads/$rama\`"$'\n'
    anotar "$rama" "$sha" borrada
    borradas=$((borradas + 1))
  else
    salida+="- **saltada** \`$rama\`: ya no está en $sha (alguien empujó) o ya no existe"$'\n'
    anotar "$rama" "$sha" saltada-cambio
    saltadas=$((saltadas + 1))
  fi
done <"$lista"

if [ "$borrar" = true ]; then
  titulo="Ramas borradas: $borradas; saltadas: $saltadas"
else
  titulo="Simulacro: no se borró nada ($n ramas en la lista; para borrar, lanza el workflow con «borrar» marcado)"
fi
resultado="### $titulo"$'\n\n'"${salida:-_La lista está vacía._}"
echo "$resultado"
if [ -n "${GITHUB_STEP_SUMMARY:-}" ]; then echo "$resultado" >>"$GITHUB_STEP_SUMMARY"; fi
