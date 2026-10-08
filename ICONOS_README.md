# Iconos SVG 2.5D abstractos: genealogía y biblioteca

Dos sets de iconos SVG de 64x64 en estilo 2.5D (formas con extrusión y sombra suave), **sin letras ni números**, así que no dependen del idioma. Cada set está en su propia carpeta para poder copiarlo por separado a un proyecto.

| Set | Carpeta | Iconos | Archivos (3 variantes) |
|---|---|---|---|
| Árbol genealógico | `iconos-genealogia/` | 172 | 516 |
| Biblioteca virtual | `iconos-biblioteca/` | 161 | 483 |

Para verlos todos, abre `galeria-iconos.html` desde la raíz del repositorio.

## Variantes

- `color/`: gradientes, extrusión y sombra.
- `lineas/`: solo contornos, sin rellenos; las partes tapadas por otra forma no se dibujan.
- `gris/`: el mismo dibujo en grises neutros que conservan el volumen.

Las tres variantes tienen exactamente los mismos nombres y carpetas.

## Estructura y nombres

`<variante>/<prefijo>/<prefijo>_<descripcion>.svg`: la carpeta coincide con el prefijo del nombre; los nombres van en minúsculas, ASCII, con guion bajo y en inglés.

### Genealogía

| Prefijo | Iconos | Contenido | Ejemplo |
|---|---|---|---|
| `person_` | 79 | Parentescos desde la perspectiva de "tú" | `person_uncle` |
| `relationship_` | 27 | Vínculos y tipos de familia | `relationship_married` |
| `state_` | 8 | Estado de una persona | `state_widowed` |
| `view_` | 12 | Formas de ver el árbol | `view_fan` |
| `action_` | 32 | Acciones del usuario | `action_add_parent` |
| `ui_` | 14 | Elementos de interfaz | `ui_dna` |

### Biblioteca

| Prefijo | Iconos | Contenido | Ejemplo |
|---|---|---|---|
| `book_` | 23 | Tipos y partes de un libro | `book_boxset` |
| `genre_` | 52 | Géneros literarios y temas | `genre_scifi` |
| `format_` | 7 | Ediciones y formatos | `format_braille` |
| `status_` | 22 | Disponibilidad y estado de lectura | `status_overdue` |
| `action_` | 24 | Acciones del usuario | `action_borrow` |
| `attr_` | 14 | Atributos de un libro | `attr_award` |
| `ui_` | 19 | Elementos de interfaz | `ui_library_card` |

## Cómo se leen

**Genealogía.** Los parentescos son diagramas: naranja = el familiar, azul oscuro con punto = tú, gris claro = el resto. Cuadrado = hombre, círculo = mujer, rombo = sin especificar. Línea doble = matrimonio, línea cortada = divorcio, discontinua = adopción o parentesco parcial, punteada = acogida; un anillo sobre el enlace indica padrino o madrina. Un anillo discontinuo alrededor de un nodo significa "desconocido" o "sin pareja".

**Biblioteca.** Los libros y géneros son una portada con un motivo geométrico (órbita, arco, ondas...); la disponibilidad y el estado son insignias circulares; las acciones, baldosas de esquinas redondeadas; los atributos, hexágonos. Los glifos de acción son los símbolos habituales (más, cruz, lupa, flechas, corazón, estrella).

Al ser abstractos, conviene acompañar cada icono con su etiqueta en la interfaz.

## Uso

```html
<img src="iconos-genealogia/color/person/person_uncle.svg" alt="Tío" width="48" height="48">
```

```css
.icono-ebook {
  background: url('iconos-biblioteca/lineas/book/book_ebook.svg') center / contain no-repeat;
}
```

Los `id` internos (los gradientes, con el nombre del icono) son únicos entre iconos, pero las variantes `color` y `gris` de un mismo icono comparten los suyos: si incrustas el SVG en línea, no pongas esas dos variantes del mismo icono en la misma página (con `<img>` o CSS no hay problema).

## Regenerar y validar

Los SVG son salida generada. Se editan en `iconos-tools/` (`genealogia.py` y `biblioteca.py` definen cada icono una sola vez; `lib.py`, `glyphs.py` y `motifs.py` son las piezas comunes) y se regeneran con:

```
python3 iconos-tools/build.py      # escribe los dos sets y galeria-iconos.html
python3 iconos-tools/validate.py   # comprueba las reglas de abajo
```

El validador comprueba que cada SVG esté bien formado, mida 64x64 y no contenga texto; que las tres variantes tengan los mismos archivos y formas; que `gris/` no tenga colores cromáticos y `lineas/` no tenga rellenos; que los nombres sigan la convención y estén en la carpeta de su prefijo; que no haya ids repetidos ni iconos idénticos.

## Limitaciones conocidas

- Varios pares masculino/femenino (`person_uncle` y `person_aunt`) se distinguen solo por la forma de un nodo, y adopción, acogida y padrinazgo por una pequeña marca sobre el enlace (corazón, cuadrado, anillo).
- Los motivos de género de la biblioteca son abstractos: no se deducen sin la etiqueta.
- El efecto 2.5D es una extrusión hacia abajo y a la derecha con sombra proyectada; no es una proyección isométrica estricta.
- El validador solo detecta iconos idénticos; los casi idénticos se revisaron comparando miniaturas fuera del repositorio.
