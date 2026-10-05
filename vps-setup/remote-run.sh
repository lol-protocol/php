#!/bin/bash
# Ejecuta un script de vps-setup/ en el VPS desde TU equipo, por SSH (solo llave).
# Sube vps-setup/ y landing-page/ (con tar, sin necesitar rsync en el VPS) y corre
# el script con ssh -t, asi los prompts interactivos funcionan.
#
# Uso:  ./remote-run.sh [--dry-run] <script.sh> [argumentos...]
# Ej.:  ./remote-run.sh install-all.sh initech.fun admin@initech.fun --yes
#       ./remote-run.sh 08-healthcheck.sh initech.fun
# Config: vps.env junto a este script (ver vps.env.example), o VPS_ENV=/otra/ruta.
set -e
set -o pipefail   # que un fallo de tar (lado local) no pase inadvertido en "tar | ssh"

DRY_RUN=0
if [ "$1" = "--dry-run" ]; then DRY_RUN=1; shift; fi
if [ -z "$1" ]; then sed -n '2,9p' "$0"; exit 2; fi
SCRIPT=$1; shift

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE=${VPS_ENV:-$HERE/vps.env}
# shellcheck disable=SC1090
[ -f "$ENV_FILE" ] && source "$ENV_FILE"

VPS_USER=${VPS_USER:-ubuntu}
VPS_PORT=${VPS_PORT:-22}
REMOTE_DIR=${REMOTE_DIR:-vps-setup-run}
if [ -z "$VPS_HOST" ]; then
    echo "ERROR: falta VPS_HOST. Crea $ENV_FILE a partir de vps.env.example."
    exit 1
fi
if [ ! -f "$HERE/$SCRIPT" ]; then
    echo "ERROR: no existe $HERE/$SCRIPT"
    exit 1
fi

SSH_OPTS=(-p "$VPS_PORT" -o StrictHostKeyChecking=accept-new)
[ -n "$VPS_KEY" ] && SSH_OPTS+=(-i "${VPS_KEY/#\~/$HOME}")
TARGET="$VPS_USER@$VPS_HOST"

# Los argumentos se escapan para que sobrevivan al shell remoto (espacios, comillas).
printf -v REMOTE_ARGS ' %q' "$@"
REMOTE_CMD="cd $REMOTE_DIR/vps-setup && chmod +x ./*.sh && ./$SCRIPT$REMOTE_ARGS"

if [ "$DRY_RUN" -eq 1 ]; then
    echo "[dry-run] tar -C <repo> -czf - vps-setup landing-page | ssh ${SSH_OPTS[*]} $TARGET 'mkdir -p $REMOTE_DIR && tar -xzf - -C $REMOTE_DIR'"
    echo "[dry-run] ssh -t ${SSH_OPTS[*]} $TARGET '$REMOTE_CMD'"
    exit 0
fi

echo "Subiendo scripts a $TARGET:~/$REMOTE_DIR ..."
tar -C "$HERE/.." --exclude='vps-setup/vps.env' --exclude='vps-setup/tests' -czf - vps-setup landing-page \
    | ssh "${SSH_OPTS[@]}" "$TARGET" "mkdir -p $REMOTE_DIR && rm -rf $REMOTE_DIR/vps-setup $REMOTE_DIR/landing-page && tar -xzf - -C $REMOTE_DIR"

echo "Ejecutando $SCRIPT en el VPS ..."
ssh -t "${SSH_OPTS[@]}" "$TARGET" "$REMOTE_CMD"
