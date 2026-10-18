# Iconos de árbol genealógico (SVG 2.5D)

172 iconos SVG de 64x64 en estilo 2.5D (formas con extrusión y sombra suave),
**sin letras, números ni pictogramas** (nada dibuja un objeto literal), así que
no dependen del idioma. 516 archivos: 3 variantes × 172 iconos.

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
| `person_` | 79 | Parentescos desde la perspectiva de "tú" | `person_uncle` |
| `relationship_` | 27 | Vínculos y tipos de familia | `relationship_married` |
| `state_` | 8 | Estado de una persona | `state_widowed` |
| `view_` | 12 | Formas de ver el árbol | `view_fan` |
| `action_` | 32 | Acciones del usuario | `action_add_parent` |
| `ui_` | 14 | Elementos de interfaz | `ui_dna` |

## Cómo se leen

Los parentescos son diagramas: naranja = el familiar, azul oscuro con punto =
tú, gris claro = el resto. Cuadrado = hombre, círculo = mujer, rombo = sin
especificar. Línea doble = matrimonio, línea cortada = divorcio, discontinua =
adopción o parentesco parcial, punteada = acogida; una marca sobre el enlace
distingue al padrino (anillo hueco), al adoptante (punto pequeño), al
acogedor (cuadrado lleno) y al tutor (punta de flecha). En los diagramas
densos (primos segundos, tíos abuelos, sobrinos nietos...) el familiar se
dibuja más grande para que se vea su forma. Un anillo discontinuo alrededor
de un nodo significa "desconocido" o "sin pareja"; una línea ondulada une a
una pareja sentimental y una barra gruesa roja, el vínculo de sangre. Las
acciones y la interfaz usan la misma gramática que el set de biblioteca (ver
`iconos-biblioteca/README.md`).

## Uso

```html
<img src="iconos-genealogia/color/person/person_uncle.svg" alt="Tío" width="48" height="48">
```

Los `id` internos (los gradientes, con el nombre del icono) son únicos entre
iconos, pero las variantes `color` y `gris` de un mismo icono comparten los
suyos: si incrustás el SVG en línea, no pongas esas dos variantes del mismo
icono en la misma página (con `<img>` o CSS no hay problema).

## Regenerar y validar

Los SVG son salida generada, no se editan a mano — se definen en
`iconos-tools/genealogia.py` y se regeneran con `iconos-tools/build.py`. Ver
`iconos-tools/README.md` para el generador, el validador y el workflow de CI.

## Limitaciones conocidas

- Varios pares masculino/femenino (`person_uncle` y `person_aunt`) se
  distinguen solo por la forma de un nodo, y adopción, acogida y padrinazgo
  por una pequeña marca sobre el enlace (punto, cuadrado, anillo).
- El efecto 2.5D es una extrusión hacia abajo y a la derecha con sombra
  proyectada; no es una proyección isométrica estricta.
- A 24 px los pares masculino/femenino de `person_` son los más cercanos
  entre sí (los diez primeros, de 0,99 a 1,2, frente a 0,8 de límite):
  `person_grandson` y `person_granddaughter`, `person_great_grandson` y
  `person_great_granddaughter`, `person_grandfather` y `person_grandmother`...
  `iconos-tools/similares.py` los lista.
