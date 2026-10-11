#!/bin/bash
# Shared shell functions for VPS setup scripts
# Source this file at the start of each setup script: source "$(dirname "$0")/lib.sh"

# Print colored header for each installation step
print_header() {
    local step_num=$1
    local step_name=$2
    echo "========================================"
    echo "[$step_num] $step_name"
    echo "========================================"
    echo ""
}

# Start and enable a systemd service
service_start_enable() {
    local service=$1
    sudo systemctl start "$service"
    sudo systemctl enable "$service"
}

# Raiz de la configuracion de Nginx (reemplazable en los tests).
NGINX_DIR=${NGINX_DIR:-/etc/nginx}

# require_arg <valor> <uso>: sin valor, muestra el uso y sale con 2. Los scripts NO usan
# un dominio por defecto: correr uno sin argumentos en el servidor equivocado configuraria
# (o pediria certificados para) un dominio que no es el tuyo.
require_arg() {
    if [ -z "$1" ]; then
        echo "Uso: $2" >&2
        exit 2
    fi
}

# claim_nginx_vhost <dominio> <marca>: cada vhost se llama como su DOMINIO y lleva en su
# primera linea "# vps-setup: <marca>" (script + app). Falla si el dominio ya tiene un vhost
# con OTRA marca (otro tipo de sitio u otra app) o si otro sitio activo ya declara ese
# server_name: dos vhosts con el mismo dominio hacen que Nginx ignore uno en silencio.
# Re-correr el mismo script para el mismo dominio y app si se permite (es idempotente).
claim_nginx_vhost() {
    local domain=$1 mark=$2 f other existing re
    f="$NGINX_DIR/sites-available/$domain"
    if [ -f "$f" ] && ! grep -qxF "# vps-setup: $mark" "$f"; then
        existing=$(grep -m1 '^# vps-setup:' "$f" | sed 's/^# vps-setup: //')
        echo "ERROR: $domain ya tiene un sitio (${existing:-creado a mano}) en $f." >&2
        echo "Quitalo primero con: ./10_B-remove-domain.sh $domain" >&2
        exit 1
    fi
    re="^[[:space:]]*server_name([[:space:]]+[^[:space:];]+)*[[:space:]]+(www\.)?${domain//./\\.}([[:space:];]|$)"
    for other in "$NGINX_DIR"/sites-enabled/*; do
        [ -e "$other" ] || continue
        [ "$(basename "$other")" = "$domain" ] && continue
        if grep -qE "$re" "$other"; then
            echo "ERROR: el sitio activo $(basename "$other") ya responde a $domain (server_name)." >&2
            echo "Quitalo primero (./10_B-remove-domain.sh ... --app $(basename "$other")) o usa otro dominio." >&2
            exit 1
        fi
    done
}

# enable_nginx_site <nombre>: activa sites-available/<nombre>, valida con "nginx -t" y recarga.
# Si nginx rechaza la configuracion, DESACTIVA el sitio nuevo (el archivo queda en
# sites-available para revisarlo): un enlace roto en sites-enabled impediria que Nginx
# arranque en el proximo reinicio y tumbaria todos los dominios.
enable_nginx_site() {
    local name=$1 link
    link="$NGINX_DIR/sites-enabled/$name"
    sudo ln -sf "$NGINX_DIR/sites-available/$name" "$link"
    if ! sudo nginx -t; then
        sudo rm -f "$link"
        echo "ERROR: nginx rechazo la configuracion de $name; se desactivo ese sitio (revisa $NGINX_DIR/sites-available/$name)." >&2
        return 1
    fi
    sudo systemctl reload nginx
}

# Safe UFW rule (only if ufw is active)
ufw_allow() {
    local rule=$1
    if ! sudo ufw status | grep -q "^Status: active"; then
        echo "WARNING: UFW is not active, skipping rule: $rule"
        return 0
    fi
    sudo ufw allow "$rule" || echo "WARNING: Failed to add UFW rule: $rule"
}

# Check if a required command/package is installed
check_dependency() {
    local command=$1
    local install_guidance=$2

    if ! command -v "$command" &> /dev/null; then
        echo "ERROR: $command no esta instalado."
        echo "Corre primero: $install_guidance"
        exit 1
    fi
}

# Create Nginx domain log directories.
# Must stay root-owned: Nginx opens logs as root, so a www-data-writable log dir
# lets a compromised app symlink its way to root (CVE-2016-1247).
setup_nginx_domain_logs() {
    local domain=$1
    sudo mkdir -p /var/log/nginx/"$domain"
}

# Setup app directories with correct ownership
setup_app_directories() {
    local app_path=$1
    local domain=$2

    sudo mkdir -p "$app_path"
    sudo chown -R www-data:www-data "$app_path"
    setup_nginx_domain_logs "$domain"
}

# Deploy landing page or app files
deploy_files() {
    local source_dir=$1
    local dest_dir=$2

    if [ ! -d "$source_dir" ]; then
        echo "ERROR: Source directory does not exist: $source_dir"
        return 1
    fi

    # "/." (not "/*") copies the CONTENTS of source_dir, including dotfiles,
    # and never fails just because the glob matched nothing. The previous
    # "cp * || cp source_dir dest_dir" fallback nested the whole source_dir
    # INSIDE dest_dir on any failure (e.g. landing-page/index.html would land
    # at $dest_dir/landing-page/index.html instead of $dest_dir/index.html),
    # silently 404-ing Nginx without the script ever reporting an error.
    sudo cp -r "$source_dir"/. "$dest_dir"/
    sudo find "$dest_dir" -type f -exec chmod 644 {} \;
    sudo find "$dest_dir" -type d -exec chmod 755 {} \;
    sudo chown -R www-data:www-data "$dest_dir"
}

# Get public IP (cached if already fetched this session)
get_public_ip() {
    # Check if already cached in this session
    if [ -n "$CACHED_PUBLIC_IP" ]; then
        echo "$CACHED_PUBLIC_IP"
        return 0
    fi

    local ip
    ip=$(curl -4 -s --max-time 5 ifconfig.me 2>/dev/null || echo "")
    if [ -z "$ip" ]; then
        # Placeholder SIN cachear: un fallo de red momentaneo no debe quedar
        # "pegado" (y exportado a los pasos hijos) para el resto de la corrida.
        echo "TU_IP_PUBLICA"
        return 0
    fi

    # export (not just assign) so child processes started with "bash script.sh"
    # inherit the cached value instead of re-fetching it themselves
    export CACHED_PUBLIC_IP="$ip"
    echo "$ip"
}

# Verifica que $1 y www.$1 resuelvan a la IP $2. Hay que comprobar los DOS por
# separado: certbot pide el certificado para ambos y rechaza TODO si uno falla,
# asi que "alguno de los dos coincide" no basta.
verify_dns_resolution() {
    local domain=$1
    local expected_ip=$2
    local name ips

    for name in "$domain" "www.$domain"; do
        ips=$(dig +short -t A "$name" 2>/dev/null | sort -u)
        if [ -z "$ips" ]; then
            echo "WARNING: $name does not resolve yet. DNS propagation can take 15-60 minutes."
            return 1
        fi
        # Con un CNAME, dig +short lista el alias y luego la(s) IP: basta que este la esperada.
        if ! echo "$ips" | grep -qx "$expected_ip"; then
            echo "WARNING: $name resolves to [$(echo $ips)], expected $expected_ip"
            return 1
        fi
    done
    return 0
}

# ---------------------------------------------------------------------------
# Validacion de entradas. Dominio, email, nombres de app, etc. terminan dentro de
# archivos de Nginx/systemd/cron y de comandos con sudo; un valor con espacios,
# saltos de linea, ";" o "$(...)" romperia la configuracion o ejecutaria cosas.
# Se valida con listas BLANCAS (solo lo permitido), antes de tocar el sistema.
# ---------------------------------------------------------------------------

# Dominio en minusculas, sin punto final: etiquetas de 1-63 [a-z0-9-] (sin guion
# al inicio/fin), al menos un punto y TLD que empieza con letra. Punycode (xn--)
# vale; los nombres con caracteres Unicode deben pasarse ya convertidos.
validate_domain() {
    local d=$1
    [ "${#d}" -ge 4 ] && [ "${#d}" -le 253 ] || return 1
    [[ "$d" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]([a-z0-9-]{0,61}[a-z0-9])$ ]]
}

validate_email() {
    local e=$1 local_part domain
    [ "${#e}" -le 254 ] || return 1
    [[ "$e" == *@* ]] || return 1
    local_part=${e%@*}
    domain=${e##*@}
    [[ "$local_part" =~ ^[A-Za-z0-9._%+-]{1,64}$ ]] || return 1
    validate_domain "${domain,,}"
}

validate_ipv4() {
    [[ "$1" =~ ^((25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])\.){3}(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])$ ]]
}

# Puerto TCP local para una app (fuera del rango privilegiado).
validate_port() {
    [[ "$1" =~ ^[0-9]{4,5}$ ]] && [ "$1" -ge 1024 ] && [ "$1" -le 65535 ]
}

# IPv6 (forma completa o abreviada con "::"); solo hex y ":".
validate_ipv6() {
    [[ "$1" =~ ^[0-9a-fA-F:]+$ ]] && [[ "$1" == *:*:* ]] && [ "${#1}" -le 39 ]
}

# Nombre de app / servicio systemd: minusculas, digitos, "_" y "-".
validate_name() {
    [[ "$1" =~ ^[a-z0-9][a-z0-9_-]{0,62}$ ]]
}

# Ruta de contexto de Tomcat: puede ser vacia (raiz); sin "/" ni "..".
validate_context_path() {
    [[ "$1" =~ ^[A-Za-z0-9._-]*$ ]] && [[ "$1" != *..* ]] && [[ "$1" != "." ]]
}

# URL de webhook que se guarda en un archivo que el monitor "source"-a como shell:
# no puede contener comillas, "$", "`", "\", espacios ni saltos de linea.
validate_webhook_url() {
    [[ "$1" =~ ^https?://[A-Za-z0-9._~:/?#@\!\&,\;=%+-]+$ ]]
}

# require_valid <tipo> <valor> <etiqueta>: si no valida, explica y sale con 2.
require_valid() {
    local kind=$1 value=$2 label=$3 hint
    if "validate_$kind" "$value"; then
        return 0
    fi
    case "$kind" in
        domain)       hint="en minusculas, con al menos un punto, sin espacios ni caracteres especiales (ej. ejemplo.com)" ;;
        email)        hint="formato usuario@dominio.com" ;;
        ipv4)         hint="una IPv4 como 203.0.113.5" ;;
        ipv6)         hint="una IPv6 como 2001:db8::5" ;;
        port)         hint="un numero entre 1024 y 65535" ;;
        name)         hint="minusculas, digitos, '_' y '-' (max. 63), debe empezar con letra o digito" ;;
        context_path) hint="letras, digitos, '.', '_' y '-' (sin '/' ni '..'); vacio = raiz" ;;
        webhook_url)  hint="una URL http(s) sin comillas, espacios, '\$' ni '\`'" ;;
        *)            hint="" ;;
    esac
    printf 'ERROR: %s invalido: %q\n  Debe ser: %s\n' "$label" "${value:0:80}" "$hint" >&2
    exit 2
}
