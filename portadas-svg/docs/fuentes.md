# Fuentes

Los SVG llevan sus fuentes dentro, como `@font-face` con archivos `woff2` en base64, así que no dependen de lo que tenga instalado quien los abra. Son libres (licencia OFL, con su texto en `fuentes/`; el origen, la versión y lo que se les hizo están en [`fuentes/ORIGEN.md`](../fuentes/ORIGEN.md)):

| Familia | Quién la usa | Caras |
|---|---|---|
| Source Serif 4 | `clasica` y `tipografica` | normal y cursiva, peso 400 |
| Inter | `sobria` | normal, pesos 400 y 700 |

Cada cara está cortada en **trozos por alfabeto**, cada uno con su `unicode-range`: las letras básicas (U+0020–007E), el resto del latín (tildes, `¿ ¡ « » · —`), el latín extendido (`ł ş č ő`…), el vietnamita, el griego, el griego politónico (solo en Inter), el cirílico y el cirílico extendido. Una pieza incrusta solo los trozos que sus textos necesitan: un texto en español lleva los dos primeros de cada cara, no la fuente entera, y el griego o el cirílico solo pesan si el título los usa. Por eso un SVG ocupa unos 20–60 KB y no más de 100.

Lo que ninguna de las dos fuentes tiene (chino, japonés, coreano, hebreo, árabe, devanagari…) sale con la fuente de reserva del visor, y el generador avisa por libro qué letras faltan (`MedidaFuente::noCubiertas()`). Para algunos de esos alfabetos hay una salida: ver [Alfabetos sin fuente](#alfabetos-sin-fuente).

## Texto a trazos (`--trazos`)

Algunos programas ignoran las fuentes incrustadas en un SVG: CairoSVG, por ejemplo, las cambia por otras, y lo mismo pueden hacer Inkscape y algunos lectores de ebook. Con `--trazos` el texto no se escribe con la fuente sino como contornos, y la pieza no depende de ninguna:

```bash
php bin/generar.php ejemplos/platon-apologia.json --trazos
```

Cada letra es un trazado definido una sola vez en `<defs>` (con `fuentes/trazos/*.json.gz`, generados por `herramientas/preparar-fuentes.py`) y colocado con `<use>`. Se ve igual que con la fuente en Chromium y en CairoSVG, y una imprenta que pide el texto convertido a trazos lo recibe ya hecho. A cambio, el texto no se puede seleccionar ni buscar (la descripción accesible de cada SVG sigue teniéndolo) y aplica el mismo kerning que la medida del texto. Sirve para los mismos alfabetos que las fuentes.

## Alfabetos sin fuente

Meter en el repositorio una fuente de chino o de japonés pesaría decenas de megas. En su lugar, el proyecto saca los contornos de **una fuente que ya tengas** (por ejemplo Noto Sans CJK, de licencia libre), solo de las letras que tus libros usan:

```bash
pip install fonttools brotli
python3 herramientas/glifos-extra.py NotoSansTC-Regular.otf fuentes/extra/chino.json --lista libros.csv
php bin/generar.php --lista=libros.csv --trazos
```

`--lista` toma del CSV (o del JSON) las letras que las fuentes del proyecto no tienen; también vale `--texto "道德經"`. Con esos contornos el generador dibuja las letras (solo con `--trazos`: una fuente incrustada no se puede componer con ellas) y **mide el texto con su ancho real**, así que un título en chino no se sale de la portada. Los `.json` van en `fuentes/extra/` o en otra carpeta que pases con `--extra=carpeta` (útil si la licencia de tu fuente no permite redistribuir sus contornos). Sin ellos el generador avisa de las letras y las deja en blanco.

Sirve para los alfabetos que se escriben letra a letra y de izquierda a derecha (chino, japonés, coreano). Esto sirve para los alfabetos que se escriben letra a letra y de izquierda a derecha (chino, japonés, coreano). Para los demás, ver abajo.

### Árabe, hebreo y devanagari: palabras ya compuestas

En esos alfabetos una letra cambia de forma según las vecinas (árabe, devanagari) o se escribe de derecha a izquierda (hebreo, árabe): no basta con un contorno por letra. Para ellos, el mismo script compone **cada palabra de antemano con HarfBuzz** (el motor de formas de letras de los navegadores) y guarda la palabra entera, con sus letras ya colocadas:

```bash
pip install fonttools brotli uharfbuzz
python3 herramientas/glifos-extra.py NotoSansArabic-Regular.otf fuentes/extra/arabe.formas.json --formas --lista libros.csv
python3 herramientas/glifos-extra.py NotoSansHebrew-Regular.otf fuentes/extra/hebreo.formas.json --formas --lista libros.csv
python3 herramientas/glifos-extra.py NotoSansDevanagari-Regular.otf fuentes/extra/hindi.formas.json --formas --lista libros.csv
php bin/generar.php --lista=libros.csv --trazos
```

El archivo se llama `*.formas.json`. Cada script toma de la lista las palabras que su fuente puede componer y deja las demás. Con ellas, el generador:

- dibuja la palabra tal cual (el árabe sale unido y en la forma de cada posición; el devanagari con sus conjuntos de letras) y la **mide** como es;
- encuentra la palabra aunque lleve signos pegados (`«كليلة»`, `الكتاب.`);
- coloca de derecha a izquierda una línea escrita solo con palabras así, y en una frase mezclada («…es una obra de ابن المقفع»), cada tramo seguido de ellas.

Es un bidi mínimo: no resuelve números ni paréntesis dentro de un tramo, no alinea a la derecha un párrafo entero de derecha a izquierda, y una palabra que no esté en el archivo no se dibuja (hay que volver a correr el script cuando cambian los textos). Probado con Noto Sans Arabic, Hebrew y Devanagari en Chromium.

## El ancho del texto

El ajuste de títulos, subtítulos, rótulos y párrafos mide el texto con los anchos reales de cada letra, que están en `datos/anchos.json`. Suma además el kerning de los pares ASCII (ver [Kerning](#kerning)); lo que no ajusta (letras con acento, por ejemplo) hace que mida igual o algo más que un navegador, y un texto que cabe en la estimación cabe también al dibujarlo. Los títulos se reparten en líneas parejas, sin dejar una palabra suelta en la última.

## Volver a preparar las fuentes

`fuentes/*.woff2`, `datos/fuentes.json` (qué letras trae cada trozo) y `datos/anchos.json` los genera `herramientas/preparar-fuentes.py` a partir de las fuentes variables de Fontsource. Solo hay que correrla si cambian las fuentes o los pesos que usan las plantillas:

```bash
pip install fonttools brotli
npm pack @fontsource-variable/source-serif-4@5.3.1 @fontsource-variable/inter@5.3.0
# extrae los dos .tgz, junta sus carpetas files/ en una y pásala:
python3 herramientas/preparar-fuentes.py carpeta/con/los/woff2
```

Una prueba compara la tabla de anchos con las medidas de un navegador.

## Kerning

`datos/kerning.json` guarda el ajuste de cada par de letras ASCII seguidas dentro de una palabra («AV» se acerca, por ejemplo), calculado con HarfBuzz. Lo usan la medida del texto y el texto a trazos. Los pares con letras fuera de ASCII no se ajustan (ahí la medida sale igual o algo mayor que la real, no menor). Se regenera con `python3 herramientas/kerning.py` (necesita `fonttools`, `brotli` y `uharfbuzz`), y `--comprobar` falla si no está al día.
