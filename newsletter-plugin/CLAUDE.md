# Plugin de newsletter (Conce y Plataforma POS)

Plugin reutilizable. Se integra a una plataforma a la vez, de forma independiente.
Core común, con estrategias de programación distintas por plataforma.

## Estado
Documentación v0.1 (borrador). Implementación no iniciada.
Hay decisiones pendientes (ver docs/05-riesgos-y-pendientes.md, sección "Decisiones pendientes").

## Fuentes de verdad
- docs/01-politicas.md: si el código contradice este documento, gana el documento.
- docs/02-diseno-funcional.md
- docs/03-arquitectura.md
- docs/00-inconsistencias.md: historial de decisiones y su resolución.
- docs/05-riesgos-y-pendientes.md: decisiones tomadas, pendientes, riesgos y supuestos.
- docs/06-ley-21719.md: la Ley 21.719 aplicada al newsletter (no es asesoría legal).

## Reglas no negociables
- No hay vía urgente en el newsletter.
- Carril de notificaciones: llamada directa, tiempo real, sin cola ni agrupación. Solo Conce.
- Clasificación de cambios sensibles: la entrega el host con el evento. El plugin no llama a `defamatory-content-review/` ni ve el valor del dato, y el revisor solo lee y no decide política.
- Fail-closed ante datos sensibles.
- Los logs no contienen valores de datos personales.
- Un envío por usuario y ventana (clave única en send_log).
- En POS el perfil solo segmenta contenido; nunca cambia la fecha de envío.
- En Conce la agrupación por familia es bloque opcional dentro del correo personal. Nunca hay envío conjunto.

## Supuestos [SUPUESTO]
Están marcados en docs/. No implementarlos como definitivos sin validación.

## Pendientes [PENDIENTE]
No implementar hasta resolverse:
- Contrato del carril de notificaciones (propuesta en docs/02; la envía el usuario a Conce).
- Alcance del caso "abuelo avisa a familiares" (se decide al implementar el carril).
- Validación de la lista de campos sensibles, con el contrato (la valida Conce; docs/05, pendiente 3).
- Ley 21.719 aplicada al newsletter: cotejar docs/06 con el texto oficial (docs/05, pendiente 4).
- Encargado del correo: faltan el nombre del proveedor y el país de sus servidores (docs/06, sección 3, punto 5).
- País del VPS del host, donde corren el cron y el PostgreSQL del plugin (docs/05, pendiente 7).

Decididos (ver docs/05):
- 18-nov-2026: destinatarios, cron en el VPS del host, lenguaje PHP, doble confirmación y umbrales con valores iniciales.
- 20-nov-2026: el host entrega la clasificación con el evento, y la lista sensible incluye opiniones políticas.
- 25-nov-2026: base del aviso sensible (interés legítimo), arranque sin fecha, correo por proveedor externo, plazos de conservación, aviso que no nombra campos de categoría especial, correo con solo los apellidos que el titular ya ve, y baja de los avisos sensibles para el titular y la persona afectada con cuenta.
- 26-nov-2026: formato de la clasificación (el host manda las categorías detectadas), sin framework (PHP plano con PDO), PostgreSQL propio del plugin en el VPS del host, un estado por destinatario en `sensitive_event` y fechas del Gantt como referencia orientativa.

## Stack
Lenguaje: PHP, igual que el resto del repo (decidido el 18-nov-2026), plano y con PDO, sin framework. Base de datos: un PostgreSQL propio del plugin, una base por integración, en el VPS del host (decidido el 26-nov-2026). La versión mínima de PHP se fija al implementar.
