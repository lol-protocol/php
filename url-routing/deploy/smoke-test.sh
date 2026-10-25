#!/usr/bin/env bash
#
# Prueba de humo del despliegue de url-routing: levanta nginx y PHP-FPM de verdad, con
# deploy/nginx.conf.example y los pasos de docs/DESPLIEGUE.md, y comprueba lo que la guía dice que
# se ve. Lo que prueba es la instalación (permisos, migraciones, cabeceras, lo que no debe servirse,
# HTTPS, el limitador), no la lógica de las páginas: esa la cubre phpunit.
#
# Uso, desde url-routing/:   ./deploy/smoke-test.sh
#
# Hace falta: nginx, php-fpm de la misma versión que `php`, composer, curl y openssl. No toca el
# sistema: todo vive en un directorio temporal que se borra al terminar, y los servicios escuchan en
# 127.0.0.1 en puertos altos (8088 HTTP y 8443 HTTPS por omisión).
#
# Opciones, por variables de entorno:
#   SMOKE_PG_DSN, SMOKE_PG_USER, SMOKE_PG_PASSWORD
#       Una base PostgreSQL vacía para el sitio de genealogía (el POS usa SQLite). Sin ellas,
#       genealogía también usa SQLite.
#   SMOKE_NGINX, SMOKE_PHP_FPM    Rutas de los binarios, si no están en el PATH ni en /usr/sbin.
#   SMOKE_PHP_INI                 Un php.ini para PHP-FPM (por ejemplo, con extension_dir) si el
#                                 binario no viene de un paquete del sistema.
#   SMOKE_PORT, SMOKE_TLS_PORT    Puertos HTTP y HTTPS.
#   SMOKE_KEEP=1                  No borrar el directorio temporal (para mirar los logs).
#
# Como root, los servicios corren como www-data (y las migraciones también: ver paso 4 de la guía);
# como cualquier otro usuario, corren como ese usuario.

set -euo pipefail

AQUI=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PROYECTO=$(dirname "$AQUI")
PORT=${SMOKE_PORT:-8088}
TLS_PORT=${SMOKE_TLS_PORT:-8443}
GENEALOGIA=tudominio.com
POS=contrastocolor.com

falta() {
    echo "smoke-test: falta $1" >&2
    exit 2
}

buscar() { # buscar <variable> <nombre>... : primer ejecutable que exista
    local variable=$1 candidato
    shift
    if [ -n "${!variable:-}" ]; then
        echo "${!variable}"
        return 0
    fi
    for candidato in "$@"; do
        if command -v "$candidato" >/dev/null 2>&1; then
            command -v "$candidato"
            return 0
        fi
        if [ -x "/usr/sbin/$candidato" ]; then
            echo "/usr/sbin/$candidato"
            return 0
        fi
    done
    return 1
}

VERSION_PHP=$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')
NGINX=$(buscar SMOKE_NGINX nginx) || falta nginx
FPM=$(buscar SMOKE_PHP_FPM "php-fpm${VERSION_PHP}" php-fpm) || falta "php-fpm${VERSION_PHP}"
for herramienta in curl openssl composer php; do
    command -v "$herramienta" >/dev/null 2>&1 || falta "$herramienta"
done

if [ "$(id -u)" -eq 0 ]; then
    WEB_USER=www-data
    id "$WEB_USER" >/dev/null 2>&1 || falta "el usuario $WEB_USER"
else
    WEB_USER=$(id -un)
fi

TRABAJO=$(mktemp -d)
chmod 755 "$TRABAJO" # www-data tiene que poder atravesarlo

limpiar() {
    local pid
    for pid in "$TRABAJO/run/nginx.pid" "$TRABAJO/run/fpm.pid"; do
        if [ -f "$pid" ]; then
            kill "$(cat "$pid")" 2>/dev/null || true
        fi
    done
    if [ -n "${SMOKE_KEEP:-}" ]; then
        echo "smoke-test: se conserva $TRABAJO"
    else
        sleep 1
        rm -rf "${TRABAJO:?}"
    fi
}
trap limpiar EXIT

como_web() { # ejecuta un comando como el usuario de PHP-FPM
    if [ "$(id -u)" -eq 0 ]; then
        runuser -u "$WEB_USER" -- "$@"
    else
        "$@"
    fi
}

mkdir -p "$TRABAJO/run" "$TRABAJO/log/app" "$TRABAJO/log/nginx-urr" "$TRABAJO/tmp" "$TRABAJO/nginx/sites" "$TRABAJO/nginx/tmp" "$TRABAJO/datos"

echo "== Paso 1: el código, instalado como en producción"
APP="$TRABAJO/app"
mkdir -p "$APP"
tar -C "$PROYECTO" --exclude=./vendor --exclude=./var --exclude=./.env --exclude=./tests --exclude=./.phpunit.cache -cf - . | tar -C "$APP" -xf -
(cd "$APP" && composer install --no-dev --optimize-autoloader --no-interaction --quiet)

echo "== Pasos 2 y 3: directorios, permisos y .env"
install -d -m 750 "$TRABAJO/datos"
TOKEN=$(openssl rand -hex 32)
cp "$APP/.env.example" "$APP/.env"
if [ -n "${SMOKE_PG_DSN:-}" ]; then
    DSN_GENEALOGIA=$SMOKE_PG_DSN
    USER_GENEALOGIA=${SMOKE_PG_USER:-}
    CLAVE_GENEALOGIA=${SMOKE_PG_PASSWORD:-}
else
    DSN_GENEALOGIA="sqlite:$TRABAJO/datos/genealogia.sqlite"
    USER_GENEALOGIA=
    CLAVE_GENEALOGIA=
fi
sed -i \
    -e "s|^OWNER_TOKEN=.*|OWNER_TOKEN=$TOKEN|" \
    -e "s|^POS_DOMAINS=.*|POS_DOMAINS=$POS|" \
    -e "s|^DB_DSN_GENEALOGY=.*|DB_DSN_GENEALOGY=$DSN_GENEALOGIA|" \
    -e "s|^DB_USER_GENEALOGY=.*|DB_USER_GENEALOGY=$USER_GENEALOGIA|" \
    -e "s|^DB_PASSWORD_GENEALOGY=.*|DB_PASSWORD_GENEALOGY=$CLAVE_GENEALOGIA|" \
    -e "s|^DB_DSN_POS=.*|DB_DSN_POS=sqlite:$TRABAJO/datos/pos.sqlite|" \
    -e "s|^LOG_FILE=.*|LOG_FILE=$TRABAJO/log/app/app.log|" \
    "$APP/.env"
if [ "$(id -u)" -eq 0 ]; then
    chown -R "root:$WEB_USER" "$APP/.env"
    chmod 640 "$APP/.env"
else
    chmod 600 "$APP/.env"
fi
chown -R "$WEB_USER" "$TRABAJO/datos" "$TRABAJO/log/app" "$TRABAJO/tmp" 2>/dev/null || true
chmod -R go+rX "$APP" "$TRABAJO/run" 2>/dev/null || true
chmod 640 "$APP/.env" 2>/dev/null || true

echo "== Paso 4: migraciones, como el usuario de PHP-FPM"
(cd "$APP" && como_web php bin/migrate.php genealogy && como_web php bin/migrate.php pos)

echo "== Paso 5: PHP-FPM y nginx, con el ejemplo del repositorio"
INI_EXTRA=()
if [ -n "${SMOKE_PHP_INI:-}" ]; then
    INI_EXTRA=(-c "$SMOKE_PHP_INI")
fi
POOL_USUARIO=
NGINX_USUARIO=
if [ "$(id -u)" -eq 0 ]; then
    # Como root hay que decir con qué usuario trabajan los dos; si no, PHP-FPM se niega a arrancar.
    POOL_USUARIO="user = $WEB_USER
group = $WEB_USER
listen.owner = $WEB_USER
listen.group = $WEB_USER"
    NGINX_USUARIO="user $WEB_USER;"
fi
cat > "$TRABAJO/fpm.conf" <<EOF
[global]
pid = $TRABAJO/run/fpm.pid
error_log = $TRABAJO/log/fpm.log
daemonize = yes
[www]
$POOL_USUARIO
listen = $TRABAJO/run/php-fpm.sock
pm = static
pm.max_children = 2
catch_workers_output = yes
env[TMPDIR] = $TRABAJO/tmp
EOF
"$FPM" ${INI_EXTRA[@]+"${INI_EXTRA[@]}"} -y "$TRABAJO/fpm.conf"

ENLACES=(snippets fastcgi_params fastcgi.conf mime.types)
for enlace in "${ENLACES[@]}"; do
    [ -e "/etc/nginx/$enlace" ] || falta "/etc/nginx/$enlace (el paquete nginx de Debian o Ubuntu)"
    ln -sfn "/etc/nginx/$enlace" "$TRABAJO/nginx/$enlace"
done

openssl req -x509 -newkey rsa:2048 -nodes -days 2 -keyout "$TRABAJO/nginx/key.pem" -out "$TRABAJO/nginx/cert.pem" -subj "/CN=$GENEALOGIA" 2>/dev/null

cat > "$TRABAJO/nginx/nginx.conf" <<EOF
$NGINX_USUARIO
worker_processes 1;
pid $TRABAJO/run/nginx.pid;
error_log $TRABAJO/log/nginx-main.log;
events { worker_connections 64; }
http {
    include mime.types;
    client_body_temp_path $TRABAJO/nginx/tmp/body;
    fastcgi_temp_path $TRABAJO/nginx/tmp/fcgi;
    proxy_temp_path $TRABAJO/nginx/tmp/proxy;
    uwsgi_temp_path $TRABAJO/nginx/tmp/uwsgi;
    scgi_temp_path $TRABAJO/nginx/tmp/scgi;
    access_log off;
    include $TRABAJO/nginx/sites/*.conf;
}
EOF

# El ejemplo tal cual, con solo lo que cambia de un servidor a otro: puerto, raíz, socket y carpeta de
# logs. Después, lo que agrega certbot al activar HTTPS: el listen ssl y el certificado.
sed \
    -e "s|listen 80;|listen $PORT;\n    listen $TLS_PORT ssl;\n    ssl_certificate $TRABAJO/nginx/cert.pem;\n    ssl_certificate_key $TRABAJO/nginx/key.pem;|" \
    -e '/listen \[::\]:80;/d' \
    -e "s|/var/www/url-routing/public|$APP/public|" \
    -e "s|/run/php/php[0-9.]*-fpm.sock|$TRABAJO/run/php-fpm.sock|" \
    -e "s|/var/log/nginx/url-routing/|$TRABAJO/log/nginx-urr/|" \
    "$AQUI/nginx.conf.example" > "$TRABAJO/nginx/sites/url-routing.conf"

"$NGINX" -t -e "$TRABAJO/log/nginx-main.log" -c "$TRABAJO/nginx/nginx.conf"
"$NGINX" -e "$TRABAJO/log/nginx-main.log" -c "$TRABAJO/nginx/nginx.conf"

# --- comprobaciones ---------------------------------------------------------------------------------

TOTAL=0
FALLOS=0
HTTP="http://127.0.0.1:$PORT"
HTTPS="https://127.0.0.1:$TLS_PORT"
JAR="$TRABAJO/cookies.txt"

ok() { # ok <descripción> <esperado> <obtenido>
    TOTAL=$((TOTAL + 1))
    if [ "$2" = "$3" ]; then
        printf '  ok     %s\n' "$1"
    else
        printf '  FALLA  %s: esperado [%s], obtenido [%s]\n' "$1" "$2" "$3"
        FALLOS=$((FALLOS + 1))
    fi
}

contiene() { # contiene <descripción> <texto buscado> <texto>
    TOTAL=$((TOTAL + 1))
    if printf '%s' "$3" | grep -q -i -F -- "$2"; then
        printf '  ok     %s\n' "$1"
    else
        printf '  FALLA  %s: no aparece [%s] en [%s]\n' "$1" "$2" "$(printf '%s' "$3" | head -c 200)"
        FALLOS=$((FALLOS + 1))
    fi
}

no_contiene() { # no_contiene <descripción> <texto> <texto donde no debe estar>
    TOTAL=$((TOTAL + 1))
    if printf '%s' "$3" | grep -q -i -F -- "$2"; then
        printf '  FALLA  %s: aparece [%s]\n' "$1" "$2"
        FALLOS=$((FALLOS + 1))
    else
        printf '  ok     %s\n' "$1"
    fi
}

codigo() { # codigo <host> <ruta> [opciones de curl]: el código HTTP
    local host=$1 ruta=$2
    shift 2
    curl -s -k -o /dev/null -w '%{http_code}' -H "Host: $host" "$@" "$ruta" || true
}

cuerpo() { # cuerpo <host> <ruta> [opciones de curl]
    local host=$1 ruta=$2
    shift 2
    curl -s -k -H "Host: $host" "$@" "$ruta" || true
}

cabeceras() { # cabeceras <host> <ruta> [opciones de curl]: solo las cabeceras
    local host=$1 ruta=$2
    shift 2
    curl -s -k -o /dev/null -D - -H "Host: $host" "$@" "$ruta" || true
}

titulo() { # el primer <h1> de una página
    grep -o -m1 '<h1[^>]*>[^<]*' | sed 's/<h1[^>]*>//; s/[[:space:]]*$//'
}

reiniciar_limitador() {
    rm -f "$TRABAJO"/tmp/rate_limit_*
}

echo "== Paso 6: comprobar que quedó bien (genealogía)"
H=$GENEALOGIA
reiniciar_limitador
contiene "/health responde ok" '"status":"ok"' "$(cuerpo "$H" "$HTTP/health")"
ok "/ responde 200" 200 "$(codigo "$H" "$HTTP/")"
ok "/ready: la base responde y las migraciones están aplicadas" 200 "$(codigo "$H" "$HTTP/ready")"
contiene "/ready dice qué comprobó" '"migrations":"applied"' "$(cuerpo "$H" "$HTTP/ready")"
no_contiene "/ready no abre sesión" 'set-cookie' "$(cabeceras "$H" "$HTTP/ready")"
ok "/0/ sin clave pide la clave" 401 "$(codigo "$H" "$HTTP/0/")"
ok "/0/ con la clave equivocada" 401 "$(codigo "$H" "$HTTP/0/" -u "x:esta-no-es-la-clave")"
ok "/0/ con la clave y sin el usuario 1 no existe" 404 "$(codigo "$H" "$HTTP/0/" -u "x:$TOKEN")"
contiene "/0/ dice qué falta" 'No existe el usuario 1' "$(cuerpo "$H" "$HTTP/0/" -u "x:$TOKEN")"

echo "   (con la demostración, que trae el usuario 1)"
(cd "$APP" && como_web php bin/migrate.php genealogy --seed >/dev/null)
ok "/0/ con la clave" 200 "$(codigo "$H" "$HTTP/0/" -u "x:$TOKEN")"
ok "/0/ con Bearer" 200 "$(codigo "$H" "$HTTP/0/" -H "Authorization: Bearer $TOKEN")"
CAB=$(cabeceras "$H" "$HTTP/0/" -u "x:$TOKEN")
contiene "Cache-Control: private, no-store" 'cache-control: private, no-store' "$CAB"
contiene "Vary: Authorization" 'authorization' "$(printf '%s' "$CAB" | grep -i '^vary')"

echo "== Lo que no debe servirse"
ok "/.env" 403 "$(codigo "$H" "$HTTP/.env")"
ok "/.git/config" 403 "$(codigo "$H" "$HTTP/.git/config")"
ok "/bin/migrate.php" 404 "$(codigo "$H" "$HTTP/bin/migrate.php")"
ok "/composer.json" 404 "$(codigo "$H" "$HTTP/composer.json")"
ok "/vendor/autoload.php" 404 "$(codigo "$H" "$HTTP/vendor/autoload.php")"
ok "/index.php directo" 404 "$(codigo "$H" "$HTTP/index.php")"
ok "/otra-cosa.php" 404 "$(codigo "$H" "$HTTP/otra-cosa.php")"
ok "la semilla" 404 "$(codigo "$H" "$HTTP/database/genealogy/seeds/001_demo.sql")"

echo "== HTTPS"
ok "/health por HTTPS" 200 "$(codigo "$H" "$HTTPS/health")"
CAB=$(cabeceras "$H" "$HTTPS/0/" -u "x:$TOKEN")
contiene "la cookie de sesión es secure" 'secure' "$(printf '%s' "$CAB" | grep -i '^set-cookie')"
contiene "la cookie de sesión es HttpOnly" 'httponly' "$(printf '%s' "$CAB" | grep -i '^set-cookie')"
contiene "Strict-Transport-Security" 'strict-transport-security' "$CAB"
no_contiene "quien solo mira no recibe cookie" 'set-cookie' "$(cabeceras "$H" "$HTTPS/")"
no_contiene "ni siquiera la página de una persona" 'set-cookie' "$(cabeceras "$H" "$HTTPS/6128473101/")"
contiene "una página con formulario sí abre sesión (secure)" 'secure' "$(cabeceras "$H" "$HTTPS/r/6128473105/" | grep -i '^set-cookie')"
no_contiene "por HTTP plano la cookie no es secure" 'secure' "$(cabeceras "$H" "$HTTP/0/" -u "x:$TOKEN" | grep -i '^set-cookie')"

echo "== El sitio de cada dominio"
ok "genealogía: idioma por omisión" '<html lang="es">' "$(cuerpo "$H" "$HTTP/" | grep -o -m1 '<html[^>]*>')"
ok "genealogía: subdominio eng." '<html lang="en">' "$(cuerpo "eng.$GENEALOGIA" "$HTTP/" | grep -o -m1 '<html[^>]*>')"
ok "POS: /health" 200 "$(codigo "$POS" "$HTTP/health")"
ok "POS: /" 200 "$(codigo "$POS" "$HTTP/")"
ok "POS: /ready con sus migraciones" 200 "$(codigo "$POS" "$HTTP/ready")"
ok "POS: /0/ sin el usuario 1" 404 "$(codigo "$POS" "$HTTP/0/" -u "x:$TOKEN")"
# El INSERT del paso 4, con PDO en vez del cliente sqlite3 (que no siempre está instalado).
como_web php -r '
    $p = new PDO("sqlite:" . $argv[1]);
    $p->exec("INSERT INTO usuarios (id, email, nombre) VALUES (1, \x27tu@correo.com\x27, \x27Tu nombre\x27)");
' "$TRABAJO/datos/pos.sqlite"
ok "POS: /0/ con el usuario 1 creado como el usuario de PHP-FPM" 200 "$(codigo "$POS" "$HTTP/0/" -u "x:$TOKEN")"

echo "== /ready detecta un despliegue que olvidó migrar"
# El código nuevo trae una migración y todavía nadie corrió bin/migrate.php.
mkdir -p "$APP/database/pos/migrations"
echo 'CREATE TABLE prueba_de_humo (x INTEGER);' > "$APP/database/pos/migrations/999_prueba_de_humo.sql"
chmod a+r "$APP/database/pos/migrations/999_prueba_de_humo.sql"
ok "POS con una migración sin aplicar" 503 "$(codigo "$POS" "$HTTP/ready")"
contiene "dice cuál es el problema, y no más" '"migrations":"pending"' "$(cuerpo "$POS" "$HTTP/ready")"
no_contiene "no nombra la migración" '999_prueba' "$(cuerpo "$POS" "$HTTP/ready")"
contiene "la razón queda en el log" '999_prueba_de_humo' "$(cat "$TRABAJO/log/app/app.log")"
ok "las páginas siguen sirviéndose (/ready es una sonda, no un candado)" 200 "$(codigo "$POS" "$HTTP/")"
(cd "$APP" && como_web php bin/migrate.php pos | grep -q '999_prueba_de_humo')
ok "tras migrar vuelve a estar listo" 200 "$(codigo "$POS" "$HTTP/ready")"
ok "genealogía no se enteró" 200 "$(codigo "$H" "$HTTP/ready")"

echo "== El propietario marca a una persona como viva (cookies, CSRF y POST por nginx y HTTPS)"
PERSONA=6128473101
reiniciar_limitador
ok "el visitante ve el nombre" "José García Álvarez" "$(cuerpo "$H" "$HTTPS/$PERSONA/" | titulo)"
ok "una persona viva se muestra como tal" "Persona viva" "$(cuerpo "$H" "$HTTPS/6128473105/" | titulo)"
ok "quien solo está en una colección privada" "Persona privada" "$(cuerpo "$H" "$HTTPS/6128473110/" | titulo)"
rm -f "$JAR"
PAGINA=$(cuerpo "$H" "$HTTPS/$PERSONA/5/" -c "$JAR" -b "$JAR" -u "x:$TOKEN")
CSRF=$(printf '%s' "$PAGINA" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"//')
ok "la página del propietario trae un token CSRF" 1 "$([ -n "$CSRF" ] && echo 1 || echo 0)"
ok "sin el token se rechaza" 403 "$(codigo "$H" "$HTTPS/$PERSONA/5/" -b "$JAR" -c "$JAR" -d 'viva=si')"
ok "con el token se acepta" 303 "$(codigo "$H" "$HTTPS/$PERSONA/5/" -b "$JAR" -c "$JAR" -d "csrf_token=$CSRF&viva=si")"
ok "el visitante ya no ve el nombre" "Persona viva" "$(cuerpo "$H" "$HTTPS/$PERSONA/" | titulo)"
PAGINA=$(cuerpo "$H" "$HTTPS/$PERSONA/5/" -b "$JAR" -c "$JAR")
CSRF=$(printf '%s' "$PAGINA" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"//')
ok "deducir de nuevo por las fechas" 303 "$(codigo "$H" "$HTTPS/$PERSONA/5/" -b "$JAR" -c "$JAR" -d "csrf_token=$CSRF&viva=deducir")"
ok "el visitante vuelve a ver el nombre" "José García Álvarez" "$(cuerpo "$H" "$HTTPS/$PERSONA/" | titulo)"
ok "un visitante no puede usar el formulario" 401 "$(codigo "$H" "$HTTPS/$PERSONA/5/" -d "csrf_token=$CSRF&viva=si")"

echo "== Pedir el retiro de una página y vigilarlo con cron (formulario, base y bin/pedidos-retiro.php)"
cron_retiros() { # cron_retiros [opciones]: deja la salida en CRON_SALIDA y el código de salida en CRON_CODIGO
    CRON_CODIGO=0
    CRON_SALIDA=$( (cd "$APP" && como_web php bin/pedidos-retiro.php "$@") 2>&1 ) || CRON_CODIGO=$?
}
PEDIDA=6128473105
CORREO="quien-pidio@example.com"
reiniciar_limitador
cron_retiros
ok "sin pedidos, cron no imprime nada" "" "$CRON_SALIDA"
ok "sin pedidos, sale con 0" 0 "$CRON_CODIGO"
rm -f "$JAR"
PAGINA=$(cuerpo "$H" "$HTTPS/r/$PEDIDA/" -c "$JAR" -b "$JAR")
CSRF=$(printf '%s' "$PAGINA" | grep -o 'name="csrf_token" value="[^"]*"' | head -1 | sed 's/.*value="//; s/"//')
ok "el formulario de retiro lo puede abrir cualquiera" 1 "$([ -n "$CSRF" ] && echo 1 || echo 0)"
ok "sin el token se rechaza" 403 "$(codigo "$H" "$HTTPS/r/$PEDIDA/" -b "$JAR" -c "$JAR" -d 'motivo=me_nombra')"
ok "el pedido se recibe" 303 "$(codigo "$H" "$HTTPS/r/$PEDIDA/" -b "$JAR" -c "$JAR" \
    --data-urlencode "csrf_token=$CSRF" --data-urlencode 'motivo=me_nombra' \
    --data-urlencode 'detalle=Es falso: vivo en Calle Falsa 123.' --data-urlencode "contacto=$CORREO")"
ok "recibir un pedido no oculta la página" 200 "$(codigo "$H" "$HTTPS/$PEDIDA/")"
cron_retiros
ok "cron avisa del pedido" 0 "$CRON_CODIGO"
contiene "dice cuántos esperan" '1 pedido de retiro espera respuesta' "$CRON_SALIDA"
contiene "dice de qué página" "persona $PEDIDA" "$CRON_SALIDA"
no_contiene "no imprime el correo de quien lo pidió" "$CORREO" "$CRON_SALIDA"
no_contiene "ni lo que escribió" 'Calle Falsa' "$CRON_SALIDA"
# Para no esperar 24 horas: el pedido llegó hace 30.
(cd "$APP" && como_web php -r '
    require "vendor/autoload.php";
    App\Support\Config::load();
    App\Support\Database::forSite("genealogy")->execute("UPDATE pedidos_retiro SET creado_en = ?", [date("Y-m-d H:i:s", time() - 30 * 3600)]);
')
cron_retiros
ok "vencido: cron sale con 1" 1 "$CRON_CODIGO"
contiene "y lo dice" 'VENCIDO' "$CRON_SALIDA"
ok "avisar no oculta la página" 200 "$(codigo "$H" "$HTTPS/$PEDIDA/")"
cron_retiros --ocultar
ok "con --ocultar sale con 0" 0 "$CRON_CODIGO"
contiene "dice que ocultó" 'oculto automáticamente' "$CRON_SALIDA"
ok "el visitante ya recibe 410" 410 "$(codigo "$H" "$HTTPS/$PEDIDA/")"
ok "el propietario sigue viéndola" 200 "$(codigo "$H" "$HTTPS/$PEDIDA/" -u "x:$TOKEN")"
cron_retiros --ocultar
ok "ya no queda nada pendiente: silencio" "" "$CRON_SALIDA"

echo "== Recargas (lo que hace systemctl reload)"
kill -USR2 "$(cat "$TRABAJO/run/fpm.pid")"
sleep 2
ok "tras recargar PHP-FPM" 200 "$(codigo "$H" "$HTTP/ready")"
"$NGINX" -e "$TRABAJO/log/nginx-main.log" -c "$TRABAJO/nginx/nginx.conf" -s reload
sleep 1
ok "tras recargar nginx" 200 "$(codigo "$H" "$HTTP/ready")"

echo "== El limitador (100 peticiones por minuto y por IP)"
reiniciar_limitador
for _ in $(seq 1 100); do
    curl -s -k -o /dev/null -H "Host: $H" "$HTTP/" || true
done
ok "la petición 101 se rechaza" 429 "$(codigo "$H" "$HTTP/")"
RETRY=$(cabeceras "$H" "$HTTP/" | tr -d '\r' | awk -F': ' 'tolower($1) == "retry-after" { print $2 }')
ok "Retry-After es un número de segundos de 1 a 60" 1 "$([[ "$RETRY" =~ ^[0-9]+$ ]] && [ "$RETRY" -ge 1 ] && [ "$RETRY" -le 60 ] && echo 1 || echo 0)"
ok "/ready también cuenta" 429 "$(codigo "$H" "$HTTP/ready")"
ok "/health no cuenta" 200 "$(codigo "$H" "$HTTP/health")"
reiniciar_limitador

echo
if [ "$FALLOS" -eq 0 ]; then
    echo "smoke-test: $TOTAL comprobaciones, todas bien (nginx $("$NGINX" -v 2>&1 | sed 's|.*/||') y PHP-FPM $("$FPM" ${INI_EXTRA[@]+"${INI_EXTRA[@]}"} -v | head -1 | cut -d' ' -f2))"
else
    echo "smoke-test: $FALLOS de $TOTAL comprobaciones fallaron" >&2
    echo "--- app.log" >&2
    tail -n 20 "$TRABAJO/log/app/app.log" >&2 || true
    echo "--- fpm.log" >&2
    tail -n 20 "$TRABAJO/log/fpm.log" >&2 || true
    echo "--- nginx" >&2
    tail -n 20 "$TRABAJO/log/nginx-urr/error.log" "$TRABAJO/log/nginx-main.log" >&2 || true
    exit 1
fi
