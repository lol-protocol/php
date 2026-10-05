#!/bin/bash
# Endurece SSH: login solo con llave, sin root, sin contrasenas.
# Uso:   ./07_C-harden-ssh.sh <llave-publica | archivo.pub> [--yes]
#        ./07_C-harden-ssh.sh --revert        (deshace el endurecimiento)
# Corre como TU usuario normal (con sudo), no como root.
#
# ADVERTENCIA: si desactivas la contrasena sin que la llave funcione, te quedas
# fuera del VPS (solo recuperable por la consola de rescate del proveedor). Por eso
# el script exige confirmar que ya probaste entrar con la llave desde OTRA terminal.
set -e

source "$(dirname "$0")/lib.sh"

# Rutas reemplazables por variables de entorno (se usan en los tests).
SSHD_CONF_DIR=${SSHD_CONF_DIR:-/etc/ssh/sshd_config.d}
SSHD_BIN=${SSHD_BIN:-/usr/sbin/sshd}
SSH_SERVICE=${SSH_SERVICE:-ssh}
# Prefijo 00-: OpenSSH usa el PRIMER valor que lee de cada directiva, y los
# archivos de sshd_config.d se leen en orden alfabetico. Imagenes de nube suelen
# traer 50-cloud-init.conf con "PasswordAuthentication yes"; un archivo 99-
# quedaria ignorado y el endurecimiento no tendria efecto.
CONF_FILE="$SSHD_CONF_DIR/00-hardening.conf"

print_header "07_C" "Endurecimiento de SSH"

reload_ssh() { sudo systemctl reload "$SSH_SERVICE"; }

ASSUME_YES=0
REVERT=0
KEY_ARG=""
for arg in "$@"; do
    case "$arg" in
        --yes|-y) ASSUME_YES=1 ;;
        --revert) REVERT=1 ;;
        *) KEY_ARG="$arg" ;;
    esac
done

if [ "$REVERT" -eq 1 ]; then
    sudo rm -f "$CONF_FILE"
    sudo "$SSHD_BIN" -t
    reload_ssh
    echo "✓ Endurecimiento revertido: vuelve a regir la configuracion anterior de SSH."
    exit 0
fi

if [ -z "$KEY_ARG" ]; then
    echo "ERROR: falta la llave publica."
    echo "Uso: $0 'ssh-ed25519 AAAA... tu@equipo'   (o la ruta a un archivo .pub)"
    echo "Genera una en TU equipo con: ssh-keygen -t ed25519"
    exit 1
fi

# El usuario que va a entrar con llave. Nunca root: con PermitRootLogin no, si la
# unica llave fuera de root quedarias fuera.
TARGET_USER=${SSH_TARGET_USER:-${SUDO_USER:-${USER:-$(id -un)}}}
if [ "$TARGET_USER" = "root" ]; then
    echo "ERROR: corre este script como tu usuario normal con sudo (ej. 'ubuntu'), no como root."
    exit 1
fi
TARGET_HOME=${SSH_TARGET_HOME:-$(getent passwd "$TARGET_USER" | cut -d: -f6)}
if [ -z "$TARGET_HOME" ] || [ ! -d "$TARGET_HOME" ]; then
    echo "ERROR: no encuentro el home del usuario '$TARGET_USER'."
    exit 1
fi

if [ -f "$KEY_ARG" ]; then KEYS=$(cat "$KEY_ARG"); else KEYS="$KEY_ARG"; fi
KEY_RE='^(ssh-(rsa|ed25519)|ecdsa-sha2-nistp(256|384|521)|sk-ssh-ed25519@openssh\.com) [A-Za-z0-9+/=]+( .*)?$'
VALID_KEYS=()
while IFS= read -r line; do
    [ -z "$line" ] && continue
    [[ "$line" == \#* ]] && continue
    if [[ "$line" =~ $KEY_RE ]]; then
        VALID_KEYS+=("$line")
    else
        echo "ERROR: esto no parece una llave publica SSH valida: ${line:0:40}..."
        echo "Debe empezar con ssh-ed25519, ssh-rsa o ecdsa-sha2-... (nunca pegues la llave PRIVADA)."
        exit 1
    fi
done <<< "$KEYS"
if [ "${#VALID_KEYS[@]}" -eq 0 ]; then
    echo "ERROR: no se encontro ninguna llave publica en la entrada."
    exit 1
fi

SSH_DIR="$TARGET_HOME/.ssh"
AUTH="$SSH_DIR/authorized_keys"
sudo mkdir -p "$SSH_DIR"
sudo touch "$AUTH"
for k in "${VALID_KEYS[@]}"; do
    if sudo grep -qxF "$k" "$AUTH"; then
        echo "  La llave ya estaba autorizada: ${k:0:30}..."
    else
        printf '%s\n' "$k" | sudo tee -a "$AUTH" > /dev/null
        echo "  Llave agregada: ${k:0:30}..."
    fi
done
sudo chmod 700 "$SSH_DIR"
sudo chmod 600 "$AUTH"
sudo chown -R "$TARGET_USER:$TARGET_USER" "$SSH_DIR"

if [ "$ASSUME_YES" -ne 1 ]; then
    echo ""
    echo "ANTES DE CONTINUAR: abre OTRA terminal y comprueba que entras con la llave:"
    echo "    ssh -i <tu-llave-privada> $TARGET_USER@<IP_DEL_VPS>"
    echo "No cierres esta sesion hasta que eso funcione."
    if [ -t 0 ]; then
        read -r -p "¿Ya entraste con la llave desde otra terminal? Escribe SI para continuar: " REPLY
        [ "$REPLY" = "SI" ] || { echo "Cancelado: no se cambio nada de la configuracion de SSH."; exit 1; }
    else
        echo "Modo no interactivo: agrega --yes para confirmar que ya lo probaste."
        exit 1
    fi
fi

sudo mkdir -p "$SSHD_CONF_DIR"
sudo tee "$CONF_FILE" > /dev/null <<'EOSSH'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin no
PubkeyAuthentication yes
MaxAuthTries 3
EOSSH

# Valida TODA la configuracion antes de recargar; si falla, quita lo nuestro.
if ! sudo "$SSHD_BIN" -t; then
    sudo rm -f "$CONF_FILE"
    echo "ERROR: sshd rechazo la configuracion; se quito el archivo y NO se recargo SSH."
    exit 1
fi
# Comprueba el valor EFECTIVO (otro archivo podria estar ganando).
if ! sudo "$SSHD_BIN" -T | grep -qi '^passwordauthentication no'; then
    sudo rm -f "$CONF_FILE"
    echo "ERROR: PasswordAuthentication sigue activo por otra directiva; se revirtio el cambio."
    exit 1
fi
reload_ssh

echo ""
echo "✓ SSH endurecido: solo llave, sin contrasena, sin root."
echo "Si algo sale mal (desde una sesion ya abierta o la consola del proveedor):"
echo "    ./07_C-harden-ssh.sh --revert"
