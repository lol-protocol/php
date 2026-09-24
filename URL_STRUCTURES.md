# Estructura de URLs — Esquema Abstracto por Longitud

Ningún tipo de recurso aparece como palabra en la URL. El **tipo se infiere de la forma** del primer segmento:

- **Solo dígitos** → el número de dígitos selecciona el tipo (ver tablas abajo).
- **Solo letras** → jerarquía de lugar (país/región/ciudad).
- **Coincidencia exacta** → rutas fijas de sistema (`cart`, `checkout`, `order`, `0` para cuenta).

El largo en dígitos escala con el tamaño esperado del catálogo de cada tipo: a mayor cardinalidad prevista, más dígitos. `persona` es el techo (10 dígitos); todo lo demás queda por debajo, en orden.

No se usan guiones ni guiones bajos en ningún segmento. Los códigos de lugar se normalizan a minúsculas (`/MX/` y `/mx/` son la misma URL).

### Contrato de generación de ids (importante)

Un id **debe** generarse ya relleno con ceros al ancho fijo de su tipo. `persona #47` no es `/47/` (2 dígitos → caería en un tipo de 2 dígitos, que no existe, o peor, en uno que sí exista) — es `/0000000047/` (10 dígitos). El helper `enlace($tipo, $id)` en `index.php` hace este padding automáticamente consultando `Router::typeLength()`; nunca se debe construir un enlace concatenando el id "crudo" a mano.

### Sufijo de acción desconocido

Si el segundo segmento es numérico pero no está en el arreglo `actions` del tipo resuelto, el resultado es **404**, no un silencioso `show` del recurso base. Evita que una URL mal escrita parezca funcionar mostrando el contenido equivocado.

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
| texto (2-4 letras) | lugar | jerárquico país/región/ciudad; no es un registro secuencial |

### Acciones (segundo segmento, numérico, con significado propio por tipo)

**persona** (10 dígitos):
| Código | Acción |
|---|---|
| 1 | ascendencia |
| 2 | descendencia |
| 3 | vínculos |
| 4 | cronología |

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

spa.tudominio.com/?t=10&q=texto       listado/búsqueda de personas (t = dígitos del tipo)
spa.tudominio.com/0/                  cuenta
spa.tudominio.com/0/1/                cuenta: mis colecciones
spa.tudominio.com/0/2/                cuenta: mis aportes
```

---

## 2. POS — Contrastocolor

| Formato | Tipo | Justificación de escala |
|---|---|---|
| 8 dígitos | producto | mayor cardinalidad del sitio, menor que persona |
| 7 dígitos | colección | colecciones / campañas |
| 6 dígitos | etiqueta | |
| 5 dígitos | atributo | color / variante — conjunto acotado |
| 4 dígitos | grupo | categoría — taxonomía pequeña |

### Acciones

**producto** (8 dígitos): `1` variantes · `2` atributos
**etiqueta** (6 dígitos): `1` productos con esa etiqueta
**atributo** (5 dígitos): `1` productos de ese color

### Flujo transaccional (excepción deliberada — palabras cortas en inglés, sin guiones)

```
/cart/
/checkout/
/checkout/shipping/
/checkout/payment/
/checkout/confirm/
/order/{id}/
/order/{id}/1/    invoice
/order/{id}/2/    track
/order/{id}/3/    devolucion
```

El id de `order` es secuencial simple (no requiere inferencia por largo, ya está bajo el prefijo `order`).

### Ejemplos

```
spa.contrastocolor.com/81372047/           producto
spa.contrastocolor.com/81372047/1/         variantes
spa.contrastocolor.com/81372047/2/         atributos (colores del producto)

spa.contrastocolor.com/48213/              atributo (color)
spa.contrastocolor.com/48213/1/            productos de ese color

spa.contrastocolor.com/?t=8&q=texto        listado/búsqueda de productos

spa.contrastocolor.com/cart/
spa.contrastocolor.com/checkout/payment/
spa.contrastocolor.com/order/8137204719000/
spa.contrastocolor.com/order/8137204719000/2/   track

spa.contrastocolor.com/0/                  cuenta
spa.contrastocolor.com/0/2/                cuenta: mis órdenes
spa.contrastocolor.com/0/3/                cuenta: deseos
```

---

## Lógica de despacho (router)

```
segmento vacío           -> reserved['']  (home)
literal exacto           -> literal[segmento]  (cart, checkout/*)
"order" + id + acción?   -> order         (POS)
"0" (único segmento)     -> reserved['0'] (cuenta)
solo dígitos             -> by_length[strlen(segmento)]
solo letras              -> place (lugar, jerárquico)
cualquier otra cosa      -> 404
```

Un segundo segmento numérico (cuando aplica) selecciona una acción dentro del tipo ya resuelto — su significado está definido por tipo, nunca es global.
