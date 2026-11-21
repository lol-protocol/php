# Plugin de newsletter (Conce y Plataforma POS)

Plugin reutilizable que envía el boletín de cada plataforma. Se integra a una plataforma a la vez, de forma independiente, y tiene un core común con dos estrategias de programación: ventana por usuario en Conce y fecha fija (día 15 de cada mes) en Plataforma POS.

> **Estado: borrador de diseño v0.1.** No hay código, pruebas ni CI, y el stack todavía no está definido. No se implementa hasta resolver las decisiones pendientes de [`docs/05-riesgos-y-pendientes.md`](docs/05-riesgos-y-pendientes.md).

## Qué hay aquí

| Archivo | Contenido |
|---|---|
| [`CLAUDE.md`](CLAUDE.md) | Reglas y fuentes de verdad para quien trabaje en esta carpeta. |
| [`docs/00-inconsistencias.md`](docs/00-inconsistencias.md) | Contradicciones detectadas en el diseño y cómo se resolvieron. |
| [`docs/01-politicas.md`](docs/01-politicas.md) | Reglas de envío, perfiles, suscripción, carril de notificaciones, privacidad y errores. Si el código contradice este documento, gana el documento. |
| [`docs/02-diseno-funcional.md`](docs/02-diseno-funcional.md) | Componentes, interfaces mínimas, modelo de datos y casos de uso. |
| [`docs/03-arquitectura.md`](docs/03-arquitectura.md) | Vista general y flujos de cada carril. |
| [`docs/04-mockups.md`](docs/04-mockups.md) | Plantillas de correo en texto. |
| [`docs/05-riesgos-y-pendientes.md`](docs/05-riesgos-y-pendientes.md) | Decisiones pendientes, riesgos y supuestos sin validar. |
| [`docs/gantt/`](docs/gantt/) | Escenarios de uso de Conce y POS, y el calendario de implementación de Conce. |

Los supuestos van marcados como **[SUPUESTO]** en los documentos. Ninguno debe implementarse como definitivo sin validación.
