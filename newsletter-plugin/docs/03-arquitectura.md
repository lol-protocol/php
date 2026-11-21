# 03 · Arquitectura común (ambos carriles)

Ambos carriles comparten la misma lógica de núcleo. Lo que cambia es la estrategia de programación y el proveedor de contenido.

## Principios
- Core agnóstico de plataforma.
- Adaptadores para todo lo externo (correo, notificaciones, submódulo de difamación, datos del host).
- Políticas declarativas, en docs/01-politicas.md.
- Carriles separados: newsletter con agrupación; notificaciones en tiempo real.
- Fail-closed para lo sensible.
- Sin valores de datos personales en los logs.

---

## 1. Vista general

```mermaid
flowchart LR
  subgraph Host["Plataforma host (Conce o POS)"]
    DU["Datos de usuario y árbol"]
    EV["Eventos de cambio"]
  end
  subgraph Plugin["Plugin de newsletter"]
    SUB["Suscripciones"]
    PERF["Resolutor de perfil"]
    POL["Políticas por plataforma"]
    PROV["ContentProvider"]
    Q[("Cola de newsletter")]
    AGR["Agrupación"]
    REN["Renderizador"]
    SENS["Regla sensible"]
    NA["Adaptador de notificaciones"]
  end
  CRON["Scheduler cron"]
  MAIL["Correo existente"]
  NOTIF["Carril de notificaciones"]
  DEF["Submódulo difamación solo lectura"]

  DU --> PERF --> POL --> AGR
  CRON --> PROV --> Q
  SUB --> AGR
  Q --> AGR --> REN --> MAIL
  EV --> SENS
  SENS -.consulta.-> DEF
  SENS --> NA --> NOTIF
```

---

## 2. Carril 1 · Newsletter POS

```mermaid
flowchart TD
  A["Cron día 15 a las 09:00 hora Santiago"] --> B["Leer suscriptores activos de POS"]
  B --> C["Segmentar: coleccionista o explorador"]
  C --> D["ContentProvider POS arma fragmentos del mes"]
  D --> E{"¿Hay contenido?"}
  E -- No --> F["No enviar"]
  E -- Sí --> G["Renderizar plantilla por segmento"]
  G --> H["Enviar por correo"]
  H --> I["Registrar en send_log"]
```

---

## 3. Carril 1 · Newsletter Conce

```mermaid
flowchart TD
  A["Cron horario"] --> B["ContentProvider Conce recoge novedades nuevas"]
  B --> C["Encolar por usuario"]
  C --> D["Recalcular perfil e intervalo efectivo"]
  D --> E{"¿Ventana vencida en zona del usuario?"}
  E -- No --> Z["Esperar"]
  E -- Sí --> F{"¿Hay items pendientes?"}
  F -- No --> Z
  F -- Sí --> G["Renderizar correo con bloque familiar opcional"]
  G --> H["Enviar y marcar items como enviados"]
  H --> I["Registrar send_log por usuario y ventana"]
```

---

## 4. Carril 2 · Notificaciones Conce (tiempo real)

```mermaid
sequenceDiagram
  participant H as Host Conce
  participant P as Plugin
  participant S as Regla sensible
  participant D as Submódulo difamación
  participant A as Adaptador
  participant N as Carril notificaciones
  H->>P: evento de cambio en registro
  P->>P: guardar en sensitive_event
  P->>S: evaluar
  S->>D: consulta de solo lectura
  D-->>S: datos o error
  alt campo sensible o error del submódulo
    S->>A: emitir evento
    A->>N: llamada directa
    N-->>A: ack o error
    A-->>P: resultado
    P->>P: marcar notificado o pendiente de reintento
  else no sensible
    S-->>P: no notificar
  end
```

---

## 5. Atributos no funcionales

- **Observabilidad:** métricas de envíos, omitidos por falta de contenido, errores y notificaciones pendientes.
- **Privacidad:** los logs no contienen valores de datos personales.
- **Resiliencia:** reintentos acotados, idempotencia y fail-closed.
- **Dependencias externas:** correo existente, carril de notificaciones (funciones por confirmar), submódulo de difamación (solo lectura) y datos del host.
