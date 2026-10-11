# Marcalibros — Identidad de marca para Plataforma POS

Marcalibros es el conjunto de assets de marca (libros en SVG y paisajes en JPG) para Plataforma POS, la herramienta de etiquetado NLP sobre textos de Project Gutenberg, y para su lector de textos.

## Contenido

- `catalog/taxonomia.yaml`: movimientos, autores y motivos. Es la fuente de verdad.
- `catalog/svg/`: marcalibros en SVG (900 en el set completo).
- `catalog/jpg/`: marcalibros en JPG, paisajes con grano (100 en el set completo).
- `catalog/manifest.json`: índice generado de todos los marcalibros.
- `catalog/revision/`: hojas de contacto para revisión humana.
- `generator/`: generador reproducible. Misma entrada, misma salida.

## Generar

    pip install -r generator/requirements.txt
    python generator/build.py --movimiento romanticismo
    python generator/build.py --all

## Convenciones

- Cada marcalibro sale en un solo formato: SVG (900) o JPG (100).
- Los JPG son paisajes con grano, calidad 85 y sin metadatos. Los SVG son vectoriales y escalan sin pérdida.
- Los colores de la marca se definen una sola vez, en `generator/plantillas.py`, y se reutilizan en SVG y en raster.

## Estado

Prototipo: Romanticismo / Gustavo Adolfo Bécquer (10 marcalibros, 1 JPG y 9 SVG).

Pendiente:
- Los otros 9 movimientos.
- Validar visualmente los SVG en un rasterizador real. En el prototipo, las hojas de contacto usan el renderer raster como respaldo.
- Hacer más variados los movimientos oscuros (las variantes en paleta "noche" se parecen entre sí).
- Lógica de marcalibros de usuarios.
