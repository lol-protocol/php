# 05 · Riesgos, supuestos y pendientes

## Decisiones tomadas

Registradas también en docs/00-inconsistencias.md.

1. **Destinatarios del carril de notificaciones** (18-nov-2026): titular del árbol y persona afectada si tiene cuenta. Ambos pueden apagar los avisos (decisión 19).
2. **Cron** (18-nov-2026): en el VPS del host.
3. **Lenguaje** (18-nov-2026): PHP, igual que el resto del repo. Framework y base de datos: decisiones 21 y 22.
4. **Doble confirmación en el alta** (18-nov-2026): sí.
5. **Umbrales de perfil** (18-nov-2026): se implementan con los valores de docs/01 como supuesto, y se calibran con datos de uso.
6. **Campos sensibles** (18-nov-2026, ampliada el 20-nov-2026): la lista se amplía con salud, origen étnico o racial, creencias o religión, vida sexual u orientación, y opiniones políticas. Falta validarla.
7. **Letra N** (18-nov-2026): el máximo de envíos por ventana conserva N; el mínimo de títulos del perfil coleccionista pasa a M.
8. **POS** (18-nov-2026): el perfil solo segmenta contenido. El primer envío no tiene fecha firme.
9. **Clasificación de cambios sensibles** (20-nov-2026): el host guarda el resultado de `defamatory-content-review/` y lo pasa con el evento. El plugin no llama al revisor ni ve el valor del dato.
10. **Validación de la lista sensible** (21-nov-2026): la valida Conce junto con el contrato del carril.
11. **Contrato del carril** (21-nov-2026): lo envía el usuario a Conce, con un resumen de una página.
12. **Base del aviso sensible** (25-nov-2026): interés legítimo, con una ponderación documentada. Falta confirmar con el texto oficial que la ley acepte esa base para este caso.
13. **Arranque del boletín** (25-nov-2026): sin fecha por ahora. No se fija hasta cerrar docs/06.
14. **Correo** (25-nov-2026): lo envía un proveedor externo, que va como encargado. Falta su nombre y el país de sus servidores.
15. **Plazos de conservación** (25-nov-2026): `queue_item`, 30 días después del envío; `send_log` y `profile_snapshot`, 6 meses; `sensitive_event`, mientras exista la ficha; `subscriptions`, 6 meses desde la baja. Siguen como supuesto hasta cotejarlos con el texto oficial.
16. **Aviso sensible y categorías especiales** (25-nov-2026): el aviso no nombra el campo cuando es categoría especial; dice que hubo un cambio y enlaza a la ficha.
17. **Personas vivas en el correo** (25-nov-2026): el correo solo muestra apellidos que el titular ya ve en la ficha.
18. **Criterio de bloqueo de docs/06** (25-nov-2026): se mantienen las nueve obligaciones marcadas como bloqueantes. Es criterio del equipo, no de la ley.
19. **Baja de los avisos sensibles** (25-nov-2026): el titular y la persona afectada con cuenta pueden apagarlos, cada uno desde sus Preferencias. El plugin registra el evento y no se lo envía a quien lo apagó.
20. **Formato de la clasificación** (26-nov-2026): el host manda en `clasificacion` las categorías sensibles que detectó; el plugin las compara con su lista y, si coincide alguna, el aviso no nombra el campo. Los identificadores son una propuesta (docs/02).
21. **Framework** (26-nov-2026): ninguno; PHP plano con PDO, como el resto del repo.
22. **Base de datos** (26-nov-2026): un PostgreSQL propio del plugin, una base por integración (docs/02, §3).
23. **Estado por destinatario** (26-nov-2026): `sensitive_event` guarda `estado_titular` y `estado_afectada` en la misma fila.

## Decisiones pendientes

Necesarias antes de implementar.

1. **El abuelo que avisa a familiares:** diferido; se decide al implementar el carril. Propuesta: fuera del alcance del plugin.
2. **Contrato del carril de notificaciones:** propuesta de firmas en docs/02, pendiente de validación con Conce (decisión 11). Incluye `clasificacion` en el evento (decisión 9).
3. **Lista de campos sensibles:** la valida Conce junto con el contrato (decisión 10).
4. **Ley 21.719 aplicada al newsletter:** revisada en docs/06 (21-nov-2026, actualizada el 25-nov-2026). Falta cotejarla con el texto oficial.
5. **Versión mínima de PHP:** se fija al implementar. No bloquea los documentos.
6. **Encargado del correo** (25-nov-2026): falta el nombre del proveedor y el país de sus servidores (docs/06, sección 3, punto 5).
7. **Servidor del PostgreSQL del plugin** (26-nov-2026): sin decidir dónde corre (en el VPS del host o en un servidor aparte) ni en qué país (docs/06, sección 3, punto 5).

## Riesgos

- **Dominio público de Project Gutenberg:** el sitio indica el estado según la ley de EE. UU. Antes de publicar fragmentos desde Chile hay que verificar el estado de cada obra según la ley chilena.
- **Submódulo de difamación:** identificado como `defamatory-content-review/`. El host le pasa los textos y guarda el resultado (decisión 9); falta documentar el contrato del host. Si el resultado no llega o viene incompleto, se trata como sensible (fail-closed).
- **Umbrales de perfil sin datos reales:** los intervalos de 7, 14 y 30 días y los cortes de 14 y 90 días de inactividad son supuestos. Calibrar con datos de uso.
- **Carril de notificaciones solo propuesto:** Conce todavía no validó las firmas de docs/02. El adaptador concentra el riesgo: si cambia la firma, solo cambia el adaptador.
- **Ley 21.719 y el newsletter:** el boletín no estaba en el registro de tratamientos del repo. docs/06 lo cubre. Rige desde el 1-dic-2026 según las fuentes del repo; falta el texto oficial (pendiente 4).
- **Plazo de `sensitive_event`:** «mientras exista la ficha» es el más largo de los cuatro (decisión 15). Hay que poder justificarlo ante la ley (docs/06, sección 4, pregunta 9).
- **Infraestructura propia del plugin:** el PostgreSQL propio suma un servidor que mantener, respaldar y cubrir en la hoja de fugas (docs/06, filas 12 y 15).
- **Carga de pruebas:** las pruebas suman entre 35 % y 45 % adicional a la estimación de LOC.

## Supuestos sin validar

Marcados como **[SUPUESTO]** en los documentos:
- Lista de campos sensibles (ampliada, sin validar; la valida Conce).
- Umbrales de perfil: valores iniciales (decisión 5).
- Interés legítimo como base del aviso sensible, con la ponderación por documentar (decisión 12).
- Plazos de conservación decididos (decisión 15), sin cotejar con el texto oficial.
- Umbral de colección para el perfil coleccionista (M a definir).
- Reintentos de correo: máximo 3.
- Envío POS el día 15 a las 09:00 hora de Santiago, sin fecha firme para el primer envío.
- Casos típicos de los diagramas Gantt (docs/gantt/), ilustrativos.
- Identificadores de las categorías de `clasificacion` y valores de `estado_*` (docs/02): propuestas.

## Estado de madurez

El diseño es coherente en sus decisiones principales. No está probado. Los supuestos anteriores deben validarse antes de darlo por sólido.
