#!/usr/bin/env bats
load helpers

setup() { setup_stubs; setup_system_stubs; }

hc() { run bash "$VPS_DIR/08-healthcheck.sh" "$@"; }

@test "todo sano: sale 0 sin fallas" {
    hc ejemplo.com
    [ "$status" -eq 0 ]
    [[ "$output" == *"0 falla(s), 0 aviso(s)"* ]]
}

@test "sin dominio omite las pruebas web y sale 0" {
    hc
    [ "$status" -eq 0 ]
    [[ "$output" == *"sin dominio"* ]]
}

@test "un servicio caido es una falla (exit 1)" {
    DOWN="nginx" hc ejemplo.com
    [ "$status" -eq 1 ]
    [[ "$output" == *"[FALLA] nginx NO esta activo"* ]]
}

@test "UFW inactivo es una falla" {
    UFW_STATUS=inactive hc ejemplo.com
    [ "$status" -eq 1 ]
    [[ "$output" == *"UFW inactivo"* ]]
}

@test "un puerto sin escuchar es una falla" {
    NOPORT="443" hc ejemplo.com
    [ "$status" -eq 1 ]
    [[ "$output" == *"puerto 443"* ]]
}

@test "DNS que no apunta a este VPS es una falla" {
    DIGIP=1.2.3.4 hc ejemplo.com
    [ "$status" -eq 1 ]
    [[ "$output" == *"DNS de ejemplo.com"* ]]
}

@test "HTTPS distinto de 200 y sin redireccion HTTP son fallas" {
    CODE=502 REDIR=200 hc ejemplo.com
    [ "$status" -eq 1 ]
    [[ "$output" == *"responde '502'"* ]]
    [[ "$output" == *"no redirige"* ]]
}

@test "certificado por vencer es solo aviso" {
    CERT_DAYS=5 hc ejemplo.com
    [ "$status" -eq 0 ]
    [[ "$output" == *"[AVISO] certificado vence en"* ]]
}

@test "certificado vencido es falla" {
    CERT_DAYS=-3 hc ejemplo.com
    [ "$status" -eq 1 ]
    [[ "$output" == *"VENCIDO"* ]]
}

@test "headers faltantes son avisos que apuntan a 07_B" {
    HDRS="X-Frame-Options: y\r\n" hc ejemplo.com
    [ "$status" -eq 0 ]
    [[ "$output" == *"falta Strict-Transport-Security"* ]]
    [[ "$output" == *"07_B"* ]]
}

@test "servicios opcionales instalados se revisan: fail2ban caido es falla" {
    DOWN="fail2ban" hc ejemplo.com
    [ "$status" -eq 1 ]
    [[ "$output" == *"fail2ban instalado pero NO activo"* ]]
}
