# 12. 📊 Métricas Avanzadas de Ética

**Lectura previa:** [Transformación Organizacional](./11-organizational-transformation.md)  
**Siguiente:** [Dilemas Avanzados](./13-advanced-dilemmas.md)

---

## 👋 Introducción

Medir es lo que haces. Lo que no mides, no haces.

Por eso necesitas métricas que **premien ética, no solo crecimiento.**

---

## 1️⃣ Nivel 1: Métricas Fundamentales

### 1.1 Privacy Score (0-100)

**Qué mide:** Cuánto respetas privacidad del usuario.

```
CÁLCULO:

Data Minimization (25 puntos)
- [ ] Solo pedís datos que necesitas (5 pt)
- [ ] Guardás por el tiempo necesario (5 pt)
- [ ] Permites borrar/exportar (5 pt)
- [ ] No compartes sin consentimiento (5 pt)
- [ ] Encripción en almacenamiento (5 pt)

Transparency (25 puntos)
- [ ] Privacy policy en lenguaje simple (7 pt)
- [ ] Usuario ve qué data tienes (6 pt)
- [ ] Notificación de cambios ANTES (6 pt)
- [ ] Términos accesibles (6 pt)

Consent (25 puntos)
- [ ] Nada preseleccionado (8 pt)
- [ ] Granular (no all-or-nothing) (8 pt)
- [ ] Fácil cambiar de opinión (9 pt)

Control (25 puntos)
- [ ] Cambiar settings es rápido (8 pt)
- [ ] Cancelar suscripción = 1 click (8 pt)
- [ ] Borrar cuenta = fácil (9 pt)

SCORING:
0-40: Crisis (urgente)
41-60: Problematic (mejora pronto)
61-80: Good (refinar)
81-100: Excellent (mantener)
```

### 1.2 Trust Score (1-10)

**Qué mide:** ¿Confían en ti?

```
ENCUESTA (enviada mensualmente):

"¿Cuánto confías en que respetamos tu privacidad?"
[1] [2] [3] [4] [5] [6] [7] [8] [9] [10]

"¿Crees que vendemos tus datos?"
[Sí] [No] [No sé]

"¿Nos recomendarías a un amigo?"
[Seguro no] ← → [Seguro sí]

SCORE:
Average de respuesta 1 = Trust Score
Target: 7+ es healthy
8+ es excellent
Tracking: Mejora o degrada

Si cae:
- Por qué? (encuesta abierta)
- Qué pasó? (audit)
- Arreglar (acción)
```

### 1.3 Net Promoter Score Ético (NPS+)

**Qué mide:** ¿Recomendarían?

```
ESTÁNDAR NPS:
"¿Recomendarías a un amigo?"
[0] ← → [10]

ETHICAL NPS:
"¿Recomendarías porque confías en privacidad?"
[0] ← → [10]

DIFERENCIA:
Si NPS = 50 pero eNPS = 30:
→ Recomiendan por feature, no trust
→ Problema: cuando hay alternativa confiable, se van

Si ambos = 50:
→ Trust + Feature = loyal
→ Mejor retención
```

---

## 2️⃣ Nivel 2: Métricas de Engagement

### 2.1 Engagement Genuino (% de feature adoption sin notifs)

**Qué mide:** ¿Usan porque quieren vs. porque presionamos?

```
CÁLCULO:

Feature adoption con notificaciones: X%
Feature adoption sin notificaciones: Y%

Genuine engagement = Y%
Forced engagement = X% - Y%

INTERPRETACIÓN:
Si Y es alto (80%+ adopt sin notif):
→ Feature es genuinamente útil
→ Engagement es genuine

Si X es alto pero Y es bajo (notif driving):
→ Users no aman feature
→ Engagement es artificial
→ Dark pattern territory
```

### 2.2 Retention Sin Addiction

**Qué mide:** ¿Vuelven porque quieren o porque están atrapados?

```
MÉTRICA 1: Voluntary usage (quién usa sin notificaciones)

MÉTRICA 2: Churn reason (por qué se van)

ANÁLISIS:
"Encontré alternativa mejor" = Healthy churn
"El app me estresa" = Addiction churn
"No me gustó" = Feature churn

TRACKING:
Month 1 active: 100%
Month 2 active: 75%
  - Healthy churn (25% cambió idea): 18%
  - Addiction churn (se estresaba): 4%
  - Feature churn (falta algo): 3%

INSIGHT:
Más healthy churn = más genuine engagement
```

### 2.3 Habit Formation Metric (Propósito > Addiction)

**Qué mide:** ¿Hábito porque lo aman o porque manipulamos?**

```
FRAMEWORK DE FOGG:

Usuarios con hábito: X%
  ├─ Porque les aporta valor: Y% ← (good)
  └─ Porque dark patterns: Z% ← (bad)

CÁLCULO:
- Survey: "¿Por qué usas?"
  [Porque me aporta] vs [Por notificaciones]
- Tracking: quiénes usan SIN notifications
- Churn test: disable notifications → qué pasa

HEALTHY RATIO:
80%+ genuine habit = excellent
50-80% = okay, mejora
<50% = problem
```

---

## 3️⃣ Nivel 3: Métricas de Impacto Ético

### 3.1 Dark Patterns Index (DPI)

**Qué mide:** ¿Cuántos dark patterns tienes?

```
AUDIT TRIMESTRAL:

Cada uno que encontramos:
- Severity (critical / major / minor)
- Location (donde)
- Fix status (open / in progress / done)

DPI Score:
0 = Perfect (goal)
1-2 = Excellent
3-5 = Good (watch list)
6+ = Problem (urgent)

TREND:
If DPI ↓ every quarter: Progreso
If DPI ↑ every quarter: Regresión
If DPI stable: Not improving

TARGET:
Reach 0 by end of year
Maintain 0 thereafter
```

### 3.2 Regulatory Compliance Score (RCS)

**Qué mide:** ¿Cumples regulaciones?

```
GDPR COMPLIANCE (25 puntos)
- Consent mechanism (7)
- Right to access (6)
- Right to deletion (6)
- Data portability (6)

CCPA/CPRA (25 puntos)
- Disclosure (7)
- Opt-out (6)
- Deletion (6)
- Non-discrimination (6)

INTERNAL PRACTICES (25 puntos)
- Privacy by design (8)
- Data minimization (8)
- Breach notification (9)

THIRD PARTIES (25 puntos)
- Vendor compliance (8)
- Subprocessor agreements (8)
- Data Processing Agreements (9)

SCORE:
0-60: Non-compliant (legal risk)
61-80: Partial (vulnerabilities)
81-95: Compliant (good)
96-100: Excellent (exemplary)
```

### 3.3 Ethical Revenue Ratio (ERR)

**Qué mide:** ¿De dónde viene el dinero?

```
CATEGORIZE REVENUE:

ETHICAL SOURCES:
✅ Subscriptions (users paid for value)
✅ Freemium upgrade (chose premium)
✅ Affiliate (disclosed partnerships)
✅ Data sales (anonymized, consented)

QUESTIONABLE:
⚠️ Ads (ethical but intrusive)
⚠️ Sponsored features (clearly labeled)

UNETHICAL:
❌ Selling private data
❌ Dark pattern conversion
❌ Manipulative pricing

CÁLCULO:
Ethical revenue: $X
Total revenue: $Y
ERR = X/Y

TARGET:
>80% from ethical = good
>90% = excellent
<70% = problem
```

---

## 4️⃣ Nivel 4: Métricas de Valor de Usuario

### 4.1 User Value Achievement (UVA)

**Qué mide:** ¿El usuario logró lo que vino a lograr?**

```
DEFINE USER GOAL:
"Why did they come?"
Examples:
- Fitness app: "Perder 5kg en 3 meses"
- Learning: "Aprender Python"
- Social: "Conectar con amigos"

TRACK:
- Did they state goal? (30 days)
- Did they achieve it? (90 days)
- How much? (80%+, 50-80%, <50%)

UVA = (users who achieved ÷ total) × 100

INTERPRETATION:
>70% = Excellent (product delivers)
50-70% = Good (most get value)
<50% = Problem (doesn't help)

NOTE:
UVA > Retention
Even if some churn after achieving goal, 
goal achievement > addiction
```

### 4.2 Quality of Life Improvement (QLI)

**Qué mide:** ¿La vida del usuario mejoró?

```
SURVEY (post-use, 30 days):

"Gracias al app:"
- Mi energía: ↓ ↔ ↑
- Mi estrés: ↓ ↔ ↑
- Mi productividad: ↓ ↔ ↑
- Mi relaciones: ↓ ↔ ↑
- Mi felicidad: ↓ ↔ ↑

SCORING:
↑ = +1, ↔ = 0, ↓ = -1
Score = sum / 5

TARGET:
>0 = net positive
>0.5 = strong positive
<-0.2 = harmful (reconsider)

ALARM:
If QLI negative, app is HURTING users
This overrides all other metrics
Fix or discontinue feature
```

---

## 5️⃣ Nivel 5: Métricas de Sostenibilidad

### 5.1 Business Sustainability Ratio (BSR)

**Qué mide:** ¿Puedes mantener ética y ser rentable?

```
FÓRMULA:

BSR = (LTV - CAC) × Retention Rate
      ─────────────────────────────
      Cost of Ethical Implementation

LTV = Lifetime Value (cuánto gana por user)
CAC = Customer Acquisition Cost
Retention = % que vuelven
Cost of Ethical = costo de medir + cumplir privacidad

INTERPRETATION:
>1.5 = Sustainable (ética es viable)
1.0-1.5 = Viable pero tight (optimizar)
<1.0 = Unsustainable (model broken)

If <1.0:
- Raise prices? (ethical premium)
- Reduce CAC? (better targeting)
- Improve retention? (ethical engagement)
- OR: model is broken, pivot
```

### 5.2 Regulatory Risk Score (RRS)

**Qué mide:** ¿Qué riesgo legal tienes?

```
ASSESSMENT (0-10 scale):

Data breaches (0-2):
0 = never, 2 = frequent

Compliance violations (0-2):
0 = none, 2 = multiple

Privacy complaints (0-2):
0 = none, 2 = many

Dark pattern violations (0-2):
0 = none, 2 = many

Transparency issues (0-2):
0 = clear, 2 = opaque

RRS TOTAL: __ / 10

INTERPRETATION:
0-2 = Low risk (safe)
3-5 = Medium risk (watch)
6-8 = High risk (urgent)
9-10 = Critical (lawsuit likely)

If >5:
- Audit immediately
- Fix top issues
- Recompute monthly
```

---

## 6️⃣ Nivel 6: Dashboards Integrados

### 6.1 Quarterly Business & Ethics Report

```
┌──────────────────────────────────────────────────────┐
│ Q3 2026 REPORT: Business + Ethics                    │
├──────────────────────────────────────────────────────┤
│                                                      │
│ BUSINESS METRICS                                    │
│ ├─ Revenue: $250K (+15% vs Q2)                     │
│ ├─ Users: 50K (+10%)                               │
│ ├─ Churn: 8% (↓ from 10%)                          │
│ └─ LTV/CAC: 3.2x (healthy)                         │
│                                                      │
│ ETHICAL METRICS                                     │
│ ├─ Privacy Score: 72/100 (target: 100)             │
│ ├─ Trust Score: 7.2/10 (healthy)                   │
│ ├─ Dark Patterns: 2 (↓ from 5)                     │
│ ├─ RCS: 85/100 (compliant)                         │
│ └─ ERR: 92% (ethical revenue)                      │
│                                                      │
│ CORRELATION                                         │
│ ├─ Privacy ↑ → Churn ↓ (causation?)               │
│ ├─ Trust ↑ → Recommendation ↑                      │
│ ├─ DPI ↓ → Brand sentiment ↑                       │
│ └─ UVA 78% (users achieve goals)                   │
│                                                      │
│ ACTION ITEMS                                        │
│ ├─ [ ] Fix remaining 2 dark patterns               │
│ ├─ [ ] Improve privacy score from 72 → 85         │
│ ├─ [ ] Audit 3rd party compliance                  │
│ └─ [ ] Expand ethical revenue streams              │
│                                                      │
└──────────────────────────────────────────────────────┘
```

### 6.2 Red Flags (Alert System)

```
IF ANY OF THESE DROP:

🔴 CRITICAL ALERT:
- Trust Score drops >1 point month-over-month
  → Investigate immediately
- Data breach: 0 → 1+
  → Execute breach protocol
- Dark Pattern Index: 2 → 5
  → Pause launches, audit

🟡 WARNING:
- Privacy Score drops >5 points
  → Review what changed
- RCS drops below 80
  → Compliance risk
- UVA drops >10%
  → Product not delivering

🟢 MONITOR:
- Churn increases 1%
  → Normal variation, track trend
- LTV/CAC drops slightly
  → Monitor, don't panic
```

---

## 7️⃣ Nivel 7: Advanced Analysis

### 7.1 Ethical Elasticity

**Qué mide:** Cómo cambian metrics cuando mejoras ética?

```
HYPOTHESIS:
"Privacy improvements → Higher trust → Lower churn"

TEST:
1. Baseline: Privacy score 60, Trust 6, Churn 15%
2. Improvement: Remove 3 dark patterns
3. New Privacy score: 75, Trust 7.5, Churn 12%
4. Elasticity: (12-15)/15 = -20% churn per +15 pts privacy

INTERPRETATION:
-20% elasticity = strong effect
Small privacy improvements = big churn reduction
Worth doing
```

### 7.2 Competitive Positioning Matrix

```
          HIGH PRIVACY
          │
    OURS  │    IDEAL
          │
          ├─────────────→ REVENUE
    THEM  │    (compromise)
          │
          LOW PRIVACY

Position yourself in IDEAL quadrant:
- High privacy ✅
- High revenue ✅
- Competitors in low privacy / high revenue

Over time: Their churn ↑, your retention ↑
Market shifts to you
```

---

## 🛠️ Cómo Implementar Este Sistema

### Fase 1: Pick Top 5 Metrics

Don't do all at once. Start with:
1. Privacy Score (foundational)
2. Trust Score (user perception)
3. Dark Patterns Index (track cleanness)
4. Retention (business health)
5. User Value Achievement (impact)

### Fase 2: Set up Dashboard

Tool options:
- Mixpanel / Amplitude (product metrics)
- Google Sheets (manual tracking)
- Grafana (engineering metrics)

### Fase 3: Review Cadence

- Weekly: DPI, anomalies
- Monthly: Privacy score, trust
- Quarterly: Full report

### Fase 4: Take Action

If metrics decline:
- Investigate why
- Fix root cause
- Recompute
- Repeat

---

## 📝 Resumen: Métrica Fundamental

Si solo tracked UNA métrica:

**User Value Achievement (UVA)**

> "Of 100 users who came with a goal, how many achieved it?"

Everything else flows from this:
- Genuine engagement
- Sustainable business
- User trust
- Ethical differentiation

Optimize for UVA + Trust, other metrics follow.

---

**Siguiente lectura:** [Dilemas Avanzados →](./13-advanced-dilemmas.md)

**Relacionado:**
- [Implementation Guide](./07-implementation-guide.md)
- [Organizational Transformation](./11-organizational-transformation.md)
- [Gray Area Ethics](./10-gray-area-ethics.md)
