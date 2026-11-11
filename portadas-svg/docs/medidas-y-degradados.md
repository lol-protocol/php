# Medidas y degradados

La cubierta completa, de izquierda a derecha, es:

```
sangrado | solapa | contratapa | lomo | portada | solapa | sangrado
```

Sin solapas, esas dos no existen y el sangrado de cada lado pega con la contratapa o con la portada.

- **Lomo** = `ceil(páginas / 2) × grosor_hoja_mm`, salvo que des `lomo_mm`. Con 0,1 mm por hoja, un papel de unos 80 g/m². Es una aproximación: confírmalo con la imprenta.
- **Ancho de la cubierta completa** = `2 × sangrado + 2 × ancho + lomo`, más `2 × solapas` si las hay.
- **Alto** = `alto + 2 × sangrado`.

Con los ejemplos: el de Platón tiene 112 páginas, así que su lomo mide 5,6 mm y su sobrelomo, con solapas de 90 mm, mide 461,6 × 216 mm. El de Sun Tzu (96 páginas, sin solapas) mide 280,8 × 216 mm.

## Degradados

El fondo puede ser un degradado en lugar de un color sólido. Se escribe en SVG, dentro de un `<defs>`, y el lomo lo comparte, así que no se corta.

```json
"degradado": { "tipo": "lineal", "angulo": 90, "colores": ["#f3ead7", "#c9a96e"] }
```

- `tipo`: `lineal` (por defecto) o `radial`. El radial sale del centro de la cubierta completa y llega a las esquinas.
- `angulo`: en grados, solo para el lineal. `0` va de izquierda a derecha y `90` de arriba abajo (por defecto `90`).
- `colores`: una lista de 2 a 6 colores en `#rrggbb`, repartidos a partes iguales. O la palabra `"auto"` (por defecto): el fondo va hacia un segundo color que se aleja del color del texto, así que el contraste mejora de un extremo al otro.

Las coordenadas van en milímetros sobre la cubierta completa. Por eso el degradado coincide al unir las piezas y cada archivo muestra su tramo correcto. El `medidas.txt` describe el degradado usado.

Un `"degradado": {}` vacío equivale a un degradado `auto` lineal de arriba abajo.
