#!/usr/bin/env bash
# Deja PostgreSQL listo para el backoffice: servicio arriba, rol y base creados
# si hacía falta. Se puede correr de nuevo sin problema (no rompe nada si ya
# estaba todo listo). Usa las mismas variables de entorno que ConexionBd.php.
set -euo pipefail

if ! pg_isready -q 2>/dev/null; then
    echo "Iniciando PostgreSQL..."
    service postgresql start
    for _ in $(seq 1 20); do
        pg_isready -q 2>/dev/null && break
        sleep 0.5
    done
fi

ROL="${BACKOFFICE_BD_USUARIO:-backoffice_app}"
CLAVE="${BACKOFFICE_BD_CLAVE:-backoffice_dev_2026}"
BASE="${BACKOFFICE_BD_NOMBRE:-backoffice}"

if [ "$(su postgres -c "psql -tAc \"SELECT 1 FROM pg_roles WHERE rolname='$ROL'\"")" != "1" ]; then
    echo "Creando rol de PostgreSQL '$ROL'..."
    su postgres -c "psql -c \"CREATE ROLE $ROL LOGIN PASSWORD '$CLAVE';\""
fi

if [ "$(su postgres -c "psql -tAc \"SELECT 1 FROM pg_database WHERE datname='$BASE'\"")" != "1" ]; then
    echo "Creando base de datos '$BASE'..."
    su postgres -c "createdb -O $ROL $BASE"
fi
