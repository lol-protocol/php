# lol-protocol/php

Monorepo con módulos independientes, sin relación funcional entre sí:

## [`apps/content-review/`](./apps/content-review/README.md)

Motor de detección de contenido difamatorio/ofensivo en nombres, con soporte
fonético para 33 idiomas y modelo de parentesco lingüístico. Ver su
[README](./apps/content-review/README.md) para uso, arquitectura y cómo
agregar un idioma nuevo.

## [`apps/routing/`](./apps/routing/docs/README_URLS.md)

Sistema de URLs y routing para un sitio de genealogía y un sitio POS
(Contrastocolor), donde el tipo de recurso se infiere de la forma del primer
segmento de la URL en vez de por palabras. Ver
[`docs/README_URLS.md`](./apps/routing/docs/README_URLS.md) para la guía de
uso y [`docs/URL_STRUCTURES.md`](./apps/routing/docs/URL_STRUCTURES.md) para
la tabla completa de formatos.

## [`web-animations/`](./web-animations/README.md)

Galería de demostración de 36 animaciones HTML/CSS/JS con arquitectura
modular (`common.css`/`common.js`), sin relación con los módulos PHP. Ver su
[README](./web-animations/README.md).

## [`data/document_formats/`](./data/document_formats/README.md)

Base de datos de formatos de documento, papel y pantalla por país (CSVs +
traducciones), consumible como datos estáticos. Ver su
[README](./data/document_formats/README.md).

## Desarrollo (PHP)

Un solo `composer.json`/`phpunit.xml` en la raíz cubre los dos módulos PHP
(`apps/content-review` y `apps/routing`; `web-animations` y
`data/document_formats` no tienen dependencias PHP):

```bash
composer install
vendor/bin/phpunit
```

`phpunit.xml` define un testsuite por módulo (`Content Review`, `Routing`),
así que `vendor/bin/phpunit --testsuite Routing` corre solo los tests de
uno de los dos.
