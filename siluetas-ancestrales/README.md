# siluetas-ancestrales

Generador de **siluetas de frente de personas ancestrales** (nuestros antepasados), en **JavaScript y SVG**, sin dependencias. Son ilustraciones, no fotografías: cuerpo y cabeza con proporciones reales y ropa con pliegues, ribetes y telas, pero sin rostro (los rasgos son opcionales y apenas se insinúan). **Sin bordes negros ni grises:** el volumen sale de degradados propios de cada color (luz desde arriba a la izquierda, sombra cálida del otro lado) y de sombras suaves que cada capa proyecta sobre las de abajo (la manga sobre el torso, la cabeza sobre el cuello, el pelo sobre la frente). Cada figura tiene una **pose casual y distinta con cada semilla**.

Cada figura **saluda con una mano** (por defecto la derecha de la persona, que de frente queda a la izquierda de quien mira).

Trae un **catálogo grande de ropa** organizado por región, época, sexo y clase social, y un **generador de ropa y colores nuevos** para cuando el catálogo no alcanza.

| | |
|---|---|
| Atuendos completos | 438, por región, época, sexo y clase social, **de la Antigüedad a 1985**: mundo antiguo, Europa medieval, moderna y del siglo XX, pueblos de todos los continentes y, para genealogía, la ropa de abuelos y bisabuelos (Chile, Latinoamérica, Estados Unidos y Europa de 1850 a 1980: jeans, overoles, ternos, vestidos de cada década, uniformes, oficios, novias y luto) |
| Prendas | 414 (túnicas, mantos, faldas, pantalones, chaquetas, calzado, joyas…) sobre 15 plantillas paramétricas, cada una con su **catálogo de colores y la probabilidad de cada color** |
| Tintes con nombre | 110, con época y costo aproximados (índigo, cochinilla, púrpura de Tiro, denim, anilinas…) y 47 perfiles de color reutilizables |
| Etnias y culturas | 116, con los que se estiman región, atuendo, piel, cabello, peinado, barba, complexión y estatura |
| Pieles, cabellos | 12 tonos de piel y 13 de cabello |
| Peinados, barbas, tocados | 26, 8 y 40 |
| Patrones de tela | 19 (rayas, tartán, grecas, escalonado, rombos, escamas, diseños geométricos amazónicos…) |

## Uso rápido

Requiere Node 22 o más (el módulo `node:test` con patrones de archivo). No hay nada que instalar.

```bash
cd siluetas-ancestrales
node bin/generar.js --semilla 7 --atuendo huaso -o huaso.svg
node bin/generar.js --region andes --anio 1500 --sexo f --edad 30 -o andina.svg
node bin/generar.js --etnia mapuche --sexo f --edad 34 --anio 1850 --avisos -o mapuche.svg   # estima lo demás
node bin/generar.js --etnia mapuche,espanol --sexo m --edad 29 --anio 1800 --saludo izquierda -o mestizo.svg
node bin/generar.js --nueva --estilo festivo --semilla 12 -o nueva.svg     # ropa inventada
node bin/generar.js --listar etnias          # también: atuendos, regiones, prendas, tintes, resumen
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

// Por etnia: se estiman región, atuendo, piel, cabello, peinado, barba, complexión y estatura
const p = generarPersona({ semilla: 3, etnia: 'aymara', sexo: 'm', edad: 48, anio: 1900 });
console.log(p.estimaciones, p.avisos);        // qué se estimó y qué filtros hubo que aflojar
const mezcla = generarSVG({ etnia: ['mapuche', 'espanol'], sexo: 'f', edad: 30, anio: 1800 });

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
| `etnia` | id, lista (`['mapuche','espanol']`), texto con comas o `{id: peso}`; `--listar etnias`. **Estima** todo lo que no des a mano: región y atuendo, piel, cabello, peinado, barba, complexión y estatura (ver abajo) |
| `anio` | año (negativo = antes de nuestra era): filtra atuendos por época y tintes por disponibilidad, y ajusta un poco la estatura |
| `pose` | `casual` (por defecto, distinta con cada semilla), `'firme'` (simétrica, como las primeras versiones) o un objeto parcial para fijar partes: `{ brazoLibre: 'abajo'\|'cintura'\|'bolsillo', carga: -1\|0\|1 (la pierna que carga el peso, vista de frente), cabeza: grados, inclinacion: grados, abre: 0,85 a 1,5 (apertura de los pies), giro: grados de los pies, saludo: { codo, antebrazo, mano } }`; la pose casual sale en `persona.cuerpo.pose` |
| `contorno` | `'ninguno'` (por defecto: sin bordes) o `'suave'` (un borde fino y tenue, por si se prefiere) |
| `saludo` | `derecha` (por defecto), `izquierda` o `ninguno`: la mano levantada es la de la persona; de frente, la derecha queda a la izquierda de quien mira |
| `sexo` (`f`/`m`), `edad` (2–100), `corpulencia` (`delgada`, `media`, `robusta`, `corpulenta` o un número), `altura` (cm), `postura` (`firme`, `relajada`, `abierta`) | cuerpo; los niños tienen otras proporciones |
| `piel`, `cabello` | id del catálogo o `#rrggbb` |
| `barba`, `peinado`, `tocado`, `calzado` | ids para forzarlos |
| `paleta` | `{A,B,C,D}`: principal, secundario, ribete y detalle de todo el atuendo |
| `variacion` | 0 a 1: cuánto se varían holguras, vuelos y pliegues respecto al catálogo |
| `detalle` | `minimo` (plano, sin degradados ni sombras), `medio` (degradados y sombras proyectadas), `alto` (más contraste y luz) |
| `rasgos` | `true` insinúa ojos, cejas, nariz y boca |
| `ropaNueva`, `estilo`, `esquema` | ropa inventada (`sobrio`, `trabajo`, `festivo`, `ceremonial`) con un esquema de color (`analogo`, `complementario`, `triada`, `monocromo`, `tierra`, `festivo`) |

## Cómo está hecho

- `src/cuerpo.js`: proporciones por edad, sexo y complexión; el cuerpo se dibuja con curvas suaves a partir de puntos de referencia (hombro, cintura, cadera, rodilla…).
- `src/cabeza.js`: cabeza, orejas, peinados, barbas y tocados, dibujados en unidades de la cabeza.
- `src/prendas.js`: las plantillas de prenda. Cada una se **ancla a las medidas del cuerpo**, así que la misma túnica sirve para un niño y para un anciano corpulento.
- `src/datos/`: los datos. `prendas.js` (cada prenda es una plantilla con sus parámetros), `atuendos.js` (combinaciones por región y época, con su paleta de tintes), `colores.js` (tintes, pieles, cabellos).
- `src/generador.js`: elige atuendo, colores y rasgos; inventa ropa nueva; arma paletas.
- `src/svg.js`: capas, degradados, patrones y sombras. Cada forma se define una vez y se reutiliza con `<use>`; todos los `id` de una figura llevan un prefijo propio, así que se pueden poner varias figuras en una misma página sin que choquen. El SVG resultante no lleva scripts, imágenes ni enlaces externos.

### Catálogo de colores por prenda y probabilidad de cada color

Cada prenda tiene su propio conjunto de colores posibles, cada uno con su **porcentaje**: los jeans solo salen en azules y grises, la camisa blanca casi siempre es blanca, el calzado va en cueros y negros, el vestido de novia en blancos y marfiles. Los porcentajes suman 100 por prenda y papel de color (`principal`, `secundario`, `ribete`, `detalle`).

```bash
node bin/generar.js --colores pantalon-vaquero            # azul denim 40 %, denim oscuro 25 %, denim claro 18 %…
node bin/generar.js --colores pantalon-vaquero --anio 1880  # solo los tintes que existían: azul denim 61,5 %, denim oscuro 38,5 %
node bin/generar.js --listar colores                      # los tres colores más probables de cada prenda
```

```js
import { distribucionColor, catalogoColores, generarPersona } from './src/index.js';
distribucionColor('pantalon-vaquero', 'principal', { anio: 1950 });   // [{ id, nombre, hex, pct, origen }, …]
catalogoColores('camisa-blanca');                                      // todos los papeles de color con datos
generarPersona({ atuendo: 'eeuu-vaquero' }).coloresPrendas;            // qué color salió en cada prenda y con qué probabilidad
```

De dónde salen los porcentajes:

- **Perfil propio** (`origen: "perfil …"`): la prenda tiene colores y pesos propios (`src/datos/colores-prendas.js`: `PERFILES` y las asignaciones por prenda). Es el caso de la ropa moderna y de las prendas universales (camisas, calzado, joyas, uniformes…). Los pesos son una **estimación razonada, no una estadística medida**: sirven para que la ropa se vea verosímil y para poder ver y cambiar con qué frecuencia sale cada color.
- **Atuendo** (`origen: "atuendo"`): las prendas propias de una cultura (el chamal mapuche, el kimono…) toman el color de la paleta del atuendo donde aparecen; su porcentaje se calcula con lo que el generador hace de verdad (el reparto entre los atuendos que la llevan y, dentro de cada uno, entre los tintes de su letra de la paleta).
- **Época:** con `anio` se descartan los tintes que no existían (el denim es posterior a 1873; las anilinas, a 1856).
- **Coherencia con el atuendo:** al elegir, los tintes que la paleta del atuendo ya usa pesan 2,5 veces más; el `pct` informado es el del catálogo, sin ese refuerzo.
- `colores: 'atuendo'` en las opciones (o `--colores-modo atuendo`) usa solo la paleta del atuendo; si das una `paleta` propia, tampoco se usan los perfiles.

### Cómo se estima a partir de la etnia

`src/datos/etnias.js` guarda, por pueblo o cultura: de qué regiones salen sus atuendos, el rango de tonos de piel, la distribución de colores de cabello, peinados habituales, frecuencia de barba, estatura media (mujer y hombre) y complexión. Con `etnia`:

- **Atuendo:** se busca en las regiones de esa etnia, para el `anio`, el `sexo` y la `clase`. Si no hay uno exacto se afloja un filtro a la vez (clase, año, pueblo, región) y **se avisa** en `persona.avisos`.
- **Piel y cabello:** se sortea dentro del rango de la etnia, con variación individual, y el cabello encanece con la edad.
- **Estatura:** media de la etnia y el sexo, ajustada por la edad (los niños son más bajos) y por la época (algo menor antes de 1800), con variación individual.
- **Mezcla:** con varias etnias la piel es el promedio ponderado, el cabello se sortea con los pesos de todas y la **primera manda en la ropa**.
- **Lo que des a mano manda:** `piel`, `cabello`, `altura`, `corpulencia`, `region`, `atuendo`… no se tocan, y `persona.estimaciones` solo cuenta lo que sí se estimó.

Son **promedios aproximados de poblaciones, no reglas sobre una persona**, y «etnia» aquí es cultura de origen, no una categoría biológica. Los pueblos que el catálogo no cubre con trajes propios (por ejemplo los afrodescendientes de las Américas) usan los más cercanos y lo dicen en `nota` o en los avisos.

### Agregar ropa

1. Una prenda nueva: una línea en `src/datos/prendas*.js` con la plantilla, sus parámetros y los papeles de color (`A` principal, `B` secundario, `C` ribete, `D` detalle). Para darle colores y porcentajes propios, agrégala a un grupo de `src/datos/colores-prendas.js` (o crea un perfil nuevo); si no, tomará el color del atuendo.
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

Las pruebas revisan que no haya bordes por defecto, que los degradados y las sombras estén en cada nivel de detalle, que los `id` no choquen entre figuras, que la pose varíe con la semilla y se mantenga en rangos naturales (mano en la cintura sobre la cadera, pierna libre más abierta, pose firme simétrica); que todo el catálogo de colores sume 100 % por prenda, que los jeans solo salgan en azules y grises, que el filtro por año quite los tintes que no existían, que el muestreo siga los porcentajes (4000 muestras) y que ninguna prenda quede sin usar; que cada etnia tenga datos válidos y que las estimaciones sigan a la etnia (estatura, piel, cabello), a la edad y al año, y avisen cuando el atuendo sea de otra región; que la mano en saludo quede del lado y a la altura correctos para todos los atuendos; que cada atuendo apunte a prendas, tintes y peinados que existen; que **todo atuendo** se dibuje bien a seis edades y complexiones (SVG bien formado, sin `NaN`, sin scripts ni referencias externas, rellenos definidos); que la misma semilla dé el mismo SVG; que los filtros se respeten; que la ropa nueva sea válida y variada; y que `dist/` dé exactamente lo mismo que `src/`.

## Confianza y revisión (para uso antropológico)

Cada atuendo y cada etnia trae un nivel de **confianza**: `media` es una aproximación razonable de un traje o un pueblo conocido; `baja` es una imagen genérica o muy simplificada (por ejemplo los pueblos amazónicos, los himba, los aborígenes australianos o los taínos, de los que el catálogo no tiene un traje concreto o las fuentes son escasas). Ninguno está marcado `alta`: nada de esto reemplaza una fuente primaria.

- `persona.confianza` es la menor de las del atuendo y las etnias; `persona.notas` trae las advertencias propias de cada entrada (por ejemplo que no se reproduce la pintura corporal himba ni el tā moko maorí). La galería las muestra.
- `node bin/generar.js --listar auditoria` lista todo lo marcado `baja`, para revisarlo con fuentes del propio pueblo o con sus miembros antes de publicar.
- `node bin/generar.js --listar etnias` y `--listar atuendos` incluyen la confianza.

Para un proyecto de genealogía conviene tratar la etnia como una **pista estadística** y no como un dato de la persona: sirve para ilustrar un linaje probable.

## Límites, dicho con honestidad

- Es **ilustración histórica aproximada**, no un estudio de indumentaria: las épocas son anchas y los trajes duraron más que las fechas. Los tintes traen época y costo para no vestir a un campesino medieval de púrpura de Tiro, pero no son una tesis.
- Algunas prendas son una **simplificación de forma** (por ejemplo la toga, el sari o el kimono se resuelven con plantillas generales de manto, falda y túnica cruzada).
- Los atuendos de pueblos vivos o con comunidades vivas (mapuche, selk'nam, rapanui, maasái, pueblos de las llanuras, maoríes, aborígenes australianos, himba, wixárika…) son una representación genérica; antes de usarlos en algo publicado conviene revisarlos con personas de esos pueblos o con fuentes de su propia mano. Los objetos y adornos ceremoniales o de rango (por ejemplo los tocados de plumas) se usan aquí como elementos de ilustración y no sustituyen su significado.
- No hay siluetas de pueblos de los que no existan trajes en el catálogo: si una etnia no tiene atuendo propio para el año pedido, el generador usa el más cercano y lo avisa en `persona.avisos`.
- Solo vista frontal, de pie, con una mano levantada. La pose casual varía lo que se puede ver de frente (inclinación, apoyo, brazo libre); no hay giros de torso ni perfiles.
- La **etnia** es una estimación estadística: sirve para ilustrar un linaje probable, no para afirmar cómo era una persona concreta.
