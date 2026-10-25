# Cómo contribuir

Este archivo es para una persona que abre un pull request a mano, desde la web de GitHub o desde su propio clon. [`CLAUDE.md`](CLAUDE.md) tiene las mismas reglas con más detalle, pensadas para las sesiones de Claude Code que trabajan en el repositorio; si algo de aquí no queda claro, esa es la fuente completa.

## Antes de cambiar nada

- Mira los [pull requests abiertos](https://github.com/lol-protocol/php/pulls) y qué carpetas tocan. No toques una carpeta que ya tenga un PR abierto: aporta ahí, o espera a que se resuelva.
- Si tu cambio ya está cubierto por otro PR abierto, no abras uno en paralelo.
- Parte siempre de `master` al día.

## Un PR por carpeta de proyecto

Este repositorio reúne proyectos independientes (la lista está en [`README.md`](README.md)). Un PR toca **una sola carpeta de proyecto**; si tu cambio cruza varias, sepáralo en varios PR.

Los archivos compartidos de la raíz (`README.md`, `.gitignore` y el `composer.json` de la raíz) son los que más chocan entre PR distintos: cámbialos en un PR chico y aparte, para que se pueda revisar y fusionar rápido. La excepción: una carpeta de proyecto nueva trae su fila en la tabla de `README.md`, y un workflow nuevo su viñeta en la sección «CI/CD», en el mismo PR que los agrega (el check `estructura.yml` falla si faltan).

## Pruebas

Cada proyecto tiene su propio `README.md` con cómo instalarlo y probarlo, y su propio workflow en `.github/workflows/`, que corre solo cuando cambia esa carpeta. La tabla «Probar antes de empujar» de `CLAUDE.md` resume qué correr en cada uno. Corre las pruebas del proyecto que tocaste antes de abrir el PR.

## El pull request

- Ábrelo en borrador mientras esperas la CI o una revisión, y márcalo listo cuando esté.
- Completa la plantilla (`.github/pull_request_template.md`): qué cambia y por qué, las comprobaciones de antes de abrirlo, qué pruebas corriste y lo que quien revisa debe saber.
- La fusión la decide la persona que mantiene el repositorio: no fusiones tu propio PR.
