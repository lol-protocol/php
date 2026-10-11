# Iconos SVG 2.5D abstractos: genealogía y biblioteca

Dos sets de iconos SVG de 64x64 en estilo 2.5D (formas con extrusión y sombra suave), **sin letras, números ni pictogramas** (nada dibuja un objeto: ni libros con lomo, ni corazones, ni lupas con mango), así que no dependen del idioma. Cada set está en su propia carpeta para poder copiarlo por separado a un proyecto.

| Set | Carpeta | Iconos | Archivos (3 variantes) |
|---|---|---|---|
| Árbol genealógico | `iconos-genealogia/` | 172 | 516 |
| Biblioteca virtual | `iconos-biblioteca/` | 151 | 453 |

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
| `genre_` | 42 | Géneros literarios y temas (6 familias x 7 patrones) | `genre_scifi` |
| `format_` | 7 | Ediciones y formatos | `format_braille` |
| `status_` | 22 | Disponibilidad y estado de lectura | `status_overdue` |
| `action_` | 24 | Acciones del usuario | `action_borrow` |
| `attr_` | 14 | Atributos de un libro | `attr_award` |
| `ui_` | 19 | Elementos de interfaz | `ui_library_card` |

## Cómo se leen

**Genealogía.** Los parentescos son diagramas: naranja = el familiar, azul oscuro con punto = tú, gris claro = el resto. Cuadrado = hombre, círculo = mujer, rombo = sin especificar. Línea doble = matrimonio, línea cortada = divorcio, discontinua = adopción o parentesco parcial, punteada = acogida; una marca sobre el enlace distingue al padrino (anillo), al adoptante (punto lleno), al acogedor (cuadrado) y al tutor (punta de flecha). Un anillo discontinuo alrededor de un nodo significa "desconocido" o "sin pareja"; una línea ondulada une a una pareja sentimental y una barra gruesa roja, el vínculo de sangre. Las acciones y la interfaz usan la misma gramática que la biblioteca (abajo).

**Biblioteca.** Cada icono se compone con pocas formas geométricas y se lee por forma, relleno y posición:

- `book_`: volúmenes neutros (un bloque con volumen); lo que cambia es la composición (abierto, apilado, inclinado, con arcos...).
- `genre_`: la **familia** se lee por la forma y el color (círculo rosa = personas y vida, cuadrado marrón = hechos y textos, triángulo rojo = tensión y aventura, hexágono verde azulado = ciencia y técnica, rombo violeta = imaginación y arte, semicírculo verde = vida práctica) y el **género** por el patrón dentro de ella (liso, rayas, puntos, ondas, anillo, cruz, mitad).
- `status_`: discos con un indicador blanco: el estado se lee por el relleno (lleno, vacío, mitad, anillo, anillo roto, arco...).
- `action_`: piezas sueltas con movimiento; la estela marca de dónde viene la pieza (descargar, subir, importar, exportar).
- `attr_` y `ui_`: composiciones sobre un hexágono o un bloque redondeado.

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

Los SVG son salida generada. Se editan en `iconos-tools/` (`genealogia.py` y `biblioteca.py` definen cada icono una sola vez; `lib.py` y `kit.py` son las piezas comunes) y se regeneran con:

```
python3 iconos-tools/build.py      # escribe los dos sets y galeria-iconos.html
python3 iconos-tools/validate.py   # comprueba las reglas de abajo

pip install -r iconos-tools/requirements.txt   # solo para similares.py
python3 iconos-tools/similares.py  # busca iconos casi indistinguibles a 24 px
```

El validador comprueba que cada SVG esté bien formado, mida 64x64 y no contenga texto; que las tres variantes tengan los mismos archivos y formas; que `gris/` no tenga colores cromáticos y `lineas/` no tenga rellenos; que los nombres sigan la convención y estén en la carpeta de su prefijo; que no haya ids repetidos ni iconos idénticos.

`similares.py` dibuja la variante `gris` de cada icono a 24x24 y compara todos los pares de un mismo set; falla si dos se diferencian en menos de 0,8 (diferencia media por píxel, de 0 a 255; unos dos píxeles totalmente distintos de los 576). Muestra además los pares más cercanos, aunque no fallen. Los pares que ya estaban por debajo de ese límite al añadir la comprobación figuran en `ACEPTADOS`, con el motivo.

El workflow `.github/workflows/iconos.yml` ejecuta los tres pasos en cada cambio de los iconos y falla además si los SVG o la galería versionados no coinciden con lo que genera `build.py` (un archivo editado a mano o un generador cambiado sin regenerar).

## Limitaciones conocidas

- Varios pares masculino/femenino (`person_uncle` y `person_aunt`) se distinguen solo por la forma de un nodo, y adopción, acogida y padrinazgo por una pequeña marca sobre el enlace (punto, cuadrado, anillo).
- Sin pictogramas, los iconos de la biblioteca no se reconocen a primera vista: hay que aprender el código (familia = forma, género = patrón) o acompañarlos de su etiqueta. Dentro de una familia, algunos patrones se parecen a tamaños pequeños (rayas y ondas, anillo y cruz).
- El efecto 2.5D es una extrusión hacia abajo y a la derecha con sombra proyectada; no es una proyección isométrica estricta.
- A 24 px hay pares que solo se distinguen por un detalle de unos pocos píxeles: seis de `person_` (`person_foster_mother` y `person_godmother`, `person_great_aunt` y `person_great_uncle`, `person_second_cousin` y `person_second_cousin_f`...) están aceptados de forma explícita en `similares.py`, y muchos pares masculino/femenino quedan apenas por encima del límite.
