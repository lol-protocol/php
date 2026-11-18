# Gantt · POS · Bibliotecario amateur armando su colección

**[SUPUESTO]** Caso típico. Año corrido desde el 1 de diciembre de 2026.

Escenario: un bibliotecario amateur etiqueta textos de forma constante, su colección crece y recibe el boletín el día 15 de cada mes con estadísticas de su colección.

```mermaid
gantt
  title POS · Bibliotecario amateur armando su colección
  dateFormat YYYY-MM-DD
  axisFormat %b %y
  section Alta y colección
  Alta y perfil coleccionista      :done, p1a, 2026-12-01, 1d
  Etiquetado de primeros textos    :active, p1b, 2026-12-02, 45d
  Colección crece con etiquetado constante :p1c, 2027-01-05, 330d
  Colección supera 40 títulos      :milestone, p1d, 2027-02-10, 0d
  section Boletín día 15
  Boletín mensual con fragmentos   :p1e, 2026-12-15, 335d
  Boletín con estadísticas de colección :p1f, 2027-02-15, 270d
  section Cierre
  Revisión de año corrido          :milestone, p1g, 2027-11-30, 0d
```

## Notas
- El perfil coleccionista aparece cuando la colección supera el umbral N (a definir). El hito de 40 títulos es un supuesto.
- El boletín llega el día 15 de cada mes con independencia del ritmo de etiquetado.
