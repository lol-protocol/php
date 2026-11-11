# Plantillas

- **`clasica`**: fondo crema, marco doble, título centrado en serif, el adorno de la tradición (o un rombo) y el autor en mayúsculas espaciadas.
- **`sobria`**: fondo oscuro, sans, título a la izquierda, friso o barra de color de acento y lomo en ese mismo color.
- **`ilustrada`**: la imagen del libro (`imagen`) ocupa la parte de arriba de la portada, a sangre, con un filete de acento y, debajo, título y autor centrados en serif. Sin `imagen`, usa el emblema de la tradición (si hay) sobre un fondo tenue.
- **`cartel`**: sans en negrita, con el título enorme sobre una banda del color de acento que llega al borde del papel, y el autor en mayúsculas espaciadas. Solo letra: no dibuja adornos ni motivos.
- **`tipografica`**: fondo claro sin marco, el autor en mayúsculas espaciadas sobre un título grande en serif, a la izquierda, y el emblema de la tradición en grande, de línea fina, ocupando la parte de abajo.

Para añadir una plantilla, crea `src/Plantillas/Nombre.php` que extienda `Plantilla`. Tiene que implementar `coloresPorDefecto()`, `paleta()` (sus colores teñidos de un tono) y `portada()`, y puede redefinir `familia()` (`serif` por defecto, o `sans`) y `coloresLomo()`. Para medir un texto antes de colocarlo, usa `$this->medidaDe()`, con los atributos de estilo del texto si no es el normal (`['font-style' => 'italic']`, `['font-weight' => 'bold']`). Después regístrala en `Plantilla::disponibles()`. La contratapa, el lomo y las solapas se heredan.

## Temas claro y oscuro

`tema` (`claro` u `oscuro`) cambia la paleta de cualquier plantilla sin tocar sus colores uno a uno: con `categoria`, el tema tiñe su tono; sin ella usa el crema de la clásica. Los `colores` propios mandan sobre el tema, y el generador avisa si el texto no contrasta lo bastante.

## Formatos con nombre

`formato` también acepta un nombre en lugar de las medidas: `novela` (135 × 210 mm), `bolsillo` (110 × 178), `a5` (148 × 210), `a6` (105 × 148) o `ebook` (100 × 160). En una lista CSV, la columna `formato` es incompatible con `ancho_mm` y `alto_mm`.

## Serie y volumen

`volumen` (`3`, `III`) se escribe en el lomo después del autor («Título · Autor · Vol. 3») y en el pie, tras la colección. La palabra sale en el idioma del libro (`Vol.`, o `T.` en francés; ver `datos/frases.json`).

## Imagen y logo

`imagen` es la ilustración de la portada y `logo` el de la editorial (arriba a la derecha de la contratapa, hasta 26 × 12 mm). Pueden ser SVG, PNG, JPG, GIF o WebP de hasta 2 MB, con la ruta relativa al JSON o al CSV. Se incrustan en el SVG como `data:`, así que la cubierta no depende de ningún archivo. Un SVG con scripts, manejadores `on…` o enlaces a otros archivos se rechaza. Solo la plantilla `ilustrada` dibuja `imagen`; con otra, el generador avisa de que no se usa.
