# Iconos del punto de venta (SVG 2.5D)

8 iconos SVG de 64x64 en estilo 2.5D (formas con extrusión y sombra suave),
**sin letras, números ni pictogramas**, así que no dependen del idioma. 24
archivos: 3 variantes × 8 iconos. Son los de las pantallas del sitio de punto
de venta de `url-routing/` (producto, orden y cuenta del cliente) que no tenían
icono en los otros dos sets.

Para verlos todos, abre `galeria-iconos.html` desde la raíz del repositorio.

## Variantes

- `color/`: gradientes, extrusión y sombra.
- `lineas/`: solo contornos, sin rellenos; las partes tapadas por otra forma no se dibujan.
  Los colores se pueden cambiar desde la página (ver «Colores de `lineas`»).
- `gris/`: el mismo dibujo en grises neutros que conservan el volumen.

Las tres variantes tienen exactamente los mismos nombres y carpetas.

## Estructura y nombres

`<variante>/<prefijo>/<prefijo>_<descripcion>.svg`: la carpeta coincide con el
prefijo del nombre; los nombres van en minúsculas, ASCII, con guion bajo y en
inglés.

| Prefijo | Iconos | Contenido | Ejemplo |
|---|---|---|---|
| `catalog_` | 3 | Lo que se vende: producto, variantes, atributos | `catalog_variants` |
| `order_` | 3 | Lo que se compró: pedidos, factura, seguimiento | `order_tracking` |
| `account_` | 2 | Lo que guarda el cliente: deseos y direcciones | `account_wishlist` |

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

Al ser abstractos, conviene acompañar cada icono con su etiqueta en la
interfaz. Para acciones y elementos de interfaz comunes (añadir, buscar,
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
los colores originales.

## Regenerar y validar

Los SVG son salida generada, no se editan a mano — se definen en
`iconos-tools/pos.py` y se regeneran con `iconos-tools/build.py`. Ver
`iconos-tools/README.md` para el generador, el validador y el workflow de CI.

## Limitaciones conocidas

- Sin pictogramas, los iconos no se reconocen a primera vista: hay que
  aprender el código o acompañarlos de su etiqueta.
- El efecto 2.5D es una extrusión hacia abajo y a la derecha con sombra
  proyectada; no es una proyección isométrica estricta.
- Con ocho iconos el set es una primera entrega: todavía no hay icono para
  devolución, colección, etiqueta ni grupo, que el sitio de `url-routing/`
  también tiene; se añaden en `iconos-tools/pos.py`.
