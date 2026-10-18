# portadas-svg

Mini sistema para diagramar cubiertas de libros en SVG: portada, contratapa, lomo, cubierta completa y sobrelomo (con solapas). Está pensado para libros de dominio público, como la *Apología de Sócrates* o *El arte de la guerra*.

Cada libro es un JSON con sus datos. El generador escribe un SVG por pieza, con las medidas de corte, el sangrado y el lomo calculado a partir de las páginas, más un `medidas.txt` para pasárselo a la imprenta.

## Requisitos

- PHP 8.3, con las extensiones `dom` y `mbstring`.
- Sin Composer ni dependencias.
- Para ver o convertir los SVG: cualquier navegador, Inkscape o `rsvg-convert`.

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
| `cubierta-completa.svg` | Contratapa, lomo y portada en una sola pieza. Sirve para rústica o tapa dura sin sobrelomo. |
| `sobrelomo.svg` | Cubierta completa con solapas. Solo existe si el libro tiene `solapas_mm`. |
| `medidas.txt` | Medidas de corte y tamaño de cada archivo, para la imprenta. |

Para varios libros a la vez, o para escribir en otra carpeta:

```bash
php bin/generar.php libro1.json libro2.json --salida=/ruta/de/salida
```

Cada pieza es un recorte del mismo dibujo de la cubierta completa, así que una pieza suelta coincide exactamente con su parte de la cubierta.

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
| `lomo_mm` | calculado | Lomo fijo, en mm. |
| `grosor_hoja_mm` | `0.1` | Grosor de una hoja (dos páginas). |
| `formato.ancho_mm`, `formato.alto_mm` | `135` y `210` | Medida de corte. |
| `sangrado_mm` | `3` | Sangrado en cada borde exterior. |
| `solapas_mm` | `0` | Ancho de cada solapa. `0` no pone sobrelomo; si no es `0`, mínimo 40. |
| `plantilla` | `clasica` | `clasica` o `sobria`. |
| `colores.fondo`, `colores.texto`, `colores.acento` | los de la plantilla | Colores en `#rrggbb`. |
| `lomo_direccion` | `arriba-abajo` | Sentido del texto del lomo: `arriba-abajo` o `abajo-arriba`. |
| `contratapa` | vacío | Texto de la contratapa. Los párrafos van separados por una línea en blanco. |
| `solapa_contratapa` | vacío | Texto de la solapa interior de la contratapa (solo con solapas). |
| `solapa_portada` | vacío | Texto de la solapa interior de la portada (solo con solapas). |

El de `ejemplos/platon-apologia.json` usa casi todos los campos; el de Sun Tzu, solo los básicos.

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

## Plantillas

- **`clasica`**: fondo crema, marco doble, título centrado en serif, un rombo como ornamento y el autor en mayúsculas espaciadas.
- **`sobria`**: fondo oscuro, sans, título a la izquierda, regla de color de acento y lomo en ese mismo color.

Para añadir una plantilla, crea `src/Plantillas/Nombre.php` que extienda `Plantilla`. Tiene que implementar `coloresPorDefecto()` y `portada()`, y puede redefinir `fuente()`, `factorAncho()` y `coloresLomo()`. Después regístrala en `Plantilla::disponibles()`. La contratapa, el lomo y las solapas se heredan.

## Antes de imprimir

- **Fuentes.** Los SVG piden fuentes instaladas (Georgia, Helvetica…), y Georgia o Helvetica no son libres para incrustar. Para imprimir, convierte el texto a trazos en Inkscape (Trayecto → Objeto a trazado) o usa fuentes libres (licencia OFL, como EB Garamond o Libre Baskerville) incrustadas.
- **Ajuste del texto.** El ajuste usa un ancho medio de letra aproximado, sin medir la fuente. Mira siempre la vista previa con la fuente real.
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
```

Si cambias el generador, regenera los ejemplos con `php bin/generar.php ejemplos/*.json --salida=ejemplos/salida`. El workflow `pruebas-portadas-svg.yml` corre las dos comprobaciones cuando cambia esta carpeta.

## Estructura

```
bin/generar.php          línea de comandos
src/                     Libro, Medidas, Texto, Svg, Generador y las plantillas
ejemplos/*.json          libros de ejemplo
ejemplos/salida/         SVG de los ejemplos, versionados y comprobados por CI
pruebas/ejecutar.php     pruebas unitarias, sin dependencias
```

## Limitaciones

- El ajuste de texto no mide fuentes reales; puede quedar algo más ancho o más estrecho que en la vista previa.
- No genera el código de barras ni el PDF para imprenta; se obtiene desde los SVG con una herramienta externa.
- Solo hay dos plantillas.
