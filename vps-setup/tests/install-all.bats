#!/usr/bin/env bats
load helpers

# Copia install-all.sh y lib.sh a un directorio temporal junto con scripts "falsos"
# para cada paso: asi se prueba el orquestador sin instalar nada.
setup() {
    setup_stubs; setup_system_stubs
    FAKE="$BATS_TEST_TMPDIR/fake"; mkdir -p "$FAKE"
    cp "$VPS_DIR/install-all.sh" "$VPS_DIR/lib.sh" "$FAKE/"
    export RUN_LOG="$BATS_TEST_TMPDIR/run.log"; : > "$RUN_LOG"
    export INSTALL_STATE_FILE="$BATS_TEST_TMPDIR/state"
    export HOME="$BATS_TEST_TMPDIR"
    for s in $(grep -oE '"[0-9A-Z_]+-[a-z0-9-]+\.sh' "$VPS_DIR/install-all.sh" | tr -d '"') 08-healthcheck.sh; do
        printf '#!/bin/bash\necho "$0 $*" >> "$RUN_LOG"\n[ -n "$FAIL_STEP" ] && [[ "$0" == *"$FAIL_STEP" ]] && exit 1\nexit 0\n' > "$FAKE/$s"
    done
    # 02_J registra la IP que heredo del orquestador
    printf '#!/bin/bash\necho "$0" >> "$RUN_LOG"\necho "$CACHED_PUBLIC_IP" > "$BATS_TEST_TMPDIR/ip_en_hijo"\n' > "$FAKE/02_J-install-webmin.sh"
    chmod +x "$FAKE"/*.sh
}

ia() { run bash "$FAKE/install-all.sh" "$@"; }
steps_run() { sed 's|.*/||; s| .*||' "$RUN_LOG"; }

@test "corrida completa: 01 primero, todos los pasos en orden, healthcheck al final" {
    ia ejemplo.com a@ejemplo.com --yes
    [ "$status" -eq 0 ]
    [ "$(steps_run | head -1)" = "01-system-update.sh" ]
    [ "$(steps_run | tail -1)" = "08-healthcheck.sh" ]
    [ "$(steps_run | wc -l)" -eq 15 ]
    [[ "$output" == *"SETUP COMPLETADO"* ]]
}

@test "pasa dominio y email a 03/04/05" {
    ia ejemplo.com a@ejemplo.com --yes
    grep -q "03-configure-nginx-site.sh ejemplo.com$" "$RUN_LOG"
    grep -q "04-setup-ssl.sh ejemplo.com a@ejemplo.com$" "$RUN_LOG"
    grep -q "05-deploy-landing-page.sh ejemplo.com$" "$RUN_LOG"
}

@test "la IP se consulta UNA vez y los hijos la heredan; el resumen la usa" {
    ia ejemplo.com a@ejemplo.com --yes
    [ "$(cat "$BATS_TEST_TMPDIR/ip_en_hijo")" = "203.0.113.9" ]
    [ "$(wc -l < "$BATS_TEST_TMPDIR/ifconfig.calls")" -eq 1 ]
    [[ "$output" == *"https://203.0.113.9:10000"* ]]
}

@test "--dry-run no ejecuta nada y no guarda estado" {
    ia ejemplo.com --dry-run
    [ "$status" -eq 0 ]
    [ ! -s "$RUN_LOG" ]
    [ ! -e "$INSTALL_STATE_FILE" ]
    [[ "$output" == *"[dry-run] bash ./04-setup-ssl.sh ejemplo.com admin@ejemplo.com"* ]]
}

@test "el contador [N/TOTAL] queda alineado para pasos de 1 y 2 cifras" {
    ia ejemplo.com --dry-run
    [[ "$output" == *"║ [1/14] 01-system-update.sh"* ]]
    [[ "$output" == *"║ [10/14] 04-setup-ssl.sh"* ]]
    ancho=$(echo "$output" | grep -E '^║ \[(1|10)/14\]' | python3 -c "import sys; print({len(l.rstrip('\n')) for l in sys.stdin})")
    [ "$ancho" = "{62}" ]
}

@test "si un paso falla: aborta, no corre los siguientes e indica como retomar" {
    FAIL_STEP=02_E-install-nginx.sh ia ejemplo.com a@ejemplo.com --yes
    [ "$status" -eq 1 ]
    [[ "$output" == *"ERROR: fallo 02_E-install-nginx.sh"* ]]
    [[ "$output" == *"--resume"* ]]
    ! steps_run | grep -q "02_F-install-certbot.sh"
    ! steps_run | grep -q "08-healthcheck.sh"
    grep -q "02_D-install-postgresql.sh" "$INSTALL_STATE_FILE"
    ! grep -q "02_E-install-nginx.sh" "$INSTALL_STATE_FILE"
}

@test "--resume retoma desde el paso que fallo sin repetir los anteriores" {
    FAIL_STEP=02_E-install-nginx.sh ia ejemplo.com a@ejemplo.com --yes || true
    : > "$RUN_LOG"
    ia ejemplo.com a@ejemplo.com --yes --resume
    [ "$status" -eq 0 ]
    ! steps_run | grep -q "01-system-update.sh"
    ! steps_run | grep -q "02_D-install-postgresql.sh"
    steps_run | grep -q "02_E-install-nginx.sh"
    steps_run | grep -q "07_D-setup-logrotate.sh"
}

@test "sin --resume una corrida nueva empieza de cero" {
    ia ejemplo.com a@ejemplo.com --yes
    : > "$RUN_LOG"
    ia ejemplo.com a@ejemplo.com --yes
    steps_run | grep -q "01-system-update.sh"
    [ "$(steps_run | wc -l)" -eq 15 ]
}

@test "el estado de un dominio no afecta a otro" {
    ia uno.com --yes
    : > "$RUN_LOG"
    ia dos.com --yes --resume
    steps_run | grep -q "01-system-update.sh"
}

@test "opcion desconocida: exit 2" {
    ia --nada
    [ "$status" -eq 2 ]
}

@test "reiniciar un dominio NO borra el estado de otro cuyo nombre lo contiene (initech.fun vs www.initech.fun)" {
    ia www.initech.fun --yes
    ia initech.fun --yes
    [ "$(grep -c '^www.initech.fun'$'\t' "$INSTALL_STATE_FILE")" -eq 14 ]
}
