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
│   ├── gallery-metadata.mjs           Test automático: la galería, las páginas y esta tabla cuentan lo mismo
│   ├── smoke.mjs                      Test automático (corre en CI)
│   ├── particles-in-bounds.mjs        Test automático: las partículas de G no se hunden en el piso ni en las paredes,
│   │                                  tampoco al achicar o agrandar la ventana
│   ├── physics-object.mjs             Test automático: PhysicsObject (ceros, modos clamp y reflect, bordes de la ventana)
│   ├── toggles-y-red.mjs              Test automático: alternadores (un solo callback al reanudar), red radial con los
│   │                                  anillos cerrados y la plantilla de secuencias junto a common.js
│   ├── dashboard-modal.mjs            Test automático: el modal de la galería se cierra con ×, fondo y Escape
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

`npm test` corre cuatro chequeos y falla si alguno falla. Primero
`tests/gallery-metadata.mjs` (sin navegador): cada animación se nombra en tres
lugares, la tarjeta de la galería (`data.js`), la propia página (su `<title>`,
su encabezado y su descripción) y la tabla de más abajo, y los tres tienen que
hablar de la misma animación; además, la dificultad de cada tarjeta tiene que
tener color en el modal de detalles. Después `tests/smoke.mjs` abre las 37
páginas en Chromium y falla si alguna tiene un error de JavaScript, un recurso
que no carga, `common.css`/`common.js` sin aplicar, o un botón de pausa que no
responde. Si ya tienes un Chromium instalado (en los contenedores de la nube la versión no es
la que baja Playwright), pásalo con `CHROMIUM=/ruta/al/chromium npm test`. Después
`tests/particles-in-bounds.mjs` avanza la lluvia de partículas (G) 1.500 cuadros, con
números al azar de semilla fija, y falla si alguna partícula queda bajo el piso o fuera
de una pared; lo repite al achicar y al agrandar la ventana, porque los bordes siguen
su tamaño. `tests/physics-object.mjs` prueba `PhysicsObject` en una página
mínima: que un `0` pasado como configuración se respete, los dos modos de rebote y que
los bordes por defecto sean los de la ventana. `tests/toggles-y-red.mjs` fija que
`SequenceToggle` y `ParticleEmitterToggle` hagan una sola cosa al reanudar con `toggle()`,
que `createRadialWeb` cierre cada anillo y que el código de la plantilla `jsSequenceAnimation`
se cargue junto a `common.js`. Por último `tests/dashboard-modal.mjs` abre el modal de detalles
de la galería y comprueba que se cierre con la ×, con el fondo y con Escape. Corre en CI en cada push.

`tests/index.html` tiene además suites manuales (validación, accesibilidad
WCAG 2.1 AA, rendimiento) — ver [`tests/README.md`](tests/README.md).

## Animaciones

| Grupo | ID | Animación | Categoría |
|---|---|---|---|
| Iniciales | A | Vuelo 3D | 3D |
|  | B | Círculos Rebotadores | Física |
|  | C | Página Volteándose | 3D |
|  | D | Cubo 3D | 3D |
|  | E | Pirámide Construyéndose | Patrones |
|  | F | Polígono Morphing | Patrones |
|  | G | Lluvia Partículas | Física |
|  | H | Hoja Cayendo | Física |
|  | I | Péndulo | Mecánico |
|  | J | Bola Rodando por Laberinto | Movimiento |
|  | K | Barras Ecualizador | Patrones |
|  | L | Círculos Concéntricos | Patrones |
|  | M | Grid de Puntos Deformándose | Patrones |
|  | N | Espiral Hipnótica Rotando | Patrones |
|  | O | Tinta Derramándose | Efectos |
|  | P | Tela Ondeando al Viento | Efectos |
|  | Q | Aurora Boreal | Efectos |
|  | R | Efecto Matriz (Código Cayendo) | Efectos |
|  | S | Dominó Cayendo en Secuencia | 3D |
|  | T | Engranajes Rotando | Mecánico |
|  | U | Reloj Analógico Animado | Mecánico |
|  | V | Brújula Girando | Mecánico |
| Extended | W | Onda Gravitacional | Efectos |
|  | X | Rayos Fractal | Patrones |
|  | Y | Patrón Hexagonal | Patrones |
|  | Z | Zoom Infinito | Efectos |
| Complex | AA | Prisma de Luz | Efectos |
|  | AB | Engranajes Giratorios | Mecánico |
|  | AC | Nebulosa Estelar | Efectos |
|  | AD | Espejos Recursivos | 3D |
|  | AE | Nodo de Red | Patrones |
|  | AF | Mandala Geométrico | Patrones |
|  | AG | ADN Helicoidal | 3D |
|  | AH | Lluvia de Estrellas | Efectos |
|  | AI | Telaraña Oscilante | Efectos |
|  | AJ | Arena Cayendo | Física |

Respetan `prefers-reduced-motion`: con esa preferencia activa, las
animaciones se reducen a un solo ciclo casi instantáneo.
