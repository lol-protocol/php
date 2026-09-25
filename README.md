# lol-protocol/php

Este repositorio aloja varios proyectos independientes, cada uno en su propia carpeta. No comparten código ni dependencias entre sí.

## 📁 Proyectos

### [`marketing-y-mas/`](./marketing-y-mas/)
Librería PHP **DefamatoryContentReview** (paquete Composer `lol-protocol/defamatory-content-review`) para detectar contenido difamatorio en nombres, con soporte fonético para 17 idiomas y diccionarios en 33. Incluye también la guía de psicología y neuromarketing ético (`psychology-and-marketing/`).

Instalación y tests: ver [`marketing-y-mas/README.md`](./marketing-y-mas/README.md) y [`marketing-y-mas/CONTRIBUTING.md`](./marketing-y-mas/CONTRIBUTING.md).

### [`web-animations/`](./web-animations/)
Galería de animaciones web (HTML/CSS/JS) modularizadas, con su propia suite de tests de accesibilidad y performance.

### [`data/document_formats/`](./data/document_formats/)
Base de datos de formatos de papel y documentos por país (ISO 216, formatos regionales, tamaños de pantalla, localización a 30+ idiomas).

### [`_Garbage/`](./_Garbage/)
Artefactos descartados de una iteración temprana. No se usan en ningún proyecto activo — ver su propio README antes de tocar nada ahí.

---

## Antes de abrir un PR

Si tu cambio pertenece a uno de los proyectos de arriba, trabajá dentro de esa carpeta y no toques las demás. Si es un proyecto nuevo, dale su propia carpeta en la raíz en vez de mezclarlo con uno existente.
