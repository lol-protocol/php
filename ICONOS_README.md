# Iconos SVG 2.5D: genealogía y biblioteca

Dos sets de iconos SVG de 64x64 en estilo 2.5D, cada uno en tres variantes. Cada set está en su propia carpeta para poder copiarlo por separado a un proyecto.

| Set | Carpeta | Iconos | Archivos (3 variantes) |
|---|---|---|---|
| Árbol genealógico | `iconos-genealogia/` | 150 | 450 |
| Biblioteca virtual | `iconos-biblioteca/` | 149 | 447 |

Para verlos todos, abre `galeria-iconos.html` desde la raíz del repositorio.

## Variantes

- `color/`: versión original, con gradientes y sombra.
- `lineas/`: solo contornos, sin rellenos. Las etiquetas de texto se dibujan en gris `#4B5563`.
- `gris/`: el mismo dibujo en grises neutros que conservan el volumen.

Las tres variantes tienen los mismos nombres y las mismas subcarpetas.

## Estructura

```
iconos-genealogia/{color,lineas,gris}/
    acciones/ (8)   base/ (81)   relaciones/ (42)   ui/ (5)   vistas/ (14)

iconos-biblioteca/{color,lineas,gris}/
    acciones/ (11)  contenido/ (102)  disponibilidad/ (18)  generos/ (6)  navegacion/ (3)  perfil/ (9)
```

Las subcarpetas son el reparto temático inicial y no siempre coinciden con el prefijo del nombre (por ejemplo, hay iconos `action_*` en `vistas/`). Para buscar por tipo de icono, usa el prefijo.

## Nombres

`<prefijo>_<descripcion>`: minúsculas, ASCII (sin tildes ni ñ), con guion bajo y en inglés.

### Genealogía

| Prefijo | Iconos | Significado | Ejemplo |
|---|---|---|---|
| `person_` | 47 | Personas y parentescos | `person_father`, `person_godmother` |
| `relationship_` | 15 | Vínculos entre personas | `relationship_married` |
| `state_` | 4 | Estado civil | `state_widowed` |
| `view_` | 7 | Formas de ver el árbol | `view_tree` |
| `action_` | 15 | Acciones del usuario | `action_add` |
| `ui_` | 6 | Elementos de interfaz | `ui_export_pdf` |
| `person_variant_NNN` | 56 | Marcadores de posición | `person_variant_001` |

### Biblioteca

| Prefijo | Iconos | Significado | Ejemplo |
|---|---|---|---|
| `book_` | 13 | Tipos y partes de un libro | `book_ebook` |
| `genre_` | 30 | Géneros literarios | `genre_romance` |
| `format_` | 4 | Ediciones y series | `format_series` |
| `status_` | 13 | Disponibilidad y estado de lectura | `status_available` |
| `action_` | 10 | Acciones del usuario | `action_rate` |
| `attr_` | 8 | Atributos de un libro | `attr_premium` |
| `ui_` | 13 | Elementos de interfaz | `ui_map` |
| `book_title_NNN` | 58 | Marcadores de posición | `book_title_001` |

## Uso

```html
<img src="iconos-genealogia/color/base/person_father.svg" alt="Padre" width="48" height="48">
```

```css
.icono-ebook {
  background: url('iconos-biblioteca/lineas/contenido/book_ebook.svg') center / contain no-repeat;
}
```

Los `id` internos (gradientes y filtros) son únicos entre iconos, pero las tres variantes de un mismo icono comparten los suyos. Si incrustas el SVG en línea, no pongas dos variantes del mismo icono en la misma página; con `<img>` o CSS no hay problema.

## Limitaciones conocidas

- **Marcadores de posición.** Los 56 `person_variant_NNN` y los 58 `book_title_NNN` repiten 3 formas base cambiando solo el color. Sin ellos quedan 94 iconos con nombre propio en genealogía y 91 en biblioteca.
- **Formas repetidas con nombre propio.** Algunas parejas comparten dibujo y se distinguen solo por color (por ejemplo `person_niece` y `person_nephew`). Hay cinco iconos `person_unknown_*` para el mismo concepto, y `person_daughter_in_law` tiene un duplicado `_alt`.
- **Efecto 2.5D.** En muchos iconos se reduce a una sombra proyectada y una cara lateral más oscura; no todos son isométricos estrictos.
- **Etiquetas de texto.** Algunas (`PDF`, `CSV`) quedan recortadas por el borde del icono.
- **Ids internos.** Conservan el nombre original en español (por ejemplo `grad_padre_1`), distinto del nombre del archivo.

## Comprobaciones hechas

Al preparar esta versión se comprobó que los 897 SVG son XML bien formado con `viewBox="0 0 64 64"`, que las tres variantes tienen los mismos archivos, que todos los nombres siguen la convención, que `gris/` no tiene colores cromáticos, que `lineas/` no tiene rellenos y que todos se cargan en Chromium. Todavía no hay una validación automática en el repositorio.
