# portadas-svg

Mini sistema para diagramar cubiertas de libros en SVG: portada, contratapa, lomo, cubierta completa y sobrelomo (con solapas). Está pensado para libros de dominio público, como la *Apología de Sócrates* o *El arte de la guerra*.

Cada libro es un JSON con sus datos. El generador escribe un SVG por pieza, con las medidas de corte, el sangrado y el lomo calculado a partir de las páginas, más un `medidas.txt` para pasárselo a la imprenta.

## Requisitos

- PHP 8.3 o más, con la extensión `mbstring` (las pruebas usan además `dom`). `composer.json` declara esos requisitos y dos atajos (`composer test` y `composer comprobar`).
- Sin dependencias: no hace falta `composer install`.
- Para ver o convertir los SVG: cualquier navegador, Inkscape o `rsvg-convert`. Las fuentes van dentro del SVG, así que se ven igual en todos.
- Solo para volver a medir las fuentes (casi nunca): Python 3 con `fonttools` y `brotli`. Ver «Fuentes».
- El proyecto es solo PHP y SVG: no tiene HTML ni JavaScript, y una prueba lo comprueba.

## Uso

```bash
cd portadas-svg
php bin/generar.php ejemplos/platon-apologia.json
```

Escribe en `salida/platon-apologia/`:

| Archivo | Qué es |
|---|---|
| `portada.svg` | Portada, con sangrado en los bordes exteriores. |
| `contratapa.svg` | Contratapa, con sangrado en los bordes exteriores. |
| `lomo.svg` | Lomo, con sangrado arriba y abajo. |
| `cubierta-completa.svg` | Solo sin solapas: contratapa, lomo y portada en una pieza, para rústica o tapa dura. |
| `sobrelomo.svg` | Solo con solapas (`solapas_mm`): la cubierta completa más las solapas. Sustituye a `cubierta-completa.svg`. |
| `medidas.txt` | Medidas de corte y tamaño de cada archivo, para la imprenta. |

Cada libro sale con cuatro SVG: portada, contratapa, lomo y la cubierta, que es el sobrelomo si tiene solapas y la cubierta completa si no.

Para varios libros a la vez, o para escribir en otra carpeta:

```bash
php bin/generar.php libro1.json libro2.json --salida=/ruta/de/salida
```

Cada libro escribe en una carpeta con el nombre de su archivo JSON, así que dos archivos con el mismo nombre se rechazan antes de escribir nada. `php bin/generar.php --help` muestra el uso.

Para cientos de libros, una lista en CSV (ver «Lista de libros»):

```bash
php bin/generar.php --lista=ejemplos/lista.csv --salida=/ruta/de/salida
```

Cada pieza usa las coordenadas de la cubierta completa, pero lleva solo su parte del dibujo: una pieza suelta coincide exactamente con su parte de la cubierta y no arrastra los otros paneles.

## El JSON de un libro

Solo `titulo`, `autor` y las páginas (o `lomo_mm`) son obligatorios. Lo demás tiene un valor por defecto.

| Campo | Por defecto | Qué es |
|---|---|---|
| `titulo` | — | Título. Obligatorio. |
| `autor` | — | Autor. Obligatorio. |
| `subtitulo` | vacío | Se escribe bajo el título. |
| `traductor` | vacío | «Traducción de …» en la portada. |
| `editorial`, `coleccion` | vacío | Pie de la portada y de la contratapa. |
| `isbn` | vacío | Se escribe sobre el hueco del código de barras. |
| `paginas` | — | Para calcular el lomo. Obligatorio si no das `lomo_mm`. |
| `lomo_mm` | calculado | Lomo fijo, en mm. Hasta 150. |
| `grosor_hoja_mm` | `0.1` | Grosor de una hoja (dos páginas), entre 0,02 y 0,5 mm. |
| `formato.ancho_mm`, `formato.alto_mm` | `135` y `210` | Medida de corte, entre 50 y 500 mm. |
| `sangrado_mm` | `3` | Sangrado en cada borde exterior, hasta 10 mm. |
| `solapas_mm` | `0` | Ancho de cada solapa. `0` no pone sobrelomo; si no es `0`, mínimo 40 y no más ancho que la portada. |
| `plantilla` | `clasica` | `clasica` o `sobria`. |
| `colores.fondo`, `colores.texto`, `colores.acento` | los de la plantilla | Colores en `#rrggbb`. |
| `degradado` | sin degradado | Degradado del fondo de toda la cubierta. Ver «Degradados». |
| `lomo_direccion` | `arriba-abajo` | Sentido del texto del lomo: `arriba-abajo` o `abajo-arriba`. |
| `epoca` | vacío | Época del autor o de la obra, en texto libre (`siglo II`, `399 a. C.`). Solo para la ficha. |
| `tradicion` | vacío | Tradición cultural: una de la lista de «Clasificación». |
| `categoria` | vacío | Categoría de Marcalibros: una de la lista de «Clasificación». |
| `cita` | vacío | Una frase breve de la propia obra, para abrir la contratapa. |
| `contratapa` | la ficha | Texto de la contratapa. Los párrafos van separados por una línea en blanco. |
| `solapa_contratapa` | la ficha | Texto de la solapa interior de la contratapa (solo con solapas). |
| `solapa_portada` | la ficha | Texto de la solapa interior de la portada (solo con solapas). |

El de `ejemplos/platon-apologia.json` usa casi todos los campos; el de Sun Tzu, solo los básicos y la clasificación.

Las medidas fuera de esos límites se rechazan con un error que nombra el campo: casi siempre es un error de unidades (centímetros o pulgadas en vez de milímetros).

Los caracteres de control de un texto pegado se limpian al escribir el SVG: el salto de página que trae un PDF se lee como espacio y los demás se quitan, porque XML no los admite.

## Clasificación y ficha

Cada libro puede llevar dos clasificaciones, que se escriben por su identificador o por su nombre, con o sin tildes y mayúsculas (`filosofia`, `Filosofía y ensayo` y `FILOSOFIA` son lo mismo):

- **`tradicion`**: la tradición cultural del texto. Las que hay están en `datos/tradiciones.json`: griega, romana, egipcia, mesopotámica, hebrea, persa, árabe, india, china, japonesa, celta, nórdica, medieval, renacentista, precolombina y moderna.
- **`categoria`**: la categoría de Marcalibros, un movimiento literario o un tipo de texto. Las 27 están en `datos/categorias.json`, copiadas de `marcalibros-plataforma-pos-brand-identity/catalog/` para que este proyecto no dependa de esa carpeta: si allí cambian las categorías, se actualiza el archivo a mano.

Un identificador que no existe se rechaza con la lista de los que sí.

### La ficha de la contratapa y las solapas

Si el libro trae `epoca`, `tradicion`, `categoria` o `cita`, y no trae los textos escritos a mano, la contratapa y las solapas se arman con esos datos. **No hay sinopsis inventada**: la ficha solo dice lo que ya está en los datos. Por ejemplo, la contratapa de *Meditaciones* sale así:

```
«Lo que no conviene a la colmena, no conviene a la abeja.»

«Meditaciones» es una obra de Marco Aurelio (siglo II), de la tradición romana.

Tipo de texto: Filosofía y ensayo.

224 páginas.
```

- **Contratapa**: la cita (si hay), qué obra es y de quién, la clasificación, la traducción y la extensión.
- **Solapa de la portada**: la clasificación, la traducción y la extensión.
- **Solapa de la contratapa**: el autor, su época y la tradición.

Lo escrito a mano manda: si el libro trae `contratapa`, `solapa_portada` o `solapa_contratapa`, ese texto se usa tal cual, aunque sea vacío (`"contratapa": ""` deja la contratapa sin texto). Sin ninguno de los cuatro datos, no se arma nada.

## Lista de libros

Para muchos libros, una fila de un CSV por libro, en UTF-8 (en Excel o LibreOffice: «CSV UTF-8»). El separador puede ser coma, punto y coma o tabulador. La primera fila son los encabezados, que no distinguen mayúsculas ni tildes (`Título` y `titulo` son lo mismo) y pueden ir en cualquier orden. Una celda vacía es no dar el dato.

```csv
titulo,subtitulo,autor,epoca,tradicion,categoria,paginas,cita,editorial,coleccion
Meditaciones,,Marco Aurelio,siglo II,romana,filosofia,224,"Lo que no conviene a la colmena, no conviene a la abeja.",Edición de ejemplo,Clásicos
Tao Te King,,Lao Tsé,siglo IV a. C.,china,libros-sagrados,96,,Edición de ejemplo,Clásicos
```

| Columna | Qué es |
|---|---|
| `titulo`, `autor` | Obligatorias: tienen que estar en los encabezados y llenas en cada fila. |
| `paginas` | Número entero. Obligatoria salvo que la fila dé `lomo_mm`. |
| `id` | Nombre de la carpeta del libro: minúsculas sin tilde, cifras y guiones. Si falta, sale del autor y el título (`marco-aurelio-meditaciones`). |
| `subtitulo`, `traductor`, `editorial`, `coleccion`, `isbn` | Como en el JSON. |
| `epoca`, `tradicion`, `categoria`, `cita` | Ver «Clasificación y ficha». |
| `contratapa`, `solapa_portada`, `solapa_contratapa` | Texto a mano; manda sobre la ficha. Un salto de línea doble separa párrafos. |
| `plantilla`, `solapas_mm`, `lomo_mm` | Como en el JSON (los mm admiten punto o coma decimal). |
| `fondo`, `texto`, `acento` | Colores `#rrggbb`. |

El resto de campos del JSON (`formato`, `sangrado_mm`, `degradado`…) no están en la lista: valen los de siempre, y un libro que los necesite se escribe en JSON.

Cada fila pasa por los mismos controles que un JSON. Si alguna falla, el error nombra la fila (como se ve en la hoja de cálculo) y el título, se dan todas juntas y no se escribe ningún libro, para corregir la lista de una vez:

```
lista.csv: 2 filas con errores:
  fila 4 («Mala tradición»): 'tradicion': la tradición «marciana» no existe; usa una de: griega, romana, …
  fila 9 («Sin autor»): 'titulo' y 'autor' son obligatorios.
```

Una columna que no se conoce (un `tradicioon` mal escrito) también se rechaza, en vez de ignorarla en silencio.

Con 300 libros, el generador tarda medio segundo y escribe unos 85 MB (cada SVG lleva sus fuentes). Si un título o un texto lleva letras que las fuentes no cubren, el generador lo avisa por libro y sigue.

## Medidas

La cubierta completa, de izquierda a derecha, es:

```
sangrado | solapa | contratapa | lomo | portada | solapa | sangrado
```

Sin solapas, esas dos no existen y el sangrado de cada lado pega con la contratapa o con la portada.

- **Lomo** = `ceil(páginas / 2) × grosor_hoja_mm`, salvo que des `lomo_mm`. Con 0,1 mm por hoja, un papel de unos 80 g/m². Es una aproximación: confírmalo con la imprenta.
- **Ancho de la cubierta completa** = `2 × sangrado + 2 × ancho + lomo`, más `2 × solapas` si las hay.
- **Alto** = `alto + 2 × sangrado`.

Con los ejemplos: el de Platón tiene 112 páginas, así que su lomo mide 5,6 mm y su sobrelomo, con solapas de 90 mm, mide 461,6 × 216 mm. El de Sun Tzu (96 páginas, sin solapas) mide 280,8 × 216 mm.

## Degradados

El fondo puede ser un degradado en lugar de un color sólido. Se escribe en SVG, dentro de un `<defs>`, y el lomo lo comparte, así que no se corta.

```json
"degradado": { "tipo": "lineal", "angulo": 90, "colores": ["#f3ead7", "#c9a96e"] }
```

- `tipo`: `lineal` (por defecto) o `radial`. El radial sale del centro de la cubierta completa y llega a las esquinas.
- `angulo`: en grados, solo para el lineal. `0` va de izquierda a derecha y `90` de arriba abajo (por defecto `90`).
- `colores`: una lista de 2 a 6 colores en `#rrggbb`, repartidos a partes iguales. O la palabra `"auto"` (por defecto): el fondo va hacia un segundo color que se aleja del color del texto, así que el contraste mejora de un extremo al otro.

Las coordenadas van en milímetros sobre la cubierta completa. Por eso el degradado coincide al unir las piezas y cada archivo muestra su tramo correcto. El `medidas.txt` describe el degradado usado.

Un `"degradado": {}` vacío equivale a un degradado `auto` lineal de arriba abajo.

## Plantillas

- **`clasica`**: fondo crema, marco doble, título centrado en serif, un rombo como ornamento y el autor en mayúsculas espaciadas.
- **`sobria`**: fondo oscuro, sans, título a la izquierda, regla de color de acento y lomo en ese mismo color.

Para añadir una plantilla, crea `src/Plantillas/Nombre.php` que extienda `Plantilla`. Tiene que implementar `coloresPorDefecto()` y `portada()`, y puede redefinir `familia()` (`serif` por defecto, o `sans`) y `coloresLomo()`. Para medir un texto antes de colocarlo, usa `$this->medidaDe()`, con los atributos de estilo del texto si no es el normal (`['font-style' => 'italic']`, `['font-weight' => 'bold']`). Después regístrala en `Plantilla::disponibles()`. La contratapa, el lomo y las solapas se heredan.

## Fuentes

Los SVG llevan sus fuentes dentro, como `@font-face` con el archivo `woff2` en base64, así que no dependen de lo que tenga instalado quien los abra. Son libres (licencia OFL, con su texto en `fuentes/`):

| Familia | Quién la usa | Archivos |
|---|---|---|
| Source Serif 4 | `clasica` (normal y cursiva) | `fuentes/source-serif-4-latin-wght-*.woff2` |
| Inter | `sobria` (normal y negrita) | `fuentes/inter-latin-wght-normal.woff2` |

Cada pieza incrusta solo las caras que dibuja: el lomo de una portada con subtítulo en cursiva no lleva la cursiva. Por eso un SVG pesa entre 65 y 140 KB, casi todo fuentes.

Las fuentes son el subconjunto latino: cubren español, francés, italiano, portugués, alemán y los idiomas del latín básico, pero **no** `ł`, `ş`, `č`, `ă`, `ő` ni otras letras del este y el centro de Europa, ni griego o cirílico. Esas letras salen con la fuente de reserva del visor. `MedidaFuente::noCubiertas()` dice cuáles faltan en un texto.

### El ancho del texto

El ajuste de títulos, subtítulos, rótulos y párrafos mide el texto con los anchos reales de cada letra, que están en `datos/anchos.json`. No tiene en cuenta el kerning, que casi siempre acorta un poco la línea, así que mide igual o algo más que un navegador (hasta un 4 % más en los textos probados) y un texto que cabe en la estimación cabe también al dibujarlo. Los títulos se reparten en líneas parejas, sin dejar una palabra suelta en la última.

`datos/anchos.json` se genera con `herramientas/medir-fuentes.py` (`pip install fonttools brotli`). Solo hay que volver a correrla si cambian las fuentes de `fuentes/` o los pesos que usan las plantillas; una prueba compara la tabla con las medidas de un navegador.

## Antes de imprimir

- **Fuentes.** Van incrustadas (ver «Fuentes»). Algunas imprentas piden el texto convertido a trazos: en Inkscape, Trayecto → Objeto a trazado.
- **Ajuste del texto.** Mira siempre la vista previa. Si un texto no cabe ni al tamaño mínimo, se recorta con «…»: acórtalo en el JSON en vez de dejar que se recorte.
- **Degradados al imprimir.** Las impresoras los convierten a CMYK, y eso puede cambiar tonos o mostrar bandas en degradados suaves. Pide prueba de color al impresor.
- **Vista previa en imagen.** `rsvg-convert -w 2000 portada.svg -o portada.png`, Inkscape o Chromium. Los SVG están en milímetros.
- **Código de barras.** El rectángulo blanco de la contratapa es un hueco reservado para el EAN-13 del ISBN. Este sistema no genera el código: lo pone quien imprime.
- **Tapa dura.** No se modela el grosor de las tapas. Ajusta `lomo_mm` y las medidas con la imprenta.
- **Imágenes.** La portada no admite todavía una ilustración.

## Dominio público

Los textos antiguos (Platón, Sun Tzu) son de dominio público, pero **una traducción moderna puede no serlo**. En España y en la UE, la protección dura toda la vida del traductor más 70 años. Comprueba la licencia de la traducción antes de poner el nombre del traductor. Por eso los ejemplos dejan `traductor` vacío.

## Pruebas

```bash
php pruebas/ejecutar.php          # pruebas unitarias
php bin/generar.php --comprobar   # ejemplos/salida/ coincide con lo que genera el código
phpstan analyse --no-progress     # análisis estático, nivel máximo (phpstan.neon.dist)
```

Las pruebas de geometría comprueban, con textos largos y cortos y en las dos plantillas, que ningún texto se salga de su panel, que no se pise con otro texto ni con los adornos (rombo, reglas, hueco del código de barras), y que el subtítulo deje bajo el título un espacio proporcional a su tamaño. Miden con la misma tabla de anchos que las plantillas, que no tiene kerning: la vista previa en un navegador es la prueba final.

Si cambias el generador, regenera los ejemplos con `php bin/generar.php ejemplos/*.json --salida=ejemplos/salida`. El workflow `pruebas-portadas-svg.yml` corre las pruebas, la comprobación de los ejemplos, PHPStan 2.1 y `composer validate` cuando cambia esta carpeta.

## Estructura

```
bin/generar.php          línea de comandos
src/                     Libro (con Formato y Textos), Lista (CSV), Catalogo, Ficha, Nombres, Medidas, Texto, Medida (con FactorFijo y MedidaFuente), Anchos, Fuentes, Color, Degradado, Svg, Generador, Cli, Archivos y las plantillas
fuentes/                 fuentes woff2 que se incrustan en los SVG, con sus licencias
datos/anchos.json        ancho de cada letra de esas fuentes, para ajustar el texto
datos/categorias.json    las 27 categorías de Marcalibros
datos/tradiciones.json   las tradiciones culturales
herramientas/            medir-fuentes.py, que genera datos/anchos.json
ejemplos/*.json          libros de ejemplo
ejemplos/lista.csv       lista de ejemplo, con la ficha armada a partir de los datos
ejemplos/salida/         SVG de los ejemplos, versionados y comprobados por CI
pruebas/ejecutar.php     pruebas unitarias, sin dependencias
```

## Limitaciones

- El ajuste de texto mide con anchos reales pero sin kerning: las líneas pueden quedar hasta un 4 % más cortas de lo posible. Las letras que las fuentes no cubren (ver «Fuentes») se miden con un ancho medio.
- Cada SVG pesa entre 65 y 140 KB por las fuentes incrustadas.
- No genera el código de barras ni el PDF para imprenta; se obtiene desde los SVG con una herramienta externa.
- Solo hay dos plantillas.
