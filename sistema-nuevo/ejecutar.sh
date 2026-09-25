#!/usr/bin/env bash
# Levanta los 3 componentes del backoffice: microservicio de estadísticas (Java),
# API backend (PHP) y el panel de administración estático (HTML/CSS/JS).
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

./preparar-postgres.sh

echo "Generando datos semilla (crudos -> saneados, cargados en PostgreSQL)..."
php datos/generar-datos-semilla.php

echo "Compilando el microservicio de estadísticas (Java)..."
javac servicio-estadisticas-java/*.java

pids=()
cleanup() {
    echo
    echo "Deteniendo servicios..."
    kill "${pids[@]}" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

(cd servicio-estadisticas-java && java ServicioEstadisticas) &
pids+=($!)

# PHP_CLI_SERVER_WORKERS: el servidor embebido de PHP atiende un solo
# request a la vez por default. El panel ya pide varios endpoints en
# paralelo con Promise.all() (loadAppData() en aplicacion.js) -- sin esto,
# esos pedidos igual se encolan del lado del servidor.
PHP_CLI_SERVER_WORKERS=4 php -S localhost:8000 -t servidor-php/publico servidor-php/publico/index.php &
pids+=($!)

PHP_CLI_SERVER_WORKERS=4 php -S localhost:8082 -t interfaz &
pids+=($!)

sleep 1
echo
echo "Listo:"
echo "  - Panel de administración: http://localhost:8082 (usuario demo: admin / admin123)"
echo "  - API backend (PHP):       http://localhost:8000/api/session"
echo "  - Stats service (Java):    http://localhost:8081/stats?type=login"
echo
echo "Ctrl+C para detener todo."
wait
