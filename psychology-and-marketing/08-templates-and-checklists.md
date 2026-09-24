# 8. 📋 Templates y Checklists Prácticos

**Lectura previa:** [Implementation Guide](./07-implementation-guide.md)  
**Volver a:** [Índice Principal](./README.md)

---

## Introducción

Este documento contiene **templates listos para usar** y checklists que puedes copiar, adaptar y implementar directamente en tu producto.

---

## 1. Templates: Comunicación

### 1.1 Email de Cambio de Política (Transparente)

```
Asunto: Actualización importante de privacidad (lo que cambia)

Hola [Nombre],

Queremos ser transparentes contigo. Estamos haciendo un cambio pequeño 
pero importante en cómo manejamos tus datos.

¿QUÉ CAMBIA?
[Descripción específica, lenguaje simple]
Ejemplo: "Ahora guardamos tu ubicación por 7 días en lugar de 30."

¿POR QUÉ?
[Razón honesta]
Ejemplo: "Menos datos = menos riesgo si hay un problema de seguridad."

¿QUÉ HACES TÚ?
Nada. Este cambio se aplica automáticamente.
Si tienes preguntas: [link a FAQ] o responde a este email.

Gracias por confiar en nosotros,
[Nombre + Firma]
```

### 1.2 Notificación en-app de Nuevo Feature

```
Título: [Nueva feature en 3-5 palabras]

Cuerpo:
"Ahora puedes [beneficio en 1 línea].

¿Por qué? [Contexto: qué problema resuelve]

[Ver demo] [Tal vez después]"

Ejemplo:
"Ahora puedes exportar tu data en un click.

Esto te permite hacer backup o moverte a otra app sin perder nada.

[Ver cómo] [Tal vez después]"
```

### 1.3 Respuesta a Feedback Negativo (Constructivo)

```
Gracias por tu feedback: "[cita del feedback]"

Entendemos tu frustración. Aquí está lo que vamos a hacer:

[Si es bug:]
- Reproducimos el problema ✅
- Lo priorizamos para esta semana
- Te notificamos cuando esté arreglado

[Si es feature request:]
- Lo agregamos al backlog
- Aquí te explico por qué no es prioridad YA:
  [Razón honesta: está en roadmap pero otras cosas son urgentes]
- Te notificamos cuando esté planeado

[Si es UX issue:]
- Vemos el punto
- Aquí te explico cómo diseñamos esto:
  [Contexto: por qué así]
- Estamos iterando, tu feedback ayuda

Pregunta: ¿hay algo específico que te gustaría que pruebe?

[Tu nombre + equipo]
```

---

## 2. Templates: Formularios y Onboarding

### 2.1 Formulario de Signup (Ético)

```html
<!-- Paso 1: Lo Esencial -->
<h2>Crear tu cuenta</h2>

<label for="email">
  Email *
  <small>(para tu login, nunca spam)</small>
</label>
<input type="email" id="email" required>

<label for="password">
  Contraseña *
  <small>(mínimo 8 caracteres)</small>
</label>
<input type="password" id="password" required>

<button>Siguiente</button>

---

<!-- Paso 2: Opcional pero Útil -->
<h2>Personalizar tu experiencia (opcional)</h2>

<label for="name">
  Nombre (para personalizarte)
</label>
<input type="text" id="name">

<label>
  <input type="checkbox">
  Tips y noticias relevantes (2-3 veces/mes)
</label>

<label>
  <input type="checkbox">
  Me importa la privacidad, explica cómo la proteges
  <a href="/privacy-simple">↗ Leer</a>
</label>

<button>Crear cuenta</button>

---

<!-- Paso 3: Celebrar -->
<h2>¡Bienvenido! 🎉</h2>

<p>Tu cuenta está creada. Aquí está tu primer paso:</p>

<div class="next-step">
  <h3>Configura tu perfil (2 min)</h3>
  <p>Esto nos ayuda a recomendarte contenido relevante.</p>
  <button>Empezar</button>
  <button secondary>Después</button>
</div>
```

### 2.2 Modal de Permisos (Claro)

```html
<div class="modal">
  <h2>Necesitamos tu ubicación</h2>
  
  <p>¿Por qué?</p>
  <ul>
    <li>✅ Mostrar tiendas cerca tuyo</li>
    <li>❌ NO se vende a terceros</li>
    <li>❌ NO se usa para publicidad</li>
  </ul>
  
  <p style="color: gray;">
    Guardamos tu ubicación por 24 horas.
    <a href="/privacy-location">Más información</a>
  </p>
  
  <button primary>Permitir ubicación</button>
  <button secondary>No, usar código postal</button>
  <button ghost>Más tarde</button>
</div>
```

### 2.3 Flujo de Cancelación (1-Click)

```html
<!-- Opción A: Dentro de Settings -->
<section class="billing">
  <h3>Suscripción</h3>
  
  <p>Plan Actual: Premium ($9.99/mes)</p>
  <p>Próximo cobro: 15 de octubre, 2026</p>
  
  <button class="danger">Cancelar Suscripción</button>
  
  [Si hace click:]
  
  <dialog>
    <h3>¿Seguro que quieres cancelar?</h3>
    
    <p>Perderás acceso a:
    - Feature X
    - Feature Y
    </p>
    
    <p>¿Hay algo que podamos mejorar?</p>
    <textarea placeholder="Tu feedback..."></textarea>
    
    <button class="danger">Sí, cancelar</button>
    <button>Cambiar de idea</button>
  </dialog>
</section>
```

---

## 3. Templates: Conversaciones (Chatbot/Asistente)

### 3.1 Conversación de Onboarding

```
BOT: Hola 👋 Soy Alex, tu asistente.

USER: Hola

BOT: ¿Cuál es tu objetivo principal con nosotros?
     (Puedo ayudarte con: aprender, crear, conectar)

USER: Quiero aprender

BOT: Perfecto. ¿Qué te gustaría aprender?

USER: Programación

BOT: Excelente. ¿Cuál es tu nivel?
     [Principiante] [Intermedio] [Avanzado]

USER: [Principiante]

BOT: Tengo la ruta perfecta para ti.
     Aquí hay 3 cursos que empiezan de cero.
     
     ¿Cuál te atrae más?
     1. "Web basics" (4 semanas)
     2. "Python para todos" (6 semanas)
     3. "Datos y análisis" (8 semanas)

USER: Python

BOT: Excelente elección. Empezamos mañana.
     [Ver calendario] [Empezar ahora]
     
     Una pregunta: ¿prefieres recordatorios por email?
     [Sí] [No]
```

### 3.2 Conversación de Soporte (Escalada Inteligente)

```
USER: Hola, tengo un problema

BOT: Siento escuchar eso. Estoy aquí para ayudarte.
     ¿Cuál es el problema?

USER: No puedo hacer login

BOT: Entendido. Voy a ayudarte a recuperar acceso.
     ¿Recuerdas cuál email usaste?

USER: juan@email.com

BOT: Listo. Acabo de enviar un link para resetear password.
     Revisa tu email (y spam).
     
     [Ya lo recibí] [No lo veo]

USER: No lo veo

BOT: A veces tarda. ¿Hace cuánto lo enviamos?
     [Hace menos de 1 min]
     [Hace varios minutos]

USER: Hace varios minutos

BOT: Puede ser que el email no llegó.
     Voy a pasarte con un humano que puede ayudarte 
     de forma más rápida.
     
     ¿Tienes 2 minutos? (Tiempo de espera: 0 min)
     [Sí, conectarme] [Que me llame después]
```

---

## 4. Templates: Investigación de Usuarios

### 4.1 Script de Entrevista (30 min)

```
OPENING (2 min):
"Hola [Nombre], gracias por tu tiempo. 
Estamos tratando de entender mejor cómo usan
nuestro producto y qué podríamos mejorar.
No hay respuestas correctas o incorrectas."

BACKGROUND (3 min):
"Para empezar, cuéntame sobre ti.
¿Qué haces? ¿Cuál es tu relación con [tema]?"

CURRENT USAGE (10 min):
"¿Cómo usas [producto] hoy?
¿Podrías mostrarme? Quiero ver tu flujo natural."

[Observa, toma notas, no hables]

Si se atasca:
"¿Qué esperabas que pasara?
¿Dónde creías que estaba [función]?"

PAIN POINTS (8 min):
"¿Qué es lo más frustrante?
¿Hay algo que no funcione bien?
¿Hay algo que abandones?"

[Pregunta seguido: "¿Por qué?"]

CLOSING (2 min):
"¿Hay algo que no pregunté pero querés que sepa?
¿Recomendarías esto a un amigo?"

THANK YOU:
"Muchas gracias. Tu feedback es valioso."
```

### 4.2 Template de Encuesta (5 min)

```
PREGUNTA 1 (Comportamiento):
"¿Cuántas veces a la semana usas [producto]?"
☐ Nunca
☐ 1-2 veces
☐ 3-5 veces
☐ Diariamente

PREGUNTA 2 (Satisfacción):
"¿Qué tan probable es que recomiendes 
[producto] a un amigo?"
1 (Nada probable) ← → 10 (Muy probable)
[Slider]

PREGUNTA 3 (Abierta):
"¿Cuál fue el motivo principal para dejar 
de usar [producto]? (Si aplica)"
[Texto libre]

PREGUNTA 4 (Prioridad):
"¿Cuál de estas features te gustaría que 
tengamos?"
☐ X
☐ Y
☐ Z
☐ Otro: ___

CIERRE:
"Gracias por tu feedback. Eres uno de los pocos que lo da."
```

---

## 5. Checklists: Audit de Producto

### 5.1 Privacy Audit Checklist

```
DATOS
☐ Listamos TODOS los datos que pedimos
☐ Para cada dato, explicamos por qué
☐ Para cada dato, sabemos cuánto tiempo lo guardamos
☐ Podemos borrar datos a pedido del usuario
☐ Los datos no se comparten sin permiso explícito

CONSENTIMIENTO
☐ Nada preseleccionado (checkboxes vacíos por defecto)
☐ Granular (no "todo o nada")
☐ Fácil cambiar de opinión después
☐ El "No" es tan fácil como el "Sí"
☐ Usuarios saben qué aceptan exactamente

TRANSPARENCIA
☐ Política de privacidad en lenguaje simple
☐ Términos explicados (no legalese)
☐ Cambios notificados ANTES de implementar
☐ Usuario puede ver qué datos tenemos de él
☐ Usuario puede descargar sus datos

SEGURIDAD
☐ Encriptación en tránsito (HTTPS)
☐ Encriptación en almacenamiento (si es sensible)
☐ Acceso limitado (solo quien lo necesita)
☐ Auditoría de quién accedió a qué
☐ Plan de breach (qué hacer si algo pasa)

CONTROL
☐ Usuario puede cambiar configuración fácilmente
☐ Usuario puede borrar su cuenta (1 click)
☐ Usuario puede exportar datos
☐ Datos se borran cuando dice el usuario
☐ No hay "ghost traps" (imposible dejar)

Puntuación:
__/25 casillas = __% cumplimiento
Meta: 100%
Aceptable: 80%+
```

### 5.2 UX Dark Patterns Audit

```
FRICCIÓN ASIMÉTRICA
☐ "Sí" y "No" son igual de fáciles
☐ Unsubscribe = 1 click
☐ Cancelar = Sí
☐ Cambiar configuración = Rápido

CONSENTIMIENTO
☐ Nada preseleccionado
☐ Lenguaje claro (no legal)
☐ Separo essential de optional
☐ Puedo cambiar de opinión sin penalidad

INFORMACIÓN
☐ Precios visibles ANTES del checkout
☐ Términos accesibles antes de comprar
☐ Sin sorpresas en el último paso
☐ Puedo ver toda mi data

URGENCIA
☐ Sin urgencia fake ("¡Solo 2 disponibles!")
☐ Sin countdown falsos
☐ Sin "ofertas que expiran" que vuelven mañana
☐ Si hay escasez, es REAL

ENGAÑO
☐ Sin testimonios fake
☐ Sin números inventados
☐ Sin comparaciones deshonesta
☐ Sin bots fingiendo ser humanos

Puntuación:
__/20 = __% sin dark patterns
Meta: 100% (0 dark patterns)
Crítico si: <50%
```

### 5.3 Marketing Audit Checklist

```
PROMESAS
☐ Cada claim tiene fuente (puedo verificar)
☐ Diferencio between "proven" y "anecdotal"
☐ No digo "todos" si significa "algunos"
☐ Admito limitaciones

COMPARACIÓN
☐ Comparo con competencia real, no strawman
☐ Muestro ambos lados
☐ No cherry-pick datos
☐ Reconozco fortalezas de otros

TESTIMONIOS
☐ Reviews son reales (nombre, foto, fecha)
☐ Muestro ratings actuales (no highest)
☐ Muestro reviews malas también
☐ Puedo verificar cada testimonio

URGENCIA
☐ Si hay deadline, es REAL
☐ Si hay descuento, es HONESTO
☐ Si hay escasez, es REAL
☐ No uso fake urgency

AUTORIDAD
☐ Los expertos hablan en su especialidad
☐ Reconozco cuando NO soy experto
☐ No uso bata blanca sin ser relevante
☐ Digo quién soy (conflictos de interés claros)

Puntuación:
__/20 = __% ético
Meta: 100% (marketing honesto)
Crítico si: <70%
```

---

## 6. Checklists: Lanzamiento

### 6.1 Pre-Launch Checklist (Cambio Grande)

```
RESEARCH (Semana 1-2)
☐ Entrevistamos 5+ usuarios sobre cambio
☐ Testeamos UI/UX con usuarios reales
☐ Analytics dice qué pasará (baseline)
☐ Tenemos hipótesis clara: "Si X, entonces Y"

DISEÑO (Semana 2-3)
☐ Diseño sigue guía de privacidad
☐ Accesibilidad testeada (WCAG)
☐ Mobile-first (si aplica)
☐ Copy es claro, sin legalese
☐ Error messages son útiles

CÓDIGO (Semana 3-4)
☐ Minimización de datos implementada
☐ Encriptación donde corresponde
☐ Tests unitarios pasan
☐ Code review completado
☐ No hay secrets en el código

DOCUMENTACIÓN
☐ Changelog actualizado
☐ Explicamos por QUÉ cambio
☐ FAQ preparada para preguntas comunes
☐ Team capacitado (si hay support)

COMUNICACIÓN
☐ Mensaje para usuarios (en-app + email)
☐ Explicamos qué cambia (specifics)
☐ Explicamos por qué (contexto)
☐ Timing claro (cuándo entra en vigor)

CONTINGENCIA
☐ Plan de rollback (si algo falla)
☐ Monitoreo 24h primeros 2 días
☐ Chat de soporte alertado
☐ Contacto de emergencia designado

LAUNCH
☐ Rollout gradual (10% → 50% → 100%)
☐ Monitorear: crashes, errores, complaints
☐ Feedback sheet en tiempo real
☐ Decisión: seguir, iterar, o rollback

POST-LAUNCH (1 semana)
☐ Recolectar feedback
☐ Comparar con hipótesis
☐ Documenta qué funcionó/qué no
☐ Próximas mejoras planeadas
```

### 6.2 Communication Timeline (Cambio de Política)

```
T-7 DÍAS:
Comunicación interna: team se entera de cambio
Email interno: "En una semana haremos X porque Y"

T-3 DÍAS:
Email a usuarios: "Cambio importante viene"
Sección FAQ preparada

T-0:
Cambio se implementa
Notificación in-app
Email (repetir)
Social media (si relevante)
Blog post explicando

T+1-7 DÍAS:
Monitorear: complaints, preguntas
Chat/email activo para soporte
Recopilación de feedback
Iteraciones rápidas si hay problemas

T+30 DÍAS:
Review: funcionó como esperábamos?
Documentar learnings
Thankyou post a usuarios que dieron feedback
```

---

## 7. Métricas: Dashboard Ético

### 7.1 Tablero de Privacidad (Mensual)

```
┌─────────────────────────────────────────────────┐
│ PRIVACY DASHBOARD - Septiembre 2026             │
├─────────────────────────────────────────────────┤
│ Data Minimization                               │
│ ├─ Datos guardados: 2.3GB (↓ 15% vs mes pasado)│
│ ├─ Tiempo promedio de retención: 45 días (meta:|
│ └─ Requests de deletion: 127 (3% de usuarios)  │
│                                                 │
│ Consent & Control                              │
│ ├─ % con permisos granulares: 78%  (meta: 90%)│
│ ├─ Users que cambiaron settings: 12% (meta: 5%)
│ └─ Unsubscribe rate: 0.8% (healthy)            │
│                                                 │
│ Transparency                                    │
│ ├─ Privacy policy reads: 2.1K (↑ 40%)          │
│ ├─ Clarity score: 8.2/10 (survey)              │
│ └─ Dark patterns found: 0 (audit)              │
│                                                 │
│ Trust Metrics                                   │
│ ├─ Privacy NPS: 45 (meta: 50)                  │
│ ├─ Recommendation rate: 62% (+2%)              │
│ └─ Complaints about data: 3 (vs 12 mes pasado) │
└─────────────────────────────────────────────────┘
```

### 7.2 Tablero de Engagement (Semanal)

```
┌─────────────────────────────────────────────────┐
│ ENGAGEMENT DASHBOARD - Semana 38                │
├─────────────────────────────────────────────────┤
│ Motivation                                      │
│ ├─ Users que alcanzaron goal: 34% (meta: 50%)  │
│ ├─ Goal clarity: 7.1/10 (encuesta)             │
│ └─ "Why I use this" responses: 89 (sentiment)  │
│                                                 │
│ Retention                                       │
│ ├─ Day 7 retention: 68% (↑ 5%)                 │
│ ├─ Day 30 retention: 42% (↑ 3%)                │
│ └─ Churn rate: 8% (meta: <10%)                 │
│                                                 │
│ Progress                                        │
│ ├─ Users con progreso visible: 71%             │
│ ├─ Feature adoption (new): 34%                 │
│ └─ "I'm making progress": 6.8/10 (survey)     │
│                                                 │
│ Community                                       │
│ ├─ Active in community: 23% (meta: 20%)        │
│ ├─ Recommendations: 156 (↑ 23%)                │
│ └─ User-generated content: 43 posts            │
└─────────────────────────────────────────────────┘
```

---

## 8. Guías Rápidas

### 8.1 Guía: Pedir Permiso (Correctamente)

```
❌ MAL:
"¿Aceptas cookies?" 
[Aceptar] [Configuración]

✅ BIEN:
"Usamos cookies para:
- Recordar tu login (essencial)
- Saber qué contenido funciona (opcional)
- Personalizar anuncios (opcional)

[Essencial + analytics] [Todo] [Solo essencial]"

PRINCIPIOS:
1. Explica QUÉ y POR QUÉ
2. Separa essential de optional
3. Default = mínimo necesario
4. Cambiar después = fácil
5. Lenguaje simple, no legal
```

### 8.2 Guía: Decir "No" a Presión

```
Cuando alguien en tu empresa dice:
"Necesitamos más datos de users para vender mejor"

Responde:
"Entiendo, pero tengamos en cuenta:
1. Más datos = más riesgo de breach
2. Usuarios que descubren → churn
3. Regulaciones (GDPR, etc.) → legal risk
4. Companies con HIGH privacy (Apple) = más rentables

Propuesta: Probemos con MENOS datos.
Si retención sube, implementamos."

[Datos = poder. Honestidad = poder duradero]
```

### 8.3 Guía: Responder a Data Breach

```
PRIMERAS 24 HORAS:
1. Confirmar qué data se escapó
2. Verificar alcance (cuántos usuarios)
3. Notificar a reguladores (si obliga legal)

COMUNICACIÓN (Honesta):
Email a usuarios:
"Descubrimos un problema de seguridad.

QUÉ PASÓ:
[Explicar técnicamente pero simple]

QUIÉN SE VIO AFECTADO:
[X usuarios, Y datos]

QUÉ HACEMOS:
[Acciones reparadoras]

QUÉ PUEDES HACER:
[Pasos del usuario]

No pedimos disculpas vagas. Decimos exactamente qué pasó."

TRANSPARENCIA = Confianza recuperada
Ocultamiento = Confianza destruida
```

---

## 9. Plantilla: Roadmap de Privacidad (2026-2027)

```
Q4 2026 (Octubre-Diciembre):
QUICK WINS (Low effort, high impact)
- [ ] Unsubscribe = 1 click (1 semana)
- [ ] Privacy policy en lenguaje simple (2 semanas)
- [ ] User data dashboard (3 semanas)
- [ ] Mejora de error messages (1 semana)

Q1 2027 (Enero-Marzo):
MEDIUM LIFT
- [ ] End-to-end encryption para chats (6 semanas)
- [ ] Data minimization audit (4 semanas)
- [ ] Consent granular (4 semanas)
- [ ] Audit de dark patterns (2 semanas)

Q2 2027 (Abril-Junio):
STRATEGIC
- [ ] Data deletion tools (8 semanas)
- [ ] Redesign de onboarding (8 semanas)
- [ ] Community transparency report (4 semanas)
- [ ] ISO 27001 certification? (ongoing)

METRICS TO TRACK:
- Privacy NPS (meta: 50+)
- Churn (meta: <10%)
- Trust score (meta: 8/10)
- Dark patterns found (meta: 0)
- Recommendation rate (meta: 65%)
```

---

## 10. Conclusión

Usa estos templates como starting point. **Adapta a tu contexto.** El principio es:

**Transparencia + Respeto + Valor = Confianza duradero**

No es manipulación. Es diseño honesto.

---

**Regresa:** [Índice Principal](./README.md)

**Relacionado:**
- [UX Design Principles](./03-ux-design-principles.md)
- [Conversational Design](./04-conversational-design.md)
- [Implementation Guide](./07-implementation-guide.md)
