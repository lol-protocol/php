# Historial de cambios

Los cambios de `datos/` y de lo que se calcula con ellos, de lo más reciente a lo más viejo. La versión de los datos está en `datos/signos.json` (campo `version`) y tiene que coincidir con la primera entrada de este archivo: `python3 generar.py --comprobar` falla si no.

Versionado semántico: la **mayor** cambia si se rompe el esquema de `datos/signos.json` o lo que devuelven las consultas; la **menor** cuando se agregan datos, campos o funciones; la **parche** cuando se corrige un texto, una fecha o una figura sin cambiar la forma.

## [1.3.0] - 2026-11-03

### Agregado
- `datos/arboles.json`: tres sistemas que asocian árboles con fechas de nacimiento, en los 30 idiomas y con sus rasgos:
  - **El calendario celta de los árboles**: 13 meses de 28 días, con su árbol y su letra ogham, y el 23 de diciembre aparte. Lo inventó el poeta Robert Graves en 1948; no es un calendario celta antiguo.
  - **El horóscopo de los árboles** («druídico» o «galo»): 21 árboles con sus tramos de fechas, cuatro de un solo día (los equinoccios y los solsticios). Popular y de creación moderna; las fechas varían según la fuente.
  - **Los árboles de los 27 nakshatras** de la astrología védica. Depende de la posición de la Luna al nacer, no de la fecha; en 11 de ellos las fuentes no coinciden en el árbol y las fichas lo marcan.
- `zodiaco.py` y `php/`: `arbol_celta`, `arbol_de_fecha`, `nakshatra_de_instante`, `longitud_de_la_luna` y `rasgos_de`. La Luna se calcula con la serie abreviada de Meeus (error de 0,05° como máximo entre 1900 y 2100, comprobado contra una efeméride) y el ayanamsa de Lahiri.
- Cada ficha de `por-idioma/` termina con las tres tablas.
- `datos/vectores.json` cubre las funciones nuevas.

## [1.2.0] - 2026-11-03

### Agregado
- `datos/espiritus.json`: los **72 ángeles** del Shem ha-Mephorash (de Éxodo 14:19-21, vía la Cábala) y los **72 demonios** de la Goetia (siglo XVII; un grimorio, no el canon bíblico), con sus cualidades en los 30 idiomas. Las cualidades son claves de un glosario de 158 conceptos traducidos, así que cada una se dice igual en todo el proyecto. Los demonios llevan rango(s) y legiones.
- Cada ficha de `por-idioma/` termina con las dos tablas: número, nombre, coro o rango, fechas y cualidades.
- `zodiaco.py` y `php/`: `angel_de_fecha`, `demonio_de_fecha`, `tramos_de_espiritus` y `cualidades_de`. Cada signo occidental se parte en seis tramos de unos 5 días; el ángel y el demonio del mismo número comparten tramo (zodiaco tropical).
- `datos/vectores.json` cubre las funciones nuevas.

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
