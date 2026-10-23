# Registro de cambios de los datos

Versiones de los **datos** de `Listado-colores/` (los CSV y lo que se genera de ellos), no del código del generador. `generar-tabla.py` toma la versión de la primera entrada (la más reciente) y la escribe en `exportes/manifiesto.json`, junto con el número de filas y la suma SHA-256 de cada CSV y de cada exporte. Quien use los datos puede fijar esa versión y comprobar las sumas para saber que tiene lo mismo.

Cuándo subir cada número es decisión de quien cambia los datos; el generador no puede saberlo, pero `--comprobar` falla si el manifiesto no coincide con los archivos, así que cualquier cambio de datos obliga a regenerarlo y se ve en la revisión.

- **Mayor (X):** se quita o se renombra una columna, cambia lo que significa o el orden de las que ya había, o cambia el formato de un archivo de modo que un lector que funcionaba deje de funcionar.
- **Menor (Y):** se añade una columna, un archivo, un idioma, un color o filas nuevas.
- **Parche (Z):** se corrigen datos que ya existían (una traducción, un hex, una referencia) sin cambiar la estructura.

Cada entrada empieza por `## X.Y.Z - AAAA-MM-DD`, va de la más reciente a la más antigua y dice qué cambió. `generar-tabla.py` comprueba el formato.

## 1.1.0 - 2026-10-22

- **Traducciones de nombres corregidas** en `colores.csv`: 27 celdas de 23 colores en 12 idiomas, para que dos colores CSS de hex distinto dejen de llamarse igual. Los nombres CSS repetidos entre colores de distinto hex pasan de 40 a 29. Se cambia el nombre que peor encaja de cada par, no los dos, y solo donde había un nombre distinto de uso corriente:
  - Hindi: la familia de los grises se llamaba `भूरा` («marrón», que es `brown`), en contradicción con el léxico del propio proyecto. Ahora usa `स्लेटी` (el lema del léxico) y, con «slate», `धूसर` (14 colores).
  - Ruso `cyan`: `голубой` → `циан` (`голубой` es «azul claro», `lightblue`). Ucraniano `cyan`: `блакитний` → `ціан`.
  - Finés `navy`: `tummansininen` → `laivastonsininen` (`tummansininen` queda en `darkblue`).
  - Árabe `magenta`: `أرجواني` → `ماجنتا` (`أرجواني` queda en `purple`).
  - Chino `crimson`: `深红色` → `绯红色` (`深红色` queda en `darkred`).
  - Coreano `lightgreen`: `연두` → `연초록` (`연두` queda en `yellowgreen`).
  - Vietnamita: `lightblue`, `lightgreen` y `palegreen` se llamaban `xanh nhạt`; ahora `xanh dương nhạt`, `xanh lá nhạt` y `xanh lá non`.
  - Indonesio `crimson`: `merah tua` → `merah padam` (como en el léxico; `merah tua` queda en `darkred`).
  - Danés `lightpink`: `lyserød` → `lys lyserød` (`lyserød` queda en `pink`).
  - Polaco `purple`: `fioletowy` → `purpurowy`; checo `purple`: `fialová` → `purpurová` (`fioletowy` y `fialová` quedan en `violet`).
- **Categorías gramaticales** (`categorias.csv`, `categorias.md`): 210 filas con las categorías (`ADJ`, `NOUN`, `PROPN`, `VERB`, las de Universal Dependencies) con que puede aparecer cada palabra de color de una sola palabra, con una frase de ejemplo por categoría. Cubre 61 palabras en inglés y 32 en español. El JSONL gana la clave `categorias`, el gazetteer la columna `categorias` (al final) y la base SQLite la tabla `categorias`.
- Son propuestas de un asistente de IA, sin revisión de hablantes nativos. Las 29 colisiones que quedan son sobre todo lenguas que no distinguen los dos colores (púrpura y violeta en hebreo, persa, bengalí, suajili…) o casos en los que no había un nombre distinto del que estuviera seguro; no se tocan sin un hablante nativo.

## 1.0.0 - 2026-10-19

Primera versión numerada. Antes no hubo versiones: los cambios anteriores están en el historial de git.

Contenido de esta versión:

- Siete CSV de datos: `colores.csv` (148 colores CSS con su forma natural en inglés, su nombre en 30 idiomas y dos significados en español), `significados-idiomas.csv` (los significados en los otros 29 idiomas), `lexico-basico.csv` (38 términos de color de uso real en los 30 idiomas), `evidencia.csv`, `significados-culturales.csv`, `referencias.csv` y `nombres-completos.csv` (31.935 nombres con su hex, su color CSS más cercano, la distancia y la banda de cercanía).
- Exportes: `exportes/colores.jsonl` (una línea por color) y `exportes/gazetteer.tsv` con ocho columnas: `forma`, `idioma`, `fuente`, `tipo`, `concepto`, `hex`, `rasgos` y `forma_normalizada`.
- La base SQLite que construye `python3 generar-tabla.py --sqlite ruta.db`, con `nombre_normalizado` en `nombres` y `forma_normalizada` en `lexico`.
- `exportes/manifiesto.json`, con la versión, las filas y las sumas de lo anterior.
- `ambiguedad.md`, el informe de las formas que designan más de un color, y `auditoria-nombres.md`, el de los nombres sospechosos de la lista completa.

Escrito por un asistente de IA y sin revisión de hablantes nativos: las traducciones de los nombres y de los significados, el léxico básico, las formas naturales y los matices culturales.
