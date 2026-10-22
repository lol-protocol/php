# Marcalibros

Lee `PLAN.md` antes de empezar: contiene las decisiones tomadas, el estado del prototipo y las mejoras pendientes.

- Idioma de trabajo: español.
- Generar: `python generator/build.py --movimiento romanticismo` (o `--all`).
- La fuente de verdad es `catalog/taxonomia.yaml`. No edites a mano `catalog/svg/`, `catalog/jpg/` ni `catalog/manifest.json`: son salida del generador.
- Rama de trabajo: `feat/marcalibros-plataforma-pos-brand-identity-libros-svg-paisajes-jpg`.
- Formato: solo vertical, 200×1400 (1:7). No generes versiones horizontales.
- El catálogo completo (`catalog/svg|jpg|jpg2x|revision`) no se versiona: está en `.gitignore` y lo publica la CI como artefacto. En git solo van taxonomía, generador, tests, manifest y `catalog/muestras/`.
- Tras cambiar plantillas a propósito: `python tests/test_generador.py --actualizar` y revisa las hojas de `catalog/revision/`.
