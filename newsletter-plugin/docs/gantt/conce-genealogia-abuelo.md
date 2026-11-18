# Gantt · Conce · Genealogía · Abuelo con 3 hijos y varios nietos

**[SUPUESTO]** Caso típico. Año corrido desde el 1 de diciembre de 2026.

Escenario: un abuelo da de alta su árbol, sus hijos y nietos se suman, y el sistema le avisa a él y a su familia cuando aparece un apellido nuevo en la rama paterna.

```mermaid
gantt
  title Genealogía · Abuelo con 3 hijos y varios nietos
  dateFormat YYYY-MM-DD
  axisFormat %b %y
  section Alta y árbol
  Alta por opt-in                  :done, g1a, 2026-12-01, 2d
  Carga del árbol familiar         :done, g1b, 2026-12-03, 21d
  section Perfil y envíos
  Perfil activo envío cada 7 días  :active, g1c, 2026-12-24, 190d
  Perfil ocasional envío cada 14 días :g1d, 2027-07-01, 92d
  Vuelve a activo                  :g1e, 2027-10-01, 61d
  section Familia
  Hijos y nietos abren cuenta      :g1f, 2027-02-01, 120d
  Nuevo apellido en rama paterna   :milestone, g1g, 2027-03-10, 0d
  Bloque familiar en el correo     :g1h, 2027-03-17, 14d
  section Eventos sensibles
  Cambio de fecha en registro de nieto :crit, g1i, 2027-07-14, 3d
  Aviso al titular por carril notif    :milestone, g1j, 2027-07-14, 0d
```

## Notas
- El aviso de cambio sensible va por el carril de notificaciones, en tiempo real, al titular del árbol.
- Que el abuelo avise a hijos y nietos está diferido: se decide al implementar. Ver docs/00-inconsistencias.md, fila 5.
- Ilustrativo: no es el calendario de implementación; ver docs/gantt/conce-implementacion.md.
