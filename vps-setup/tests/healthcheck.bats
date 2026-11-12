#!/usr/bin/env bats
load helpers

setup() { setup_stubs; setup_system_stubs; export NGINX_DIR="$BATS_TEST_TMPDIR/nginx"; mkdir -p "$NGINX_DIR/sites-enabled"; }

hc() { run bash "$VPS_DIR/08-healthcheck.sh" "$@"; }

@test "todo sano: sale 0 sin fallas" {
    hc ejemplo.com
    [ "$status" -eq 0 ]
    [[ "$output" == *"0 falla(s), 0 aviso(s)"* ]]
}

@test "--sin-ssl prueba HTTP y omite HTTPS, certificado y headers" {
    REDIR=200 hc ejemplo.com --sin-ssl     # el stub de curl contesta REDIR a las direcciones http://
    [ "$status" -eq 0 ]
    [[ "$output" == *"http://ejemplo.com responde 200"* ]]
    [[ "$output" != *"https://"* ]] && [[ "$output" != *"Certificado:"* ]]
    REDIR=502 hc ejemplo.com --sin-ssl
    [ "$status" -eq 1 ]
}

@test "una opcion desconocida se rechaza" {
    hc ejemplo.com --nada
    [ "$status" -eq 2 ]
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

@test "Webmin escuchando en una direccion publica es un AVISO; solo en localhost, OK" {
    make_stub ss 'if [[ "$*" == *10000* ]]; then printf "State Recv-Q Send-Q Local Address:Port Peer\nLISTEN 0 128 ${WEBMIN_ADDR} 0.0.0.0:*\n"; else echo LISTEN; fi'
    WEBMIN_ADDR="0.0.0.0:10000" hc ejemplo.com
    [ "$status" -eq 0 ]                                          # un aviso no es una falla
    [[ "$output" == *"[AVISO] Webmin escucha en una direccion publica"* ]]
    WEBMIN_ADDR="127.0.0.1:10000" hc ejemplo.com
    [[ "$output" == *"[OK]    Webmin solo escucha en localhost"* ]]
    WEBMIN_ADDR="[::]:10000" hc ejemplo.com
    [[ "$output" == *"[AVISO] Webmin escucha en una direccion publica"* ]]
}

# ---- Sin certificado todavia: PENDIENTE, no FALLA ----
vhost_http() { printf '# vps-setup: 03-landing\nserver { listen 80; server_name ejemplo.com; }\n' > "$NGINX_DIR/sites-enabled/ejemplo.com"; }

@test "un vhost sin HTTPS se trata como --sin-ssl solo: nada de HTTPS/certificado/headers y el 443 queda PENDIENTE" {
    vhost_http
    REDIR=200 NOPORT="443" hc ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [[ "$output" == *"[PENDIENTE] puerto 443"* ]]
    [[ "$output" == *"[PENDIENTE] HTTPS, certificado y headers"* ]] && [[ "$output" == *"04-setup-ssl.sh ejemplo.com"* ]]
    [[ "$output" != *"[FALLA]"* ]] && [[ "$output" != *"Certificado:"* ]]
    [[ "$output" == *"0 falla(s), 0 aviso(s)"* ]]
}

@test "sin certificado y con el DNS sin apuntar aqui: PENDIENTE, y HTTP se prueba en este servidor" {
    vhost_http
    DIGIP=1.2.3.4 REDIR=200 hc ejemplo.com
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [[ "$output" == *"[PENDIENTE] DNS de ejemplo.com"* ]]
    [[ "$output" == *"responde 200 (probado en este servidor)"* ]]
}

@test "con HTTPS en el vhost, el DNS mal sigue siendo FALLA y el 443 caido tambien" {
    printf 'server { listen 443 ssl; ssl_certificate /x; }\n' > "$NGINX_DIR/sites-enabled/ejemplo.com"
    DIGIP=1.2.3.4 NOPORT="443" hc ejemplo.com
    [ "$status" -eq 1 ]
    [[ "$output" == *"[FALLA] DNS de ejemplo.com"* ]] && [[ "$output" == *"[FALLA] nada escucha en el puerto 443"* ]]
}

@test "--sin-ssl explicito: el HTTP caido sigue siendo FALLA aunque lo demas este pendiente" {
    REDIR=502 DIGIP=1.2.3.4 hc ejemplo.com --sin-ssl
    [ "$status" -eq 1 ]
    [[ "$output" == *"[FALLA] http://ejemplo.com responde '502'"* ]]
}
