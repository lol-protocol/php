# Línea de comandos

```bash
php bin/generar.php libro.json [más.json ...] [--salida=carpeta]
php bin/generar.php --lista=libros.csv [--omitir-errores] [--trazos] [--extra=carpeta] [--incremental] [--manifiesto] [--json] [--indice]
php bin/generar.php --comprobar
```

| Opción | Qué hace |
|---|---|
| `--lista=archivo.csv` | Lee una fila por libro (ver [Lista de libros](lista-de-libros.md)). Se puede repetir. |
| `--omitir-errores` | Salta las filas malas de una lista, las avisa y escribe el resto. Sin ella, un error no escribe nada. |
| `--trazos` | Escribe el texto como contornos, sin fuentes (ver [Fuentes](fuentes.md)). Un libro con letras de `fuentes/extra/` o de palabras compuestas sale a trazos aunque no la pidas. |
| `--extra=carpeta` | Contornos de otras letras, en lugar de `fuentes/extra/`. |
| `--incremental` | No vuelve a escribir los libros que no cambiaron (datos, imagen, modo, fuentes y código del generador). Guarda un `.huella` en cada carpeta de libro. |
| `--manifiesto` | Escribe `<salida>/manifiesto.json`: cada libro, su estado, archivos con su tamaño y avisos, y los totales. |
| `--json` | Imprime ese mismo manifiesto en vez de una línea por libro, para usar el generador desde otro programa. Los avisos siguen saliendo como texto por la salida de errores. |
| `--indice` | Escribe `<salida>/indice.svg`, una hoja de contactos con la portada de cada libro en miniatura. Es SVG puro y se refiere a cada `portada.svg` por ruta relativa. |
| `--comprobar` | Regenera `ejemplos/*.json` y falla si no coincide con `ejemplos/salida/` (y con `ejemplos/salida-trazos/`). |

Con más de un libro, o con `--incremental`, termina con un resumen: `N libros (X escritos, Y sin cambios), A avisos, Z MB, T s`.

## Códigos de salida

| Código | Significa |
|---|---|
| `0` | Bien. |
| `1` | Error al escribir (disco, permisos) u otro error del programa. |
| `2` | Uso incorrecto: opción desconocida o falta un argumento. |
| `3` | Datos no válidos: un libro o una lista que se puede corregir. |

## Interruptores (opcional)

`--interruptores=<carpeta>` lee el interruptor `pos.portadas` del repositorio (`interruptores/`, ver su README) y no escribe las piezas que tenga apagadas: `portada`, `contratapa`, `lomo` y `cubierta-completa` (que incluye el sobrelomo). Con el interruptor de fábrica, todo sale como sin la opción.

```bash
php bin/generar.php libro.json --salida=salida --interruptores=../interruptores
```

- Sin esta opción, el generador no lee nada de fuera: sigue siendo independiente del repositorio.
- No se combina con `--comprobar`, que compara la salida guardada y no depende del estado.
- Si el interruptor no se puede leer (no hay código, el catálogo está dañado), el generador sale con código 2 antes de escribir nada.
- Una pieza oculta se quita si ya estaba escrita, y la huella de la carpeta cambia: `--incremental` la vuelve a escribir.

## Avisos

Antes de escribir, el generador avisa (sin detener nada) de lo que casi seguro es un error: letras que ninguna fuente tiene, traductor (derechos de la traducción), ISBN con dígito de control que no cuadra, lomo de menos de 3 mm (no lleva texto), texto que no contrasta (menos de 4,5:1) con el fondo —también con cada color del degradado—, textos que se recortan con «…» y una imagen que la plantilla no usa.
