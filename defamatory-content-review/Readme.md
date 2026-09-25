# Módulo de Revisión de Contenido Difamatorio

[![Tests](https://github.com/lol-protocol/php/actions/workflows/tests.yml/badge.svg?branch=master)](https://github.com/lol-protocol/php/actions/workflows/tests.yml)

Detecta insultos, léxico soez y construcciones de ridiculización en nombres y
apellidos de personas, para plataformas de información genealógica.

- **11 tipos de riesgo** — no sólo *cuánto* ofende un término, sino *de qué modo*.
- **33 idiomas** identificados por código ISO 639-3, con ~6.400 términos.
- **Modelo de parentesco lingüístico** — validación cruzada entre lenguas
  emparentadas, con la coincidencia ponderada por su afinidad léxica.
- **Protección de apellidos legítimos** — «Cerda», «Moro» o «Savage» son linajes
  reales; nunca se rechazan en automático.
- **Detección de fusión fonética** — «Elba Gina» → «el vagina», «Felipe Lotas»
  → «Feli-pelotas»: nombre y apellido, ninguno ofensivo por separado, que al
  leerse seguidos componen otra palabra.

---

## Instalación

```bash
composer install
```

Requiere PHP >= 8.1 y la extensión `mbstring`.

## Uso

```php
use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/config', 'spa');

$result = $reviewer->validateFullName('Zoila', 'Cerda');
$report = $reviewer->getDetailedReport($result);

$report['severity'];   // medium
$report['decision'];   // review
$report['recommendation'];
// Revisión humana: coincide con términos ofensivos (animal) pero también
// con apellidos documentados.
```

---

## Tipos de riesgo

Cada término declara *qué clase* de agravio supone, además de su severidad.
Una comparación con un animal y un insulto étnico no son el mismo problema y
no deberían tratarse con el mismo procedimiento.

| Tipo | Qué recoge | Ejemplo (es) |
|---|---|---|
| `animal` | Comparación con animales | cerda, burro, sabandija |
| `intelectual` | Menoscabo de la capacidad mental | idiota, zopenco, subnormal |
| `fisico` | Menoscabo de la apariencia | adefesio, gordinflón, esperpento |
| `discapacidad` | Referencia despectiva a discapacidad | tullido, jorobado, cegato |
| `moral` | Imputación moral o delictiva | bastardo, canalla, estafador |
| `genero` | Insulto por género u orientación | maricón, furcia, marimacho |
| `ordinario` | Léxico soez u obsceno | mierda, gilipollas, coño |
| `burlesco` | Burla y ridiculización | vejestorio, mamarracho, **zurdo** |
| `etnico` | Insulto étnico o racial | sudaca, negrata, charnego |
| `religioso` | Insulto religioso | hereje, blasfemo, endemoniado |
| `fonetico` | Fusión de nombre y apellido en otra palabra | Elba Gina → «el vagina» |

Las definiciones viven en `config/risk-categories.php`.

### Severidad y decisión

Cada palabra trae su propia severidad (`low` / `medium` / `high`); por defecto,
la del nombre completo es la del **peor** término hallado (no la suma de
todos). La severidad se traduce luego en una acción:

| Severidad | Decisión | Salvo que… |
|---|---|---|
| `none` | `accept` | |
| `low` | `accept_with_flag` | |
| `medium` | `review` | |
| `high` | `reject` | haya colisión con apellido, o la detección sea sólo fonética → `review` |

Ninguno de estos números está grabado en la clase del motor: pesos,
cortes de banda, reglas de decisión y modo de agregación viven en un
objeto `ScoringPolicy` aparte, pensado para ajustarse sin tocar
`DefamatoryContentReviewer`. Ver la sección **Ajustar la sensibilidad del
filtro (`ScoringPolicy`)** más abajo.

### Ridiculización

Algunos rasgos no son insulto por sí solos y sólo ofenden usados como mote —
sobre todo combinados de forma contradictoria, como el «Zurdo Diestro» clásico.
Van marcados `burlesco` con severidad `low`: se señalan sin bloquear.

```php
$reviewer->validateFullName('Zurdo', 'Diestro')->getSeverity();  // low
$reviewer->decide($result);                                      // accept_with_flag
```

### Fusión fonética: cuando el agravio vive en la unión

Hay un fenómeno bien documentado de humor —y a veces difamación— con nombres:
"Elba Gina", "Felipe Lotas", "Dolores Delano", "Susana Oria". Ninguno de los
dos campos es ofensivo por separado; leídos seguidos, sin la pausa que marca
dónde termina uno y empieza el otro, forman una palabra distinta:

```
Elba Gina     → "elvagina"   → contiene "vagina"
Felipe Lotas  → "felipelotas" → contiene "pelotas"
Susana Oria   → "susanaoria"  → contiene "zanahoria" (z/s y h muda se pliegan igual)
```

El módulo lo detecta plegando cada campo a una forma fonética aproximada
(`SpanishPhoneticFolder`: unifica b/v, s/z/c suave, ll/y, h muda, y el sonido de j y
de g suave) y buscando términos del diccionario que **crucen la frontera**
entre el nombre y el apellido plegados. Además, la coincidencia tiene que
leerse como tal: debe **tocar el principio o el final** del nombre completo y
tomar **al menos 2 letras de cada lado**. Un término enterrado en medio
(«Emine Kaya» → «inek», «Siti Kusuma» → «tikus») o que sólo roza la unión
(«Ana O…» → «anão») no se percibe al leer, y disparaba con nombres reales
comunes (ver `CommonNamesFalsePositiveTest`):

```php
$result = $reviewer->validateFullName('Elba', 'Gina');

$result->isValid();                     // false
$result->hasOnlyPhoneticDetections();   // true — es inferencia, no coincidencia literal
$result->getPhoneticFusionTerms();      // [{term: 'vagina', riskType: 'ordinario', ...}]

$reviewer->decide($result);             // 'review' — nunca 'reject' sólo por esto
```

**La exigencia de cruce es la salvaguarda.** "ano" cae entero dentro de
«Mariano», «Luciano», «Adriano», «Cristiano», «Emiliano» — apellidos y nombres
perfectamente corrientes. Sin esa exigencia, cualquiera de ellos dispararía
una alerta falsa. El detector sólo cuenta una coincidencia si empieza antes de
la frontera entre los dos campos y termina después:

```php
// "ano" aparece en "Mariano", pero entero dentro del apellido: no cruza.
$reviewer->validateFullName('Juan', 'Mariano')->isValid();  // true

// "vagina" aparece a caballo entre "Elba" y "Gina": sí cruza.
$reviewer->validateFullName('Elba', 'Gina')->isValid();     // false
```

También cubre variantes ortográficas de un solo campo que suenan igual a un
término del diccionario (evasivas o no): "Cojes" frente a la entrada "Coges".

```php
$reviewer->validateFullName('Paco', 'Cojes');
// getPhoneticVariantTerms() — no es fusión: cae entero en el apellido,
// pero con otra grafía del mismo sonido.
```

### Diecisiete idiomas, diecisiete fonéticas distintas

Cubre los 17 idiomas en script latino de los 33 soportados — cada uno con su
propio folder (`SpanishPhoneticFolder`, `PortuguesePhoneticFolder`,
`ItalianPhoneticFolder`, `FrenchPhoneticFolder`, `GermanPhoneticFolder`,
`CzechPhoneticFolder`, `SlovakPhoneticFolder`, `DanishPhoneticFolder`,
`NorwegianPhoneticFolder`, `SwedishPhoneticFolder`, `FinnishPhoneticFolder`,
`HungarianPhoneticFolder`, `IndonesianPhoneticFolder`,
`TurkishPhoneticFolder`, `PolishPhoneticFolder`, `DutchPhoneticFolder`,
`RomanianPhoneticFolder`), seleccionado por `PhoneticFolderRegistry` según el
idioma activo. No son la misma clase con distinto nombre: cada fonética
tiene sus propias confusiones reales, y aplicar las reglas de una a otra
produciría colisiones falsas.

**Las cinco fonéticas originales:**

| | español | portugués | italiano | francés | alemán |
|---|---|---|---|---|---|
| b/v | se confunden | **no** se confunden | **no** se confunden | **no** se confunden | **no** se toca (bimodal: "Vater"/"Vase") |
| s/z/c suave | se confunden | se confunden | **no** se confunden | ç/ce/ci/cy → s | — |
| h | muda | muda | endurece c/g (che, chi) — nunca se borra | siempre muda | **no** se toca (bimodal: alarga vocal o inicial audible) |
| g suave / j | se aspira, se borra | sonido audible, se unifica (no se borra) | — | sonido audible, se unifica (no se borra) | — |
| otras reglas propias | — | — | — | qu→k, ph→f, protege "ch" (sonido "sh") | ä/ö/ü/ß → grafía alternativa real (ae/oe/ue/ss); w→v sin excepciones |

**Los doce idiomas añadidos después**, cada uno con una sola ambigüedad (o
un par) verdaderamente sistemática — casi siempre la que un hablante nativo
aprendió de memoria en la escuela porque el oído no la resuelve solo:

| Idioma | Qué pliega | Por qué es real |
|---|---|---|
| Checo (`ces`) | y/ý → i/í | Suenan idéntico; cuál se escribe es una regla histórica, no fonética |
| Eslovaco (`slk`) | y/ý → i/í | Misma ambigüedad que el checo |
| Danés (`dan`) | æ/ø/å → ae/oe/aa | Grafía alternativa real, no aproximación (å era literalmente "aa") |
| Noruego (`nor`) | æ/ø/å → ae/oe/aa | Misma convención histórica que el danés |
| Sueco (`swe`) | ä/ö/å → ae/oe/aa | Misma convención histórica |
| Finlandés (`fin`) | ä/ö → a/o | A diferencia del alemán, no hay dígrafo codificado: la práctica real es simplificar a la vocal base |
| Húngaro (`hun`) | ly → j; ő/ű → ö/ü | "ly" y "j" suenan igual (regla histórica); ő/ű son sólo la versión larga de ö/ü |
| Indonesio (`ind`) | oe → u; dj/tj/nj/sj/ch/j → j/c/ny/sy/kh/y | Reforma ortográfica de 1972 — "Soekarno"/"Sukarno" es el ejemplo de manual |
| Turco (`tur`) | ı/ş/ç/ö/ü/ğ → i/s/c/o/u/g | Sustitución real y extendida cuando no hay teclado turco ("Erdoğan"→"Erdogan") |
| Polaco (`pol`) | ó → u; rz/ż y ch/h se unifican | Los tres pares suenan idéntico; cuál se escribe es etimología, no sonido |
| Neerlandés (`nld`) | ei/ij se unifican; au/ou se unifican | La confusión ortográfica más famosa del idioma — ambos pares son homófonos reales |
| Rumano (`ron`) | â/î se unifican; ş/ţ → ș/ț | â/î suenan idéntico (posición, no sonido); ş/ţ son variantes de codificación de la misma letra |

Quedan fuera a propósito: el **inglés** (script latino, pero sin la grafía
alternativa real y consolidada que sí tiene el resto de esta tabla — la
distancia entre sonido y letra es irregular caso por caso, y los chistes de
fusión conocidos en inglés, "Mike Rotch", dependen de un parecido fonético
aproximado, no de una equivalencia ortográfica verificable); el
**vietnamita** (script latino, pero el tono es fonémico — no hay grafía
alternativa que plegar sin colapsar significados); y los once idiomas en
script no latino (árabe, búlgaro, griego, hebreo, hindi, japonés, coreano,
ruso, tailandés, ucraniano, chino) — el mecanismo de plegar sustituyendo
caracteres no tiene un equivalente verificable sin una romanización propia,
y sin un hablante nativo que confirme cada regla sería inventar, no
normalizar.

```php
$reviewer = DefamatoryContentReviewer::create($configDir, 'pol');
$reviewer->languages()->wordList('pol')->supportsPhoneticFolding();  // true
$reviewer->languages()->wordList('eng')->supportsPhoneticFolding();  // false — sin reglas registradas
```

Añadir un idioma nuevo a la fusión fonética es añadir su folder y una fila
en `PhoneticFolderRegistry::FOLDERS` — nada más cambia.

### Fusión literal en idiomas sin reglas fonéticas

La fusión en sí no necesita reglas fonéticas: es unir nombre y apellido y
buscar un término que cruce la unión. Para 9 idiomas sin folder (ruso,
ucraniano, búlgaro, griego, hindi, coreano, islandés, swahili y tagalo),
`FusionSupport` la hace sobre la misma forma normalizada que usa la búsqueda
literal — sin variantes fonéticas de un solo campo, que ahí duplicarían la
búsqueda literal. Con eso la fusión cubre **26 de los 33 idiomas**.

Cada idioma entró sólo tras un barrido de combinaciones de nombres reales
comunes con cero falsos positivos. Quedaron fuera los que no lo pasaron o no
pueden pasarlo:

| Excluido | Motivo |
|---|---|
| Inglés | Falso positivo real que la regla de lectura no evita: «Dustin King» → «stinking» (anclado, 4 letras por lado). «Chris Hitt» → «shit» ya no dispararía |
| Árabe | «محمد منصور» → «مدمن» ya no dispararía con la regla de lectura, y el corpus de prueba da cero falsos positivos, pero es pequeño (144 combinaciones) para un idioma sin vocales escritas: falta un corpus mayor y revisión nativa |
| Hebreo | Como el árabe, no escribe vocales: las uniones forman palabras con demasiada facilidad |
| Japonés, tailandés, cantonés | Sin espacios entre palabras; el umbral de longitud está pensado para alfabetos |
| Vietnamita | El tono distingue palabras y la normalización colapsa parte de los tonos |

```php
$reviewer = DefamatoryContentReviewer::create($configDir, 'rus');
$reviewer->validateFullName('Сво', 'Лочь')->getPhoneticFusionTerms();  // «сволочь»
FusionSupport::isSupported('ara');                                      // false
```

### Evasión cubierta y no cubierta

- **Transliteración numérica** ("c3rda", "v4g1na"): cubierta. `WordList::normalize()`
  sustituye los dígitos/símbolos de un solo carácter más comunes
  (`0→o 1→i 3→e 4→a 5→s 7→t 8→b @→a $→s`) antes de comparar, y los 17
  folders fonéticos hacen lo mismo antes de plegar — por eso también se
  detecta combinada con la fusión: `validateFullName('Elb4', 'G1na')` marca
  "vagina" igual que la versión sin dígitos.
- **Variantes ortográficas estándar de scripts no latinos**: cubierta.
  `ScriptFolding` iguala las formas que los propios hablantes tratan como
  equivalentes: griego en mayúsculas («ΜΑΛΑΚΑΣ», sin tonos y con sigma
  medial — lo normal en registros genealógicos), ruso con «е» por «ё»
  («козел»), árabe con kashida, harakat, alef sin hamza, «ى»/«ي» y
  «ة»/«ه», y hebreo con niqqud. Antes todas estas formas pasaban.
- **Apellidos compuestos con guion o apóstrofo** ("Pérez-García", "O'Brien"):
  cubierta. La búsqueda literal ya los separaba en tokens; los folders
  fonéticos ahora también descartan el guion/apóstrofo (antes quedaba
  literal en la forma plegada y rompía el cálculo de la frontera de fusión).
- **Variantes por distancia de edición** ("Cerrda", "Certa"): **deliberadamente
  no cubierta**. Colapsar letras dobles cerraría este caso, pero across
  ~6.400 palabras en 33 idiomas no hay forma de verificar a mano qué
  colisiones no deseadas produciría — "Serrano" (apellido real) se volvería
  "Serano", y así con cada doble letra en cada idioma. Se documenta como
  límite en vez de implementarse a medias.
- **Re-segmentación dentro de un único campo** («Delano» → «de ano»):
  deliberadamente no cubierta, por la misma razón que arriba (ver la sección
  de fusión fonética): exigir el cruce de frontera es lo que evita los falsos
  positivos masivos.

Es inferencia, no un insulto literal: por eso `decide()` nunca usa sola una
detección fonética o de evasión numérica para rechazar, sólo para marcar en
revisión.

---

## Idiomas

Identificados por **ISO 639-3** (tres letras). Los códigos de dos letras se
aceptan como alias de entrada y se normalizan al entrar, de modo que una
integración existente puede seguir pasando `es` o `pt`.

```php
$registry->resolve('es');   // 'spa'
$registry->resolve('zh');   // 'yue'
$registry->resolve('SPA');  // 'spa'
```

| Familia | Idiomas |
|---|---|
| Romance | `spa` `por` `fra` `ita` `ron` |
| Germánica | `eng` `deu` `nld` `swe` `dan` `nor` `isl` |
| Eslava | `rus` `ukr` `bul` `pol` `ces` `slk` |
| Urálica | `fin` `hun` |
| Semítica | `ara` `heb` |
| Austronesia | `ind` `tgl` |
| Otras (una familia cada una) | `ell` `tur` `hin` `jpn` `kor` `yue` `tha` `vie` `swa` |

### Cobertura de los diccionarios

`coverage` no es cosmético: dice dónde hace falta revisión de hablante nativo
antes de usar el módulo en producción para ese idioma. ~6.400 términos en
total; sólo 3 idiomas (`isl`, `swa`, `tgl` — los últimos en incorporarse)
siguen en `basic`.

Vive en un único sitio — `meta.coverage` dentro del propio archivo de cada
idioma — y se consulta a través de `DefamatoryContentReviewer`, no del
registro de idiomas: `LanguageRegistry` es sólo identidad y parentesco
(nombre, alias, familia), nunca estado de contenido. Guardarlo dos veces fue
justamente el bug que motivó este diseño: las dos copias se desincronizaron
la primera vez que sólo una de ellas se actualizó.

| Nivel | Idiomas | Términos c/u |
|---|---|---|
| `comprehensive` | spa, eng, por, fra, ita, deu (6) | 200 – 418 |
| `moderate` | ron, nld, swe, dan, nor, rus, ukr, pol, ces, slk, bul, ell, hun, fin, tur, ara, heb, hin, jpn, kor, yue, tha, vie, ind (24) | 120 – 160 |
| `basic` | isl, swa, tgl (3) | 60 – 119 |

```php
$reviewer->languages()->byCoverage('moderate');  // los candidatos a comprehensive
```

Un test de integridad (`DictionaryIntegrityTest`) exige que todo idioma
declarado `moderate` tenga ≥120 términos y `comprehensive` ≥200: subir el
nivel es responder por un mínimo verificable, no una etiqueta.

Los idiomas sin separación por espacios (`jpn`, `yue`, `tha`) declaran
`requiresTokenizer`: para texto libre necesitan un segmentador externo
(MeCab, jieba) antes de consultar el diccionario. Para nombres ya separados en
campos no hace falta.

---

## Parentesco entre idiomas

Los registros genealógicos de una región traen apellidos de las lenguas
vecinas: un árbol español contiene ramas portuguesas, uno ruso ramas ucranianas.
El módulo modela ese parentesco y puede validar contra el idioma principal
**y sus asociados a la vez**.

```php
// "porco" es portugués, no español: sólo aparece al cruzar.
$reviewer->validateName('João Porco')->isValid();          // true
$reviewer->related()->validate('João Porco')->isValid(); // false
```

Una coincidencia hallada en un idioma asociado **pesa menos** que una del
principal: su confianza es la afinidad léxica entre ambos, y esa confianza
descuenta la severidad. Un insulto grave en italiano marca un nombre español
para revisión, pero no lo rechaza con la rotundidad de uno en español.

```php
$term = $reviewer->related()->validate('Marco Stronzo')->getFlaggedTerms()[0];

$term['sourceLanguage'];  // 'ita'
$term['confidence'];      // 0.82
$term['severity'];        // 'high' en italiano…
// …pero la severidad del nombre baja a 'medium' por el descuento.
```

### Afinidades

Aproximaciones basadas en estimaciones publicadas de similitud léxica e
inteligibilidad mutua. No son medidas exactas: son el peso relativo con el que
decidir qué diccionarios consultar y cuánto fiarse de lo que encuentren.

| Par | Afinidad | Par | Afinidad |
|---|---|---|---|
| `ces` ↔ `slk` | 0.91 | `spa` ↔ `por` | 0.89 |
| `dan` ↔ `nor` | 0.90 | `fra` ↔ `ita` | 0.89 |
| `swe` ↔ `nor` | 0.88 | `rus` ↔ `ukr` | 0.86 |
| `deu` ↔ `nld` | 0.84 | `spa` ↔ `ita` | 0.82 |
| `eng` ↔ `nld` | 0.63 | `eng` ↔ `deu` | 0.60 |

Se incluyen también pares sin parentesco directo pero con préstamo intenso
(`jpn`↔`yue`, `ron`↔`bul`, `tur`↔`ell`), con afinidad baja.

```php
$registry->getAffinity('spa', 'por');   // 0.89 — simétrico
$registry->getRelated('rus');           // ['ukr'=>0.86, 'bul'=>0.74, …]
$registry->getFamilyMembers('ces');     // ['rus','ukr','bul','pol','slk']
```

El umbral por defecto (0.60) se ajusta por llamada:

```php
$reviewer->related()->validate($name, 0.85);  // sólo los muy cercanos
```

O se salta el modelo por completo cuando ya se sabe qué lenguas concurren:

```php
$reviewer->validateInLanguages('Hans Scheisse', ['spa', 'deu']);  // ambas al 1.0
```

---

## Apellidos legítimos

El riesgo real de una lista negra en genealogía es borrar linajes reales.
**Cerda** es la casa de la Cerda; **Moro**, **Calvo**, **Bravo**, **Vaca**,
**Savage**, **Hogg** y **Bastard** son apellidos documentados.

Esos términos llevan `nameCollision => true`. Siguen detectándose y siguen
puntuando, pero **nunca bastan por sí solos para rechazar**: la decisión baja a
revisión humana.

```php
$result = $reviewer->validateFullName('Juan', 'Moro');

$result->getSeverity();       // 'high'
$result->hasNameCollision();  // true
$reviewer->decide($result);   // 'review'  ← no 'reject'
```

---

## Ajustar la sensibilidad del filtro (`ScoringPolicy`)

**¿Filtro binario o un puntaje tipo spam?** Ninguna de las dos cosas
exactamente. Cada término marcado tiene un puntaje numérico (0.0 por
defecto para `none`, 3.0 para `high`), pero por defecto el puntaje del
nombre completo es el **del peor término encontrado, no la suma de
todos** — diez coincidencias de baja severidad no se combinan en una
severidad alta, a diferencia de un filtro bayesiano de spam donde muchas
señales débiles sí se acumulan hasta cruzar un umbral. Ese puntaje se
discretiza luego en 4 bandas (`none`/`low`/`medium`/`high`) y una tabla de
reglas pequeña las traduce en una decisión.

Nada de eso está fijo en `DefamatoryContentReviewer`: vive en un objeto
`ScoringPolicy` inmutable que el motor recibe opcionalmente.
`ScoringPolicy::default()` reproduce exactamente el comportamiento de
siempre — no pasar ninguno es idéntico a antes de que esta clase existiera.

```php
use DefamatoryContentReview\ScoringPolicy;

// Pesos por severidad, por defecto none=0 / low=1 / medium=2 / high=3.
$propia = ScoringPolicy::default()->withSeverityWeights([
    'none' => 0.0, 'low' => 0.5, 'medium' => 2.0, 'high' => 5.0,
]);

// Multiplicador por tipo de riesgo: subir la sensibilidad a 'etnico' sin
// tocar la severidad de cada palabra individual.
$propia = $propia->withRiskTypeWeight('etnico', 1.5);

// Bandas propias — tantas como haga falta, no sólo 3.
$propia = $propia->withBands([
    [4.0, 'high'], [2.0, 'medium'], [0.5, 'low'],
]);

// Qué decisión corresponde a cada banda.
$propia = $propia->withDecisionRules([
    'none' => 'accept', 'low' => 'accept_with_flag',
    'medium' => 'review', 'high' => 'reject',
]);

// En qué bandas una colisión de apellido o una detección puramente
// fonética degradan 'reject' a 'review' (por defecto, sólo 'high').
$propia = $propia->withPhoneticCapLabels(['high', 'medium']);

// 'max' (por defecto, el peor término manda) o 'sum' (se acumulan todos
// los términos — más parecido a un filtro de spam aditivo, a costa de
// que muchos términos leves puedan superar a uno solo grave).
$propia = $propia->withAggregation('sum');

$reviewer = DefamatoryContentReviewer::create($configDir, 'spa', $propia);
// o sobre uno ya creado:
$reviewer->setPolicy($propia);
```

Cada `with*()` devuelve una copia; la política original no cambia. El
puntaje numérico crudo (antes de discretizar) queda disponible en el
resultado, por si se prefiere un umbral propio en vez de las bandas:

```php
$result = $reviewer->validateName('Cerda');
$result->getScore();  // 2.0 — el peso de 'medium', el severidad declarada de "Cerda"
```

El resguardo contra rechazo automático (`nameCollision` / detección sólo
fonética) sigue aplicando aunque se reconfiguren bandas y reglas: sólo se
activa cuando la regla resuelta es `reject` y la banda está en
`phoneticCapLabels` — nunca desaparece por accidente al personalizar otra
cosa, hay que sacarlo explícitamente con `withPhoneticCapLabels([])`.

---

## API

### `DefamatoryContentReviewer`

```php
DefamatoryContentReviewer::create(string $configDir, string $language = 'spa', ?ScoringPolicy $policy = null): self
```

| Método | Devuelve |
|---|---|
| `validateName(string $name)` | `ValidationResult` |
| `validateFullName(string $first, string $last)` | `ValidationResult` |
| `validateInLanguages(string $name, array $languages)` | `ValidationResult` |
| `batchValidateNames(array $names)` | `ValidationResult[]` |
| `batchValidateFullNames(array $names)` | `ValidationResult[]` |
| `decide(ValidationResult $r)` | `accept` \| `accept_with_flag` \| `review` \| `reject` |
| `getDetailedReport(ValidationResult $r)` | `array` |
| `getLanguage()` / `setLanguage(string $l)` | `string` / `self` |
| `getPolicy()` / `setPolicy(ScoringPolicy $p)` | `ScoringPolicy` / `self` |
| `languages()` | `LanguageAccess` — diccionarios y cobertura |
| `related()` | `RelatedLanguageValidator` — validación contra idiomas emparentados |

Los métodos que antes vivían directo en `DefamatoryContentReviewer` para
idiomas emparentados y acceso a diccionarios ahora cuelgan de dos objetos
más chicos — mismo comportamiento, distinta puerta de entrada:

```php
// antes                                          ahora
$reviewer->validateAcrossRelated($n, $t);          $reviewer->related()->validate($n, $t);
$reviewer->validateFullNameAcrossRelated($f,$l,$t); $reviewer->related()->validateFullName($f,$l,$t);
$reviewer->getRelatedLanguages($t);                 $reviewer->related()->languages($t);
$reviewer->getRegistry();                           $reviewer->languages()->registry();
$reviewer->getWordList($code);                      $reviewer->languages()->wordList($code);
$reviewer->getWordListStatistics($code);            $reviewer->languages()->statistics($code);
$reviewer->getLanguagesByCoverage($level);           $reviewer->languages()->byCoverage($level);
```

### `ValidationResult`

| Método | Devuelve |
|---|---|
| `isValid()` / `getSeverity()` | `bool` / `string` |
| `getScore()` | `float` — puntaje crudo antes de discretizar en severidad |
| `getFlaggedTerms()` | términos con `riskType`, `severity`, `sourceLanguage`, `confidence`, `nameCollision` |
| `getFlaggedRiskTypes()` / `getFlaggedCategories()` | `string[]` |
| `getTermsByRiskType(string $t)` | `array` |
| `getTermsByLanguage(string $l)` / `getPrimaryLanguageTerms()` | `array` |
| `hasNameCollision()` / `getNameCollisionTerms()` | `bool` / `array` |
| `hasOnlyPhoneticDetections()` | `bool` |
| `getPhoneticFusionTerms()` / `getPhoneticVariantTerms()` | `array` |
| `getTermsByDetectionMethod(string $m)` | `array` (`'literal'` \| `'phonetic_fusion'` \| `'phonetic_variant'`) |
| `getLanguagesChecked()` | `array<string,float>` |
| `getExplanations()` | `string[]` — una frase por término: qué coincidió, con qué entrada, idioma, tipo y severidad |
| `toArray()` | `array` (incluye `explanations`) |

Cada término de `getFlaggedTerms()` trae también `matchedEntry` (la forma del
diccionario que coincidió) y, en fusiones, `fusedFrom` (el nombre completo):

```php
$reviewer->validateFullName('Elba', 'Gina')->getExplanations();
// ['«Elba Gina» leído seguido suena como «vagina» (diccionario spa, tipo ordinario, severidad medium).']
```

### `ScoringPolicy`

```php
ScoringPolicy::default(): self   // pesos none=0/low=1/medium=2/high=3, cortes 1.5/2.5, agregación 'max'
```

| Método | Devuelve |
|---|---|
| `withSeverityWeights(array $w)` | `self` (copia) — peso por etiqueta de severidad |
| `withRiskTypeWeight(string $t, float $w)` / `withRiskTypeWeights(array $w)` | `self` — multiplicador por tipo de riesgo |
| `withHighSeverityRiskTypes(array $types)` | `self` — respaldo cuando la palabra no declara severidad |
| `withBands(array $bands)` | `self` — pares `[umbral, etiqueta]`, tantos como se quiera |
| `withDecisionRules(array $rules)` | `self` — etiqueta de severidad → decisión |
| `withPhoneticCapLabels(array $labels)` | `self` — en qué etiquetas `nameCollision`/sólo-fonético bajan `reject` a `review` |
| `withAggregation('max' \| 'sum')` | `self` — el peor término, o la suma de todos |
| `scoreOf(array $match)` / `aggregate(array $scores)` | `float` |
| `severityFromScore(float $s)` / `decisionFor(...)` | `string` |
| `getSeverityWeights()` / `getRiskTypeWeights()` / `getBands()` / `getDecisionRules()` / `getPhoneticCapLabels()` / `getAggregation()` | introspección |

### `LanguageAccess` (`$reviewer->languages()`)

| Método | Devuelve |
|---|---|
| `registry()` | `LanguageRegistry` |
| `wordList(string $code)` | `WordList` |
| `statistics(string $code)` | `array` |
| `byCoverage(string $level)` | `string[]` — única fuente: `WordList::getCoverage()` de cada idioma |

### `RelatedLanguageValidator` (`$reviewer->related()`)

| Método | Devuelve |
|---|---|
| `validate(string $name, ?float $threshold = null)` | `ValidationResult` |
| `validateFullName(string $first, string $last, ?float $threshold = null)` | `ValidationResult` |
| `languages(?float $threshold = null)` | `array<string,float>` idiomas asociados => afinidad |

### `LanguageRegistry`

| Método | Devuelve |
|---|---|
| `resolve(string $code)` | ISO 639-3; lanza `InvalidArgumentException` si no existe |
| `isSupported(string $code)` | `bool` |
| `getAffinity(string $a, string $b)` | `float` (simétrico; 1.0 consigo mismo) |
| `getRelated(string $c, ?float $t = null)` | `array<string,float>` ordenado desc. |
| `getValidationSet(string $c, ?float $t = null)` | el idioma + sus asociados |
| `getFamily()` / `getFamilyMembers()` / `getFamilies()` | rama genealógica |
| `getMetadata()` / `getAll()` / `getCodes()` | catálogo (identidad, no estado de contenido) |

### `WordList`

`search()`, `findInText()` (detecta frases de hasta 3 palabras),
`getByRiskType()`, `getByCategory()`, `getBySeverity()`, `getNameCollisions()`,
`getStatistics()`, `requiresTokenizer()`, `getCoverage()`,
`supportsPhoneticFolding()`, `fold()`, `searchPhoneticExact()`,
`getFusionCandidates()`.

### `PhoneticFusionDetector` / folders fonéticos

| Método | Devuelve |
|---|---|
| `PhoneticFolderRegistry::isSupported(string $lang)` | si ese idioma tiene reglas registradas |
| `PhoneticFolderRegistry::fold(string $lang, string $text)` | forma fonética canónica para ese idioma |
| `detectFusion(string $first, string $last)` | coincidencias que cruzan la frontera (vacío si el idioma no tiene reglas) |
| `detectVariant(string $word)` | coincidencia fonética exacta de un campo completo |

---

## Estructura

```
src/DefamatoryContentReview/
├── DefamatoryContentReviewer.php   Facade: construcción y validación en el idioma principal
├── NameEvaluator.php               Coincidencias literales y fusión fonética (interno)
├── RiskReportBuilder.php           Arma getDetailedReport() (interno)
├── LanguageAccess.php              Diccionarios y cobertura — $reviewer->languages()
├── RelatedLanguageValidator.php    Validación entre idiomas emparentados — $reviewer->related()
├── LanguageRegistry.php            Identidad de idiomas (códigos, alias, familias)
├── LanguageAffinity.php            Afinidad léxica — colaborador de LanguageRegistry
├── WordList.php                    Diccionario: carga, normalización, búsqueda
├── WordListIndex.php / WordListPhonetics.php   Colaboradores de WordList (almacén, plegado)
├── WordListScanner.php             Búsqueda de términos en texto — colaborador de WordList
├── AccentFolding.php               Plegado de diacríticos compartido por WordList
├── ScriptFolding.php               Variantes estándar de griego, cirílico, árabe y hebreo
├── ScoringPolicy.php               Orquesta pesos/bandas/decisión (configurable)
├── ScoringWeights.php / SeverityBands.php / DecisionTable.php   Colaboradores de ScoringPolicy
├── SpanishPhoneticFolder.php       Plegado fonético del español
├── PortuguesePhoneticFolder.php    Plegado fonético del portugués
├── ItalianPhoneticFolder.php       Plegado fonético del italiano
├── FrenchPhoneticFolder.php        Plegado fonético del francés
├── GermanPhoneticFolder.php        Plegado fonético del alemán
├── Czech…RomanianPhoneticFolder.php  Los otros 12 idiomas latinos (ver tabla arriba)
├── AccentOnlyPhoneticFolding.php   Wiring compartido por los folders sin reglas propias además de acentos
├── Leetspeak.php / LeetspeakFolding.php   Sustitución numérica compartida por los folders
├── PhoneticFolderRegistry.php      Qué idioma usa qué folder
├── FusionSupport.php               Qué idiomas tienen fusión (fonética o literal) y por qué no el resto
├── PhoneticFusionDetector.php      Fusión nombre+apellido y variantes ortográficas
├── ValidationResult.php            Resultado con trazabilidad por idioma y método
├── FlaggedTermCollection.php       Términos marcados y sus consultas — colaborador de ValidationResult
└── TermExplanation.php             Frase legible de por qué se marcó cada término

config/
├── risk-categories.php             Los 11 tipos de riesgo
├── language-families.php           Familias y afinidades
└── languages/
    ├── supported-languages.php     Catálogo ISO 639-3 + alias 639-1
    └── spa.php eng.php por.php …   33 diccionarios

tests/
└── fixtures/common-names.php       Nombres reales comunes por idioma (falsos positivos y benchmark)
examples/                           Ejecutados por ExamplesRunTest
bin/benchmark.php                   Nombres validados por segundo, por idioma
```

Ningún archivo de `src/`, `tests/`, `examples/` o `bin/` supera 100 líneas
(lo verifica `FileSizeLimitTest`) — cuando una clase
crece más allá de eso, se descompone en colaboradores internos (mismo
patrón en todo el proyecto: `ScoringPolicy`/`WordList`/`ValidationResult`/
`LanguageRegistry` conservan su API pública intacta; sólo
`DefamatoryContentReviewer` se dividió rompiendo API, en objetos propios
como `languages()`/`related()`, porque ahí no alcanzaba con recomponer
internamente). `config/languages/*.php` queda exento: son diccionarios de
datos, no lógica.

## Añadir un idioma

1. Crear `config/languages/<iso639-3>.php`:

```php
return [
    'meta' => [
        'code' => 'cat', 'iso639_1' => 'ca', 'name' => 'Catalan',
        'nativeName' => 'Català', 'family' => 'romance', 'coverage' => 'basic',
    ],
    'words' => [
        'animal' => [
            ['word' => 'porc', 'riskType' => 'animal', 'severity' => 'medium'],
        ],
    ],
];
```

2. Registrarlo en `config/languages/supported-languages.php`.
3. Añadirlo a su familia y declarar afinidades en `config/language-families.php`.

Los tests verifican automáticamente que todo idioma registrado tenga
diccionario, que declare su propio código y que use tipos de riesgo y
severidades válidos.

## Ampliar un diccionario existente

Añadir entradas en la categoría que corresponda y subir `coverage` cuando el
idioma quede cubierto en las once categorías de riesgo. Marcar
`nameCollision => true` en todo término que también sea nombre o apellido
documentado — es lo que evita que la lista negra borre linajes reales.

Los 24 diccionarios en nivel `moderate` necesitan sobre todo revisión de
hablantes nativos, no más palabras — ver [`Contributing.md`](Contributing.md)
para el proceso y qué verifica `DictionaryIntegrityTest` en cada cambio.

## Tests

```bash
./vendor/bin/phpunit          # tests, ejemplos, límite de líneas, falsos positivos
phpstan analyse               # análisis estático, nivel 5 (phpstan.neon.dist)
php bin/benchmark.php 10000   # rendimiento: comparar antes/después en la misma máquina
```

El CI corre los tres en PHP 8.1–8.4. `CommonNamesFalsePositiveTest` cruza
nombres y apellidos reales frecuentes de cada idioma con fusión (y de inglés
y árabe, para la búsqueda literal) y exige cero detecciones: si un término o
una regla nueva marca un linaje real, falla ahí.

---

## Limitaciones

- La detección literal es por término completo o frase de hasta tres palabras;
  no encuentra palabras incrustadas dentro de una sola palabra sin cruce de
  frontera (ver la sección de fusión fonética más arriba). La transliteración
  numérica de un solo carácter sí se cubre (ver «Evasión cubierta y no
  cubierta»).
- La fusión cubre 26 de los 33 idiomas: 17 con plegado fonético
  (`PhoneticFolderRegistry`) y 9 con fusión literal (`FusionSupport`).
  Quedan fuera inglés, árabe, hebreo, japonés, tailandés, cantonés y
  vietnamita (motivos en la sección «Fusión literal»). La fusión exige que
  el término se lea al principio o al final del nombre completo, así que
  un chiste con el término en medio no se detecta: es el precio de no
  marcar nombres reales como «Emine Kaya». En coreano la
  longitud mínima se cuenta en sílabas, así que sólo alcanza a los
  términos más largos. En todos, sólo cubre el cruce entre nombre y
  apellido, no la re-segmentación dentro de un único campo.
- 3 diccionarios (`isl`, `swa`, `tgl`) siguen en `basic`, y los 24 en
  `moderate` siguen necesitando revisión de hablante nativo antes de
  producción — es una base verificable, no una traducción exhaustiva.
- El árabe dialectal y las variedades regionales del chino no están cubiertos.
- La distancia de edición («Cerrda») no está cubierta: ver el docblock de
  `EvasionTest::testEditDistanceEvasionIsADeliberateGap()` para el porqué.
- Las afinidades son aproximaciones, no medidas.
- El módulo no decide por la plataforma: `decide()` propone, y los casos
  `review` requieren persona.

## Licencia

MIT
