# Gantt · Conce · Implementación del plugin (referencia para el equipo)

**[SUPUESTO]** Fechas orientativas. Dependen de la resolución de las decisiones pendientes en docs/05-riesgos-y-pendientes.md.

```mermaid
gantt
  title Genealogía · Implementación del plugin en Conce
  dateFormat YYYY-MM-DD
  axisFormat %b %y
  section Diseño
  Diseño y políticas               :done, g3a, 2026-12-01, 30d
  section Core
  Suscripción y perfiles           :g3b, 2027-01-04, 30d
  Cola y agrupación                :g3c, 2027-02-01, 21d
  Adaptador de correo existente    :g3d, 2027-02-15, 14d
  Contrato del carril de notificaciones :milestone, g3e, 2027-02-28, 0d
  Adaptador de notificaciones      :g3f, 2027-03-01, 21d
  Proveedor de contenido Conce     :g3g, 2027-03-15, 30d
  section Pruebas y salida
  Pruebas con datos reales         :crit, g3h, 2027-04-15, 30d
  Piloto con usuarios              :g3i, 2027-05-15, 45d
  Ajustes de umbral y perfiles     :g3j, 2027-06-30, 30d
  Producción                       :milestone, g3k, 2027-07-31, 0d
  Operación y monitoreo            :g3l, 2027-08-01, 120d
  Revisión de año corrido          :milestone, g3m, 2027-11-30, 0d
```

## Notas
- El hito del contrato del carril de notificaciones es bloqueante para el adaptador. Sin él no se puede avanzar en esa parte.
- Las pruebas con datos reales dependen de validar los umbrales de perfil.
