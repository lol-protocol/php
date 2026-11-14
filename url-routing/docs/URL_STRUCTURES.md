# Estructura de URLs — Esquema Abstracto por Longitud

Ningún tipo de recurso aparece como palabra en la URL. El **tipo se infiere de la forma** del primer segmento, y cada forma significa siempre lo mismo:

| Forma | Regex | Significa |
|---|---|---|
| vacío | `^$` | inicio |
| `0` | `^0$` | cuenta |
| 1 letra | `^[a-z]$` | función del sistema: `b` búsqueda; en genealogía, `r` pedir el retiro de una página; en el POS, `c` carrito y `p` pago |
| 2 letras | `^[a-z]{2}$` | país (ISO 3166-1); detrás, región y ciudad de 1-3 letras (ISO 3166-2, UN/LOCODE) |
| 3 letras | `^[a-z]{3}$` | idioma (ISO 639-2): va en el subdominio (`spa.`, `eng.`), nunca en la ruta |
| 4+ letras | `^[a-z]{4,}$` | texto libre: lo que se busca, detrás de `/b/{tipo}/` |
| solo dígitos | `^\d+$` | una entidad: la cantidad de dígitos dice el tipo (ver tablas abajo); 1 dígito detrás de algo es una acción |
| con punto | `\.` | un archivo: para buscadores (`robots.txt` y los sitemaps) y el script de los listados (`/b.js`, un archivo de `public/` que sirve el servidor web) |

La lista completa de rutas de cada sitio, generada desde `config/routes/*.php`, está en [`RUTAS.md`](./RUTAS.md) (`php bin/rutas.php > docs/RUTAS.md`; un test falla si queda desactualizada).

Un 404 del router explica por qué, con lo que dice la forma de la URL: `/47/` sugiere `/0000000047/` (persona) y los demás anchos; una acción que no existe lista las que sí; `/eng/` recuerda que el idioma va en el subdominio. Nunca consulta la base de datos, así que no distingue un id inexistente de uno privado. Un método que la URL no acepta (un GET a `/c/1/`) es un **405** con la cabecera `Allow`.

El largo en dígitos escala con el tamaño esperado del catálogo de cada tipo: a mayor cardinalidad prevista, más dígitos. `persona` es el techo (10 dígitos); todo lo demás queda por debajo, en orden.

No se usan guiones ni guiones bajos en ningún segmento.

### URL canónica

Cada página tiene una sola URL: en minúsculas, con barras simples y con barra final (un archivo, como `/robots.txt`, termina en su extensión). Un GET a cualquier otra forma (`/MX/JAL`, `/6128473105`, `//mx//jal/`) recibe un **301** a la canónica, conservando la query string. Un POST nunca se redirige (el 301 lo convertiría en GET): se atiende donde llegó. Las URLs que se mudaron (el flujo de compra en palabras) también dan 301, vía el mapa `redirect` de `config/routes/pos.php`.

### Personas fusionadas

Cuando dos personas resultan ser la misma y una se fusiona en la otra, el id que desaparece queda en `persona_alias`: `/{id viejo}/` y sus acciones dan **301** a la misma página de la persona que quedó, en vez de 404. Un id que nunca existió sigue siendo 404.

La fusión se hace con `php bin/fusionar.php <duplicado> <que queda>`, que sin `--confirmar` solo muestra qué movería. En una transacción pasa a la que queda los hijos, vínculos, sucesos, organizaciones, colecciones y alias de la duplicada (sin duplicar lo que ya tenía), completa lo que le falte (padres, apellido, marca de vida), borra la duplicada y deja su id como alias. Se niega a fusionar a dos personas que no pueden ser la misma: madre o padre e hija o hijo, o dos que tienen un vínculo entre sí.

### Grupos fusionados

Un apellido se escribe de más de una forma («García» y «Garcia»), y el segundo grupo que alguien crea por error es el duplicado más probable de todo el catálogo. Funciona igual que con las personas: el id del grupo que desaparece queda en `grupo_alias` y `/{id viejo}/`, `/{id viejo}/1/` y `/{id viejo}/2/` dan **301** a la misma página del grupo que quedó. Un id que nunca existió sigue siendo 404.

`php bin/fusionar.php grupo <duplicado> <que queda> [--confirmar]` (sin `--confirmar` solo muestra qué movería; sin la palabra `grupo` fusiona personas, como siempre). Pasa a las personas del duplicado al que queda, le completa el origen y la descripción que le falten, borra el duplicado y deja su id como alias. **Se niega** si uno de los dos tiene un pedido de retiro que lo oculta o lo retira: esa página sigue respondiendo 410 en su id viejo, y fusionarla antes enterraría la decisión.

### Contrato de generación de ids (importante)

Un id **debe** generarse ya relleno con ceros al ancho fijo de su tipo. `persona #47` no es `/47/` (2 dígitos → caería en un tipo de 2 dígitos, que no existe, o peor, en uno que sí exista) — es `/0000000047/` (10 dígitos). El helper `enlace($tipo, $id)` en `index.php` hace este padding automáticamente consultando `Router::typeLength()`; nunca se debe construir un enlace concatenando el id "crudo" a mano.

### Dígito verificador (opcional, se decide antes de abrir el sitio)

Hoy los ids son números corridos: `/6128473105/` y `/6128473106/` son dos personas vecinas, cualquiera puede recorrer el catálogo desde el 1 y un id mal copiado es indistinguible de uno que no existe. Con `IDS_DIGITO_VERIFICADOR=true` (apagado por omisión) **el último dígito de cada id es de verificación**, calculado con los anteriores (algoritmo de Damm: detecta todo error de un dígito y todo cambio de dos dígitos contiguos, lo que Luhn no hace con 09 y 90). Un id cuyo dígito no coincide da **404 sin consultar la base**, con la pista «el último dígito de un id es de verificación y este no coincide».

- **No es un interruptor.** Encenderlo sobre ids que no lo traen hace que casi todos den 404. Cada id de la base tiene que terminar en su dígito, incluidos los de `persona_alias` y `grupo_alias`. Por eso se decide ahora, que solo hay datos de demostración: después de abrir, cambiar un id rompe los enlaces que ya circulan.
- **Quien crea ids** los completa: `php bin/id.php completar <número> [<ancho>]` (`completar 612847310 10` da `6128473101`) y `php bin/id.php verificar <id>`; en PHP, `DigitoVerificador::completar()`. Tampoco cabe un id más corto: el ancho de cada tipo no cambia, el dígito ocupa el último lugar (una persona tiene 9 dígitos de número y 1 de verificación).
- **Los códigos de las órdenes quedan fuera** (`'verificador' => false` en su entrada): son aleatorios, y su protección es no poder adivinarse.
- **Qué NO hace:** no vuelve secretos los ids (con 9 dígitos libres siguen siendo recorribles; el dígito solo corta lo que se inventa o se escribe mal) ni protege nada privado: eso lo siguen haciendo `Privacidad` y `AccessPolicy`. Si lo que se quiere es que el catálogo no se pueda recorrer, la salida es un id aleatorio como el de las órdenes, y eso sí cambia las URL de otra forma.
- El ancho fijo y el relleno con ceros no cambian (`enlace()` sigue igual); con la opción encendida, ya no se sugiere el id «rellenado» de un id corto, porque tampoco sería válido.

### Filtros con barra: `/1001/48213/`

Una categoría se puede estrechar con más entidades **como segmentos de la ruta**, no como `?color=…`: `/1001/48213/` es la categoría 1001 (Ropa) con el atributo 48213 (azul marino), y `/1001/48213/520002/` suma la etiqueta 520002. Qué es cada segmento lo dice su **ancho**, como en cualquier otro id; y un segundo segmento de **un dígito sigue siendo una acción**, así que no chocan (los filtros tienen 2 o más).

- **Qué tipos filtran qué se declara en la configuración**: `'filtros' => ['atributo', 'etiqueta']` en la entrada de `by_length` (hoy solo la categoría del POS; la gramática vale para cualquier tipo). Un tipo que no la declara sigue sin aceptar nada tras el id (`/48213/1001/` es 404), y la configuración se valida al cargarla.
- **Una sola URL por página**: los filtros van en un orden fijo (el id más corto primero: atributo, luego etiqueta), `filtrado()` / `UrlHelper::filtrado()` los escribe así, y cualquier otro orden da **301** a ese. Un filtro que no existe, un tipo repetido (dos colores), un tipo que no filtra o más filtros que tipos son **404**; una combinación válida sin productos es una página vacía (200), no un error.
- **Solo se ofrecen filtros que llevan a algo**: la categoría muestra los atributos y etiquetas que algún producto activo suyo tiene (nada que termine en una página vacía), los aplicados con un ✕ que quita ese filtro y conserva los demás.
- **Una página filtrada no se indexa** (`noindex`) ni va en el sitemap: es una combinación más de los mismos productos. Los enlaces para agregar un filtro llevan `rel="nofollow"`. El dígito verificador, si está activo, también revisa los ids de los filtros.
- **En la búsqueda (`/b/…`) no hay filtros por ahora**: allí el segmento tras el texto ya es la página, y un texto de solo dígitos también lleva la suya (`/b/8/2026/1/`), así que un filtro de 4 dígitos chocaría con una página 2026. Habría que fijar otra posición antes de agregarlos.

### Qué URLs dan 404 o 301 (y cuántas veces)

Cada respuesta 404 o 301 se anota como **la forma** de su URL, nunca la URL: un 404 es justo donde el nombre de alguien termina escrito en una ruta (`/maria-garcia/`). La forma dice de qué tipo era cada segmento y nada más: un id de 10 dígitos es `#10`, la cuenta es `0`, una letra queda como está (`b`, `c`), 2 y 3 letras son `aa` y `aaa`, 4 o más `a4+`, un archivo que suele pedir un escáner (`wp-login.php`, `.env`) es `*.php` o `*.env` (lista cerrada de extensiones: `juan.perez` no es una extensión, es un `?`), una palabra que el propio sitio enruta (`robots.txt`, `cart`) queda tal cual, y cualquier otra cosa es `?`. No se guarda la query, ni la IP, ni el navegador. Un 301 anota también la forma de adonde fue.

- Es un archivo por día en `URL_LOG_DIR` (por omisión una carpeta del directorio temporal), con una línea por petición. Un día deja de crecer a los 5 MB (una inundación de 404 no llena el disco), se guardan 60 días y escribir nunca rompe una página. `URL_LOG_DIR=off` lo apaga.
- `php bin/urls-informe.php [--dias=7] [--maximo=20]` lo resume: qué formas dan 404 y cuáles se redirigen, de más a menos frecuentes. Sirve para dos cosas: encontrar enlaces rotos (un `#10` que se repite es un id que alguien sigue enlazando), y saber cuándo una redirección vieja (`/cart/` → `/c/`) ya no recibe visitas y se puede quitar del mapa `redirect`.
- La forma conserva si la ruta terminaba en barra, porque es lo que distingue `/6128473105` de `/6128473105/` en un 301 de URL no canónica.

### Los enlaces de las páginas son canónicos

`tests/App/Integration/EnlacesTest.php` recorre, con un servidor real, todas las páginas de los dos sitios sembrados como lo hace un buscador: parte de la portada, de los listados y de **todas las URL del sitemap** (que así quedan comprobadas: cada una existe y es la canónica) y sigue cada enlace `<a href="/…">`. Falla si alguno redirige (un 301 es un enlace escrito a mano: `enlace()` y `accion()` existen para que nadie lo haga) o da 404. Lo hace como visitante y como propietario. Lo único que no sigue: la cuenta, que a un visitante le responde 401 (es el diseño, y esa prueba exige el 401).

Ya encontró un defecto: al propietario se le ofrecía «Pedir el retiro de esta página» en sucesos y registros que un visitante no puede ver, y ese formulario responde 404.

### Sufijo de acción desconocido

Si el segundo segmento es numérico pero no está en el arreglo `actions` del tipo resuelto, el resultado es **404**, no un silencioso `show` del recurso base. Evita que una URL mal escrita parezca funcionar mostrando el contenido equivocado.

### Sin segmentos sobrantes

`/582317/1/basura/`, `/mx/jal/1/gdl/` o cualquier segmento después de lo que la forma del tipo permite también es **404**. Nada se trunca ni se ignora en silencio — incluye un 4° nivel de `lugar` (el tope es país/región/ciudad, 3 niveles).

### Dominio e idioma

`determineSite()` exige coincidencia exacta de dominio o un subdominio real (`.contrastocolor.local`), nunca un sufijo de cadena suelto — así `evilcontrastocolor.local` no se confunde con un subdominio legítimo. `HostParser::getLocale()` solo reconoce los subdominios de la lista de idiomas **activos**; un subdominio de 3 letras que no sea uno de ellos (`api.`, `cdn.`) nunca se interpreta como idioma.

El URL conoce 30 idiomas (`HostParser::IDIOMAS_CONOCIDOS`, ISO 639-2/T, con su código `hreflang`), pero **sirve** solo los de `IDIOMAS_ACTIVOS` en `.env` (por omisión `spa,eng`; el español siempre está). Activar uno es una línea en `.env`: `hreflang`, `<html lang>` y el reconocimiento del subdominio salen solos. Lo que no sale solo: el DNS y el certificado del subdominio (un comodín `*.dominio` cubre todos; el ejemplo de nginx ya lo acepta), los textos (ver abajo) y los meses de `Format::MESES` (un idioma que no esté ahí muestra las fechas con meses en español). Por eso la lista es de **activos**: anunciar un idioma en `hreflang` antes de que sus páginas estén en ese idioma le miente al buscador.

**Los textos.** Los que pasan por `t('clave')` (`Support/Textos.php`) salen en el idioma del subdominio, desde una tabla por idioma, `lang/{código}.php` (`lang/spa.php` es la fuente y `lang/eng.php` la traducción). Hoy son **el encabezado y el pie de todas las páginas** (tipos de búsqueda, «Mi cuenta», «Pedir el retiro…», el carrito del POS), **las páginas de error** (404, 401, 403, 405, 500 y el 429 de los pedidos de retiro), **el formulario de pedir el retiro** con sus mensajes y **la página retirada**. **Todo lo demás sigue en español**: las páginas de personas, lugares, colecciones y productos, las solicitudes de datos (`/s/`), la entrega con clave (`/e/`), la cuenta del propietario y las pistas técnicas del 404. Por eso, mientras eso siga así, `hreflang` le promete al buscador más de lo que hay: la lista de activos por omisión es `spa,eng`, y solo esas superficies están en inglés.

Para **traducir otro texto**, agrega la clave en `lang/spa.php` y en cada idioma que ya tenga tabla, con las mismas `{marcas}`, y pídelo con `t()` en una vista o con `Textos::t()` en código (el texto sale **sin escapar**: escápalo donde lo muestres). `TextosTest` falla si una tabla tiene claves de más o de menos, si las `{marcas}` no coinciden, o si el código pide una clave que `lang/spa.php` no tiene. Un idioma activo que no tenga tabla dice todo en español, nunca una clave. Para **un idioma nuevo**, crea `lang/{código}.php` con todas las claves (el nombre es el código de tres letras de `IDIOMAS_CONOCIDOS`) y agrégalo a `IDIOMAS_ACTIVOS`. Los textos que ve el propietario (el correo del cron, la cola `/0/3/`) no se traducen: son para quien administra el sitio.

### Headroom para tipos futuros

Los largos de dígito no usados por debajo del tipo más chico de cada sitio (1-4 en genealogía, 1-3 en POS) quedan deliberadamente libres, para poder agregar un tipo nuevo de baja cardinalidad más adelante sin tener que renumerar ningún tipo existente.

## Idiomas

Subdominios de 3 letras (ISO 639-2): `spa.` (español), `eng.` (inglés), etc. La ruta es idéntica entre idiomas — solo cambia el subdominio.

---

## 1. Genealogía

| Formato | Tipo | Justificación de escala |
|---|---|---|
| 10 dígitos | persona | mayor cardinalidad esperada (individuos) |
| 9 dígitos | suceso | eventos ligados a personas |
| 8 dígitos | registro | documentos / fuentes |
| 7 dígitos | colección | árboles genealógicos |
| 6 dígitos | grupo | apellidos / familias |
| 5 dígitos | organización | instituciones / archivos |
| letras: país (2) / región / ciudad (1-3) | lugar | jerárquico; no es un registro secuencial |

### Acciones (segundo segmento, numérico, con significado propio por tipo)

**persona** (10 dígitos):
| Código | Acción |
|---|---|
| 1 | ascendencia |
| 2 | descendencia |
| 3 | vínculos |
| 4 | cronología |
| 5 | privacidad (solo el propietario: marca si la persona vive) |

**colección** (7 dígitos): `1` vista · `2` editar · `3` exportar
**grupo** (6 dígitos): `1` red · `2` dispersión
**organización** (5 dígitos): `1` miembros · `2` registros
**registro** (8 dígitos): `1` fuente
**lugar** (texto): `1` personas de ese lugar · `2` sucesos de ese lugar

### Ejemplos

```
spa.tudominio.com/6128473190/         persona
spa.tudominio.com/6128473190/1/       ascendencia
spa.tudominio.com/6128473190/2/       descendencia
spa.tudominio.com/6128473190/3/       vínculos
spa.tudominio.com/6128473190/4/       cronología

spa.tudominio.com/1048293/            colección (árbol)
spa.tudominio.com/1048293/1/          vista
spa.tudominio.com/1048293/3/          exportar

spa.tudominio.com/582317/             grupo (apellido)
spa.tudominio.com/582317/1/           red (árbol del apellido)

spa.tudominio.com/mx/                 lugar: país
spa.tudominio.com/mx/jal/             lugar: región
spa.tudominio.com/mx/jal/gdl/         lugar: ciudad
spa.tudominio.com/mx/jal/gdl/1/       personas de esa ciudad

spa.tudominio.com/b/10/              todas las personas (10 = dígitos del tipo)
spa.tudominio.com/b/10/garcia/       búsqueda de personas
spa.tudominio.com/b/10/garcia/2/     página 2
spa.tudominio.com/0/                  cuenta
spa.tudominio.com/0/1/                cuenta: mis colecciones
spa.tudominio.com/0/2/                cuenta: mis aportes

spa.tudominio.com/robots.txt          deja fuera /0/ y apunta al sitemap
spa.tudominio.com/sitemap.xml         índice de sitemaps
spa.tudominio.com/sitemap/10/1.xml    personas, parte 1 (50.000 URLs por parte)
spa.tudominio.com/sitemap/l/1.xml     lugares
```

Las páginas de una persona oculta (viva o privada) llevan `<meta name="robots" content="noindex">`. Las de lugares, personas y productos llevan migas de pan (`Inicio › México › Jalisco › Guadalajara`), también como `BreadcrumbList` en JSON-LD; la ficha de una persona visible se describe como `Person` (nombre, nacimiento, defunción) y la de un producto como `Product` (precio, moneda, disponibilidad). Una persona oculta no emite nada sobre sí. Toda página declara su URL canónica (`rel="canonical"`) y la misma ruta en los otros idiomas (`hreflang` `es`, `en` y `x-default` en el dominio sin subdominio).

El sitemap lista solo lo que un visitante puede ver, lo pida quien lo pida: ninguna persona que podría estar viva o que solo está en colecciones privadas, ningún suceso ni registro que la delataría, ninguna colección privada. Cada parte se nombra por el largo de dígitos de su tipo (`10`, `9`, `8`, `7`, `6`, `5`) o `l` para lugares. Las URLs absolutas usan `SITE_URL_GENEALOGY` (o, sin ella, el esquema y el host de la petición).

Cada petición vuelve a preguntar a la base, así que una página que se oculta o se retira sale del sitemap en ese momento. Lo que un buscador sí se ahorra es la descarga:

- Cada archivo sale con `ETag` y `Cache-Control: no-cache` (se puede guardar, pero se pregunta cada vez). Una petición con el `If-None-Match` de la copia actual recibe un **304** sin cuerpo.
- `Support/SitemapEstado.php` recuerda, por archivo, una huella de lo que lista y **desde cuándo lista exactamente eso**. Esa fecha es el `<lastmod>` de cada parte en el índice y el `Last-Modified` del archivo (y `If-Modified-Since` también da 304). Se mueve cuando entra o sale una página, no cuando se vuelve a generar el archivo.
- Una parte que nunca se sirvió no lleva `<lastmod>` en el índice hasta que alguien la pida: mejor sin fecha que con una inventada. Cada página lleva además su propio `<lastmod>` (un día, `AAAA-MM-DD`) si su fila tiene `actualizado_en` (migración 010; genealogía: personas, sucesos, registros, colecciones, grupos y organizaciones). Reglas de esa columna:
  - **Está vacía hasta que alguien la escribe**, y una fila sin fecha no lleva `<lastmod>`: un buscador deja de creerle a un sitio cuyas fechas no se mueven, así que mejor ninguna que una inventada.
  - **La mueve quien cambia el contenido de la página**: una importación o una edición pone `actualizado_en = CURRENT_TIMESTAMP` en esa fila (este repositorio no trae importador ni editor: ese es el contrato para quien los escriba). Las herramientas de `bin/` ya lo hacen: al fusionar personas se mueven la que queda y sus hijos; al fusionar grupos, el que queda y las personas que recibe; y al suprimir una persona, las páginas que pierden algo (sus hijos, sus vínculos, las organizaciones y colecciones en que estaba y los sucesos que compartía).
  - **No la mueve** un cambio de quién puede ver la página (marcar una persona como viva, un pedido de retiro): no cambia lo que la página dice, y la fecha delataría el cambio.
  - Una persona toma además la fecha del último de sus **sucesos visibles** (la página los lista): si cambia un suceso, su página cambió. Un cambio en otra fila relacionada (el nombre de un pariente) no la mueve sola: la fecha es la de la fila de la página.
  - Los lugares y el POS no tienen la columna y no llevan fecha por página.
- El estado son archivos chicos en `SITEMAP_ESTADO_DIR` (por omisión, una carpeta en el directorio temporal). Si se pierde, las fechas empiezan de nuevo; si no se puede escribir, no hay fechas y el sitemap se sirve igual.
- Lo único que se guarda unos minutos es **cuántas páginas tiene cada tipo** (5 minutos), porque contarlas es la consulta cara del índice y solo decide cuántos archivos anuncia. Las páginas de cada archivo siguen saliendo de la base en cada petición: una persona que se oculta o se retira sale del sitemap al instante. Una página nueva que haga falta una parte más puede tardar hasta 5 minutos en anunciarse en el índice.

### Listados que cargan más al bajar (sin una URL nueva)

`/b/{tipo}/[{texto}/][{página}/]` sigue siendo paginado: cada página tiene su URL, su enlace «← Anterior» y «Siguiente →» (con `rel="prev"` y `rel="next"`) y es lo que ve un buscador o un navegador sin JavaScript. Encima, el script `/b.js` (cargado por el listado) lo mejora: al llegar al final de la lista pide la página siguiente y la agrega, sin salir de la página en que está.

- **Pide la misma URL** del enlace «Siguiente →», con la cabecera `X-Fragmento: 1`, y el servidor contesta **solo la lista y la navegación**, sin la página alrededor. No hay una forma de URL nueva que aprender ni que enlazar: sin la cabecera (un buscador, `curl`, un navegador sin JavaScript) la respuesta es la página de siempre. Solo el valor exacto `1` pide el fragmento.
- Como la misma URL contesta de dos maneras, todo listado manda `Vary: X-Fragmento`. (Las páginas ya van con `Cache-Control: no-store`, así que ninguna caché las guarda; el `Vary` es por si algún día lo hacen.)
- El fragmento tampoco consume el aviso de una sola vez (`Flash`) que el diseño completo sí muestra y borra, que es una razón para no pedir la página entera y extraerle la lista.
- **El script es un archivo de `public/`**: nginx y Apache sirven un archivo que existe por su cuenta, sin pasar por PHP, y la política CSP (`script-src 'self'`) lo permite porque es del mismo origen. No hay scripts en línea. El servidor integrado de PHP (desarrollo y pruebas) entrega todo al router, así que `public/index.php` le cede los archivos que existen bajo `public/` (nunca uno oculto, un `.php`, ni uno fuera de la carpeta).
- Se probó en Chromium: de 50 a 130 filas sin repetidas, la URL del navegador no cambia, sin JavaScript queda el enlace, y si el servidor falla el enlace vuelve a verse.

### Qué no se indexa

`robots.txt` deja fuera `/0/`, `/b/` (y en el POS `/c/` y `/p/`), pero eso solo le dice a un buscador que no recorra esos caminos: una URL que alguien enlace desde otro sitio puede aparecer igual en los resultados. Por eso cada ruta funcional lleva `'noindex' => true` en su entrada de `reserved` (`config/routes/*.php`) y el router responde con `X-Robots-Tag: noindex`: la cuenta, la búsqueda, el carrito, el pago y pedir un retiro (`/r/{id}/`). La exportación GEDCOM (`/{colección}/3/`) la manda también, porque es un archivo y no una página. Una prueba exige que todo lo que `robots.txt` excluye lleve `noindex`, para que las dos listas no se separen.

Una cabecera sirve donde un `<meta>` no puede (un archivo que no es HTML). La excepción son las páginas de una persona oculta, que ya llevan el `<meta name="robots" content="noindex">` en su HTML.

---

## 2. POS — Contrastocolor

| Formato | Tipo | Justificación de escala |
|---|---|---|
| 8 dígitos | producto | mayor cardinalidad del sitio, menor que persona |
| 7 dígitos | colección | colecciones / campañas |
| 6 dígitos | etiqueta | |
| 5 dígitos | atributo | color / variante — conjunto acotado |
| 4 dígitos | grupo | categoría — taxonomía pequeña |
| 16 dígitos | orden | código aleatorio, no secuencial (ver abajo) |

### Acciones

**producto** (8 dígitos): `1` variantes · `2` atributos
**etiqueta** (6 dígitos): `1` productos con esa etiqueta
**atributo** (5 dígitos): `1` productos de ese color

**orden** (16 dígitos): `1` factura · `2` seguimiento · `3` devolución

### Flujo de compra: símbolos de una letra, no palabras

El carrito y el pago no tienen un id que contar, así que usan una letra que se lee igual en español, inglés y la mayoría de los idiomas con alfabeto latino. Sus pasos son acciones numéricas, como en el resto del sitio.

```
/c/          carrito (c = carrito / cart / cesta)
/c/1/        agregar      (solo POST, con token CSRF)
/c/2/        actualizar   (solo POST)
/c/3/        quitar       (solo POST)
/c/4/        vaciar       (solo POST)
/p/          pago (p = pago / pay / paiement): resumen de la compra
/p/1/        envío: la dirección (GET el formulario, POST la guarda en la sesión)
/p/2/        pago: por ahora no se cobra en línea
/p/3/        confirmar (GET el resumen, POST crea la orden)
```

Confirmar crea la orden en una transacción: cada línea descuenta su stock solo si todavía alcanza (`UPDATE … WHERE stock >= cantidad`), así que dos compras a la vez no venden la última unidad dos veces; si una línea ya no alcanza, no cambia nada y se vuelve al carrito. La orden copia el nombre y el precio de cada línea, queda `pendiente` de pago, y la navegación sigue a `/{código}/`. Como no hay cuentas de usuario, la orden se asocia a la sesión que la hizo: esa sesión la puede abrir, además del propietario.

Las URLs antiguas en palabras (`/cart/`, `/checkout/`, `/checkout/shipping/`...) dan 301 a las nuevas.

### Órdenes: código aleatorio de 16 dígitos

La URL de una orden no lleva su id (que es correlativo y se podría recorrer) sino `ordenes.codigo`: 16 dígitos aleatorios, únicos, de ancho fijo. Como cualquier otro tipo, el router la reconoce por el largo: `/{16 dígitos}/`. El checkout lo genera con `random_int()`. Una orden la ven solo el propietario y la sesión que la hizo (ver [`ACCESO.md`](./ACCESO.md)) y nunca aparecen en la búsqueda (`/b/16/` es un 400).

### Ejemplos

```
spa.contrastocolor.com/81372047/           producto
spa.contrastocolor.com/81372047/1/         variantes
spa.contrastocolor.com/81372047/2/         atributos (colores del producto)

spa.contrastocolor.com/48213/              atributo (color)
spa.contrastocolor.com/48213/1/            productos de ese color

spa.contrastocolor.com/b/8/gorra/         búsqueda de productos
spa.contrastocolor.com/b/8/2026/1/        un texto de solo dígitos lleva su página: solo, sería una página

spa.contrastocolor.com/sitemap.xml         índice: productos a la venta, colecciones, etiquetas, atributos y categorías (nunca órdenes)

spa.contrastocolor.com/c/                  carrito
spa.contrastocolor.com/p/2/                pago
spa.contrastocolor.com/4821937065182734/   orden
spa.contrastocolor.com/4821937065182734/2/ seguimiento

spa.contrastocolor.com/0/                  cuenta
spa.contrastocolor.com/0/2/                cuenta: mis órdenes
spa.contrastocolor.com/0/3/                cuenta: deseos
```

---

## Lógica de despacho (router)

```
GET no canónico          -> 301 a la URL canónica (o a donde se mudó, `redirect`)
segmento vacío           -> reserved['']  (home; /?t=&q= del buscador -> 301 a /b/)
nombre de archivo        -> archivos[primer segmento]  (robots.txt, sitemap)
solo dígitos             -> by_length[strlen(segmento)]
2 letras (país)          -> place (lugar, jerárquico; solo genealogía)
segmento reservado       -> reserved[segmento]  ('0' cuenta; 'b' búsqueda; POS: 'c', 'p')
cualquier otra cosa      -> 404, con la pista de por qué
```

Un segundo segmento numérico (cuando aplica) selecciona una acción dentro del tipo ya resuelto — su significado está definido por tipo, nunca es global.
