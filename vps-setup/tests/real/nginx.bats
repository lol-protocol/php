#!/usr/bin/env bats
# Nginx REAL: los vhosts y headers que generan los scripts se validan con "nginx -t" y,
# donde se puede, sirviendo trafico de verdad. Es lo mas cercano al VPS sin VPS.
load ../helpers

D1=bats-real-a.example.com
APP=batsrealapp
D2=bats-real-b.example.com
D3=bats-real-c.example.com

setup() { setup_real; }

teardown() {
    nginx_down
    rm -f /etc/nginx/conf.d/security-headers.conf /etc/nginx/conf.d/server-tokens.conf /etc/nginx/conf.d/00-default-server.conf /etc/nginx/conf.d/bats-real-*.conf
    for n in $D1 $APP $D3; do rm -f "/etc/nginx/sites-enabled/$n" "/etc/nginx/sites-available/$n"; done
    rm -rf "/var/www/landing-page/$D1" "/var/www/$APP" "/var/log/nginx/$D1" "/var/log/nginx/$D2" "/var/log/nginx/$D3"
    sed -i "/$D1/d" /etc/hosts 2>/dev/null || true
}

@test "03 + 05 + 07_B: la landing page se sirve de verdad con los 4 headers y .ht bloqueado" {
    run bash "$VPS_DIR/03-configure-nginx-site.sh" $D1;   [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    run bash "$VPS_DIR/07_B-nginx-security-headers.sh";   [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    run bash "$VPS_DIR/05-deploy-landing-page.sh" $D1;    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    nginx_up

    run curl -s -D - -H "Host: $D1" http://127.0.0.1/
    [[ "$output" == *"200 OK"* ]]
    [[ "$output" == *"Initech"* ]]
    for h in Strict-Transport-Security X-Frame-Options X-Content-Type-Options Referrer-Policy; do
        echo "$output" | grep -qi "^$h:" || { echo "falta el header $h"; return 1; }
    done
    # los headers tambien deben ir en respuestas de error ("always")
    run curl -s -o /dev/null -D - -H "Host: $D1" http://127.0.0.1/no-existe
    [[ "$output" == *"404"* ]]
    echo "$output" | grep -qi '^X-Frame-Options:'
    run curl -s -o /dev/null -w '%{http_code}' -H "Host: $D1" http://127.0.0.1/.htaccess
    [ "$output" = "403" ]
}

@test "03 no deja el vhost anidado: index.html esta en la raiz del dominio (deploy_files real)" {
    bash "$VPS_DIR/03-configure-nginx-site.sh" $D1 > /dev/null
    [ -f "/var/www/landing-page/$D1/index.html" ]
    [ ! -e "/var/www/landing-page/$D1/landing-page" ]
    [ "$(stat -c %a "/var/www/landing-page/$D1/index.html")" = "644" ]
}

@test "06_A: el vhost PHP pasa nginx -t real" {
    run bash "$VPS_DIR/06_A-setup-php-app.sh" $APP $D2
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -q "fastcgi_pass unix:/run/php/php8.3-fpm.sock" /etc/nginx/sites-available/$APP
    run nginx -t
    [ "$status" -eq 0 ]
}

@test "06_D: el proxy a Tomcat pasa nginx -t real y termina el proxy_pass en '/'" {
    run bash "$VPS_DIR/06_D-setup-tomcat-app.sh" $D3 miapp
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -q "proxy_pass http://127.0.0.1:8080/miapp/;" /etc/nginx/sites-available/$D3
    ! grep -q 'manager' /etc/nginx/sites-available/$D3     # con context path no hace falta
    run nginx -t
    [ "$status" -eq 0 ]
}

@test "06_D en la raiz: /manager y /host-manager de Tomcat NO se alcanzan desde internet" {
    run bash "$VPS_DIR/06_D-setup-tomcat-app.sh" $D3
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    nginx -t
    nginx_up
    for p in /manager /manager/html /host-manager/html; do
        run curl -s -o /dev/null -w '%{http_code}' -H "Host: $D3" "http://127.0.0.1$p"
        [ "$output" = "404" ] || { echo "$p -> $output"; return 1; }
    done
    # lo demas sigue yendo a Tomcat (no esta corriendo aqui: 502, no 404 de nginx)
    run curl -s -o /dev/null -w '%{http_code}' -H "Host: $D3" "http://127.0.0.1/otra-ruta"
    [ "$output" = "502" ]
}

@test "07_B: server_tokens off y el vhost por defecto corta Host ajenos sin romper los dominios propios" {
    bash "$VPS_DIR/03-configure-nginx-site.sh" $D1 > /dev/null
    run bash "$VPS_DIR/07_B-nginx-security-headers.sh"; [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    nginx -t
    nginx_up
    run curl -s -D - -o /dev/null -H "Host: $D1" http://127.0.0.1/
    [[ "$output" == *"200"* ]]
    echo "$output" | grep -i '^server:' | grep -qE '^[Ss]erver: nginx\s*$'      # sin /1.24.0
    run curl -s -o /dev/null -w '%{http_code}' -H "Host: desconocido.example.net" http://127.0.0.1/
    [ "$status" -ne 0 ] || [ "$output" = "000" ]                                # 444: conexion cerrada
    run curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1/                # por IP, sin Host de nuestros dominios
    [ "$output" = "000" ]
}

@test "dos dominios conviven (cada uno su vhost y su carpeta)" {
    bash "$VPS_DIR/03-configure-nginx-site.sh" $D1 > /dev/null
    bash "$VPS_DIR/06_D-setup-tomcat-app.sh" $D3 > /dev/null
    run nginx -t
    [ "$status" -eq 0 ]
    [ -e /etc/nginx/sites-enabled/$D1 ] && [ -e /etc/nginx/sites-enabled/$D3 ]
}

@test "08-healthcheck contra un nginx real con certificado: HTTPS, redireccion, cert y headers" {
    command -v openssl > /dev/null || skip "falta openssl"
    bash "$VPS_DIR/03-configure-nginx-site.sh" $D1 > /dev/null
    bash "$VPS_DIR/07_B-nginx-security-headers.sh" > /dev/null
    # certificado autofirmado para el dominio de prueba + vhost 443 y redireccion 80->443
    cert="$BATS_TEST_TMPDIR/c.pem"; key="$BATS_TEST_TMPDIR/k.pem"
    openssl req -x509 -newkey rsa:2048 -nodes -keyout "$key" -out "$cert" -days 30 -subj "/CN=$D1" > /dev/null 2>&1
    rm -f /etc/nginx/sites-enabled/$D1 /etc/nginx/sites-available/$D1   # lo reemplaza el vhost de prueba
    cat > /etc/nginx/conf.d/bats-real-hc.conf <<EOT
server { listen 80; server_name $D1; return 301 https://\$host\$request_uri; }
server { listen 443 ssl; server_name $D1; ssl_certificate $cert; ssl_certificate_key $key;
         root /var/www/landing-page/$D1; index index.html; }
EOT
    echo "127.0.0.1 $D1" >> /etc/hosts
    nginx_up
    export CURL_CA_BUNDLE="$cert"
    run bash "$VPS_DIR/08-healthcheck.sh" $D1
    echo "$output" | grep -q "\[OK\]    https://$D1 responde 200"
    echo "$output" | grep -q "\[OK\]    http://$D1 redirige a HTTPS (301)"
    echo "$output" | grep -qE "\[OK\]    certificado vence en (29|30) dias"
    for h in Strict-Transport-Security X-Frame-Options X-Content-Type-Options Referrer-Policy; do
        echo "$output" | grep -q "\[OK\]    $h" || { echo "$output"; return 1; }
    done
}

@test "10_B: con nginx REAL, quitar un dominio lo archiva, nginx -t sigue OK y el otro dominio sigue sirviendo" {
    bash "$VPS_DIR/03-configure-nginx-site.sh" $D1 > /dev/null
    bash "$VPS_DIR/03-configure-nginx-site.sh" $D3 > /dev/null
    nginx_up
    run curl -s -o /dev/null -w '%{http_code}' -H "Host: $D1" http://127.0.0.1/;  [ "$output" = "200" ]
    export REMOVED_DIR="$BATS_TEST_TMPDIR/removed"
    run bash "$VPS_DIR/10_B-remove-domain.sh" $D1 --yes
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ ! -e /etc/nginx/sites-enabled/$D1 ] && [ ! -e /etc/nginx/sites-available/$D1 ]
    d=$(ls -d "$REMOVED_DIR"/$D1-*)
    [ -f "$d/sites-available/$D1" ] && [ -f "$d/www/landing-page/$D1/index.html" ]
    nginx -t
    run curl -s -o /dev/null -w '%{http_code}' -H "Host: $D3" http://127.0.0.1/;  [ "$output" = "200" ]
}
