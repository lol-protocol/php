# Gantt · Conce · Genealogía · Persona en otro país buscando su árbol

**[SUPUESTO]** Caso típico. Año corrido desde el 1 de diciembre de 2026.

Escenario: una persona se da de alta desde el extranjero, carga apellidos de su familia, pasa por períodos de poca actividad y vuelve a activarse tras una mudanza.

```mermaid
gantt
  title Genealogía · Persona en otro país buscando su árbol
  dateFormat YYYY-MM-DD
  axisFormat %b %y
  section Alta desde el extranjero
  Alta con zona horaria local      :done, g2a, 2026-12-01, 1d
  Carga inicial de apellidos       :done, g2b, 2026-12-02, 30d
  section Perfil y envíos
  Perfil activo cada 7 días        :active, g2c, 2026-12-08, 82d
  Perfil ocasional cada 14 días    :g2d, 2027-03-01, 121d
  Perfil latente cada 30 días      :g2e, 2027-07-01, 62d
  Vuelve a activo                  :g2f, 2027-09-06, 86d
  section Árbol
  Novedad en rama materna          :milestone, g2g, 2027-04-12, 0d
  Reactivación por login           :milestone, g2h, 2027-09-05, 0d
  Cambio de zona horaria por mudanza :g2i, 2027-09-10, 1d
```

## Notas
- El cambio de zona horaria recalcula las ventanas en la nueva zona. Ver docs/01-politicas.md, sección 7.
- Los intervalos de perfil son supuestos a calibrar.
