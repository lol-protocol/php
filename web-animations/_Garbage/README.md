# _Garbage

Archivos obsoletos que ninguna página carga. Se conservan en vez de
borrarse por si sirven de referencia, pero no deben volver a usarse ni
enlazarse.

| Archivo | Por qué está acá |
|---|---|
| `web-animations.html` | Versión original con todas las animaciones en un solo archivo, antes de separarlas en `src/animations/`. |
| `update_js.sh` | Script de migración de un solo uso que convirtió varias animaciones a `AnimationToggle`. Ya se ejecutó. |
| `animacion-c.js` | Vacío. `animacion-c.html` no carga ningún `.js` propio. |
| `animacion-d.js`, `animacion-l.js`, `animacion-o.js`, `animacion-q.js` | Versiones viejas con una función `toggleAnimation()` propia. Sus HTML usan un `<script>` inline con `AnimationToggle` y nunca cargaron estos archivos. |
