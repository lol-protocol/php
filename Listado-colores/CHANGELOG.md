# Registro de cambios de los datos

Versiones de los **datos** de `Listado-colores/` (los CSV y lo que se genera de ellos), no del código del generador. `generar-tabla.py` toma la versión de la primera entrada (la más reciente) y la escribe en `exportes/manifiesto.json`, junto con el número de filas y la suma SHA-256 de cada CSV y de cada exporte. Quien use los datos puede fijar esa versión y comprobar las sumas para saber que tiene lo mismo.

Cuándo subir cada número es decisión de quien cambia los datos; el generador no puede saberlo, pero `--comprobar` falla si el manifiesto no coincide con los archivos, así que cualquier cambio de datos obliga a regenerarlo y se ve en la revisión.

- **Mayor (X):** se quita o se renombra una columna, cambia lo que significa o el orden de las que ya había, o cambia el formato de un archivo de modo que un lector que funcionaba deje de funcionar.
- **Menor (Y):** se añade una columna, un archivo, un idioma, un color o filas nuevas.
- **Parche (Z):** se corrigen datos que ya existían (una traducción, un hex, una referencia) sin cambiar la estructura.

Cada entrada empieza por `## X.Y.Z - AAAA-MM-DD`, va de la más reciente a la más antigua y dice qué cambió. `generar-tabla.py` comprueba el formato.

## 1.0.0 - 2026-10-19

Primera versión numerada. Antes no hubo versiones: los cambios anteriores están en el historial de git.

Contenido de esta versión:

- Siete CSV de datos: `colores.csv` (148 colores CSS con su forma natural en inglés, su nombre en 30 idiomas y dos significados en español), `significados-idiomas.csv` (los significados en los otros 29 idiomas), `lexico-basico.csv` (38 términos de color de uso real en los 30 idiomas), `evidencia.csv`, `significados-culturales.csv`, `referencias.csv` y `nombres-completos.csv` (31.935 nombres con su hex, su color CSS más cercano, la distancia y la banda de cercanía).
- Exportes: `exportes/colores.jsonl` (una línea por color) y `exportes/gazetteer.tsv` con ocho columnas: `forma`, `idioma`, `fuente`, `tipo`, `concepto`, `hex`, `rasgos` y `forma_normalizada`.
- La base SQLite que construye `python3 generar-tabla.py --sqlite ruta.db`, con `nombre_normalizado` en `nombres` y `forma_normalizada` en `lexico`.
- `exportes/manifiesto.json`, con la versión, las filas y las sumas de lo anterior.
- `ambiguedad.md`, el informe de las formas que designan más de un color, y `auditoria-nombres.md`, el de los nombres sospechosos de la lista completa.

Escrito por un asistente de IA y sin revisión de hablantes nativos: las traducciones de los nombres y de los significados, el léxico básico, las formas naturales y los matices culturales.
