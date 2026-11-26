# 02 · Diseño funcional interno

Describe qué hace el plugin con firmas que no dependen del lenguaje (el lenguaje es PHP; ver docs/05). Las reglas están en docs/01-politicas.md.

---

## 1. Componentes

- **Suscripciones:** alta, baja y estado.
- **Resolutor de perfil:** calcula el perfil desde datos actuales (Conce y segmentación en POS).
- **Políticas:** `ScheduleStrategy` y umbral N.
- **ContentProvider:** interfaz con dos implementaciones, una por plataforma.
- **Cola de newsletter** y **agrupación**.
- **Renderizador** de plantilla por segmento.
- **Adaptador de correo** (sistema existente).
- **Regla sensible** y **adaptador de notificaciones** (solo Conce).
- **Preferencias de aviso** (solo Conce): guardan si cada destinatario apagó los avisos sensibles.
- **Recepción de la clasificación de cambios sensibles**, que el host entrega con el evento. El submódulo `defamatory-content-review/` no se consulta desde el plugin.
- **Scheduler:** cron horario en Conce y fecha fija en POS.
- **Registro** de envíos y eventos.

---

## 2. Interfaces mínimas

```
ContentProvider.collect(usuario, desde) -> [ContentItem]
ScheduleStrategy.intervalo(perfil) -> duración
ScheduleStrategy.vencida(usuario, ahora) -> bool
MailSender.send(destinatario, asunto, html, texto)
```

**Nota (18-nov-2026):** el submódulo es `defamatory-content-review/`. Su API pública revisa nombres y texto libre (`DefamatoryContentReviewer::validateName`, `ChatLineReviewer::review`). No consulta registros, así que `consultar(ref_registro)` no existe tal cual. **Decidido (20-nov-2026):** el host guarda el resultado del revisor y lo pasa con el evento (`EventoSensible.clasificacion`). El plugin no llama al revisor ni ve el valor del dato.

**Propuesta de contrato del carril de notificaciones** [PROPUESTA, la valida Conce]:

```
NotificationAdapter.emit(evento: EventoSensible) -> ack | error
NotificationAdapter.estado(evento_id) -> pendiente | entregado | error
EventoSensible = { id, arbol_id, ref_registro, tipo_cambio, clasificacion, titular_id | null, afectada_id | null, detectado_at }
```

`id` es la clave de idempotencia: un reintento del cron no duplica el aviso. El evento no lleva valores del dato (docs/01, sección 5), y el aviso no nombra el campo cuando es de categoría especial (decidido el 25-nov-2026). Si un destinatario apagó los avisos desde Preferencias, el plugin deja su campo en `null` y el aviso no se le envía; si los dos campos quedan en `null`, registra el evento y no llama al adaptador (decidido el 25-nov-2026).

---

## 3. Modelo de datos

Almacenamiento propio por integración. Ninguna tabla es compartida entre plataformas.

- `subscriptions(user_id, status, zona_horaria, opt_in_at, baja_at)`: `status` toma `pendiente_de_confirmar`, `activa` o `baja`, y `opt_in_at` es el momento de la confirmación (doble confirmación, docs/01, §4) **[PROPUESTA]**
- `profile_snapshot(user_id, calculado_at, perfil, señales)`
- `queue_item(id, user_id, ref_contenido, creado_at, estado)`
- `send_log(user_id, ventana_id, enviado_at, estado)`, con clave única (user_id, ventana_id)
- `sensitive_event(id, ref_registro, tipo_cambio, detectado_at, notificado_at, estado)`, solo en Conce
- `preferencias_aviso(user_id, avisos_sensibles, actualizado_at)`, solo en Conce **[PROPUESTA]**: guarda si cada destinatario (titular o persona afectada con cuenta) apagó los avisos sensibles (decidido el 25-nov-2026; la tabla es una propuesta)

---

## 4. Casos de uso

- **Suscribirse:** opt-in con doble confirmación por correo, zona horaria y alta en `subscriptions`.
- **Darse de baja:** estado a baja y borrado de ítems pendientes.
- **Calcular perfil:** snapshot y cálculo del intervalo efectivo.
- **Acumular contenido:** el proveedor encola novedades.
- **Enviar ventana:** si hay ítems y la ventana venció, se arma y envía el correo.
- **Detectar cambio sensible:** registro, evaluación y, si corresponde, notificación a cada destinatario que no haya apagado los avisos.

---

## 5. Estimación de LOC (productivas, sin pruebas)

| Componente | Conce | POS |
|---|---|---|
| Core: suscripciones, cola, agrupación, cron, renderizador, registro | 600–800 | 600–800 |
| ScheduleStrategy (ventana por usuario / fecha fija) | 120–160 | 60–90 |
| Perfiles y segmentación | 120–180 | 60–90 |
| ContentProvider de la plataforma | 200–280 | 200–280 |
| Adaptador de correo | 80–120 | 80–120 |
| Regla sensible y adaptador de notificaciones | 150–220 | — |
| **Total** | **1.270–1.760** | **1.000–1.380** |

- Las pruebas suman entre 35 % y 45 % adicional, no incluido.
- El core es el mismo código para ambas plataformas. Solo cambian las estrategias y los proveedores.
- Cifras orientativas. Dependen del stack: el lenguaje es PHP; el framework y la base de datos siguen sin decidir (docs/05).
