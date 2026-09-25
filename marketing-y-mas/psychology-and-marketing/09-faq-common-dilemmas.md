# 9. ❓ FAQ: Dilemas y Preguntas Frecuentes

**Lectura previa:** [Templates](./08-templates-and-checklists.md)  
**Siguiente:** [Dilemas Éticos en Gris](./10-gray-area-ethics.md)

---

## ❓ Preguntas Frecuentes

### P1: "Si recolecto menos datos, ¿no pierdo insights sobre mis usuarios?"

**R:** Sí, pierdes *algunos* insights. Pero:

1. **Los datos que necesitas:** Comportamiento observable (qué feature usan, cuándo se van)
2. **Los que no necesitas:** Ubicación exacta, búsquedas privadas, datos biométricos

```
Ejemplo:
❌ Saber: "Juan está en Starbucks a las 3pm"
✅ Saber: "Juan abre la app a las 3pm"

El segundo es suficiente para hacer mejores recomendaciones
sin invadir privacidad.
```

**Principio:** Minimaliza datos *recolectados*, no *análisis*.

Analiza todo lo que tienes. Solo no pidas lo que no necesitas.

---

### P2: "¿Y si competencia usa datos + dark patterns y nosotros no?"

**R:** Corto plazo: Pueden crecer más rápido.  
Largo plazo: Pierden.

**Por qué:**
```
Competencia con dark patterns:
- User acquisition ↑ (inicialmente)
- Retention ↓ (descubren el truco)
- Churn ↑ (se van a alternativas)
- Reputación ↓ (reviews negativas)
- Legal risk ↑ (GDPR, lawsuits)

Tú (ético):
- User acquisition → Lento inicialmente
- Retention ↑ (el producto funciona)
- Churn ↓ (usuarios leales)
- Reputación ↑ (word of mouth)
- Legal risk → Mínimo

Resultado: Ellos crecen rápido → Caen estrepitosamente
Tú creces lento → Creces para siempre
```

**Ejemplos reales:**
- Facebook (dark patterns) → Legislación, confianza destruida
- Notion (privacy first) → Crece sin oscilaciones
- Apple (privacy) → Brand loyalty altísima

---

### P3: "¿Cómo balanceo privacidad con personalización?"

**R:** No es trade-off. Es **datos mínimos + análisis máximo**.

```
DATOS MÍNIMOS:
- Explícitamente pedir, usuario elige
- Guardar solo lo necesario
- Borrar después de X tiempo

ANÁLISIS MÁXIMO:
- Analizar TODO lo que tienes
- Machine learning sobre esos datos
- Patrones agregados, no individuales

EJEMPLO:
Datos: 
✅ "Qué feature usa Juan" (pide explícito)
❌ "Ubicación de Juan" (no lo pides)

Análisis:
✅ ML para recomendar features similares
✅ Cohort analysis (users como Juan usan X después)
❌ Predecir ubicación (no tienes datos)
```

---

### P4: "¿Somos manipuladores si usamos psicología?"

**R:** No. **La diferencia es la dirección:**

```
MANIPULACIÓN:
- Usas psicología CONTRA intención del usuario
- Objetivo: que haga lo que TÚ quieres
- Ejemplo: "Add to cart" gigante, "Remove from cart" escondido

ÉTICA:
- Usas psicología ALINEADA con usuario
- Objetivo: que el usuario alcance SU objetivo
- Ejemplo: "Add to cart" obvio, "Remove" igual de fácil
  porque queremos que decida libremente
```

**Test:** Si el usuario supiera lo que haces, ¿se sentiría traicionado?
- Sí = Manipulación
- No = Ética

---

### P5: "¿Es ético usar FOMO (Fear of Missing Out) si es real?"

**R:** Depende de cómo lo uses.

```
❌ FOMO FAKE:
"Últimas 2 plazas disponibles" (pero siempre hay 2)
"Oferta que termina hoy" (pero mañana igual)

✅ FOMO REAL:
"100 primeros compradores obtienen descuento" (es verdad, 100 es real)
"Evento en vivo ahora" (puedes entrar ahora o verás grabación después)

La diferencia:
- Fake = Deshonesto + daña confianza
- Real = Comunicar honestamente
```

**Si hay escasez real:** Comunícalo honestamente. Es poderoso.  
**Si no hay:** No la inventes.

---

### P6: "¿Qué hago si ejecutivo presiona para 'solo una vez' romper privacidad?"

**R:** No existe "solo una vez". Es pendiente resbaladizo.

```
Día 1: "Vendemos ubicación solo a partner A"
Día 7: "Bueno, partner B es similar"
Día 30: "A todos les vendemos todo"

Un NO claro ahora = 10 NOs después.
```

**Cómo responder:**
```
"Entiendo la presión. Pero:
1. Una breech legal nos cuesta más de lo que ganaría partner
2. Si se entera usuario, pierde confianza (esto sí es permanent)
3. Propongo: ¿Podemos alcanzar meta SIN romper privacidad?

(Dale 1 semana de brainstorm. Casi siempre hay forma.)
```

---

### P7: "¿Cómo explico privacidad a usuarios que dicen 'no me importa'?"

**R:** Sienten que "no les importa" porque:
- No entienden las implicaciones
- No han visto una breech personal
- O realmente no les importa (es válido)

```
OPCIÓN 1: Educación (suave)
"Aquí está por qué datos importan:
- Identity theft
- Price discrimination (pagas más si saben que tienes dinero)
- Manipulation (ads basados en vulnerabilidades)"

OPCIÓN 2: Control
"No tienes que estar de acuerdo. Elige:
- [ ] Máxima privacidad (sin personalizacion)
- [ ] Privacidad media (datos anónimos)
- [ ] Privacidad baja (perfilado completo)"

OPCIÓN 3: Respeto
"Si realmente no te importa, tu decisión."
(Dale la opción, no presiones)
```

La mayoría cambia de opinión cuando entiende.

---

### P8: "¿Está bien guardar datos indefinidamente si están encriptados?"

**R:** No. Encriptación ≠ Retención ilimitada.

```
Por qué no:
1. Breach futuro podría descifrar (tecnología cambia)
2. Data = liability (guardarlo cuesta, es riesgo)
3. User solicita delete → tienes legal obligation
4. GDPR: "Purpose Limitation" (solo guarda mientras necesites)

Mejor:
- Encriptado ✅ + Expira después X tiempo ✅
- Ejemplo: Chat = encriptado + borra en 30 días

Excepciones (GUARDAR LARGO PLAZO):
- Transacciones (compliance)
- Logs de seguridad (auditoría)
- Legal holds (demanda activa)
```

---

### P9: "¿Qué pasa si competencia nos denuncia con reguladores?"

**R:** Si eres ético, reguladores no te sancionan. Más:

```
ESCENARIO 1: Competencia denuncia pero TÚ eres ético
Regulador: Verifica → "Están compliant"
Competencia: Se ve como mala fe
TÚ: Sales más limpios

ESCENARIO 2: Competencia denuncia y TÚ tienes violaciones
Regulador: Verifica → "Tienen issues"
TÚ: Sanctions + reputación dañada

Conclusión: Ser ético es defensa contra estos ataques.
```

**Además:** Reguladores van a investigar a competencia también. Si ellos no son limpios, se descubre.

---

### P10: "¿Cómo motivar equipo a priorizar ética sobre corto plazo?"

**R:** Cambia cómo medís éxito.

```
HOY (métrica corta):
- Conversión
- Revenue MRR
- DAU

AGREGÁ (métrica larga):
- Retention (que vuelvan)
- NPS (que recomienden)
- Churn rate (quién se va)
- Trust score (confianza)
- Lifetime value (valor total usuario)

RESULTADO:
Equipo ve que ética = métrica mejor, no trade-off.

EJEMPLO:
"Si cortamos dark pattern X, conversión cae 5%"
"Pero retention sube 10%"
"Y lifetime value sube 20%"
→ Clarísimo que vale la pena.
```

---

### P11: "¿Podemos A/B test cambios de privacidad?"

**R:** Sí, pero con cuidado.

```
✅ OK TO TEST:
- "¿Label claro ayuda a compliance?" (clarity)
- "¿Checkbox separado mejora consentimiento?" (clarity)
- "¿Dashboard de datos aumenta confianza?" (transparency)

❌ NO TO TEST:
- "¿Sin avisar cambia privacidad?" (unethical)
- "¿Checkbox oculto engaña más?" (unethical)
- "¿Oscurecer configuración ayuda retention?" (unethical)

REGLA: A/B test es OK si AMBAS variantes son éticas.
Si una no lo es, no lo testes, simplemente usa la ética.
```

---

### P12: "¿Qué hago si descubrimos que vendimos data incorrectamente?"

**R:** Acción inmediata. Honestidad total.

```
PASO 1 (24 horas):
- Confirma qué data, a quién, cuándo
- Stop la venta inmediatamente
- Notifica a regulador (obligatorio)

PASO 2 (1 semana):
- Email a affected users: "Descubrimos X, aquí está qué pasó"
- Explicar impacto (no minimizar)
- Decir qué haces para arreglarlo

PASO 3 (2 semanas):
- Cambio técnico para evitar repetición
- Audit completo de otras áreas
- Public transparency report

PASO 4 (Ongoing):
- Follow-up con users
- Compensation si aplica
- 3rd party audit para rebuilding trust

NOTA: La mayoría de usuarios perdonan si:
1. Descubriste TÚ (no un hacker)
2. Fuiste honesto YA
3. Implementaste fix
```

Ejemplos: Apple/Google cuando descubren issues, transparencia = recuperan confianza.

---

### P13: "¿Ético es usar dark patterns si competencia lo hace?"

**R:** No. Dos wrongs no hacen un right.

```
LÓGICA:
- Competencia usa dark patterns
- Eso no significa es ético
- Solo significa ellos son unethical

ESTRATEGIA:
- TÚ no los usas
- Usuarios descubren diferencia
- TÚ te vuelves destino confiable
- Competencia pierde gradualmente

TIMING:
- Corto plazo: Ellos ganan (truco funciona)
- Largo plazo: TÚ ganas (confianza > truco)
```

**Historia:** Aquellos con más integridad usualmente ganan a 10 años.

---

### P14: "¿Cómo medimos ROI de privacidad?"

**R:** No es directo. Pero medís impacto:

```
MÉTRICA | EFECTO PRIVACIDAD
────────────────────────────
NPS    | ↑ (usuarios confían)
Churn  | ↓ (no se van desconfiados)
LTV    | ↑ (vuelven más veces)
Retention | ↑ (enganchados genuinamente)
Word-of-mouth | ↑ (recomiendan)
Brand value | ↑ (percibida como confiable)

CÁLCULO SIMPLE:
1. Mide NPS/Retention/LTV hoy (baseline)
2. Implementa privacy changes
3. Mide después (3 meses)
4. Diferencia = ROI

EJEMPLO:
- NPS: 30 → 45 (+15)
- LTV: $100 → $125 (+25%)
- Total revenue: $1M → $1.25M (+250k)
- Cost of privacy changes: $50k
- ROI: 5x
```

Privacidad no es costo. Es inversión.

---

## 🏁 Conclusión

**Las preguntas "difíciles" casi siempre tienen respuesta clara si aplicas principio:**

> **¿Es ético? = ¿Estaría cómodo si el usuario supiera?**

Si la respuesta es no, no lo hagas. Simple.

---

**Siguiente lectura:** [Dilemas Éticos en Gris →](./10-gray-area-ethics.md)

**Relacionado:**
- [Ethical Neuromarketing](./02-ethical-neuromarketing.md) - Principios
- [Organizational Change](./11-organizational-transformation.md) - Implementar cambios
- [Advanced Metrics](./12-advanced-metrics.md) - Medir progreso
