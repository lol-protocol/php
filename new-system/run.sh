#!/usr/bin/env bash
# Levanta los 3 componentes del backoffice: microservicio de estadísticas (Java),
# API backend (PHP) y el panel de administración estático (HTML/CSS/JS).
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

echo "Generando datos semilla..."
php data/generate_seed_data.php

echo "Compilando el microservicio de estadísticas (Java)..."
javac stats-service-java/StatsService.java

pids=()
cleanup() {
    echo
    echo "Deteniendo servicios..."
    kill "${pids[@]}" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

(cd stats-service-java && java StatsService) &
pids+=($!)

php -S localhost:8000 -t backend-php/public backend-php/public/index.php &
pids+=($!)

php -S localhost:8082 -t frontend &
pids+=($!)

sleep 1
echo
echo "Listo:"
echo "  - Panel de administración: http://localhost:8082"
echo "  - API backend (PHP):       http://localhost:8000/api/users"
echo "  - Stats service (Java):    http://localhost:8081/stats?type=login"
echo
echo "Ctrl+C para detener todo."
wait
