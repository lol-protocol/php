# 05 · Riesgos, supuestos y pendientes

## Decisiones tomadas (18-nov-2026)

Registradas también en docs/00-inconsistencias.md.

1. **Destinatarios del carril de notificaciones:** titular del árbol siempre, y persona afectada si tiene cuenta.
2. **Cron:** en el VPS del host.
3. **Stack:** PHP, igual que el resto del repo.
4. **Doble confirmación en el alta:** sí.
5. **Umbrales de perfil:** se implementan con los valores de docs/01 como supuesto, y se calibran con datos de uso.
6. **Campos sensibles:** la lista se amplía con salud, origen étnico o racial, creencias o religión, y vida sexual u orientación. Falta validarla.
7. **Letra N:** el máximo de envíos por ventana conserva N; el mínimo de títulos del perfil coleccionista pasa a M.
8. **POS:** el perfil solo segmenta contenido. El primer envío no tiene fecha firme.

## Decisiones pendientes

Necesarias antes de implementar.

1. **El abuelo que avisa a familiares:** diferido; se decide al implementar el carril. Propuesta: fuera del alcance del plugin.
2. **Contrato del carril de notificaciones:** propuesta de firmas en docs/02, pendiente de validación con Conce.
3. **Registros marcados del submódulo:** ¿cómo llega al plugin la clasificación de un cambio? `defamatory-content-review/` revisa nombres y texto libre; no consulta registros. Opciones: (a) el host guarda el resultado y lo pasa con el evento; (b) el plugin llama al revisor con el texto del campo, lo que le expone el valor; (c) otra fuente.
4. **Lista de campos sensibles:** validar la lista ampliada. Decidir si entran las opiniones políticas, que PRIVACIDAD-Y-HONRA.md define como sensibles y la lista no incluye.
5. **Ley 21.719:** no está en el diseño. Pendiente de revisión.

## Riesgos

- **Dominio público de Project Gutenberg:** el sitio indica el estado según la ley de EE. UU. Antes de publicar fragmentos desde Chile hay que verificar el estado de cada obra según la ley chilena.
- **Submódulo de difamación:** identificado como `defamatory-content-review/`, sin contrato de registros marcados (pendiente 3). Fail-closed mientras tanto.
- **Umbrales de perfil sin datos reales:** los 7, 14 y 30 días son supuestos. Calibrar con datos de uso.
- **Carril de notificaciones sin documentar:** el adaptador concentra el riesgo. Si cambia la firma, solo cambia el adaptador.
- **Ley 21.719 fuera del diseño:** rige desde el 1-dic-2026 según PRIVACIDAD-Y-HONRA.md. Pendiente de revisión (pendiente 5).
- **Carga de pruebas:** las pruebas suman entre 35 % y 45 % adicional a la estimación de LOC.

## Supuestos sin validar

Marcados como **[SUPUESTO]** en los documentos:
- Lista de campos sensibles (ampliada, sin validar).
- Umbrales de perfil: valores iniciales (decisión 5).
- Umbral de colección para el perfil coleccionista (M a definir).
- Reintentos de correo: máximo 3.
- Envío POS el día 15 a las 09:00 hora de Santiago, sin fecha firme para el primer envío.
- Casos típicos de los diagramas Gantt (docs/gantt/), ilustrativos.

## Estado de madurez

El diseño es coherente en sus decisiones principales. No está probado. Los supuestos anteriores deben validarse antes de darlo por sólido.
