# Marcalibros — Identidad de marca para Plataforma POS

Identidad visual de Marcalibros, los marcadores de lectura que usan el lector y la herramienta de etiquetado de Plataforma POS.

## Contenido

| Carpeta      | Qué contiene                                                          |
|--------------|-----------------------------------------------------------------------|
| `svg/`       | Iconos de 24×24 y estados vacíos de 64×64, en SVG                     |
| `images/`    | Paisajes en JPG: 1920×1080 para cabeceras y 1200×630 para vistas previas |
| `catalog/`   | Marcalibros predefinidos: SVG, vistas previas JPG, manifiesto y taxonomía |
| `generator/` | Script que genera el catálogo y los paisajes                          |

El archivo `brand-tokens.md` define la paleta, los contrastes y los trazos. Todo lo demás se apoya en él.

## Generar el catálogo y los paisajes

Requiere Python 3 con Pillow y numpy.

```bash
python3 generator/generar_marcalibros.py                        # 100 marcalibros
python3 generator/generar_marcalibros.py --por-movimiento 100   # 1000 marcalibros
```

El generador es determinista: la misma ejecución produce los mismos archivos. Cada marcalibro se define una sola vez y de esa definición salen su SVG y su vista previa JPG.

## Convenciones

- Iconos e ilustraciones: SVG con trazo de 2 px y extremos redondeados. Menos de 10 KB cada uno.
- Paisajes y vistas previas: JPG con calidad 85, sin metadatos EXIF.
- Nombres de archivo en minúsculas, separados por guiones y sin números al inicio.
- Los valores decimales usan 1, 2, 4 o 5 cifras.
- Los iconos funcionales sobre fondo claro usan `tinta` o `verde`. `ocre` y `niebla` sobre `papel` son solo decorativos.

## Pendiente

- Confirmar la taxonomía de movimientos y autores en `catalog/taxonomia.md`.
- Confirmar el número de marcalibros predefinidos (100 o 1000).
- Definir el flujo de marcalibros propios: subida de imágenes JPG, PNG o WebP, eliminación de EXIF y normalización al tamaño del marcalibro.
- Ajustar la tipografía final de los títulos.
