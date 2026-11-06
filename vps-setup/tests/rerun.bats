#!/usr/bin/env bats
load helpers

# Re-correr un script de vhost sobre un dominio que ya tiene HTTPS (certbot agrego los bloques 443).
setup() {
    setup_stubs
    export NGINX_DIR="$BATS_TEST_TMPDIR/nginx" WWW_DIR="$BATS_TEST_TMPDIR/www" PHP_UPLOAD_INI="$BATS_TEST_TMPDIR/php/90-uploads.ini"
    mkdir -p "$NGINX_DIR/sites-available" "$NGINX_DIR/sites-enabled" "$WWW_DIR" "$BATS_TEST_TMPDIR/php"
    # sudo falso: ejecuta de verdad en las carpetas temporales y se salta lo que apunte a /var o /etc reales
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"; case "$1" in certbot) exit "${CERTBOT_EXIT:-0}";; systemctl|nginx|chown|fail2ban-client) exit 0;; esac; for a in "$@"; do case "$a" in /var/*|/etc/*) exit 0;; esac; done; exec "$@"'
}

con_https() {
    printf '# vps-setup: 06_A-php tienda\nserver {\n    listen 443 ssl;\n    server_name tienda.ejemplo.com;\n    ssl_certificate /etc/letsencrypt/live/tienda.ejemplo.com/fullchain.pem;\n}\n' > "$NGINX_DIR/sites-available/tienda.ejemplo.com"
}

@test "re-correr 06_A sobre un dominio con HTTPS reinstala su certificado" {
    con_https
    run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -q "certbot install --nginx --cert-name tienda.ejemplo.com --redirect --non-interactive" "$SUDO_LOG"
    [[ "$output" == *"ya tenia HTTPS"* ]]
}

@test "sin HTTPS previo no se llama a certbot" {
    run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    ! grep -q "certbot" "$SUDO_LOG"
}

@test "si certbot no puede reinstalarlo, avisa como arreglarlo y el sitio queda en HTTP" {
    con_https
    CERTBOT_EXIT=1 run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 0 ]
    [[ "$output" == *"solo en HTTP"* ]] && [[ "$output" == *"04-setup-ssl.sh tienda.ejemplo.com"* ]]
}

@test "03, 06_B y 06_D tambien usan claim_nginx_vhost antes de reescribir (la reinstalacion los cubre)" {
    for s in 03-configure-nginx-site 06_A-setup-php-app 06_B-setup-python-app 06_D-setup-tomcat-app 06_E-setup-marcalibros-app; do
        grep -q "claim_nginx_vhost" "$VPS_DIR/$s.sh" || { echo "$s no llama a claim_nginx_vhost"; return 1; }
        grep -q "enable_nginx_site" "$VPS_DIR/$s.sh" || { echo "$s no llama a enable_nginx_site"; return 1; }
    done
}
