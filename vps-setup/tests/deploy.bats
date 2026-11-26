#!/usr/bin/env bats
load helpers

# 10_C: despliegue por git. El "remoto" es un repositorio local real (DEPLOY_ALLOW_LOCAL=1): el fetch y el checkout
# son de verdad; sudo/systemctl/id se simulan (no hay usuario app-blog ni systemd aqui).
setup() {
    setup_stubs
    export WWW_DIR="$BATS_TEST_TMPDIR/www" NGINX_DIR="$BATS_TEST_TMPDIR/nginx" SYSTEMD_DIR="$BATS_TEST_TMPDIR/systemd" \
           DEPLOY_KEYS_DIR="$BATS_TEST_TMPDIR/keys" BACKUP_DIR="$BATS_TEST_TMPDIR/backup" \
           DEPLOY_ALLOW_LOCAL=1 DEPLOY_WAIT=0 \
           GIT_AUTHOR_NAME=t GIT_AUTHOR_EMAIL=t@t GIT_COMMITTER_NAME=t GIT_COMMITTER_EMAIL=t@t
    mkdir -p "$NGINX_DIR/sites-available" "$WWW_DIR"
    make_stub sudo 'echo "$*" >> "$SUDO_LOG"
if [ "$1" = -u ]; then shift 2; exec "$@"; fi
case "$1" in systemctl|chown|journalctl) exit 0 ;; esac
exec "$@"'
    make_stub id 'if [ "$1" = app-blog ] || [ "$1" = app-shop ]; then exit 0; else exec /usr/bin/id "$@"; fi'
    make_stub systemctl 'if [ "$1" = is-active ]; then exit "${ACTIVE_RC:-0}"; fi; exit 0'
    make_stub composer 'echo "composer $*" >> "$SUDO_LOG"'
    make_stub ssh 'exit 0'
    R="$BATS_TEST_TMPDIR/repo"
}

mkrepo() {   # mkrepo <archivo> <contenido>
    git init -q -b main "$R"
    (cd "$R" && mkdir -p "$(dirname "$1")" && echo "$2" > "$1" && git add -A && git commit -qm uno)
}
alta_python() {
    printf '# vps-setup: 06_B-python blog\nserver { }\n' > "$NGINX_DIR/sites-available/blog.ejemplo.com"
    mkdir -p "$WWW_DIR/blog/venv/bin"
    printf "from flask import Flask\n# Hello from Python App\n" > "$WWW_DIR/blog/app.py"
    echo 'DATABASE_URL=postgresql://x' > "$WWW_DIR/blog/.env"
    printf '#!/bin/bash\necho "pip $*" >> "$SUDO_LOG"\n' > "$WWW_DIR/blog/venv/bin/pip"; chmod +x "$WWW_DIR/blog/venv/bin/pip"
}
alta_php() {
    printf '# vps-setup: 06_A-php shop\nserver { }\n' > "$NGINX_DIR/sites-available/shop.ejemplo.com"
    mkdir -p "$WWW_DIR/shop/public"
    echo '<?php echo "PHP-FPM + Nginx funcionando correctamente.";' > "$WWW_DIR/shop/public/index.php"
}
deploy() { run bash "$VPS_DIR/10_C-deploy-app.sh" "$@"; }

@test "10_C primera vez (Python): aparta la pagina de ejemplo SIN borrarla, baja el codigo, instala requirements y reinicia el servicio" {
    alta_python
    mkrepo app.py 'print("MI APP")'; (cd "$R" && echo 'flask' > requirements.txt && git add -A && git commit -qm req)
    deploy blog --repo "$R"
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ "$(cat "$WWW_DIR/blog/app.py")" = 'print("MI APP")' ]
    grep -q 'Hello from Python App' "$BACKUP_DIR"/blog-*/app.py                  # la de ejemplo quedo guardada
    [ "$(cat "$WWW_DIR/blog/.env")" = 'DATABASE_URL=postgresql://x' ]            # el .env de la app, intacto
    [ -f "$WWW_DIR/blog/venv/bin/pip" ]
    grep -q 'pip install --no-cache-dir -r requirements.txt' "$SUDO_LOG"
    a=$(grep -n 'systemctl reset-failed blog' "$SUDO_LOG" | cut -d: -f1); b=$(grep -n 'systemctl restart blog' "$SUDO_LOG" | cut -d: -f1)
    [ -n "$a" ] && [ "$a" -lt "$b" ]
    [[ "$output" == *"desplegada"* ]]
}

@test "10_C: si el primer intento falla al bajar (repo inexistente), el reintento sigue contando como primera vez y aparta la pagina de ejemplo" {
    alta_python; mkrepo app.py 'MI APP'
    deploy blog --repo "$BATS_TEST_TMPDIR/no-existe.git"
    [ "$status" -ne 0 ]
    grep -q 'Hello from Python App' "$WWW_DIR/blog/app.py"                # no se toco nada
    deploy blog --repo "$R"
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ "$(cat "$WWW_DIR/blog/app.py")" = 'MI APP' ]
    grep -q 'Hello from Python App' "$BACKUP_DIR"/blog-*/app.py
}

@test "10_C re-correr sin --repo ni --branch actualiza (como un pull) y muestra el commit anterior y el nuevo" {
    alta_python; mkrepo app.py 'v1'
    deploy blog --repo "$R"; [ "$status" -eq 0 ]
    (cd "$R" && echo v2 > app.py && git commit -qam dos)
    deploy blog
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ "$(cat "$WWW_DIR/blog/app.py")" = v2 ]
    [[ "$output" == *"Commit: "*" -> "* ]] && [[ "$output" == *"Para volver al anterior"* ]]
}

@test "10_C: con cambios sin guardar en los archivos del repo NO pisa nada y lo explica" {
    alta_python; mkrepo app.py 'v1'
    deploy blog --repo "$R"; [ "$status" -eq 0 ]
    echo 'cambio local' > "$WWW_DIR/blog/app.py"
    (cd "$R" && echo v2 > app.py && git commit -qam dos)
    deploy blog
    [ "$status" -eq 1 ]
    [[ "$output" == *"cambios sin guardar"* ]]
    [ "$(cat "$WWW_DIR/blog/app.py")" = 'cambio local' ]
}

@test "10_C se niega si el repo trae un .env (pisaria el de la app con su DATABASE_URL) y no despliega nada" {
    alta_python; mkrepo .env 'DATABASE_URL=otra'
    deploy blog --repo "$R"
    [ "$status" -eq 1 ]
    [[ "$output" == *"trae un archivo .env"* ]]
    [ "$(cat "$WWW_DIR/blog/.env")" = 'DATABASE_URL=postgresql://x' ]
    grep -q 'Hello from Python App' "$WWW_DIR/blog/app.py"               # la pagina de ejemplo sigue donde estaba
    [ ! -d "$BACKUP_DIR" ]
    ! git -C "$WWW_DIR/blog" rev-parse -q --verify HEAD > /dev/null 2>&1   # no hubo checkout
}

@test "10_C Python: si el servicio no queda activo, falla, muestra el journal y dice como volver" {
    alta_python; mkrepo app.py 'v1'
    ACTIVE_RC=3 deploy blog --repo "$R"
    [ "$status" -eq 1 ]
    [[ "$output" == *"no quedo activo"* ]] && [[ "$output" == *"checkout -B main"* ]]
    grep -q 'journalctl -u blog' "$SUDO_LOG"
}

@test "10_C: si falla la instalacion de dependencias NO reinicia el servicio y dice como volver" {
    alta_python; mkrepo app.py v1
    (cd "$R" && echo flask > requirements.txt && git add -A && git commit -qm r)
    printf '#!/bin/bash\nexit 1\n' > "$WWW_DIR/blog/venv/bin/pip"
    deploy blog --repo "$R"
    [ "$status" -eq 1 ]
    [[ "$output" == *"fallo la instalacion de dependencias"* ]]
    ! grep -q 'systemctl restart' "$SUDO_LOG"
}

@test "10_C PHP: aparta la pagina de ejemplo (tambien dentro de public/) y corre composer install si hay composer.json" {
    alta_php
    mkrepo public/index.php '<?php echo "MI TIENDA";'; (cd "$R" && echo '{}' > composer.json && git add -A && git commit -qm c)
    deploy shop --repo "$R"
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ "$(cat "$WWW_DIR/shop/public/index.php")" = '<?php echo "MI TIENDA";' ]
    grep -q 'PHP-FPM + Nginx funcionando' "$BACKUP_DIR"/shop-*/index.php
    grep -q 'composer install --no-dev --optimize-autoloader' "$SUDO_LOG"
    ! grep -q 'systemctl restart' "$SUDO_LOG"                            # PHP no se reinicia
}

@test "10_C por ssh: copia la llave (600, solo de la app), usa known_hosts propio e IdentitiesOnly, y no la imprime ni toca el original" {
    alta_python
    printf -- '-----BEGIN OPENSSH PRIVATE KEY-----\nSECRETO-DE-LA-LLAVE\n-----END OPENSSH PRIVATE KEY-----\n' > "$BATS_TEST_TMPDIR/dk"
    make_stub git 'echo "GIT_SSH_COMMAND=$GIT_SSH_COMMAND | $*" >> "$BATS_TEST_TMPDIR/git.log"
case "$1" in rev-parse) echo abc1234 ;; esac'
    deploy blog --repo git@github.com:yo/blog.git --key "$BATS_TEST_TMPDIR/dk"
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
    [ "$(stat -c %a "$DEPLOY_KEYS_DIR/blog/key")" = 600 ] && [ "$(stat -c %a "$DEPLOY_KEYS_DIR/blog")" = 700 ]
    cmp "$BATS_TEST_TMPDIR/dk" "$DEPLOY_KEYS_DIR/blog/key"
    [ -f "$BATS_TEST_TMPDIR/dk" ]
    grep -q "IdentitiesOnly=yes" "$BATS_TEST_TMPDIR/git.log"
    grep -q -- "-i $DEPLOY_KEYS_DIR/blog/key" "$BATS_TEST_TMPDIR/git.log"
    grep -q "UserKnownHostsFile=$DEPLOY_KEYS_DIR/blog/known_hosts" "$BATS_TEST_TMPDIR/git.log"
    [[ "$output" != *SECRETO* ]] && ! grep -q SECRETO "$SUDO_LOG"
}

@test "10_C por ssh sin llave (ni guardada) se rechaza con 2; una llave .pub o inexistente tambien; --key con https avisa" {
    alta_python
    deploy blog --repo git@github.com:yo/blog.git
    [ "$status" -eq 2 ]; [[ "$output" == *"--key"* ]]
    echo 'ssh-ed25519 AAAA pub' > "$BATS_TEST_TMPDIR/k.pub"
    deploy blog --repo git@github.com:yo/blog.git --key "$BATS_TEST_TMPDIR/k.pub"; [ "$status" -eq 2 ]
    deploy blog --repo git@github.com:yo/blog.git --key /no/existe;                [ "$status" -eq 2 ]
    [ ! -e "$DEPLOY_KEYS_DIR" ]
    mkrepo app.py v1
    printf -- '-----BEGIN OPENSSH PRIVATE KEY-----\nx\n-----END OPENSSH PRIVATE KEY-----\n' > "$BATS_TEST_TMPDIR/dk"
    deploy blog --repo "$R" --key "$BATS_TEST_TMPDIR/dk"
    [ "$status" -eq 0 ]; [[ "$output" == *"solo aplica a repositorios por ssh"* ]]
}

@test "10_C rechaza URLs y ramas peligrosas, una app inexistente y la falta de --repo, sin tocar nada" {
    alta_python
    for u in '-oProxyCommand=x' 'https://user:clave@github.com/yo/r.git' 'ext::sh -c id' 'file:///etc' 'https://h/x y' 'https://h/a/../b'; do
        deploy blog --repo "$u"; [ "$status" -eq 2 ] || { echo "'$u' no se rechazo"; return 1; }
    done
    for b in '--x' 'a..b' '/abs' 'a b' 'x/'; do
        deploy blog --repo "$R" --branch "$b"; [ "$status" -eq 2 ] || { echo "rama '$b' no se rechazo"; return 1; }
    done
    deploy blog;          [ "$status" -eq 2 ]; [[ "$output" == *"falta --repo"* ]]
    deploy nada --repo "$R"; [ "$status" -eq 1 ]; [[ "$output" == *"no es una app"* ]]
    deploy; [ "$status" -eq 2 ]
    [ ! -d "$WWW_DIR/blog/.git" ] && [ ! -d "$BACKUP_DIR" ]
}

@test "10_C --dry-run no toca nada" {
    alta_python; mkrepo app.py v1
    deploy blog --repo "$R" --dry-run
    [ "$status" -eq 0 ]; [[ "$output" == *"[dry-run]"* ]]
    [ ! -d "$WWW_DIR/blog/.git" ] && [ ! -d "$BACKUP_DIR" ]
    ! grep -q 'systemctl restart' "$SUDO_LOG"
}

@test "validate_repo_url y validate_branch: aceptan lo normal" {
    run bash -c "source '$VPS_DIR/lib.sh'
        for u in https://github.com/yo/repo.git git@github.com:yo/repo.git ssh://git@host.com:2222/yo/repo.git; do validate_repo_url \"\$u\" || { echo \"no: \$u\"; exit 1; }; done
        for b in main release/1.2 feature-x v1.0.0; do validate_branch \"\$b\" || { echo \"no: \$b\"; exit 1; }; done"
    [ "$status" -eq 0 ] || { echo "$output"; return 1; }
}
