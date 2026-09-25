# 15. ✅ Checklist de Lanzamiento: Antes de Ir a Producción

**Lectura previa:** [Mentalidad del Usuario](./14-user-psychology-deep.md)  
**Siguiente:** [Conflictos Internos](./16-internal-conflicts.md)

---

## Introducción

Un lanzamiento exitoso no es suerte. Es sistema.

Esta checklist te asegura que **nada se olvida** y que lanzan con **confianza ética**.

---

## Fase 0: Pre-Launch (2 Semanas Antes) 🚀

### 0.1 Product Readiness

```
□ Feature completado y testado
□ Todos los bugs críticos fixed
□ Performance testeado (no crashes con 1000 users simultáneos)
□ Mobile responsivo (testear en 3+ devices)
□ Loading time <3 seg en 4G lento
□ Accessibility check (keyboard navigation, screen reader)
□ Copy revisada (grammar, tone consistent, sin jerga)
□ Links internos/externos working
□ Forms auto-save (no pierda data)
□ Offline mode (si aplica)
□ Error messages claros (no technical jargon)
□ Analytics instrumentado (pero privacy-respecting)
□ Logging en lugar seguro (PII filtrado)
```

### 0.2 Privacy & Security

```
□ Privacy impact assessment completado
□ Data classification (which data is sensitive)
□ Encryption en transit (HTTPS everywhere)
□ Encryption at rest (database encrypted)
□ Access controls (who can see what)
□ Audit logging (track all data access)
□ Data retention policy (how long we keep)
□ Data deletion implemented (users can delete)
□ Third-party vendors vetted (privacy agreements)
□ Breach protocol in place (what if goes wrong)
□ Privacy policy updated (and reviewed by legal)
□ Terms of service updated
□ Consent mechanism working (GDPR/CCPA compliant)
□ Opt-out functional (not just opt-in)
□ No dark patterns in consent flow
```

### 0.3 Compliance

```
□ GDPR (if EU users)
□ CCPA/CPRA (if US users)
□ COPPA (if kids <13)
□ HIPAA (if health data)
□ PCI-DSS (if payments)
□ Accessibility (WCAG 2.1 AA)
□ Country-specific laws (know your geography)
□ Export controls (no restricted content)
□ Content moderation policy (if user-generated)
□ Terms reviewed by legal counsel
□ Insurance coverage (cyber liability)
```

### 0.4 Communication Plan

```
□ Announcement drafted (blog/email/social)
□ Timeline communicated (when exactly)
□ What's new documented
□ Known limitations disclosed (transparency)
□ Support plan ready (how users will get help)
□ FAQ prepared (anticipate questions)
□ Help docs written (tutorials, walkthroughs)
□ Video tutorial (optional but recommended)
□ Status page ready (communicate if goes down)
□ Rollback plan written (what if fails)
□ Monitoring dashboard set up
```

---

## Fase 1: Technical Launch (Day 0) 🔧

### 1.1 Pre-Launch Checks (4 Hours Before)

```
□ Team standup: everyone knows their role
□ Database backed up (full backup before deploy)
□ Monitoring alerts set (CPU, RAM, errors, latency)
□ Slack/PagerDuty configured (who gets notified)
□ Runbook written (how to rollback in 5 min)
□ Feature flag ready (can disable if needed)
□ Deployment script tested (run it dry-run first)
□ Staging environment matches production
□ Load testing done (can handle expected traffic)
□ Dependency versions pinned
□ Environment variables checked
□ Secrets not in code (use secret manager)
□ Last security scan passed
```

### 1.2 Launch Execution (Deployment)

```
□ Deploy to canary (5% of users)
□ Monitor for 15 minutes (error rate, latency, crashes)
□ If OK → deploy to 50%
□ Monitor for 15 minutes
□ If OK → deploy to 100%
□ If NOT OK → rollback immediately (feature flag)

MONITORING WHILE DEPLOYING:
□ Error rate normal (<0.1%)
□ Response latency normal (<300ms p95)
□ CPU/RAM not spiking
□ Database connections stable
□ Queue processing normal (if applicable)
□ External API calls working
□ Third-party integrations responsive
□ User session creation working
□ Authentication/authorization working
```

### 1.3 Post-Launch (First Hour)

```
□ Announcement sent (email, blog, social)
□ Team monitoring active (someone watching)
□ Support team on alert (ready for issues)
□ First user feedback collected
□ Metrics dashboard live
□ Error tracking working
□ Customer support tools ready
□ Executive dashboard updated
```

---

## Fase 2: Quality Assurance (Launch Day) ✔️

### 2.1 Smoke Testing (First 1 Hour)

```
□ Core feature works end-to-end
□ Sign up/login working
□ Primary action works
□ Payment processing (if applicable)
□ Search/discovery working
□ Filters/sorting working
□ Edit/update workflow
□ Delete/removal workflow
□ Share functionality
□ Notifications working
□ Email notifications received
□ Settings/preferences working
□ Account page accurate
□ Data displaying correctly
```

### 2.2 User Acceptance Testing

```
□ Sample of 10-20 actual users tested
□ They could complete core workflow without help
□ They found the feature discoverable
□ They understood the value
□ No crashes or obvious bugs
□ Performance acceptable
□ Error messages made sense
□ Positive feedback collected
□ Issues documented (severity level)
□ Critical issues: fix immediately
□ Major issues: fix within 24h
□ Minor issues: add to backlog
```

### 2.3 Security Testing (Launch Day)

```
□ SQL injection tested (common inputs)
□ XSS tested (common payloads)
□ CSRF token present
□ Session handling correct
□ Permissions enforced (can't access others' data)
□ Rate limiting in place (prevent abuse)
□ No sensitive data in logs
□ No secrets leaked in error messages
□ API endpoints authenticated
□ No information disclosure
□ File uploads validated
□ Input validation working
```

---

## Fase 3: Communication & Support (Days 1-7) 📢

### 3.1 Day 1 Announcement

```
□ Blog post published (technical details)
□ Email sent to users (what's new + how to use)
□ Social media posts (Twitter, LinkedIn, etc)
□ In-app notification (new users see feature)
□ Help center updated (docs live)
□ FAQ page live
□ Slack/community notified
□ Press release (if significant)
□ Investor update (if applicable)
```

### 3.2 First Week Support

```
□ Support team trained (know feature deeply)
□ FAQ updated with real questions
□ Help docs improved based on user questions
□ Common issues documented
□ Video tutorial updated (if applicable)
□ Onboarding flow adjusted based on confusion
□ Bugs found = prioritized and fixed
□ User feedback collected (survey, interviews)
□ NPS tracked (baseline for this feature)
□ Performance monitored (any degradation?)
□ Error rate tracked (any spike?)
□ Crash reports reviewed
□ Session analytics reviewed (how users use)
```

### 3.3 Week 2-4 Monitoring

```
□ Feature adoption tracking
□ Retention of new users
□ Engagement metrics healthy
□ No regression in other features
□ Dark patterns check (did we add any?)
□ Privacy audit passed
□ Compliance still in place
□ User sentiment (are they happy?)
□ Comparison to baseline metrics
□ Roadmap impact understood
```

---

## Fase 4: Ethical Audit (Before + After) 🧭

### 4.1 Pre-Launch Ethical Review

```
□ Does feature respect user autonomy? (users in control)
□ Could feature be addictive? (red flags?)
□ Any dark patterns? (acknowledge if yes, fix them)
□ Is data collection minimal? (only what needed)
□ Is transparency clear? (user knows what happens)
□ Can users easily opt-out? (no trap)
□ Does it align with company values? (check mission)
□ Would we be proud to defend this? (honest test)
□ Is there potential for harm? (edge cases)
□ Have we consulted users? (did they want this?)
□ Could vulnerable users be affected? (children, elderly)
□ Is the business model ethical? (how we make money)
```

### 4.2 Post-Launch Ethical Monitoring

```
□ User wellbeing impact tracked (QLI survey)
□ Unintended negative effects identified
□ Addiction patterns emerging? (users report stress)
□ Privacy breaches? (monitoring for incident)
□ Compliance violations? (automated checks)
□ User complaints analyzed (what are they saying?)
□ Dark patterns emerging? (vigilant monitoring)
□ Vulnerable groups affected? (specially monitor)
□ Long-term impact understood (doesn't just measure week 1)
```

---

## Fase 5: Metrics & Analytics (Ongoing) 📊

### 5.1 Feature Adoption

```
□ Users who saw feature: X
□ Users who activated: Y
□ Activation rate: Y/X = ?

□ Target: 40%+ activation within first week
□ If <40%: investigate why (discoverability? value unclear?)
```

### 5.2 Engagement Quality

```
□ Users return: X%
□ Users with "aha moment": Y%
□ Average time in feature: Z min

□ Without notifications: A%
□ With notifications: B%

□ If A is high (80%+): genuine engagement
□ If B > A (forced engagement): might be problem
```

### 5.3 Retention

```
□ Day 1 retention: _%
□ Day 7 retention: _%
□ Day 30 retention: _%

□ Trend over weeks:
  Week 1: 100%
  Week 2: 65%
  Week 3: 45%
  Week 4: 35%

□ Is churn accelerating? (early sign of problem)
□ Is retention stable? (sustainable)
```

### 5.4 User Satisfaction

```
□ NPS score (0-10): __
□ "Would recommend": _%
□ "Solves my problem": _%
□ "Easy to use": _%
□ "Trust this feature": _%
```

### 5.5 Revenue Impact (If Applicable)

```
□ Feature monetizes: Y/N
□ If Yes:
  □ Revenue per user: $__
  □ Conversion rate: __%
  □ LTV impact: $__ (increase)
  □ Pricing feedback: (too high/low/fair)
```

---

## Fase 6: Post-Launch Review (Week 4) 🔍

### 6.1 Success Criteria Met?

```
Define BEFORE launch what "success" means:

TECHNICAL:
- [ ] Zero critical bugs in first week
- [ ] <0.05% error rate
- [ ] <300ms latency p95
- [ ] 99.9% uptime

BUSINESS:
- [ ] 40%+ adoption within week
- [ ] 35%+ day-30 retention
- [ ] NPS >40
- [ ] If monetized: X% conversion

ETHICAL:
- [ ] Zero dark patterns discovered
- [ ] Trust score stable or up
- [ ] User wellbeing not harmed (QLI positive)
- [ ] Privacy audit passed
- [ ] Zero complaints about manipulation

THEN SCORE:
All met → Launch successful, celebrate
90%+ met → Launch successful with minor issues
75%+ met → Launch works but has issues, create plan
<75% → Investigate before celebrating
```

### 6.2 Lessons Learned

```
□ What went well? (replicate)
□ What went wrong? (avoid next time)
□ What surprised us? (unexpected behavior)
□ If could do again, what different? (improvement)
□ Team feedback gathered (retrospective)
□ Process improvements documented
□ Roadmap adjusted based on learnings
□ Success story documented (for future launches)
```

### 6.3 Iteration Planning

```
□ Top 3 user requests documented
□ Top 3 bugs found documented
□ Top 3 improvement opportunities identified
□ Prioritized backlog created
□ Next sprint planned
□ Who owns ongoing monitoring?
□ Communication plan for iterations
```

---

## Fase 7: Long-Term Stewardship (Week 5+) 🌱

### 7.1 Continuous Monitoring

```
WEEKLY:
□ Adoption trends (up, down, stable?)
□ Error rate (any spikes?)
□ User feedback (any patterns?)
□ Competitive moves (anyone launch similar?)

MONTHLY:
□ Retention cohorts (week by week)
□ Revenue impact (if monetized)
□ Feature usage (where do users click?)
□ Churn analysis (why do users leave?)
□ User satisfaction (NPS, sentiment)
□ Dark patterns audit (did we slip?)
□ Privacy audit (compliance check)

QUARTERLY:
□ Full business impact assessment
□ User wellbeing impact (QLI)
□ Market share (vs competitors)
□ Roadmap alignment (still makes sense?)
□ Tech debt assessment (pay down?)
```

### 7.2 User Feedback Loop

```
CHANNELS:
□ In-app survey (how is feature working?)
□ Email to segment (how often they use)
□ Community discussion (what do they say?)
□ Support tickets analyzed (what breaks?)
□ User interviews (deep dive on experience)

ACTION:
□ Common feedback themes identified
□ Prioritized based on impact
□ Communicated back to users ("We heard you")
□ Roadmap updated if major finding
□ Next iteration planned
```

### 7.3 Prevention of Regression

```
□ Metrics dashboard always visible (team knows health)
□ Alerts if metrics drop (automatic notice)
□ Quarterly ethical audit (prevents dark pattern creep)
□ User satisfaction tracked (catches decline early)
□ Competitor monitoring (don't fall behind)
□ Tech debt paid down (prevents slowdown)
□ Team trained on feature (knowledge doesn't disappear)
□ Documentation kept current (future reference)
```

---

## Templates de Resumen 📋

### Template 1: Pre-Launch Summary

```
FEATURE: [Name]
LAUNCH DATE: [Date]
OWNER: [Person]

READINESS: Ready / Almost Ready / At Risk
SECURITY: Passed / Minor Issues / Critical Issues
COMPLIANCE: Compliant / Needs Work / At Risk
TEAM PREP: Ready / Needs Training / Not Ready

RISKS:
- [Risk 1]
- [Risk 2]
- [Risk 3]

GO/NO-GO DECISION: GO / NO-GO

[Sign-off]
```

### Template 2: Launch Day Summary

```
FEATURE: [Name]
LAUNCH TIME: [Time]
DEPLOYMENT: Success / Issues / Rollback

METRICS AT LAUNCH:
- Error rate: _%
- Latency p95: __ms
- Users affected: X
- Adoption (first hour): __%

ISSUES FOUND:
- [Issue 1] - Severity: [Critical/Major/Minor]
- [Issue 2] - Severity: [Critical/Major/Minor]

ACTIONS TAKEN:
- [Action 1]
- [Action 2]

STATUS: Live & Stable / Live with Issues / Rolled Back

[Sign-off]
```

### Template 3: Week 4 Retrospective

```
FEATURE: [Name]
PERIOD: [Week 1-4]
OWNER: [Person]

SUCCESS CRITERIA:
- Technical: Metric = Target ✓ / ✗
- Business: Metric = Target ✓ / ✗
- Ethical: Metric = Target ✓ / ✗

OVERALL: Success ✓ / Partial ✓ / Needs Work ✗

KEY LEARNINGS:
1. [Learning 1]
2. [Learning 2]
3. [Learning 3]

NEXT STEPS:
1. [Action 1]
2. [Action 2]
3. [Action 3]

[Sign-off]
```

---

## Resumen Rápido: No Olvides 🚀

```
PRE-LAUNCH:
✓ Test thoroughly
✓ Audit privacy
✓ Plan communication
✓ Brief team

LAUNCH:
✓ Deploy gradually (canary → 50% → 100%)
✓ Monitor actively
✓ Support ready
✓ Announce

POST-LAUNCH:
✓ Day 1: Smoke test
✓ Week 1: User feedback
✓ Week 4: Retrospective
✓ Ongoing: Monitor metrics

ETHICAL:
✓ Before: ethical audit
✓ After: dark pattern check
✓ Always: user wellbeing

Una cosa: 
Si no puedes responder "sí" a todos estos items,
no estás listo para lanzar.
Tómate el tiempo.
Hazlo bien.
Los usuarios lo van a apreciar.
```

---

**Siguiente:** [Conflictos Internos →](./16-internal-conflicts.md)

**Relacionado:**
- [Implementation Guide](./07-implementation-guide.md) - Antes de llegar al launch
- [Advanced Metrics](./12-advanced-metrics.md) - Qué medir post-launch
- [Organizational Transformation](./11-organizational-transformation.md) - Cómo preparar el equipo
