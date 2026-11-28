#!/bin/bash
# Despliega el codigo de una app (06_A PHP o 06_B Python) desde un repositorio git, como el USUARIO de la app.
# Uso: ./10_C-deploy-app.sh <app> [--repo URL] [--branch RAMA] [--key ARCHIVO] [--dry-run]
#   --repo    URL del repositorio (https://host/dueno/repo.git, git@host:dueno/repo.git o ssh://...). Solo la primera vez:
#             queda en el git de la app y las siguientes veces basta ./10_C-deploy-app.sh <app> (equivale a un "git pull").
#   --branch  rama (por defecto la que ya usa la app, o "main" la primera vez)
#   --key     llave privada de SOLO LECTURA ("deploy key") para un repo privado: se COPIA a una carpeta solo de la app
#             ($DEPLOY_KEYS_DIR/<app>, modo 600) y el archivo original no se toca ni se borra. Nunca se imprime.
#   --dry-run muestra que se haria sin tocar nada
# Despues instala dependencias (pip -r requirements.txt o composer install) y reinicia el servicio (Python).
# No usa tokens en la URL (quedarian en .git/config): para un repo privado, usa una deploy key por ssh.
# Rutas reemplazables para pruebas: WWW_DIR, NGINX_DIR, SYSTEMD_DIR, PHP_POOL_DIR, DEPLOY_KEYS_DIR, REMOVED_DIR.
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "$SCRIPT_DIR/lib.sh"

WWW_DIR=${WWW_DIR:-/var/www}
SYSTEMD_DIR=${SYSTEMD_DIR:-/etc/systemd/system}
BACKUP_DIR=${BACKUP_DIR:-/var/backups/vps-setup/deploy}

APP="" REPO="" BRANCH="" KEY="" DRY_RUN=0
while [ $# -gt 0 ]; do
    case "$1" in
        --repo|--branch|--key)
            [ $# -ge 2 ] || { echo "Falta el valor de $1" >&2; exit 2; }
            case "$1" in --repo) REPO=$2 ;; --branch) BRANCH=$2 ;; --key) KEY=$2 ;; esac
            shift 2 ;;
        --dry-run) DRY_RUN=1; shift ;;
        -h|--help) sed -n '2,13p' "$0"; exit 0 ;;
        -*) echo "Opcion desconocida: $1 (usa --help)" >&2; exit 2 ;;
        *) [ -z "$APP" ] || { echo "Solo se admite una app (sobra: $1)" >&2; exit 2; }
           APP=$1; shift ;;
    esac
done
require_arg "$APP" "$0 <app> [--repo URL] [--branch RAMA] [--key ARCHIVO]   (ej. $0 tienda --repo git@github.com:yo/tienda.git --key ~/deploy_key)"
require_valid name "$APP" "El nombre de la app"
[ -z "$REPO" ] || require_valid repo_url "$REPO" "--repo"
[ -z "$BRANCH" ] || require_valid branch "$BRANCH" "--branch"

APP_USER=$(app_user_name "$APP")
APP_PATH="$WWW_DIR/$APP"
KEY_DIR="$DEPLOY_KEYS_DIR/$APP"

# --- Que tipo de app es: lo dice la marca que 06_A/06_B dejan en su vhost ---
TYPE=""
for v in "$NGINX_DIR"/sites-available/*; do
    [ -f "$v" ] || continue
    case "$(sed -n '1p' "$v")" in
        "# vps-setup: 06_A-php $APP") TYPE=php; break ;;
        "# vps-setup: 06_B-python $APP") TYPE=python; break ;;
    esac
done
if [ -z "$TYPE" ]; then
    echo "ERROR: '$APP' no es una app PHP ni Python de estos scripts (no hay un vhost con su marca)." >&2
    echo "Crea la app primero: ./06_A-setup-php-app.sh $APP <dominio>  o  ./06_B-setup-python-app.sh $APP <dominio>" >&2
    exit 1
fi
id "$APP_USER" > /dev/null 2>&1 || { echo "ERROR: no existe el usuario $APP_USER (re-corre el script de la app: 06_A o 06_B)." >&2; exit 1; }
[ -d "$APP_PATH" ] || { echo "ERROR: no existe $APP_PATH." >&2; exit 1; }
check_dependency git "sudo apt-get install -y git"

print_header "10_C" "Desplegar $APP ($TYPE) desde git"

if [ -n "$KEY" ]; then
    [ -f "$KEY" ] || { echo "ERROR: --key: no existe el archivo $KEY." >&2; exit 2; }
    grep -q 'PRIVATE KEY' "$KEY" || { echo "ERROR: --key: $KEY no parece una llave PRIVADA (¿pasaste la .pub?)." >&2; exit 2; }
fi

# Repositorio y rama: los de ahora, o los que la app ya tiene guardados en su git.
GIT=(sudo -u "$APP_USER" env -C "$APP_PATH" "HOME=$APP_PATH" GIT_TERMINAL_PROMPT=0 git)
HAS_GIT=0; [ -d "$APP_PATH/.git" ] && HAS_GIT=1
if [ "$HAS_GIT" -eq 1 ]; then
    CUR_REPO=$("${GIT[@]}" config --get remote.origin.url || true)
    # La rama guardada solo vale si ya hubo un despliegue (un git sin commits tiene la rama por defecto, "master", que no es nuestra).
    if "${GIT[@]}" rev-parse -q --verify HEAD > /dev/null 2>&1; then CUR_BRANCH=$("${GIT[@]}" symbolic-ref --short -q HEAD || true); fi
fi
REPO=${REPO:-${CUR_REPO:-}}
BRANCH=${BRANCH:-${CUR_BRANCH:-main}}
if [ -z "$REPO" ]; then
    echo "ERROR: falta --repo (la app todavia no tiene un repositorio guardado)." >&2
    exit 2
fi
require_valid repo_url "$REPO" "El repositorio guardado"
require_valid branch "$BRANCH" "La rama"

USES_SSH=0; case "$REPO" in https://*) ;; /*) ;; *) USES_SSH=1 ;; esac
[ -z "$KEY" ] || [ "$USES_SSH" -eq 1 ] || echo "AVISO: --key solo aplica a repositorios por ssh; $REPO es https y no se usa la llave." >&2
[ "$USES_SSH" -eq 0 ] || check_dependency ssh "sudo apt-get install -y openssh-client"
if [ "$USES_SSH" -eq 1 ] && [ -z "$KEY" ] && ! sudo test -f "$KEY_DIR/key"; then
    echo "ERROR: $REPO es por ssh y la app no tiene llave de despliegue: pasa --key <archivo> (una deploy key de solo lectura)." >&2
    exit 2
fi

echo "App: $APP ($TYPE)   Usuario: $APP_USER   Carpeta: $APP_PATH"
echo "Repositorio: $REPO   Rama: $BRANCH${KEY:+   Llave: se copiara a $KEY_DIR (modo 600)}"
if [ "$DRY_RUN" -eq 1 ]; then echo "[dry-run] no se toco nada"; exit 0; fi

# --- Llave de despliegue: copia solo de la app. Se guarda con su known_hosts (TOFU: confia en la huella la 1a vez). ---
GIT_SSH=ssh
if [ "$USES_SSH" -eq 1 ]; then
    if [ -n "$KEY" ]; then
        sudo mkdir -p "$KEY_DIR"
        sudo cp "$KEY" "$KEY_DIR/key"
        sudo chown -R "$APP_USER:$APP_USER" "$KEY_DIR"
        sudo chmod 700 "$KEY_DIR"
        sudo chmod 600 "$KEY_DIR/key"
    fi
    GIT_SSH="ssh -i $KEY_DIR/key -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=$KEY_DIR/known_hosts"
fi
GIT=(sudo -u "$APP_USER" env -C "$APP_PATH" "HOME=$APP_PATH" GIT_TERMINAL_PROMPT=0 "GIT_SSH_COMMAND=$GIT_SSH" git)

# --- Primera vez: repositorio nuevo en la carpeta de la app; despues, el que ya tiene (sin pisar cambios locales) ---
if [ "$HAS_GIT" -eq 0 ]; then
    "${GIT[@]}" init -q
    "${GIT[@]}" remote add origin "$REPO"
else
    # (si un intento anterior fallo antes de bajar nada, el git esta creado pero sin commits: sigue siendo "la primera vez")
    "${GIT[@]}" remote set-url origin "$REPO" 2> /dev/null || "${GIT[@]}" remote add origin "$REPO"
    if [ -n "$("${GIT[@]}" status --porcelain --untracked-files=no)" ]; then
        echo "ERROR: hay cambios sin guardar en los archivos del repositorio de $APP_PATH; no se pisan." >&2
        echo "Revisalos: sudo -u $APP_USER git -C $APP_PATH status   (guardalos con commit o descartalos tu)." >&2
        exit 1
    fi
fi
BEFORE=$("${GIT[@]}" rev-parse --short -q HEAD 2> /dev/null || echo "-")

echo "Descargando $BRANCH..."
"${GIT[@]}" fetch --depth 1 origin "$BRANCH"
# Un .env del repo pisaria el de la app (con su DATABASE_URL): se niega antes de tocar nada.
if "${GIT[@]}" ls-tree -r --name-only FETCH_HEAD | grep -qx '\.env'; then
    echo "ERROR: el repositorio trae un archivo .env: no se despliega para no pisar el de la app (con su DATABASE_URL)." >&2
    echo "Quitalo del repo (y ponlo en .gitignore) y vuelve a correr este script." >&2
    exit 1
fi
# La pagina/app de ejemplo de 06_A/06_B estorbaria al primer checkout: se aparta (no se borra), y solo cuando ya
# se sabe que el despliegue va a seguir.
if [ "$BEFORE" = "-" ]; then
    sample=""
    case "$TYPE" in
        python) [ -f "$APP_PATH/app.py" ] && grep -q 'Hello from Python App' "$APP_PATH/app.py" && sample="$APP_PATH/app.py" ;;
        php)    for f in "$APP_PATH/index.php" "$APP_PATH"/*/index.php; do
                    [ -f "$f" ] && grep -q 'PHP-FPM + Nginx funcionando' "$f" && { sample=$f; break; }
                done ;;
    esac
    if [ -n "$sample" ]; then
        dest="$BACKUP_DIR/$APP-$(date +%Y%m%d-%H%M%S)"
        sudo mkdir -p "$dest"; sudo chmod 700 "$BACKUP_DIR" "$dest"
        sudo mv "$sample" "$dest/"
        echo "La pagina de ejemplo se aparto en $dest (no se borro)."
    fi
fi

"${GIT[@]}" checkout -q -B "$BRANCH" FETCH_HEAD
AFTER=$("${GIT[@]}" rev-parse --short HEAD)
echo "Commit: $BEFORE -> $AFTER"

# --- Dependencias, como el usuario de la app ---
# Si fallan, el codigo ya esta cambiado en disco pero el servicio NO se reinicia (sigue con el anterior): se dice y se
# da el comando para volver, en vez de cortar sin explicar.
deps_failed() {
    echo "ERROR: fallo la instalacion de dependencias. El codigo de $APP_PATH ya es $AFTER, pero NO se reinicio nada." >&2
    [ "$BEFORE" = "-" ] || echo "Para volver al anterior: sudo -u $APP_USER git -C $APP_PATH checkout -B $BRANCH $BEFORE" >&2
    exit 1
}
case "$TYPE" in
    python)
        if [ -f "$APP_PATH/requirements.txt" ]; then
            echo "Instalando requirements.txt..."
            sudo -u "$APP_USER" env -C "$APP_PATH" "HOME=$APP_PATH" "$APP_PATH/venv/bin/pip" install --no-cache-dir -r requirements.txt || deps_failed
        fi ;;
    php)
        if [ -f "$APP_PATH/composer.json" ]; then
            if command -v composer > /dev/null 2>&1; then
                echo "composer install..."
                sudo -u "$APP_USER" env -C "$APP_PATH" "HOME=$APP_PATH" COMPOSER_NO_INTERACTION=1 composer install --no-dev --optimize-autoloader || deps_failed
            else
                echo "AVISO: el repo trae composer.json pero composer no esta instalado: instala las dependencias a mano." >&2
            fi
        fi ;;
esac

# --- Python: reiniciar el servicio y comprobar que queda arriba ---
if [ "$TYPE" = python ]; then
    service_enable_restart "$APP"
    sleep "${DEPLOY_WAIT:-3}"
    if ! systemctl is-active --quiet "$APP"; then
        echo "ERROR: $APP no quedo activo tras el despliegue. Ultimas lineas del servicio:" >&2
        sudo journalctl -u "$APP" -n 15 --no-pager >&2 || true
        echo "El codigo desplegado es $AFTER; para volver al anterior (${BEFORE}): sudo -u $APP_USER git -C $APP_PATH checkout -B $BRANCH $BEFORE" >&2
        exit 1
    fi
fi

echo ""
echo "✓ $APP desplegada: $REPO ($BRANCH) en $AFTER"
[ "$BEFORE" = "-" ] || echo "Para volver al anterior: sudo -u $APP_USER git -C $APP_PATH checkout -B $BRANCH $BEFORE"
