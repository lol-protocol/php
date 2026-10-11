# Tokens de marca

Fuente única de verdad para colores, trazos y medidas de Marcalibros y Plataforma POS.
Todo SVG y JPG del repositorio usa estos valores.

## Paleta

| Token    | Hex       | Uso                                         |
|----------|-----------|---------------------------------------------|
| `tinta`  | `#1E2A38` | Trazos principales, texto, fondo oscuro     |
| `papel`  | `#F4EEE1` | Fondo claro, páginas, relleno de contraste  |
| `ocre`   | `#C98B3A` | Acento cálido, detalles decorativos         |
| `verde`  | `#3E5C4A` | Acento natural, iconos de la interfaz       |
| `niebla` | `#7A9CB0` | Acento frío, capas de fondo y atmósfera     |

## Contraste (WCAG)

Relaciones calculadas entre pares de la paleta:

| Par                    | Razón  | Uso permitido                              |
|------------------------|--------|--------------------------------------------|
| `papel` sobre `tinta`  | 12.58  | Texto y trazos sobre fondo oscuro          |
| `tinta` sobre `papel`  | 12.58  | Texto y trazos sobre fondo claro           |
| `verde` sobre `papel`  | 6.41   | Iconos de la interfaz (apto, supera 3:1)   |
| `ocre` sobre `tinta`   | 5.01   | Acentos sobre fondo oscuro                 |
| `niebla` sobre `tinta` | 4.99   | Capas de fondo sobre fondo oscuro          |
| `tinta` sobre `verde`  | 1.96   | No usar como par de contraste              |
| `ocre` sobre `papel`   | 2.51   | Solo decorativo, nunca como trazo funcional |
| `niebla` sobre `papel` | 2.52   | Solo decorativo, nunca como trazo funcional |

Regla: los elementos funcionales (iconos, estados, bordes que transmiten información) necesitan 3:1 como mínimo.
Sobre `papel` solo se usan `tinta` y `verde` para eso. `ocre` y `niebla` sobre `papel` quedan como relleno decorativo.

## Trazos y medidas

- **Iconos (24×24):** trazo de 2 px, esquinas y extremos redondeados (`stroke-linecap` y `stroke-linejoin` en `round`).
- **Ilustraciones de estado vacío (64×64):** trazo de 2 px, misma regla de extremos.
- **Marcalibros (1400×200):** sin texto dentro del SVG. El nombre del autor o del movimiento vive en `catalog/manifest.json`.
- **Grilla base:** 8 px. Los márgenes internos de los iconos son de 2 px.

## Tipografía

- **Títulos:** serif de apoyo al estilo grabado. Propuesta: Source Serif 4, con respaldo en `Georgia, serif`.
- **Interfaz y texto corrido:** sans-serif. Propuesta: Inter, con respaldo en `system-ui, sans-serif`.
- Ningún SVG de iconos incluye texto. Las fuentes solo se usan en la interfaz de la aplicación.

## Archivos

- SVG para iconos e ilustraciones (escalan sin pérdida y conservan transparencia). Menos de 10 KB cada uno. El generador comprueba este límite en los marcalibros del catálogo y se detiene si alguno lo supera.
- JPG para paisajes, calidad 85, sin metadatos EXIF. Menos de 300 KB cada uno.
- Nombres de archivo en minúsculas, separados por guiones, sin números al inicio.
