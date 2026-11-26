# 00 · Inconsistencias detectadas y resolución

Registro de contradicciones encontradas durante el diseño, con la resolución propuesta y su estado. También es el historial de decisiones: las que se toman se añaden como filas.

| # | Tema | Resolución | Estado |
|---|---|---|---|
| 1 | Umbral N: ¿por usuario o por plataforma? | N es política de plataforma. El perfil solo elige la frecuencia dentro de ese límite. | Resuelto |
| 2 | Perfil vs. envío global en POS | En POS el perfil solo segmenta contenido (coleccionista o explorador). La fecha la fija la plataforma. | Resuelto (18-nov-2026) |
| 3 | Cola única vs. colas por usuario | Un core con una interfaz `ScheduleStrategy` y dos implementaciones: fecha fija (POS) y ventana por usuario (Conce). | Resuelto |
| 4 | Agrupación por familia: ¿reemplaza o es opcional? | Es un bloque dentro del correo personal. Nunca hay envío conjunto. | Resuelto |
| 5 | Caso "el abuelo informa a hijos y nietos" | Implica que un usuario dispara avisos hacia otros, algo que el newsletter no contempla. Propuesta: fuera del alcance del plugin. | Diferido: se decide al implementar |
| 6 | Destinatario del carril de notificaciones | Titular del árbol y persona afectada si tiene cuenta (decidido el 18-nov-2026). Ambos pueden apagar los avisos (filas 26 y 27). | Resuelto |
| 7 | Rol del submódulo de difamación | Identificado como `defamatory-content-review/` (18-nov-2026): revisa nombres y texto libre, y no guarda registros de personas. La regla de qué se notifica vive en el plugin. Decidido (20-nov-2026): el host guarda el resultado del revisor y lo pasa con el evento; el plugin no llama al revisor ni ve el valor del dato (docs/05, decisión 9). Si la clasificación no llega o viene incompleta, el evento se trata como sensible (fail-closed). | Resuelto |
| 8 | Contrato del carril de notificaciones | Propuesta de firmas en docs/02 (18-nov-2026). El adaptador es la única superficie de contacto. | Propuesta; la envía el usuario a Conce (decisión 11 de docs/05) |
| 9 | Zona horaria | Las ventanas se calculan en la zona del usuario. El cron evalúa cada hora en UTC. | Resuelto |
| 10 | Letra N usada para dos cosas | El máximo de envíos por ventana conserva N. El mínimo de títulos del perfil coleccionista pasa a M (a definir). | Resuelto (18-nov-2026) |
| 11 | Umbrales de perfil sin datos reales | Se implementan con los valores de docs/01 como supuesto, y se calibran con datos de uso. | Decidido (18-nov-2026) |
| 12 | Doble confirmación en el alta | Sí: doble confirmación por correo. | Decidido (18-nov-2026) |
| 13 | Ubicación del cron | En el VPS del host. | Decidido (18-nov-2026) |
| 14 | Stack | Lenguaje: PHP, igual que el resto del repo. El framework y la base de datos siguen sin decidir. | Parcial: lenguaje decidido (18-nov-2026) |
| 15 | Lista de campos sensibles | Ampliada con salud, origen étnico o racial, creencias o religión, vida sexual u orientación, y opiniones políticas (20-nov-2026), a partir de PRIVACIDAD-Y-HONRA.md (Ley 19.628). Sigue [SUPUESTO] hasta validarla. | Ampliada; la valida Conce con el contrato (21-nov-2026) |
| 16 | Ley 21.719 aplicada al newsletter | Cruce con `CUMPLIMIENTO-LEY-21719.md`: el boletín no estaba en el registro de tratamientos. Ver docs/06. | Revisada; falta cotejar con el texto oficial |
| 17 | Versión mínima de PHP | Se fija al implementar. No bloquea los documentos. | Diferido |
| 18 | Contrato con Conce | La propuesta la envía el usuario a Conce con un resumen de una página. | Decidido (21-nov-2026) |
| 19 | Base del aviso sensible | Interés legítimo, con una ponderación documentada (decisión 12 de docs/05). Falta confirmarla con el texto oficial. | Decidido (25-nov-2026) |
| 20 | Arranque del boletín | Sin fecha por ahora; no se fija hasta cerrar docs/06. | Decidido (25-nov-2026) |
| 21 | Correo del boletín | Lo envía un proveedor externo, que va como encargado. Faltan su nombre y el país de sus servidores (docs/06, sección 3, punto 5). | Pendiente de datos |
| 22 | Plazos de conservación | `queue_item`: 30 días después del envío. `send_log` y `profile_snapshot`: 6 meses. `sensitive_event`: mientras exista la ficha. `subscriptions`: 6 meses desde la baja. Siguen como supuesto hasta cotejarlos con el texto oficial. | Decidido (25-nov-2026) |
| 23 | Aviso sensible y categorías especiales | El aviso no nombra el campo cuando es categoría especial: dice que hubo un cambio y enlaza a la ficha. | Decidido (25-nov-2026) |
| 24 | Personas vivas en el correo | El correo solo muestra apellidos que el titular ya ve en la ficha. | Decidido (25-nov-2026) |
| 25 | Criterio de bloqueo de docs/06 | Se mantienen las nueve obligaciones marcadas como bloqueantes. Es criterio del equipo, no de la ley. | Decidido (25-nov-2026) |
| 26 | Baja de los avisos sensibles | El titular y la persona afectada con cuenta pueden apagarlos desde sus Preferencias; el plugin registra el evento y no se lo envía a quien lo apagó. | Decidido (25-nov-2026) |
| 27 | Persona afectada con cuenta y los avisos | Puede apagarlos desde sus Preferencias, igual que el titular. | Decidido (25-nov-2026) |
| 28 | Auditoría de coherencia | Se alinearon docs/01, docs/02, docs/03, docs/04, docs/06, CLAUDE.md, el README y los Gantt con las decisiones sobre la clasificación del host, el stack, los plazos y la baja de los avisos. Los escenarios de perfil siguen ahora la regla de docs/01, §2.1 (ocasional: de 15 a 90 días). | Resuelto (26-nov-2026) |

## Notas
- Quedan abiertas: la 5 (se decide al implementar), la 8 y la 15 (las valida Conce con el contrato), la 14 (framework y base de datos), la 16 (cotejar con el texto oficial), la 17 (se fija al implementar) y la 21 (falta el nombre del proveedor de correo y el país de sus servidores).
- Un supuesto no validado no es una decisión. Ver docs/05-riesgos-y-pendientes.md.
