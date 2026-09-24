# 6. Métodos de Investigación Ética (UX Research)

**Lectura previa:** [Engagement Psychology](./05-user-engagement-psychology.md)  
**Siguiente:** [Implementation Guide](./07-implementation-guide.md)

---

## ¿Por qué Investigación?

### Sin investigación:
```
❌ Diseñas lo que CREES que quieren (90% mal)
❌ Gastas recursos en features incorrectas
❌ Usuarios abandona porque no entendiste necesidad
❌ Compites con suposiciones, no datos
```

### Con investigación:
```
✅ Entiendes QUÉ necesitan (y por qué)
✅ Priorizar correctamente
✅ Diseñar para necesidad real
✅ Competir en productividad, no en trucos
```

---

## 1. UX Research Honesto (No Dark)

### ❌ Dark Research (Invasivo)
```
- Tracker de mouse (qué exactamente ves)
- Grabación de pantalla sin permiso
- Heat maps en páginas privadas
- Análisis de comportamiento para manipular
```

### ✅ Ethical Research
```
- Surveys transparentes
- Entrevistas 1:1
- User testing con consentimiento
- Analytics anónimos y agregados
- Feedback directo
```

---

## 2. Métodos de Investigación

### Método 1: Encuestas (Surveys)

**¿Qué es:** Preguntas escritas a muchas personas.

**Ventajas:**
- Escala rápidamente
- Datos cuantitativos
- Bajo costo

**Desventajas:**
- Baja tasa de respuesta
- Respuestas superficiales
- No sabes por qué respondieron así

**Cómo hacerla bien:**

```
❌ Mal:
"¿Qué te parece nuestra app?"
(Muy vaga, respuestas inútiles)

✅ Bien:
"¿En qué momento se siente confuso al usar nuestra app?"
(Específico, actionable)
```

**Tipos de preguntas:**

```
LIKERT SCALE:
"Qué tan fácil es encontrar [feature]"
1 (Muy difícil) → 5 (Muy fácil)

OPEN-ENDED:
"¿Qué es lo primero que cambiarías?"
[Respuesta libre]

MULTIPLE CHOICE:
"¿Cuál es tu principal desafío?"
A) Tiempo
B) Dinero  
C) Falta de conocimiento
D) Otro
```

**Dónde hacer:**
- Dentro de la app (pop-up breve, 30 seg)
- Email a usuarios (máx 5 min)
- Google Forms / Typeform (fácil)

---

### Método 2: Entrevistas Cualitativas

**¿Qué es:** Conversación 1:1 con un usuario (30-60 min).

**Ventajas:**
- Entiendes el "por qué"
- Descubres necesidades no explícitas
- Context (trabajo, vida, frustraciones)

**Desventajas:**
- Tiempo intensivo (N=20 usuarios es mucho)
- Costoso
- Sesgo del investigador (cómo preguntas influencia respuesta)

**Cómo hacerla:**

```
ESTRUCTURA:
1. Rapport (2 min)
   "Cuéntame sobre ti, ¿qué haces?"
   
2. Background (5 min)
   "¿Cuál es tu relación con [tema]?"
   
3. Deep Dive (20 min)
   "¿Cómo lo usas hoy? Muéstrame"
   [Observar, no juzgar]
   
4. Pain Points (15 min)
   "¿Qué es frustrante?"
   
5. Cierre (5 min)
   "¿Hay algo que olvidé preguntar?"
```

**Tips:**
- Sé curioso, no asertivo
- Escucha más que hablas
- No interrumpas
- "¿Por qué?" es tu herramienta

---

### Método 3: User Testing (Pruebas de Usabilidad)

**¿Qué es:** Pedis a usuarios que completen tareas mientras observas.

**Ventajas:**
- Ves dónde fallan (no dónde creen que fallan)
- Comportamiento real, no reportado
- Feedback rápido sobre cambios

**Desventajas:**
- Ambiente artificial (user sabe que la observas)
- Small sample (5-8 usuarios suficiente)
- Time-consuming

**Cómo hacerla:**

```
TASK:
"Sin decirte cómo hacerlo, ¿puedes:
 - Crear una cuenta
 - Cambiar tu ubicación
 - Cancelar tu suscripción"
 
[Observa en silencio]

CUANDO SE ATASCA:
User: "¿Dónde está el botón?"
Tú: "¿Dónde ESPERAS que esté?"
[No le digas la respuesta]

DESPUÉS:
"¿Qué pensabas que iba a pasar?"
"¿Qué te confundió?"
```

**Herramientas:**
- Zoom + screen share
- User Testing (usertesting.com)
- Maze.co (prototype testing)
- Maze / Figma Integration

---

### Método 4: Analytics (Datos Agregados)

**¿Qué es:** Números sobre cómo usan tu producto.

**Ventajas:**
- Escala infinita
- Patrón real (no comportamiento reportado)
- Puedes ver conversión, churn, etc.

**Desventajas:**
- No sabes "por qué"
- Fácil malinterpretar
- Correlación ≠ Causación

**Métrica es ética si:**
```
✅ Es agregada (no identificable a individuo)
✅ Tiene propósito claro (mejorar producto)
✅ Usuario puede optar (privacy setting)
✅ Data se borra después de X tiempo
```

**Qué medir:**
- [ ] Funnel: ¿En dónde se caen?
- [ ] Retention: ¿Quién vuelve?
- [ ] Feature adoption: ¿Quién usa nueva feature?
- [ ] Time to value: ¿Cuánto tarda en conseguir beneficio?
- [ ] Churn: ¿Cuándo se van?

**Herramientas:**
- Mixpanel / Amplitude (product analytics)
- Google Analytics (web)
- Segment (data pipeline)

---

### Método 5: Feedback Directo

**¿Qué es:** Usuarios te dicen qué piensan (reviews, support).

**Ventajas:**
- Motivación: usuario dice directamente
- Gratis
- Fácil de categorizar (top 10 complaints)

**Desventajas:**
- Sesgo: solo los extremos opinan
- Ruido: reviews fake, trolls
- Superficial: "Mala app" sin por qué

**Cómo usar:**
```
Categoriza:
- Bugs (no funciona)
- UX (confuso)
- Features (falta algo)
- Performance (lento)

Ve trending:
Si 10 personas dicen lo mismo →
Problema real, no outlier
```

---

## 3. Plan de Research: Paso a Paso

### Fase 1: Definir Pregunta
```
"¿Cómo el usuario descubre nuevas features?"
(Específico)

NO: "¿Qué piensan los usuarios?"
(Demasiado vago)
```

### Fase 2: Elegir Método
```
¿Necesito escala? → Analytics
¿Necesito "por qué"? → Entrevistas
¿Necesito validar UI? → User Testing
¿Necesito mucho input? → Surveys
```

### Fase 3: Reclutar Participantes
```
✅ Representativos (no solo tus amigos)
✅ Segmento correcto (users reales)
✅ Voluntarios (incentivize si es posible)
✅ Transparencia (diles qué haces con data)
```

### Fase 4: Ejecutar
```
- Observar (no presionar)
- Tomar notas
- Grabar (con permiso)
```

### Fase 5: Analizar
```
Patrón emergente (no outliers)
Categorizar hallazgos
Priorizar por impacto
```

### Fase 6: Actuar
```
Cambios prioritarios
Roadmap de features
A/B test (validar cambios)
```

---

## 4. A/B Testing Ético

**¿Qué es:** Mostrar 2 versiones a usuarios (50-50) y ver cuál funciona mejor.

### ✅ Ético
```
A/B test UI: ¿Rojo o azul es más claro?
A/B test UX: ¿3 pasos o 4 pasos al checkout?
A/B test messaging: ¿Cuál headline convierte mejor?
```

### ❌ No ético
```
A) Muestra precio real
B) Muestra precio inflado vs. "descuento"
→ Testing manipulación, no mejora
```

### Cómo hacerlo:
```
1. Hipótesis clara:
   "Si cambio botón a rojo, más clics"
   
2. Variante clara:
   A: Azul (control)
   B: Rojo (test)
   
3. Métrica clara:
   "Medir: clicks en X hora"
   
4. Duración:
   Mínimo 100 usuarios per variant
   Mínimo 1 semana (capture variación)
   
5. Análisis:
   ¿B > A con 95% confianza?
   (Si no, mantén A)
```

**Advertencia:** Múltiples tests simultáneos ↑ false positives.

---

## 5. Segmentación y Personalización Ética

### ❌ Invasivo
```
"Muestra precio más alto a gente con dinero"
(Discriminatorio)

"Muestra anuncios basado en búsquedas privadas"
(Invasión de privacidad)
```

### ✅ Ético
```
"Muestra fitness routines según nivel (beginner, intermediate, advanced)"
(Personalización por preferencia, no discriminación)

"Muestra tips en idioma del usuario"
(Localización, no profiling)
```

### Principio
**Segmentación útil para usuario, no explotación.**

---

## 6. Sesgo de Investigador

### Problema
**Tu opinión influencia resultados.**

```
❌ Sesgo en pregunta:
"¿No te parece que nuestro diseño es hermoso?"
(Influye hacia sí)

✅ Neutral:
"¿Qué te parece el diseño?"
```

```
❌ Sesgo en interpretación:
Usuario dice: "Está bien"
TÚ interpreta: "Le encanta" (porque quieres creerlo)

✅ Preguntar más:
"¿Qué específicamente te gustó?"
(Sin asumir)
```

### Cómo evitar:
- Haz que alguien neutral administre
- Script estandarizado (mismas preguntas)
- Graba (revisar después)
- Involucra a otros (no solo tú interpreta)

---

## 7. Tamaño de Muestra

### Regla de Dedo

```
Cualitativo (entrevistas):
5-20 usuarios = patrones emergentes

Cuantitativo (analytics, surveys):
30-100+ usuarios = estadísticas válidas

Cambios de UI:
5 usuarios = 75% de problemas encontrados
```

### No necesitas encuestar 1000 personas.

Con 5-10 entrevistas reales, ves lo que quebanta.
Con 100 en survey, ves tendencias.

---

## Checklist: ¿Tu Research es Ético?

- [ ] ¿Pedí consentimiento? (Informado, explícito)
- [ ] ¿Explico qué hago con los datos?
- [ ] ¿Puedo anonimizar?
- [ ] ¿Los datos se borran después?
- [ ] ¿El usuario puede optar?
- [ ] ¿Hago preguntas sin sesgos?
- [ ] ¿Busco verdad, no validación?
- [ ] ¿Represento bien a usuarios (no solo amigos)?
- [ ] ¿Actúo sobre hallazgos o solo recolecto?

---

## Research en Acción: Caso Real

### Escenario
Startup de fitness quiere saber: ¿Por qué se van a los 30 días?

### Research Plan:

**Semana 1: Analytics**
```
Ver: ¿A qué hora se van? ¿Después de qué acción?
Resultado: Se van entre días 25-30, después de 0 workouts en 3 días
```

**Semana 2: Entrevistas**
```
Entrevistar a 10 usuarios que se fueron
Pregunta: "¿Qué pasó alrededor del día 25?"
Resultado:
- Usuario 1: "Se aburrió de la rutina"
- Usuario 2: "No vio resultados"
- Usuario 3: "La app se volvió confusa"
- Usuario 4: "Viajé, se rompió el hábito"
```

**Semana 3: Validar**
```
A/B test:
A: Rutina actual (same)
B: Rutina renovada cada semana + community

Esperar 30 días
→ B retiene mejor
→ Validado: Cambio funciona
```

**Acción:**
```
Implement B para todos
Retroalimentación: usuario vuelve por variedad + comunidad
```

---

## Herramientas Recomendadas

| Tipo | Herramienta | Costo | Nota |
|------|-----------|-------|------|
| Surveys | Typeform, Google Forms | Gratis | Fácil de usar |
| Entrevistas | Zoom, Calendly | Gratis | Grabar con permiso |
| User Testing | UserTesting.com, Maze | $$ | Rápido feedback |
| Analytics | Mixpanel, Amplitude | $ | Product analytics |
| Feedback | Trustpilot, Intercom | $ | Centralizar feedback |

---

## Conclusión

**Buena research = Mejor producto.**

Sin research, diseñas suposiciones.
Con research, diseñas soluciones.

Los usuarios te dirán qué necesitan.
Tu trabajo es escuchar.

---

**Siguiente lectura:** [Implementation Guide →](./07-implementation-guide.md)

**Relacionado:**
- [All previous documents] - Teoría que validas con research
- [Implementation Guide](./07-implementation-guide.md) - Cómo usar hallazgos
