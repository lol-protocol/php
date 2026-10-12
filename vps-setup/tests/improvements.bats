#!/usr/bin/env bats
# Mejoras de robustez: argumentos obligatorios, un vhost por dominio, puertos de apps Python,
# 04 sin gastar intentos de Let's Encrypt, log de install-all.
load helpers

setup() {
    setup_stubs; setup_system_stubs
    export NGINX_DIR="$BATS_TEST_TMPDIR/nginx" SYSTEMD_DIR="$BATS_TEST_TMPDIR/systemd"
    mkdir -p "$NGINX_DIR/sites-available" "$NGINX_DIR/sites-enabled" "$SYSTEMD_DIR"
}

# ---- 1. sin dominio por defecto ----
@test "los scripts que configuran un dominio exigen el argumento (no usan initech.fun por defecto)" {
    for s in 03-configure-nginx-site 04-setup-ssl 05-deploy-landing-page 06_A-setup-php-app \
             06_B-setup-python-app 06_C-setup-dns-server 06_D-setup-tomcat-app install-all; do
        run bash "$VPS_DIR/$s.sh" < /dev/null
        [ "$status" -eq 2 ] || { echo "$s -> $status: $output"; return 1; }
        [[ "$output" == *"Uso:"* ]] || { echo "$s sin uso: $output"; return 1; }
    done
    ! grep -nE '\$\{[0-9]:-"[^"]*initech' "$VPS_DIR"/0*.sh "$VPS_DIR"/install-all.sh
    [ ! -s "$SUDO_LOG" ]
}

@test "06_C sin IP usa la IP publica detectada; si no se puede detectar, falla en vez de inventarla" {
    make_stub curl 'exit 1'          # ifconfig.me inalcanzable
    run bash "$VPS_DIR/06_C-setup-dns-server.sh" ejemplo.com < /dev/null
    [ "$status" -eq 2 ]
    [[ "$output" == *"IP del VPS"* ]]
    VPS_IP6='no-es-ipv6' run bash "$VPS_DIR/06_C-setup-dns-server.sh" ejemplo.com 203.0.113.7
    [ "$status" -eq 2 ]
}

# ---- 3. un vhost por dominio ----
@test "claim_nginx_vhost: re-correr el mismo sitio vale; otro tipo/app en el mismo dominio no" {
    source "$VPS_DIR/lib.sh"
    printf '# vps-setup: 06_A-php tienda\nserver { server_name ejemplo.com www.ejemplo.com; }\n' > "$NGINX_DIR/sites-available/ejemplo.com"
    ln -s "$NGINX_DIR/sites-available/ejemplo.com" "$NGINX_DIR/sites-enabled/ejemplo.com"
    run claim_nginx_vhost ejemplo.com "06_A-php tienda";  [ "$status" -eq 0 ]
    run claim_nginx_vhost ejemplo.com "06_A-php otra";    [ "$status" -eq 1 ]; [[ "$output" == *"06_A-php tienda"* ]]
    run claim_nginx_vhost ejemplo.com "03-landing";       [ "$status" -eq 1 ]
}

@test "claim_nginx_vhost: detecta otro sitio activo con el mismo server_name, sin falsos positivos por prefijo" {
    source "$VPS_DIR/lib.sh"
    printf 'server {\n    server_name ejemplo.com www.ejemplo.com;\n}\n' > "$NGINX_DIR/sites-available/viejo"
    ln -s "$NGINX_DIR/sites-available/viejo" "$NGINX_DIR/sites-enabled/viejo"
    run claim_nginx_vhost ejemplo.com "03-landing";      [ "$status" -eq 1 ]; [[ "$output" == *"viejo"* ]]
    run claim_nginx_vhost www.ejemplo.com "03-landing";  [ "$status" -eq 1 ]
    run claim_nginx_vhost ejemplo.com.ar "03-landing";   [ "$status" -eq 0 ]
    run claim_nginx_vhost sub.ejemplo.com "03-landing";  [ "$status" -eq 0 ]
    run claim_nginx_vhost ejemploXcom.ar "03-landing";   [ "$status" -eq 0 ]
}

@test "un archivo hecho a mano para el dominio no se pisa" {
    source "$VPS_DIR/lib.sh"
    echo 'server { listen 80; }' > "$NGINX_DIR/sites-available/ejemplo.com"
    run claim_nginx_vhost ejemplo.com "03-landing"
    [ "$status" -eq 1 ]; [[ "$output" == *"creado a mano"* ]]
}

# ---- 5. enable_nginx_site ----
@test "enable_nginx_site: si nginx -t falla, desactiva el sitio nuevo y no recarga" {
    source "$VPS_DIR/lib.sh"
    echo x > "$NGINX_DIR/sites-available/ejemplo.com"
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"; [ "$1 $2" = "nginx -t" ] && exit 1; [ "$1" = systemctl ] && exit 0; exec "$@"'
    run enable_nginx_site ejemplo.com
    [ "$status" -eq 1 ]
    [ ! -L "$NGINX_DIR/sites-enabled/ejemplo.com" ]
    ! grep -q "systemctl reload nginx" "$SUDO_LOG"
}

@test "enable_nginx_site: con nginx -t OK deja el enlace y recarga" {
    source "$VPS_DIR/lib.sh"
    echo x > "$NGINX_DIR/sites-available/ejemplo.com"
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"; case "$1" in nginx|systemctl) exit 0;; esac; exec "$@"'
    run enable_nginx_site ejemplo.com
    [ "$status" -eq 0 ]
    [ -L "$NGINX_DIR/sites-enabled/ejemplo.com" ]
    grep -q "systemctl reload nginx" "$SUDO_LOG"
}

@test "03/06_A/06_B/06_D usan enable_nginx_site y nombran el vhost por el dominio" {
    for s in 03-configure-nginx-site 06_A-setup-php-app 06_B-setup-python-app 06_D-setup-tomcat-app; do
        grep -q 'enable_nginx_site "\$DOMAIN"' "$VPS_DIR/$s.sh" || { echo "$s"; return 1; }
        grep -q 'sites-available/\$DOMAIN"' "$VPS_DIR/$s.sh" || { echo "$s"; return 1; }
        ! grep -q 'sites-available/\$APP_NAME' "$VPS_DIR/$s.sh"
    done
}

# ---- 6. landing sin PHP ----
@test "03: el vhost estatico de la landing NO ejecuta PHP" {
    ! grep -q 'fastcgi_pass' "$VPS_DIR/03-configure-nginx-site.sh"
}

# ---- 2. puertos de apps Python ----
@test "06_B rechaza un puerto que ya usa otra app y un puerto invalido" {
    printf 'ExecStart=/x/gunicorn --workers 3 --bind 127.0.0.1:8000 app:app\n' > "$SYSTEMD_DIR/otra.service"
    run bash "$VPS_DIR/06_B-setup-python-app.sh" blog blog.ejemplo.com
    [ "$status" -eq 1 ]; [[ "$output" == *"ya lo usa otra"* ]]
    run bash "$VPS_DIR/06_B-setup-python-app.sh" blog blog.ejemplo.com 80
    [ "$status" -eq 2 ]
    ! grep -q "systemctl" "$SUDO_LOG"
}

@test "06_B con un puerto libre lo usa en la unidad y en el proxy" {
    export NOPORT=8001 SUDO_EXEC=1
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"; case "$1" in -u|systemctl|nginx|chown|ufw) exit 0;; esac; for a in "$@"; do case "$a" in /var/*|/etc/*) exit 0;; esac; done; exec "$@"'
    run bash "$VPS_DIR/06_B-setup-python-app.sh" blog blog.ejemplo.com 8001
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -q -- '--bind 127.0.0.1:8001 app:app' "$SYSTEMD_DIR/blog.service"
    grep -q 'proxy_pass http://127.0.0.1:8001;' "$NGINX_DIR/sites-available/blog.ejemplo.com"
    head -1 "$NGINX_DIR/sites-available/blog.ejemplo.com" | grep -qx '# vps-setup: 06_B-python blog'
}

# ---- 4. 04 no gasta intentos de Let's Encrypt ----
@test "04 sin terminal y con DNS que no apunta al VPS: aborta SIN llamar a certbot" {
    DIGIP=198.51.100.1 run bash "$VPS_DIR/04-setup-ssl.sh" ejemplo.com < /dev/null
    [ "$status" -eq 1 ]
    [[ "$output" == *"--force"* ]]
    ! grep -q certbot "$SUDO_LOG"
}

@test "04 --force sigue aunque el DNS no apunte; --staging pide certificado de prueba" {
    DIGIP=198.51.100.1 run bash "$VPS_DIR/04-setup-ssl.sh" ejemplo.com --force < /dev/null
    [ "$status" -eq 0 ]
    grep -q "certbot run --nginx --agree-tos" "$SUDO_LOG"
    : > "$SUDO_LOG"
    run bash "$VPS_DIR/04-setup-ssl.sh" ejemplo.com a@ejemplo.com --staging < /dev/null
    [ "$status" -eq 0 ]
    grep -q "certbot run --nginx --test-cert .*--email a@ejemplo.com" "$SUDO_LOG"
    run bash "$VPS_DIR/04-setup-ssl.sh" ejemplo.com --inventada < /dev/null
    [ "$status" -eq 2 ]
}

# ---- 11. 10_A --port ----
@test "10_A --port pasa el puerto a 06_B y solo vale para --type python" {
    run bash "$VPS_DIR/10_A-add-domain.sh" blog.ejemplo.com --type python --name blog --port 8002 --dry-run
    [ "$status" -eq 0 ]
    [[ "$output" == *"+ ./06_B-setup-python-app.sh blog blog.ejemplo.com 8002"* ]]
    run bash "$VPS_DIR/10_A-add-domain.sh" ejemplo.com --port 8002 --dry-run
    [ "$status" -eq 2 ]
    run bash "$VPS_DIR/10_A-add-domain.sh" blog.ejemplo.com --type python --port 99 --dry-run
    [ "$status" -eq 2 ]
}
