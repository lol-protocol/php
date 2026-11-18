# 00 · Inconsistencias detectadas y resolución

Registro de contradicciones encontradas durante el diseño, con la resolución propuesta y su estado.

| # | Tema | Resolución | Estado |
|---|---|---|---|
| 1 | Umbral N: ¿por usuario o por plataforma? | N es política de plataforma. El perfil solo elige la frecuencia dentro de ese límite. | Resuelto |
| 2 | Perfil vs. envío global en POS | En POS el perfil solo segmenta contenido (coleccionista o explorador). La fecha la fija la plataforma. | **[SUPUESTO]** a validar |
| 3 | Cola única vs. colas por usuario | Un core con una interfaz `ScheduleStrategy` y dos implementaciones: fecha fija (POS) y ventana por usuario (Conce). | Resuelto |
| 4 | Agrupación por familia: ¿reemplaza o es opcional? | Es un bloque dentro del correo personal. Nunca hay envío conjunto. | Resuelto |
| 5 | Caso "el abuelo informa a hijos y nietos" | Implica que un usuario dispara avisos hacia otros, algo que el newsletter no contempla. Propuesta: fuera del alcance del plugin. | Pendiente de decisión |
| 6 | Destinatario del carril de notificaciones | Propuesta: titular del árbol siempre, y persona afectada si tiene cuenta. | Pendiente de decisión |
| 7 | Rol del submódulo de difamación | Repositorio de datos de solo lectura, sin decisiones de política. La regla de qué se notifica vive en el plugin. Si el submódulo no responde, se trata como sensible (fail-closed). | Resuelto |
| 8 | Contrato del carril de notificaciones | Nombres y firmas de funciones aún no documentados. El adaptador es la única superficie de contacto. | Dependencia abierta |
| 9 | Zona horaria | Las ventanas se calculan en la zona del usuario. El cron evalúa cada hora en UTC. | Resuelto |

## Notas
- Las filas 2, 5, 6 y 8 requieren confirmación antes de implementar.
- Un supuesto no validado no es una decisión. Ver docs/05-riesgos-y-pendientes.md.
