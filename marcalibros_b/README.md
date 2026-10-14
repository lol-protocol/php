# Marcalibros — Identidad de marca para Plataforma POS

Marcalibros son los marcadores de lectura con diseño propio (libros en SVG y paisajes en JPG) para Plataforma POS, la herramienta de etiquetado NLP sobre textos de Project Gutenberg, y para su lector de textos.

**Formato: solo vertical, 200×1400 px (proporción 1:7).** Cada pieza reserva una franja central de 168×420 px para el texto, libre de sol y de motivos, con contraste mínimo de 4,5:1 (si hace falta, lleva un velo translúcido horneado en la propia franja).

## Contenido

- `catalog/taxonomia.yaml`: movimientos, autores y obras. Es la fuente de verdad. Ver también `catalog/motivos.yaml` (biblioteca de motivos) y `catalog/animos.yaml` (ánimo → paletas).
- `catalog/manifest.json`: índice generado de todos los marcalibros (versionado).
- `catalog/muestras/`: unas pocas piezas de ejemplo (versionadas).
- `catalog/svg/`, `catalog/jpg/`, `catalog/revision/`: **catálogo completo, salida del generador. No está en git**: lo publica el flujo de CI como artefacto `marcalibros-catalogo` (`.github/workflows/marcalibros-b.yml`, en la raíz del repo).
- `generator/`: generador reproducible. Misma entrada, misma salida.
- `tests/`: pruebas y hashes de referencia.

## Generar en local

    pip install -r generator/requirements.txt -r generator/requirements-dev.txt
    python generator/build.py --movimiento romanticismo     # o --all
    python -m pytest -q tests

Requiere Chromium (variable `CHROMIUM` o `/opt/pw-browsers/chromium`): el QA rasteriza cada SVG con un navegador real. Python 3.13.

## Convenciones

- Cada marcalibro sale en un solo formato: la variante 01 de cada autor es JPG (paisaje con grano, calidad 85, sin metadatos); el resto, SVG.
- Los colores se definen una sola vez, en `generator/plantillas.py`, y se reutilizan en SVG y en raster.
- Estado, decisiones y pendientes: `PLAN.md`.
