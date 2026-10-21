#!/usr/bin/env bats
load helpers

# 06_E instala Marcalibros; el codigo sale del propio monorepo ($VPS_DIR/..).
setup() {
    setup_stubs
    make_stub nginx 'exit 0'
    make_stub php 'case "$1" in -m) printf "gd\nmbstring\nfileinfo\nexif\nzlib\n";; -r) exit "${NOWEBP:-0}";; esac'
    export NGINX_DIR="$BATS_TEST_TMPDIR/nginx" WWW_DIR="$BATS_TEST_TMPDIR/www" DATA_DIR="$BATS_TEST_TMPDIR/data" \
           CRON_DIR="$BATS_TEST_TMPDIR/cron" PHP_UPLOAD_INI="$BATS_TEST_TMPDIR/php/90-uploads.ini" \
           NGINX_LOG_DIR="$BATS_TEST_TMPDIR/log" SYSTEMD_DIR="$BATS_TEST_TMPDIR/systemd" REMOVED_DIR="$BATS_TEST_TMPDIR/removed"
    mkdir -p "$NGINX_DIR/sites-available" "$NGINX_DIR/sites-enabled" "$WWW_DIR" "$DATA_DIR" "$CRON_DIR" "$BATS_TEST_TMPDIR/php" "$SYSTEMD_DIR"
    # sudo falso: ejecuta de verdad en las carpetas temporales y se salta lo que apunte a /var o /etc reales
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"; case "$1" in systemctl|nginx|chown|fail2ban-client) exit 0;; esac; for a in "$@"; do case "$a" in /var/*|/etc/*) exit 0;; esac; done; exec "$@"'
    P=marcalibros-plataforma-pos-brand-identity
}

instalar() { run bash "$VPS_DIR/06_E-setup-marcalibros-app.sh" "$@"; }

@test "06_E exige el dominio y rechaza uno invalido o una opcion desconocida, sin tocar nada" {
    instalar; [ "$status" -eq 2 ]; [[ "$output" == *"Uso:"* ]]
    instalar 'x.com;id'; [ "$status" -eq 2 ]
    instalar ejemplo.com --nope; [ "$status" -eq 2 ]
    MAX_UPLOAD='6M; return 200' instalar ejemplo.com; [ "$status" -eq 2 ]
    MARCALIBROS_RETENTION_DAYS='30; x' instalar ejemplo.com; [ "$status" -eq 2 ]
    [ ! -e "$WWW_DIR/marcalibros" ] && [ ! -s "$SUDO_LOG" ]
}

@test "06_E copia solo lo que la app necesita, con la disposicion del monorepo" {
    instalar ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    A="$WWW_DIR/marcalibros"
    [ -f "$A/$P/app/public/index.php" ] && [ -f "$A/$P/app/bootstrap.php" ] && [ -f "$A/$P/app/bin/limpiar.php" ]
    [ -d "$A/$P/php/src" ] && [ -f "$A/$P/catalog/manifest.json" ] && [ -d "$A/$P/images" ] && [ -d "$A/$P/svg" ]
    [ -d "$A/defamatory-content-review/src" ] && [ -d "$A/defamatory-content-review/config" ]
    [ ! -e "$A/$P/app/tests" ] && [ ! -e "$A/$P/app/vendor" ] && [ ! -e "$A/$P/app/e2e" ] && [ ! -e "$A/$P/generator" ]
    [ ! -e "$A.nuevo" ] && [ ! -e "$A.anterior" ]
}

@test "06_E: Nginx sirve public/ y manda todo a index.php con el almacenamiento fuera de la web" {
    instalar ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    v="$NGINX_DIR/sites-available/ejemplo.com"
    [ "$(head -1 "$v")" = "# vps-setup: 06_E-marcalibros marcalibros" ]
    grep -q "root $WWW_DIR/marcalibros/$P/app/public;" "$v"
    grep -q 'client_max_body_size 6M;' "$v"
    grep -q "fastcgi_param MARCALIBROS_STORAGE $DATA_DIR/marcalibros;" "$v"
    grep -q 'fastcgi_param MARCALIBROS_PUBLIC_URL https://ejemplo.com;' "$v"
    grep -q 'fastcgi_pass unix:/run/php/php8.3-fpm.sock;' "$v"
    ! grep -q RETENTION "$v"
    [ -L "$NGINX_DIR/sites-enabled/ejemplo.com" ]
    [ -d "$DATA_DIR/marcalibros" ] && [ "$(stat -c %a "$DATA_DIR/marcalibros")" = 750 ]
    grep -qx 'upload_max_filesize = 6M' "$PHP_UPLOAD_INI"
}

@test "06_E deja la limpieza diaria en cron.d, con los dias de conservacion si se dan" {
    MARCALIBROS_RETENTION_DAYS=7 instalar ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    c="$CRON_DIR/marcalibros"
    grep -qx "MARCALIBROS_STORAGE=$DATA_DIR/marcalibros" "$c"
    grep -qx 'MARCALIBROS_RETENTION_DAYS=7' "$c"
    grep -q "^17 3 \* \* \* www-data /usr/bin/php $WWW_DIR/marcalibros/$P/app/bin/limpiar.php$" "$c"
    grep -q 'fastcgi_param MARCALIBROS_RETENTION_DAYS 7;' "$NGINX_DIR/sites-available/ejemplo.com"
}

@test "06_E al re-correrlo actualiza el codigo y conserva los PNG y los marcalibros creados" {
    instalar ejemplo.com
    mkdir -p "$WWW_DIR/marcalibros/$P/catalog/png"; echo png > "$WWW_DIR/marcalibros/$P/catalog/png/mb-leyes-01.png"
    echo viejo > "$WWW_DIR/marcalibros/$P/app/sobrante.txt"
    echo creado > "$DATA_DIR/marcalibros/uno.json"
    instalar ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ "$(cat "$WWW_DIR/marcalibros/$P/catalog/png/mb-leyes-01.png")" = png ]
    [ ! -e "$WWW_DIR/marcalibros/$P/app/sobrante.txt" ]
    [ "$(cat "$DATA_DIR/marcalibros/uno.json")" = creado ]
}

@test "06_E --png genera los PNG en la copia instalada" {
    make_stub python3 'if [ "$1" = -c ]; then exit 0; fi; while [ $# -gt 0 ]; do [ "$1" = --salida ] && echo ok > "$2/mb-x-01.png"; shift; done'
    instalar ejemplo.com --png
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ -f "$WWW_DIR/marcalibros/$P/catalog/png/mb-x-01.png" ]
}

@test "06_E --png sin Pillow/numpy falla antes de copiar nada" {
    make_stub python3 'exit 1'
    instalar ejemplo.com --png
    [ "$status" -eq 1 ] && [[ "$output" == *"Pillow"* ]]
    [ ! -e "$WWW_DIR/marcalibros" ]
}

@test "06_E falla con un mensaje claro si falta una extension de PHP o la carpeta del repo es otra" {
    NOWEBP=1 instalar ejemplo.com
    [ "$status" -eq 1 ] && [[ "$output" == *"WebP"* ]]
    make_stub php 'case "$1" in -m) printf "mbstring\n";; esac'
    instalar ejemplo.com
    [ "$status" -eq 1 ] && [[ "$output" == *"gd"* ]]
    make_stub php 'case "$1" in -m) printf "gd\nmbstring\nfileinfo\nexif\nzlib\n";; -r) exit 0;; esac'
    instalar ejemplo.com --repo "$BATS_TEST_TMPDIR"
    [ "$status" -eq 1 ] && [[ "$output" == *"falta"* ]]
    [ ! -e "$WWW_DIR/marcalibros" ]
}

@test "06_E no pisa un dominio ajeno ni instala una segunda copia para otro dominio" {
    printf '# vps-setup: 03-landing\nserver { server_name ejemplo.com; }\n' > "$NGINX_DIR/sites-available/ejemplo.com"
    instalar ejemplo.com
    [ "$status" -eq 1 ] && [[ "$output" == *"ya tiene un sitio"* ]]
    rm "$NGINX_DIR/sites-available/ejemplo.com"
    instalar uno.com; [ "$status" -eq 0 ]
    instalar otro.com
    [ "$status" -eq 1 ] && [[ "$output" == *"uno.com"* ]]
    [ ! -e "$NGINX_DIR/sites-available/otro.com" ]
}

@test "10_A --type marcalibros llama a 06_E; 10_B lo detecta, archiva la app y el cron, y avisa de los datos" {
    run bash "$VPS_DIR/10_A-add-domain.sh" ejemplo.com --type marcalibros --dry-run
    [ "$status" -eq 0 ]
    [[ "$output" == *"+ ./06_E-setup-marcalibros-app.sh ejemplo.com"* ]] && [[ "$output" == *"--app marcalibros"* ]]
    instalar ejemplo.com
    [ "$status" -eq 0 ]
    run bash "$VPS_DIR/10_B-remove-domain.sh" ejemplo.com --yes
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [[ "$output" == *"App detectada en el vhost: marcalibros"* ]] && [[ "$output" == *"/var/lib/marcalibros"* ]]
    d=$(ls -d "$REMOVED_DIR"/ejemplo.com-*)
    [ -d "$d/www/marcalibros" ] && [ -f "$d/cron/marcalibros" ] && [ ! -e "$CRON_DIR/marcalibros" ]
    [ -d "$DATA_DIR/marcalibros" ]
}
