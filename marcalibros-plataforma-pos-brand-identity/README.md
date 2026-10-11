# Marcalibros — Identidad de marca para Plataforma POS

Identidad visual de Marcalibros, los marcadores de lectura que usan el lector y la herramienta de etiquetado de Plataforma POS.

## Contenido

| Carpeta      | Qué contiene                                                          |
|--------------|-----------------------------------------------------------------------|
| `svg/`       | Iconos de 24×24 y estados vacíos de 64×64, en SVG                     |
| `images/`    | Paisajes en JPG: 1920×1080 para cabeceras y 1200×630 para vistas previas |
| `catalog/`   | 1000 marcalibros predefinidos: SVG, vistas previas JPG de 700×100, manifiesto y taxonomía |
| `generator/` | Scripts que generan el catálogo y los paisajes. `motivos.py` y `motivos_texto.py` definen los 80 motivos visuales (4 por categoría) |
| `php/`       | Módulo PHP para que cada usuario cree su marcalibro con una imagen de la biblioteca o subiendo la suya. Ver `php/README.md` |
| `app/`       | Aplicación web independiente, sin cuentas: catálogo para explorar y descargar, y creación de marcalibros propios con recorte 7:1. Ver `app/README.md` |

El archivo `brand-tokens.md` define la paleta, los contrastes y los trazos. Todo lo demás se apoya en él.

## El catálogo

Son **20 categorías con 50 marcalibros cada una: 1000 en total.** Diez son movimientos literarios (Romanticismo, Realismo, Naturalismo, Modernismo, Vanguardias, Gótico, Barroco, Clasicismo, Simbolismo y Realismo mágico) y diez son tipos de texto (manuales, libros sagrados, leyes, cartas, diarios, tratados científicos, recetas, filosofía, teatro y viajes). La lista completa, los motivos y las notas para revisarla están en `catalog/taxonomia.md`.

## Generar el catálogo y los paisajes

Requiere Python 3 con Pillow y numpy.

```bash
python3 generator/generar_marcalibros.py                        # 1000 marcalibros (50 por categoría)
python3 generator/generar_marcalibros.py --por-categoria 10     # 200 marcalibros, para pruebas rápidas
```

El generador es determinista: la misma ejecución produce los mismos archivos. Cada marcalibro se define una sola vez y de esa definición salen su SVG y su vista previa JPG.

- El SVG es la versión de referencia. Las vistas previas JPG (700×100, la mitad del tamaño real, unos 11 KB cada una) son una rasterización aproximada: en líneas finas pueden diferir del SVG en un píxel de borde.
- El generador comprueba que cada SVG pese menos de 10 KB y que no haya dos marcalibros idénticos, y termina con error si falla alguna de las dos cosas.

## Marcalibros propios (PHP)

El módulo de `php/` recibe una imagen subida o un paisaje de `images/`, la valida, le quita los metadatos, la recorta a proporción 7:1 y guarda dos JPEG: 1400×200 y 2800×400. También lee el catálogo (`Catalog`: categorías, motivos, búsqueda por categoría). No depende de ningún framework.

```bash
cd php
composer install
./vendor/bin/phpunit
```

## Aplicación web (sin cuentas)

La carpeta `app/` usa ese módulo para ofrecer una aplicación completa y autónoma, sin inicio de sesión: se explora el catálogo, se descarga cada marcalibro en SVG, y se crea uno propio subiendo una imagen o eligiendo un paisaje. **No está conectada a Plataforma POS** (se dejó para después, a propósito).

```bash
cd app
php -S localhost:8080 -t public public/index.php
```

Detalles de configuración, seguridad y despliegue en `app/README.md`.

## Convenciones

- Iconos e ilustraciones: SVG con trazo de 2 px y extremos redondeados. Menos de 10 KB cada uno.
- Paisajes y vistas previas: JPG con calidad 85, sin metadatos EXIF.
- Nombres de archivo en minúsculas, separados por guiones y sin números al inicio.
- Los valores decimales usan 1, 2, 4 o 5 cifras.
- Los iconos funcionales sobre fondo claro usan `tinta` o `verde`. `ocre` y `niebla` sobre `papel` son solo decorativos.

## Pendiente

- Confirmar la taxonomía en `catalog/taxonomia.md`: los 10 movimientos y autores, y sobre todo los 10 tipos de texto, que son una propuesta nueva.
- Integrar la aplicación (o solo el módulo PHP) en Plataforma POS cuando se decida: falta saber dónde vive Plataforma POS, y cómo se identificará a las personas (hoy el enlace es la única llave).
- Ejecutar las pruebas de `php/` y `app/` con PHPUnit real y el análisis con PHPStan (nivel 6), y generar los `composer.lock`. Packagist no era accesible desde el entorno donde se escribió el código, así que las pruebas (95 del módulo y 121 de la aplicación) solo se han ejecutado con un ejecutor propio mínimo, y PHPStan no se ha corrido.
- Agregar un workflow de CI para `php/` y `app/`, con filtro de rutas como los de los otros proyectos del repositorio.
- Ajustar la tipografía final: Source Serif 4 e Inter están declaradas pero no incluidas en la aplicación.
