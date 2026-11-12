# Imprenta, accesibilidad y derechos

## Accesibilidad

Cada SVG se anuncia como una imagen (`role="img"`) con su `<title>` y su `<desc>`, y declara el idioma del libro (`lang` y `xml:lang`, del campo `idioma`). La descripción dice qué pieza es y de qué libro, y la de la contratapa lleva su texto, porque un lector de pantalla no recorre el texto dibujado.

## Antes de imprimir

- **Fuentes.** Van incrustadas (ver [Fuentes](fuentes.md)). Si la imprenta pide el texto convertido a trazos, usa `--trazos`.
- **Ajuste del texto.** Mira siempre la vista previa. Si un texto no cabe ni al tamaño mínimo, se recorta con «…»: acórtalo en el JSON en vez de dejar que se recorte.
- **Degradados al imprimir.** Las impresoras los convierten a CMYK, y eso puede cambiar tonos o mostrar bandas en degradados suaves. Pide prueba de color al impresor.
- **Vista previa en imagen.** `rsvg-convert -w 2000 portada.svg -o portada.png`, Inkscape o Chromium. Los SVG están en milímetros.
- **Código de barras.** El rectángulo blanco de la contratapa es un hueco reservado para el EAN-13 del ISBN. Este sistema no genera el código: lo pone quien imprime.
- **Tapa dura.** No se modela el grosor de las tapas. Ajusta `lomo_mm` y las medidas con la imprenta.
- **Imágenes.** La portada no admite todavía una ilustración.

## Dominio público

Los textos antiguos (Platón, Sun Tzu) son de dominio público, pero **una traducción moderna puede no serlo**. En España y en la UE, la protección dura toda la vida del traductor más 70 años. Comprueba la licencia de la traducción antes de poner el nombre del traductor. Por eso los ejemplos dejan `traductor` vacío, y el generador avisa de cada libro que lleva traductor.
