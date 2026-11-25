# 05 · Riesgos, supuestos y pendientes

## Decisiones tomadas

Registradas también en docs/00-inconsistencias.md.

1. **Destinatarios del carril de notificaciones** (18-nov-2026): titular del árbol siempre, y persona afectada si tiene cuenta.
2. **Cron** (18-nov-2026): en el VPS del host.
3. **Stack** (18-nov-2026): PHP, igual que el resto del repo.
4. **Doble confirmación en el alta** (18-nov-2026): sí.
5. **Umbrales de perfil** (18-nov-2026): se implementan con los valores de docs/01 como supuesto, y se calibran con datos de uso.
6. **Campos sensibles** (18-nov-2026, ampliada el 20-nov-2026): la lista se amplía con salud, origen étnico o racial, creencias o religión, vida sexual u orientación, y opiniones políticas. Falta validarla.
7. **Letra N** (18-nov-2026): el máximo de envíos por ventana conserva N; el mínimo de títulos del perfil coleccionista pasa a M.
8. **POS** (18-nov-2026): el perfil solo segmenta contenido. El primer envío no tiene fecha firme.
9. **Clasificación de cambios sensibles** (20-nov-2026): el host guarda el resultado de `defamatory-content-review/` y lo pasa con el evento. El plugin no llama al revisor ni ve el valor del dato.
10. **Validación de la lista sensible** (21-nov-2026): la valida Conce junto con el contrato del carril.
11. **Contrato del carril** (21-nov-2026): lo envía el usuario a Conce, con un resumen de una página.

## Decisiones pendientes

Necesarias antes de implementar.

1. **El abuelo que avisa a familiares:** diferido; se decide al implementar el carril. Propuesta: fuera del alcance del plugin.
2. **Contrato del carril de notificaciones:** propuesta de firmas en docs/02, pendiente de validación con Conce (decisión 11). Incluye `clasificacion` en el evento (decisión 9).
3. **Lista de campos sensibles:** la valida Conce junto con el contrato (decisión 10).
4. **Ley 21.719 aplicada al newsletter:** revisada en docs/06 el 21-nov-2026. Falta cotejarla con el texto oficial y decidir las bases, plazos y encargados que señala.
5. **Versión mínima de PHP:** se fija al implementar. No bloquea los documentos.

## Riesgos

- **Dominio público de Project Gutenberg:** el sitio indica el estado según la ley de EE. UU. Antes de publicar fragmentos desde Chile hay que verificar el estado de cada obra según la ley chilena.
- **Submódulo de difamación:** identificado como `defamatory-content-review/`. El host le pasa los textos y guarda el resultado (decisión 9); falta documentar el contrato del host. Si el resultado no llega o viene incompleto, se trata como sensible (fail-closed).
- **Umbrales de perfil sin datos reales:** los 7, 14 y 30 días son supuestos. Calibrar con datos de uso.
- **Carril de notificaciones sin documentar:** el adaptador concentra el riesgo. Si cambia la firma, solo cambia el adaptador.
- **Ley 21.719 y el newsletter:** el boletín no estaba en el registro de tratamientos del repo. docs/06 lo cubre. Rige desde el 1-dic-2026 según las fuentes del repo; falta el texto oficial (pendiente 4).
- **Carga de pruebas:** las pruebas suman entre 35 % y 45 % adicional a la estimación de LOC.

## Supuestos sin validar

Marcados como **[SUPUESTO]** en los documentos:
- Lista de campos sensibles (ampliada, sin validar; la valida Conce).
- Umbrales de perfil: valores iniciales (decisión 5).
- Umbral de colección para el perfil coleccionista (M a definir).
- Reintentos de correo: máximo 3.
- Envío POS el día 15 a las 09:00 hora de Santiago, sin fecha firme para el primer envío.
- Plazos de conservación propuestos en docs/06, sección 3 (punto 4).
- Casos típicos de los diagramas Gantt (docs/gantt/), ilustrativos.

## Estado de madurez

El diseño es coherente en sus decisiones principales. No está probado. Los supuestos anteriores deben validarse antes de darlo por sólido.
