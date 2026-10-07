#!/usr/bin/env bats
load helpers

setup() {
    setup_stubs
    FAKE="$BATS_TEST_TMPDIR/fake"; mkdir -p "$FAKE"
    cp "$VPS_DIR/10_A-add-domain.sh" "$VPS_DIR/10_B-remove-domain.sh" "$VPS_DIR/lib.sh" "$FAKE/"
    export RUN_LOG="$BATS_TEST_TMPDIR/run.log"; : > "$RUN_LOG"
    for s in 03-configure-nginx-site 04-setup-ssl 06_A-setup-php-app 06_B-setup-python-app 06_D-setup-tomcat-app 08-healthcheck; do
        printf '#!/bin/bash\necho "$(basename "$0") $*" >> "$RUN_LOG"\n[ "$FAIL_STEP" = "$(basename "$0")" ] && exit 1\nexit 0\n' > "$FAKE/$s.sh"
    done
    chmod +x "$FAKE"/*.sh
    # arbol falso para 10_B
    export NGINX_DIR="$BATS_TEST_TMPDIR/nginx" WWW_DIR="$BATS_TEST_TMPDIR/www" NGINX_LOG_DIR="$BATS_TEST_TMPDIR/log" \
           SYSTEMD_DIR="$BATS_TEST_TMPDIR/systemd" REMOVED_DIR="$BATS_TEST_TMPDIR/removed"
    mkdir -p "$NGINX_DIR/sites-available" "$NGINX_DIR/sites-enabled" "$WWW_DIR/landing-page/ejemplo.com" "$WWW_DIR/miapp" "$NGINX_LOG_DIR/ejemplo.com" "$SYSTEMD_DIR"
    echo conf > "$NGINX_DIR/sites-available/ejemplo.com"
    ln -s "$NGINX_DIR/sites-available/ejemplo.com" "$NGINX_DIR/sites-enabled/ejemplo.com"
    echo hola > "$WWW_DIR/landing-page/ejemplo.com/index.html"
    echo log > "$NGINX_LOG_DIR/ejemplo.com/access.log"
    echo unit > "$SYSTEMD_DIR/miapp.service"
    export SUDO_EXEC=1
}

add() { run bash "$FAKE/10_A-add-domain.sh" "$@"; }
del() { run bash "$FAKE/10_B-remove-domain.sh" "$@"; }

# ---------------- 10_A ----------------
@test "10_A landing: solo 03 y healthcheck, sin SSL por defecto" {
    add ejemplo.com
    [ "$status" -eq 0 ]
    [ "$(sed 's/ .*//' "$RUN_LOG" | paste -sd,)" = "03-configure-nginx-site.sh,08-healthcheck.sh" ]
    grep -q "03-configure-nginx-site.sh ejemplo.com$" "$RUN_LOG"
    [[ "$output" == *"04-setup-ssl.sh ejemplo.com admin@ejemplo.com"* ]]   # solo como recordatorio
}

@test "10_A --ssl: 04 corre DESPUES del vhost y con el email dado" {
    add ejemplo.com --ssl --email a@ejemplo.com
    [ "$status" -eq 0 ]
    [ "$(sed 's/ .*//' "$RUN_LOG" | paste -sd,)" = "03-configure-nginx-site.sh,04-setup-ssl.sh,08-healthcheck.sh" ]
    grep -q "04-setup-ssl.sh ejemplo.com a@ejemplo.com$" "$RUN_LOG"
}

@test "10_A php/python/tomcat llaman al script correcto con sus argumentos" {
    add ejemplo.com --type php --name tienda
    grep -q "06_A-setup-php-app.sh tienda ejemplo.com$" "$RUN_LOG"
    : > "$RUN_LOG"; add ejemplo.com --type python
    grep -q "06_B-setup-python-app.sh ejemplo-com ejemplo.com$" "$RUN_LOG"    # nombre por defecto
    : > "$RUN_LOG"; add ejemplo.com --type tomcat --context app1
    grep -q "06_D-setup-tomcat-app.sh ejemplo.com app1$" "$RUN_LOG"
}

@test "10_A --dry-run no ejecuta nada" {
    add ejemplo.com --type php --ssl --dry-run
    [ "$status" -eq 0 ]
    [ ! -s "$RUN_LOG" ]
    [[ "$output" == *"+ ./06_A-setup-php-app.sh ejemplo-com ejemplo.com"* ]]
}

@test "10_A valida entradas antes de ejecutar nada" {
    add "Mal Dominio"; [ "$status" -eq 2 ]
    add ejemplo.com --type ruby; [ "$status" -eq 2 ]
    add ejemplo.com --type php --name 'a;b'; [ "$status" -eq 2 ]
    add ejemplo.com --type tomcat --context '../x'; [ "$status" -eq 2 ]
    add ejemplo.com --email malo; [ "$status" -eq 2 ]
    add ejemplo.com --nope; [ "$status" -eq 2 ]
    add; [ "$status" -eq 2 ]
    add a.com b.com; [ "$status" -eq 2 ]
    [ ! -s "$RUN_LOG" ]
}

@test "10_A si el paso del sitio falla, no sigue con SSL y sale con error" {
    FAIL_STEP=03-configure-nginx-site.sh add ejemplo.com --ssl
    [ "$status" -ne 0 ]
    ! grep -q 04-setup-ssl "$RUN_LOG"
}

@test "10_A un healthcheck con avisos no hace fallar el alta" {
    FAIL_STEP=08-healthcheck.sh add ejemplo.com
    [ "$status" -eq 0 ]
    [[ "$output" == *"AVISO"* ]]
}

# ---------------- 10_B ----------------
@test "10_B archiva vhost, sitio y logs SIN borrar nada, y desactiva el enlace" {
    del ejemplo.com --yes
    [ "$status" -eq 0 ]
    [ ! -e "$NGINX_DIR/sites-enabled/ejemplo.com" ] && [ ! -L "$NGINX_DIR/sites-enabled/ejemplo.com" ]
    d=$(ls -d "$REMOVED_DIR"/ejemplo.com-*)
    [ "$(cat "$d/sites-available/ejemplo.com")" = conf ]
    [ "$(cat "$d/www/landing-page/ejemplo.com/index.html")" = hola ]
    [ "$(cat "$d/logs/ejemplo.com/access.log")" = log ]
    [ -d "$WWW_DIR/miapp" ]            # la app no se toca sin --app
    grep -q "nginx -t" "$SUDO_LOG"; grep -q "systemctl reload nginx" "$SUDO_LOG"
    [[ "$output" == *"certbot delete --cert-name ejemplo.com"* ]]
}

@test "10_B --app archiva tambien la app y la unidad, y detiene el servicio" {
    del ejemplo.com --app miapp --yes
    [ "$status" -eq 0 ]
    d=$(ls -d "$REMOVED_DIR"/ejemplo.com-*)
    [ -d "$d/www/miapp" ] && [ "$(cat "$d/systemd/miapp.service")" = unit ]
    [ ! -e "$WWW_DIR/miapp" ]
    grep -q "systemctl disable --now miapp" "$SUDO_LOG"
}

@test "10_B --dry-run y la negativa a confirmar no tocan nada" {
    del ejemplo.com --dry-run
    [ "$status" -eq 0 ]; [ -L "$NGINX_DIR/sites-enabled/ejemplo.com" ]; [ ! -d "$REMOVED_DIR" ]
    run bash -c "echo n | bash '$FAKE/10_B-remove-domain.sh' ejemplo.com"
    [ "$status" -eq 1 ]; [ -L "$NGINX_DIR/sites-enabled/ejemplo.com" ]; [ -f "$WWW_DIR/landing-page/ejemplo.com/index.html" ]
}

@test "10_B si nginx -t falla sin el sitio, lo restaura y no archiva nada" {
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"; [ "$1" = nginx ] && exit 1; exec "$@"'
    del ejemplo.com --yes
    [ "$status" -eq 1 ]
    [ -L "$NGINX_DIR/sites-enabled/ejemplo.com" ]
    [ -f "$WWW_DIR/landing-page/ejemplo.com/index.html" ]
    [ ! -d "$REMOVED_DIR" ]
}

@test "10_B sin nada del dominio avisa y sale 0; entradas invalidas -> 2" {
    del otro.com --yes
    [ "$status" -eq 0 ]; [[ "$output" == *"No hay nada"* ]]
    del 'x;y' --yes; [ "$status" -eq 2 ]
    del ejemplo.com --app 'A B' --yes; [ "$status" -eq 2 ]
    [ -L "$NGINX_DIR/sites-enabled/ejemplo.com" ]
}
