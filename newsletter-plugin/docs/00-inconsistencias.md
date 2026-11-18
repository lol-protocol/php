# 00 · Inconsistencias detectadas y resolución

Registro de contradicciones encontradas durante el diseño, con la resolución propuesta y su estado. También es el historial de decisiones: las que se toman se añaden como filas.

| # | Tema | Resolución | Estado |
|---|---|---|---|
| 1 | Umbral N: ¿por usuario o por plataforma? | N es política de plataforma. El perfil solo elige la frecuencia dentro de ese límite. | Resuelto |
| 2 | Perfil vs. envío global en POS | En POS el perfil solo segmenta contenido (coleccionista o explorador). La fecha la fija la plataforma. | Resuelto (18-nov-2026) |
| 3 | Cola única vs. colas por usuario | Un core con una interfaz `ScheduleStrategy` y dos implementaciones: fecha fija (POS) y ventana por usuario (Conce). | Resuelto |
| 4 | Agrupación por familia: ¿reemplaza o es opcional? | Es un bloque dentro del correo personal. Nunca hay envío conjunto. | Resuelto |
| 5 | Caso "el abuelo informa a hijos y nietos" | Implica que un usuario dispara avisos hacia otros, algo que el newsletter no contempla. Propuesta: fuera del alcance del plugin. | Diferido: se decide al implementar |
| 6 | Destinatario del carril de notificaciones | Titular del árbol siempre, y persona afectada si tiene cuenta (decidido el 18-nov-2026). | Resuelto |
| 7 | Rol del submódulo de difamación | Identificado como `defamatory-content-review/` (18-nov-2026): revisa nombres y texto libre, y no guarda registros de personas. La regla de qué se notifica vive en el plugin. Si el submódulo no responde, se trata como sensible (fail-closed). Falta decidir cómo llega la clasificación al plugin (docs/05, pendiente 3). | Pendiente de decisión |
| 8 | Contrato del carril de notificaciones | Propuesta de firmas en docs/02 (18-nov-2026). El adaptador es la única superficie de contacto. | Propuesta; falta validar con Conce |
| 9 | Zona horaria | Las ventanas se calculan en la zona del usuario. El cron evalúa cada hora en UTC. | Resuelto |
| 10 | Letra N usada para dos cosas | El máximo de envíos por ventana conserva N. El mínimo de títulos del perfil coleccionista pasa a M (a definir). | Resuelto (18-nov-2026) |
| 11 | Umbrales de perfil sin datos reales | Se implementan con los valores de docs/01 como supuesto, y se calibran con datos de uso. | Decidido (18-nov-2026) |
| 12 | Doble confirmación en el alta | Sí: doble confirmación por correo. | Decidido (18-nov-2026) |
| 13 | Ubicación del cron | En el VPS del host. | Decidido (18-nov-2026) |
| 14 | Stack | PHP, igual que el resto del repo. | Decidido (18-nov-2026) |
| 15 | Lista de campos sensibles | Ampliada con salud, origen étnico o racial, creencias o religión, y vida sexual u orientación, a partir de PRIVACIDAD-Y-HONRA.md (Ley 19.628). Sigue [SUPUESTO] hasta validarla. | Ampliada; falta validar |

## Notas
- Quedan abiertas: la 5 (se decide al implementar), la 7 (cómo llega la clasificación al plugin), la 8 (validar con Conce) y la 15 (validar la lista).
- Un supuesto no validado no es una decisión. Ver docs/05-riesgos-y-pendientes.md.
