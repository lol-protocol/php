# 10. ⚖️ Dilemas Éticos en Gris

**Lectura previa:** [FAQ](./09-faq-common-dilemmas.md)  
**Siguiente:** [Transformación Organizacional](./11-organizational-transformation.md)

---

## 👋 Introducción

No todo es blanco o negro. Hay decisiones donde **ambas opciones tienen costo**. Aquí es cómo navegarlas.

---

## Dilema 1: 📍 Ubicación Real-Time vs. Seguridad

### Escenario
Tu app de viajes/dating quiere ofrecer features útiles:
- **Dating app:** "Ve quién está cerca" (requiere GPS real-time)
- **Viajes:** "Detectar si usuario está en peligro" (requiere ubicación)

Pero GPS = invasión máxima de privacidad.

### Opciones

#### Opción A: No pedir (MÁXIMA PRIVACIDAD)
```
Ventaja:
- Mejor privacidad
- User no siente invasión
- Menor legal risk

Desventaja:
- Pierde feature central (valioso)
- Competencia con GPS gana usuarios
- Producto menos seguro/útil
```

#### Opción B: Pedir GPS siempre (MÁXIMA VALOR)
```
Ventaja:
- Mejor experience
- Features más útiles
- Competitivo

Desventaja:
- Privacy invasiva
- Usuarios incómodos
- Legal risk
```

#### Opción C: HÍBRIDA (BALANCEADA) ✅
```
DATING APP:
- "Ver quién está cerca": Modo ON/OFF por usuario
- Solo guarda ubicación 15 min (expira)
- Nunca se almacena
- Puedes verlo en settings

VIAJES:
- "Detectar peligro": Automático pero:
  - Procesa en-device (no sube a servidor)
  - Se borra inmediatamente
  - User ve qué causó alerta

RESULTADO:
- Features valiosas ✅
- Privacy reasonable ✅
- Legal clean ✅
```

### Decisión
**Opta por Opción C.** Comunica:
> "Queremos que disfrutes sin sentirte observado. Por eso:"

---

## Dilema 2: 🎯 Personalización vs. Profiling

### Escenario
Tienes datos: "Usuario busca X, compra Y, chatea Z"

¿Los usas para personalizar? ¿O esperas permiso explícito?

### Análisis

#### Opción A: Personaliza sin pedir (NO ÉTICO)
```
"User buscó depresión → Mostrar therapy ads"
(Sin que sepa que viste)

Problema: Creepy. Invasivo. User se siente spied.
```

#### Opción B: Pide permiso siempre (COSTO ALTO)
```
"Podemos personalizar si permites tracking"
[Permitir] [No]

Problema: 90% dice No. Pierdes personalización.
```

#### Opción C: TRANSPARENCIA GRADUAL ✅
```
PASO 1: Feature funciona sin tracking
"Aquí hay cursos populares"

PASO 2: Ofrece mejor (con opt-in)
"¿Quieres que te mostremos cursos basados en TU interés?
 [Sí] [No, prefiero genérico]"

PASO 3: Si dice Sí, explica
"Vemos que te interesa: Python, Data
 Por eso te recomendamos: [cursos relevantes]"

PASO 4: Control siempre visible
"No te gustan estas recomendaciones?
 [Cambiar preferencias] [Ver qué datos tenemos]"

RESULTADO:
- Personalización ✅
- Transparencia ✅
- User in control ✅
```

### Decisión
**Opta por Opción C.** El usuario elige cuando ve valor.

---

## Dilema 3: 💰 Monetización sin Anuncios vs. Viabilidad

### Escenario
Tienes app útil (fitness, productividad, finanzas).
Pero:
- Modelo gratuito = no paga servidores
- Anuncios = invasivos
- Paywall = pocos compran

¿Cómo monetizar sin manipular?

### Opciones

#### Opción A: Ads invasivos (problema ético)
```
- Video ads que no puedes skipear
- Personalized ads basado en profiling
- Ads que parecen contenido

Resultado: Revenue ↑, Ética ↓
```

#### Opción B: Freemium sin conversión (no viable)
```
- Feature gratis para siempre
- Pedir donación (muy pocos dan)
- Esperar que paguen (casi nadie)

Resultado: Ética ✅, Revenue ↓ (startup muere)
```

#### Opción C: MODELO ÉTICO + VIABLE ✅
```
OPCIÓN 1: Freemium transparente
- Versión gratis: features core
- Versión paid: features nice-to-have
- Comunica diferencia clara
- Fácil upgrade, fácil downgrade

OPCIÓN 2: Ads éticos
- No personalizados (genéricos)
- Clearly labeled "Ad"
- Skipeable (o menos intrusivos)
- Opción para pagar sin ads

OPCIÓN 3: Datos = monetización
- Vende DATOS AGREGADOS a terceros
- Pero: completely anonymized
- User ve beneficio ("Vendemos insights, eso financia tu app")
- User puede opt-out (entonces pierde feature)

OPCIÓN 4: Hybrid
- Freemium + Ethical ads + Optional data sale
- User elige combo que le gusta

EJEMPLO REAL:
Spotify:
- Gratuito: con ads
- Premium: sin ads
- Ambos funcionales
- Usuarios eligen según necesidad
```

### Decisión
**Opta por Opción C (hybrid).** Comunica cómo ganas dinero.

---

## Dilema 4: 🔓 Retención vs. Libertad

### Escenario
User usó app 30 días. Ahora quiere dejar.
¿Qué haces?

#### Opción A: Dark patterns (BAD)
```
- Cancelación requiere llamar
- Confusing unsubscribe button
- Send emails trying to convince
- Make it hard to delete account
```

#### Opción B: Laissez-faire (INDIFFERENCE)
```
- User quiere irse → OK chao
- No preguntas por qué
- No ofreces mejorar
```

#### Opción C: RETENTION ÉTICO ✅
```
PASO 1: Entiende por qué
"Vemos que no has usado app en 5 días. ¿Hay algo que podemos mejorar?"

PASO 2: Ofrece soluciones
"¿El problema es X? Aquí está cómo lo solucionamos:
 - Feature para X
 - Modo que requiere menos tiempo
 - Tutorial más simple"

PASO 3: Valida y respeta
"Si aún quieres irte, lo entendemos.
 [Pausa temporalmente] [Borrar cuenta]"

PASO 4: Follow-up (opcional)
"Nos encantaría tu feedback:
 ¿Qué nos faltó? [Encuesta 2 min]
 
 Tu respuesta nos ayuda a mejorar para otros."

RESULTADO:
- Algunos regresan (verdadera mejora) ✅
- Otros se van felices (hiciste el intento) ✅
- Feedback para mejorar ✅
```

### Decisión
**Opta por Opción C.** Si alguien se va sabiendo qué ofrecías, la pérdida es legítima.

---

## Dilema 5: 🔁 Algoritmo Recomendador vs. Echo Chamber

### Escenario
Tu algoritmo muestra contenido similar a lo que user consume.
Problema: Crea "bubble" (solo ve un lado).

```
User le gusta: política progresista
Algoritmo muestra: solo progresismo
Resultado: Radicalización, no diversidad

vs.

Algoritmo muestra: mezcla progresismo + visiones alternativas
Resultado: User aprende, piensa mejor
```

### Opciones

#### Opción A: Maximize engagement (PROBLEM)
```
"Mostrar lo que user ya cree → engagement ↑"
Resultado: Polarización, radicalization
```

#### Opción B: Forced diversity (PATRONIZING)
```
"50% matching, 50% opposite views"
Resultado: User odia, abandona app
```

#### Opcion C: INTELLIGENT MIX ✅
```
ALGORITMO:
- 70% content user enjoys (engagement)
- 20% related but slightly different angle (learning)
- 10% alternative views (growth, perspective)

TRANSPARENCY:
"Mostramos contenido que disfrutas +
 contenido que te ayuda a pensar diferente."

CONTROL:
"Prefiero solo lo que me gusta" [Toggle]
[Resultado: se respeta preferencia]

RESULTADO:
- Engagement ✅
- User growth ✅
- Reduced polarization ✅
- User choice ✅
```

### Decisión
**Opta por Opción C.** Engagement sin radicalización.

---

## Dilema 6: 🚪 Early Exit vs. Complete Onboarding

### Escenario
User empieza signup. A mitad de la forma, se va.

¿Presionas para completar? ¿O dejas ir?

#### Opción A: Force completion (DARK PATTERN)
```
- Cant go back (no cancel button)
- Progress bar guilt
- Fields marked "required" unnecessarily
```

#### Opción B: Let them escape (MISSED OPPORTUNITY)
```
- No effort to understand por qué se van
- No offer to help
```

#### Opción C: GENTLE RESISTANCE ✅
```
PASO 1: Notice they're leaving
"¿Hay algo confuso? [Help] [Okay, otro día]"

PASO 2: If they still go
Capture email (if possible):
"¿Te gustaría que te ayudemos por email?"

PASO 3: Follow-up (next day)
"Hola [Name], ¿hay algo que podamos aclarar?"
[Specific help based on where they left]

PASO 4: Respect decision
"Si no es el momento, está bien.
 Vuelve cuando quieras. Tu progreso se guarda."

RESULTADO:
- Some return with better UX ✅
- Others feel respected, vuelven después ✅
- Data to improve onboarding ✅
- No dark pattern ✅
```

### Decisión
**Opta por Opción C.** Presión suave, no fuerza.

---

## Dilema 7: 📣 Influencer Disclosure vs. Authenticity

### Escenario
Influencer ama tu producto. ¿Lo promovería sin pago?
Pero si le pagas, ¿pierde autenticidad?

#### Opción A: No pagues, espera organic (NAIVE)
```
"Si es bueno, lo van a amar gratis"
Resultado: Casi nadie lo menciona
```

#### Opción B: Pay but hide (FRAUD)
```
"Paga influencer pero dice 'lo descubrí naturalmente'"
Resultado: Deceptive, illegal (FTC)
```

#### Opción C: PAY + DISCLOSE ✅
```
APPROACH 1: Honest partnership
"Hey [Influencer], amamos que uses [product].
 ¿Podemos pagarte para hablar de ello?
 (Pero debes ser honesto: es partnership)"

APPROACH 2: Affiliate (transparent)
"Ganas comisión si alguien compra con tu link.
 Divulga el link, el resto es genuine."

APPROACH 3: Long-term ambassador (best)
"Te damos acceso lifetime gratis.
 Si amas, recomienda. Si no, di que no."

DISCLOSURE FORMAT:
"[Ad] Este post es patrocinado"
OR
"Recebo comisión de estos links"
OR
"Soy embajador, pero genuinamente amo esto"

RESULTADO:
- Influencer honesto ✅
- Users saben que es partnership ✅
- Less deceptive, more effective ✅
```

### Decisión
**Opta por Opción C.** Transparencia + pago = mejor relación.

---

## Dilema 8: 🏛️ User Data Request vs. Privacy

### Escenario
Government pide datos de user.
¿Das? ¿Rechazas? ¿Solicitas warrant?

#### Opción A: Comply immediately (PRIVACY VIOLATION)
```
Gobierno pide todo → Das todo
User nunca se entera
```

#### Opción B: Refuse (LEGAL RISK)
```
"No vamos a dar nada"
Resultado: Fines, reputación
```

#### Opción C: LAWFUL BUT PROTECTIVE ✅
```
STEP 1: Get it in writing
"Warrant only, not request"
(Warrant = judge approved, request = just asking)

STEP 2: Minimize
"Aquí está exactamente lo que pediste, nada más"
(No voluntary expansion)

STEP 3: Notify user (if legal)
"Un gobierno pidió tus datos.
 Aquí está lo que dimos:
 [List]"
(Unless ordered to not disclose)

STEP 4: Transparency report
"En Q1 recibimos X requests.
 Complied Y%, partially Z%, refused W%"
(Like Apple, Google do)

RESULTADO:
- Legal compliance ✅
- User privacy protected ✅
- Transparency ✅
- No overreach ✅
```

### Decisión
**Opta por Opción C.** Legal + transparent.

---

## 🧭 Framework: Cómo Decidir en Gris

### 1. Responde esta pregunta
```
"¿Si el user supiera exactamente lo que hago,
 se sentiría traicionado?"

Sí → NO LO HAGAS
No → PROBABLEMENTE OK
```

### 2. Busca Opción C (hybrid)
```
Casi siempre existe third option:
- Transparencia + Value
- Control + Engagement
- Privacy + Personalización
```

### 3. Pregunta al user
```
"¿Qué prefierías?"
(Dale opciones reales)
```

### 4. Mide y ajusta
```
- Implementa decisión
- Mide metrics éticas
- Si users no están contentos, change
```

### 5. Document y communicate
```
"Decidimos esto porque:"
(Comunica reasoning)
```

---

## 🏁 Conclusión

**Los dilemas éticos en gris NO tienen enemy perfect.**

La meta es: **máximo valor + máxima privacidad.**

Eso usualmente requiere:
- 💡 Creatividad (opción C que otros no ven)
- 🤝 Escuchar a users
- 📊 Medir impact
- 🔄 Iterar cuando no funciona

---

**Siguiente lectura:** [Transformación Organizacional →](./11-organizational-transformation.md)

**Relacionado:**
- [Ethical Neuromarketing](./02-ethical-neuromarketing.md) - Principios base
- [FAQ](./09-faq-common-dilemmas.md) - Preguntas comunes
- [Advanced Metrics](./12-advanced-metrics.md) - Medir decisiones
