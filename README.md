# lol-protocol/php

Este repositorio aloja varios proyectos independientes, cada uno en su propia
carpeta con su propio README:

| Proyecto | Qué es |
|---|---|
| [`defamatory-content-review/`](defamatory-content-review/) | Librería PHP — detección de insultos y ridiculización en nombres para plataformas genealógicas. El proyecto principal del repositorio. |
| [`url-routing/`](url-routing/docs/README_URLS.md) | Routing de URLs para los sitios de genealogía y POS (Contrastocolor): el tipo de recurso se infiere de la forma del primer segmento (cantidad de dígitos, letras de lugar), no de palabras. |
| [`web-animations/`](web-animations/) | Galería de demostración de 36 animaciones HTML/CSS/JS. |
| [`data/document_formats/`](data/document_formats/) | Base de datos de formatos de documento y papel por país. |
| [`sistema-nuevo/`](sistema-nuevo/README.md) | Backoffice de actividad de usuarios (PHP + Java + JS, PostgreSQL): flujo cronológico de acciones por usuario comparado contra el promedio de un universo filtrable. |
| [`vps-setup/`](vps-setup/README.md) | Scripts para aprovisionar el VPS (Ubuntu 24: PHP, Java, Python, PostgreSQL, Nginx + HTTPS). La documentación de infraestructura vive en los `.md` de la raíz (`ARCHITECTURE.md`, `DEPLOYMENT.md`, `DOMAINS.md`, …). |
| [`landing-page/`](landing-page/index.html) | Página de presentación estática (HTML). |

Cada carpeta es autocontenida: su propio código, tests y documentación no
dependen de las otras. Ver el README de cada una para instalación y uso.
La excepción son los documentos de infraestructura del VPS en la raíz, que
describen dónde y cómo se despliegan los proyectos.

## Licencia

MIT — ver [LICENSE](LICENSE).
