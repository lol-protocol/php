# Núcleo compartido del verificador de enlaces

`check-links-core.js`: la lógica detrás de `privacy-laws/scripts/check-links.js` y
`copyright-laws/scripts/check-links.js`. Hasta esta carpeta, los dos eran el mismo
archivo mantenido sincronizado a mano (y ya se desincronizaron una vez: ver #140).

## Qué vive aquí y qué no

Vive acá todo lo que no depende de qué módulo lo llama: cómo se hace cada pedido
HTTP (`HEAD` primero, `GET` si lo rechaza, un reintento en los `5xx`), cómo se
clasifica un resultado (`ok` / `warning` / `broken`, ver el comentario de
`classify`), la concurrencia (`mapLimit`), el parseo de argumentos de la línea de
comandos y el reporte (consola, JSON, resumen de GitHub Actions).

Lo que sí depende del módulo —la columna de la URL (`website_url` o
`linked_resources`), la etiqueta de la unidad para el resumen (`country` o
`jurisdiction`), el `User-Agent` y el propio `./lib/dataset.js` de cada
proyecto (columnas distintas)— lo pasa cada `check-links.js` al llamar a
`createCheckLinks({ urlField, unit, userAgent, dataset })`.

No tiene pruebas propias: lo prueban las de `privacy-laws/tests/check-links.test.js`
y `copyright-laws/tests/check-links.test.js`, que siguen corriendo contra el
`check-links.js` de su propio proyecto (son 20 líneas que configuran y reexportan
este archivo).

## Por qué no es una dependencia entre `privacy-laws/` y `copyright-laws/`

Ninguno de los dos importa del otro: ambos importan de esta carpeta, un tercero
sin lógica propia de ninguno de los dos módulos. La única dependencia entre
proyectos documentada en el README raíz (`phone-directory/` → `defamatory-content-review/`)
no cambia.
