#!/usr/bin/env bats
load helpers

setup() {
    setup_stubs; setup_system_stubs
    export VPS_MONITOR_CONF="$BATS_TEST_TMPDIR/monitor.conf"
    export VPS_MONITOR_STATE="$BATS_TEST_TMPDIR/state"
    export CERT_DIR="$BATS_TEST_TMPDIR/live"; mkdir -p "$CERT_DIR"
    export LOADAVG_FILE="$BATS_TEST_TMPDIR/loadavg"; echo "0.10 0.10 0.10 1/100 1" > "$LOADAVG_FILE"
    export CALLS="$BATS_TEST_TMPDIR/webhook.calls"; : > "$CALLS"
    export MAILS="$BATS_TEST_TMPDIR/mail.calls"; : > "$MAILS"
    printf 'WEBHOOK_URL="http://hook.test/x"\nMOUNTS="/"\nSERVICES="nginx"\n' > "$VPS_MONITOR_CONF"
    # Sano por defecto; cada test sobreescribe lo que quiera romper.
    make_stub df 'printf "Filesystem 1K Used Avail Capacity Mounted\n/dev/x 100 %s 10 %s%% /\n" "${DISK:-40}" "${DISK:-40}"'
    make_stub free 'printf "              total used free shared buff/cache available\nMem: 1000 %s 0 0 0 %s\n" "${MEM_USED:-300}" "$((1000 - ${MEM_USED:-300}))"'
    make_stub nproc 'echo 2'
    make_stub logger 'exit 0'
    make_stub mail 'echo "$*" >> "$MAILS"; cat >> "$MAILS"'
    # curl del monitor: registra el cuerpo enviado al webhook
    make_stub curl 'echo "$*" >> "$CALLS"'
    MON="$VPS_DIR/monitoring/vps-monitor.sh"
}

mon() { run bash "$MON" "$@"; }
alerts() { wc -l < "$CALLS" | tr -d ' '; }

@test "sin problemas: OK y no envia nada" {
    mon
    [ "$status" -eq 0 ]
    [[ "$output" == *"OK: sin problemas"* ]]
    [ "$(alerts)" = "0" ]
}

@test "disco sobre el umbral: lo reporta y envia una alerta al webhook" {
    DISK=92 mon
    [[ "$output" == *"Disco / al 92%"* ]]
    [ "$(alerts)" = "1" ]
}

@test "RAM alta (cuenta memoria disponible, no la usada por cache)" {
    MEM_USED=950 mon --dry-run
    [[ "$output" == *"RAM al 95%"* ]]
}

@test "carga alta respecto a los nucleos" {
    echo "9.50 5.00 3.00 1/100 1" > "$LOADAVG_FILE"
    mon --dry-run
    [[ "$output" == *"Carga alta: 9.50 con 2 nucleo"* ]]
}

@test "certificado por vencer y vencido" {
    mkdir -p "$CERT_DIR/a.com" "$CERT_DIR/b.com"; touch "$CERT_DIR/a.com/cert.pem" "$CERT_DIR/b.com/cert.pem"
    CERT_DAYS=5 mon --dry-run
    [[ "$output" == *"a.com vence en 4 dia"* || "$output" == *"a.com vence en 5 dia"* ]]
    CERT_DAYS=-2 mon --dry-run
    [[ "$output" == *"VENCIDO"* ]]
}

@test "servicio caido y unidades systemd fallidas" {
    FAILED_UNITS="foo.service loaded failed" DOWN="nginx" mon --dry-run
    [[ "$output" == *"Servicio nginx NO esta activo"* ]]
    [[ "$output" == *"Unidades systemd fallidas: foo.service"* ]]
}

@test "--dry-run no envia ni guarda estado" {
    DISK=95 mon --dry-run
    [ "$(alerts)" = "0" ]
    [ ! -e "$VPS_MONITOR_STATE" ]
}

@test "el mismo problema NO vuelve a alertar en la corrida siguiente" {
    DISK=92 mon
    DISK=92 mon
    [ "$(alerts)" = "1" ]
}

@test "un problema nuevo SI alerta" {
    DISK=92 mon
    DISK=92 MEM_USED=950 mon
    [ "$(alerts)" = "2" ]
}

@test "al recuperarse avisa una vez y luego calla" {
    DISK=92 mon
    DISK=40 mon
    DISK=40 mon
    [ "$(alerts)" = "2" ]
    grep -q "Recuperado" "$CALLS"
}

@test "recordatorio: con REMIND_SECS=0 repite el aviso de un problema vigente" {
    echo 'REMIND_SECS=0' >> "$VPS_MONITOR_CONF"
    DISK=92 mon
    DISK=92 mon
    [ "$(alerts)" = "2" ]
}

@test "el payload JSON del webhook trae text y content, con comillas escapadas" {
    mkdir -p "$CERT_DIR/a\"b.com"; touch "$CERT_DIR/a\"b.com/cert.pem"
    CERT_DAYS=1 mon
    grep -q '"text":"' "$CALLS"
    grep -q '"content":"' "$CALLS"
    grep -q 'a\\"b.com' "$CALLS"
}

@test "email: usa 'mail' si hay ALERT_EMAIL" {
    echo 'ALERT_EMAIL="yo@ejemplo.com"' >> "$VPS_MONITOR_CONF"
    DISK=92 mon
    grep -q "yo@ejemplo.com" "$MAILS"
}

@test "--test envia una alerta de prueba" {
    mon --test
    [ "$status" -eq 0 ]
    [ "$(alerts)" = "1" ]
    grep -q "prueba" "$CALLS"
}

@test "opcion desconocida: exit 2" {
    mon --nada
    [ "$status" -eq 2 ]
}

@test "--test NO imprime la URL del webhook" {
    mon --test
    [[ "$output" == *"webhook: si"* ]]
    [[ "$output" != *"hook.test"* ]]
}

@test "la huella ignora los numeros: carga/disco cambiantes NO re-alertan" {
    echo "9.50 5 3 1/100 1" > "$LOADAVG_FILE"; DISK=90 mon
    echo "11.20 5 3 1/100 1" > "$LOADAVG_FILE"; DISK=93 mon
    [ "$(alerts)" = "1" ]
}

@test "certificado vencido hace MENOS de un dia se reporta VENCIDO (no 'vence en 0 dias')" {
    mkdir -p "$CERT_DIR/a.com"; touch "$CERT_DIR/a.com/cert.pem"
    CERT_WHEN="-5 hours" mon --dry-run
    [[ "$output" != *"vence en 0"* ]]
    [[ "$output" == *"VENCIDO"* ]]
}

@test "el JSON del webhook elimina tabs, CR y caracteres de control" {
    d=$(printf 'a\tb\r\001.com'); mkdir -p "$CERT_DIR/$d"; touch "$CERT_DIR/$d/cert.pem"
    CERT_DAYS=1 mon
    [ "$(alerts)" = "1" ]
    ! grep -qP '[\x00-\x08\x0b-\x1f]' "$CALLS"
    grep -q 'a b.com' "$CALLS"
}
