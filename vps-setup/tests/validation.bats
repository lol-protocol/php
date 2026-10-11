#!/usr/bin/env bats
load helpers

setup() { setup_stubs; setup_system_stubs; }

v() { bash -c "source '$VPS_DIR/lib.sh'; validate_$1 \"\$2\"" _ "$1" "$2"; }

@test "validate_domain acepta dominios normales" {
    for d in ejemplo.com www.ejemplo.com a-b.c1.co.uk initech.fun xn--bcher-kva.example sub.dominio-largo.cl; do
        run v domain "$d"; [ "$status" -eq 0 ] || { echo "deberia aceptar: $d"; return 1; }
    done
}

@test "validate_domain rechaza entradas peligrosas o mal formadas" {
    for d in "" "ejemplo" "Ejemplo.com" "a b.com" "a.com;rm -rf /" 'a.com$(id)' 'a.com`id`' "a.com|x" "a.com&b" \
             "a.com/../x" "../x.com" "-a.com" "a-.com" "a..com" ".a.com" "a.com." "a.c" "a.123" "a.com'x" 'a.com"x' \
             "$(printf 'a.com\nserver {')" "$(printf 'a.com\r')" "$(printf 'a.com\t')" "$(printf 'a%.0s' {1..64}).com"; do
        run v domain "$d"; [ "$status" -ne 0 ] || { echo "deberia rechazar: [$d]"; return 1; }
    done
}

@test "validate_email acepta y rechaza" {
    for e in a@ejemplo.com nombre.apellido+tag@sub.ejemplo.com ADMIN@Ejemplo.COM; do
        run v email "$e"; [ "$status" -eq 0 ] || { echo "deberia aceptar: $e"; return 1; }
    done
    for e in "" "a" "@ejemplo.com" "a@" "a@ejemplo" "a b@ejemplo.com" 'a@ejemplo.com;id' 'a$(id)@ejemplo.com' "a@@ejemplo.com" \
             "$(printf 'a@ejemplo.com\nBcc: x')"; do
        run v email "$e"; [ "$status" -ne 0 ] || { echo "deberia rechazar: [$e]"; return 1; }
    done
}

@test "validate_ipv4" {
    for i in 1.2.3.4 203.0.113.5 255.255.255.255 0.0.0.0; do run v ipv4 "$i"; [ "$status" -eq 0 ]; done
    for i in "" 256.1.1.1 1.2.3 1.2.3.4.5 01.2.3.4 a.b.c.d "1.2.3.4;id" "1.2.3.4 " "::1"; do
        run v ipv4 "$i"; [ "$status" -ne 0 ] || { echo "deberia rechazar: [$i]"; return 1; }
    done
}

@test "validate_name (apps y servicios)" {
    for n in app php-app mi_app2 a; do run v name "$n"; [ "$status" -eq 0 ]; done
    for n in "" "-app" "_app" "App" "a b" "a/b" "../x" "a;b" 'a$b' "$(printf 'a%.0s' {1..64})"; do
        run v name "$n"; [ "$status" -ne 0 ] || { echo "deberia rechazar: [$n]"; return 1; }
    done
}

@test "validate_context_path: vacio vale; '/', '..' y espacios no" {
    for c in "" miapp app-1 v1.2 ROOT; do run v context_path "$c"; [ "$status" -eq 0 ]; done
    for c in "/app" "a/b" ".." "a..b" "." "a b" 'a;b' 'a$(id)'; do
        run v context_path "$c"; [ "$status" -ne 0 ] || { echo "deberia rechazar: [$c]"; return 1; }
    done
}

@test "validate_webhook_url: sin caracteres que rompan un archivo que se carga con 'source'" {
    for u in "https://hooks.slack.com/services/T000/B000/XXXX" "https://discord.com/api/webhooks/123/abc_DEF-ghi" "http://localhost:8080/x?y=1&z=2"; do
        run v webhook_url "$u"; [ "$status" -eq 0 ] || { echo "deberia aceptar: $u"; return 1; }
    done
    for u in "" "ftp://x.com" "javascript:alert(1)" 'https://x.com/"; touch /tmp/pwn; "' 'https://x.com/$(id)' 'https://x.com/`id`' "https://x.com/a b" 'https://x.com/\n' "$(printf 'https://x.com/\nrm')" "https://x.com/'x"; do
        run v webhook_url "$u"; [ "$status" -ne 0 ] || { echo "deberia rechazar: [$u]"; return 1; }
    done
}

@test "require_valid sale con 2, explica y muestra el valor escapado" {
    run bash -c "source '$VPS_DIR/lib.sh'; require_valid domain \$'a.com\nserver {' 'El dominio'"
    [ "$status" -eq 2 ]
    [[ "$output" == *"El dominio invalido"* ]]
    [[ "$output" == *'$'"'"'a.com\nserver {'"'"* ]]   # %q: el salto de linea NO sale crudo
}

# --- los scripts validan ANTES de tocar el sistema ---
rechaza() {  # <script> <args...>  -> exit 2, mensaje de error y NINGUNA llamada a sudo
    run bash "$VPS_DIR/$1" "${@:2}"
    [ "$status" -eq 2 ] || { echo "$1 ${*:2}: status=$status"; return 1; }
    [[ "$output" == *"ERROR"* ]]
    [ ! -s "$SUDO_LOG" ] || { echo "$1 llamo a sudo:"; cat "$SUDO_LOG"; return 1; }
}

@test "03/04/05 rechazan un dominio con inyeccion sin llamar a sudo" {
    rechaza 03-configure-nginx-site.sh 'a.com;id'
    rechaza 04-setup-ssl.sh '$(id).com' a@ejemplo.com
    rechaza 05-deploy-landing-page.sh "a b.com"
}

@test "04 rechaza un email invalido" {
    rechaza 04-setup-ssl.sh ejemplo.com 'a@ejemplo.com;id'
}

@test "06_A/06_B rechazan nombre de app y dominio invalidos" {
    rechaza 06_A-setup-php-app.sh '../etc' ejemplo.com
    rechaza 06_A-setup-php-app.sh app 'x.com;id'
    rechaza 06_B-setup-python-app.sh 'App Name' ejemplo.com
    rechaza 06_B-setup-python-app.sh app 'x y.com'
}

@test "06_C rechaza IP y secundario invalidos" {
    rechaza 06_C-setup-dns-server.sh ejemplo.com '999.1.1.1'
    rechaza 06_C-setup-dns-server.sh ejemplo.com 1.2.3.4 'ns;id.com'
}

@test "06_D rechaza context path con '/' o '..'" {
    rechaza 06_D-setup-tomcat-app.sh ejemplo.com '../x'
    rechaza 06_D-setup-tomcat-app.sh ejemplo.com 'a/b'
}

@test "08 rechaza un dominio invalido" {
    rechaza 08-healthcheck.sh 'a.com;id'
}

@test "09_A rechaza webhook y email que romperian el archivo de configuracion" {
    export MONITOR_BIN="$BATS_TEST_TMPDIR/m" MONITOR_CONF="$BATS_TEST_TMPDIR/m.conf" MONITOR_CRON="$BATS_TEST_TMPDIR/c" MONITOR_LOGROTATE="$BATS_TEST_TMPDIR/l"
    rechaza 09_A-setup-monitoring.sh 'https://x.com/"; touch /tmp/pwn; "'
    rechaza 09_A-setup-monitoring.sh https://x.com/ok 'a@x.com;id'
    [ ! -e "$MONITOR_CONF" ]
}

@test "install-all rechaza dominio/email invalidos, tambien en --dry-run" {
    run bash "$VPS_DIR/install-all.sh" 'a.com;id' --dry-run
    [ "$status" -eq 2 ]
    run bash "$VPS_DIR/install-all.sh" ejemplo.com 'no-es-email' --dry-run
    [ "$status" -eq 2 ]
}

@test "remote-run rechaza un host que se leeria como opcion de ssh (-oProxyCommand)" {
    VPS_ENV=/no/existe VPS_HOST='-oProxyCommand=id' run bash "$VPS_DIR/remote-run.sh" --dry-run install-all.sh
    [ "$status" -eq 2 ]
    [[ "$output" == *"VPS_HOST invalido"* ]]
}

@test "remote-run valida usuario, puerto, directorio remoto y nombre de script" {
    export VPS_ENV=/no/existe VPS_HOST=1.2.3.4
    VPS_USER='a;id' run bash "$VPS_DIR/remote-run.sh" --dry-run install-all.sh; [ "$status" -eq 2 ]
    VPS_PORT='22;id' run bash "$VPS_DIR/remote-run.sh" --dry-run install-all.sh; [ "$status" -eq 2 ]
    REMOTE_DIR='x;id' run bash "$VPS_DIR/remote-run.sh" --dry-run install-all.sh; [ "$status" -eq 2 ]
    run bash "$VPS_DIR/remote-run.sh" --dry-run '../x.sh'; [ "$status" -eq 2 ]
}
