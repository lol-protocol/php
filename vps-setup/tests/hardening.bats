#!/usr/bin/env bats
load helpers

setup() {
    setup_stubs; setup_system_stubs
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
@test "07_A: jail solo de SSH con backend systemd, sin jails de nginx" {
    export FAIL2BAN_JAIL="$BATS_TEST_TMPDIR/jail.local" APT_AUTO_CONF="$BATS_TEST_TMPDIR/20auto"
    run bash "$VPS_DIR/07_A-install-fail2ban-autoupdates.sh"
    [ "$status" -eq 0 ]
    grep -q "^\[sshd\]" "$FAIL2BAN_JAIL"
    grep -q "^backend = systemd" "$FAIL2BAN_JAIL"
    grep -q "^enabled = true" "$FAIL2BAN_JAIL"
    ! grep -q "nginx" "$FAIL2BAN_JAIL"
    grep -q 'Unattended-Upgrade "1"' "$APT_AUTO_CONF"
    grep -q "apt-get install -y fail2ban unattended-upgrades" "$SUDO_LOG"
    grep -q "systemctl restart fail2ban" "$SUDO_LOG"
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
