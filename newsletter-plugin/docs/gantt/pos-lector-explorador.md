# Gantt · POS · Lector que busca autores parecidos

**[SUPUESTO]** Caso típico. Año corrido desde el 1 de diciembre de 2026.

Escenario: un lector explorador etiqueta de forma esporádica, descubre autores nuevos, deja de actuar durante un período y vuelve a etiquetar.

```mermaid
gantt
  title POS · Lector explorador que busca autores parecidos
  dateFormat YYYY-MM-DD
  axisFormat %b %y
  section Alta y lectura
  Alta y perfil explorador         :done, p2a, 2026-12-01, 1d
  Lectura y etiquetado esporádico  :p2b, 2026-12-03, 60d
  Descubre y etiqueta nuevo autor  :p2c, 2027-02-16, 40d
  Sin acciones 60 días             :p2d, 2027-05-01, 60d
  Vuelve a etiquetar               :milestone, p2e, 2027-07-03, 0d
  section Boletín día 15
  Boletín mensual con fragmentos   :p2f, 2026-12-15, 335d
  Autores similares en el boletín  :milestone, p2g, 2027-02-15, 0d
  section Cierre
  Revisión de año corrido          :milestone, p2h, 2027-11-30, 0d
```

## Notas
- En POS el perfil no cambia la frecuencia. El boletín sigue llegando el día 15 aunque el lector deje de actuar.
- Lo que cambia es el contenido: recomendaciones de autores similares según lo que etiquetó.
- Ilustrativo: POS no tiene calendario de implementación; la fecha del 15-dic-2026 no está comprometida.
