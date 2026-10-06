#!/usr/bin/env bats
load helpers

setup() { setup_stubs; setup_system_stubs; }

run_lib() { run bash -c "source '$VPS_DIR/lib.sh'; $1"; }

@test "print_header imprime el paso y el nombre" {
    run_lib 'print_header 02_X "Algo"'
    [ "$status" -eq 0 ]
    [[ "$output" == *"[02_X] Algo"* ]]
}

@test "check_dependency pasa si el comando existe" {
    run_lib 'check_dependency bash "./nada.sh"'
    [ "$status" -eq 0 ]
}

@test "check_dependency falla y sugiere el script previo si falta" {
    run_lib 'check_dependency comando-inexistente-xyz "./02_A-install-java.sh"'
    [ "$status" -eq 1 ]
    [[ "$output" == *"no esta instalado"* ]]
    [[ "$output" == *"./02_A-install-java.sh"* ]]
}

@test "ufw_allow no agrega la regla si UFW esta inactivo" {
    UFW_STATUS=inactive run_lib 'ufw_allow "10000/tcp"'
    [ "$status" -eq 0 ]
    [[ "$output" == *"not active"* ]]
    ! grep -q "ufw allow" "$SUDO_LOG"
}

@test "ufw_allow agrega la regla si UFW esta activo" {
    run_lib 'ufw_allow "10000/tcp"'
    [ "$status" -eq 0 ]
    grep -q "ufw allow 10000/tcp" "$SUDO_LOG"
}

@test "service_start_enable arranca y habilita" {
    run_lib 'service_start_enable nginx'
    grep -q "systemctl start nginx" "$SUDO_LOG"
    grep -q "systemctl enable nginx" "$SUDO_LOG"
}

@test "setup_nginx_domain_logs crea la carpeta y NO hace chown (CVE-2016-1247)" {
    run_lib 'setup_nginx_domain_logs ejemplo.com'
    grep -q "mkdir -p /var/log/nginx/ejemplo.com" "$SUDO_LOG"
    ! grep -q "chown" "$SUDO_LOG"
}

@test "setup_app_directories da la app a www-data pero no los logs" {
    run_lib 'setup_app_directories /var/www/app ejemplo.com'
    grep -q "chown -R www-data:www-data /var/www/app" "$SUDO_LOG"
    ! grep -q "chown.*/var/log/nginx" "$SUDO_LOG"
}

@test "get_public_ip devuelve la IP, la cachea y la exporta a procesos hijos" {
    run bash -c "source '$VPS_DIR/lib.sh'
        get_public_ip > '$BATS_TEST_TMPDIR/a'; get_public_ip > '$BATS_TEST_TMPDIR/b'
        cat '$BATS_TEST_TMPDIR/a' '$BATS_TEST_TMPDIR/b'
        bash -c 'echo hijo=\$CACHED_PUBLIC_IP'"
    [ "$status" -eq 0 ]
    [[ "$output" == *"203.0.113.9"* ]]
    [[ "$output" == *"hijo=203.0.113.9"* ]]
    [ "$(wc -l < "$BATS_TEST_TMPDIR/ifconfig.calls")" -eq 1 ]
}

@test "get_public_ip usa el placeholder si curl falla" {
    make_stub curl 'exit 7'
    run_lib 'get_public_ip'
    [ "$output" = "TU_IP_PUBLICA" ]
}

@test "verify_dns_resolution acepta la IP esperada" {
    run_lib 'verify_dns_resolution ejemplo.com 203.0.113.9'
    [ "$status" -eq 0 ]
}

@test "verify_dns_resolution rechaza otra IP" {
    DIGIP=1.2.3.4 run_lib 'verify_dns_resolution ejemplo.com 203.0.113.9'
    [ "$status" -eq 1 ]
    [[ "$output" == *"expected 203.0.113.9"* ]]
}

@test "verify_dns_resolution avisa si no resuelve" {
    make_stub dig 'exit 0'
    run_lib 'verify_dns_resolution ejemplo.com 203.0.113.9'
    [ "$status" -eq 1 ]
    [[ "$output" == *"does not resolve"* ]]
}

@test "deploy_files copia el CONTENIDO (sin anidar la carpeta origen) y fija permisos" {
    src="$BATS_TEST_TMPDIR/landing-page"; dst="$BATS_TEST_TMPDIR/dest"
    mkdir -p "$src/sub" "$dst"; echo hola > "$src/index.html"; echo x > "$src/sub/f.txt"
    SUDO_EXEC=1 run_lib "deploy_files '$src' '$dst'"
    [ "$status" -eq 0 ]
    [ -f "$dst/index.html" ]
    [ ! -e "$dst/landing-page" ]
    [ "$(stat -c %a "$dst/index.html")" = "644" ]
    [ "$(stat -c %a "$dst/sub")" = "755" ]
}

@test "deploy_files falla si el origen no existe" {
    run_lib "deploy_files '$BATS_TEST_TMPDIR/no-existe' '$BATS_TEST_TMPDIR'"
    [ "$status" -eq 1 ]
}

@test "verify_dns_resolution exige que AMBOS (dominio y www) apunten a la IP: certbot pide los dos" {
    # apex correcto, www apuntando a otra IP
    make_stub dig 'if [[ "$*" == *www.* ]]; then echo 9.9.9.9; else echo 203.0.113.9; fi'
    run_lib 'verify_dns_resolution ejemplo.com 203.0.113.9'
    [ "$status" -eq 1 ]
    [[ "$output" == *"www.ejemplo.com"* ]]
}

@test "verify_dns_resolution falla si www no resuelve aunque el apex si" {
    make_stub dig 'if [[ "$*" == *www.* ]]; then exit 0; else echo 203.0.113.9; fi'
    run_lib 'verify_dns_resolution ejemplo.com 203.0.113.9'
    [ "$status" -eq 1 ]
}

@test "verify_dns_resolution acepta un CNAME seguido de la IP correcta" {
    make_stub dig 'printf "lb.ejemplo.net.\n203.0.113.9\n"'
    run_lib 'verify_dns_resolution ejemplo.com 203.0.113.9'
    [ "$status" -eq 0 ]
}

@test "get_public_ip NO cachea el placeholder: un fallo transitorio no envenena el resto de la corrida" {
    make_stub curl 'exit 7'
    run bash -c "source '$VPS_DIR/lib.sh'
        get_public_ip > '$BATS_TEST_TMPDIR/a'
        echo \"cache=[\$CACHED_PUBLIC_IP]\""
    [[ "$output" == *"cache=[]"* ]]
    make_stub curl 'echo 203.0.113.9'
    run bash -c "source '$VPS_DIR/lib.sh'; get_public_ip; get_public_ip > /dev/null; echo \"cache=[\$CACHED_PUBLIC_IP]\""
    [[ "$output" == *"cache=[203.0.113.9]"* ]]
}
