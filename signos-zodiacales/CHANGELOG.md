# Historial de cambios

Los cambios de `datos/` y de lo que se calcula con ellos, de lo más reciente a lo más viejo. La versión de los datos está en `datos/signos.json` (campo `version`) y tiene que coincidir con la primera entrada de este archivo: `python3 generar.py --comprobar` falla si no.

Versionado semántico: la **mayor** cambia si se rompe el esquema de `datos/signos.json` o lo que devuelven las consultas; la **menor** cuando se agregan datos, campos o funciones; la **parche** cuando se corrige un texto, una fecha o una figura sin cambiar la forma.

## [1.3.1] - 2026-11-06

### Corregido
- **Los decanatos y los tramos de los 72 ya no dependen del año.** Antes se calculaban con fechas reales: en un año bisiesto un día podía cambiar de decanato o de ángel (el 10/03/2024 daba el decanato 3 y el 10/03/2025, el 2) y las fichas, calculadas para 2025, no coincidían con las funciones. Ahora se reparten en un año de 365 días y el 29/02 cuenta con el 28/02. El parámetro `anio` de `tramos_de_decanatos()` y `tramos_de_espiritus()` queda `@deprecated` y no cambia nada.
- **Las consultas devuelven copias de los datos.** Antes devolvían los diccionarios compartidos: modificar un resultado corrompía todas las respuestas siguientes. (En PHP no pasaba: los arrays se copian.)
- **Una clave sin glosario da un error claro.** En Python salía `RuntimeError: generator raised StopIteration`; en PHP se descartaba en silencio. Ahora es un `KeyError` / `InvalidArgumentException` que dice cuál.
- La validación ya no se salta la comprobación de espacios en una ruta que contenga la palabra «separador».

### Cambiado
- `generar.py`: un solo `_errores_de_traducciones()` (con `permite_espacios` explícito) en lugar de bloques repetidos en cuatro validadores. `zodiaco.py`: `_exigir_fecha()` y `_cargar_json()` en lugar de código repetido.
- `fechas_anio_nuevo()` devuelve una copia del diccionario.
- Las pruebas de Python tardan algo más (2,6 s → 3,8 s) por las copias.

## [1.3.0] - 2026-11-03

### Agregado
- `datos/arboles.json`: tres sistemas que asocian árboles con fechas de nacimiento, en los 30 idiomas y con sus rasgos:
  - **El calendario celta de los árboles**: 13 meses de 28 días, con su árbol y su letra ogham, y el 23 de diciembre aparte. Lo inventó el poeta Robert Graves en 1948; no es un calendario celta antiguo.
  - **El horóscopo de los árboles** («druídico» o «galo»): 21 árboles con sus tramos de fechas, cuatro de un solo día (los equinoccios y los solsticios). Popular y de creación moderna; las fechas varían según la fuente.
  - **Los árboles de los 27 nakshatras** de la astrología védica. Depende de la posición de la Luna al nacer, no de la fecha; en 11 de ellos las fuentes no coinciden en el árbol y las fichas lo marcan.
- `zodiaco.py` y `php/`: `arbol_celta`, `arbol_de_fecha`, `nakshatra_de_instante`, `longitud_de_la_luna` y `rasgos_de`. La Luna se calcula con la serie abreviada de Meeus (error de 0,05° como máximo entre 1900 y 2100, comprobado contra una efeméride) y el ayanamsa de Lahiri.
- `datos/numerologia.json`: el número del camino de vida (1 a 9 y los maestros 11, 22 y 33) con sus rasgos en los 30 idiomas, y `camino_de_vida`, `anio_personal`, `reducir_numero` y `rasgos_de_numero` en `zodiaco.py` y `php/`. Se calcula sumando todos los dígitos de la fecha. Los rasgos reutilizan los glosarios existentes.
- Cada ficha de `por-idioma/` termina con las tres tablas de árboles y la de numerología.
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
