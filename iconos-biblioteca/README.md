# Iconos de biblioteca virtual (SVG 2.5D)

151 iconos SVG de 64x64 en estilo 2.5D (formas con extrusión y sombra suave),
**sin letras, números ni pictogramas** (nada dibuja un objeto literal, ni
libros con lomo ni lupas con mango), así que no dependen del idioma. 453
archivos de icono (3 variantes × 151 iconos), más un `sprite.svg` por variante.

Para verlos todos, abre `galeria-iconos.html` desde la raíz del repositorio.

## Variantes

- `color/`: gradientes, extrusión y sombra.
- `lineas/`: solo contornos, sin rellenos, como un dibujo de alambre: los volúmenes que se solapan se ven
  transparentes (también los que en `color` son translúcidos); solo se ocultan los enlaces y las marcas que
  quedan detrás de un volumen.
  Los colores se pueden cambiar desde la página (ver «Colores de `lineas`»).
- `gris/`: el mismo dibujo en grises neutros que conservan el volumen.

Las tres variantes tienen exactamente los mismos nombres y carpetas, y cada una trae un
`sprite.svg` en su raíz con todos sus iconos (ver «Sprite»).

## Estructura y nombres

`<variante>/<prefijo>/<prefijo>_<descripcion>.svg`: la carpeta coincide con el
prefijo del nombre; los nombres van en minúsculas, ASCII, con guion bajo y en
inglés.

| Prefijo | Iconos | Contenido | Ejemplo |
|---|---|---|---|
| `book_` | 23 | Tipos y partes de un libro | `book_boxset` |
| `genre_` | 42 | Géneros literarios y temas (6 familias de 6 a 8 géneros, uno por patrón) | `genre_scifi` |
| `format_` | 7 | Ediciones y formatos | `format_braille` |
| `status_` | 22 | Disponibilidad y estado de lectura | `status_overdue` |
| `action_` | 24 | Acciones del usuario | `action_borrow` |
| `attr_` | 14 | Atributos de un libro | `attr_award` |
| `ui_` | 19 | Elementos de interfaz | `ui_library_card` |

## Cómo se leen

Cada icono se compone con pocas formas geométricas y se lee por forma,
relleno y posición:

- `book_`: volúmenes neutros (un bloque con volumen); lo que cambia es la
  composición (abierto, apilado, inclinado, con arcos...).
- `genre_`: la **familia** se lee por la forma y el color (círculo rosa =
  personas y vida, cuadrado marrón = hechos y textos, triángulo rojo =
  tensión y aventura, hexágono verde azulado = ciencia y técnica, rombo
  violeta = imaginación y arte, semicírculo verde = vida práctica) y el
  **género** por el patrón dentro de ella (liso, rayas, puntos, ondas, anillo,
  cruz, mitad y, solo en la familia de ocho géneros, la violeta, una V). Los
  dos cómics, `genre_graphic_novel` y `genre_manga`, van juntos en esa familia.
- `status_`: discos con un indicador blanco: el estado se lee por el relleno
  (lleno, vacío, mitad, anillo, anillo roto, arco...).
- `action_`: piezas sueltas con movimiento; la estela marca de dónde viene la
  pieza (descargar, subir, importar, exportar).
- `attr_` y `ui_`: composiciones sobre un hexágono o un bloque redondeado.

Al ser abstractos, conviene acompañar cada icono con su etiqueta en la
interfaz. Las acciones y la interfaz usan la misma gramática que el set de
genealogía (ver `iconos-genealogia/README.md`).

## Uso

```css
.icono-ebook {
  background: url('iconos-biblioteca/lineas/book/book_ebook.svg') center / contain no-repeat;
}
```

Los `id` internos (los gradientes, con el nombre del icono y, en `gris`, el
sufijo `_g`) son únicos entre los iconos y las variantes del set, así que puedes
incrustar varios SVG en línea en la misma página, también `color` y `gris` de un
mismo icono. Los iconos con el mismo nombre en los dos sets (`ui_menu`...) sí
comparten ids.

## Sprite

Cada variante trae un `sprite.svg` en su raíz (`iconos-biblioteca/color/sprite.svg`...) con
todos sus iconos como `<symbol>`, cada uno con el nombre del icono por `id`, para
pintarlos con `<use>` sin un archivo por icono:

```html
<svg width="24" height="24" aria-hidden="true"><use href="iconos-biblioteca/color/sprite.svg#book_ebook"/></svg>
```

El icono toma el tamaño del `<svg>` que lo contiene. Hay un sprite por variante y
no uno que junte las tres, que pesaría el triple: el de `color` pesa unos 180 KB (unos 16 KB con gzip).

- **Como archivo aparte** (el ejemplo): la página y el sprite deben servirse por
  HTTP desde el mismo origen. Abriendo el HTML con `file://` no se pinta. Se
  descarga una vez y el navegador lo reutiliza para todos los iconos.
- **En línea**: pega el contenido del sprite en la página, con `width="0"
  height="0" style="position:absolute"` y `aria-hidden="true"` en su `<svg>`, y
  usa `<use href="#book_ebook"/>`. No lo ocultes con `display:none` ni con
  `hidden`: un `<svg>` oculto no pinta los degradados y los iconos de `color` y
  `gris` salen oscuros. Pega una sola variante por página: los `id` de los
  `<symbol>` son los mismos en las tres.

El dibujo es el mismo que el del archivo suelto.

## Colores de `lineas`

Cada trazo de `lineas/` lleva el color de su tono (`#883307` para `orange`...), y
ese color se puede sobrescribir con una variable CSS `--ic-<tono>`. Funciona con
el SVG **en línea** en la página (`<svg>` pegado o inyectado en el HTML) y con
`<use>` sobre el sprite, en línea o como archivo aparte (ver «Sprite»): la
variable se hereda hasta el icono. Con `<img>` o `background: url(...)` los
iconos se ven con sus colores de siempre, porque la página no llega al interior
del archivo.

```css
.barra svg { --ic-orange: currentColor; --ic-slate: currentColor; --ic-mist: currentColor; }
.alerta svg { --ic-orange: crimson; }   /* solo cambia lo naranja */
```

Los tonos que usa un icono están en su `<style>` (`.ic-orange`, `.ic-slate`...).
Un renderizador sin variables CSS (por ejemplo `resvg`) ignora esa parte y dibuja
los colores originales. Las reglas van acotadas a la clase `icono25d` que lleva la raíz del SVG
(un `<style>` en línea vale para todo el documento), así que no cambian nada de la página fuera del icono.

## Regenerar y validar

Los SVG son salida generada, no se editan a mano — se definen en
`iconos-tools/biblioteca.py` y se regeneran con `iconos-tools/build.py`. Ver
`iconos-tools/README.md` para el generador, el validador y el workflow de CI.

## Limitaciones conocidas

- Sin pictogramas, los iconos no se reconocen a primera vista: hay que
  aprender el código (familia = forma, género = patrón) o acompañarlos de su
  etiqueta. Dentro de una familia, algunos patrones se parecen a tamaños
  pequeños (rayas y ondas, anillo y cruz).
- El efecto 2.5D es una extrusión hacia abajo y a la derecha con sombra
  proyectada; no es una proyección isométrica estricta.
- A 24 px algunos pares quedan cerca entre sí, frente a 1,2 de límite: el
  más cercano es `status_read` con `status_reading` (1,27) y, dentro de las
  familias de géneros, `genre_horror` con `genre_thriller` (1,35).
  `iconos-tools/similares.py` los lista.
