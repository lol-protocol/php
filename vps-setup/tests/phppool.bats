#!/usr/bin/env bats
load helpers

# El pool "www" por defecto de PHP-FPM pasa a ondemand con pocos procesos (02_B).
setup() {
    setup_stubs; setup_system_stubs
    export PHP_POOL_DIR="$BATS_TEST_TMPDIR/pool.d"; mkdir -p "$PHP_POOL_DIR"
    make_stub php 'echo "PHP stub"'
    # sudo que ejecuta de verdad solo sobre las rutas de prueba (nada de /etc ni /var reales)
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"; case "$1" in systemctl|apt-get) exit 0;; esac; for a in "$@"; do case "$a" in /var/*|/etc/*) exit 0;; esac; done; exec "$@"'
    # www.conf como lo deja Ubuntu 24.04 (solo las lineas que importan)
    cat > "$PHP_POOL_DIR/www.conf" <<'EOC'
[www]
user = www-data
group = www-data
listen = /run/php/php8.3-fpm.sock
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
;pm.process_idle_timeout = 10s;
EOC
}

@test "02_B deja el pool www en ondemand con 2 procesos y 10s de espera, sin tocar usuario ni socket" {
    run bash "$VPS_DIR/02_B-install-php.sh"
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    f="$PHP_POOL_DIR/www.conf"
    grep -qx 'pm = ondemand' "$f" && grep -qx 'pm.max_children = 2' "$f" && grep -qx 'pm.process_idle_timeout = 10s' "$f"
    grep -qx 'user = www-data' "$f" && grep -qx 'listen = /run/php/php8.3-fpm.sock' "$f"
    [ "$(grep -c '^pm = ' "$f")" -eq 1 ] && [ "$(grep -c 'process_idle_timeout' "$f")" -eq 1 ]
}

@test "02_B reinicia PHP-FPM para aplicar el ajuste, y re-correrlo no cambia nada mas" {
    bash "$VPS_DIR/02_B-install-php.sh" > /dev/null
    cp "$PHP_POOL_DIR/www.conf" "$BATS_TEST_TMPDIR/antes"
    : > "$SUDO_LOG"
    run bash "$VPS_DIR/02_B-install-php.sh"
    [ "$status" -eq 0 ]
    cmp "$PHP_POOL_DIR/www.conf" "$BATS_TEST_TMPDIR/antes"
    grep -q 'systemctl restart php8.3-fpm' "$SUDO_LOG"
}

@test "PHP_WWW_MAX_CHILDREN fija el tope, y un valor invalido se rechaza antes de tocar nada" {
    PHP_WWW_MAX_CHILDREN=4 run bash "$VPS_DIR/02_B-install-php.sh"
    [ "$status" -eq 0 ]
    grep -qx 'pm.max_children = 4' "$PHP_POOL_DIR/www.conf"
    : > "$SUDO_LOG"; cp "$PHP_POOL_DIR/www.conf" "$BATS_TEST_TMPDIR/antes"
    PHP_WWW_MAX_CHILDREN='2; rm' run bash "$VPS_DIR/02_B-install-php.sh"
    [ "$status" -eq 2 ]
    cmp "$PHP_POOL_DIR/www.conf" "$BATS_TEST_TMPDIR/antes"
}

@test "set_fpm_option agrega la linea si no existe y sin www.conf solo avisa" {
    printf '[www]\nuser = x\n' > "$PHP_POOL_DIR/www.conf"
    run bash "$VPS_DIR/02_B-install-php.sh"
    [ "$status" -eq 0 ]
    grep -qx 'pm = ondemand' "$PHP_POOL_DIR/www.conf" && grep -qx 'pm.max_children = 2' "$PHP_POOL_DIR/www.conf"
    rm "$PHP_POOL_DIR/www.conf"
    run bash "$VPS_DIR/02_B-install-php.sh"
    [ "$status" -eq 0 ] && [[ "$output" == *"no se ajusta el pool por defecto"* ]]
}
