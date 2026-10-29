# Iconos de árbol genealógico (SVG 2.5D)

204 iconos SVG de 64x64 en estilo 2.5D (formas con extrusión y sombra suave),
**sin letras, números ni pictogramas** (nada dibuja un objeto literal), así que
no dependen del idioma. 612 archivos: 3 variantes × 204 iconos.

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
| `person_` | 103 | Parentescos desde la perspectiva de "tú" | `person_uncle` |
| `relationship_` | 27 | Vínculos y tipos de familia | `relationship_married` |
| `state_` | 8 | Estado de una persona | `state_widowed` |
| `view_` | 13 | Formas de ver el árbol | `view_fan` |
| `action_` | 33 | Acciones del usuario | `action_add_parent` |
| `ui_` | 20 | Elementos de interfaz | `ui_dna` |

## Cómo se leen

Los parentescos son diagramas: naranja = el familiar, azul oscuro con punto =
tú, gris claro = el resto. Cuadrado = hombre, círculo = mujer, rombo = sin
especificar. «Tú» (`person_self`) es el nodo propio solo, sin anillo.

Cada estilo de enlace significa una cosa:

- línea: de padres a hijos, o una pareja sin más (`person_partner`)
- doble: matrimonio. Cortada: divorcio.
- discontinua: adopción o parentesco parcial (medios hermanos)
- punteada: vínculo provisional, según el contexto: acogida (de padres a hijo) o
  compromiso (entre una pareja)
- con un travesaño en el medio: vínculo lateral entre hermanos, hermanastros o
  cuñados (gemelos, `relationship_step_family`, los concuñados)
- ondulada: pareja sentimental. Barra gruesa roja: el vínculo de sangre.

Una marca sobre el enlace distingue al padrino (anillo hueco), al adoptante
(punto pequeño), al acogedor (cuadrado lleno) y al tutor (punta de flecha). Un
anillo discontinuo alrededor de un nodo significa «desconocido» o «sin pareja»;
alrededor de varios (`view_family_group`), un grupo. En los diagramas densos
(primos segundos, tíos abuelos, sobrinos nietos...) el familiar se dibuja más
grande para que se vea su forma. Las acciones y la interfaz usan la misma
gramática que el set de biblioteca (ver `iconos-biblioteca/README.md`). Lo que
no es una persona (una organización, los aportes de una cuenta) se dibuja con
bloques redondeados en lugar de nodos.

Los nombres de parentesco llevan el género en la palabra (`person_father`,
`person_mother`, `person_parent`). Donde la palabra no lo distingue (`cousin`),
sin sufijo es el masculino (cuadrado), `_f` el femenino (círculo) y `_u` el sin
especificar (rombo): `person_cousin`, `person_cousin_f`, `person_cousin_u`.

Las demás parejas con género tienen su versión sin especificar (rombo) con una palabra propia:
`person_pibling` (tío o tía) y `person_nibling` (sobrino o sobrina), con sus «grandes»;
`person_stepparent`, `person_stepchild`, `person_stepsibling` y `person_half_sibling`;
`person_parent_in_law`, `person_child_in_law` y `person_sibling_in_law`; `person_ex_spouse`;
`person_adoptive_parent`, `person_adopted_child`, `person_foster_parent`, `person_foster_child`,
`person_godparent` y `person_godchild`; y `person_grandparent_paternal` y `_maternal`.

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
los colores originales. Las reglas van acotadas a la clase `icono25d` que lleva la raíz del SVG
(un `<style>` en línea vale para todo el documento), así que no cambian nada de la página fuera del icono.

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
