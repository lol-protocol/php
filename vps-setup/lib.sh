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

# Version de PHP del stack: la que trae Ubuntu 24.04. Es el UNICO lugar donde se fija: los
# paquetes (02_B), el socket de PHP-FPM (06_A), el healthcheck (08) y la lista de servicios del
# monitor (la escribe 09_A en /etc/vps-monitor.conf) salen de aqui.
# shellcheck disable=SC2034  # variables para los scripts que hacen "source lib.sh"
PHP_VERSION=${PHP_VERSION:-8.3}
# shellcheck disable=SC2034
PHP_FPM_SERVICE="php${PHP_VERSION}-fpm"

# Tamano maximo de una peticion (subidas de archivos) en las apps (06_A/06_B/06_D). Sin esto
# Nginx usa 1 MB y rechaza cualquier subida mayor con un "413 Request Entity Too Large".
MAX_UPLOAD=${MAX_UPLOAD:-20M}
# Cuanto espera Nginx a una app (Python/Tomcat) antes de cortar con 504; el default (60 s) no se
# eligio para nada en particular. Formato Nginx: 120s, 5m...
PROXY_TIMEOUT=${PROXY_TIMEOUT:-120s}

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
    # fail2ban resuelve el glob de logs por dominio (/var/log/nginx/*/...) al arrancar: un
    # dominio nuevo no queda vigilado hasta que recarga.
    if systemctl is-active --quiet fail2ban 2>/dev/null; then
        sudo fail2ban-client reload > /dev/null 2>&1 || true
    fi
}

# raise_php_upload_limit <tamano>: sube upload_max_filesize y post_max_size de PHP-FPM a <tamano>.
# PHP tiene sus propios limites (2M y 8M por defecto): con solo subir el de Nginx, PHP seguiria
# descartando el archivo. Es un ajuste GLOBAL de PHP-FPM (todas las apps comparten el pool); el
# limite de cada sitio lo pone su client_max_body_size en Nginx. Si ya habia uno mas grande (de
# otra app), se conserva. Archivo reemplazable con PHP_UPLOAD_INI (pruebas).
raise_php_upload_limit() {
    local size=$1 ini current
    ini=${PHP_UPLOAD_INI:-/etc/php/$PHP_VERSION/fpm/conf.d/90-vps-setup-uploads.ini}
    to_bytes() { local n=${1%?}; case "${1: -1}" in K) echo $((n*1024));; M) echo $((n*1024**2));; G) echo $((n*1024**3));; esac; }
    current=$(sed -n 's/^upload_max_filesize *= *//p' "$ini" 2>/dev/null || true)   # aun no existe: vacio
    if [ ! -d "$(dirname "$ini")" ]; then
        echo "AVISO: no existe $(dirname "$ini") (¿falta 02_B-install-php.sh?): no se ajusto el limite de subida de PHP."
    elif [ -z "$current" ] || [ "$(to_bytes "$size")" -gt "$(to_bytes "$current")" ]; then
        printf 'upload_max_filesize = %s\npost_max_size = %s\n' "$size" "$size" | sudo tee "$ini" > /dev/null
        sudo systemctl reload "$PHP_FPM_SERVICE"
    fi
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

# app_has_code <carpeta>: 0 si la carpeta ya tiene algo que NO sea el venv/ de los scripts, es decir,
# codigo (o archivos) de la persona. 06_A y 06_B lo usan para no pisar una app ya subida al
# re-correrlos (por ejemplo, para cambiar el puerto o el limite de subida).
app_has_code() {
    [ -d "$1" ] && [ -n "$(find "$1" -mindepth 1 -maxdepth 1 ! -name venv -print -quit 2>/dev/null)" ]
}

# ---------------------------------------------------------------------------
# Aislamiento por app: cada app tiene su usuario de sistema "app-<nombre>", sus archivos son
# suyos (750) y, si hay PostgreSQL, su propia base y su propio usuario de BD. Asi una app
# comprometida no lee el codigo, el .env ni la base de las demas.
# ---------------------------------------------------------------------------
APP_USER_PREFIX=${APP_USER_PREFIX:-app-}

app_user_name() { echo "${APP_USER_PREFIX}$1"; }

# create_app_user <usuario> <carpeta>: usuario de sistema sin login ni home propio (su "home" es la
# carpeta de la app). Idempotente. Para entrar como el: sudo -u <usuario> bash (no -s: su shell es nologin).
create_app_user() {
    local user=$1 home=$2
    if id "$user" > /dev/null 2>&1; then return 0; fi
    sudo useradd --system --user-group --home-dir "$home" --no-create-home --shell /usr/sbin/nologin "$user"
}

# own_app_dir <carpeta> <usuario>: la carpeta (y su contenido) pasa a ser del usuario de la app, modo 750.
# Solo cambia el dueno de TODO el arbol si la carpeta esta vacia o ya es del usuario: sobre una app con
# codigo de OTRO dueno no se toca nada (chown -R cambiaria permisos de todos sus archivos) y se explica
# como migrarla a mano.
own_app_dir() {
    local path=$1 user=$2 cur
    cur=$(stat -c %U "$path" 2> /dev/null || echo "")
    if [ "$cur" = "$user" ]; then
        :
    elif app_has_code "$path"; then
        echo "ERROR: $path ya tiene codigo y es de '$cur', no de '$user'." >&2
        echo "Para aislarla, pasala a su usuario (revisa antes que nada la use mas): sudo chown -R $user:$user $path" >&2
        exit 1
    else
        sudo chown -R "$user:$user" "$path"
    fi
    sudo chmod 750 "$path"
}

# setup_app_database <app> <archivo .env> <usuario dueno del .env>: crea (si PostgreSQL esta instalado)
# el rol y la base "app_<nombre>", les pone una contrasena aleatoria y la guarda como DATABASE_URL en
# el .env de la app (modo 600, solo del usuario de la app). Nada de la contrasena pasa por la linea de
# comandos ni por la pantalla: el SQL entra por stdin y el .env se escribe con umask 077.
#   - Idempotente: si el .env ya tiene DATABASE_URL, no se toca (no se rota la contrasena en cada corrida).
#   - Aislada: se revoca CONNECT a PUBLIC, asi el rol de otra app no puede entrar a esta base.
#   - APP_DB=0 la omite.
setup_app_database() {
    local app=$1 env_file=$2 owner=$3 db pw exists
    db="app_${app//-/_}"
    if [ "${APP_DB:-1}" = 0 ]; then
        echo "APP_DB=0: no se crea base de datos para $app."
        return 0
    fi
    if ! command -v psql > /dev/null 2>&1; then
        echo "AVISO: PostgreSQL no esta instalado (02_D-install-postgresql.sh): no se crea base de datos para $app."
        return 0
    fi
    if sudo test -f "$env_file" && sudo grep -q '^DATABASE_URL=' "$env_file"; then
        echo "  La base de datos de $app ya esta configurada (.env con DATABASE_URL): se conserva."
        return 0
    fi
    pw=$(openssl rand -hex 16)
    # "cd /": el usuario postgres no puede entrar a la carpeta actual (psql avisaria de ello)
    exists=$(cd / && sudo -u postgres psql -tAc "SELECT 1 FROM pg_roles WHERE rolname='$db'")
    if [ "$exists" = 1 ]; then
        printf "ALTER ROLE %s WITH LOGIN PASSWORD '%s';\n" "$db" "$pw" | (cd / && sudo -u postgres psql -q -v ON_ERROR_STOP=1)
    else
        printf "CREATE ROLE %s LOGIN PASSWORD '%s';\n" "$db" "$pw" | (cd / && sudo -u postgres psql -q -v ON_ERROR_STOP=1)
    fi
    exists=$(cd / && sudo -u postgres psql -tAc "SELECT 1 FROM pg_database WHERE datname='$db'")
    [ "$exists" = 1 ] || (cd / && sudo -u postgres createdb --owner="$db" "$db")
    printf 'REVOKE ALL ON DATABASE %s FROM PUBLIC;\n' "$db" | (cd / && sudo -u postgres psql -q -v ON_ERROR_STOP=1)
    printf 'DATABASE_URL=postgresql://%s:%s@localhost:5432/%s\n' "$db" "$pw" "$db" \
        | sudo sh -c 'umask 077; cat > "$1"' sh "$env_file"
    sudo chown "$owner:$owner" "$env_file"
    sudo chmod 600 "$env_file"
    echo "  Base de datos '$db' creada; la conexion (DATABASE_URL) esta en $env_file (solo la lee $owner)."
}

# Setup app directories with correct ownership
setup_app_directories() {
    local app_path=$1
    local domain=$2

    sudo mkdir -p "$app_path"
    # chown -R solo en una carpeta nueva o sin codigo: sobre una app ya subida cambiaria el dueno y
    # los permisos de TODOS sus archivos (un .env con 600, un deploy con otro usuario...)
    if app_has_code "$app_path"; then
        sudo chown www-data:www-data "$app_path"
    else
        sudo chown -R www-data:www-data "$app_path"
    fi
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

# Tamano para Nginx/PHP: numero + K, M o G (ej. 20M). Max. 4 cifras.
validate_size() {
    [[ "$1" =~ ^[1-9][0-9]{0,3}[KMG]$ ]]
}

# Tiempo para Nginx: numero + s o m (ej. 120s, 5m).
validate_duration() {
    [[ "$1" =~ ^[1-9][0-9]{0,3}[sm]$ ]]
}

# Nombre de app cuyo usuario de sistema "app-<nombre>" cabe en los 32 caracteres que admite Linux.
validate_appuser() {
    [ $(( ${#1} + ${#APP_USER_PREFIX} )) -le 32 ]
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
        appuser)      hint="como maximo $((32 - ${#APP_USER_PREFIX})) caracteres (su usuario de sistema es ${APP_USER_PREFIX}<nombre>, y Linux admite 32)" ;;
        size)         hint="un numero seguido de K, M o G (ej. 20M)" ;;
        duration)     hint="un numero seguido de s o m (ej. 120s)" ;;
        name)         hint="minusculas, digitos, '_' y '-' (max. 63), debe empezar con letra o digito" ;;
        context_path) hint="letras, digitos, '.', '_' y '-' (sin '/' ni '..'); vacio = raiz" ;;
        webhook_url)  hint="una URL http(s) sin comillas, espacios, '\$' ni '\`'" ;;
        *)            hint="" ;;
    esac
    printf 'ERROR: %s invalido: %q\n  Debe ser: %s\n' "$label" "${value:0:80}" "$hint" >&2
    exit 2
}
