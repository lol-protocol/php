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

## Estado: Fase 1 (modelo) y Fase 2 (política de visibilidad, sin apellidos)

**La política ya restringe**: una persona con una exclusión activa no se conecta,
no se muestra en listas y no sale en una exportación. Lo que sigue pendiente es la
coincidencia por apellido o homónimo (ver «Pendiente» abajo).

| Pieza | Qué hace |
|---|---|
| `src/Exclusion.php` | La solicitud, inmutable. Transiciones: `solicitar` → `verificar` (activa) o `rechazar`; `activa` → `revocar`. Entradas inválidas lanzan `InvalidArgumentException`; transiciones no permitidas, `DomainException`. |
| `src/Estado.php` | `pendiente`, `activa`, `rechazada`, `revocada`. |
| `src/Solicitante.php` | `persona`, `familiar_fallecido`, `apoderado`. |
| `src/Visibilidad.php` | La política: `esVisible`, `puedeConectar`, `puedeExportar` y `filtrar` (quita excluidas de una lista, sin cambiar el orden). Solo dice sí o no, sin decir por qué. |
| `src/RegistroExclusiones.php` | Interfaz del registro: `activasDe` y `guardar`. |
| `src/RegistroPdo.php` | Implementación sobre la tabla `exclusion` (PostgreSQL). |
| `src/RegistroEnMemoria.php` | Implementación sin base de datos, para pruebas. |
| `database/esquema.sql` | Tabla `exclusion` en PostgreSQL, con `CHECK` de longitudes y estados, y de que una activa tenga prueba. |

### Respuesta uniforme

Quien llama a la plataforma debe dar **la misma respuesta** cuando una persona no
existe y cuando está excluida. `Visibilidad` solo devuelve `true` o `false`, sin
motivo, para que la respuesta no revele que existe un registro.

### Alcance

- Solo la persona excluida queda fuera. Sus parientes siguen visibles: qué hacer
  con sus vínculos queda para decidirlo aparte.
- Una exclusión pendiente o rechazada no excluye. Una revocada vuelve a verse.

### Pendiente

- Coincidencia por apellido y homónimos. Requiere la normalización de
  `defamatory-content-review` y decidir cuántos falsos positivos se aceptan
  antes de ocultar una familia entera.

## Pruebas

```bash
composer install
TEST_PG_DSN="pgsql:host=127.0.0.1;port=5432;dbname=pe_test" TEST_PG_USER=pe_test TEST_PG_PASSWORD=pe_test ./vendor/bin/phpunit
psql -f database/esquema.sql   # dos veces: la segunda no debe fallar
```

Sin `TEST_PG_DSN`, las 4 pruebas de `RegistroPdoTest` se omiten; el resto corre igual.

La CI (`.github/workflows/pruebas-personas-especiales.yml`) corre PHPUnit en PHP
8.1 a 8.4 contra PostgreSQL 16, comprueba que `RegistroPdoTest` no se omitió, y
carga el esquema dos veces.

## Dependencias

Sin dependencias de runtime fuera de `ext-mbstring`. PHPUnit 10 solo para pruebas.
