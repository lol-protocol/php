# 01 · Especificación de políticas (v0.1, borrador)

Este documento fija las reglas. Si el código contradice este documento, gana el documento.

Convención: lo marcado como **[SUPUESTO]** debe validarse antes de implementar. Lo marcado como **[PROPUESTA]** aún no tiene decisión.

---

## 1. Política por plataforma

| Aspecto | POS | Conce |
|---|---|---|
| Modo de programación | Fecha fija: día 15 de cada mes | Ventana por usuario |
| Intervalo mínimo entre envíos | 1 mes | 7 días |
| Umbral N (máx. envíos por ventana) | 1 | 1 |
| Intervalo efectivo | Fijo | max(intervalo del perfil, intervalo mínimo) |
| Agrupación | Todo el contenido del mes en un correo | Todo lo pendiente en un correo |
| El perfil influye en | Solo contenido | Frecuencia y contenido |
| Carril de notificaciones | No aplica | Sí, en tiempo real |
| Vía urgente | No existe | No existe en el newsletter |

El umbral N es política de plataforma. No se configura por usuario.

---

## 2. Perfiles

### 2.1 Conce (frecuencia)

**[SUPUESTO]** Valores iniciales: se implementan así (decidido el 18-nov-2026) y se calibran con datos de uso.

| Perfil | Regla (último login) | Intervalo |
|---|---|---|
| Activo | Hasta 14 días | 7 días |
| Ocasional | Entre 15 y 90 días | 14 días |
| Latente | Más de 90 días | 30 días |

Señales de contenido que **no** cambian la frecuencia: cantidad de apellidos distintos, tamaño del árbol y ramas con novedades.

### 2.2 POS (contenido)

El perfil no cambia la fecha de envío: solo segmenta el contenido (decidido el 18-nov-2026).

- **Coleccionista:** tiene colección propia con al menos M títulos guardados (M a definir). Recibe bloque de estadísticas de su colección.
- **Explorador:** sin colección propia, con etiquetados o lecturas. Recibe recomendaciones de autores similares.

---

## 3. Cola y agrupación

- Cada contenido nuevo se encola con su ventana objetivo.
- Al vencer la ventana del usuario sale un solo correo con todo lo pendiente.
- Si no hay contenido, no se envía nada.
- No existe vía urgente en el newsletter.
- Si un contenido deja de ser válido antes del envío (por ejemplo, el apellido se elimina del árbol), se retira de la cola.
- Un cambio de perfil no altera los ítems ya encolados. Aplica desde la siguiente ventana.

---

## 4. Suscripción

- Alta por opt-in explícito con doble confirmación por correo (decidido el 18-nov-2026).
- Baja en un clic desde cualquier correo, con efecto inmediato. Se eliminan los ítems pendientes de ese usuario en esa plataforma.
- Sin suscripción activa no hay newsletter, aunque existan novedades.
- La desuscripción es siempre por plataforma. No hay conflicto, porque no hay integración simultánea.

---

## 5. Carril de notificaciones (solo Conce)

- **Alcance:** cambios en campos sensibles de una persona del árbol.
  **[SUPUESTO]** Lista de campos: nombre legal, fechas de nacimiento y defunción, filiación, adopción, notas biográficas, cualquier campo marcado como sensible, y las categorías salud, origen étnico o racial, creencias o religión, vida sexual u orientación, y opiniones políticas (ampliada el 18-nov-2026, y con opiniones políticas el 20-nov-2026, a partir de PRIVACIDAD-Y-HONRA.md). Falta validarla.
- **Clasificación:** el submódulo de difamación entrega datos (registros marcados, disputas). El plugin decide si notifica.
- **Destinatarios:** titular del árbol siempre, y persona afectada si tiene cuenta (decidido el 18-nov-2026).
- **Fail-closed:** si el submódulo no responde o entrega datos incompletos, el evento se trata como sensible.
- **Tiempo real:** no pasa por cola, ventana, umbral ni agrupación.
- **Contenido neutral:** el aviso indica que hubo un cambio y enlaza a la ficha. No reproduce el valor del dato.
- **Trazabilidad:** cada evento se registra antes de llamar al carril.

---

## 6. Privacidad en el correo

- El newsletter muestra apellidos y conteos, nunca nombres de terceros ni datos sensibles.
- El bloque familiar muestra apellidos compartidos y conteos, sin identificar personas.

---

## 7. Zona horaria

- Las ventanas se calculan en la zona horaria del perfil del usuario.
- El cron corre cada hora en UTC y evalúa qué ventanas vencieron.
- POS: envío el día 15 a las 09:00, hora de Santiago. **[SUPUESTO]** No hay fecha firme para el primer envío (decidido el 18-nov-2026).

---

## 8. Idempotencia y no envío

- Un envío por usuario y por ventana. La clave única (usuario, ventana) está en `send_log`.
- Volver a ejecutar el cron no duplica envíos.
- Los rebotes definitivos no se reintentan y el correo se marca como inválido.

---

## 9. Manejo de errores

- **Correo falla:** reintento en el siguiente ciclo, máximo 3 intentos. **[SUPUESTO]**
- **Proveedor de contenido falla:** no encola ni envía, y registra el error.
- **Notificación falla:** `sensitive_event` queda pendiente y se reintenta en el cron.
- **Submódulo falla:** fail-closed según la sección 5.
