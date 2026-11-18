# 02 · Diseño funcional interno

Describe qué hace el plugin, sin fijar todavía la tecnología. Las reglas están en docs/01-politicas.md.

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
- **Lector del submódulo de difamación** (solo lectura).
- **Scheduler:** cron horario en Conce y fecha fija en POS.
- **Registro** de envíos y eventos.

---

## 2. Interfaces mínimas

```
ContentProvider.collect(usuario, desde) -> [ContentItem]
ScheduleStrategy.intervalo(perfil) -> duración
ScheduleStrategy.vencida(usuario, ahora) -> bool
MailSender.send(destinatario, asunto, html, texto)
NotificationAdapter.emit(evento_sensible) -> ack | error
DefamationReader.consultar(ref_registro) -> datos | error   (solo lectura)
```

**Nota (18-nov-2026):** el submódulo es `defamatory-content-review/`. Su API pública revisa nombres y texto libre (`DefamatoryContentReviewer::validateName`, `ChatLineReviewer::review`). No consulta registros, así que `consultar(ref_registro)` no existe tal cual. Cómo llega la clasificación al plugin queda pendiente en docs/05.

**Propuesta de contrato del carril de notificaciones** [PROPUESTA, la valida Conce]:

```
NotificationAdapter.emit(evento: EventoSensible) -> ack | error
NotificationAdapter.estado(evento_id) -> pendiente | entregado | error
EventoSensible = { id, arbol_id, ref_registro, tipo_cambio, titular_id, afectada_id | null, detectado_at }
```

`id` es la clave de idempotencia: un reintento del cron no duplica el aviso. El evento no lleva valores del dato (docs/01, sección 5).

---

## 3. Modelo de datos

Almacenamiento propio por integración. Ninguna tabla es compartida entre plataformas.

- `subscriptions(user_id, status, zona_horaria, opt_in_at, baja_at)`
- `profile_snapshot(user_id, calculado_at, perfil, señales)`
- `queue_item(id, user_id, ref_contenido, creado_at, estado)`
- `send_log(user_id, ventana_id, enviado_at, estado)`, con clave única (user_id, ventana_id)
- `sensitive_event(id, ref_registro, tipo_cambio, detectado_at, notificado_at, estado)`, solo en Conce

---

## 4. Casos de uso

- **Suscribirse:** opt-in, zona horaria y alta en `subscriptions`.
- **Darse de baja:** estado a baja y borrado de ítems pendientes.
- **Calcular perfil:** snapshot y cálculo del intervalo efectivo.
- **Acumular contenido:** el proveedor encola novedades.
- **Enviar ventana:** si hay ítems y la ventana venció, se arma y envía el correo.
- **Detectar cambio sensible:** registro, evaluación y, si corresponde, notificación.

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
- Cifras orientativas. Dependen del stack, que aún no está definido.
