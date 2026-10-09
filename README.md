# lol-protocol/php

Este repositorio aloja varios proyectos independientes, cada uno en su propia carpeta:

| Proyecto | Qué es |
|---|---|
| [`defamatory-content-review/`](defamatory-content-review/) | Librería PHP — detección de insultos y ridiculización en nombres para plataformas genealógicas. El proyecto principal del repositorio. |
| [`url-routing/`](url-routing/docs/README_URLS.md) | Routing de URLs para los sitios de genealogía y POS (Contrastocolor): el tipo de recurso se infiere de la forma del primer segmento (cantidad de dígitos, letras de lugar), no de palabras. |
| [`web-animations/`](web-animations/) | Galería de demostración de 36 animaciones HTML/CSS/JS. |
| [`document-formats/`](document-formats/) | Base de datos de formatos de documento y papel por país. |
| [`marketing/`](marketing/) | Frases y textos de marketing para un proyecto de genealogía: lemas, eslóganes, redes sociales y correo. |
| [`phone-directory/`](phone-directory/) | Parser de directorios telefónicos históricos (6 idiomas) para registros genealógicos. |
| [`psychology-and-marketing/`](psychology-and-marketing/) | Guía de psicología humana y neuromarketing ético para diseñar productos sin violar la privacidad del usuario. Solo documentos, sin código ni CI. |
| [`sistema-nuevo/`](sistema-nuevo/) | Backoffice para revisar la actividad de un usuario contra el promedio de su universo comparable (PHP + Java + JS). |
| [`cobros-ingresos-funnels/`](cobros-ingresos-funnels/) | Panel web de cobros, ingresos y funnel de conversión: boletas, pagos, clientes, cohortes y auditoría (PHP + PostgreSQL). |
| [`vps-setup/`](vps-setup/) | Scripts para configurar desde cero un VPS Ubuntu (Nginx, PHP, Python, PostgreSQL, SSL). |
| [`landing-page/`](landing-page/) | Landing page estática que despliegan los scripts de `vps-setup/`. |
| [`iconos-genealogia/` y `iconos-biblioteca/`](ICONOS_README.md) | Dos sets de iconos SVG abstractos en 2.5D, sin texto: árbol genealógico (172 iconos) y biblioteca virtual (161), cada uno en 3 variantes. Galería en `galeria-iconos.html`, generador y validador en `iconos-tools/`. |

Cada carpeta tiene su propio README con instrucciones de instalación y uso, salvo los sets de
iconos (`iconos-genealogia/`, `iconos-biblioteca/`, `iconos-tools/`), documentados juntos en
[`ICONOS_README.md`](ICONOS_README.md) en la raíz. La mayoría tiene también su propio código y
tests; `landing-page/` es un solo archivo; `psychology-and-marketing/` es solo texto; `marketing/`
es texto e imágenes con dos scripts PHP sueltos (`render.php` e `iconos/vista-previa.php`), sin
tests ni CI; y los sets de iconos son SVG más un generador/validador en
Python (`iconos-tools/`) que no corre en CI. La única dependencia entre proyectos es:
**`phone-directory/` requiere `defamatory-content-review/`** (usa sus clases de plegado de
acentos y claves fonéticas) — su `composer.json` la declara como dependencia Composer
(`lol-protocol/defamatory-content-review`, repositorio `path` a `../defamatory-content-review`),
así que ambas carpetas deben estar presentes. El resto de proyectos no depende de ningún otro.

## Desarrollo

Cada proyecto se instala y se prueba desde su propia carpeta:

```bash
cd phone-directory        # o defamatory-content-review, sistema-nuevo, vps-setup...
composer install          # o npm install, según el proyecto
./vendor/bin/phpunit
```

El `composer.json` de la raíz es opcional: declara `defamatory-content-review/` y
`phone-directory/` como repositorios `path` para instalar los dos juntos desde código externo
al monorepo. No sustituye al `composer install` de cada carpeta (Composer no instala las
`require-dev` — y por tanto los tests — de una dependencia `path`).

## Carpetas `_Garbage/`

Cuatro proyectos (`defamatory-content-review/`, `cobros-ingresos-funnels/`, `web-animations/` y
`vps-setup/`) tienen una carpeta `_Garbage/` con material obsoleto: código que se sacó de uso,
versiones viejas y restos de iteraciones tempranas. **Se conserva a propósito**: la decisión del
repositorio es guardar lo obsoleto como referencia en vez de borrarlo (la de
`defamatory-content-review/` se restauró en #23 después de que #14 la borrara como "peso muerto").
A cambio, nada la usa: ni el código, ni los tests, ni la CI, ni el análisis estático la cargan, y
el README de cada una explica qué hay y por qué está ahí. No hace falta tocarlas al limpiar un
proyecto.

## CI/CD

`.github/workflows/tests.yml` (nombre interno del workflow: "Pruebas") tiene un job por proyecto:
- `phpunit`: tests, PHPStan y benchmark de `defamatory-content-review/`.
- `chat-front-guard`: tests en Node del tope de letras repetidas para el front, `defamatory-content-review/js/`.
- `phone-directory`: tests de `phone-directory/` (incluidos los ejemplos) contra SQLite y PostgreSQL, y el benchmark.
- `phone-directory-ports`: tests de los ports de `phone-directory/` a Python y Java.
- `url-routing`: tests de `url-routing/` contra SQLite y PostgreSQL, y que sus migraciones corran limpias en PostgreSQL.
- `document-formats`: tests en Node de `document-formats/`.
- `document-formats-database`: importa `document-formats/` a MySQL (dos veces, para comprobar que no duplica filas) y lo valida contra los CSV.
- `web-animations`: prueba de humo de las animaciones.

`sistema-nuevo/`, `vps-setup/` y `cobros-ingresos-funnels/` tienen sus propios workflows (`pruebas-backoffice.yml`, `vps-setup.yml` y `pruebas-cobros-ingresos-funnels.yml`).

## Licencia

MIT — ver [LICENSE](LICENSE).
