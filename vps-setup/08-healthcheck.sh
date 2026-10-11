#!/bin/bash
# Verificacion post-instalacion. Solo lee el estado: no cambia nada del servidor.
# Uso: ./08-healthcheck.sh [dominio]   (sin dominio, omite las pruebas web)
# Sale con codigo 1 si algo FALLA; los AVISOS (cosas opcionales) no cuentan.

source "$(dirname "$0")/lib.sh"

DOMAIN=${1:-""}
[ -n "$DOMAIN" ] && require_valid domain "$DOMAIN" "El dominio (argumento 1)"
FAILS=0
WARNS=0

ok()   { echo "  [OK]    $1"; }
fail() { echo "  [FALLA] $1"; FAILS=$((FAILS + 1)); }
warn() { echo "  [AVISO] $1"; WARNS=$((WARNS + 1)); }

print_header "08" "Healthcheck${DOMAIN:+ de $DOMAIN}"

echo "Servicios:"
# Obligatorios: los instalan los pasos 02_B/02_D/02_E del flujo estandar.
for svc in nginx php8.3-fpm postgresql; do
    if systemctl is-active --quiet "$svc"; then ok "$svc activo"; else fail "$svc NO esta activo"; fi
done
# Opcionales: solo se revisan si estan instalados.
for svc in webmin fail2ban mariadb tomcat10; do
    if systemctl list-unit-files "$svc.service" 2>/dev/null | grep -q "$svc.service"; then
        if systemctl is-active --quiet "$svc"; then ok "$svc activo"; else fail "$svc instalado pero NO activo"; fi
    fi
done

echo "Firewall:"
if sudo ufw status | grep -q "^Status: active"; then ok "UFW activo"; else fail "UFW inactivo"; fi

echo "Puertos escuchando:"
for port in 22 80 443; do
    if ss -ltn "( sport = :$port )" | grep -q LISTEN; then ok "puerto $port"; else fail "nada escucha en el puerto $port"; fi
done

if [ -z "$DOMAIN" ]; then
    warn "sin dominio: se omiten DNS, HTTPS, certificado y headers (uso: $0 tudominio.com)"
else
    echo "DNS:"
    MY_IP=$(get_public_ip)
    if verify_dns_resolution "$DOMAIN" "$MY_IP" >/dev/null; then
        ok "$DOMAIN y www.$DOMAIN apuntan a $MY_IP"
    else
        fail "DNS de $DOMAIN o www.$DOMAIN no apunta a $MY_IP (puede estar propagando)"
    fi

    echo "HTTPS:"
    CODE=$(curl -s -o /dev/null -m 10 -w '%{http_code}' "https://$DOMAIN/" || true)
    if [ "$CODE" = "200" ]; then ok "https://$DOMAIN responde 200"; else fail "https://$DOMAIN responde '${CODE:-sin respuesta}'"; fi
    REDIR=$(curl -s -o /dev/null -m 10 -w '%{http_code}' "http://$DOMAIN/" || true)
    case "$REDIR" in
        301|302|308) ok "http://$DOMAIN redirige a HTTPS ($REDIR)";;
        *) fail "http://$DOMAIN no redirige a HTTPS (respuesta '${REDIR:-ninguna}')";;
    esac

    echo "Certificado:"
    END=$(echo | openssl s_client -servername "$DOMAIN" -connect "$DOMAIN:443" 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)
    if [ -z "$END" ]; then
        fail "no se pudo leer el certificado de $DOMAIN"
    else
        DAYS=$(( ($(date -d "$END" +%s) - $(date +%s)) / 86400 ))
        if [ "$DAYS" -lt 0 ]; then fail "certificado VENCIDO ($END)"
        elif [ "$DAYS" -lt 14 ]; then warn "certificado vence en $DAYS dias ($END): revisa la renovacion (sudo certbot renew --dry-run)"
        else ok "certificado vence en $DAYS dias"; fi
    fi

    echo "Headers de seguridad:"
    HDRS=$(curl -sI -m 10 "https://$DOMAIN/" || true)
    for h in Strict-Transport-Security X-Frame-Options X-Content-Type-Options Referrer-Policy; do
        if echo "$HDRS" | grep -qi "^$h:"; then ok "$h"; else warn "falta $h (corre 07_B-nginx-security-headers.sh)"; fi
    done
fi

echo ""
echo "Resultado: $FAILS falla(s), $WARNS aviso(s)"
[ "$FAILS" -eq 0 ]
