# Marcalibros

Lee `PLAN.md` antes de empezar: contiene las decisiones tomadas, el estado del prototipo y las mejoras pendientes.

- Idioma de trabajo: español.
- Generar: `python generator/build.py --movimiento romanticismo` (o `--all`).
- La fuente de verdad es `catalog/taxonomia.yaml`. No edites a mano `catalog/svg/`, `catalog/jpg/` ni `catalog/manifest.json`: son salida del generador.
- Formato: solo vertical, 200×1400 (1:7). No generes versiones horizontales.
- El catálogo completo (`catalog/svg|jpg|jpg2x|revision`) no se versiona: está en `.gitignore` y lo publica la CI como artefacto. En git solo van taxonomía, generador, tests, manifest y `catalog/muestras/`.
- Tras cambiar plantillas a propósito: `python tests/test_generador.py --actualizar` y revisa las hojas de `catalog/revision/`.
- Pruebas rápidas (segundos, sin generar el catálogo): `python -m pytest -q -m "not lento"`. Las marcadas `lento` generan las 1000 piezas o usan Chromium; la CI corre todo.
- `editor/index.html` copia datos del generador (paletas, franja, límites): tras tocarlos, `python generator/editor_datos.py` (y `--comprobar` para verificarlo, como hace una prueba).
- Solo los módulos de `MODULOS_DE_RENDER` (build.py) invalidan la caché del catálogo: editor, modelo, marca, revisión y Gutenberg pueden cambiar sin rehacer las 1000 piezas.
