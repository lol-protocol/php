# Utilidades comunes de los tests de vps-setup (bats).
# Sube desde el directorio del test hasta encontrar lib.sh (sirve para tests/ y tests/real/).
VPS_DIR="$(cd "$BATS_TEST_DIRNAME" && while [ ! -f lib.sh ] && [ "$PWD" != / ]; do cd ..; done; pwd)"

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

# ---- Pruebas con herramientas REALES (tests/real/) ----
# Escriben en /etc, /var y /run de verdad: solo en CI o en una maquina desechable.
# Usan el nginx, logrotate y sshd reales; lo unico simulado es lo que depende de
# systemd, ufw, apt, certbot y fail2ban (y chown, que requiere usuarios reales).
setup_real() {
    [ "$REAL_TESTS" = 1 ] || skip "pruebas reales: define REAL_TESTS=1 (solo en CI o una maquina desechable)"
    [ "$(id -u)" -eq 0 ] || skip "las pruebas reales deben correr como root (sudo -E bats ...)"
    for t in nginx logrotate; do command -v "$t" > /dev/null || skip "falta $t"; done
    # Todo el trafico es a este mismo equipo: que un proxy del entorno no se meta.
    unset http_proxy https_proxy HTTP_PROXY HTTPS_PROXY all_proxy ALL_PROXY
    STUB_BIN="$BATS_TEST_TMPDIR/bin"; mkdir -p "$STUB_BIN"
    export SUDO_LOG="$BATS_TEST_TMPDIR/sudo.log"; : > "$SUDO_LOG"
    make_stub sudo '
case "$1" in
    systemctl|ufw|apt-get|certbot|fail2ban-client|chown)
        echo "$*" >> "$SUDO_LOG"
        [ "$1 $2" = "ufw status" ] && echo "Status: active"
        exit 0;;
esac
exec "$@"'
    export PATH="$STUB_BIN:$PATH"
    setup_system_stubs
    # setup_system_stubs tambien simula curl/dig/ss/openssl: aqui se usan los REALES.
    rm -f "$STUB_BIN/curl" "$STUB_BIN/dig" "$STUB_BIN/ss" "$STUB_BIN/openssl"
    # Entornos sin IPv6 en el kernel (algunas sandboxes): "listen [::]:80" falla en el
    # bind aunque la sintaxis sea correcta. Solo ahi, "nginx" pasa por un envoltorio que
    # copia /etc/nginx, quita las lineas "listen [::]" y llama al nginx REAL. En un VPS o
    # en el runner de CI (con IPv6) no se usa y se prueba la configuracion tal cual.
    if [ ! -e /proc/net/if_inet6 ]; then
        make_stub nginx '
T=$(mktemp -d); trap "rm -rf $T" EXIT
cp -rL /etc/nginx/. "$T/"
grep -rl "listen \[::\]" "$T" 2>/dev/null | xargs -r sed -i "/listen \[::\]/d"
sed -i "s#/etc/nginx#$T#g" "$T/nginx.conf"
/usr/sbin/nginx -c "$T/nginx.conf" "$@"'
    fi
}

# nginx_up: arranca el nginx real con la configuracion instalada; salta si el puerto 80 esta ocupado.
nginx_up() {
    if ss -ltn 2>/dev/null | grep -qE ':80\s'; then skip "el puerto 80 ya esta en uso"; fi
    nginx -t > /dev/null 2>&1 || { nginx -t; return 1; }
    nginx
    for _ in $(seq 1 20); do curl -s -o /dev/null http://127.0.0.1/ && return 0; sleep 0.25; done
    return 0
}
nginx_down() { nginx -s stop > /dev/null 2>&1 || true; }
