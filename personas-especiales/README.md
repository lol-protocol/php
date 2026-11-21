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

## Estado: el modelo y su registro

Este trozo cubre la solicitud, su registro en PostgreSQL y las migraciones. **Todavía no
restringe nada**: la política de visibilidad (no conectar, no mostrar, no exportar) y la
auditoría llegan en PR siguientes, y la integración con `url-routing` espera a que se
fusione #611.

| Pieza | Qué hace |
|---|---|
| `src/Exclusion.php` | La solicitud, inmutable. Transiciones: `solicitar` → `verificar` (activa) o `rechazar`; `activa` → `revocar`. Entradas inválidas lanzan `InvalidArgumentException`; transiciones no permitidas, `DomainException`. |
| `src/Estado.php` | `pendiente`, `activa`, `rechazada`, `revocada`. |
| `src/Solicitante.php` | `persona`, `familiar_fallecido`, `apoderado`. |
| `src/Motivo.php` | `privacidad_personal`, `seguridad`, `asociacion_con_familiar`, `otro`. Lista cerrada, **sin texto libre**: lo elige quien pide, y ninguno nombra una religión, un origen ni una organización. |
| `src/RegistroExclusiones.php` | Interfaz del registro: `activasDe`, `porId`, `insertar` (nunca reemplaza) y `actualizar` (solo lo que cambia una transición, y solo si el estado sigue siendo el leído). |
| `src/RegistroPdo.php` | Implementación sobre la tabla `exclusion` (PostgreSQL). Dentro de una transacción, `porId` bloquea la fila (`FOR UPDATE`). |
| `src/RegistroEnMemoria.php` | Implementación sin base de datos, para pruebas. |
| `src/Fila.php` | Lectura comprobada de lo que devuelve la base: un tipo inesperado falla con un error claro en vez de convertirse en `""`. |
| `src/Migrador.php`, `bin/migrar.php` | Aplican en orden las migraciones que faltan, cada una en su transacción, con un candado asesor de PostgreSQL para que dos instancias no choquen. |
| `database/migraciones/` | `001_exclusion.sql`: tabla `exclusion`, con `CHECK` de longitudes, estados y motivos, y de que una activa tenga prueba. |

### Concurrencia

- **Un id repetido nunca pisa a otra solicitud.** `insertar` falla con `DomainException` si el
  id ya existe, y lo decide la base (la llave primaria), no una lectura previa: vale también
  cuando dos solicitudes llegan a la vez. Los ids los genera quien llama (que sean UUID).
- **Dos revisores sobre la misma solicitud no se pisan.** `actualizar` recibe el estado que se
  leyó y cambia la fila solo si sigue en ese estado (`WHERE id = … AND estado = …`, en un solo
  paso). Si otro la cambió antes, falla en vez de deshacerle el cambio. Tampoco puede tocar la
  persona, quien pide, el motivo ni la fecha de alta: solo estado, prueba y fecha del cambio.
- **Dos instancias que migran a la vez** se turnan: `Migrador` toma un candado asesor de
  PostgreSQL (`Migrador::CLAVE_CANDADO`), la segunda espera y luego no encuentra nada pendiente.
  El candado se libera al terminar, también si una migración falla, y si la conexión se cae.

### El motivo es una lista cerrada

Un campo de texto libre invitaría a escribir justo lo que el módulo no debe guardar («es
judío», «hijo de tal político»). Por eso `motivo` es una lista cerrada (`Motivo`), y la base
también la exige con un `CHECK`. «Otro» no pide ni guarda cuál es la razón. Si hace falta un
motivo más, se agrega al `enum` y a la migración, a propósito y a mano.

Esta lista la decidió la persona a cargo el 2026-11-20 (se dejan los cuatro motivos).

### Pendiente

- La política de visibilidad, la revisión por apellido y la auditoría (PR siguientes).
- Quién puede pedir la exclusión y cómo se verifica: hoy se asumen la persona viva, un
  familiar directo de una persona fallecida y un apoderado con prueba. Está sin decidir y
  pasa al abogado.
- Guardar las pruebas de forma segura, y el contacto del solicitante.

## Pruebas

```bash
composer install
export TEST_PG_DSN="pgsql:host=127.0.0.1;port=5432;dbname=pe_test" TEST_PG_USER=pe_test TEST_PG_PASSWORD=pe_test
./vendor/bin/phpunit --fail-on-skipped
phpstan analyse                 # nivel max sobre src/ (phpstan.neon.dist)
PE_DSN=$TEST_PG_DSN PE_USER=pe_test PE_PASSWORD=pe_test php bin/migrar.php   # idempotente
```

Las pruebas que usan PostgreSQL **borran las tablas del módulo**: usa solo una base de
prueba. Sin `TEST_PG_DSN` esas pruebas se omiten y el resto corre; con `--fail-on-skipped`
una omisión es un fallo, como en la CI.

La CI (`.github/workflows/pruebas-personas-especiales.yml`) corre PHPUnit en PHP 8.1 a 8.4
contra PostgreSQL 16 (con `--fail-on-skipped`), aplica las migraciones dos veces con
`bin/migrar.php` y comprueba que la base rechace lo que debe (una activa sin prueba y un
motivo de texto libre), y corre PHPStan 2.1 en nivel `max`.

## Dependencias

Sin dependencias de runtime fuera de `ext-mbstring`. PHPUnit 10 solo para pruebas.
