# 4. 💬 Diseño Conversacional

**Lectura previa:** [UX Design Principles](./03-ux-design-principles.md)  
**Siguiente:** [User Engagement Psychology](./05-user-engagement-psychology.md)

---

## ¿Qué es Diseño Conversacional?

Es diseñar **interacciones humanas** en un contexto digital. Cómo tu producto "habla" con el usuario.

### Conversación natural vs. Interrogatorio
```
❌ Interrogatorio (manipulador):
P: "¿Cuál es tu email?"
P: "¿Cuál es tu edad?"
P: "¿Dónde vives?"
(El usuario siente que está siendo cuestionado)

✅ Conversación (natural):
P: "¿Cómo te llamas? (Para personalizar tu experiencia)"
R: "Juan"
P: "Un gusto Juan, ¿de dónde eres?"
R: "Buenos Aires"
P: "Perfecto, voy a mostrarte opciones para BA"
(El usuario siente que está siendo comprendido)
```

---

## 1. El Tono de Voz

### Define quién eres como marca

**Formal vs. Casual:**
```
❌ Formal/Distante:
"Se requiere ingreso de credenciales de autenticación"

✅ Casual/Cercano:
"Necesitamos tu usuario y contraseña para que entres"
```

**Siempre claridad > estilo**
```
✅ Casual pero claro:
"Oops, ese email no existe en nuestro sistema. ¿Intentas con otro?"

❌ Casual pero confuso:
"¡Nope! Ese no es el correo. 🤷 Ensaya de nuevo."
```

### Guía de tono:
- **Profesional:** Ley, finanzas, seguros
- **Amigable:** Startups, apps de lifestyle
- **Expert:** Educación, tecnología profunda
- **Empático:** Salud, apps terapéuticas

**Importante:** Elige uno y mantente consistente.

---

## 2. Preguntas Abiertas vs. Cerradas

### Preguntas Cerradas
Requieren sí/no o selección de opciones.

```
"¿Quieres hacer ejercicio?"
(Sí / No)
```

**Cuándo usarlas:**
- [ ] Confirmaciones ("¿Seguro que quieres borrar?")
- [ ] Decisiones binarias
- [ ] Para acelerar el flujo

### Preguntas Abiertas
Permiten respuestas largas.

```
"¿Cuál es tu objetivo de fitness?"
(Usuario escribe lo que quiere)
```

**Cuándo usarlas:**
- [ ] Primeras interacciones (conocer al usuario)
- [ ] Feedback
- [ ] Cuando quieres descubrir necesidades

### Combinación inteligente:
```
P: "¿Cuál es tu principal desafío?" (abierta)
R: "No tengo tiempo para ejercitar"

P: "Entendido. ¿Prefieres ejercicio de 15 o 30 minutos?" (cerrada)
R: "15"
(Ahora la conversación avanzó)
```

---

## 3. Contexto y Relevancia

### Regla de Oro
**No preguntes lo que ya sabes.**

```
❌ Malo:
[Usuario hace login]
"¿Cuál es tu nombre?"
[Sistema ya sabe que es María]

✅ Bien:
[Sistema sabe que es María]
"Hola María, ¿cómo estás?"
```

### Anticipación
```
✅ Inteligente:
Usuario abre la app a las 7am
App: "¿Corrida matutina hoy?"
(Anticipó la intención basada en patrón)

❌ Malo:
Cualquier hora: "¿Quieres correr?"
```

### Personalización
```
✅ Buen uso:
Usuario buscó yoga last week
Ahora: "Veo que te interesa yoga, mira esta clase"

❌ Invasivo:
Usuario buscó "síntomas depresión"
Ahora: "¿Tienes depresión?"
(Demasiado asertivo, demasiado privado)
```

---

## 4. Empatía en el Diálogo

### Validar antes de pedir

```
❌ Sin empatía:
User: "Intenté hacer ejercicio pero me dolerá la espalda"
App: "Eso está en tu cabeza, inténtalo"

✅ Con empatía:
User: "Intenté hacer ejercicio pero me dolerá la espalda"
App: "Entiendo, dolor de espalda es serio. 
      Aquí hay ejercicios de bajo impacto.
      ¿Quieres intentar con esos?"
```

### Reconocer frustraciones
```
✅ Bueno:
Error: "La contraseña no coincide.
       (Esto pasa, no es tu culpa)"

❌ Culpabilizador:
Error: "CONTRASEÑA INCORRECTA"
```

---

## 5. Claridad sobre Claridad

### Evita ambigüedad
```
❌ Confuso:
"¿Continuamos?"

✅ Claro:
"¿Quieres hacer otra serie de ejercicios?"
```

### Explica opciones
```
❌ Oscuro:
[Botón A] [Botón B]

✅ Claro:
[Empezar rutina]  [Ver programas disponibles]
```

### Anticipa preguntas
```
❌ Confuso:
"Tu suscripción se renovará"
(¿Cuándo? ¿Cuánto cuesta?)

✅ Claro:
"Tu suscripción se renovará en 3 días por $9.99"
```

---

## 6. Ritmo y Pacing

### No bombardees
```
❌ Agobiante (5 preguntas a la vez):
"¿Nombre? ¿Email? ¿Edad? ¿Ubicación? ¿Teléfono?"

✅ Respirado (paso a paso):
P: "¿Cómo te llamas?"
[Usuario responde]
P: "Gracias Juan, ¿cuál es tu email?"
```

### Velocidad apropiada
```
✅ Para aplicación de dating:
Preguntas rápidas, reacción inmediata
(El usuario quiere rapidez)

✅ Para aplicación de banca:
Confirmaciones, sin prisa
(El usuario quiere seguridad)
```

---

## 7. Manejo de Errores

### El error es oportunidad, no castigo

```
❌ Culpabilizador:
"ERROR: EMAIL INVÁLIDO"

✅ Educativo:
"Ese email parece incompleto. 
 ¿Quisiste decir: juan@email.com?"
```

### Proporciona soluciones
```
❌ Solo problema:
"Ese nombre de usuario está tomado"

✅ Problema + solución:
"Ese nombre de usuario está tomado.
 ¿Te gustaría: juan_2024 o juan_fitness?"
```

### Error a positivo
```
✅ Oportunidad:
"Veo que eres nuevo. Déjame ayudarte a empezar"
(Convierte error en engagement)
```

---

## 8. Flujo de Conversación Natural

### Estructura básica:
```
1. GREETING: Bienvenida, tono establecido
2. CONTEXT: Explica por qué preguntas
3. REQUEST: La pregunta clara
4. ANTICIPATION: Sugiere opciones o recuerda opciones previas
5. CONFIRMATION: Resume lo que entendiste
6. ACTION: Siguiente paso claro
```

### Ejemplo completo:
```
App: "Hola María 👋" (GREETING)

App: "Para personalizar tu experiencia..." (CONTEXT)

App: "¿Cuántos minutos tienes hoy?" (REQUEST)

App: "Tengo rutinas de 15, 30 y 60 minutos" (ANTICIPATION)

User: "15"

App: "Perfecto, voy a mostrarte rutinas rápidas" (CONFIRMATION)

App: "[Mostrar 3 rutinas]" (ACTION)
```

---

## 9. Manejo de Silencios

### El silencio táctico puede ser manipulación

```
❌ Manipulativo (de "Espejo Inverso"):
User dice algo
Asistente: "..."
(Espera incómoda para que hable más)

✅ Ético:
Si necesitas más info, pregunta claramente:
"Para poder ayudarte mejor, ¿me cuentas...?"
```

### Cuándo usar pausa:
- [ ] Entre puntos clave (absorber información)
- [ ] Después de news importante (dejar que procese)
- [ ] Jamás para manipular

---

## 10. Respeta la Privacidad Conversacional

### No asumas
```
❌ Asume:
User menciona "no puedo ejercitar"
App: "Ah, estás fuera de forma"

✅ Pregunta:
User menciona "no puedo ejercitar"  
App: "¿Hay una razón física o de tiempo?"
```

### No presiones
```
❌ Presión:
"Veo que no hiciste ejercicio ayer. ¿Por qué?"

✅ Apoyo:
"Ayer no entrenaste. ¿Hay algo en lo que pueda ayudarte?"
```

### No exhibas datos privados
```
❌ Incómodo:
"Veo que buscaste 'ansiedad'...¿Necesitas ayuda?"

✅ Privado:
Mostrar recursos de salud mental sin mencionar búsquedas específicas
```

---

## Checklist: ¿Tu Conversación es Ética?

- [ ] ¿El tono es consistente y auténtico?
- [ ] ¿Evito preguntas que ya sé la respuesta?
- [ ] ¿Explico por qué pido información?
- [ ] ¿Las preguntas son claras, no ambiguas?
- [ ] ¿Valido antes de asumir o juzgar?
- [ ] ¿No presiono al usuario?
- [ ] ¿Manejo errores con empatía?
- [ ] ¿El flujo es natural, no como interrogatorio?
- [ ] ¿Respeto la privacidad emocional?

---

## Caso de Estudio: Dos Chatbots

### Chatbot ❌ (Manipulador)
```
Bot: "Hola, ¿nombre?"
User: "Juan"
Bot: "¿Email?"
User: "juan@email.com"
Bot: "¿Teléfono?"
User: "No quiero darlo"
Bot: "¿Seguro? Es importante"
[Usuario abandona el flujo]
```

### Chatbot ✅ (Ético)
```
Bot: "Hola Juan 👋 Voy a ayudarte a empezar"
Bot: "Necesito tu email para tu cuenta (puedes cambiar después)"
User: "juan@email.com"
Bot: "Listo, creé tu cuenta. Bienvenido 🎉"
Bot: "¿Te gustaría tu teléfono para notificaciones? (Opcional)"
User: "Ahora no"
Bot: "Sin problema, puedes agregarlo después en configuración"
[Usuario sigue adelante confiado]
```

**Diferencia:** Uno parece interrogatorio. Otro parece conversación.

---

## Conversación vs. Dark Pattern

| Aspecto | Conversación | Dark Pattern |
|---------|-------------|------------|
| **Tono** | Auténtico, empático | Forzado, presionante |
| **Ritmo** | Natural, pausas | Acelerado, urgente |
| **Claridad** | Explica todo | Ambiguo, confuso |
| **Validación** | Reconoce sentimientos | Ignora objeciones |
| **Privacidad** | Respeta límites | Presiona por datos |
| **Resultado** | Usuario confía | Usuario se siente usado |

---

## Herramientas para Diseñar Conversaciones

### 1. Script de conversación
```
Bot: "¿Qué buscas?"
User: [múltiples opciones posibles]
  → Si A: "Entendido, aquí está A"
  → Si B: "Claro, voy a ayudarte con B"
  → Si confuso: "Ayuda: puedo fazer A, B o C"
```

### 2. Mapa de sentimientos
```
Frustración → Ofrecen solución
Confusión → Ofrecen claridad
Entusiasmo → Mantienen momentum
Duda → Ofrecen garantía
```

### 3. Prueba de humanidad
¿Sentiría un usuario que habla con una persona, o con un bot?

---

## Conclusión

El diseño conversacional es como una buena amistad:
- ✅ Te entiende sin que expliques todo
- ✅ Es empático, no asume
- ✅ Respeta tus límites
- ✅ Hace que quieras volver

No es manipulación, es **conexión genuina**.

---

**Siguiente lectura:** [User Engagement Psychology →](./05-user-engagement-psychology.md)

**Relacionado:**
- [UX Design Principles](./03-ux-design-principles.md) - Estructura
- [Research Methods](./06-research-methods.md) - Cómo validar conversaciones
- [Implementation Guide](./07-implementation-guide.md) - Cómo hacerlo
