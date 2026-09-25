#!/usr/bin/env bash
# Corre las pruebas unitarias de JS (node:test, sin dependencias externas).
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

node --test pruebas/js/*.test.mjs
