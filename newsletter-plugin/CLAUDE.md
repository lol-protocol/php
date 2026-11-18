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
- Destinatarios del carril de notificaciones (titular, persona afectada o ambos).
- Contrato del carril de notificaciones (nombres de funciones y firmas).
- Ubicación del cron (VPS del host o del plugin).
- Alcance del caso "abuelo avisa a familiares".
- Validación de umbrales de perfil y lista de campos sensibles.

## Stack
Por definir.
