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
| `contratapa` | vacío | Texto de la contratapa. Los párrafos van separados por una línea en blanco. |
| `solapa_contratapa` | vacío | Texto de la solapa interior de la contratapa (solo con solapas). |
| `solapa_portada` | vacío | Texto de la solapa interior de la portada (solo con solapas). |

El de `ejemplos/platon-apologia.json` usa casi todos los campos; el de Sun Tzu, solo los básicos.

Las medidas fuera de esos límites se rechazan con un error que nombra el campo: casi siempre es un error de unidades (centímetros o pulgadas en vez de milímetros).

Los caracteres de control de un texto pegado se limpian al escribir el SVG: el salto de página que trae un PDF se lee como espacio y los demás se quitan, porque XML no los admite.

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
src/                     Libro (con Formato y Textos), Medidas, Texto, Medida (con FactorFijo y MedidaFuente), Anchos, Fuentes, Color, Degradado, Svg, Generador, Cli, Archivos y las plantillas
fuentes/                 fuentes woff2 que se incrustan en los SVG, con sus licencias
datos/anchos.json        ancho de cada letra de esas fuentes, para ajustar el texto
herramientas/            medir-fuentes.py, que genera datos/anchos.json
ejemplos/*.json          libros de ejemplo
ejemplos/salida/         SVG de los ejemplos, versionados y comprobados por CI
pruebas/ejecutar.php     pruebas unitarias, sin dependencias
```

## Limitaciones

- El ajuste de texto mide con anchos reales pero sin kerning: las líneas pueden quedar hasta un 4 % más cortas de lo posible. Las letras que las fuentes no cubren (ver «Fuentes») se miden con un ancho medio.
- Cada SVG pesa entre 65 y 140 KB por las fuentes incrustadas.
- No genera el código de barras ni el PDF para imprenta; se obtiene desde los SVG con una herramienta externa.
- Solo hay dos plantillas.
