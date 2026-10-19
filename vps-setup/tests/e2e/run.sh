#!/bin/bash
# Prueba de punta a punta: corre install-all.sh DE VERDAD (apt, systemd, servicios) y luego
# comprueba el resultado desde fuera. SOLO en una maquina desechable con Ubuntu 24.04 y systemd
# (el runner de GitHub Actions, o una VM de prueba): instala paquetes, activa UFW y escribe en /etc.
# Uso: sudo REAL_E2E=1 vps-setup/tests/e2e/run.sh
set -u
[ "${REAL_E2E:-0}" = 1 ] || { echo "Esto instala y modifica el sistema: REAL_E2E=1 para confirmar."; exit 2; }
[ "$(id -u)" = 0 ] || { echo "Correr como root (sudo)."; exit 2; }

VPS_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
D=e2e.example.test           # .test: dominio reservado, nunca resuelve en internet
APP_PHP=tiendae2e; D_PHP=tienda.$D
APP_PY=blog; D_PY=blog.$D; PY_PORT=8011
D_JAVA=java.$D
export INSTALL_STATE_FILE=/tmp/e2e-state/install-all.state
mkdir -p /tmp/e2e-state
FAILS=0

ok()   { echo "  [OK]    $1"; }
fail() { echo "  [FALLA] $1"; FAILS=$((FAILS + 1)); }
OUT=/tmp/e2e-state/check.out
# Si falla, muestra el final de la salida del comando (si no, el error se pierde).
check() {
    local desc=$1; shift
    if "$@" > "$OUT" 2>&1; then ok "$desc"; else fail "$desc"; tail -n 15 "$OUT" | sed 's/^/          | /'; fi
}
http()  { curl -s -o /dev/null -m 10 -w '%{http_code}' -H "Host: $1" "http://127.0.0.1$2"; }
# "nginx reload" es asincrono: justo despues, los workers viejos aun pueden atender con la config
# anterior. Se espera (hasta ~20 s) a que el Host responda lo esperado antes de comprobar.
wait_http() { for _ in $(seq 1 20); do [ "$(http "$1" "$2")" = "$3" ] && return 0; sleep 1; done; return 1; }
step()  { echo; echo "=== $* ==="; }

cd "$VPS_DIR" || exit 2

step "install-all (con Webmin, sin SSL: $D no tiene DNS publico)"
./install-all.sh "$D" "admin@$D" --yes --with-webmin --skip-ssl < /dev/null
echo "install-all termino con codigo $?"
check "install-all marco los 14 pasos como hechos" test "$(grep -c "^$D"$'\t' "$INSTALL_STATE_FILE")" -eq 14

step "Servicios"
for s in nginx php8.3-fpm postgresql fail2ban webmin; do check "$s activo" systemctl is-active --quiet "$s"; done
check "UFW activo" bash -c 'ufw status | grep -q "Status: active"'
check "fail2ban vigila sshd" fail2ban-client status sshd
for j in recidive nginx-botsearch nginx-http-auth nginx-bad-request; do
    check "fail2ban: jail $j activo" fail2ban-client status "$j"
done
check "UFW limita SSH (LIMIT, no solo ALLOW)" bash -c 'ufw status | grep -E "^OpenSSH +LIMIT"' 
check "Webmin responde en 10000 (HTTPS)" bash -c 'curl -sk -o /dev/null -w "%{http_code}" https://127.0.0.1:10000/ | grep -qE "^(200|30[0-9])$"'
check "apt: espera el lock y conserva configs (01)" grep -q 'Lock::Timeout' /etc/apt/apt.conf.d/90-vps-setup

step "Landing ($D)"
check "responde 200" test "$(http "$D" /)" = 200
H=$(curl -s -D - -o /dev/null -H "Host: $D" -H 'Accept-Encoding: gzip' http://127.0.0.1/ | tr -d '\r')
for h in Strict-Transport-Security X-Frame-Options X-Content-Type-Options Referrer-Policy; do
    check "header $h" grep -qi "^$h:" <<< "$H"
done
check "gzip (07_E)" grep -qi '^content-encoding: gzip' <<< "$H"
check "sin version de Nginx (server_tokens off)" grep -qiE '^server: nginx$' <<< "$H"
check "un .php en la landing da 404" bash -c "echo '<?php echo 1;' > /var/www/landing-page/$D/t.php; test \"\$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: $D' http://127.0.0.1/t.php)\" = 404"
check "un Host ajeno no recibe respuesta (444)" test "$(http otro.example.net /)" = 000

step "App PHP (06_A)"
check "06_A termina bien" ./06_A-setup-php-app.sh "$APP_PHP" "$D_PHP"
wait_http "$D_PHP" / 200
check "una subida de 5 MB llega a PHP (sin limite seria 413)" bash -c "head -c 5000000 /dev/zero > /tmp/e2e-state/5mb && test \"\$(curl -s -o /dev/null -w '%{http_code}' -H 'Host: $D_PHP' -F f=@/tmp/e2e-state/5mb http://127.0.0.1/)\" = 200"
check "PHP acepta subidas de 20M (90-vps-setup-uploads.ini)" bash -c "php-fpm8.3 -i 2>/dev/null | grep -q 'upload_max_filesize => 20M' || grep -q 'upload_max_filesize = 20M' /etc/php/8.3/fpm/conf.d/90-vps-setup-uploads.ini"
check "PHP-FPM ejecuta la pagina de prueba" bash -c "curl -s -H 'Host: $D_PHP' http://127.0.0.1/ | grep -q 'PHP-FPM + Nginx funcionando'"

step "App Python (06_B, servicio systemd real en el puerto $PY_PORT)"
check "06_B termina bien" ./06_B-setup-python-app.sh "$APP_PY" "$D_PY" "$PY_PORT"
check "servicio $APP_PY activo" systemctl is-active --quiet "$APP_PY"
wait_http "$D_PY" / 200
check "gunicorn responde detras de Nginx" bash -c "curl -s -H 'Host: $D_PY' http://127.0.0.1/ | grep -q 'Hello from Python App'"
check "otra app en el mismo puerto se rechaza" bash -c "! ./06_B-setup-python-app.sh otra otra.$D $PY_PORT < /dev/null"

step "Tomcat (02_H + 06_D en la raiz)"
check "02_H termina bien" ./02_H-install-tomcat.sh
check "06_D termina bien" ./06_D-setup-tomcat-app.sh "$D_JAVA"
wait_http "$D_JAVA" / 200
check "Tomcat responde por Nginx" test "$(http "$D_JAVA" /)" = 200
check "/manager/html NO se alcanza (404)" test "$(http "$D_JAVA" /manager/html)" = 404
check "Tomcat no queda expuesto en 8080 desde fuera (UFW sin regla)" bash -c '! ufw status | grep -q 8080'

step "DNS propio (06_C, BIND real; el secundario de OVH se resuelve por internet)"
check "06_C termina bien" env VPS_IP6=2001:db8::7 ./06_C-setup-dns-server.sh "$D" 203.0.113.7
check "named habilitado al arranque" systemctl is-enabled --quiet named
check "BIND responde el registro A" test "$(dig @127.0.0.1 +short "$D" A)" = 203.0.113.7
check "BIND responde CAA (solo Let's Encrypt)" bash -c "dig @127.0.0.1 +short '$D' CAA | grep -q letsencrypt.org"
check "BIND responde AAAA" test "$(dig @127.0.0.1 +short "www.$D" AAAA)" = 2001:db8::7
check "BIND no es resolvedor abierto (REFUSED)" bash -c "dig @127.0.0.1 +time=3 +tries=1 example.org A | grep -q REFUSED"

step "Alta y baja de dominios (10_A / 10_B)"
check "10_A agrega un dominio" ./10_A-add-domain.sh "nuevo.$D"
wait_http "nuevo.$D" / 200
check "el dominio nuevo responde 200" test "$(http "nuevo.$D" /)" = 200
check "10_B lo archiva" ./10_B-remove-domain.sh "nuevo.$D" --yes
wait_http "nuevo.$D" / 000
check "tras 10_B ya no se sirve" test "$(http "nuevo.$D" /)" = 000
check "la landing sigue en 200" test "$(http "$D" /)" = 200
check "nginx -t sigue OK" nginx -t

step "Monitoreo (09_A) y healthcheck sin dominio (08)"
check "09_A termina bien" ./09_A-setup-monitoring.sh
check "vps-monitor --dry-run sin problemas" bash -c 'vps-monitor --dry-run | grep -q "OK: sin problemas"'
check "08-healthcheck (sin dominio) sin fallas" ./08-healthcheck.sh

echo
echo "Resultado e2e: $FAILS falla(s)"
[ "$FAILS" -eq 0 ]
