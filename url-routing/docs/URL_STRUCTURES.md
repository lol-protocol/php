# Estructura de URLs — Esquema Abstracto por Longitud

Ningún tipo de recurso aparece como palabra en la URL. El **tipo se infiere de la forma** del primer segmento, y cada forma significa siempre lo mismo:

| Forma | Regex | Significa |
|---|---|---|
| vacío | `^$` | inicio |
| `0` | `^0$` | cuenta |
| 1 letra | `^[a-z]$` | función del sistema: `b` búsqueda; en el POS, `c` carrito y `p` pago |
| 2 letras | `^[a-z]{2}$` | país (ISO 3166-1); detrás, región y ciudad de 1-3 letras (ISO 3166-2, UN/LOCODE) |
| 3 letras | `^[a-z]{3}$` | idioma (ISO 639-2): va en el subdominio (`spa.`, `eng.`), nunca en la ruta |
| 4+ letras | `^[a-z]{4,}$` | texto libre: lo que se busca, detrás de `/b/{tipo}/` |
| solo dígitos | `^\d+$` | una entidad: la cantidad de dígitos dice el tipo (ver tablas abajo); 1 dígito detrás de algo es una acción |
| con punto | `\.` | un archivo para buscadores: `robots.txt` y los sitemaps |

La lista completa de rutas de cada sitio, generada desde `config/routes/*.php`, está en [`RUTAS.md`](./RUTAS.md) (`php bin/rutas.php > docs/RUTAS.md`; un test falla si queda desactualizada).

Un 404 del router explica por qué, con lo que dice la forma de la URL: `/47/` sugiere `/0000000047/` (persona) y los demás anchos; una acción que no existe lista las que sí; `/eng/` recuerda que el idioma va en el subdominio. Nunca consulta la base de datos, así que no distingue un id inexistente de uno privado. Un método que la URL no acepta (un GET a `/c/1/`) es un **405** con la cabecera `Allow`.

El largo en dígitos escala con el tamaño esperado del catálogo de cada tipo: a mayor cardinalidad prevista, más dígitos. `persona` es el techo (10 dígitos); todo lo demás queda por debajo, en orden.

No se usan guiones ni guiones bajos en ningún segmento.

### URL canónica

Cada página tiene una sola URL: en minúsculas, con barras simples y con barra final (un archivo, como `/robots.txt`, termina en su extensión). Un GET a cualquier otra forma (`/MX/JAL`, `/6128473105`, `//mx//jal/`) recibe un **301** a la canónica, conservando la query string. Un POST nunca se redirige (el 301 lo convertiría en GET): se atiende donde llegó. Las URLs que se mudaron (el flujo de compra en palabras) también dan 301, vía el mapa `redirect` de `config/routes/pos.php`.

### Personas fusionadas

Cuando dos personas resultan ser la misma y una se fusiona en la otra, el id que desaparece queda en `persona_alias`: `/{id viejo}/` y sus acciones dan **301** a la misma página de la persona que quedó, en vez de 404. Un id que nunca existió sigue siendo 404.

La fusión se hace con `php bin/fusionar.php <duplicado> <que queda>`, que sin `--confirmar` solo muestra qué movería. En una transacción pasa a la que queda los hijos, vínculos, sucesos, organizaciones, colecciones y alias de la duplicada (sin duplicar lo que ya tenía), completa lo que le falte (padres, apellido, marca de vida), borra la duplicada y deja su id como alias. Se niega a fusionar a dos personas que no pueden ser la misma: madre o padre e hija o hijo, o dos que tienen un vínculo entre sí.

### Contrato de generación de ids (importante)

Un id **debe** generarse ya relleno con ceros al ancho fijo de su tipo. `persona #47` no es `/47/` (2 dígitos → caería en un tipo de 2 dígitos, que no existe, o peor, en uno que sí exista) — es `/0000000047/` (10 dígitos). El helper `enlace($tipo, $id)` en `index.php` hace este padding automáticamente consultando `Router::typeLength()`; nunca se debe construir un enlace concatenando el id "crudo" a mano.

### Sufijo de acción desconocido

Si el segundo segmento es numérico pero no está en el arreglo `actions` del tipo resuelto, el resultado es **404**, no un silencioso `show` del recurso base. Evita que una URL mal escrita parezca funcionar mostrando el contenido equivocado.

### Sin segmentos sobrantes

`/582317/1/basura/`, `/mx/jal/1/gdl/` o cualquier segmento después de lo que la forma del tipo permite también es **404**. Nada se trunca ni se ignora en silencio — incluye un 4° nivel de `lugar` (el tope es país/región/ciudad, 3 niveles).

### Dominio e idioma

`determineSite()` exige coincidencia exacta de dominio o un subdominio real (`.contrastocolor.local`), nunca un sufijo de cadena suelto — así `evilcontrastocolor.local` no se confunde con un subdominio legítimo. `determineLocale()` solo reconoce subdominios que están en la lista explícita de idiomas soportados (`spa`, `eng`); un subdominio de 3 letras que no sea un idioma real (`api.`, `cdn.`) nunca se interpreta como uno.

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

Confirmar crea la orden en una transacción: cada línea descuenta su stock solo si todavía alcanza (`UPDATE … WHERE stock >= cantidad`), así que dos compras a la vez no venden la última unidad dos veces; si una línea ya no alcanza, no cambia nada y se vuelve al carrito. La orden copia el nombre y el precio de cada línea, queda `pendiente` de pago, y la navegación sigue a `/{código}/`. Como no hay login, la orden se asocia a la sesión que la hizo: esa sesión la puede abrir, además del propietario.

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
