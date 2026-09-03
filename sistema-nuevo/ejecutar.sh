#!/usr/bin/env bash
# Levanta los 3 componentes del backoffice: microservicio de estadísticas (Java),
# API backend (PHP) y el panel de administración estático (HTML/CSS/JS).
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

echo "Generando datos semilla (crudos -> saneados)..."
php datos/generar-datos-semilla.php

echo "Compilando el microservicio de estadísticas (Java)..."
javac servicio-estadisticas-java/ServicioEstadisticas.java

pids=()
cleanup() {
    echo
    echo "Deteniendo servicios..."
    kill "${pids[@]}" 2>/dev/null || true
}
trap cleanup EXIT INT TERM

(cd servicio-estadisticas-java && java ServicioEstadisticas) &
pids+=($!)

php -S localhost:8000 -t servidor-php/publico servidor-php/publico/index.php &
pids+=($!)

php -S localhost:8082 -t interfaz &
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
