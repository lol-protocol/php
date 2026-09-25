# LOL Protocol - PHP Implementation

Este repositorio contiene múltiples proyectos de la implementación del LOL Protocol, cada uno en su propia carpeta.

## Estructura del Repositorio

```
.
├── defamatory-content-review/    # Librería de revisión de contenido difamatorio
│   ├── src/DefamatoryContentReview/
│   ├── config/                   # Diccionarios y familias de idiomas
│   ├── tests/
│   └── composer.json
│
├── phone-directory/              # Parser de directorios telefónicos históricos
│   ├── src/PhoneDirectory/       # Código fuente
│   ├── tests/PhoneDirectory/     # Pruebas unitarias
│   ├── bin/                      # CLI (phonedir) y benchmark
│   ├── examples/                 # Ejemplos de uso
│   ├── docs/                     # Resumen, estado, estimaciones y análisis de errores
│   ├── ports/                    # Versiones simplificadas en Python y Java, con sus tests
│   ├── composer.json             # Dependencias del proyecto
│   └── phpunit.xml               # Configuración de pruebas
│
├── sistema-nuevo/                # Backoffice de actividad de usuarios
├── vps-setup/, landing-page/     # Automatización del VPS y landing page
├── web-animations/               # Animaciones web
├── data/                         # Datos compartidos
├── composer.json                 # Instala los dos proyectos PHP juntos (opcional)
└── .github/                      # CI/CD workflows
```

## Dependencias entre proyectos

`phone-directory` usa clases de `defamatory-content-review` (por ejemplo `AccentFolding` y
`PhoneticFolderRegistry`). Su `composer.json` carga el namespace `DefamatoryContentReview\`
directamente desde `../defamatory-content-review/src/`, así que ambas carpetas deben estar presentes.

El `composer.json` de la raíz declara los dos proyectos como repositorios `path`, así que
`composer install` en la raíz los enlaza en `vendor/` para usarlos juntos desde otro código.
Para desarrollar o correr los tests de cada proyecto se usa su propia carpeta.

## Desarrollo

```bash
cd phone-directory
composer install
./vendor/bin/phpunit
php bin/phonedir help
```

## CI/CD

`.github/workflows/tests.yml` tiene un job por proyecto:
- `phpunit`: tests, PHPStan y benchmark de `defamatory-content-review/`.
- `phone-directory`: tests de `phone-directory/` contra SQLite y PostgreSQL, los ejemplos y el benchmark.
- `phone-directory-ports`: tests de los ports a Python y Java.
- `web-animations`: prueba de humo de las animaciones.

`sistema-nuevo/` tiene su propio workflow (`pruebas-backoffice.yml`).
