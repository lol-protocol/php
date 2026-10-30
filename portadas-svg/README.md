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
| `plantilla` | `clasica` | `clasica`, `sobria` o `tipografica`. Ver «Plantillas». |
| `idioma` | `es` | Idioma del texto de la cubierta (`es`, `en`, `pt-BR`…). Va en cada SVG para los lectores de pantalla. |
| `colores.fondo`, `colores.texto`, `colores.acento` | los de la plantilla | Colores en `#rrggbb`. |
| `degradado` | sin degradado | Degradado del fondo de toda la cubierta. Ver «Degradados». |
| `lomo_direccion` | `arriba-abajo` | Sentido del texto del lomo: `arriba-abajo` o `abajo-arriba`. |
| `epoca` | vacío | Época del autor o de la obra, en texto libre (`siglo II`, `399 a. C.`). Solo para la ficha. |
| `tradicion` | vacío | Tradición cultural: una de la lista de «Clasificación». |
| `categoria` | vacío | Categoría de Marcalibros: una de la lista de «Clasificación». |
| `cita` | vacío | Una frase breve de la propia obra. Abre la contratapa, más grande y firmada por el autor. |
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

### Adornos por tradición

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

### Un motivo por categoría

Cada una de las 27 categorías tiene un dibujo pequeño (`src/Adornos/Motivo.php`): un amanecer para el romanticismo, una balanza para las leyes, una lira para la poesía, un faro para el modernismo anglosajón, una brújula para los viajes… Es de líneas finas en el color de acento y va en tres sitios:

- **Contratapa** (las tres plantillas): abajo a la izquierda, sobre el pie.
- **Lomo**: abajo, en el color del texto del lomo, si el lomo mide 5 mm o más; el título se acorta lo justo, con el mismo margen arriba y abajo. En un lomo más estrecho no se pone.
- **Portada sobria**: arriba a la derecha, frente al friso.

Una prueba comprueba que haya uno por cada categoría de `datos/categorias.json` y que quepa en su cuadrado.

### Colores por categoría

Cada categoría tiene un `tono` (0–360°) en `datos/categorias.json`, repartidos a partes iguales (13° entre uno y el siguiente) y mezclados para que las categorías de una misma familia queden lejos entre sí. Si el libro no da `colores`, la plantilla se tiñe de ese tono: la clásica y la tipográfica con fondo claro, la sobria con fondo oscuro, y en todas el acento del mismo tono. Así los libros de una categoría se parecen entre sí. Los `colores` del libro mandan sobre los de la categoría, color por color. Una prueba comprueba que, en las 27 categorías y las tres plantillas, el texto tenga contraste 7:1 con el fondo y el acento 3:1.

### La ficha de la contratapa y las solapas

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
| `plantilla`, `idioma`, `lomo_direccion`, `solapas_mm`, `lomo_mm`, `sangrado_mm`, `grosor_hoja_mm` | Como en el JSON (los mm admiten punto o coma decimal). |
| `ancho_mm`, `alto_mm` | El formato de corte (`formato.ancho_mm` y `formato.alto_mm` del JSON). |
| `degradado`, `degradado_tipo`, `degradado_angulo` | El degradado del fondo: `auto` o de 2 a 6 colores separados por espacios (`#f3ead7 #c9a96e`); `lineal` o `radial`; y los grados. Con solo el tipo o el ángulo, los colores son `auto`. |
| `fondo`, `texto`, `acento` | Colores `#rrggbb`. |

Cada fila pasa por los mismos controles que un JSON. Si alguna falla, el error nombra la fila (como se ve en la hoja de cálculo) y el título, se dan todas juntas y no se escribe ningún libro, para corregir la lista de una vez:

```
lista.csv: 2 filas con errores:
  fila 4 («Mala tradición»): 'tradicion': la tradición «marciana» no existe; usa una de: griega, romana, …
  fila 9 («Sin autor»): 'titulo' y 'autor' son obligatorios.
```

Una columna que no se conoce (un `tradicioon` mal escrito) también se rechaza, en vez de ignorarla en silencio.

Si prefieres seguir con las filas buenas, `--omitir-errores` salta las malas, avisa de cada una (con su fila y su error) y escribe el resto:

```bash
php bin/generar.php --lista=libros.csv --omitir-errores --salida=salida
```

Con 300 libros, el generador tarda un par de segundos y escribe unos 60 MB (cada SVG lleva las partes de fuente que usa). Si un título o un texto lleva letras que las fuentes no cubren, el generador lo avisa por libro y sigue.

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

- **`clasica`**: fondo crema, marco doble, título centrado en serif, el adorno de la tradición (o un rombo) y el autor en mayúsculas espaciadas.
- **`sobria`**: fondo oscuro, sans, título a la izquierda, friso o barra de color de acento y lomo en ese mismo color.
- **`tipografica`**: fondo claro sin marco, el autor en mayúsculas espaciadas sobre un título grande en serif, a la izquierda, y el emblema de la tradición en grande, de línea fina, ocupando la parte de abajo.

Para añadir una plantilla, crea `src/Plantillas/Nombre.php` que extienda `Plantilla`. Tiene que implementar `coloresPorDefecto()`, `paleta()` (sus colores teñidos de un tono) y `portada()`, y puede redefinir `familia()` (`serif` por defecto, o `sans`) y `coloresLomo()`. Para medir un texto antes de colocarlo, usa `$this->medidaDe()`, con los atributos de estilo del texto si no es el normal (`['font-style' => 'italic']`, `['font-weight' => 'bold']`). Después regístrala en `Plantilla::disponibles()`. La contratapa, el lomo y las solapas se heredan.

## Fuentes

Los SVG llevan sus fuentes dentro, como `@font-face` con archivos `woff2` en base64, así que no dependen de lo que tenga instalado quien los abra. Son libres (licencia OFL, con su texto en `fuentes/`):

| Familia | Quién la usa | Caras |
|---|---|---|
| Source Serif 4 | `clasica` y `tipografica` | normal y cursiva, peso 400 |
| Inter | `sobria` | normal, pesos 400 y 700 |

Cada cara está cortada en **trozos por alfabeto**, cada uno con su `unicode-range`: las letras básicas (U+0020–007E), el resto del latín (tildes, `¿ ¡ « » · —`), el latín extendido (`ł ş č ő`…), el vietnamita, el griego, el griego politónico (solo en Inter), el cirílico y el cirílico extendido. Una pieza incrusta solo los trozos que sus textos necesitan: un texto en español lleva los dos primeros de cada cara, no la fuente entera, y el griego o el cirílico solo pesan si el título los usa. Por eso un SVG ocupa unos 20–60 KB y no más de 100.

Lo que ninguna de las dos fuentes tiene (chino, japonés, árabe, hebreo, devanagari…) sale con la fuente de reserva del visor, y el generador avisa por libro qué letras faltan (`MedidaFuente::noCubiertas()`).

### Texto a trazos (`--trazos`)

Algunos programas ignoran las fuentes incrustadas en un SVG: CairoSVG, por ejemplo, las cambia por otras, y lo mismo pueden hacer Inkscape y algunos lectores de ebook. Con `--trazos` el texto no se escribe con la fuente sino como contornos, y la pieza no depende de ninguna:

```bash
php bin/generar.php ejemplos/platon-apologia.json --trazos
```

Cada letra es un trazado definido una sola vez en `<defs>` (con `fuentes/trazos/*.json`, generados por `herramientas/preparar-fuentes.py`) y colocado con `<use>`. Se ve igual que con la fuente en Chromium y en CairoSVG, y una imprenta que pide el texto convertido a trazos lo recibe ya hecho. A cambio, el texto no se puede seleccionar ni buscar (la descripción accesible de cada SVG sigue teniéndolo) y no aplica kerning, igual que la medida del texto. Sirve para los mismos alfabetos que las fuentes.

### El ancho del texto

El ajuste de títulos, subtítulos, rótulos y párrafos mide el texto con los anchos reales de cada letra, que están en `datos/anchos.json`. No tiene en cuenta el kerning, que casi siempre acorta un poco la línea, así que mide igual o algo más que un navegador (hasta un 4 % más en los textos probados) y un texto que cabe en la estimación cabe también al dibujarlo. Los títulos se reparten en líneas parejas, sin dejar una palabra suelta en la última.

### Volver a preparar las fuentes

`fuentes/*.woff2`, `datos/fuentes.json` (qué letras trae cada trozo) y `datos/anchos.json` los genera `herramientas/preparar-fuentes.py` a partir de las fuentes variables de Fontsource. Solo hay que correrla si cambian las fuentes o los pesos que usan las plantillas:

```bash
pip install fonttools brotli
npm pack @fontsource-variable/source-serif-4@5.3.1 @fontsource-variable/inter@5.3.0
# extrae los dos .tgz, junta sus carpetas files/ en una y pásala:
python3 herramientas/preparar-fuentes.py carpeta/con/los/woff2
```

Una prueba compara la tabla de anchos con las medidas de un navegador.

## Accesibilidad

Cada SVG se anuncia como una imagen (`role="img"`) con su `<title>` y su `<desc>`, y declara el idioma del libro (`lang` y `xml:lang`, del campo `idioma`). La descripción dice qué pieza es y de qué libro, y la de la contratapa lleva su texto, porque un lector de pantalla no recorre el texto dibujado.

## Antes de imprimir

- **Fuentes.** Van incrustadas (ver «Fuentes»). Si la imprenta pide el texto convertido a trazos, usa `--trazos`.
- **Ajuste del texto.** Mira siempre la vista previa. Si un texto no cabe ni al tamaño mínimo, se recorta con «…»: acórtalo en el JSON en vez de dejar que se recorte.
- **Degradados al imprimir.** Las impresoras los convierten a CMYK, y eso puede cambiar tonos o mostrar bandas en degradados suaves. Pide prueba de color al impresor.
- **Vista previa en imagen.** `rsvg-convert -w 2000 portada.svg -o portada.png`, Inkscape o Chromium. Los SVG están en milímetros.
- **Código de barras.** El rectángulo blanco de la contratapa es un hueco reservado para el EAN-13 del ISBN. Este sistema no genera el código: lo pone quien imprime.
- **Tapa dura.** No se modela el grosor de las tapas. Ajusta `lomo_mm` y las medidas con la imprenta.
- **Imágenes.** La portada no admite todavía una ilustración.

## Dominio público

Los textos antiguos (Platón, Sun Tzu) son de dominio público, pero **una traducción moderna puede no serlo**. En España y en la UE, la protección dura toda la vida del traductor más 70 años. Comprueba la licencia de la traducción antes de poner el nombre del traductor. Por eso los ejemplos dejan `traductor` vacío, y el generador avisa de cada libro que lleva traductor.

## Pruebas

```bash
php pruebas/ejecutar.php          # pruebas unitarias
php bin/generar.php --comprobar   # ejemplos/salida/ coincide con lo que genera el código
phpstan analyse --no-progress     # análisis estático, nivel máximo (phpstan.neon.dist)
```

Las pruebas de geometría comprueban, con textos largos y cortos, en las tres plantillas, con las 16 tradiciones y los 27 motivos, que ningún texto se salga de su panel, que no se pise con otro texto ni con los adornos (rombo, reglas, hueco del código de barras), y que el subtítulo deje bajo el título un espacio proporcional a su tamaño. Miden con la misma tabla de anchos que las plantillas, que no tiene kerning: la vista previa en un navegador es la prueba final.

Con el repositorio completo, otra prueba comprueba que `datos/categorias.json` siga igual que las categorías de `marcalibros-plataforma-pos-brand-identity/catalog/manifest.json` (si allí cambian, hay que actualizar el archivo a mano; el workflow de este proyecto no corre al cambiar esa carpeta, así que se nota la próxima vez que se corren las pruebas). Otra guarda una huella de cada dibujo (`pruebas/dibujos.json`: cada adorno, cada motivo y cada plantilla con cada tradición) y falla si alguno cambia sin que lo hayas querido. Si lo cambiaste a propósito, `php pruebas/ejecutar.php --actualizar-dibujos` regenera el archivo y el diff muestra qué dibujos cambiaron. Las huellas dependen de la aritmética de decimales de PHP: están hechas con PHP 8.3, el de la CI.

Si cambias el generador, regenera los ejemplos con `php bin/generar.php ejemplos/*.json --salida=ejemplos/salida`. El workflow `pruebas-portadas-svg.yml` corre las pruebas, la comprobación de los ejemplos, PHPStan 2.1 y `composer validate` cuando cambia esta carpeta.

## Estructura

```
bin/generar.php          línea de comandos
src/Adornos/             un adorno por tradición, el Generico, los 27 Motivo de las categorías y los Trazos que comparten
src/                     Libro (con Formato y Textos), Lista (CSV), Catalogo, Ficha, Nombres, Medidas, Texto, Medida (con FactorFijo y MedidaFuente), Anchos, Fuentes, Color, Degradado, Svg, Generador, Cli, Archivos y las plantillas
fuentes/                 las fuentes en trozos woff2 que se incrustan en los SVG, con sus licencias
fuentes/trazos/          el contorno de cada letra de esos trozos, para --trazos
datos/fuentes.json       qué letras trae cada trozo (su unicode-range)
datos/anchos.json        ancho de cada letra de esas fuentes, para ajustar el texto
datos/categorias.json    las 27 categorías de Marcalibros
datos/tradiciones.json   las tradiciones culturales
herramientas/            preparar-fuentes.py, que genera fuentes/, datos/fuentes.json y datos/anchos.json
ejemplos/*.json          libros de ejemplo
ejemplos/lista.csv       lista de ejemplo, con la ficha armada a partir de los datos
ejemplos/salida/         SVG de los ejemplos, versionados y comprobados por CI
pruebas/ejecutar.php     pruebas unitarias, sin dependencias
pruebas/dibujos.json     huellas de los dibujos, para que no cambien sin querer
```

## Limitaciones

- El ajuste de texto mide con anchos reales pero sin kerning: las líneas pueden quedar hasta un 4 % más cortas de lo posible. Las letras que las fuentes no cubren (ver «Fuentes») se miden con un ancho medio.
- Cada SVG pesa unos 20–60 KB por las fuentes incrustadas.
- La portada no admite imágenes: solo texto y adornos de línea.
- Probado en Chromium, CairoSVG y resvg. Los dos últimos ignoran las fuentes incrustadas y usan otras (la maqueta aguanta, pero no se ve igual): para esos visores, `--trazos`, que se ve igual que en Chromium. No he probado Inkscape ni lectores de ebook, que pueden hacer lo mismo. El enlace de Python de resvg (`resvg-py`) no acepta el tamaño en mm del `width`/`height`; hay que pasarlo a píxeles.
- No genera el código de barras ni el PDF para imprenta; se obtiene desde los SVG con una herramienta externa.
- Solo hay tres plantillas.
