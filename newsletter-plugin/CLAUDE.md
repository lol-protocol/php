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

## Reglas no negociables
- No hay vía urgente en el newsletter.
- Carril de notificaciones: llamada directa, tiempo real, sin cola ni agrupación. Solo Conce.
- Submódulo de difamación: solo lectura. No decide política.
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
- Baja de los avisos sensibles: sin decidir (docs/06, sección 3, punto 3).

Decididos el 18-nov-2026 (ver docs/05): destinatarios, cron en el VPS del host, stack PHP, doble confirmación y umbrales con valores iniciales. Decididos el 20-nov-2026: el host entrega la clasificación con el evento, y la lista sensible incluye opiniones políticas. Decididos el 25-nov-2026: base del aviso sensible (interés legítimo), arranque sin fecha, plazos de conservación, aviso que no nombra campos de categoría especial y correo con solo los apellidos que el titular ya ve.

## Stack
PHP, igual que el resto del repo (decidido el 18-nov-2026). La versión mínima se fija al implementar.
