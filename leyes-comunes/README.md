# Código compartido de `privacy-laws/` y `copyright-laws/`

Lo que los dos módulos hacían igual y se mantenía sincronizado a mano (y ya se desincronizó una vez: ver #140)
vive aquí una sola vez. Cada script del proyecto queda como un envoltorio que dice solo lo suyo y llama al núcleo:

| Núcleo | Lo usan | Qué hace |
|---|---|---|
| `check-links-core.js` | `scripts/check-links.js` | Comprueba que los enlaces de referencia siguen respondiendo. |
| `cache-texts-core.php` y `html_to_text.php` | `scripts/cache-texts.php` | Guarda el texto de la página de cada ley (robots.txt, un pedido por URL, HTML a Markdown). |
| `record-changes-core.js` | `scripts/record-changes.js` | Anota en `changes.csv` lo que cambió en el CSV maestro, o con `--check` falla si algo cambió sin anotarse. |
| `replace-url-core.js` | `scripts/replace-url.js` | Cambia una URL de referencia muerta por otra en el CSV maestro y en `field_sources.csv` de una vez (`npm run replace-url -- <vieja> <nueva>`). |
| `apply-research-core.js` | `scripts/apply-research.js` | Convierte un CSV de hallazgos (`code,field,value,conf,source`) en filas nuevas y celdas corregidas, cada una con su fuente en `field_sources.csv`; marca `(unconfirmed)` lo de confianza baja y no escribe nada que la validación del proyecto rechace. |
| `translations-core.js` | `scripts/translations.js` | Cobertura de las traducciones automáticas, lo que falta por idioma e importación de las hechas. |
| `dataset-core.js` | `scripts/lib/dataset.js` (los dos re-exportan lo suyo) | Nombres de los archivos de datos, niveles de confianza, `isRealDate`, `isHttpUrl`, `isPrivateHost`, los avisos de fuentes más nuevas o viejas que su fila, `toCsv`/`writeCsv`, `findLaw` y `withSideData`. Solo usa módulos de Node, sin paquetes. Además trae `createDataset({ masterCsv, dataDir, columns, metaFields, translatedFields, parseMaster, validateRecords, validateSources })`, que arma lo que es igual pero necesita algo de cada módulo (`loadMaster`, `loadSide`, `loadAndValidate`, `diffRecords`, `validateTranslations`, `validateChanges`, `translationDictionaries`, `translatableValues`, `applyWrites`); cada `dataset.js` lo llama una vez y re-exporta el resultado. Lo que sí cambia entre módulos (columnas, regiones, reglas de cada fila y de las fuentes, `parseMaster`, que usa `csv-parse`) se queda en cada `dataset.js`. |
| `validate-csv-core.js` | `scripts/validate-csv.js` | Corre la validación del proyecto, imprime los problemas y escribe el resumen de GitHub Actions. |
| `importador-core.php` | `import_database.php` | Importa el CSV a MySQL / MariaDB: conexión por `DB_*`, esquema, transacción (`--import` reemplaza y se puede repetir), fechas estrictas y los comandos. `--create-schema` y `--all` piden `--yes`, porque borran la base. |
| `database-check-core.php` | `tests/database_check.php` | Compara la base con el CSV maestro, columna por columna. Lo corre el job `database` de `laws-modules.yml`. |
| `importer-tests-core.js` | `tests/importer.test.js` | Pruebas del importador que no necesitan servidor: columnas, anchos de `VARCHAR`, `--yes` y fechas. |
| `comprobar-copias.js` | el job `copias` de `laws-modules.yml` | Comprueba que las copias que se mantienen idénticas a propósito (ver abajo) lo siguen siendo. |

## Qué vive aquí y qué no

Vive acá todo lo que no depende de qué módulo lo llama. Lo que sí depende del módulo lo pasa cada envoltorio:

- `check-links.js`: la columna de la URL (`website_url` o `linked_resources`), la etiqueta de la unidad para el
  resumen (`country` o `jurisdiction`), el `User-Agent` y el propio `./lib/dataset.js` de cada proyecto
  (columnas distintas), con `createCheckLinks({ urlField, unit, userAgent, dataset })`.
- `record-changes.js` y `translations.js`: solo el `./lib/dataset.js` del proyecto, con
  `createRecordChanges({ dataset })` y `createTranslations({ dataset })`.
- `validate-csv.js`: el `loadAndValidate` del proyecto y el título del resumen, con
  `validateCsv({ loadAndValidate, title })`.
- `cache-texts.php`: seis constantes que define el envoltorio antes de hacer `require` del núcleo (`BUNDLE_KEY`,
  `URL_FIELD`, `USER_AGENT`, `UNIT`, `DATA_DIR` y `SCRIPT_FILE`; las explica el encabezado de
  `cache-texts-core.php`). La ayuda que imprime el script sale del comentario del envoltorio, así que cada proyecto
  conserva la suya.

Cada envoltorio conserva además el comentario de uso de su script y, en `record-changes.js` y `translations.js`,
reexporta las funciones que usan las pruebas, para que los `import` de las pruebas no cambien.

Ninguno tiene pruebas propias: los prueban las de `privacy-laws/tests/` y `copyright-laws/tests/`, que siguen
corriendo contra el script de su propio proyecto.

## Lo que sigue duplicado, y por qué

- **`app/`** (`lib/chart.js`, `lib/filters.js`, `lib/util.js` y varias hojas de estilo son idénticos): la web se sirve
  como archivos estáticos desde la carpeta `app/` de cada proyecto, sin paso de build. Compartir esos archivos
  pediría un paso que los copie, o sacarlos de la carpeta que se sirve. No se hizo en la misma tanda.
- **`scripts/lib/dataset.js`**: es el corazón de cada módulo y los campos de cada ley son distintos (los de
  privacidad frente a los de derechos de autor, y `website_url` frente a `linked_resources`). Un núcleo
  compartido sería un refactor más grande que el resto junto, con el riesgo de tocar cómo se valida cada CSV.
- **`tests/`**: las pruebas son las que vigilan los envoltorios, así que cada proyecto conserva las suyas. Algunos
  archivos son idénticos (`tests/helpers/php-server.js`, `tests/fixtures/law-page.html`, `tests/translations.test.js`).

No se pueden compartir, pero sí vigilar: `comprobar-copias.js` falla en CI si una de esas copias (`app/lib/chart.js`,
`filters.js`, `util.js`, las hojas `app/styles/base.css`, `comparison.css` y `search.css`,
`tests/helpers/php-server.js`, `tests/fixtures/law-page.html`, `tests/translations.test.js`) deja de ser igual en los dos módulos.

## Sitios que rechazan a los runners

`hosts-que-rechazan-runners.txt` lista los hosts que están vivos pero rechazan la conexión
(`ECONNREFUSED`) de los servidores de GitHub Actions. En ellos, ese error es un aviso y no un enlace
roto: sin la lista, cada corrida los marcaba en rojo y la issue automática de los enlaces rotos no se
cerraba nunca. Entran solo después de verlos rechazar al runner más de una vez, y con el motivo al
lado. El rechazo es intermitente: el mismo host que falla en una corrida suele responder en la
siguiente, así que la lista crece con los casos que se vean en CI, no con un diagnóstico del sitio. No esconde enlaces muertos: un host que desaparece falla antes, en DNS, y eso sigue siendo roto.
Un puerto cerrado en cualquier otro host también.

## Por qué no es una dependencia entre `privacy-laws/` y `copyright-laws/`

Ninguno de los dos importa del otro: ambos importan de esta carpeta, un tercero
sin lógica propia de ninguno de los dos módulos. La única dependencia entre
proyectos documentada en el README raíz (`phone-directory/` → `defamatory-content-review/`)
no cambia.
