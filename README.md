# lol-protocol/php

Este repositorio aloja varios proyectos independientes, cada uno en su propia
carpeta con su propio README:

| Proyecto | Qué es |
|---|---|
| [`defamatory-content-review/`](defamatory-content-review/) | Librería PHP — detección de insultos y ridiculización en nombres para plataformas genealógicas. El proyecto principal del repositorio. |
| [`url-routing/`](url-routing/docs/README_URLS.md) | Routing de URLs para los sitios de genealogía y POS (Contrastocolor): el tipo de recurso se infiere de la forma del primer segmento (cantidad de dígitos, letras de lugar), no de palabras. |
| [`web-animations/`](web-animations/) | Galería de demostración de 36 animaciones HTML/CSS/JS. |
| [`document-formats/`](document-formats/) | Base de datos de formatos de documento y papel por país. |
| [`sistema-nuevo/`](sistema-nuevo/) | Backoffice para revisar la actividad de un usuario contra el promedio de su universo comparable (PHP + Java + JS). |
| [`vps-setup/`](vps-setup/) | Scripts para configurar desde cero un VPS Ubuntu (Nginx, PHP, Python, PostgreSQL, SSL). |
| [`landing-page/`](landing-page/) | Landing page estática que despliegan los scripts de `vps-setup/`. |

Cada carpeta es autocontenida: su propio código, tests y documentación no
dependen de las otras. Ver el README de cada una para instalación y uso.
La excepción son los documentos de infraestructura del VPS en la raíz, que
describen dónde y cómo se despliegan los proyectos.

## Licencia

MIT — ver [LICENSE](LICENSE).
