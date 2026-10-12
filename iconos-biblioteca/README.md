# Iconos de biblioteca virtual (SVG 2.5D)

151 iconos SVG de 64x64 en estilo 2.5D (formas con extrusión y sombra suave),
**sin letras, números ni pictogramas** (nada dibuja un objeto literal, ni
libros con lomo ni lupas con mango), así que no dependen del idioma. 453
archivos: 3 variantes × 151 iconos.

Para verlos todos, abrí `galeria-iconos.html` desde la raíz del repositorio.

## Variantes

- `color/`: gradientes, extrusión y sombra.
- `lineas/`: solo contornos, sin rellenos; las partes tapadas por otra forma no se dibujan.
- `gris/`: el mismo dibujo en grises neutros que conservan el volumen.

Las tres variantes tienen exactamente los mismos nombres y carpetas.

## Estructura y nombres

`<variante>/<prefijo>/<prefijo>_<descripcion>.svg`: la carpeta coincide con el
prefijo del nombre; los nombres van en minúsculas, ASCII, con guion bajo y en
inglés.

| Prefijo | Iconos | Contenido | Ejemplo |
|---|---|---|---|
| `book_` | 23 | Tipos y partes de un libro | `book_boxset` |
| `genre_` | 42 | Géneros literarios y temas (6 familias x 7 patrones) | `genre_scifi` |
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
  cruz, mitad).
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

Los `id` internos (los gradientes, con el nombre del icono) son únicos entre
iconos, pero las variantes `color` y `gris` de un mismo icono comparten los
suyos: si incrustás el SVG en línea, no pongas esas dos variantes del mismo
icono en la misma página (con `<img>` o CSS no hay problema).

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
- A 24 px, dentro de una misma familia de géneros, algunos pares quedan
  cerca entre sí (`genre_self_help` y `genre_travel`, 1,05, frente a 0,8 de
  límite).
