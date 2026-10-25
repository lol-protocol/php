# Iconos de árbol genealógico (SVG 2.5D)

183 iconos SVG de 64x64 en estilo 2.5D (formas con extrusión y sombra suave),
**sin letras, números ni pictogramas** (nada dibuja un objeto literal), así que
no dependen del idioma. 549 archivos: 3 variantes × 183 iconos.

Para verlos todos, abrí `galeria-iconos.html` desde la raíz del repositorio.

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
| `person_` | 82 | Parentescos desde la perspectiva de "tú" | `person_uncle` |
| `relationship_` | 27 | Vínculos y tipos de familia | `relationship_married` |
| `state_` | 8 | Estado de una persona | `state_widowed` |
| `view_` | 13 | Formas de ver el árbol | `view_fan` |
| `action_` | 33 | Acciones del usuario | `action_add_parent` |
| `ui_` | 20 | Elementos de interfaz | `ui_dna` |

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
`iconos-biblioteca/README.md`). Lo que no es una persona (una organización, los
aportes de una cuenta) se dibuja con bloques redondeados en lugar de nodos.

Los nombres de parentesco llevan el género en la palabra (`person_father`,
`person_mother`, `person_parent`). Donde la palabra no lo distingue (`cousin`),
sin sufijo es el masculino (cuadrado), `_f` el femenino (círculo) y `_u` el sin
especificar (rombo): `person_cousin`, `person_cousin_f`, `person_cousin_u`.

## Uso

```html
<img src="iconos-genealogia/color/person/person_uncle.svg" alt="Tío" width="48" height="48">
```

Los `id` internos (los gradientes, con el nombre del icono y, en `gris`, el
sufijo `_g`) son únicos entre los iconos y las variantes del set, así que puedes
incrustar varios SVG en línea en la misma página, también `color` y `gris` de un
mismo icono. Los iconos con el mismo nombre en los dos sets (`ui_menu`...) sí
comparten ids.

## Colores de `lineas`

Cada trazo de `lineas/` lleva el color de su tono (`#883307` para `orange`...), y
ese color se puede sobrescribir con una variable CSS `--ic-<tono>`. Solo
funciona con el SVG **en línea** en la página (`<svg>` pegado o inyectado en el
HTML): con `<img>` o `background: url(...)` los iconos se ven con sus colores de
siempre, porque la página no llega al interior del archivo.

```css
.barra svg { --ic-orange: currentColor; --ic-slate: currentColor; --ic-mist: currentColor; }
.alerta svg { --ic-orange: crimson; }   /* solo cambia lo naranja */
```

Los tonos que usa un icono están en su `<style>` (`.ic-orange`, `.ic-slate`...).
Un renderizador sin variables CSS (por ejemplo `resvg`) ignora esa parte y dibuja
los colores originales.

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
- A 24 px los pares más cercanos entre sí son adopción y acogida
  (`relationship_adoption` y `relationship_fostering`, 1,50) y los
  masculino/femenino de `person_` (`person_grandson` y `person_granddaughter`,
  1,51), frente a 1,2 de límite. `iconos-tools/similares.py` los lista.
