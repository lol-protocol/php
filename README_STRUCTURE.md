# LOL Protocol - PHP Implementation

Este repositorio contiene múltiples proyectos PHP de la implementación del LOL Protocol, cada uno en su propia carpeta.

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
│   ├── bin/                      # Ejecutables CLI
│   ├── examples/                 # Ejemplos de uso
│   ├── composer.json             # Dependencias del proyecto
│   └── phpunit.xml               # Configuración de pruebas
│
├── web-animations/               # Animaciones web
├── data/                         # Datos compartidos
├── composer.json                 # Configuración del monorepo
└── .github/                      # CI/CD workflows
```

## Dependencias entre proyectos

`phone-directory` usa clases de `defamatory-content-review` (por ejemplo `AccentFolding` y
`PhoneticFolderRegistry`). Su `composer.json` carga el namespace `DefamatoryContentReview\`
directamente desde `../defamatory-content-review/src/`, así que ambas carpetas deben estar presentes.

## Desarrollo

```bash
cd phone-directory
composer install
./vendor/bin/phpunit
php bin/phonedir help
```

## CI/CD

`.github/workflows/tests.yml` tiene un job por proyecto: `phpunit` (tests, PHPStan y benchmark de
`defamatory-content-review/`) y `phone-directory` (tests de `phone-directory/`, incluidos los de PostgreSQL).
