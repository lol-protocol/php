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
| [`sistema-nuevo/`](sistema-nuevo/) | Backoffice para revisar la actividad de un usuario contra el promedio de su universo comparable (PHP + Java + JS). |
| [`cobros-ingresos-funnels/`](cobros-ingresos-funnels/) | Panel web de cobros, ingresos y funnel de conversión: boletas, pagos, clientes, cohortes y auditoría (PHP + PostgreSQL). |
| [`vps-setup/`](vps-setup/) | Scripts para configurar desde cero un VPS Ubuntu (Nginx, PHP, Python, PostgreSQL, SSL). |
| [`landing-page/`](landing-page/) | Landing page estática que despliegan los scripts de `vps-setup/`. |

Cada carpeta tiene su propio código, tests y README con instrucciones de instalación y uso
(salvo `landing-page/`, que es un solo archivo). La única dependencia entre proyectos es:
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

## CI/CD

`.github/workflows/tests.yml` tiene un job por proyecto:
- `phpunit`: tests, PHPStan y benchmark de `defamatory-content-review/`.
- `phone-directory`: tests de `phone-directory/` (incluidos los ejemplos) contra SQLite y PostgreSQL, y el benchmark.
- `phone-directory-ports`: tests de los ports de `phone-directory/` a Python y Java.
- `web-animations`: prueba de humo de las animaciones.

`sistema-nuevo/`, `vps-setup/` y `cobros-ingresos-funnels/` tienen sus propios workflows (`pruebas-backoffice.yml`, `vps-setup.yml` y `pruebas-cobros-ingresos-funnels.yml`).

## Licencia

MIT — ver [LICENSE](LICENSE).
