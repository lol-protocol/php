#!/usr/bin/env bats
load helpers

setup() {
    setup_stubs; setup_system_stubs
    export SUDO_EXEC=1
    export MONITOR_BIN="$BATS_TEST_TMPDIR/vps-monitor" MONITOR_CONF="$BATS_TEST_TMPDIR/vps-monitor.conf"
    export MONITOR_CRON="$BATS_TEST_TMPDIR/cron" MONITOR_LOGROTATE="$BATS_TEST_TMPDIR/lr"
}

# ---- 09_A: instalador del monitoreo ----
@test "09_A instala el monitor, la config (600), el cron cada 15 min y logrotate" {
    run bash "$VPS_DIR/09_A-setup-monitoring.sh" "http://hook.test/abc" "yo@ejemplo.com"
    [ "$status" -eq 0 ]
    [ -x "$MONITOR_BIN" ]
    grep -q 'WEBHOOK_URL="http://hook.test/abc"' "$MONITOR_CONF"
    grep -q 'ALERT_EMAIL="yo@ejemplo.com"' "$MONITOR_CONF"
    [ "$(stat -c %a "$MONITOR_CONF")" = "600" ]
    grep -q "^\*/15 \* \* \* \* root $MONITOR_BIN" "$MONITOR_CRON"
    grep -q "/var/log/vps-monitor.log" "$MONITOR_LOGROTATE"
}

@test "09_A no pisa una configuracion existente" {
    echo 'DISK_WARN=70' > "$MONITOR_CONF"
    run bash "$VPS_DIR/09_A-setup-monitoring.sh" "http://otra"
    [[ "$output" == *"Se conserva"* ]]
    [ "$(cat "$MONITOR_CONF")" = "DISK_WARN=70" ]
}

@test "09_A el monitor instalado funciona con esa config" {
    bash "$VPS_DIR/09_A-setup-monitoring.sh" > /dev/null
    export VPS_MONITOR_CONF="$MONITOR_CONF" VPS_MONITOR_STATE="$BATS_TEST_TMPDIR/st" CERT_DIR="$BATS_TEST_TMPDIR/nocerts"
    make_stub df 'printf "h\n/dev/x 1 1 1 10%% /\n"'
    make_stub free 'printf "h\nMem: 1000 100 0 0 0 900\n"'
    make_stub nproc 'echo 2'
    run "$MONITOR_BIN" --dry-run
    [ "$status" -eq 0 ]
    [[ "$output" == *"OK: sin problemas"* ]]
}

# ---- remote-run.sh ----
@test "remote-run --dry-run arma el comando ssh con usuario, puerto, llave y args escapados" {
    cp "$VPS_DIR/remote-run.sh" "$BATS_TEST_TMPDIR/"; mkdir -p "$BATS_TEST_TMPDIR/vps-setup"
    cp "$VPS_DIR/remote-run.sh" "$VPS_DIR/install-all.sh" "$BATS_TEST_TMPDIR/vps-setup/" 2>/dev/null || true
    printf 'VPS_HOST=203.0.113.5\nVPS_USER=deploy\nVPS_PORT=2222\nVPS_KEY=/k/id\n' > "$BATS_TEST_TMPDIR/vps-setup/vps.env"
    run bash "$BATS_TEST_TMPDIR/vps-setup/remote-run.sh" --dry-run install-all.sh ejemplo.com "a b" --yes
    [ "$status" -eq 0 ]
    [[ "$output" == *"-p 2222"* ]]
    [[ "$output" == *"-i /k/id"* ]]
    [[ "$output" == *"deploy@203.0.113.5"* ]]
    [[ "$output" == *'a\ b'* ]]
}

@test "remote-run sin VPS_HOST falla con un mensaje claro" {
    VPS_ENV=/no/existe run bash "$VPS_DIR/remote-run.sh" --dry-run install-all.sh
    [ "$status" -eq 1 ]
    [[ "$output" == *"falta VPS_HOST"* ]]
}

@test "remote-run con un script inexistente falla" {
    VPS_HOST=1.2.3.4 VPS_ENV=/no/existe run bash "$VPS_DIR/remote-run.sh" --dry-run no-existe.sh
    [ "$status" -eq 1 ]
}
