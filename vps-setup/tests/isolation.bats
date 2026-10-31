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
