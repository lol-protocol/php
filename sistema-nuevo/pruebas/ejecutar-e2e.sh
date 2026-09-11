#!/usr/bin/env bash
# Corre las pruebas e2e (Playwright) contra el panel ya levantado.
# Requiere los 3 servicios de ejecutar.sh corriendo (Java, API PHP, panel estático).
set -uo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

export NODE_PATH="${NODE_PATH:-/opt/node22/lib/node_modules}"

fallo=0
for archivo in pruebas/e2e/*.e2e.cjs; do
    echo "=== $archivo ==="
    node "$archivo" || fallo=1
    echo
done

exit $fallo
