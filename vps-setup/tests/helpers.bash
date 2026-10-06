# Utilidades comunes de los tests de vps-setup (bats).
VPS_DIR="$(cd "$BATS_TEST_DIRNAME/.." && pwd)"

# Crea $STUB_BIN con un "sudo" falso al frente del PATH: registra cada llamada en
# $SUDO_LOG y NUNCA toca el sistema real para chown/systemctl/ufw/apt/nginx/etc.
# El resto de comandos solo se ejecutan de verdad si SUDO_EXEC=1 (para probar
# escrituras en directorios temporales); si no, solo se registran.
setup_stubs() {
    STUB_BIN="$BATS_TEST_TMPDIR/bin"
    mkdir -p "$STUB_BIN"
    export SUDO_LOG="$BATS_TEST_TMPDIR/sudo.log"
    : > "$SUDO_LOG"
    make_stub sudo '
echo "$*" >> "$SUDO_LOG"
case "$1" in
    ufw) [ "$2" = status ] && echo "Status: ${UFW_STATUS:-active}"; exit 0;;
    chown|systemctl|nginx|apt-get|fail2ban-client|logrotate|certbot|sshd) exit 0;;
esac
if [ "$SUDO_EXEC" = 1 ]; then
    # Red de seguridad: con ejecucion real, jamas tocar rutas del sistema.
    for a in "$@"; do
        case "$a" in /etc/*|/var/*|/usr/*) echo "BLOQUEADO: sudo $* tocaria el sistema real (falta redirigir la ruta en el test)" >&2; exit 99;; esac
    done
    exec "$@"
fi
exit 0'
    export PATH="$STUB_BIN:$PATH"
}

# make_stub <nombre> <cuerpo bash>
make_stub() {
    printf '#!/bin/bash\n%s\n' "$2" > "$STUB_BIN/$1"
    chmod +x "$STUB_BIN/$1"
}

# Stubs de red/sistema que usan healthcheck, monitor e install-all.
setup_system_stubs() {
    make_stub curl '
if [[ "$*" == *ifconfig.me* ]]; then echo "x" >> "$BATS_TEST_TMPDIR/ifconfig.calls"; echo "${PUBLIC_IP:-203.0.113.9}"
elif [[ "$*" == *-sI* ]]; then printf "HTTP/2 200\r\n${HDRS-Strict-Transport-Security: x\r\nX-Frame-Options: y\r\nX-Content-Type-Options: z\r\nReferrer-Policy: w\r\n}"
elif [[ "$*" == *http://* ]]; then echo "${REDIR:-301}"
else echo "${CODE:-200}"; fi'
    make_stub dig 'echo "${DIGIP:-203.0.113.9}"'
    make_stub systemctl '
case "$1" in
    is-active) [[ " $DOWN " == *" $3 "* ]] && exit 3; exit 0;;
    list-unit-files) echo "$2 enabled";;
    --failed) echo "$FAILED_UNITS";;
esac
exit 0'
    make_stub ss 'p=$(echo "$2" | grep -o "[0-9]*"); [[ " $NOPORT " == *" $p "* ]] && exit 0; echo LISTEN'
    make_stub openssl '
if [ "$1" = s_client ]; then cat > /dev/null; else echo "notAfter=$(date -d "${CERT_WHEN:-+${CERT_DAYS:-40} days}" "+%b %d %T %Y GMT")"; fi'
}
