# Parsing-fb-in-mas

Lectores de **páginas de perfil ya guardadas** y de **volcados de datos abiertos**, para tener
un punto de partida al buscar a una persona en un proyecto de genealogía. PHP 8.3, sin
dependencias propias (el cruce usa el plegado fonético de `../defamatory-content-review/`,
como `phone-directory/`).

| Qué lee | Archivo | Personas |
|---|---|---|
| Facebook, LinkedIn, Twitter/X, Instagram, TikTok, YouTube (canales), GitHub, Pinterest | una página HTML guardada | casi siempre vivas |
| WikiTree, Find a Grave | una página HTML guardada (microdatos de schema.org) | fallecidas |
| Cualquier sitio con una `Person` de schema.org (JSON-LD o microdatos) | una página HTML guardada | las que declare |
| Wikidata | un volcado local `.json` o `.json.gz` (CC0) | fallecidas |

## Lo que hace y lo que no

- **No descarga nada, no inicia sesión, no sigue enlaces ni recorre listas de contactos.**
  Los tres programas de `bin/` rechazan una URL a propósito. Las redes prohíben en sus
  términos la extracción automática, y los datos de personas son justo lo que
  `PRIVACIDAD-Y-HONRA.md` (raíz) manda cuidar. Lo «masivo» aquí es procesar lo que tú
  ya bajaste (una carpeta entera, un volcado de millones de líneas), no recorrer sitios.
- **De una persona viva se guarda poco:** red, URL, usuario, nombre, titular, lugar,
  descripción (hasta 500 caracteres) y fecha de captura. Sin correo, teléfono, contactos,
  publicaciones ni contadores de seguidores. **Fechas de nacimiento y de muerte, lugares de
  vida y parientes se guardan solo si la fuente da una fecha de muerte**; si una página trae
  `birthDate` de alguien sin `deathDate`, esa fecha se descarta (hay pruebas).
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

# parejas de FALLECIDOS que podrían ser la misma persona, entre dos fuentes
php bin/cruzar.php wikidata.jsonl perfiles.jsonl --idioma=spa
```

Pruebas: `php pruebas/ejecutar.php`; análisis estático: `phpstan analyse` (nivel `max`, ver `phpstan.neon.dist`; la CI usa PHPStan 2.1).

Los lugares y los parientes de Wikidata salen como identificadores (`Q2887`): resolverlos pide
un segundo recorrido del volcado, que todavía no está.

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
ponga Open Graph (Mastodon, Bluesky, Threads…) se hace igual que `Twitter.php`.
