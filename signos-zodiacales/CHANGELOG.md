# Historial de cambios

Los cambios de `datos/` y de lo que se calcula con ellos, de lo más reciente a lo más viejo. La versión de los datos está en `datos/signos.json` (campo `version`) y tiene que coincidir con la primera entrada de este archivo: `python3 generar.py --comprobar` falla si no.

Versionado semántico: la **mayor** cambia si se rompe el esquema de `datos/signos.json` o lo que devuelven las consultas; la **menor** cuando se agregan datos, campos o funciones; la **parche** cuando se corrige un texto, una fecha o una figura sin cambiar la forma.

## [1.1.0] - 2026-10-26

### Agregado
- `version` y `revision` en `datos/signos.json`: el estado de revisión de la traducción de cada uno de los 30 idiomas (`sin_revisar` o `revisado`, con quién y cuándo). Hoy los 30 están sin revisar. El estado sale en cada ficha de `por-idioma/` y en su índice.
- `zodiaco.version()` y `zodiaco.revision(idioma)`.
- `php/`: el port a PHP de `zodiaco.py`, sin dependencias.
- `datos/vectores.json`: las respuestas de `zodiaco.py` a un conjunto fijo de entradas, que comparten Python y PHP. Lo genera `generar.py`.
- Este `CHANGELOG.md`.

## [1.0.0] - 2026-10-24

Primera versión completa.

- 12 signos occidentales y 12 animales chinos en 30 idiomas: nombre, descripción breve y larga (en género neutro), rasgos, fechas tropicales y siderales, ramas y troncos.
- Ilustraciones SVG en dos variantes (con fondo y sin fondo), el gato vietnamita y `galeria.html`.
- `zodiaco.py`: signo de una fecha, cúspide, animal de un año o de una fecha (con el Año Nuevo lunar de 1900 a 2099 y el huso de China), nombre del año en el ciclo de 60, compatibilidad y decanatos.
- Fichas por idioma, con la dirección de derecha a izquierda en árabe, hebreo, persa y urdu.
