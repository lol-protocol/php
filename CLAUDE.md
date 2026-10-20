# lol-protocol/php — reglas para quien trabaje en este repositorio

Es un monorepo de proyectos independientes, cada uno en su carpeta. La lista y lo que hace cada uno está en `README.md`: no la repitas aquí. Cada proyecto tiene su propio `README.md` con instalación y uso, y algunos un `CLAUDE.md` propio (hoy `marcalibros-plataforma-pos-brand-identity/`): léelo antes de tocar esa carpeta; si choca con este archivo, manda el de la carpeta.

La persona que encarga el trabajo escribe en español: responde y documenta en español, salvo en un proyecto que ya esté escrito en otro idioma.

## Antes de empezar

Varias sesiones trabajan a la vez, y ya pasó que dos hicieron lo mismo o que dos PR tocaron los mismos archivos. Antes de cambiar nada:

1. `git fetch origin` y trabaja contra `origin/master`. Tu copia local puede estar vieja: lo que necesites leer, léelo con `git show origin/master:ruta`.
2. Mira qué PR hay abiertos y qué carpetas tocan:
   `gh api 'repos/lol-protocol/php/pulls?state=open' --jq '.[] | "#\(.number) \(.title) [\(.head.ref)]"'`
   y, de cada uno, `gh api repos/lol-protocol/php/pulls/<n>/files --jq '.[].filename'`.
3. Mira también las ramas con trabajo que todavía no tiene PR: `git rev-list --count origin/master..origin/<rama>`. Las ramas `claude/...` las reutilizan varias sesiones, y una puede tener commits sin fusionar de otra.
4. No toques una carpeta que tenga un PR abierto o commits sin fusionar de otra sesión. Elige otra, o pregunta.
5. No fuerces un push ni reescribas una rama que no creaste. Si el push se rechaza, trae lo que falta y fusiona.

## Cómo se parte el trabajo

- **Un PR por carpeta de proyecto.** Si un cambio cruza carpetas, sepáralo.
- **Los archivos compartidos son los que más chocan:** `README.md` de la raíz (la tabla de proyectos), `.gitignore` y el `composer.json` de la raíz. Cámbialos en un PR chico y aparte, para que se fusione rápido.
- **Cada proyecto con pruebas tiene su propio workflow** (`.github/workflows/pruebas-<proyecto>.yml`, con filtro de rutas): cámbialo en el PR del proyecto, no en uno aparte. Un proyecto nuevo con pruebas trae el suyo en vez de agregar jobs al de otro.
- **Abre los PR en borrador, y no los fusiones por tu cuenta:** fusionar lo decide la persona.
- **Si ya hay un PR que hace lo mismo,** no abras otro en paralelo: aporta ahí, o cierra el tuyo diciendo por qué.
- **Migraciones numeradas** (`cobros-ingresos-funnels/database/migraciones/NNN_*.sql`): antes de crear una, mira el último número en `master` y en los PR y ramas abiertos.

## Decisiones de la persona (no las cambies sin preguntar)

- `cobros-ingresos-funnels/`: el piso de PHP es 8.3, y no tiene login a propósito (el código del login se conserva en su `_Garbage/`, con su README).
- Las carpetas `_Garbage/` se conservan. Ver «Carpetas `_Garbage/`» en el `README.md` raíz: no las borres ni las limpies.
- `README.md` se llama siempre así, en mayúsculas. Los demás nombres de archivo siguen lo que ya haga cada proyecto (`document-formats/docs/Localization.md`, `url-routing/docs/README_URLS.md`). No renombres archivos en masa ni unifiques mayúsculas entre proyectos.
- No hay una forma única esperada para lo que devuelve un «validador» nuevo: `cobros-ingresos-funnels/src/Dinero.php`'s `Dinero::centavos()` devuelve un `int` y lanza excepción en valores inválidos; `privacy-laws/scripts/check-links.js`'s `classify()` devuelve un objeto `{status, code, detail, finalUrl}`. Si agregas un validador, elige una de esas dos formas en vez de inventar una tercera, salvo que tengas una razón concreta.

## Probar antes de empujar

Desde la carpeta de cada proyecto, salvo donde diga «desde la raíz» (el detalle y los requisitos están en su README):

| Proyecto | Pruebas |
|---|---|
| `defamatory-content-review/` | `composer install && ./vendor/bin/phpunit` (la CI corre además `phpstan analyse` — nivel y alcance en su `phpstan.neon.dist` — y `php bin/benchmark.php 2000`) |
| `phone-directory/` | `composer install && ./vendor/bin/phpunit` (depende de `../defamatory-content-review`; la CI corre además `phpstan analyse` — mismo nivel y criterio que `defamatory-content-review/`, ver su `phpstan.neon.dist` — y los puertos de `ports/python` y `ports/java`) |
| `cobros-ingresos-funnels/` | con PostgreSQL y `export APP_ENV=dev`: `composer install && php bin/recrear_con_datos_de_ejemplo.php` (borra la base y la rearma con las migraciones y datos de ejemplo), luego `./vendor/bin/phpunit` (la CI corre además PHPStan) |
| `sistema-nuevo/` | `php pruebas/ejecutar-php.php` y `./pruebas/ejecutar-js.sh`; con PostgreSQL, `php datos/generar-datos-semilla.php && php pruebas/ejecutar-integracion.php`; el e2e (`./pruebas/ejecutar-e2e.sh`) necesita los servicios de `ejecutar.sh` |
| `url-routing/` | `composer install && ./vendor/bin/phpunit` (la CI lo corre también contra PostgreSQL) |
| `document-formats/` | `node --test 'tests/*.test.js'` (la CI además importa a MySQL) |
| `web-animations/` | `npm ci && npm test` (necesita el Chromium de Playwright) |
| `iconos-genealogia/`, `iconos-biblioteca/`, `iconos-tools/` | desde la raíz: `python3 iconos-tools/validate.py`, y `python3 iconos-tools/build.py` no debe dejar cambios en git |
| `vps-setup/` | desde la raíz: `shellcheck -S warning vps-setup/*.sh vps-setup/monitoring/*.sh` y `bats vps-setup/tests` |
| `Listado-colores/` | `python3 generar-tabla.py --comprobar` (valida los siete CSV, `colores.csv`, `significados-idiomas.csv`, `lexico-basico.csv`, `evidencia.csv`, `significados-culturales.csv`, `referencias.csv` y `nombres-completos.csv`, y `CHANGELOG.md`; comprueba que el README, `colores-por-idioma.md`, `por-idioma/`, `referencias.md`, `ambiguedad.md`, `auditoria-nombres.md` y `exportes/` (con su manifiesto) estén al día y construye una base SQLite en memoria; tarda entre 13 y 20 s) y `python3 -m unittest pruebas` (unos 13 s). Si cambias datos, apunta el cambio en `CHANGELOG.md` y regenera con `python3 generar-tabla.py` |
| `Listado-paises/` | `python3 generar_tabla.py --comprobar` (valida `paises.csv`, `paises_pos.csv` y `niveles_lenguas.csv`, y que `tabla_genealogia.md`, `tabla_pos.md`, `priorizacion.csv` y `vista.html` estén al día) y `python3 -m unittest pruebas` |
| `signos-zodiacales/` | `python3 generar.py --comprobar` (valida `datos/signos.json` — los 30 idiomas, las estrellas de cada constelación — y que `constelaciones/*.svg` y `signos-por-idioma.md` estén al día) |

El resto de proyectos (`privacy-laws/`, `copyright-laws/`, `marketing/`, `marcalibros-plataforma-pos-brand-identity/`, `psychology-and-marketing/`, `landing-page/`): mira su README y los jobs de `.github/workflows/`.

La convención del repo para «generar y comprobar que lo committeado sigue al día» es el flag `--comprobar` (`Listado-colores/`, `Listado-paises/`). `iconos-tools/` lo resuelve distinto a propósito: `build.py` (escribe) y `validate.py` (comprueba las reglas) quedan separados porque `build.py` también sirve solo para regenerar tras cambiar el generador, no solo para validar en CI.

## Entorno de las sesiones en la nube (puede cambiar)

- PostgreSQL no arranca solo: `service postgresql start`. Cada proyecto espera sus propios roles y bases (están en su README).
- Composer no logra bajar algunos paquetes desde GitHub (por ejemplo PHPStan). No insistas: el análisis estático lo corre la CI. Para adelantarlo en local, el `vendor/` de `cobros-ingresos-funnels/` trae un `phpstan.phar` suelto: `cp /home/user/php/cobros-ingresos-funnels/vendor/sebastian/code-unit/tools/.phpstan/vendor/phpstan/phpstan/phpstan.phar /tmp/` y, desde la carpeta del proyecto con su `vendor/` al día (`composer install`, o `composer dump-autoload` si el autoload falla por un archivo que ya no existe), `php /tmp/phpstan.phar analyse --no-progress`. Hay que sacarlo de esa carpeta antes de correrlo (ahí carga reglas extra y da cientos de avisos que la CI no da). Es más viejo que el de la CI (2.1.8): compara con `master` limpio antes de creer un aviso.
- No hay una CLI `gh` completa: `gh api <ruta REST>` funciona, y las herramientas de GitHub de la sesión cubren PR y comentarios.
- Cada workflow solo corre si cambia la carpeta de su proyecto, o la de otro que ese proyecto usa: `phone-directory/` y `marcalibros-plataforma-pos-brand-identity/` también corren si cambia `defamatory-content-review/`. Un PR de una sola carpeta dispara pocos checks; `estructura.yml` corre siempre, y `marketing/`, `landing-page/` y `psychology-and-marketing/` no tienen más. Espera a que terminen todos antes de decir que la CI está en verde.
