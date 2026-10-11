# Marcalibros — Identidad de marca para Plataforma POS

Identidad visual de Marcalibros, los marcadores de lectura que usan el lector y la herramienta de etiquetado de Plataforma POS.

## Contenido

| Carpeta      | Qué contiene                                                          |
|--------------|-----------------------------------------------------------------------|
| `svg/`       | Iconos de 24×24 y estados vacíos de 64×64, en SVG                     |
| `images/`    | Paisajes en JPG: 1920×1080 para cabeceras y 1200×630 para vistas previas |
| `catalog/`   | Marcalibros predefinidos: SVG, vistas previas JPG, manifiesto y taxonomía |
| `generator/` | Scripts que generan el catálogo y los paisajes. `motivos.py` define los 40 motivos visuales (4 por movimiento) |
| `php/`       | Módulo PHP para que cada usuario cree su marcalibro con una imagen de la biblioteca o subiendo la suya. Ver `php/README.md` |

El archivo `brand-tokens.md` define la paleta, los contrastes y los trazos. Todo lo demás se apoya en él.

## Generar el catálogo y los paisajes

Requiere Python 3 con Pillow y numpy.

```bash
python3 generator/generar_marcalibros.py                        # 100 marcalibros
python3 generator/generar_marcalibros.py --por-movimiento 100   # 1000 marcalibros
```

El generador es determinista: la misma ejecución produce los mismos archivos. Cada marcalibro se define una sola vez y de esa definición salen su SVG y su vista previa JPG.

- El SVG es la versión de referencia. Las vistas previas JPG son una rasterización aproximada: en líneas finas pueden diferir del SVG en un píxel de borde.
- El generador comprueba que cada SVG pese menos de 10 KB y termina con error si alguno lo supera.

## Marcalibros propios (PHP)

El módulo de `php/` recibe una imagen subida o un paisaje de `images/`, la valida, le quita los metadatos, la recorta a proporción 7:1 y guarda dos JPEG: 1400×200 y 2800×400. No depende de ningún framework.

```bash
cd php
composer install
./vendor/bin/phpunit
```

## Convenciones

- Iconos e ilustraciones: SVG con trazo de 2 px y extremos redondeados. Menos de 10 KB cada uno.
- Paisajes y vistas previas: JPG con calidad 85, sin metadatos EXIF.
- Nombres de archivo en minúsculas, separados por guiones y sin números al inicio.
- Los valores decimales usan 1, 2, 4 o 5 cifras.
- Los iconos funcionales sobre fondo claro usan `tinta` o `verde`. `ocre` y `niebla` sobre `papel` son solo decorativos.

## Pendiente

- Confirmar la taxonomía de movimientos y autores en `catalog/taxonomia.md`.
- Confirmar el número de marcalibros predefinidos (100 o 1000). Con 40 motivos ya es viable llegar a 1000.
- Integrar el módulo PHP en Plataforma POS: pantalla de recorte, subida y selección del catálogo. Falta saber dónde vive la aplicación.
- Ejecutar las pruebas con PHPUnit y el análisis con PHPStan (nivel 6) y generar `composer.lock`. Packagist no era accesible desde el entorno donde se escribió el módulo, así que las 91 pruebas solo se han ejecutado con un ejecutor propio mínimo, no con PHPUnit.
- Agregar un workflow de CI para `php/`, con filtro de rutas como los de los otros proyectos del repositorio.
- Ajustar la tipografía final de los títulos.
