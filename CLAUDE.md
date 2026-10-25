# lol-protocol/php — reglas para quien trabaje en este repositorio

Es un monorepo de proyectos independientes, cada uno en su carpeta. La lista y lo que hace cada uno está en `README.md`: no la repitas aquí. Cada proyecto tiene su propio `README.md` con instalación y uso, y algunos un `CLAUDE.md` propio (hoy `marcalibros-plataforma-pos-brand-identity/` y `marcalibros_b/`): léelo antes de tocar esa carpeta; si choca con este archivo, manda el de la carpeta.

La persona que encarga el trabajo escribe en español: responde y documenta en español, salvo en un proyecto que ya esté escrito en otro idioma.

## Antes de empezar

Varias sesiones trabajan a la vez, y ya pasó que dos hicieron lo mismo o que dos PR tocaron los mismos archivos. Antes de cambiar nada:

1. `git fetch origin` y trabaja contra `origin/master`. Tu copia local puede estar vieja: lo que necesites leer, léelo con `git show origin/master:ruta`.
2. Mira qué PR hay abiertos y qué carpetas tocan:
   `gh api 'repos/lol-protocol/php/pulls?state=open' --jq '.[] | "#\(.number) \(.title) [\(.head.ref)]"'`
   y, de cada uno, `gh api repos/lol-protocol/php/pulls/<n>/files --jq '.[].filename'`.
3. Mira también las ramas con trabajo que todavía no tiene PR: `git rev-list --count origin/master..origin/<rama>`. Los PR se fusionan con squash, así que una rama ya fusionada también sale «adelantada»: antes de tomarla por trabajo pendiente, mira si su PR está fusionado (`gh api 'repos/lol-protocol/php/pulls?state=all&head=lol-protocol:<rama>' --jq '.[] | "#\(.number) \(.state) \(.merged_at)"'`). Las ramas `claude/...` las reutilizan varias sesiones, y una puede tener commits sin fusionar de otra.
4. No toques una carpeta que tenga un PR abierto o commits sin fusionar de otra sesión. Elige otra, o pregunta.
5. No fuerces un push ni reescribas una rama que no creaste. Si el push se rechaza, trae lo que falta y fusiona.

## Cómo se parte el trabajo

- **Un PR por carpeta de proyecto.** Si un cambio cruza carpetas, sepáralo.
- **Los archivos compartidos son los que más chocan:** `README.md` de la raíz (la tabla de proyectos), `.gitignore` y el `composer.json` de la raíz. Cámbialos en un PR chico y aparte, para que se fusione rápido. La excepción es lo que `estructura.yml` exige: una carpeta de proyecto nueva trae su fila de la tabla, y un workflow nuevo su viñeta en la sección «CI/CD», en el mismo PR, porque el check falla mientras falten.
- **Cada proyecto con pruebas tiene su propio workflow** (`.github/workflows/pruebas-<proyecto>.yml`, con filtro de rutas): cámbialo en el PR del proyecto, no en uno aparte. Un proyecto nuevo con pruebas trae el suyo en vez de agregar jobs al de otro.
- **Abre los PR en borrador, y no los fusiones por tu cuenta:** fusionar lo decide la persona.
- **No apiles un PR sobre la rama de otro que siga abierto.** Al fusionar el primero con squash, el segundo queda con conflicto en todo lo que los dos cambiaron (pasó con #223 y #224). Abre el segundo cuando el primero esté en `master`. Si no puedes esperar, déjalo en borrador con la base en `master`, y cuando el primero se fusione, trae `master` a su rama con un merge y resuelve los conflictos antes de pedir que lo fusionen.
- **Si ya hay un PR que hace lo mismo,** no abras otro en paralelo: aporta ahí, o cierra el tuyo diciendo por qué.
- **Migraciones numeradas** (`cobros-ingresos-funnels/database/migraciones/NNN_*.sql`): antes de crear una, mira el último número en `master` y en los PR y ramas abiertos.

## Decisiones de la persona (no las cambies sin preguntar)

- `cobros-ingresos-funnels/`: el piso de PHP es 8.3, y no tiene login a propósito (el código del login se conserva en su `_Garbage/`, con su README).
- Las carpetas `_Garbage/` se conservan. Ver «Carpetas `_Garbage/`» en el `README.md` raíz: no las borres ni las limpies.
- `README.md` se llama siempre así, en mayúsculas. Los demás nombres de archivo siguen lo que ya haga cada proyecto (`document-formats/docs/Localization.md`, `url-routing/docs/README_URLS.md`). No renombres archivos en masa ni unifiques mayúsculas entre proyectos.
- No hay una forma única esperada para lo que devuelve un «validador» nuevo: `cobros-ingresos-funnels/src/Dinero.php`'s `Dinero::centavos()` devuelve un `int` y lanza excepción en valores inválidos; `privacy-laws/scripts/check-links.js`'s `classify()` devuelve un objeto `{status, code, detail, finalUrl}`. Si agregas un validador, elige una de esas dos formas en vez de inventar una tercera, salvo que tengas una razón concreta.
- **No borres código sin uso.** Márcalo `@deprecated` (con una línea que diga desde cuándo y, si lo sabes, por qué dejó de usarse) y déjalo donde está. Es la misma lógica que las carpetas `_Garbage/`: se conserva como referencia en vez de borrarse.

## Probar antes de empujar

Desde la carpeta de cada proyecto, salvo donde diga «desde la raíz» (el detalle y los requisitos están en su README):

| Proyecto | Pruebas |
|---|---|
| `defamatory-content-review/` | `composer install && ./vendor/bin/phpunit` (la CI corre además `phpstan analyse` — nivel y alcance en su `phpstan.neon.dist` — y `php bin/benchmark.php 2000`) |
| `phone-directory/` | `composer install && ./vendor/bin/phpunit` (depende de `../defamatory-content-review`; la CI corre además `phpstan analyse` — mismo nivel y criterio que `defamatory-content-review/`, ver su `phpstan.neon.dist` — y los puertos de `ports/python` y `ports/java`) |
| `cobros-ingresos-funnels/` | con PostgreSQL y `export APP_ENV=dev`: `composer install && php bin/recrear_con_datos_de_ejemplo.php` (borra la base y la rearma con las migraciones y datos de ejemplo), luego `./vendor/bin/phpunit` (la CI corre además PHPStan) |
| `sistema-nuevo/` | `php pruebas/ejecutar-php.php` y `./pruebas/ejecutar-js.sh`; con PostgreSQL, `php datos/generar-datos-semilla.php && php pruebas/ejecutar-integracion.php`; el e2e (`./pruebas/ejecutar-e2e.sh`) necesita los servicios de `ejecutar.sh` |
| `url-routing/` | `composer install && ./vendor/bin/phpunit` (la CI lo corre también contra PostgreSQL) |
| `document-formats/` | `node --test 'tests/*.test.js'` (la CI además importa a MySQL y a MariaDB) |
| `web-animations/` | `npm ci && npm test` (necesita el Chromium de Playwright) |
| `iconos-genealogia/`, `iconos-biblioteca/`, `iconos-pos/`, `iconos-tools/` | desde la raíz: `python3 iconos-tools/validate.py`, y `python3 iconos-tools/build.py` no debe dejar cambios en git (la CI corre además `python3 iconos-tools/similares.py`, que busca iconos casi idénticos a 24 px y necesita `pip install -r iconos-tools/requirements.txt`) |
| `vps-setup/` | desde la raíz: `shellcheck -S warning vps-setup/*.sh vps-setup/monitoring/*.sh` y `bats vps-setup/tests` |
| `Listado-colores/` | `python3 generar-tabla.py --comprobar` (valida los CSV y `CHANGELOG.md`, y que todo lo generado —tablas, `por-idioma/`, `exportes/`— esté al día; tarda entre 13 y 20 s; el detalle, en su README) y `python3 -m unittest pruebas` (unos 13 s). Si cambias datos, apunta el cambio en `CHANGELOG.md` y regenera con `python3 generar-tabla.py` |
| `Listado-paises/` | `python3 generar_tabla.py --comprobar` (valida `paises.csv`, `paises_pos.csv` y `niveles_lenguas.csv`, y que `tabla_genealogia.md`, `tabla_pos.md`, `priorizacion.csv` y `vista.html` estén al día) y `python3 -m unittest pruebas` |
| `signos-zodiacales/` | `python3 generar.py --comprobar && python3 -m unittest pruebas && python3 -m doctest zodiaco.py` (valida `datos/signos.json` y `datos/anio-nuevo-lunar.json` — los 30 idiomas, elementos, ciclo chino, las estrellas de cada constelación — y que `constelaciones/*.svg`, `constelaciones-sin-fondo/*.svg`, `por-idioma/*.md` y `galeria.html` estén al día; las pruebas revisan los datos, las consultas de `zodiaco.py` y los SVG). La CI corre además `python pruebas_navegador.py`, que abre `galeria.html` en Chromium en los 30 idiomas: necesita `pip install playwright==1.55.0` y la variable `CHROMIUM` (o `/opt/pw-browsers/chromium`) |
| `portadas-svg/` | `php pruebas/ejecutar.php` y `php bin/generar.php --comprobar` (que `ejemplos/salida/` esté al día con los JSON de `ejemplos/`) |
| `privacy-laws/`, `copyright-laws/` | `npm ci && npm test`, `npm run validate` y `npm run build:check`; si tocas el CSV maestro, `npm run changes` y, antes de empujar, `npm run changes -- --check --since=origin/master` (la CI corre además PHP 8.1 y 8.4, y las pruebas de navegador con `npm run test:e2e`, que necesitan Chromium). `npm run check:links` revisa los enlaces de referencia, pero desde el contenedor de la nube no llega a esos sitios: lo corre `.github/workflows/laws-links.yml` (también a mano con `workflow_dispatch`). `leyes-comunes/` (el núcleo compartido del verificador de enlaces) no tiene pruebas propias: lo prueban las de estos dos |
| `marcalibros_b/` | `pip install -r generator/requirements.txt -r generator/requirements-dev.txt`, `python generator/build.py --all` y `python -m pytest -q tests` (necesita Chromium real para el QA — variable `CHROMIUM` o `/opt/pw-browsers/chromium`; Python 3.13). La CI sube el catálogo completo como artefacto |

El resto de proyectos (`marketing/`, `marcalibros-plataforma-pos-brand-identity/`, `psychology-and-marketing/`): mira su README y los jobs de `.github/workflows/`.

`landing-page/` no tiene `README.md` a propósito: `vps-setup/` copia **toda** la carpeta a la raíz del servidor web (`cp -r landing-page/. destino/`), así que cualquier archivo que pongas ahí queda público, un README incluido. Su descripción está en la tabla del `README.md` raíz y en el de `vps-setup/`.

La convención del repo para «generar y comprobar que lo committeado sigue al día» es el flag `--comprobar` (`Listado-colores/`, `Listado-paises/`). `iconos-tools/` lo resuelve distinto a propósito: `build.py` (escribe) y `validate.py` (comprueba las reglas) quedan separados porque `build.py` también sirve solo para regenerar tras cambiar el generador, no solo para validar en CI.

## Entorno de las sesiones en la nube (puede cambiar)

- PostgreSQL no arranca solo: `service postgresql start`. Cada proyecto espera sus propios roles y bases (están en su README).
- La red de la sesión no deja pasar `api.github.com` ni `codeload.github.com`, que es de donde Composer baja casi todos los paquetes; `git` contra `github.com` sí pasa. Antes del primer `composer install` de una carpeta, desde la raíz: `php .claude/scripts/composer-desde-git.php <carpeta>`. El script baja con git solo el commit que fija el `composer.lock` de cada paquete y lo deja en la caché de Composer, y después `composer install` funciona como siempre, PHPStan incluido; tarda menos de un minuto por carpeta. Para un `phpstan.phar` suelto con la versión de la CI (2.1.8): `git clone -q --depth 1 --branch 2.1.8 https://github.com/phpstan/phpstan /tmp/phpstan`.
- No hay una CLI `gh` completa: `gh api <ruta REST>` funciona, y las herramientas de GitHub de la sesión cubren PR y comentarios.
- Cada workflow solo corre si cambia la carpeta de su proyecto, o la de otro que ese proyecto usa: `phone-directory/`, `marcalibros-plataforma-pos-brand-identity/` y `sistema-nuevo/` (sus notas pasan por el filtro) también corren si cambia `defamatory-content-review/`. `privacy-laws/` y `copyright-laws/` también corren si cambia `leyes-comunes/` (el núcleo de su verificador de enlaces). Un PR de una sola carpeta dispara pocos checks; `estructura.yml` corre siempre, y `marketing/`, `landing-page/` y `psychology-and-marketing/` no tienen más. Espera a que terminen todos antes de decir que la CI está en verde.
