#!/usr/bin/env bats
# apt, BIND, systemd, fail2ban y certbot REALES: valida lo que antes solo se probaba con stubs.
load ../helpers

ZD=bats-real-dns.example.com
APP=batsrealpy

setup() { setup_real; }

teardown() {
    pkill -x named > /dev/null 2>&1 || true
    [ -f "$BATS_TEST_TMPDIR/named.conf.local.orig" ] && cp "$BATS_TEST_TMPDIR/named.conf.local.orig" /etc/bind/named.conf.local
    rm -f "/etc/bind/zones/db.$ZD" /etc/fail2ban/jail.local /etc/apt/apt.conf.d/20auto-upgrades
    rm -f "/etc/systemd/system/$APP.service" "/etc/nginx/sites-enabled/$APP" "/etc/nginx/sites-available/$APP"
    rm -rf "/var/www/$APP" "/var/log/nginx/bats-real-py.example.com"
}

# ---------------- apt: los paquetes que instalan los scripts EXISTEN ----------------
@test "apt: todos los paquetes de 01, 02_A..02_I y 07_A existen en los repos (un typo abortaria install-all)" {
    command -v apt-cache > /dev/null || skip "falta apt-cache"
    # sudo que solo registra (nunca instala) + stubs de lo que los scripts invocan despues
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"; exit 0'
    make_stub java 'exit 0'; make_stub lsb_release 'echo "Description:	Ubuntu 24.04"'
    for s in 01-system-update 02_A-install-java 02_B-install-php 02_C-install-python 02_D-install-postgresql \
             02_E-install-nginx 02_F-install-certbot 02_G-install-mariadb 02_H-install-tomcat \
             02_I-install-python-whisper 07_A-install-fail2ban-autoupdates; do
        bash "$VPS_DIR/$s.sh" > /dev/null 2>&1 || true
    done
    pkgs=$(grep '^apt-get install' "$SUDO_LOG" | sed 's/^apt-get install//' | tr ' ' '\n' | grep -vE '^(-.*|)$' | sort -u)
    [ -n "$pkgs" ]
    [ "$(echo "$pkgs" | wc -l)" -ge 30 ]   # si el parseo fallara, no pasaria en silencio
    missing=""
    for p in $pkgs; do
        c=$(apt-cache policy "$p" 2>/dev/null | awk '/Candidate:/{print $2}')
        { [ -z "$c" ] || [ "$c" = "(none)" ]; } && missing="$missing $p"
    done
    [ -z "$missing" ] || { echo "paquetes que NO existen:$missing"; return 1; }
}

# ---------------- 06_C: BIND real ----------------
dns_stubs() {
    # Solo la consulta a OVH (red externa) y la "verificacion local" final se simulan;
    # todo lo demas usa el dig real.
    make_stub dig '
if [[ "$*" == *sdns2.ovh.ca* ]]; then echo 203.0.113.99; exit 0; fi
if [[ "$*" == "@127.0.0.1 "* ]] && [ ! -e "$BATS_TEST_TMPDIR/named.up" ]; then exit 0; fi
exec /usr/bin/dig "$@"'
}

run_06c() { run bash "$VPS_DIR/06_C-setup-dns-server.sh" $ZD 203.0.113.7 sdns2.ovh.ca; }

@test "06_C: named-checkconf y named-checkzone REALES aceptan la zona generada" {
    command -v named-checkzone > /dev/null || skip "falta bind9utils"
    dns_stubs; mkdir -p /etc/bind; touch /etc/bind/named.conf.local; cp /etc/bind/named.conf.local "$BATS_TEST_TMPDIR/named.conf.local.orig"
    run_06c
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    run named-checkconf;                          [ "$status" -eq 0 ]
    run named-checkzone $ZD /etc/bind/zones/db.$ZD
    [ "$status" -eq 0 ]; [[ "$output" == *"OK"* ]]
}

@test "06_C: re-ejecutar no duplica la zona y el serial SUBE (si no, OVH no vera los cambios)" {
    command -v named-checkzone > /dev/null || skip "falta bind9utils"
    dns_stubs; mkdir -p /etc/bind; touch /etc/bind/named.conf.local; cp /etc/bind/named.conf.local "$BATS_TEST_TMPDIR/named.conf.local.orig"
    run_06c; [ "$status" -eq 0 ]
    s1=$(named-checkzone -o - $ZD /etc/bind/zones/db.$ZD | awk '$4=="SOA"{print $7}')
    sleep 1
    run_06c; [ "$status" -eq 0 ]
    s2=$(named-checkzone -o - $ZD /etc/bind/zones/db.$ZD | awk '$4=="SOA"{print $7}')
    [ "$s2" -gt "$s1" ]
    [ "$(grep -c "zone \"$ZD\"" /etc/bind/named.conf.local)" -eq 1 ]
    run named-checkconf; [ "$status" -eq 0 ]
}

@test "06_C: un named REAL responde con los registros y RECHAZA la transferencia de zona desde otra IP" {
    command -v named > /dev/null || skip "falta bind9"
    ss -lnu 2>/dev/null | grep -qE ':53\s' && skip "el puerto 53 ya esta en uso"
    dns_stubs; mkdir -p /etc/bind; touch /etc/bind/named.conf.local; cp /etc/bind/named.conf.local "$BATS_TEST_TMPDIR/named.conf.local.orig"
    run_06c; [ "$status" -eq 0 ]
    mkdir -p /var/cache/bind /run/named; chown bind:bind /var/cache/bind /run/named 2>/dev/null || true
    named -u bind -c /etc/bind/named.conf
    touch "$BATS_TEST_TMPDIR/named.up"
    for _ in $(seq 1 25); do /usr/bin/dig @127.0.0.1 +short +time=1 +tries=1 $ZD A 2>/dev/null | grep -q . && break; sleep 0.4; done
    run /usr/bin/dig @127.0.0.1 +short $ZD A;          [ "$output" = "203.0.113.7" ]
    run /usr/bin/dig @127.0.0.1 +short www.$ZD A;      [ "$output" = "203.0.113.7" ]
    run /usr/bin/dig @127.0.0.1 +short $ZD NS;         [[ "$output" == *"ns1.$ZD."* ]]; [[ "$output" == *"sdns2.ovh.ca."* ]]
    run /usr/bin/dig @127.0.0.1 $ZD AXFR               # allow-transfer solo para la IP de OVH
    [[ "$output" == *"Transfer failed"* ]] || [[ "$output" == *"REFUSED"* ]]
}

# ---------------- 06_B: unidad systemd real ----------------
@test "06_B: la unidad systemd generada pasa 'systemd-analyze verify' sin claves desconocidas" {
    command -v systemd-analyze > /dev/null || skip "falta systemd-analyze"
    make_stub sudo '
case "$1" in
    systemctl|ufw|apt-get|certbot|fail2ban-client|chown) echo "$*" >> "$SUDO_LOG"; [ "$1 $2" = "ufw status" ] && echo "Status: active"; exit 0;;
    -u) echo "$*" >> "$SUDO_LOG"; exit 0;;   # venv + pip como www-data: se omiten (red)
esac
exec "$@"'
    run bash "$VPS_DIR/06_B-setup-python-app.sh" $APP bats-real-py.example.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    mkdir -p /var/www/$APP/venv/bin; printf '#!/bin/sh\n' > /var/www/$APP/venv/bin/gunicorn; chmod +x /var/www/$APP/venv/bin/gunicorn
    run systemd-analyze verify /etc/systemd/system/$APP.service
    echo "$output"
    [[ "$output" != *"Unknown key"* ]]
    [[ "$output" != *"Unknown section"* ]]
    [[ "$output" != *"Unknown lvalue"* ]]
    run nginx -t; [ "$status" -eq 0 ]    # y el vhost del proxy tambien
}

# ---------------- 07_A: fail2ban y apt reales ----------------
@test "07_A: fail2ban-client REAL acepta la configuracion y deja el jail sshd con backend systemd" {
    command -v fail2ban-client > /dev/null || skip "falta fail2ban"
    run bash "$VPS_DIR/07_A-install-fail2ban-autoupdates.sh"
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    run fail2ban-client -t
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    run fail2ban-client -d
    echo "$output" | grep -q "'add', 'sshd', 'systemd'"
}

@test "07_A: apt-config REAL lee las actualizaciones automaticas activadas" {
    command -v apt-config > /dev/null || skip "falta apt-config"
    bash "$VPS_DIR/07_A-install-fail2ban-autoupdates.sh" > /dev/null 2>&1 || true
    run apt-config dump
    echo "$output" | grep -qE 'APT::Periodic::Unattended-Upgrade "1"'
    echo "$output" | grep -qE 'APT::Periodic::Update-Package-Lists "1"'
}

# ---------------- 04: opciones de certbot reales ----------------
@test "04: certbot REAL acepta exactamente las opciones que usa el script (y rechaza una inventada)" {
    command -v certbot > /dev/null || skip "falta certbot"
    # un certbot roto por su entorno Python (no por el script) no debe dar un falso fallo local;
    # en CI el guard de "# skip" lo convierte igualmente en rojo
    certbot --version > /dev/null 2>&1 || skip "certbot instalado pero no arranca en este entorno"
    # extrae el comando "certbot run ..." de 04 y lo ejecuta con --help (solo analiza los argumentos)
    cmd=$(awk '/sudo certbot run/{f=1} f{print} f&&/-d www\.\$DOMAIN/{exit}' "$VPS_DIR/04-setup-ssl.sh" \
          | sed -e 's/sudo //' -e 's/\\$//' | tr '\n' ' ' | sed -e 's/\$EMAIL/a@ejemplo.com/' -e 's/\$DOMAIN/ejemplo.com/g')
    [[ "$cmd" == "certbot run --nginx"* ]]
    run bash -c "$cmd --help"
    [ "$status" -eq 0 ] || { echo "$cmd"; echo "$output"; return 1; }
    run bash -c "$cmd --opcion-inventada"
    [ "$status" -ne 0 ]
    run certbot certify --help       # el subcomando que se uso por error en una version anterior
    [ "$status" -ne 0 ]
}
