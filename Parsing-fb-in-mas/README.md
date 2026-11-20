# Parsing-fb-in-mas

Lectores de **páginas de perfil ya guardadas** y de **volcados de datos abiertos**, para tener
un punto de partida al buscar a una persona en un proyecto de genealogía. PHP 8.3, sin
dependencias propias (el cruce usa el plegado fonético de `../defamatory-content-review/`,
como `phone-directory/`).

| Qué lee | Archivo | Personas |
|---|---|---|
| Facebook, LinkedIn, Twitter/X, Instagram, TikTok, YouTube (canales), GitHub, Pinterest, Bluesky, Threads, Mastodon (cualquier instancia) | una página HTML guardada | casi siempre vivas |
| WikiTree, Find a Grave, BillionGraves | una página HTML guardada (schema.org) | fallecidas |
| WikiTree | una respuesta JSON de su API, guardada (`.json`, un archivo por vez) | fallecidas, o vivas con lo mínimo |
| Cualquier sitio con una `Person` de schema.org (JSON-LD o microdatos) | una página HTML guardada | las que declare |
| Wikidata | un volcado local `.json`, `.json.gz` o `.json.bz2` (CC0; `.bz2` pide la extensión bz2 de PHP) | fallecidas |

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
- **El texto que escribió la persona (titular y descripción) se marca, no se borra.** Tres revisiones que se
  complementan (`src/Revision.php`), y las etiquetas salen en `marcas` con un aviso «leer el texto con cuidado»:
  1. El filtro de `../defamatory-content-review/` en su modo normal: datos personales como el RUT o un teléfono,
     insultos, acusaciones y datos sensibles dichos de **un tercero** («tiene cáncer», «la familia era judía»).
  2. El mismo filtro en su **modo biografía** (`bio-topics`): datos sensibles que alguien dice de **sí mismo**
     («tengo cáncer», «soy evangélica», «soy gay»), solo en español e inglés y con la forma «soy… / tengo…».
  3. Una lista corta propia (`src/Sensibles.php`: salud, religión, política o sindical, vida sexual, origen étnico) en
     español, inglés, francés, portugués, italiano y alemán. Cubre lo que lo anterior no ve: los otros cuatro idiomas
     y las biografías sin verbo («Mapuche. Archivera», «Militante comunista»). Si el filtro ya marcó una categoría, la
     de esta lista no se repite.

  **Es una ayuda, no una garantía:** las listas son cortas y de palabras sueltas, así que pueden marcar de más y,
  sobre todo, no marcar de menos. Que no salga ninguna marca no quiere decir que el texto esté limpio.
  **Cuesta:** unos 5 ms por texto (20.000 páginas con descripción: 90 s con revisión y 2,7 s con `--sin-revision`). Un
  perfil sin titular ni descripción no se revisa. Para lotes grandes, `--sin-revision` y revisar después solo lo que
  importe (por ejemplo, los candidatos del cruce).
- **Una publicación no es un perfil.** Si la dirección es de una publicación (`…/status/123`, `…/posts/…`,
  `/usuario/p/…`, `…/video/…`, `/pin/…`, `/watch`, un enlace `youtu.be/…`), no se guarda **ningún texto** de la página:
  ni la descripción, ni el nombre, ni el titular, ni el lugar, porque el título suele ser el texto de la publicación.
  Quedan la URL y el usuario, y sale un aviso. Una pestaña de un perfil (`/usuario/photos`, `/usuario/reels/`) no cuenta
  como publicación; una con identificador (`/usuario/photos/123`) sí.
- **Lista de exclusión (`--excluir=lista.txt`)** en `analizar.php`, `wikidata.php`, `wikidata-nombres.php` y
  `cruzar.php`: una entrada por línea, una URL o `red:usuario` (`twitter:ana`, `wikidata:Q42`, `wikitree:Ejemplo-1`);
  `#` es comentario. Una URL vale también para su versión móvil (`m.`), con barra final, con parámetros de rastreo
  (`utm_*`, `fbclid`, y `ref` solo en Facebook, `s` y `t` solo en X…) o con fragmento (menos el de los enlaces
  antiguos `#!/usuario`, que cuenta), y `twitter:@ana` es lo mismo que `twitter:ana` (`twitter:@` sin usuario falla en
  voz alta); en la exclusión todo va en minúsculas (se excluye de más antes que de menos), mientras que al quitar
  páginas repetidas se respetan las mayúsculas de los identificadores (`watch?v=AbC` no es `watch?v=abc`); un archivo con BOM de
  Windows se lee bien, y una lista sin ninguna entrada avisa. Sirve para que alguien que pidió retirarse no vuelva a salir al reprocesar
  (ver `RECLAMOS.md`). **No está conectada a la cola de pedidos de retiro del sitio** (`url-routing/`): se
  mantiene a mano, y quien decide un retiro tiene que agregar la entrada. `--excluir` solo evita que alguien
  vuelva a salir al reprocesar; para quitarlo de un JSONL ya generado está `bin/purgar.php` (línea por línea; las
  líneas que no son JSON se conservan sin revisar y el programa lo avisa). Una línea mal escrita hace fallar el
  programa en vez de ignorarse.
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
# (--max=N se detiene tras N personas; --progreso avisa por stderr cada millón de líneas)
php bin/wikidata.php latest-all.json.gz --pais=Q298 --capturado=2026-11-04 --progreso > wikidata.jsonl

# lo mismo, con una lista de personas excluidas y sin la revisión de texto
php bin/analizar.php --jsonl --excluir=retiros.txt --sin-revision carpeta-con-paginas/ > perfiles.jsonl

# una respuesta guardada de la API de WikiTree (los .json se pasan uno por uno, no por carpeta)
php bin/analizar.php --capturado=2026-11-04 --jsonl respuesta-wikitree.json >> perfiles.jsonl

# segundo recorrido del volcado: nombres de lugares y parientes de los perfiles de Wikidata
php bin/wikidata-nombres.php latest-all.json.gz wikidata.jsonl > wikidata-con-nombres.jsonl

# parejas de FALLECIDOS que podrían ser la misma persona, entre dos fuentes (pon como segundo el archivo más chico)
php bin/cruzar.php perfiles.jsonl wikidata.jsonl --idioma=spa --umbral=0.6 --anios=5 --palabras=2

# quitar a una persona de un archivo ya generado (--excluir solo la evita al reprocesar)
php bin/purgar.php perfiles.jsonl --excluir=retiros.txt --en-sitio

# validar un analizador contra una página real, sin mirar el HTML a mano
php bin/diagnostico.php pagina-guardada.html              # qué analizador la reconoció y qué campos le sacó
php bin/diagnostico.php --esqueleto pagina-guardada.html  # su estructura, con letras y dígitos enmascarados
```

Pruebas: `php pruebas/ejecutar.php`; análisis estático: `phpstan analyse` (nivel `max`, ver `phpstan.neon.dist`; la CI usa PHPStan 2.1).

## Nombres en Wikidata

`wikidata.php` deja los lugares y los parientes como identificadores (`Q2887`). `wikidata-nombres.php` hace un
segundo recorrido del volcado, lee solo las líneas que se pidieron y se detiene al encontrarlas todas: los
lugares ganan `lugar_nacimiento_nombre` y `lugar_muerte_nombre`. **El nombre de un pariente solo se resuelve si
el propio volcado lo da por fallecido**: un hijo o una hija de alguien que murió puede estar vivo, y de una
persona viva no se saca el nombre; esos parientes quedan con su identificador, `nombre` en null y un aviso. Los
parientes que están en la lista de exclusión se quitan del perfil. **Límite:** carga en memoria todos los perfiles de
`wikidata.jsonl` (necesita recorrerlos dos veces), a diferencia del resto de los programas, que leen línea por línea.

## La API de WikiTree

`WikiTreeApi` lee una respuesta JSON que ya guardaste (por ejemplo, la de la acción `getProfile`); no llama a la
API. Es más estable que el HTML, pero los campos que usa (`Name`, `FirstName`, `LastNameAtBirth`, `BirthDate`,
`DeathDate`, `IsLiving`, `Father`, `Mother`…) están escritos de memoria y **sin probar contra una respuesta
real**. Con `IsLiving` en verdadero, o con una `DeathDate` desconocida (`0000-00-00`), no sale ninguna fecha,
lugar ni pariente. Antes de usarla, revisa los términos de uso de la API de WikiTree: no lo hice.

## Validar con páginas reales

Es lo que más falta: los formatos están escritos de memoria. `bin/diagnostico.php` ayuda sin pedirte que copies
HTML a mano. Sin opciones dice qué analizador reconoció la página y qué campos le sacó (sí o no, con la ruta de la
URL enmascarada). Con `--esqueleto` escribe la estructura que ve el analizador (etiquetas, forma de sus valores) con
las letras cambiadas por `x` y los dígitos por `9`, conservando el largo y la puntuación: se puede compartir en un
PR o un mensaje sin compartir el nombre, el usuario ni la biografía de nadie. Con eso se ajusta el analizador y se
agrega una muestra **inventada** con esa misma forma a `pruebas/muestras/`. Las páginas reales no se suben al repo.

## El cruce

`bin/cruzar.php` pliega los nombres con `PhoneticFolderRegistry` (así «González» y «Gonsales»
coinciden), pide el nombre de pila de cada uno en el nombre del otro y, por defecto, al menos dos palabras en
común; descarta nacimientos a más de 5 años; suma puntos por año, lugar y pariente (por nombre) parecidos; y
escribe cada pareja como **candidata que requiere revisión humana**: nunca fusiona. Los umbrales son opciones
(`--umbral`, `--anios`, `--palabras`) y **los valores por defecto son una primera aproximación, no medida con datos
reales**: conviene mirar el puntaje y la evidencia de lo que sale y ajustarlos.

**Escala.** El archivo `b` se guarda como un índice compacto (por persona, solo lo necesario para comparar) y el `a` se
lee línea por línea. Con 20.000 contra 100.000 personas inventadas, los mismos candidatos que antes con 174 MB de pico
en vez de 688 MB; en tiempo la mejora fue modesta (17 s a 15 s), porque lo que más cuesta es leer el JSON y plegar los
nombres. Son unos 1,7 KB por persona de `b`: un millón ocupa del orden de 1,7 GB, y no hay un mecanismo para partir
el trabajo en bloques. **Omite todo perfil que pueda estar vivo** y cuenta cuántos omitió: un
perfil de red social no se empareja con registros de fallecidos ni con nada.

## Cómo agregar una red

Una clase en `src/` que extienda `Analizador` (`red()`, `dominios()` y `extraer()`),
registrada en `Detector::analizadores()` y en `src/cargar.php`, con su muestra en
`pruebas/muestras/` y sus comprobaciones en `pruebas/ejecutar.php`. Cualquier otra que
ponga Open Graph (Pleroma, Misskey…) se hace igual que `Twitter.php`; Mastodon, que no tiene un dominio fijo,
se reconoce por la forma del título (`Mastodon::reconoce()`).
