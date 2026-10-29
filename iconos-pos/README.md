# Iconos del punto de venta (SVG 2.5D)

23 iconos SVG de 64x64 en estilo 2.5D (formas con extrusión y sombra suave),
**sin letras, números ni pictogramas**, así que no dependen del idioma. 69
archivos: 3 variantes × 23 iconos. Son los de las pantallas del sitio de punto
de venta de `url-routing/` (producto, colección, etiqueta, grupo, orden,
devolución, cuenta del cliente, carrito y pago); tres de ellos
(`ui_profile`, `ui_settings` y `action_search`) son los mismos de los otros sets.

Para verlos todos, abre `galeria-iconos.html` desde la raíz del repositorio.

## Variantes

- `color/`: gradientes, extrusión y sombra.
- `lineas/`: solo contornos, sin rellenos, como un dibujo de alambre: los volúmenes que se solapan se ven
  transparentes (también los que en `color` son translúcidos); solo se ocultan los enlaces y las marcas que
  quedan detrás de un volumen.
  Los colores se pueden cambiar desde la página (ver «Colores de `lineas`»).
- `gris/`: el mismo dibujo en grises neutros que conservan el volumen.

Las tres variantes tienen exactamente los mismos nombres y carpetas.

## Estructura y nombres

`<variante>/<prefijo>/<prefijo>_<descripcion>.svg`: la carpeta coincide con el
prefijo del nombre; los nombres van en minúsculas, ASCII, con guion bajo y en
inglés.

| Prefijo | Iconos | Contenido | Ejemplo |
|---|---|---|---|
| `catalog_` | 6 | Lo que se vende: producto, variantes, atributos, colección, etiqueta y grupo | `catalog_variants` |
| `order_` | 4 | Lo que se compró: pedidos, factura, seguimiento y devolución | `order_tracking` |
| `account_` | 2 | Lo que guarda el cliente: deseos y direcciones | `account_wishlist` |
| `cart_` | 5 | El carrito: la cesta y añadir, actualizar, quitar y vaciar | `cart_add` |
| `checkout_` | 3 | El pago: envío, pago y confirmar | `checkout_payment` |
| `ui_` | 2 | Perfil y preferencias, los mismos de genealogía y biblioteca | `ui_profile` |
| `action_` | 1 | Búsqueda, la misma de genealogía | `action_search` |

## Cómo se leen

Usan la gramática de los otros sets: pocas formas geométricas que se leen por
forma, tono y posición.

- `catalog_product`: un volumen con la argolla de su etiqueta. `catalog_variants`:
  el mismo volumen en tres tonos, escalonados. `catalog_attributes`: filas de
  una muestra de color y su valor.
- `order_history`: dos volúmenes sobre una bandeja. `order_invoice`: un
  documento con filas de concepto e importe y la barra del total. `order_tracking`:
  la ruta recorrida (trazo continuo, nodos verdes), el paquete en su punto
  actual (disco naranja) y el destino por llegar (anillo discontinuo).
- `account_wishlist`: un volumen rosa con una gema, lo deseado y aún no
  comprado. `account_addresses`: dos marcadores, cada uno con su ondulación
  en el suelo.
- `catalog_collection`: varios productos juntos, en un marco discontinuo.
  `catalog_tag`: un rótulo con su agujero. `catalog_group`: tres discos que se
  tocan. `order_return`: el volumen y un arco que vuelve hacia atrás.
- `cart_`: la cesta (un trapecio azul) con los productos que asoman. La acción
  se lee por la insignia y por lo que hay dentro: `cart_add` lleva el «+» verde
  y un producto más, `cart_remove` el «−» rojo y uno solo, `cart_update` las dos
  flechas curvas ámbar, y `cart_clear` la cesta vacía con los contornos
  discontinuos de lo que había.
- `checkout_shipping`: el paquete y su flecha. `checkout_payment`: una tarjeta
  con su banda y su chip. `checkout_confirm`: el pedido con la insignia verde
  de visto bueno.

Al ser abstractos, conviene acompañar cada icono con su etiqueta en la
interfaz. Para acciones y elementos de interfaz que no están aquí (añadir,
filtrar...) usa los de `iconos-genealogia/` o `iconos-biblioteca/`.

## Uso

```html
<img src="iconos-pos/color/order/order_tracking.svg" alt="Seguimiento" width="48" height="48">
```

Los `id` internos (los gradientes, con el nombre del icono y, en `gris`, el
sufijo `_g`) son únicos entre los iconos y las variantes del set, así que puedes
incrustar varios SVG en línea en la misma página, también `color` y `gris` de un
mismo icono.

## Colores de `lineas`

Cada trazo de `lineas/` lleva el color de su tono (`#883307` para `orange`...), y
ese color se puede sobrescribir con una variable CSS `--ic-<tono>`. Solo
funciona con el SVG **en línea** en la página (`<svg>` pegado o inyectado en el
HTML): con `<img>` o `background: url(...)` los iconos se ven con sus colores de
siempre, porque la página no llega al interior del archivo.

```css
.barra svg { --ic-orange: currentColor; --ic-green: currentColor; }
.alerta svg { --ic-orange: crimson; }   /* solo cambia lo naranja */
```

Los tonos que usa un icono están en su `<style>` (`.ic-orange`, `.ic-green`...).
Un renderizador sin variables CSS (por ejemplo `resvg`) ignora esa parte y dibuja
los colores originales. Las reglas van acotadas a la clase `icono25d` que lleva la raíz del SVG
(un `<style>` en línea vale para todo el documento), así que no cambian nada de la página fuera del icono.

## Regenerar y validar

Los SVG son salida generada, no se editan a mano — se definen en
`iconos-tools/pos.py` y se regeneran con `iconos-tools/build.py`. Ver
`iconos-tools/README.md` para el generador, el validador y el workflow de CI.

## Limitaciones conocidas

- Sin pictogramas, los iconos no se reconocen a primera vista: hay que
  aprender el código o acompañarlos de su etiqueta.
- El efecto 2.5D es una extrusión hacia abajo y a la derecha con sombra
  proyectada; no es una proyección isométrica estricta.
- `ui_profile`, `ui_settings` y `action_search` no se dibujan en `pos.py` sino en
  `comunes.py`, para que sean idénticos en los sets que los comparten.
