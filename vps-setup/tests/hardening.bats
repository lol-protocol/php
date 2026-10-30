#!/usr/bin/env bats
load helpers

setup() {
    setup_stubs; setup_system_stubs; export SUDO_EXEC=0
    export SUDO_EXEC=1
    export SSHD_CONF_DIR="$BATS_TEST_TMPDIR/sshd_config.d"
    export SSH_TARGET_USER=tester SSH_TARGET_HOME="$BATS_TEST_TMPDIR/home"
    mkdir -p "$SSH_TARGET_HOME"
    # sshd falso: -t valida (rc configurable), -T imprime la config efectiva
    make_stub sshd '
case "$1" in
    -t) exit "${SSHD_T_RC:-0}";;
    -T) [ "$SSHD_PW_YES" = 1 ] && echo "passwordauthentication yes" || echo "passwordauthentication no"
        [ "$SSHD_PUBKEY_NO" = 1 ] && echo "pubkeyauthentication no" || echo "pubkeyauthentication yes"
        echo "authorizedkeysfile ${SSHD_AKF:-.ssh/authorized_keys .ssh/authorized_keys2}";;
esac'
    export SSHD_BIN="$STUB_BIN/sshd"
    KEY="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIFakeKeyForTests tester@laptop"
    AUTH="$SSH_TARGET_HOME/.ssh/authorized_keys"
    SSH="$VPS_DIR/07_C-harden-ssh.sh"
}

# ---- 07_C: SSH ----
@test "07_C sin llave: exit 1 con uso" {
    run bash "$SSH"
    [ "$status" -eq 1 ]
    [[ "$output" == *"falta la llave publica"* ]]
}

@test "07_C rechaza texto que no es una llave publica" {
    run bash "$SSH" "hola mundo" --yes
    [ "$status" -eq 1 ]
    [ ! -e "$SSHD_CONF_DIR/00-hardening.conf" ]
}

@test "07_C rechaza una llave PRIVADA pegada por error" {
    run bash "$SSH" "-----BEGIN OPENSSH PRIVATE KEY-----" --yes
    [ "$status" -eq 1 ]
    [ ! -e "$AUTH" ]
}

@test "07_C se niega a correr como root" {
    SSH_TARGET_USER=root run bash "$SSH" "$KEY" --yes
    [ "$status" -eq 1 ]
    [[ "$output" == *"no como root"* ]]
}

@test "07_C sin --yes y sin terminal NO toca SSH (la llave si queda autorizada)" {
    run bash "$SSH" "$KEY" < /dev/null
    [ "$status" -eq 1 ]
    [[ "$output" == *"--yes"* ]]
    [ ! -e "$SSHD_CONF_DIR/00-hardening.conf" ]
    grep -qxF "$KEY" "$AUTH"
}

@test "07_C --yes: autoriza la llave con permisos 600/700 y escribe el endurecimiento" {
    run bash "$SSH" "$KEY" --yes
    [ "$status" -eq 0 ]
    grep -qxF "$KEY" "$AUTH"
    [ "$(stat -c %a "$AUTH")" = "600" ]
    [ "$(stat -c %a "$SSH_TARGET_HOME/.ssh")" = "700" ]
    f="$SSHD_CONF_DIR/00-hardening.conf"
    grep -q "^PasswordAuthentication no" "$f"
    grep -q "^PermitRootLogin no" "$f"
    grep -q "^PubkeyAuthentication yes" "$f"
    grep -q "systemctl reload ssh" "$SUDO_LOG"
}

@test "07_C el archivo empieza con 00- (OpenSSH usa el primer valor leido)" {
    run bash "$SSH" "$KEY" --yes
    ls "$SSHD_CONF_DIR" | grep -q '^00-'
}

@test "07_C es idempotente: la llave no se duplica" {
    bash "$SSH" "$KEY" --yes
    bash "$SSH" "$KEY" --yes
    [ "$(grep -cxF "$KEY" "$AUTH")" -eq 1 ]
}

@test "07_C acepta la ruta a un archivo .pub" {
    printf '%s\n' "$KEY" > "$BATS_TEST_TMPDIR/k.pub"
    run bash "$SSH" "$BATS_TEST_TMPDIR/k.pub" --yes
    [ "$status" -eq 0 ]
    grep -qxF "$KEY" "$AUTH"
}

@test "07_C si sshd -t falla: quita el archivo y NO recarga" {
    SSHD_T_RC=1 run bash "$SSH" "$KEY" --yes
    [ "$status" -eq 1 ]
    [ ! -e "$SSHD_CONF_DIR/00-hardening.conf" ]
    ! grep -q "systemctl reload" "$SUDO_LOG"
}

@test "07_C si la contrasena sigue activa por otra directiva: revierte" {
    SSHD_PW_YES=1 run bash "$SSH" "$KEY" --yes
    [ "$status" -eq 1 ]
    [ ! -e "$SSHD_CONF_DIR/00-hardening.conf" ]
    ! grep -q "systemctl reload" "$SUDO_LOG"
}

@test "07_C si PubkeyAuthentication quedaria desactivada: revierte" {
    SSHD_PUBKEY_NO=1 run bash "$SSH" "$KEY" --yes
    [ "$status" -eq 1 ]
    [ ! -e "$SSHD_CONF_DIR/00-hardening.conf" ]
}

@test "07_C si AuthorizedKeysFile apunta a otro sitio: revierte" {
    SSHD_AKF="/etc/ssh/keys/%u" run bash "$SSH" "$KEY" --yes
    [ "$status" -eq 1 ]
    [ ! -e "$SSHD_CONF_DIR/00-hardening.conf" ]
    ! grep -q "systemctl reload" "$SUDO_LOG"
}

@test "07_C se niega si el home es escribible por grupo/otros (StrictModes ignoraria la llave)" {
    chmod 775 "$SSH_TARGET_HOME"
    run bash "$SSH" "$KEY" --yes
    [ "$status" -eq 1 ]
    [[ "$output" == *"escribible por grupo/otros"* ]]
    [ ! -e "$SSHD_CONF_DIR/00-hardening.conf" ]
}

@test "07_C no fija MaxAuthTries (un agente con varias llaves no debe bloquearse)" {
    bash "$SSH" "$KEY" --yes
    ! grep -qi "MaxAuthTries" "$SSHD_CONF_DIR/00-hardening.conf"
}

@test "07_C --revert quita el endurecimiento y recarga" {
    bash "$SSH" "$KEY" --yes
    : > "$SUDO_LOG"
    run bash "$SSH" --revert
    [ "$status" -eq 0 ]
    [ ! -e "$SSHD_CONF_DIR/00-hardening.conf" ]
    grep -q "systemctl reload ssh" "$SUDO_LOG"
}

# ---- 07_B: headers ----
@test "07_B escribe los 4 headers con 'always' y sin directivas obsoletas" {
    export HEADERS_CONF="$BATS_TEST_TMPDIR/security-headers.conf"
    export TOKENS_CONF="$BATS_TEST_TMPDIR/server-tokens.conf" DEFAULT_CONF="$BATS_TEST_TMPDIR/00-default.conf"
    run bash "$VPS_DIR/07_B-nginx-security-headers.sh"
    [ "$status" -eq 0 ]
    for h in Strict-Transport-Security X-Frame-Options X-Content-Type-Options Referrer-Policy; do
        grep -q "add_header $h .* always;" "$HEADERS_CONF"
    done
    ! grep -qi "x-xss-protection\|includeSubDomains\|preload" "$HEADERS_CONF"
    grep -q "nginx -t" "$SUDO_LOG"
    grep -q "systemctl reload nginx" "$SUDO_LOG"
}

# ---- 07_A: fail2ban + parches ----
@test "07_A: sshd (systemd), reincidentes y jails de nginx que cubren los logs POR DOMINIO" {
    export FAIL2BAN_JAIL="$BATS_TEST_TMPDIR/jail.local" APT_AUTO_CONF="$BATS_TEST_TMPDIR/20auto"
    export NGINX_LOG_DIR="$BATS_TEST_TMPDIR/nginxlog"; mkdir -p "$NGINX_LOG_DIR"
    run bash "$VPS_DIR/07_A-install-fail2ban-autoupdates.sh"
    [ "$status" -eq 0 ]
    grep -q "^\[sshd\]" "$FAIL2BAN_JAIL"
    grep -q "^backend = systemd" "$FAIL2BAN_JAIL"
    grep -q "^\[recidive\]" "$FAIL2BAN_JAIL" && grep -q "^bantime  = 1w" "$FAIL2BAN_JAIL"
    for j in nginx-botsearch nginx-http-auth nginx-bad-request; do grep -q "^\[$j\]" "$FAIL2BAN_JAIL"; done
    grep -q "$NGINX_LOG_DIR/\*/error.log" "$FAIL2BAN_JAIL"     # glob: todos los dominios
    grep -q "^logpath  = $NGINX_LOG_DIR/error.log" "$FAIL2BAN_JAIL"   # y uno que siempre existe
    [ -f "$NGINX_LOG_DIR/error.log" ] && [ -f "$NGINX_LOG_DIR/access.log" ]
    grep -q 'Unattended-Upgrade "1"' "$APT_AUTO_CONF"
    grep -q "apt-get install -y fail2ban unattended-upgrades python3-systemd" "$SUDO_LOG"
    grep -q "systemctl restart fail2ban" "$SUDO_LOG"
}

@test "07_A sin Nginx instalado: solo sshd y reincidentes (un logpath inexistente impediria arrancar)" {
    export FAIL2BAN_JAIL="$BATS_TEST_TMPDIR/jail.local" APT_AUTO_CONF="$BATS_TEST_TMPDIR/20auto"
    export NGINX_LOG_DIR="$BATS_TEST_TMPDIR/no-existe"
    run bash "$VPS_DIR/07_A-install-fail2ban-autoupdates.sh"
    [ "$status" -eq 0 ]
    ! grep -q nginx "$FAIL2BAN_JAIL"
    grep -q "^\[recidive\]" "$FAIL2BAN_JAIL"
}

@test "07_A espera a fail2ban (ping) antes de consultar el jail y no aborta si tarda" {
    export FAIL2BAN_JAIL="$BATS_TEST_TMPDIR/jail.local" APT_AUTO_CONF="$BATS_TEST_TMPDIR/20auto"
    export NGINX_LOG_DIR="$BATS_TEST_TMPDIR/nginxlog"; mkdir -p "$NGINX_LOG_DIR"
    run bash "$VPS_DIR/07_A-install-fail2ban-autoupdates.sh"
    ping=$(grep -n "fail2ban-client ping" "$SUDO_LOG" | head -1 | cut -d: -f1)
    stat=$(grep -n "fail2ban-client status sshd" "$SUDO_LOG" | head -1 | cut -d: -f1)
    [ -n "$ping" ] && [ "$ping" -lt "$stat" ]
}

# ---- 07_D: logrotate ----
@test "07_D cubre los logs POR DOMINIO (el logrotate de nginx no los cubre)" {
    export LOGROTATE_CONF="$BATS_TEST_TMPDIR/nginx-domains"
    run bash "$VPS_DIR/07_D-setup-logrotate.sh"
    [ "$status" -eq 0 ]
    grep -qF '/var/log/nginx/*/*.log {' "$LOGROTATE_CONF"
    grep -q "rotate 14" "$LOGROTATE_CONF"
    grep -q "create 0640 www-data adm" "$LOGROTATE_CONF"
    grep -q "invoke-rc.d nginx rotate" "$LOGROTATE_CONF"
}

@test "07_D la configuracion es valida para logrotate (si esta instalado)" {
    command -v logrotate > /dev/null || skip "logrotate no instalado"
    export LOGROTATE_CONF="$BATS_TEST_TMPDIR/nginx-domains"
    bash "$VPS_DIR/07_D-setup-logrotate.sh" > /dev/null
    /usr/sbin/logrotate -d "$LOGROTATE_CONF" > /dev/null 2>&1 || logrotate -d "$LOGROTATE_CONF" > /dev/null 2>&1
}

@test "07_B oculta la version de Nginx y define un vhost por defecto que corta el trafico ajeno" {
    export HEADERS_CONF="$BATS_TEST_TMPDIR/h.conf" TOKENS_CONF="$BATS_TEST_TMPDIR/t.conf" DEFAULT_CONF="$BATS_TEST_TMPDIR/d.conf"
    run bash "$VPS_DIR/07_B-nginx-security-headers.sh"
    [ "$status" -eq 0 ]
    grep -q '^server_tokens off;' "$TOKENS_CONF"
    grep -q 'listen 80 default_server;' "$DEFAULT_CONF" && grep -q 'return 444;' "$DEFAULT_CONF"
    grep -q 'listen 443 ssl default_server;' "$DEFAULT_CONF" && grep -q 'ssl_reject_handshake on;' "$DEFAULT_CONF"
}

# ---- 02_J: Webmin ----
@test "02_J no usa una ruta fija en /tmp para el script que ejecuta como root" {
    ! grep -q '/tmp/setup-repos' "$VPS_DIR/02_J-install-webmin.sh"
    grep -q 'mktemp -d' "$VPS_DIR/02_J-install-webmin.sh"
}

# sudo que EJECUTA de verdad solo lo que toca el miniserv.conf de prueba y simula el resto
webmin_sudo() {
    export WEBMIN_CONF="$BATS_TEST_TMPDIR/miniserv.conf" UFW_OUT="$BATS_TEST_TMPDIR/ufw.out"
    printf 'port=10000\nlisten=10000\n' > "$WEBMIN_CONF"
    printf 'Status: active\n' > "$UFW_OUT"
    make_stub apt-cache 'exit 0'
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"
case "$1" in
    sed|grep|tee) for a in "$@"; do [ "$a" = "$WEBMIN_CONF" ] && exec "$@"; done ;;
    ufw) [ "$2" = status ] && cat "$UFW_OUT" ;;
esac
exit 0'
}

@test "02_J por defecto: Webmin escucha SOLO en localhost (bind=127.0.0.1), sin regla de UFW, y explica el tunel SSH" {
    setup_stubs; setup_system_stubs; webmin_sudo
    run bash "$VPS_DIR/02_J-install-webmin.sh" < /dev/null
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -qx 'bind=127.0.0.1' "$WEBMIN_CONF"
    [ "$(grep -c '^bind=' "$WEBMIN_CONF")" -eq 1 ]
    grep -qx 'port=10000' "$WEBMIN_CONF"                         # lo demas de la config intacto
    ! grep -q "ufw allow" "$SUDO_LOG"
    grep -q "systemctl restart webmin" "$SUDO_LOG"               # miniserv.conf solo se relee al reiniciar
    [[ "$output" == *"ssh -L 10000:localhost:10000"* ]] && [[ "$output" == *"https://localhost:10000"* ]]
    [[ "$output" != *"AVISO DE SEGURIDAD"* ]]
}

@test "02_J es idempotente: re-correr no duplica el bind, y quita la regla abierta de una instalacion anterior" {
    setup_stubs; setup_system_stubs; webmin_sudo
    printf 'port=10000\nbind=0.0.0.0\n' > "$WEBMIN_CONF"
    printf 'Status: active\n10000/tcp                   ALLOW       Anywhere\n' > "$UFW_OUT"
    run bash "$VPS_DIR/02_J-install-webmin.sh" < /dev/null
    run bash "$VPS_DIR/02_J-install-webmin.sh" < /dev/null
    [ "$status" -eq 0 ]
    [ "$(grep -c '^bind=' "$WEBMIN_CONF")" -eq 1 ] && grep -qx 'bind=127.0.0.1' "$WEBMIN_CONF"
    grep -q "ufw delete allow 10000/tcp" "$SUDO_LOG"
}

@test "02_J WEBMIN_ALLOW_FROM: escucha en todas las interfaces y UFW solo deja pasar a esa IP" {
    setup_stubs; setup_system_stubs; webmin_sudo
    printf 'port=10000\nbind=127.0.0.1\n' > "$WEBMIN_CONF"
    WEBMIN_ALLOW_FROM=203.0.113.5 run bash "$VPS_DIR/02_J-install-webmin.sh" < /dev/null
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    ! grep -q '^bind=' "$WEBMIN_CONF"                            # quitado el bind de localhost
    grep -q "ufw allow from 203.0.113.5 to any port 10000 proto tcp" "$SUDO_LOG"
    ! grep -q "ufw allow 10000/tcp" "$SUDO_LOG"
    [[ "$output" == *"solo desde 203.0.113.5"* ]] && [[ "$output" != *"AVISO DE SEGURIDAD"* ]]
}

@test "02_J WEBMIN_PUBLIC=1: abierto a todo internet, y lo avisa" {
    setup_stubs; setup_system_stubs; webmin_sudo
    WEBMIN_PUBLIC=1 run bash "$VPS_DIR/02_J-install-webmin.sh" < /dev/null
    [ "$status" -eq 0 ]
    ! grep -q '^bind=' "$WEBMIN_CONF"
    grep -q "ufw allow 10000/tcp" "$SUDO_LOG"
    [[ "$output" == *"AVISO DE SEGURIDAD"* ]]
}

@test "02_J WEBMIN_ALLOW_FROM y WEBMIN_PUBLIC=1 a la vez se rechazan antes de instalar nada" {
    setup_stubs; setup_system_stubs; webmin_sudo
    WEBMIN_ALLOW_FROM=203.0.113.5 WEBMIN_PUBLIC=1 run bash "$VPS_DIR/02_J-install-webmin.sh" < /dev/null
    [ "$status" -eq 2 ]
    ! grep -q "apt-get install" "$SUDO_LOG"
}

@test "02_J rechaza un WEBMIN_ALLOW_FROM invalido antes de tocar el firewall" {
    setup_stubs; setup_system_stubs; export SUDO_EXEC=0
    WEBMIN_ALLOW_FROM='1.2.3.4; rm -rf /' run bash "$VPS_DIR/02_J-install-webmin.sh"
    [ "$status" -eq 2 ]
    ! grep -q "ufw allow" "$SUDO_LOG"
    ! grep -q "apt-get install" "$SUDO_LOG"     # ni siquiera llega a instalar
}

# ---- 07_E: rendimiento ----
@test "07_E escribe gzip ampliado + open_file_cache SIN repetir 'gzip on;' (duplicado = nginx -t falla)" {
    export PERF_CONF="$BATS_TEST_TMPDIR/performance.conf"
    run bash "$VPS_DIR/07_E-nginx-performance.sh"
    [ "$status" -eq 0 ]
    grep -q '^gzip_types .*application/json.*image/svg+xml' "$PERF_CONF"
    grep -q '^gzip_vary on;' "$PERF_CONF" && grep -q '^open_file_cache ' "$PERF_CONF"
    ! grep -q '^gzip on;' "$PERF_CONF"
}

@test "07_E si nginx -t falla, quita su archivo y sale con error" {
    export PERF_CONF="$BATS_TEST_TMPDIR/performance.conf"
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"; [ "$1 $2" = "nginx -t" ] && exit 1; [ "$1" = rm ] && exec "$@"; [ "$1" = tee ] && exec "$@"; exit 0'
    run bash "$VPS_DIR/07_E-nginx-performance.sh"
    [ "$status" -eq 1 ]
    [ ! -e "$PERF_CONF" ]
}

# ---- 01: apt robusto ----
@test "01 configura apt (espera el lock, conserva tus configs) y needrestart automatico" {
    export APT_TUNING_CONF="$BATS_TEST_TMPDIR/90-vps-setup" NEEDRESTART_DIR="$BATS_TEST_TMPDIR/needrestart/conf.d"
    mkdir -p "$BATS_TEST_TMPDIR/needrestart"
    printf 'ID=ubuntu\nVERSION_ID="24.04"\n' > "$BATS_TEST_TMPDIR/os-release"; export OS_RELEASE="$BATS_TEST_TMPDIR/os-release"
    make_stub lsb_release 'echo "Description:	Ubuntu 24.04"'
    run bash "$VPS_DIR/01-system-update.sh"
    [ "$status" -eq 0 ]
    grep -q 'DPkg::Lock::Timeout "300";' "$APT_TUNING_CONF"
    grep -q -- '--force-confold' "$APT_TUNING_CONF"
    grep -qF "\$nrconf{restart} = 'a';" "$NEEDRESTART_DIR/50-vps-setup.conf"
    grep -q "^ufw limit OpenSSH" "$SUDO_LOG"          # SSH con limite de conexiones, no solo "allow"
    ! grep -q "^ufw allow OpenSSH" "$SUDO_LOG"
}

@test "02_J llama al instalador de Webmin con --force (sin la pregunta y/N que bloquea por ssh -t)" {
    setup_stubs; setup_system_stubs; export SUDO_EXEC=0
    make_stub apt-cache 'exit 0'
    run bash "$VPS_DIR/02_J-install-webmin.sh" < /dev/null
    [ "$status" -eq 0 ]
    grep -qE '^sh .*/setup-repos\.sh --force$' "$SUDO_LOG"
}

@test "02_J falla CLARO si el repo de Webmin no quedo configurado (el instalador sale 0 aunque falle)" {
    setup_stubs; setup_system_stubs; export SUDO_EXEC=0
    make_stub apt-cache 'exit 100'      # 'webmin' no aparece en apt
    run bash "$VPS_DIR/02_J-install-webmin.sh" < /dev/null
    [ "$status" -eq 1 ]
    [[ "$output" == *"repositorio de Webmin no quedo configurado"* ]]
    ! grep -q "apt-get install -y --install-recommends webmin" "$SUDO_LOG"
    ! grep -q "ufw allow" "$SUDO_LOG"
}

# ---- 01: solo Ubuntu 24.04 ----
@test "01 se niega a correr en otra version de Ubuntu (26.04 trae PHP 8.5: php8.3-* no existe) salvo ALLOW_UNSUPPORTED_OS=1" {
    printf 'ID=ubuntu\nVERSION_ID="26.04"\n' > "$BATS_TEST_TMPDIR/os-release"
    export OS_RELEASE="$BATS_TEST_TMPDIR/os-release" APT_TUNING_CONF="$BATS_TEST_TMPDIR/90" NEEDRESTART_DIR="$BATS_TEST_TMPDIR/nr/conf.d"
    make_stub lsb_release 'echo "Description:	Ubuntu"'
    run bash "$VPS_DIR/01-system-update.sh"
    [ "$status" -eq 1 ]
    [[ "$output" == *"ubuntu 26.04"* ]] && [[ "$output" == *"Ubuntu 24.04"* ]]
    [ ! -s "$SUDO_LOG" ]                 # no toco nada
    ALLOW_UNSUPPORTED_OS=1 run bash "$VPS_DIR/01-system-update.sh"
    [ "$status" -eq 0 ]
    printf 'ID=ubuntu\nVERSION_ID="24.04"\n' > "$OS_RELEASE"
    : > "$SUDO_LOG"; run bash "$VPS_DIR/01-system-update.sh"
    [ "$status" -eq 0 ]
}
