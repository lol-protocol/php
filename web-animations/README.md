# Animaciones Web

Galería de 36 animaciones en HTML, CSS y JavaScript sin dependencias ni paso de
build. Cada animación es una página independiente que comparte estilos y
utilidades comunes.

## Ver la galería

Abrir [`src/animations/index.html`](src/animations/index.html) en un navegador.
Tiene búsqueda, filtros por grupo y categoría, y por cada animación un botón
para copiar el código de inserción y otro con sus detalles técnicos.

Cada animación también se puede abrir sola, por ejemplo
`src/animations/animacion-k.html`.

## Estructura

```
web-animations/
├── src/
│   ├── animations/
│   │   ├── index.html                 Galería
│   │   ├── dashboard-interactive.js   Copiar código y modal de detalles
│   │   ├── data.js                    Metadatos de las 36 animaciones
│   │   └── animacion-<id>.{html,css,js}
│   └── helpers/
│       ├── common.css                 Variables, clases base y keyframes compartidos
│       ├── common.js                  AnimationToggle, AnimationManager, helpers
│       └── animation-templates.js     Plantillas para generar animaciones nuevas
├── tests/
│   ├── smoke.mjs                      Test automático (corre en CI)
│   └── index.html                     Tests manuales en navegador: validación,
│                                      accesibilidad y rendimiento
├── docs/
│   ├── guides/                        Patrones DRY y módulos compartidos
│   ├── analysis/                      Reportes de errores y su estado actual
│   └── testing/                       Guías de testing, accesibilidad y rendimiento
└── _Garbage/                          Archivos obsoletos que ninguna página carga
                                       (ver su README)
```

## Crear una animación

Una página nueva enlaza los helpers con rutas relativas a
`src/animations/`:

```html
<link rel="stylesheet" href="../helpers/common.css">
<link rel="stylesheet" href="animacion-ak.css">
...
<button class="control-btn" onclick="toggle.toggle()">Pausar/Reanudar</button>
<script src="../helpers/common.js"></script>
<script src="animacion-ak.js"></script>
```

Y en su `.js`, un `AnimationToggle` con el selector de los elementos
animados por CSS:

```js
const toggle = new AnimationToggle('.mi-elemento');
```

Para animaciones que no se pausan por CSS (loops de física, emisores de
partículas, secuencias) hay subclases en `common.js`: `ParticleEmitterToggle`
y `SequenceToggle`, o `AnimationToggle()` sin selector combinado con un
`AnimationManager`. Después, agregar la entrada en `data.js` para que aparezca
en la galería.

## Tests

```bash
npm ci
npm test
```

`npm test` abre las 37 páginas en Chromium y falla si alguna tiene un error
de JavaScript, un recurso que no carga, `common.css`/`common.js` sin aplicar,
o un botón de pausa que no responde. Corre en CI en cada push.

`tests/index.html` tiene además suites manuales (validación, accesibilidad
WCAG 2.1 AA, rendimiento) — ver [`tests/README.md`](tests/README.md).

## Animaciones

| Grupo | ID | Animación | Categoría |
|---|---|---|---|
| Iniciales | A | Vuelo 3D | 3D |
| | B | Círculos Rebotadores | Física |
| | C | Página Volteándose | 3D |
| | D | Cubo 3D | 3D |
| | E | Pirámide Construyéndose | Patrones |
| | F | Polígono Morphing | Patrones |
| | G | Lluvia Partículas | Física |
| | H | Hoja Cayendo | Física |
| | I | Péndulo | Mecánico |
| | J | Onda Sinusoidal | Patrones |
| | K | Barras Ecualizador | Patrones |
| | L | Círculos Concéntricos | Patrones |
| | M | Burbujas | Física |
| | N | Fractales | Patrones |
| | O | Tinta Derramándose | Efectos |
| | P | Galaxia | Patrones |
| | Q | Aurora Boreal | Efectos |
| | R | Cascada Rectángulos | Patrones |
| | S | Ondas Ripple | Efectos |
| | T | Torbellino | Movimiento |
| | U | Universo | Patrones |
| | V | Vortex | Movimiento |
| Extended | W | Lava Flow | Efectos |
| | X | Matrix Rain | Efectos |
| | Y | Nebula | Patrones |
| | Z | Zen Garden | Patrones |
| Complex | AA | Möbius Strip | 3D |
| | AB | Kaleidoscope | Patrones |
| | AC | Mandelbrot Set | Patrones |
| | AD | Swarm Intelligence | Movimiento |
| | AE | Network Graph | Patrones |
| | AF | Crystal Growth | Patrones |
| | AG | Magnetic Field | Efectos |
| | AH | Traffic Flow | Movimiento |
| | AI | Clock Tower | Mecánico |
| | AJ | Arena Cayendo | Física |

Respetan `prefers-reduced-motion`: con esa preferencia activa, las
animaciones se reducen a un solo ciclo casi instantáneo.
