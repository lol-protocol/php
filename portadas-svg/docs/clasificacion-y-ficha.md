# Clasificación y ficha

Cada libro puede llevar dos clasificaciones, que se escriben por su identificador o por su nombre, con o sin tildes y mayúsculas (`filosofia`, `Filosofía y ensayo` y `FILOSOFIA` son lo mismo):

- **`tradicion`**: la tradición cultural del texto. Las que hay están en `datos/tradiciones.json`: griega, romana, egipcia, mesopotámica, hebrea, persa, árabe, india, china, japonesa, celta, nórdica, medieval, renacentista, precolombina y moderna.
- **`categoria`**: la categoría de Marcalibros, un movimiento literario o un tipo de texto. Las 27 están en `datos/categorias.json`, copiadas de `marcalibros-plataforma-pos-brand-identity/catalog/` para que este proyecto no dependa de esa carpeta: si allí cambian las categorías, se actualiza el archivo a mano.

Un identificador que no existe se rechaza con la lista de los que sí.

## Adornos por tradición

La `tradicion` decide el adorno de la cubierta: un **friso** (una franja que se repite) arriba de la portada y de la contratapa, y un **emblema** en el centro de la portada de la plantilla clásica. Son solo líneas finas y círculos en el color de acento, sin imágenes. Las 16 tradiciones de `datos/tradiciones.json` tienen el suyo:

| Tradición | Friso | Emblema |
|---|---|---|
| `griega` | meandro (espirales cuadradas) | espiral cuadrada dentro de un marco |
| `romana` | arcada de medio punto | corona de laurel |
| `egipcia` | flores de loto | disco solar con alas |
| `mesopotamica` | zigurat (pirámide escalonada) | roseta de ocho pétalos |
| `hebrea` | granadas con hojas | doce piedras en círculo |
| `persa` | boteh (la gota curvada) | medallón de rayos |
| `arabe` | estrellas de ocho puntas y rombos, entre dos filetes | estrella de ocho puntas con círculo y octógono |
| `india` | pétalos de loto | mandala de ocho pétalos |
| `china` | nubes en espiral | disco *bi*: círculos con un cuadrado girado |
| `japonesa` | círculos enlazados (*shippo*) | crisantemo de rayos |
| `celta` | parejas de espirales | triskel |
| `nordica` | dientes de lobo | flor de seis pétalos hecha con compás |
| `medieval` | entrelazado de dos ondas | cuadrilóbulo gótico |
| `renacentista` | onda con perlas | cartela ovalada |
| `precolombina` | escalinata | chakana (cruz escalonada) |
| `moderna` | filete con rombos | estrella de cuatro puntas |

Sin tradición, o con una que no tenga adorno, se usa el de siempre: el rombo con dos líneas y una raya o una barra de acento donde iría el friso. Para añadir uno, crea `src/Adornos/Nombre.php` que extienda `Adorno` (con `friso()` y `emblema()`; los trazos comunes están en `Trazos`) y regístralo en `Adorno::de()`. Una prueba comprueba que ningún adorno se salga de su zona ni pise un texto.

## El emblema de la contratapa

Si la contratapa tiene poco texto, el emblema de la tradición aparece en grande y muy tenue en el centro (marca de agua) para que no quede vacía. Solo se pone si cabe sin tocar el texto: una contratapa larga no lo lleva.

## Un motivo por categoría

Cada una de las 27 categorías tiene un dibujo pequeño (`src/Adornos/Motivo.php`): un amanecer para el romanticismo, una balanza para las leyes, una lira para la poesía, un faro para el modernismo anglosajón, una brújula para los viajes… Es de líneas finas en el color de acento y va en tres sitios:

- **Contratapa** (las tres plantillas): abajo a la izquierda, sobre el pie.
- **Lomo**: abajo, en el color del texto del lomo, si el lomo mide 5 mm o más; el título se acorta lo justo, con el mismo margen arriba y abajo. En un lomo más estrecho no se pone.
- **Portada sobria**: arriba a la derecha, frente al friso.

Una prueba comprueba que haya uno por cada categoría de `datos/categorias.json` y que quepa en su cuadrado.

## Colores por categoría

Cada categoría tiene un `tono` (0–360°) en `datos/categorias.json`, repartidos a partes iguales (13° entre uno y el siguiente) y mezclados para que las categorías de una misma familia queden lejos entre sí. Si el libro no da `colores`, la plantilla se tiñe de ese tono: la clásica y la tipográfica con fondo claro, la sobria con fondo oscuro, y en todas el acento del mismo tono. Así los libros de una categoría se parecen entre sí. Los `colores` del libro mandan sobre los de la categoría, color por color. Una prueba comprueba que, en las 27 categorías y las tres plantillas, el texto tenga contraste 7:1 con el fondo y el acento 3:1.

## La ficha de la contratapa y las solapas

Si el libro trae `epoca`, `tradicion`, `categoria` o `cita`, y no trae los textos escritos a mano, la contratapa y las solapas se arman con esos datos. **No hay sinopsis inventada**: la ficha solo dice lo que ya está en los datos. Por ejemplo, la contratapa de *Meditaciones* sale así:

```
«Meditaciones» es una obra de Marco Aurelio (siglo II), de la tradición romana.

Tipo de texto: Filosofía y ensayo.

224 páginas.
```

- **Contratapa**: qué obra es y de quién, la clasificación, la traducción y la extensión. La `cita`, si la hay, no va en este texto: la contratapa la compone aparte arriba, en cursiva (en la serif) o redonda (en la sans), con el nombre del autor debajo.
- **Solapa de la portada**: la clasificación, la traducción y la extensión.
- **Solapa de la contratapa**: el autor, su época y la tradición.

Lo escrito a mano manda: si el libro trae `contratapa`, `solapa_portada` o `solapa_contratapa`, ese texto se usa tal cual, aunque sea vacío (`"contratapa": ""` deja la contratapa sin texto). Sin ninguno de los cuatro datos, no se arma nada.
