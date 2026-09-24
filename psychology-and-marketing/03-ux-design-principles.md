# 3. Principios de Diseño UX (Privacy by Design)

**Lectura previa:** [Neuromarketing Ético](./02-ethical-neuromarketing.md)  
**Siguiente:** [Diseño Conversacional](./04-conversational-design.md)

---

## UX Ético vs. Dark Patterns

### Dark Patterns: Tácticas manipuladoras
```
❌ Botón "No, quiero pagar más" en letra pequeña
❌ Subscribirte es fácil, cancelar es imposible
❌ Checkbox preseleccionados para recibir emails
❌ Múltiples pasos para "No" pero uno para "Sí"
❌ Tienes que llamar para cancelar
```

### UX Ético: Diseño que respeta
```
✅ Botones de "Sí" y "No" del mismo tamaño
✅ Cancelar es tan fácil como subscribir
✅ Checkboxes desseleccionados por defecto
✅ Igual número de pasos para cualquier opción
✅ Puedes cancelar online en 2 clicks
```

---

## 1. Transparencia Radical

### Qué significa
El usuario **entiende completamente** qué datos pides, por qué, y qué hará con ellos.

#### ❌ Opaco:
```
"Necesitamos acceso a tu ubicación"
(pero no dices si es GPS o aproximada)
(ni si se guarda o se usa en tiempo real)
```

#### ✅ Transparente:
```
"Necesitamos tu ubicación para:
- Mostrar tiendas cerca (se guarda 24h)
- Detectar fraude (no se guarda)
- No se vende a terceros

¿Sí o No?"
```

### Cómo implementar:
- Cada dato que pides: explica por qué
- Lenguaje simple, no legal
- Antes de pedir, no después
- Opción de "No" clara

---

## 2. Consentimiento Informado

### NO es consentimiento:
```
✅ Clic en "Aceptar" sin haber leído
✅ Checkbox preseleccionado
✅ Pedir después de que ya lo usaste
✅ "De acuerdo con nuestros términos" (nadie los lee)
```

### SÍ es consentimiento:
```
✅ Usuario puede ver EXACTAMENTE qué acepta
✅ Puede deseleccionar cosas específicas
✅ Pides ANTES de usar sus datos
✅ El "No" es fácil e igual de prominente
✅ Puede cambiar de opinión después
```

#### Ejemplo GDPR bien hecho:
```
[ ] Emails de marketing (puedo cambiar después)
[ ] Análisis de comportamiento (anónimo)
[ ] Compartir con partners (para mejorar servicio)

[Rechazar todo]  [Solo essentials]  [Aceptar todo]
```

---

## 3. Arquitectura de Información Clara

### Problema: Sobrecarga cognitiva
Si pides 15 datos a la vez, el usuario:
- Se siente abrumado
- Comete errores
- Abandona el proceso
- O miente en los datos

### Solución: Progresiva
```
Paso 1: Email (essencial)
Paso 2: Nombre (essencial)
Paso 3: Ubicación (opcional, para mejor servicio)
Paso 4: Preferencias (opcional, para recomendaciones)

Cada paso tiene propósito claro.
```

### Principios:
1. **Primero lo esencial** (email, password)
2. **Luego lo útil** (ubicación, edad)
3. **Finalmente lo nice-to-have** (preferencias)
4. **Siempre marca optional/required**
5. **Explica por qué necesitas cada cosa**

---

## 4. Validación Transparente

### ❌ Malo:
```
Usuario ingresa email en formulario
Hace click en "Siguiente"
"ERROR: Email no válido" (¿por qué?)
Regresa al formulario vacío
Pierde 5 minutos reescribiendo
```

### ✅ Bueno:
```
Usuario empieza a escribir email
Mientras escribe: "✓ Email válido"
O: "⚠ Este email ya está registrado"
Errores desaparecen en tiempo real
```

---

## 5. Reversibilidad y Control

### El usuario debe poder:
- [ ] Ver qué datos tienes de él
- [ ] Descargar sus datos
- [ ] Corregir información incorrecta
- [ ] Eliminar su cuenta y datos
- [ ] Exportar a otro servicio
- [ ] Cambiar opciones de privacidad

### Ejemplo: Google Takeout
```
✅ Un solo lugar donde puedo ver:
- Todos mis datos
- Descargarlos
- Eliminarlos
```

---

## 6. Minimización de Datos

### Pregunta para cada campo:
**"¿Realmente necesito esto?"**

```
❌ Pedir teléfono cuando puedes verificar email
❌ Pedir ubicación exacta cuando sirve código postal
❌ Guardar 5 años cuando sirven 5 meses
❌ Pedir cumpleaños cuando solo necesitas edad
```

### Beneficio dual:
- **Privacidad:** Menos datos = menos riesgo
- **UX:** Menos campos = más usuarios completan

---

## 7. Defensa en Profundidad (Defense in Depth)

No confíes en una sola medida.

```
Capa 1: Minimizar datos (solo lo necesario)
Capa 2: Encriptación (datos protegidos)
Capa 3: Acceso controlado (solo quien lo necesita)
Capa 4: Auditoría (track quién accedió)
Capa 5: Backup (recuperación en emergencia)
```

---

## 8. Notificación en Cambios

### Cuando cambias política de privacidad:
- [ ] Notificación clara (no email perdido en spam)
- [ ] Explica QUÉ cambió (no "terms updated")
- [ ] Por QUÉ cambió (transparencia)
- [ ] Dar opción de opt-out (si es posible)
- [ ] Plazo razonable (no: "entra en vigor mañana")

---

## 9. Vocabulario Claro

### ❌ Confuso:
```
"Procesamos datos según GDPR Artículo 6(1)(f)"
"Usamos cookies de terceros para optimizar la experiencia"
```

### ✅ Claro:
```
"Guardamos tu email para poder contactarte"
"Usamos Facebook pixels para saber si nuestros anuncios funcionan"
```

---

## 10. Defensa contra Fricciones

### Dark Pattern: Fricciones innecesarias para "No"
```
❌ Unsubscribe requiere verificación de email
❌ Borrar cuenta pide contraseña + email + confirmación
❌ Cambiar privacidad: 5 pasos en 3 páginas
```

### Ético: Frición mínima para ambos
```
✅ Unsubscribe: 1 click
✅ Borrar cuenta: 2 clicks (confirmación)
✅ Cambiar privacidad: toggle inmediato
```

---

## Checklist: ¿Tu UX es ético?

### Datos
- [ ] ¿Explico por qué pido cada dato?
- [ ] ¿Los datos son minimales (solo lo necesario)?
- [ ] ¿Puedo deseleccionar cosas?
- [ ] ¿Puedo ver qué datos tienes de mí?

### Consentimiento
- [ ] ¿Nada está preseleccionado?
- [ ] ¿El "No" es igual de fácil que "Sí"?
- [ ] ¿Pido antes de usar, no después?
- [ ] ¿Puedo cambiar de opinión después?

### Transparencia
- [ ] ¿Explico en lenguaje simple?
- [ ] ¿Evito legalese?
- [ ] ¿Puedo descargar mis datos?
- [ ] ¿Puedo eliminar mi cuenta?

### Controles
- [ ] ¿Puedo ver exactamente qué pasa con mis datos?
- [ ] ¿Hay opciones granulares (no todo o nada)?
- [ ] ¿Los cambios son inmediatos?

---

## Caso de Estudio: Apple vs. Facebook

### Apple (Privacy-First)
```
✅ "Your iPhone is a private computer. It's not our business what you do with it."
✅ Encriptación end-to-end por defecto
✅ Permiso explícito para todo
✅ Puedes ver qué apps acceden a qué
```

### Facebook (Ad-Driven)
```
❌ Tracking predeterminado
❌ Privacidad es opcional (después de cambiar 5 settings)
❌ Compartir datos con partners sin claridad
❌ "Business model" es vender publicidad (basada en datos)
```

**Paradoja:** Apple limita datos y es más rentable.  
**Lección:** La privacidad no mata el negocio. El modelo de negocio lo hace o no.

---

## Privacy by Design: Framework de Cavoukian

### 7 Principios:

1. **Proactive, not Reactive**
   - Imagina problemas antes de que pasen
   - No esperes a que haya un escándalo

2. **Privacy as Default**
   - Máxima privacidad por defecto
   - Usuario elige lo que quiere que sea menos privado

3. **Privacy Embedded**
   - Privacidad en cada línea de código
   - No es un plugin, es el núcleo

4. **Full Functionality**
   - Todo debería funcionar sin sacrificar privacidad
   - No es: privacidad vs. funcionalidad
   - Es: privacidad + funcionalidad

5. **End-to-End Security**
   - Cifra datos en almacenamiento y en tránsito
   - Protege de hacker, de terceros, de tí mismo

6. **Visibility and Transparency**
   - El usuario ve exactamente qué pasa
   - Auditable por terceros

7. **User-Centric**
   - Usuario controla sus propios datos
   - No es lo que el negocio quiere, es lo que el usuario elige

---

## Ejemplo: Formulario Ético

```html
<!-- ❌ MALO -->
<input type="email" placeholder="Email*">
<input type="phone" placeholder="Phone*">
<input type="date" placeholder="Birthday*">
<input type="checkbox" checked> Enviarme emails
<input type="checkbox" checked> Compartir con partners
[Crear cuenta]

<!-- ✅ BUENO -->
<input type="email" placeholder="Email (para tu cuenta)*">

<label>
  <input type="checkbox">
  Enviarme tips y noticias (2-3 veces/mes)
</label>

<p style="color: gray">
  Tu cumpleaños y teléfono son opcionales.
  No los usamos para marketing.
  <a href="/privacy">Leer política completa</a>
</p>

[Crear cuenta]
```

---

## Conclusión

**UX ético no es menos rentable.** Es más.

Porque:
- Usuarios confían más
- Menos churn (menor abandono)
- Mejor reputación
- Menos problemas legales
- Engagement genuino

La privacidad es una feature, no un bug.

---

**Siguiente lectura:** [Diseño Conversacional →](./04-conversational-design.md)

**Relacionado:**
- [Ethical Neuromarketing](./02-ethical-neuromarketing.md) - Cómo comunicar
- [Research Methods](./06-research-methods.md) - Cómo validar
- [Implementation Guide](./07-implementation-guide.md) - Cómo hacerlo
