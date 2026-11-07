#!/usr/bin/env bats
# Aislamiento por app: usuario de sistema propio, carpeta 750, base de datos y .env propios.
load helpers

setup() {
    setup_stubs; setup_system_stubs
    export NGINX_DIR="$BATS_TEST_TMPDIR/nginx" SYSTEMD_DIR="$BATS_TEST_TMPDIR/systemd" WWW_DIR="$BATS_TEST_TMPDIR/www"
    mkdir -p "$NGINX_DIR/sites-available" "$NGINX_DIR/sites-enabled" "$SYSTEMD_DIR"
    rm -f "$STUB_BIN/openssl"                        # el real: genera la contrasena aleatoria (el stub era para fechas de certificados)
    export PSQL_IN="$BATS_TEST_TMPDIR/psql.in"; : > "$PSQL_IN"
    export NOPORT="8001 8002"
    make_stub psql 'exit 0'
    # stat: el dueno de la carpeta se simula con FAKE_OWNER (no hay un usuario real app-x)
    make_stub stat 'if [ "$1" = -c ] && [ "$2" = %U ]; then echo "${FAKE_OWNER:-root}"; else exec /usr/bin/stat "$@"; fi'
    # sudo: ejecuta de verdad solo lo que escribe en las rutas de prueba; el SQL de "sudo -u postgres psql"
    # entra por stdin y se guarda en $PSQL_IN; usuarios y servicios son solo un registro.
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"
case "$1" in
    -u) if [ "$2" = postgres ]; then
            case "$*" in
                *pg_roles*) echo "$ROLE_EXISTS" ;;
                *pg_database*) echo "$DB_EXISTS" ;;
                *createdb*) ;;
                *) cat >> "$PSQL_IN" ;;
            esac
        fi; exit 0 ;;
    systemctl|nginx|chown|ufw|fail2ban-client|useradd) exit 0 ;;
esac
for a in "$@"; do case "$a" in /var/*|/etc/*) exit 0;; esac; done
exec "$@"'
}

py() { run bash "$VPS_DIR/06_B-setup-python-app.sh" "$@"; }

@test "06_B crea el usuario de sistema app-<nombre>: sin login, sin home propio, grupo propio" {
    py blog blog.ejemplo.com 8001
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -q "useradd --system --user-group --home-dir $WWW_DIR/blog --no-create-home --shell /usr/sbin/nologin app-blog" "$SUDO_LOG"
}

@test "06_B: la carpeta pasa a ser SOLO de ese usuario (750), y venv, pip y el servicio corren como el" {
    py blog blog.ejemplo.com 8001
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -q "chown -R app-blog:app-blog $WWW_DIR/blog" "$SUDO_LOG"
    grep -q "chmod 750 $WWW_DIR/blog" "$SUDO_LOG"
    grep -q "^-u app-blog env -C $WWW_DIR/blog python3 -m venv" "$SUDO_LOG"
    grep -q "^-u app-blog env -C $WWW_DIR/blog .*pip install --no-cache-dir flask gunicorn" "$SUDO_LOG"
    u="$SYSTEMD_DIR/blog.service"
    grep -qx "User=app-blog" "$u" && grep -qx "Group=app-blog" "$u"
    grep -qx "EnvironmentFile=-$WWW_DIR/blog/.env" "$u"
    ! grep -q "www-data" "$u"
    ! grep -q "www-data" "$SUDO_LOG"
}

@test "06_B crea la base y el rol app_<nombre>, aislada (REVOKE a PUBLIC), con el SQL por stdin y no por la linea de comandos" {
    py blog blog.ejemplo.com 8001
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -qE "^CREATE ROLE app_blog LOGIN PASSWORD '[0-9a-f]{32}';$" "$PSQL_IN"
    grep -q "^REVOKE ALL ON DATABASE app_blog FROM PUBLIC;$" "$PSQL_IN"
    grep -q "createdb --owner=app_blog app_blog" "$SUDO_LOG"
    pw=$(sed -n "s/^CREATE ROLE app_blog LOGIN PASSWORD '\(.*\)';$/\1/p" "$PSQL_IN")
    [ -n "$pw" ]
    ! grep -qF "$pw" "$SUDO_LOG"                    # la contrasena no aparece en ninguna linea de comandos
    [[ "$output" != *"$pw"* ]]                      # ni en pantalla
}

@test "06_B: el .env tiene la DATABASE_URL con esa misma contrasena, modo 600 y de su usuario" {
    py blog blog.ejemplo.com 8001
    env="$WWW_DIR/blog/.env"
    [ -f "$env" ]
    [ "$(stat -c %a "$env")" = 600 ]
    grep -qE "^DATABASE_URL=postgresql://app_blog:[0-9a-f]{32}@localhost:5432/app_blog$" "$env"
    pw=$(sed -n "s/^DATABASE_URL=postgresql:\/\/app_blog:\([0-9a-f]*\)@.*/\1/p" "$env")
    grep -q "PASSWORD '$pw'" "$PSQL_IN"
    grep -q "chown app-blog:app-blog $env" "$SUDO_LOG"
}

@test "06_B es idempotente con la base: re-correr NO rota la contrasena ni toca PostgreSQL" {
    py blog blog.ejemplo.com 8001
    cp "$WWW_DIR/blog/.env" "$BATS_TEST_TMPDIR/env.1"; : > "$PSQL_IN"; : > "$SUDO_LOG"
    FAKE_OWNER=app-blog py blog blog.ejemplo.com 8001
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    cmp "$WWW_DIR/blog/.env" "$BATS_TEST_TMPDIR/env.1"
    [ ! -s "$PSQL_IN" ]
    ! grep -q "createdb" "$SUDO_LOG"
    [[ "$output" == *"ya esta configurada"* ]]
    ! grep -q "chown -R" "$SUDO_LOG"                # la carpeta ya era suya: nada de chown -R sobre su codigo
}

@test "06_B: si el rol y la base ya existen pero falta el .env, pone una contrasena nueva (ALTER) y no vuelve a crear la base" {
    ROLE_EXISTS=1 DB_EXISTS=1 py blog blog.ejemplo.com 8001
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -qE "^ALTER ROLE app_blog WITH LOGIN PASSWORD '[0-9a-f]{32}';$" "$PSQL_IN"
    ! grep -q "CREATE ROLE" "$PSQL_IN"
    ! grep -q "createdb" "$SUDO_LOG"
    [ -f "$WWW_DIR/blog/.env" ]
}

@test "06_B con APP_DB=0 no crea base ni .env; sin PostgreSQL instalado avisa y sigue" {
    APP_DB=0 py blog blog.ejemplo.com 8001
    [ "$status" -eq 0 ]
    [ ! -s "$PSQL_IN" ] && [ ! -f "$WWW_DIR/blog/.env" ]
    rm -f "$STUB_BIN/psql"
    # (si el contenedor tuviera un psql real, este caso no se puede probar)
    command -v psql > /dev/null && skip "hay un psql real en el PATH"
    py api api.ejemplo.com 8002
    [ "$status" -eq 0 ]
    [[ "$output" == *"PostgreSQL no esta instalado"* ]]
    [ ! -f "$WWW_DIR/api/.env" ]
}

@test "una carpeta con codigo de OTRO dueno no se toca: error que explica como migrarla" {
    mkdir -p "$WWW_DIR/blog"; echo 'x' > "$WWW_DIR/blog/app.py"
    FAKE_OWNER=www-data py blog blog.ejemplo.com 8001
    [ "$status" -eq 1 ]
    [[ "$output" == *"sudo chown -R app-blog:app-blog $WWW_DIR/blog"* ]]
    ! grep -q "chown -R" "$SUDO_LOG"
    [ ! -f "$SYSTEMD_DIR/blog.service" ]            # no llego a crear el servicio
}

@test "un nombre de app que no cabe en el usuario de Linux (32) se rechaza antes de crear nada" {
    n=$(printf 'a%.0s' $(seq 1 29))                  # app- + 29 = 33
    py "$n" a.ejemplo.com 8001
    [ "$status" -eq 2 ]
    [[ "$output" == *"como maximo 28"* ]]
    ! grep -q "useradd" "$SUDO_LOG"
    n=$(printf 'a%.0s' $(seq 1 28))                  # el limite exacto vale
    py "$n" b.ejemplo.com 8001
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
}

@test "10_A --type python con el nombre sacado de un dominio largo falla pronto y pide --name" {
    run bash "$VPS_DIR/10_A-add-domain.sh" una-tienda-con-un-dominio-muy-largo.ejemplo.com --type python --dry-run
    [ "$status" -eq 2 ]
    [[ "$output" == *"--name"* ]]
    run bash "$VPS_DIR/10_A-add-domain.sh" una-tienda-con-un-dominio-muy-largo.ejemplo.com --type python --name tienda --dry-run
    [ "$status" -eq 0 ]
}

@test "06_B reinicia el servicio (restart) y no solo lo arranca: re-correr con otro puerto debe aplicarse al servicio ya activo" {
    py blog blog.ejemplo.com 8001
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -q "^systemctl enable blog$" "$SUDO_LOG"
    grep -q "^systemctl restart blog$" "$SUDO_LOG"
    ! grep -q "^systemctl start blog$" "$SUDO_LOG"
    # y el unit nuevo se carga ANTES de reiniciar
    [ "$(grep -n '^systemctl daemon-reload$' "$SUDO_LOG" | head -1 | cut -d: -f1)" -lt "$(grep -n '^systemctl restart blog$' "$SUDO_LOG" | head -1 | cut -d: -f1)" ]
}

# ---- Apps PHP: usuario + pool PHP-FPM propios ----

php_setup() {
    export PHP_POOL_DIR="$BATS_TEST_TMPDIR/pool.d" PHP_UPLOAD_INI="$BATS_TEST_TMPDIR/90.ini"
    mkdir -p "$PHP_POOL_DIR"
    ROLE_EXISTS="" DB_EXISTS=""; export ROLE_EXISTS DB_EXISTS
}

@test "06_A: crea el usuario app-X, la carpeta es suya con grupo www-data y 750" {
    php_setup
    run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -q "useradd --system --user-group --home-dir $WWW_DIR/tienda .* app-tienda" "$SUDO_LOG"
    grep -q "chown -R app-tienda:www-data $WWW_DIR/tienda" "$SUDO_LOG"
    [ "$(/usr/bin/stat -c %a "$WWW_DIR/tienda")" = 750 ]
}

@test "06_A: pool propio (usuario, socket 0660 para www-data, ondemand) y Nginx apunta a ese socket" {
    php_setup
    run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    local pool="$PHP_POOL_DIR/app-tienda.conf"
    grep -qx '\[app-tienda\]' "$pool"
    grep -qx 'user = app-tienda' "$pool"
    grep -qx 'listen = /run/php/app-tienda.sock' "$pool"
    grep -qx 'listen.owner = www-data' "$pool" && grep -qx 'listen.mode = 0660' "$pool"
    grep -qx 'pm = ondemand' "$pool"
    grep -q 'fastcgi_pass unix:/run/php/app-tienda.sock;' "$NGINX_DIR/sites-available/tienda.ejemplo.com"
    grep -q "systemctl reload php8.3-fpm" "$SUDO_LOG"
}

@test "06_A: PHP_MAX_CHILDREN acota el pool" {
    php_setup
    PHP_MAX_CHILDREN=2 run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 0 ]
    grep -qx 'pm.max_children = 2' "$PHP_POOL_DIR/app-tienda.conf"
}

@test "06_A: base de datos propia en .env 600 (la clave no sale por pantalla ni por la linea de comandos)" {
    php_setup; export ROLE_EXISTS="" DB_EXISTS=""
    make_stub psql 'exit 0'
    run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    pw=$(sed -n "s/.*PASSWORD '\\([0-9a-f]*\\)'.*/\\1/p" "$PSQL_IN")
    [ -n "$pw" ]
    [ "$(/usr/bin/stat -c %a "$WWW_DIR/tienda/.env")" = 600 ]
    grep -q "DATABASE_URL=postgresql://app_tienda:$pw@localhost:5432/app_tienda" "$WWW_DIR/tienda/.env"
    [[ "$output" != *"$pw"* ]]
    ! grep -q "$pw" "$SUDO_LOG"
    [ -f "$WWW_DIR/tienda/index.php" ]            # la pagina de prueba se creo ANTES del .env
}

@test "06_A: carpeta con codigo de otro dueno (www-data) se rechaza y explica el chown" {
    php_setup
    mkdir -p "$WWW_DIR/tienda"; echo x > "$WWW_DIR/tienda/index.php"
    FAKE_OWNER=www-data run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 1 ]
    [[ "$output" == *"sudo chown -R app-tienda:www-data $WWW_DIR/tienda"* ]]
}

@test "06_A: pool que PHP-FPM rechaza se quita y aborta" {
    php_setup
    make_stub php-fpm8.3 'exit 1'
    run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 1 ]
    [ ! -e "$PHP_POOL_DIR/app-tienda.conf" ]
}

@test "10_B: al quitar una app PHP archiva su pool y recarga PHP-FPM" {
    php_setup
    export REMOVED_DIR="$BATS_TEST_TMPDIR/removed" NGINX_LOG_DIR="$BATS_TEST_TMPDIR/logs" CRON_DIR="$BATS_TEST_TMPDIR/cron"
    mkdir -p "$NGINX_LOG_DIR" "$CRON_DIR"
    bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com > /dev/null
    : > "$SUDO_LOG"
    run bash "$VPS_DIR/10_B-remove-domain.sh" tienda.ejemplo.com --yes
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ ! -e "$PHP_POOL_DIR/app-tienda.conf" ]
    [ -n "$(find "$REMOVED_DIR" -name app-tienda.conf)" ]
    grep -q "systemctl reload php8.3-fpm" "$SUDO_LOG"
}

@test "06_A: un .env que ya tenia tus variables (sin DATABASE_URL) se conserva y se le AGREGA la conexion" {
    php_setup
    mkdir -p "$WWW_DIR/tienda"; printf 'SECRETO=1' > "$WWW_DIR/tienda/.env"      # sin salto de linea final a proposito
    FAKE_OWNER=app-tienda run bash "$VPS_DIR/06_A-setup-php-app.sh" tienda tienda.ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ "$(sed -n 1p "$WWW_DIR/tienda/.env")" = 'SECRETO=1' ]
    sed -n 2p "$WWW_DIR/tienda/.env" | grep -q '^DATABASE_URL=postgresql://app_tienda:'
}
