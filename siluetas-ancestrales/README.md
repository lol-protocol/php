# siluetas-ancestrales

Generador de **siluetas de frente de personas ancestrales** (nuestros antepasados), en **JavaScript y SVG**, sin dependencias. Son ilustraciones, no fotografías: cuerpo y cabeza con proporciones reales, sombreado suave y ropa con pliegues, ribetes y telas, pero sin rostro (los rasgos son opcionales y apenas se insinúan).

Trae un **catálogo grande de ropa** organizado por región, época, sexo y clase social, y un **generador de ropa y colores nuevos** para cuando el catálogo no alcanza.

| | |
|---|---|
| Atuendos completos | 120+ (Egipto, Mesopotamia, Grecia, Roma, Bizancio, Europa medieval a siglo XX, mundo eslavo, celta y vikingo, Medio Oriente, Asia, África, Polinesia, Rapa Nui, Andes, Mesoamérica, Norteamérica, Ártico, pueblo mapuche, Chile rural, Tierra del Fuego, Río de la Plata y la Hispanoamérica colonial) |
| Prendas | 200+ (túnicas, mantos, faldas, pantalones, chaquetas, calzado, joyas…) sobre 15 plantillas paramétricas |
| Tintes con nombre | 80+, con época y costo aproximados (índigo, cochinilla, púrpura de Tiro, gualda, ocres…) |
| Pieles, cabellos | 12 tonos de piel y 13 de cabello |
| Peinados, barbas, tocados | 23, 8 y 29 |
| Patrones de tela | 18 (rayas, tartán, grecas, escalonado, rombos, escamas…) |

## Uso rápido

Requiere Node 22 o más (el módulo `node:test` con patrones de archivo). No hay nada que instalar.

```bash
cd siluetas-ancestrales
node bin/generar.js --semilla 7 --atuendo huaso -o huaso.svg
node bin/generar.js --region andes --anio 1500 --sexo f --edad 30 -o andina.svg
node bin/generar.js --nueva --estilo festivo --semilla 12 -o nueva.svg     # ropa inventada
node bin/generar.js --listar atuendos        # también: regiones, prendas, tintes, resumen
node bin/generar.js --ayuda
```

La **galería** (`galeria.html`) se abre directo desde el disco: elige región y atuendo, edad, complexión, postura, piel, nivel de detalle y fondo, genera ropa nueva y descarga el SVG. Usa `dist/siluetas-ancestrales.js`, el mismo código empaquetado en un solo archivo.

Ejemplos ya generados en [`ejemplos/`](ejemplos/).

## Como biblioteca

```js
import { generarSVG, generarPersona, renderizar, generarRopaNueva, generarPaleta, buscarAtuendos } from './src/index.js';

// Una persona del catálogo (misma semilla, misma persona)
const svg = generarSVG({ semilla: 7, region: 'andes', anio: 1500, sexo: 'f', edad: 34 });

// Describir primero y dibujar después (la descripción es JSON simple)
const persona = generarPersona({ atuendo: 'huaso', edad: 62, corpulencia: 'robusta' });
const svg2 = renderizar(persona, { detalle: 'alto', fondo: '#efe9dd' });

// Ropa que no está en el catálogo
const nueva = generarSVG({ ropaNueva: true, estilo: 'ceremonial', esquema: 'complementario', semilla: 3 });
const paleta = generarPaleta({ esquema: 'triada', semilla: 9 });   // { A, B, C, D } en hex
```

En el navegador, sin módulos: `<script src="dist/siluetas-ancestrales.js">` expone `window.SiluetasAncestrales` con las mismas funciones.

### Opciones de `generarPersona` / `generarSVG`

| Opción | Qué hace |
|---|---|
| `semilla` | número o texto; fija todo lo que no pidas |
| `atuendo` | id de `src/datos/atuendos.js`; si falta se elige uno con `region`, `anio`, `sexo` y `clase` (si no hay coincidencia exacta se afloja un filtro a la vez) |
| `sexo` (`f`/`m`), `edad` (2–100), `corpulencia` (`delgada`, `media`, `robusta`, `corpulenta` o un número), `altura` (cm), `postura` (`firme`, `relajada`, `abierta`) | cuerpo; los niños tienen otras proporciones |
| `piel`, `cabello` | id del catálogo o `#rrggbb` |
| `barba`, `peinado`, `tocado`, `calzado` | ids para forzarlos |
| `paleta` | `{A,B,C,D}`: principal, secundario, ribete y detalle de todo el atuendo |
| `variacion` | 0 a 1: cuánto se varían holguras, vuelos y pliegues respecto al catálogo |
| `detalle` | `minimo` (plano), `medio`, `alto` (más sombreado) |
| `rasgos` | `true` insinúa ojos, cejas, nariz y boca |
| `ropaNueva`, `estilo`, `esquema` | ropa inventada (`sobrio`, `trabajo`, `festivo`, `ceremonial`) con un esquema de color (`analogo`, `complementario`, `triada`, `monocromo`, `tierra`, `festivo`) |

## Cómo está hecho

- `src/cuerpo.js`: proporciones por edad, sexo y complexión; el cuerpo se dibuja con curvas suaves a partir de puntos de referencia (hombro, cintura, cadera, rodilla…).
- `src/cabeza.js`: cabeza, orejas, peinados, barbas y tocados, dibujados en unidades de la cabeza.
- `src/prendas.js`: las plantillas de prenda. Cada una se **ancla a las medidas del cuerpo**, así que la misma túnica sirve para un niño y para un anciano corpulento.
- `src/datos/`: los datos. `prendas.js` (cada prenda es una plantilla con sus parámetros), `atuendos.js` (combinaciones por región y época, con su paleta de tintes), `colores.js` (tintes, pieles, cabellos).
- `src/generador.js`: elige atuendo, colores y rasgos; inventa ropa nueva; arma paletas.
- `src/svg.js`: capas, patrones y sombreado. El SVG resultante no lleva scripts, imágenes ni enlaces externos.

### Agregar ropa

1. Una prenda nueva: una línea en `src/datos/prendas.js` con la plantilla, sus parámetros y los papeles de color (`A` principal, `B` secundario, `C` ribete, `D` detalle).
2. Un atuendo nuevo: una entrada en `src/datos/atuendos.js` con región, años, sexo, clase, prendas y los tintes posibles de cada letra de la paleta.
3. Una plantilla nueva (otra forma de prenda): una función en `src/prendas.js` que devuelva `{ atras, partes, detalles }` y se agregue a `PLANTILLAS`.

Después corre las pruebas y `node bin/construir.js`.

## Pruebas

```bash
cd siluetas-ancestrales
node --test 'pruebas/*.test.js'     # catálogo, SVG, generador y paquete
node bin/construir.js --comprobar   # dist/ y ejemplos/ al día con src/
node bin/construir.js               # regenera dist/ y ejemplos/ tras cambiar src/
```

Las pruebas revisan que cada atuendo apunte a prendas, tintes y peinados que existen; que **todo atuendo** se dibuje bien a seis edades y complexiones (SVG bien formado, sin `NaN`, sin scripts ni referencias externas, rellenos definidos); que la misma semilla dé el mismo SVG; que los filtros se respeten; que la ropa nueva sea válida y variada; y que `dist/` dé exactamente lo mismo que `src/`.

## Límites, dicho con honestidad

- Es **ilustración histórica aproximada**, no un estudio de indumentaria: las épocas son anchas y los trajes duraron más que las fechas. Los tintes traen época y costo para no vestir a un campesino medieval de púrpura de Tiro, pero no son una tesis.
- Algunas prendas son una **simplificación de forma** (por ejemplo la toga, el sari o el kimono se resuelven con plantillas generales de manto, falda y túnica cruzada).
- Los atuendos de pueblos vivos (mapuche, selk'nam, rapanui, maasái, pueblos de las llanuras…) son una representación genérica; antes de usarlos en algo publicado conviene revisarlos con personas de esos pueblos o con fuentes de su propia mano.
- Solo vista frontal, de pie.
