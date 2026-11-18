# personas-especiales

Registro de **exclusiones**: personas que no deben ser conectadas en un árbol ni
mostradas en una plataforma de genealogía, porque así lo pide la propia persona
o quien tiene derecho a pedirlo.

## Qué es y qué no es

- **Es** una lista de solicitudes de exclusión, cada una con quién la pide, con
  qué prueba, en qué estado y cuándo cambió.
- **No es** una lista de personas por religión, origen étnico, pertenencia a una
  organización ni por el cargo o la fama de su familia. La plataforma no
  clasifica a nadie: quien tiene derecho a pedir la exclusión es quien la pide.
- **No es pública.** Ninguna respuesta de la plataforma debe revelar que existe
  un registro, ni quién está en él.

La decisión de no guardar categorías está en
[`PRIVACIDAD-Y-HONRA.md`](../PRIVACIDAD-Y-HONRA.md) y en el plan que se acordó
para este módulo.

## Estado: Fase 1 (modelo de datos)

Esta fase cubre el modelo y su esquema. **Todavía no restringe nada**: las reglas
de visibilidad (no conectar en árboles, no mostrar en búsquedas, no exportar a
GEDCOM, no sugerir por apellido) son la Fase 2.

| Pieza | Qué hace |
|---|---|
| `src/Exclusion.php` | La solicitud, inmutable. Transiciones: `solicitar` → `verificar` (activa) o `rechazar`; `activa` → `revocar`. Entradas inválidas lanzan `InvalidArgumentException`; transiciones no permitidas, `DomainException`. |
| `src/Estado.php` | `pendiente`, `activa`, `rechazada`, `revocada`. |
| `src/Solicitante.php` | `persona`, `familiar_fallecido`, `apoderado`. |
| `database/esquema.sql` | Tabla `exclusion` en PostgreSQL, con `CHECK` de longitudes y estados, y de que una activa tenga prueba. |

## Pruebas

```bash
composer install
./vendor/bin/phpunit
psql -f database/esquema.sql   # dos veces: la segunda no debe fallar
```

La CI (`.github/workflows/pruebas-personas-especiales.yml`) corre PHPUnit en PHP
8.1 a 8.4 y carga el esquema en PostgreSQL 16.

## Dependencias

Sin dependencias de runtime fuera de `ext-mbstring`. PHPUnit 10 solo para pruebas.
