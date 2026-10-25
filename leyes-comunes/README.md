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
