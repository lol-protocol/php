#!/usr/bin/env bats
load helpers

# 02_I: Whisper en un venv compartido. Aqui no se instala nada: sudo registra y df/MemTotal se simulan.
setup() {
    setup_stubs; setup_system_stubs
    make_stub whisper 'echo whisper-stub'
    free_gb 50
}

free_gb() { make_stub df "printf 'Filesystem 1024-blocks Used Available Capacity Mounted\n/dev/x 100 1 $(( $1 * 1048576 )) 1%% /\n'"; }
meminfo() { printf 'MemTotal:       %s kB\n' "$(( $1 * 1024 ))" > "$BATS_TEST_TMPDIR/meminfo"; export MEMINFO_FILE="$BATS_TEST_TMPDIR/meminfo"; }
whisper() { run bash "$VPS_DIR/02_I-install-python-whisper.sh"; }

@test "02_I fija la version de Whisper (por defecto una fecha concreta) y WHISPER_VERSION la cambia" {
    meminfo 8000
    whisper
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    grep -q 'openai-whisper==20250625' "$SUDO_LOG"
    ! grep -qE 'openai-whisper( |$)' "$SUDO_LOG"                       # nunca "la ultima" sin version
    WHISPER_VERSION=20240930 whisper
    grep -q 'openai-whisper==20240930' "$SUDO_LOG"
}

@test "02_I rechaza una version o un minimo de disco invalidos, sin tocar nada" {
    WHISPER_VERSION='latest; rm -rf /' whisper; [ "$status" -eq 2 ]
    WHISPER_MIN_GB=abc whisper; [ "$status" -eq 2 ]
    [ ! -s "$SUDO_LOG" ]
}

@test "02_I con poco disco se detiene ANTES de instalar nada y explica como seguir" {
    free_gb 3
    whisper
    [ "$status" -eq 1 ]
    [[ "$output" == *"quedan 3 GB libres"* ]] && [[ "$output" == *"al menos 8 GB"* ]] && [[ "$output" == *"WHISPER_FORCE=1"* ]]
    ! grep -q 'apt-get\|venv\|pip' "$SUDO_LOG"
}

@test "02_I: WHISPER_FORCE=1 sigue con poco disco, y WHISPER_MIN_GB baja el minimo" {
    meminfo 8000; free_gb 3
    WHISPER_FORCE=1 whisper
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [[ "$output" == *"se sigue de todos modos"* ]]
    WHISPER_MIN_GB=2 whisper
    [ "$status" -eq 0 ]
    [[ "$output" != *"ERROR"* ]]
}

@test "02_I avisa de los modelos que caben segun la RAM" {
    meminfo 1000; whisper
    [[ "$output" == *"solo los modelos tiny y base"* ]]
    meminfo 4000; whisper
    [[ "$output" == *"hasta el modelo small"* ]]
    meminfo 16000; whisper
    [[ "$output" != *"AVISO: "*"MB de RAM"* ]]
}

@test "disk_free_gb: mira la carpeta que contiene a una ruta que aun no existe" {
    free_gb 12
    run bash -c "source '$VPS_DIR/lib.sh'; disk_free_gb /ruta/que/no/existe"
    [ "$output" = 12 ]
}
