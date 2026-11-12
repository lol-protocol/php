# portadas-svg

Mini sistema para diagramar cubiertas de libros en SVG: portada, contratapa, lomo, cubierta completa y sobrelomo (con solapas). Está pensado para libros de dominio público, como la *Apología de Sócrates* o *El arte de la guerra*.

Cada libro es un JSON con sus datos. El generador escribe un SVG por pieza, con las medidas de corte, el sangrado y el lomo calculado a partir de las páginas, más un `medidas.txt` para pasárselo a la imprenta.

## Requisitos

- PHP 8.3 o más, con las extensiones `mbstring` y `zlib` (las pruebas usan además `dom`). `composer.json` declara esos requisitos y dos atajos (`composer test` y `composer comprobar`).
- Sin dependencias: no hace falta `composer install`.
- Para ver o convertir los SVG: cualquier navegador, Inkscape o `rsvg-convert`. Las fuentes van dentro del SVG, así que se ven igual en todos.
- Solo para volver a medir las fuentes o el kerning (casi nunca) y para las pruebas de las herramientas: Python 3 con `fonttools`, `brotli` y `uharfbuzz` (y `resvg-py` y `Pillow` para la regresión visual). Ver [Fuentes](docs/fuentes.md).
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

Para cientos de libros, una lista en CSV (ver [Lista de libros](docs/lista-de-libros.md)):

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
| `formato` (con nombre) | — | En lugar de las medidas: `novela`, `bolsillo`, `a5`, `a6` o `ebook`. |
| `sangrado_mm` | `3` | Sangrado en cada borde exterior, hasta 10 mm. |
| `solapas_mm` | `0` | Ancho de cada solapa. `0` no pone sobrelomo; si no es `0`, mínimo 40 y no más ancho que la portada. |
| `plantilla` | `clasica` | `clasica`, `sobria`, `tipografica`, `ilustrada` o `cartel`. Ver [Plantillas](docs/plantillas.md). |
| `tema` | de la plantilla | `claro` u `oscuro`. |
| `volumen` | vacío | Número de volumen de una serie: va en el lomo y en el pie. |
| `imagen`, `logo` | vacío | Ilustración de la portada (en `ilustrada`; marca de agua en las demás, salvo `cartel`) y logo de la editorial (contratapa). SVG, PNG, JPG, GIF o WebP de hasta 2 MB. |
| `idioma` | `es` | Idioma del texto de la cubierta (`es`, `en`, `pt-BR`…). Va en cada SVG para los lectores de pantalla, y decide el idioma de las frases de la ficha (`es`, `en`, `pt` y `fr`; otros caen al español, editables en `datos/frases.json`) y las mayúsculas del autor (turco, azerí). |
| `colores.fondo`, `colores.texto`, `colores.acento` | los de la plantilla | Colores en `#rrggbb`. |
| `degradado` | sin degradado | Degradado del fondo de toda la cubierta. Ver [Medidas y degradados](docs/medidas-y-degradados.md). |
| `lomo_direccion` | `arriba-abajo` | Sentido del texto del lomo: `arriba-abajo` o `abajo-arriba`. |
| `epoca` | vacío | Época del autor o de la obra, en texto libre (`siglo II`, `399 a. C.`). Solo para la ficha. |
| `tradicion` | vacío | Tradición cultural: una de la lista de [Clasificación](docs/clasificacion-y-ficha.md). |
| `categoria` | vacío | Categoría de Marcalibros: una de la lista de [Clasificación](docs/clasificacion-y-ficha.md). |
| `cita` | vacío | Una frase breve de la propia obra. Abre la contratapa, más grande y firmada por el autor. |
| `contratapa` | la ficha | Texto de la contratapa. Los párrafos van separados por una línea en blanco. |
| `solapa_contratapa` | la ficha | Texto de la solapa interior de la contratapa (solo con solapas). |
| `solapa_portada` | la ficha | Texto de la solapa interior de la portada (solo con solapas). |

El de `ejemplos/platon-apologia.json` usa casi todos los campos; el de Sun Tzu, solo los básicos y la clasificación.

Las medidas fuera de esos límites se rechazan con un error que nombra el campo: casi siempre es un error de unidades (centímetros o pulgadas en vez de milímetros).

Los caracteres de control de un texto pegado se limpian al escribir el SVG: el salto de página que trae un PDF se lee como espacio y los demás se quitan, porque XML no los admite.

Las medidas fuera de esos límites y los textos demasiado largos (título de más de 300 caracteres, contratapa de más de 5000…) se rechazan con un error que nombra el campo; el ISBN con dígito de control que no cuadra solo avisa.

## Documentación

| Documento | De qué trata |
|---|---|
| [Línea de comandos](docs/linea-de-comandos.md) | Opciones, manifiesto, índice, códigos de salida y avisos. |
| [Clasificación y ficha](docs/clasificacion-y-ficha.md) | Tradiciones, categorías, adornos, motivos y la ficha automática. |
| [Lista de libros](docs/lista-de-libros.md) | El CSV, columna por columna. |
| [Medidas y degradados](docs/medidas-y-degradados.md) | Cómo se calcula la cubierta y sus degradados. |
| [Plantillas](docs/plantillas.md) | Las cinco plantillas, temas, formatos, volumen, imagen y logo. |
| [Fuentes](docs/fuentes.md) | Fuentes incrustadas, texto a trazos, alfabetos sin fuente, kerning. Licencias en [`fuentes/ORIGEN.md`](fuentes/ORIGEN.md). |
| [Imágenes PNG o JPG](docs/imagenes-png-jpg.md) | Convertir los SVG para las tiendas de ebooks. |
| [Imprenta, accesibilidad y derechos](docs/imprenta-y-accesibilidad.md) | Antes de imprimir, lectores de pantalla y dominio público. |


## Pruebas

```bash
php pruebas/ejecutar.php          # pruebas unitarias
python3 -m unittest discover -s herramientas/pruebas   # herramientas de Python (fuente mínima generada)
python3 herramientas/kerning.py carpeta --comprobar    # datos/kerning.json.gz al día (ver docs/fuentes.md)
python3 herramientas/pruebas/visual.py                 # regresión visual (resvg-py y Pillow)
php bin/generar.php --comprobar   # ejemplos/salida/ coincide con lo que genera el código
phpstan analyse --no-progress     # análisis estático, nivel máximo (phpstan.neon.dist)
```

Las pruebas de geometría comprueban, con textos largos y cortos, en las tres plantillas, con las 16 tradiciones y los 27 motivos, que ningún texto se salga de su panel, que no se pise con otro texto ni con los adornos (rombo, reglas, hueco del código de barras), y que el subtítulo deje bajo el título un espacio proporcional a su tamaño. Miden con la misma tabla de anchos y de kerning que las plantillas: la vista previa en un navegador es la prueba final.

Con el repositorio completo, otra prueba comprueba que `datos/categorias.json` siga igual que las categorías de `marcalibros-plataforma-pos-brand-identity/catalog/manifest.json` (si allí cambian, hay que actualizar el archivo a mano; el workflow de este proyecto no corre al cambiar esa carpeta, así que se nota la próxima vez que se corren las pruebas). Otra guarda una huella de cada dibujo (`pruebas/dibujos.json`: cada adorno, cada motivo y cada plantilla con cada tradición) y falla si alguno cambia sin que lo hayas querido. Si lo cambiaste a propósito, `php pruebas/ejecutar.php --actualizar-dibujos` regenera el archivo y el diff muestra qué dibujos cambiaron. Las huellas se calculan con los números redondeados a dos decimales (una milésima de más o de menos en una curva, por la aritmética de cada plataforma, no las cambia); aun así, están hechas con PHP 8.3, el de la CI.

La regresión visual dibuja con resvg las piezas a trazos de `ejemplos/salida-trazos/` y las compara con los PNG de `pruebas/visual/` (tolerancia: un 0,2 % de píxeles distintos); con `--actualizar` se guardan los nuevos. Si cambias el generador, regenera los ejemplos con `php bin/generar.php ejemplos/*.json --salida=ejemplos/salida` y `php bin/generar.php ejemplos/*.json --trazos --salida=ejemplos/salida-trazos`, y los PNG. El workflow `pruebas-portadas-svg.yml` corre las pruebas, la comprobación de los ejemplos, las pruebas de las herramientas de Python (con el kerning y la regresión visual), PHPStan 2.1 y `composer validate` cuando cambia esta carpeta.

## Estructura

```
bin/generar.php          línea de comandos
src/Adornos/             un adorno por tradición, el Generico, los 27 Motivo de las categorías y los Trazos que comparten
src/                     Libro (con Formato y Textos), Lista (CSV), Catalogo, Ficha, Frases, Isbn, Mayusculas, Indice, Nombres, Numeros, TextoATrazos, Medidas, Texto, Medida (con FactorFijo y MedidaFuente), Anchos, Fuentes, Color, Degradado, Svg, Generador, Cli, Archivos y las plantillas
fuentes/                 las fuentes en trozos woff2 que se incrustan en los SVG, con sus licencias
fuentes/trazos/          el contorno de cada letra de esos trozos (comprimido), para --trazos
fuentes/extra/           los contornos de otras letras, de una fuente tuya (ver [Fuentes](docs/fuentes.md))
datos/fuentes.json       qué letras trae cada trozo (su unicode-range)
datos/anchos.json        ancho de cada letra de esas fuentes, para ajustar el texto
datos/kerning.json.gz    ajuste de los pares de letras latinas, griegas y cirílicas
datos/frases.json        las frases de la ficha, por idioma
datos/categorias.json    las 27 categorías de Marcalibros
datos/tradiciones.json   las tradiciones culturales
herramientas/pruebas/     pruebas de las herramientas y regresión visual (pruebas/visual/ guarda los PNG)
herramientas/            kerning.py, preparar-fuentes.py (genera fuentes/, datos/fuentes.json y datos/anchos.json), glifos-extra.py (contornos y palabras compuestas de una fuente tuya) y a-png.sh (SVG a PNG o JPG)
ejemplos/*.json          libros de ejemplo
ejemplos/lista.csv       lista de ejemplo, con la ficha armada a partir de los datos
ejemplos/salida/         SVG de los ejemplos, versionados y comprobados por CI
ejemplos/salida-trazos/  los mismos, con el texto a trazos
docs/                    la documentación por temas
pruebas/ejecutar.php     corre las pruebas, sin dependencias
pruebas/temas/           las pruebas, por temas (libro, lista, fuentes, dibujos, color, texto)
pruebas/ayudas.php       aserciones y funciones que comparten las pruebas
pruebas/dibujos.json     huellas de los dibujos, para que no cambien sin querer
```

## Limitaciones

- El ajuste de texto mide con anchos reales y con kerning de los pares latinos, griegos y cirílicos; en los alfabetos sin fuente no se ajusta, así que ahí la línea puede quedar algo más corta de lo posible. Las letras que las fuentes no cubren (ver [Fuentes](docs/fuentes.md)) se miden con un ancho medio.
- Cada SVG pesa unos 20–60 KB por las fuentes incrustadas.
- La imagen de la portada es una ilustración solo en `ilustrada`; en clásica, sobria y tipográfica es una marca de agua al 25 % cuyo contraste con el texto no se mide, y el cartel no la dibuja.
- Probado en Chromium, CairoSVG, resvg, librsvg (`rsvg-convert`) e Inkscape 1.2. Todos menos Chromium **ignoran las fuentes incrustadas** (Inkscape lo avisa: «font face rule limited support») y usan otras: la maqueta aguanta, pero no se ve igual. Para esos visores, `--trazos`, que se ve igual que en Chromium. No he probado lectores de ebook, que pueden hacer lo mismo. El enlace de Python de resvg (`resvg-py`) no acepta el tamaño en mm del `width`/`height`; hay que pasarlo a píxeles.
- No genera el código de barras ni el PDF para imprenta; se obtiene desde los SVG con una herramienta externa.
- Hay cinco plantillas; para otra, ver [Plantillas](docs/plantillas.md).
- El texto de derecha a izquierda se alinea a la derecha solo si el párrafo entero lo es; no hay bidi completo.
