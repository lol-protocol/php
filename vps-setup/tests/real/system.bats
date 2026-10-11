#!/usr/bin/env bats
# logrotate y sshd REALES.
load ../helpers

D=bats-real-lr.example.com
KEY="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIFakeKeyForRealSshdTests tester@laptop"

setup() { setup_real; }

teardown() {
    rm -rf "/var/log/nginx/$D" /etc/logrotate.d/nginx-domains
    rm -f /etc/ssh/sshd_config.d/00-hardening.conf /etc/ssh/sshd_config.d/50-bats-cloud-init.conf /etc/ssh/sshd_config.d/99-bats-late.conf
}

need_sshd() {
    [ -x /usr/sbin/sshd ] || skip "falta sshd"
    mkdir -p /run/sshd; ssh-keygen -A > /dev/null 2>&1 || true
    mkdir -p /etc/ssh/sshd_config.d
}

# ---------------- 07_D: logrotate ----------------
@test "07_D: logrotate real acepta la configuracion (-d)" {
    run bash "$VPS_DIR/07_D-setup-logrotate.sh"
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [[ "$output" == *"validada con logrotate -d"* ]]
}

@test "07_D: una rotacion forzada ROTA los logs por dominio (el logrotate del paquete nginx no los cubre)" {
    bash "$VPS_DIR/07_D-setup-logrotate.sh" > /dev/null
    mkdir -p "/var/log/nginx/$D"; echo "linea" > "/var/log/nginx/$D/access.log"; echo "err" > "/var/log/nginx/$D/error.log"
    # el logrotate de nginx (solo /var/log/nginx/*.log) NO debe tocar estos archivos
    if [ -f /etc/logrotate.d/nginx ]; then
        run logrotate -d -s "$BATS_TEST_TMPDIR/st0" /etc/logrotate.d/nginx
        ! echo "$output" | grep -q "/var/log/nginx/$D"
    fi
    run logrotate -f -s "$BATS_TEST_TMPDIR/st" /etc/logrotate.d/nginx-domains
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ -f "/var/log/nginx/$D/access.log.1" ]
    [ -f "/var/log/nginx/$D/error.log.1" ]
    [ -f "/var/log/nginx/$D/access.log" ]
    [ "$(cat "/var/log/nginx/$D/access.log.1")" = "linea" ]
    [ "$(stat -c '%a %U' "/var/log/nginx/$D/access.log")" = "640 www-data" ]
}

# ---------------- 07_C: sshd ----------------
run_07c() {
    export SSH_TARGET_USER=tester SSH_TARGET_HOME="$BATS_TEST_TMPDIR/home"; mkdir -p "$SSH_TARGET_HOME"
    run bash "$VPS_DIR/07_C-harden-ssh.sh" "$KEY" --yes
}

@test "07_C: sshd real acepta la configuracion y el valor EFECTIVO es solo-llave sin root" {
    need_sshd
    run_07c
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    run /usr/sbin/sshd -T
    echo "$output" | grep -qx "passwordauthentication no"
    echo "$output" | grep -qx "kbdinteractiveauthentication no"
    echo "$output" | grep -qx "permitrootlogin no"
    echo "$output" | grep -qx "pubkeyauthentication yes"
    echo "$output" | grep -i "^authorizedkeysfile" | grep -q '\.ssh/authorized_keys'
}

@test "07_C: gana a un 50-cloud-init.conf con 'PasswordAuthentication yes' (OpenSSH usa el PRIMER valor)" {
    need_sshd
    echo "PasswordAuthentication yes" > /etc/ssh/sshd_config.d/50-bats-cloud-init.conf
    run_07c
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    run /usr/sbin/sshd -T
    echo "$output" | grep -qx "passwordauthentication no"
}

@test "premisa de 07_C: un archivo 99- PIERDE contra un 50- (por eso se llama 00-hardening.conf)" {
    need_sshd
    echo "PasswordAuthentication yes" > /etc/ssh/sshd_config.d/50-bats-cloud-init.conf
    echo "PasswordAuthentication no"  > /etc/ssh/sshd_config.d/99-bats-late.conf
    run /usr/sbin/sshd -T
    echo "$output" | grep -qx "passwordauthentication yes"
}

@test "07_C: --revert deja a sshd con su configuracion anterior" {
    need_sshd
    run_07c
    run bash "$VPS_DIR/07_C-harden-ssh.sh" --revert
    [ "$status" -eq 0 ]
    [ ! -e /etc/ssh/sshd_config.d/00-hardening.conf ]
    run /usr/sbin/sshd -t
    [ "$status" -eq 0 ]
}
