# Parsing-fb-in-mas

Lectores de **páginas de perfil ya guardadas** y de **volcados de datos abiertos**, para tener
un punto de partida al buscar a una persona en un proyecto de genealogía. PHP 8.3, sin
dependencias propias (el cruce usa el plegado fonético de `../defamatory-content-review/`,
como `phone-directory/`).

| Qué lee | Archivo | Personas |
|---|---|---|
| Facebook, LinkedIn, Twitter/X, Instagram, TikTok, YouTube (canales), GitHub, Pinterest, Bluesky, Threads, Mastodon (cualquier instancia) | una página HTML guardada | casi siempre vivas |
| WikiTree, Find a Grave | una página HTML guardada (microdatos de schema.org) | fallecidas |
| WikiTree | una respuesta JSON de su API, guardada (`.json`, un archivo por vez) | fallecidas, o vivas con lo mínimo |
| Cualquier sitio con una `Person` de schema.org (JSON-LD o microdatos) | una página HTML guardada | las que declare |
| Wikidata | un volcado local `.json` o `.json.gz` (CC0) | fallecidas |

## Lo que hace y lo que no

- **No descarga nada, no inicia sesión, no sigue enlaces ni recorre listas de contactos.**
  Los programas de `bin/` que leen archivos rechazan una URL a propósito. Las redes prohíben en sus
  términos la extracción automática, y los datos de personas son justo lo que
  `PRIVACIDAD-Y-HONRA.md` (raíz) manda cuidar. Lo «masivo» aquí es procesar lo que tú
  ya bajaste (una carpeta entera, un volcado de millones de líneas), no recorrer sitios.
- **De una persona viva se guarda poco:** red, URL, usuario, nombre, titular, lugar,
  descripción (hasta 500 caracteres) y fecha de captura. Sin correo, teléfono, contactos,
  publicaciones ni contadores de seguidores. **Fechas de nacimiento y de muerte, lugares de
  vida y parientes se guardan solo si la fuente da una fecha de muerte**; si una página trae
  `birthDate` de alguien sin `deathDate`, esa fecha se descarta (hay pruebas).
- **El texto que escribió la persona (titular y descripción) se marca, no se borra.** Pasa por el filtro
  de `../defamatory-content-review/` (datos personales como el RUT o un teléfono, insultos y acusaciones, y
  datos sensibles **de un tercero**: «tiene cáncer», «la familia era judía», «es comunista», «era mapuche») y por
  una lista corta propia de categorías sensibles (`src/Sensibles.php`: salud, religión, política o sindical,
  vida sexual, origen étnico). **Las dos se complementan:** el filtro del repo está escrito para frases sobre
  otra persona y no marca lo que alguien dice de sí mismo («Tengo cáncer», «Soy evangélica»), que es justo lo
  que suele traer una biografía de perfil; esa parte la cubre `Sensibles`. Las etiquetas salen en `marcas` y un
  aviso dice «leer el texto con cuidado». **Es una ayuda, no una garantía:** la lista propia es de palabras
  sueltas, así que puede marcar de más y, sobre todo, no marcar de menos. Que no salga ninguna marca no quiere
  decir que el texto esté limpio. `--sin-revision` la desactiva.
- **Lista de exclusión (`--excluir=lista.txt`)** en `analizar.php`, `wikidata.php`, `wikidata-nombres.php` y
  `cruzar.php`: una entrada por línea, una URL o `red:usuario` (`twitter:ana`, `wikidata:Q42`, `wikitree:Ejemplo-1`);
  `#` es comentario. Sirve para que alguien que pidió retirarse no vuelva a salir al reprocesar
  (ver `RECLAMOS.md`). **No está conectada a la cola de pedidos de retiro del sitio** (`url-routing/`): se
  mantiene a mano, y quien decide un retiro tiene que agregar la entrada. No quita a nadie de lo que ya se
  generó. Una línea mal escrita hace fallar el programa en vez de ignorarse.
- **`revision_humana` sale siempre en verdadero; `posiblemente_vivo` también, salvo que conste
  una muerte.** Nada se publica ni se mezcla con un árbol sin que una persona lo revise y sin
  base legal escrita (preguntas 2 y 3 de `PREGUNTAS-PARA-ABOGADO.md`, y
  `CUMPLIMIENTO-LEY-21719.md`). Una muerte reciente sigue necesitando ese cuidado.
- **Cita la fuente:** la URL y la fecha de captura (`--capturado=AAAA-MM-DD`) van en cada
  registro; si faltan, sale un aviso.
- **Lee solo lo que el sitio pone para que otros lo lean:** etiquetas `<meta>` (Open Graph,
  Twitter Cards), `<link rel="canonical">`, JSON-LD y microdatos, no clases CSS. Si la red pide
  iniciar sesión para ver el perfil, la página guardada no trae nada y el resultado sale sin
  nombre y con un aviso: funciona sobre todo con páginas públicas.
- **Sin probar contra páginas reales.** Los formatos de título y descripción de cada red, y los
  microdatos de WikiTree y Find a Grave, están escritos de memoria y probados solo con muestras
  inventadas (`pruebas/muestras/`, de personas que no existen). Antes de confiar en uno, guarda
  una página real y compáralo. Revisa también los términos de cada sitio.

## Uso

```
# páginas sueltas o carpetas enteras (una misma URL sale una vez); --jsonl = un objeto por línea
php bin/analizar.php --capturado=2026-11-04 --jsonl carpeta-con-paginas/ otra.html > perfiles.jsonl

# un volcado de Wikidata, solo personas fallecidas, de Chile (Q298); lee en streaming
php bin/wikidata.php latest-all.json.gz --pais=Q298 --capturado=2026-11-04 > wikidata.jsonl

# lo mismo, con una lista de personas excluidas y sin la revisión de texto
php bin/analizar.php --jsonl --excluir=retiros.txt --sin-revision carpeta-con-paginas/ > perfiles.jsonl

# una respuesta guardada de la API de WikiTree (los .json se pasan uno por uno, no por carpeta)
php bin/analizar.php --capturado=2026-11-04 --jsonl respuesta-wikitree.json >> perfiles.jsonl

# segundo recorrido del volcado: nombres de lugares y parientes de los perfiles de Wikidata
php bin/wikidata-nombres.php latest-all.json.gz wikidata.jsonl > wikidata-con-nombres.jsonl

# parejas de FALLECIDOS que podrían ser la misma persona, entre dos fuentes
php bin/cruzar.php wikidata.jsonl perfiles.jsonl --idioma=spa
```

Pruebas: `php pruebas/ejecutar.php`; análisis estático: `phpstan analyse` (nivel `max`, ver `phpstan.neon.dist`; la CI usa PHPStan 2.1).

## Nombres en Wikidata

`wikidata.php` deja los lugares y los parientes como identificadores (`Q2887`). `wikidata-nombres.php` hace un
segundo recorrido del volcado, lee solo las líneas que se pidieron y se detiene al encontrarlas todas: los
lugares ganan `lugar_nacimiento_nombre` y `lugar_muerte_nombre`. **El nombre de un pariente solo se resuelve si
el propio volcado lo da por fallecido**: un hijo o una hija de alguien que murió puede estar vivo, y de una
persona viva no se saca el nombre; esos parientes quedan con su identificador, `nombre` en null y un aviso. Los
parientes que están en la lista de exclusión se quitan del perfil.

## La API de WikiTree

`WikiTreeApi` lee una respuesta JSON que ya guardaste (por ejemplo, la de la acción `getProfile`); no llama a la
API. Es más estable que el HTML, pero los campos que usa (`Name`, `FirstName`, `LastNameAtBirth`, `BirthDate`,
`DeathDate`, `IsLiving`, `Father`, `Mother`…) están escritos de memoria y **sin probar contra una respuesta
real**. Con `IsLiving` en verdadero, o con una `DeathDate` desconocida (`0000-00-00`), no sale ninguna fecha,
lugar ni pariente. Antes de usarla, revisa los términos de uso de la API de WikiTree: no lo hice.

## El cruce

`bin/cruzar.php` pliega los nombres con `PhoneticFolderRegistry` (así «González» y «Gonsales»
coinciden), pide al menos dos palabras en común, descarta nacimientos a más de 5 años, suma
puntos por año y lugar parecidos, y escribe cada pareja como **candidata que requiere revisión
humana**: nunca fusiona. **Omite todo perfil que pueda estar vivo** y cuenta cuántos omitió: un
perfil de red social no se empareja con registros de fallecidos ni con nada.

## Cómo agregar una red

Una clase en `src/` que extienda `Analizador` (`red()`, `dominios()` y `extraer()`),
registrada en `Detector::analizadores()` y en `src/cargar.php`, con su muestra en
`pruebas/muestras/` y sus comprobaciones en `pruebas/ejecutar.php`. Cualquier otra que
ponga Open Graph (Pleroma, Misskey…) se hace igual que `Twitter.php`; Mastodon, que no tiene un dominio fijo,
se reconoce por la forma del título (`Mastodon::reconoce()`).
