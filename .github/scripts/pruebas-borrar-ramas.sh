#!/usr/bin/env bash
# Prueba borrar-ramas.sh contra un remoto de juguete (git init --bare): no toca el repositorio real.
# Comprueba: el simulacro no borra; BORRAR=true borra la lista; salta master, la rama por defecto,
# nombres raros y una rama a la que se empujó después de hacer la lista; MAXIMO no numérico falla;
# sin rama por defecto conocida no borra; y el archivo RESULTADO trae «rama|sha|estado».
# Prueba también informe-de-ramas.sh (la lista que usa el workflow): una rama huérfana, sin ancestro común
# con master, no lo tumba ni sale en la lista; una rama cuyo PR se fusionó y que sigue en ese commit sale en
# la lista aunque master haya cambiado sus archivos, y una a la que se empujó después no sale.
set -euo pipefail

script="$(cd "$(dirname "$0")" && pwd)/borrar-ramas.sh"
tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT
fallos=0
ok() { echo "ok   - $1"; }
mal() { echo "FALLA - $1"; fallos=$((fallos + 1)); }
espera() { # descripción condición...
  local d="$1"; shift
  if "$@"; then ok "$d"; else mal "$d"; fi
}

git init -q --bare "$tmp/remoto.git"
git clone -q "$tmp/remoto.git" "$tmp/a" 2>/dev/null
cd "$tmp/a"
git config user.email t@t && git config user.name t
git commit -q --allow-empty -m base
git branch -M master
git push -q origin master 2>/dev/null
for b in claude/vieja otra otra2 desarrollo; do git branch -q "$b"; done
git push -q origin claude/vieja otra otra2 desarrollo 2>/dev/null
sha=$(git rev-parse master)
printf 'claude/vieja %s\notra %s\notra2 %s\nmaster %s\ndesarrollo %s\nmala$rama %s\n' "$sha" "$sha" "$sha" "$sha" "$sha" "$sha" >"$tmp/lista.txt"
remotas() { git ls-remote --heads origin | awk '{sub("refs/heads/","",$2); print $2}' | sort | tr '\n' ' '; }

# 1) simulacro
out=$(RAMA_POR_DEFECTO=desarrollo RESULTADO="$tmp/res1" bash "$script" "$tmp/lista.txt")
espera "simulacro: no borra nada" test "$(remotas)" = "claude/vieja desarrollo master otra otra2 "
espera "simulacro: lo dice" grep -q "Simulacro" <<<"$out"
espera "RESULTADO del simulacro" grep -qx "otra|$sha|borraria" "$tmp/res1"

# 2) alguien empuja a otra2 tras la lista, y se borra de verdad
git checkout -q otra2 && git commit -q --allow-empty -m nuevo && git push -q origin otra2 2>/dev/null && git checkout -q master
BORRAR=true RAMA_POR_DEFECTO=desarrollo RESULTADO="$tmp/res2" bash "$script" "$tmp/lista.txt" >/dev/null
espera "borra la lista y conserva master, desarrollo (por defecto) y otra2 (cambió)" test "$(remotas)" = "desarrollo master otra2 "
espera "RESULTADO: borrada" grep -qx "claude/vieja|$sha|borrada" "$tmp/res2"
espera "RESULTADO: cambió" grep -qx "otra2|$sha|saltada-cambio" "$tmp/res2"
espera "RESULTADO: por defecto" grep -qx "desarrollo|$sha|saltada-por-defecto" "$tmp/res2"
espera "RESULTADO: formato raro" grep -q '^mala\$rama|.*|saltada-formato$' "$tmp/res2"

# 3) MAXIMO no numérico
if MAXIMO="10; rm -rf /" bash "$script" "$tmp/lista.txt" >/dev/null 2>&1; then mal "MAXIMO no numérico debía fallar"; else ok "MAXIMO no numérico falla"; fi

# 4) sin rama por defecto conocida: no borra de verdad
printf 'otra2 %s\n' "$(git rev-parse origin/otra2)" >"$tmp/lista2.txt"
if BORRAR=true RAMA_POR_DEFECTO= bash "$script" "$tmp/lista2.txt" >/dev/null 2>&1; then mal "sin rama por defecto debía negarse"; else ok "sin rama por defecto se niega"; fi
espera "…y otra2 sigue ahí" test "$(remotas)" = "desarrollo master otra2 "

# 5) informe-de-ramas.sh: la lista incluye vacías y fusionadas, y no la tumba una rama huérfana (sin merge-base)
informe="$(dirname "$script")/informe-de-ramas.sh"
# gh de juguete: ningún PR abierto, y los PR cerrados y fusionados que dice PR_FUSIONADOS (un «rama sha» por línea)
mkdir "$tmp/bin"
cat >"$tmp/bin/gh" <<'GH'
#!/usr/bin/env bash
case "$*" in *state=closed*) [ -n "${PR_FUSIONADOS:-}" ] && cat "$PR_FUSIONADOS" ;; esac
exit 0
GH
chmod +x "$tmp/bin/gh"
git branch -q vacia master && git push -q origin vacia 2>/dev/null
git checkout -q --orphan datos/huerfana && git rm -rfq . 2>/dev/null || true
git commit -q --allow-empty -m datos && git push -q origin datos/huerfana 2>/dev/null && git checkout -q -f master
git fetch -q origin
if PATH="$tmp/bin:$PATH" BASE_REF=origin/master MIN_DIAS=0 LISTA_RAMAS="$tmp/informe.txt" bash "$informe" >"$tmp/informe.out" 2>&1; then
  ok "informe-de-ramas.sh no falla con una rama huérfana"
else
  mal "informe-de-ramas.sh falla con una rama huérfana: $(tail -n 3 "$tmp/informe.out" | tr '\n' ' ')"
fi
espera "informe: la rama vacía sale en la lista" grep -qx "vacia $(git rev-parse origin/vacia)" "$tmp/informe.txt"
espera "informe: la rama huérfana no sale" bash -c '! grep -q "^datos/huerfana " "$1"' _ "$tmp/informe.txt"

# 6) ramas de PR fusionado con squash: master cambió después sus archivos (no «fusionada» por archivos), así que solo
#    su PR dice que no aportan nada. «squash» sigue en el commit del PR; «squash-despues» recibió otro commit.
git checkout -q -b squash master && echo uno >a.txt && git add a.txt && git commit -q -m pr && git push -q origin squash 2>/dev/null
git checkout -q -b squash-despues master && echo dos >b.txt && git add b.txt && git commit -q -m pr2 && git push -q origin squash-despues 2>/dev/null
punta_squash=$(git rev-parse squash)
punta_despues=$(git rev-parse squash-despues)
git commit -q --allow-empty -m "despues del PR" && git push -q origin squash-despues 2>/dev/null
git checkout -q master && echo "otro contenido" >a.txt && git add a.txt && git commit -q -m "master cambia a.txt" && git push -q origin master 2>/dev/null
git fetch -q origin
printf 'squash %s\nsquash-despues %s\n' "$punta_squash" "$punta_despues" >"$tmp/prs.txt"
PR_FUSIONADOS="$tmp/prs.txt" PATH="$tmp/bin:$PATH" BASE_REF=origin/master MIN_DIAS=0 LISTA_RAMAS="$tmp/informe2.txt" bash "$informe" >"$tmp/informe2.out" 2>&1 || mal "informe-de-ramas.sh falló con PR fusionados: $(tail -n 3 "$tmp/informe2.out" | tr '\n' ' ')"
espera "informe: la rama de un PR fusionado sale en la lista" grep -qx "squash $punta_squash" "$tmp/informe2.txt"
espera "informe: …y en la sección «Con su PR fusionado»" grep -q 'Con su PR fusionado' "$tmp/informe2.out"
espera "informe: la rama a la que se empujó después del PR no sale" bash -c '! grep -q "^squash-despues " "$1"' _ "$tmp/informe2.txt"

echo
if [ "$fallos" -gt 0 ]; then echo "$fallos fallo(s)"; exit 1; fi
echo "todo bien"
