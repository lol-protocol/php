# lol-protocol/php

Monorepo con dos módulos independientes, sin relación funcional entre sí:

## [`apps/content-review/`](./apps/content-review/README.md)

Motor de detección de contenido difamatorio/ofensivo en nombres, con soporte
fonético para 30 idiomas. Ver su [README](./apps/content-review/README.md)
para uso, arquitectura y cómo agregar un idioma nuevo.

## [`apps/routing/`](./apps/routing/docs/README_URLS.md)

Sistema de URLs y routing para un sitio de genealogía y un sitio POS
(Contrastocolor), donde el tipo de recurso se infiere de la forma del primer
segmento de la URL en vez de por palabras. Ver
[`docs/README_URLS.md`](./apps/routing/docs/README_URLS.md) para la guía de
uso y [`docs/URL_STRUCTURES.md`](./apps/routing/docs/URL_STRUCTURES.md) para
la tabla completa de formatos.

## Desarrollo

Un solo `composer.json`/`phpunit.xml` en la raíz cubre ambos módulos:

```bash
composer install
vendor/bin/phpunit
```

`phpunit.xml` define un testsuite por módulo (`Content Review`, `Routing`),
así que `vendor/bin/phpunit --testsuite Routing` corre solo los tests de
uno de los dos.
