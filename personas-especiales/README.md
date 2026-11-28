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

## Estado: el modelo, su registro, la política de visibilidad y la auditoría

**La política ya restringe** (en este módulo): una persona con una exclusión activa no se
conecta, no se muestra en listas y no sale en una exportación. La coincidencia por apellido
existe pero solo marca para revisión. Cada paso de una solicitud deja un evento de auditoría. La
integración con `url-routing` espera a que se fusione #611.

| Pieza | Qué hace |
|---|---|
| `src/Exclusion.php` | La solicitud, inmutable. Transiciones: `solicitar` → `verificar` (activa) o `rechazar`; `activa` → `revocar`. Entradas inválidas lanzan `InvalidArgumentException`; transiciones no permitidas, `DomainException`. |
| `src/Estado.php` | `pendiente`, `activa`, `rechazada`, `revocada`. |
| `src/Solicitante.php` | `persona`, `familiar_fallecido`, `apoderado`. |
| `src/Motivo.php` | `privacidad_personal`, `seguridad`, `asociacion_con_familiar`, `otro`. Lista cerrada, **sin texto libre**: lo elige quien pide, y ninguno nombra una religión, un origen ni una organización. |
| `src/Visibilidad.php` | La política: `esVisible`, `puedeConectar`, `puedeExportar` y `filtrar` (quita excluidas de una lista, sin cambiar el orden). Solo dice sí o no, sin decir por qué. |
| `src/Coincidencias.php` | Compara apellidos normalizados para revisión humana. No oculta a nadie. |
| `src/RegistroExclusiones.php` | Interfaz del registro: `activasDe`, `conExclusionActiva` (por lote, para listas largas), `porId`, `insertar` (nunca reemplaza) y `actualizar` (solo lo que cambia una transición, y solo si el estado sigue siendo el leído). |
| `src/RegistroPdo.php` | Implementación sobre la tabla `exclusion` (PostgreSQL). Dentro de una transacción, `porId` bloquea la fila (`FOR UPDATE`). |
| `src/RegistroEnMemoria.php` | Implementación sin base de datos, para pruebas. |
| `src/Gestion.php` | El camino de una solicitud: aplica la transición, la guarda y deja el evento de auditoría. Con un `PDO`, las tres cosas van en una transacción. |
| `src/Evento.php`, `src/TipoEvento.php`, `src/Auditoria.php` | Un hecho de la auditoría: quién (identificador opaco), qué y cuándo. No lleva razones ni pruebas. |
| `src/AuditoriaPdo.php`, `src/AuditoriaEnMemoria.php` | Las dos implementaciones de la auditoría. |
| `src/Texto.php` | Valida los textos que entran (ids, actor, prueba): rechaza UTF-8 inválido, caracteres de control (un NUL, un salto de línea dentro de un id) y lo vacío o demasiado largo, antes de escribir nada. La prueba y la razón de un rechazo admiten varias líneas. |
| `src/Fila.php` | Lectura comprobada de lo que devuelve la base: un tipo inesperado falla con un error claro en vez de convertirse en `""`. |
| `src/Migrador.php`, `bin/migrar.php` | Aplican en orden las migraciones que faltan, cada una en su transacción, con un candado asesor de PostgreSQL para que dos instancias no choquen. `bin/migrar.php` sale con 0 (sin errores, haya o no pendientes), 1 (falló la conexión o una migración: un mensaje de una línea, sin traza) o 2 (falta `PE_DSN`). |
| `database/migraciones/` | `001_exclusion.sql`: tabla `exclusion`, con `CHECK` de longitudes, estados y motivos, y de que una activa tenga prueba. `002_exclusion_evento.sql`: la auditoría. |

### Respuesta uniforme

Quien llama a la plataforma debe dar **la misma respuesta** cuando una persona no
existe y cuando está excluida. `Visibilidad` solo devuelve `true` o `false`, sin
motivo, para que la respuesta no revele que existe un registro.

### Alcance

- Solo la persona excluida queda fuera de las listas, las conexiones y las exportaciones.
- **Parientes (decisión de la persona a cargo, 2026-11-20):** en la ficha de un pariente, la persona
  excluida se muestra con el mismo marcador que `url-routing` usa para una persona viva
  («Persona viva»), sin nombre ni datos. Así es indistinguible de una persona viva oculta por
  privacidad y no revela que existe una exclusión. Esto se implementa en la integración con
  `url-routing` (espera a #611); este módulo hoy solo dice si una persona es visible.
- Una exclusión pendiente o rechazada no excluye. Una revocada vuelve a verse.

### Coincidencia por apellido: solo para revisión

`src/Coincidencias.php` marca posibles homónimos o familiares. **No oculta a nadie**:
ocultar por apellido tendría falsos positivos (una familia entera), así que la
decisión queda en una persona.

- Dos apellidos coinciden si, normalizados, son iguales y tienen al menos 3 letras.
  La normalización pasa a minúsculas, quita tildes y eñes (también las tildes **descompuestas**,
  «e» + una marca aparte, pero solo detrás de una letra latina), borra el apóstrofo en cualquiera de
  sus formas (`'`, `’`, `‘`, `ʼ`, `ʻ`…: «O'Brien» = «OBrien») y separa con espacio el guion y lo demás.
- Conserva las letras de otros alfabetos, y sus marcas (un apellido en cirílico o en devanagari no se
  vacía ni se parte con un espacio).
- **Límites conocidos**: no hay coincidencia fonética ni difusa, y «Müller» no es
  «Mueller». Cada una agrega falsos positivos; se decide aparte.
- No usa la librería de `defamatory-content-review` a propósito: depender de otra
  carpeta por composer acopla dos proyectos. Si se decide la fonética, se reusa entonces.

### Auditoría

Cada paso de una solicitud (`solicitada`, `verificada`, `rechazada`, `revocada`) deja un
evento con el actor y la hora. La base **no deja editar ni borrar** esa tabla: un
disparador rechaza `UPDATE`, `DELETE` y `TRUNCATE`. Si falla el registro del evento, no
queda el cambio de estado (misma transacción). El actor es un identificador opaco, no un
nombre, y el evento no copia la razón ni la prueba, para que el historial no acumule datos
personales.

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

- Conectar la coincidencia con el registro: hoy `Exclusion` guarda el id de la persona, no su
  apellido, y el apellido lo tiene la plataforma. Quien consulte pasa los apellidos de las excluidas.
- Mostrar el marcador de `url-routing` donde aparece una persona excluida (ver «Alcance»).
- Quién puede pedir la exclusión y cómo se verifica: hoy se asumen la persona viva, un familiar
  directo de una persona fallecida y un apoderado con prueba. Está sin decidir; la pregunta 33 de
  [`PREGUNTAS-PARA-ABOGADO.md`](../PREGUNTAS-PARA-ABOGADO.md) lo recoge.
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
`bin/migrar.php` y comprueba que la base rechace lo que debe (una activa sin prueba, un
motivo de texto libre y editar o borrar la auditoría), y corre PHPStan 2.1 en nivel `max`.

## Dependencias

Sin paquetes de runtime. Necesita `ext-mbstring`; los registros y la auditoría sobre PostgreSQL
(`RegistroPdo`, `AuditoriaPdo` y `Migrador`) necesitan además `ext-pdo_pgsql`, que `composer.json` solo
sugiere porque las implementaciones en memoria funcionan sin ella. PHPUnit 10 solo para pruebas.
