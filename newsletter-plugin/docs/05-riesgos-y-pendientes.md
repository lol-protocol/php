# 05 · Riesgos, supuestos y pendientes

## Decisiones pendientes

Necesarias antes de implementar.

1. **Destinatarios del carril de notificaciones:** ¿titular, persona afectada o ambos? Propuesta: titular siempre, y persona afectada si tiene cuenta.
2. **El abuelo que avisa a familiares:** ¿queda fuera del alcance del plugin? Propuesta: sí.
3. **Contrato del carril de notificaciones:** nombres de funciones, módulo y firmas.
4. **Umbrales y campos sensibles:** validar la tabla de perfiles y la lista de campos sensibles de docs/01-politicas.md. ¿Doble opt-in sí o no?
5. **Cron:** ¿corre en el VPS del host o en el del plugin?
6. **Stack:** lenguaje, framework y base de datos de cada plataforma.

## Riesgos

- **Dominio público de Project Gutenberg:** el sitio indica el estado según la ley de EE. UU. Antes de publicar fragmentos desde Chile hay que verificar el estado de cada obra según la ley chilena.
- **Contrato del submódulo de difamación:** no se conoce su interfaz. Se trata como dependencia de solo lectura con fail-closed.
- **Umbrales de perfil sin datos reales:** los 7, 14 y 30 días son supuestos. Calibrar con datos de uso.
- **Carril de notificaciones sin documentar:** el adaptador concentra el riesgo. Si cambia la firma, solo cambia el adaptador.
- **Carga de pruebas:** las pruebas suman entre 35 % y 45 % adicional a la estimación de LOC.

## Supuestos sin validar

Marcados como **[SUPUESTO]** en los documentos:
- En POS el perfil solo segmenta contenido.
- Doble confirmación en el alta.
- Lista de campos sensibles.
- Umbral de colección para el perfil coleccionista (N a definir).
- Reintentos de correo: máximo 3.
- Envío POS el día 15 a las 09:00 hora de Santiago.
- Casos típicos de los diagramas Gantt (docs/gantt/).

## Estado de madurez

El diseño es coherente en sus decisiones principales. No está probado. Los supuestos anteriores deben validarse antes de darlo por sólido.
