# LOL Protocol - PHP Implementation

Este repositorio contiene múltiples proyectos PHP de la implementación del LOL Protocol.

## Estructura del Repositorio

```
.
├── phone-directory/              # Proyecto PhoneDirectory (ubicación actual del desarrollo)
│   ├── src/                      # Código fuente
│   ├── tests/                    # Pruebas unitarias
│   ├── bin/                      # Ejecutables CLI
│   ├── config/                   # Configuración
│   ├── examples/                 # Ejemplos de uso
│   ├── composer.json             # Dependencias del proyecto
│   ├── phpunit.xml               # Configuración de pruebas
│   └── vendor/                   # Dependencias instaladas
│
├── composer.json                 # Configuración del monorepo
├── .github/                      # CI/CD workflows
├── CHANGELOG.md                  # Histórico de cambios
├── CONTRIBUTING.md               # Guía de contribución
├── README.md                     # Documentación principal
└── [Otros archivos de documentación]
```

## Desarrollo

Para trabajar con el proyecto PhoneDirectory:

```bash
cd phone-directory
composer install
./vendor/bin/phpunit
php bin/phonedir help
```

## CI/CD

Los workflows están configurados en `.github/workflows/tests.yml` y ejecutan las pruebas automáticamente en la carpeta `phone-directory/`.

## Proyectos Incluidos

### PhoneDirectory
- **Ubicación**: `phone-directory/`
- **Descripción**: Parser de directorios telefónicos históricos con soporte para múltiples idiomas
- **Tests**: 383 pruebas unitarias
- **PHP**: 8.1+
