# Módulo de Revisión de Contenido Difamatorio

Detecta insultos, léxico soez y construcciones de ridiculización en nombres y
apellidos de personas, para plataformas de información genealógica.

- **11 tipos de riesgo** — no sólo *cuánto* ofende un término, sino *de qué modo*.
- **30 idiomas** identificados por código ISO 639-3, con ~4.600 términos.
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

Requiere PHP >= 8.0 y la extensión `mbstring`.

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

Cada palabra trae su propia severidad (`low` / `medium` / `high`); la del nombre
es la del peor término hallado. La severidad se traduce luego en una acción:

| Severidad | Decisión | Salvo que… |
|---|---|---|
| `none` | `accept` | |
| `low` | `accept_with_flag` | |
| `medium` | `review` | |
| `high` | `reject` | haya colisión con apellido → `review` |

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
(`PhoneticFolder`: unifica b/v, s/z/c suave, ll/y, h muda, y el sonido de j y
de g suave) y buscando términos del diccionario que **crucen la frontera**
entre el nombre y el apellido plegados:

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

### Tres idiomas, tres fonéticas distintas

Cubre español, portugués e italiano — cada uno con su propio folder
(`PhoneticFolder`, `PortuguesePhoneticFolder`, `ItalianPhoneticFolder`),
seleccionado por `PhoneticFolderRegistry` según el idioma activo. No son la
misma clase con distinto nombre: cada fonética tiene sus propias confusiones
reales, y aplicar las reglas de una a otra produciría colisiones falsas.

| | español | portugués | italiano |
|---|---|---|---|
| b/v | se confunden | **no** se confunden | **no** se confunden |
| s/z/c suave | se confunden | se confunden | **no** se confunden |
| h | muda | muda | endurece c/g (che, chi) — nunca se borra |
| g suave / j | se aspira, se borra | sonido audible, se unifica (no se borra) | — |

```php
$reviewer = DefamatoryContentReviewer::create($configDir, 'por');
$reviewer->getWordList('por')->supportsPhoneticFolding();  // true
$reviewer->getWordList('eng')->supportsPhoneticFolding();  // false — sin reglas registradas
```

Añadir un cuarto idioma es añadir su folder y una fila en
`PhoneticFolderRegistry::FOLDERS` — nada más cambia.

### Evasión cubierta y no cubierta

- **Transliteración numérica** ("c3rda", "v4g1na"): cubierta. `WordList::normalize()`
  sustituye los dígitos/símbolos de un solo carácter más comunes
  (`0→o 1→i 3→e 4→a 5→s 7→t 8→b @→a $→s`) antes de comparar, y los tres
  folders fonéticos hacen lo mismo antes de plegar — por eso también se
  detecta combinada con la fusión: `validateFullName('Elb4', 'G1na')` marca
  "vagina" igual que la versión sin dígitos.
- **Apellidos compuestos con guion o apóstrofo** ("Pérez-García", "O'Brien"):
  cubierta. La búsqueda literal ya los separaba en tokens; los folders
  fonéticos ahora también descartan el guion/apóstrofo (antes quedaba
  literal en la forma plegada y rompía el cálculo de la frontera de fusión).
- **Variantes por distancia de edición** ("Cerrda", "Certa"): **deliberadamente
  no cubierta**. Colapsar letras dobles cerraría este caso, pero across
  ~4.600 palabras en 30 idiomas no hay forma de verificar a mano qué
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
$registry->resolve('zh');   // 'zho'
$registry->resolve('SPA');  // 'spa'
```

| Familia | Idiomas |
|---|---|
| Romance | `spa` `por` `fra` `ita` `ron` |
| Germánica | `eng` `deu` `nld` `swe` `dan` `nor` |
| Eslava | `rus` `ukr` `bul` `pol` `ces` `slk` |
| Urálica | `fin` `hun` |
| Semítica | `ara` `heb` |
| Otras | `ell` `tur` `hin` `jpn` `kor` `zho` `tha` `vie` `ind` |

### Cobertura de los diccionarios

`coverage` no es cosmético: dice dónde hace falta revisión de hablante nativo
antes de usar el módulo en producción para ese idioma. ~4.800 términos en
total; ningún idioma queda ya en `basic`.

| Nivel | Idiomas | Términos c/u |
|---|---|---|
| `comprehensive` | spa, eng, por, fra, ita, deu | 200 – 418 |
| `moderate` | los 24 restantes (ron, nld, swe, dan, nor, rus, ukr, pol, ces, slk, bul, ell, hun, fin, tur, ara, heb, hin, jpn, kor, zho, tha, vie, ind) | 120 – 160 |

```php
$registry->getLanguagesByCoverage('moderate');  // los candidatos a comprehensive
```

Un test de integridad (`DictionaryIntegrityTest`) exige que todo idioma
declarado `moderate` tenga ≥120 términos y `comprehensive` ≥200: subir el
nivel es responder por un mínimo verificable, no una etiqueta.

Los idiomas sin separación por espacios (`jpn`, `zho`, `tha`) declaran
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
$reviewer->validateAcrossRelated('João Porco')->isValid(); // false
```

Una coincidencia hallada en un idioma asociado **pesa menos** que una del
principal: su confianza es la afinidad léxica entre ambos, y esa confianza
descuenta la severidad. Un insulto grave en italiano marca un nombre español
para revisión, pero no lo rechaza con la rotundidad de uno en español.

```php
$term = $reviewer->validateAcrossRelated('Marco Stronzo')->getFlaggedTerms()[0];

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
(`jpn`↔`zho`, `ron`↔`bul`, `tur`↔`ell`), con afinidad baja.

```php
$registry->getAffinity('spa', 'por');   // 0.89 — simétrico
$registry->getRelated('rus');           // ['ukr'=>0.86, 'bul'=>0.74, …]
$registry->getFamilyMembers('ces');     // ['rus','ukr','bul','pol','slk']
```

El umbral por defecto (0.60) se ajusta por llamada:

```php
$reviewer->validateAcrossRelated($name, 0.85);  // sólo los muy cercanos
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

## API

### `DefamatoryContentReviewer`

```php
DefamatoryContentReviewer::create(string $configDir, string $language = 'spa'): self
```

| Método | Devuelve |
|---|---|
| `validateName(string $name)` | `ValidationResult` |
| `validateFullName(string $first, string $last)` | `ValidationResult` |
| `validateAcrossRelated(string $name, ?float $threshold = null)` | `ValidationResult` |
| `validateFullNameAcrossRelated(string $first, string $last, ?float $threshold = null)` | `ValidationResult` |
| `validateInLanguages(string $name, array $languages)` | `ValidationResult` |
| `batchValidateNames(array $names)` | `ValidationResult[]` |
| `batchValidateFullNames(array $names)` | `ValidationResult[]` |
| `decide(ValidationResult $r)` | `accept` \| `accept_with_flag` \| `review` \| `reject` |
| `getDetailedReport(ValidationResult $r)` | `array` |
| `getRelatedLanguages(?float $threshold = null)` | `array<string,float>` |
| `getWordList(?string $lang = null)` | `WordList` |
| `getWordListStatistics(?string $lang = null)` | `array` |

### `ValidationResult`

| Método | Devuelve |
|---|---|
| `isValid()` / `getSeverity()` | `bool` / `string` |
| `getFlaggedTerms()` | términos con `riskType`, `severity`, `sourceLanguage`, `confidence`, `nameCollision` |
| `getFlaggedRiskTypes()` / `getFlaggedCategories()` | `string[]` |
| `getTermsByRiskType(string $t)` | `array` |
| `getTermsByLanguage(string $l)` / `getPrimaryLanguageTerms()` | `array` |
| `hasNameCollision()` / `getNameCollisionTerms()` | `bool` / `array` |
| `hasOnlyPhoneticDetections()` | `bool` |
| `getPhoneticFusionTerms()` / `getPhoneticVariantTerms()` | `array` |
| `getTermsByDetectionMethod(string $m)` | `array` (`'literal'` \| `'phonetic_fusion'` \| `'phonetic_variant'`) |
| `getLanguagesChecked()` | `array<string,float>` |
| `toArray()` | `array` |

### `LanguageRegistry`

| Método | Devuelve |
|---|---|
| `resolve(string $code)` | ISO 639-3; lanza `InvalidArgumentException` si no existe |
| `isSupported(string $code)` | `bool` |
| `getAffinity(string $a, string $b)` | `float` (simétrico; 1.0 consigo mismo) |
| `getRelated(string $c, ?float $t = null)` | `array<string,float>` ordenado desc. |
| `getValidationSet(string $c, ?float $t = null)` | el idioma + sus asociados |
| `getFamily()` / `getFamilyMembers()` / `getFamilies()` | rama genealógica |
| `getLanguagesByCoverage(string $level)` | `string[]` |
| `getMetadata()` / `getAll()` / `getCodes()` | catálogo |

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
├── DefamatoryContentReviewer.php   Motor de validación y decisión
├── LanguageRegistry.php            Códigos, familias y afinidades
├── WordList.php                    Diccionario, normalización y leetspeak
├── PhoneticFolder.php              Plegado fonético del español
├── PortuguesePhoneticFolder.php    Plegado fonético del portugués
├── ItalianPhoneticFolder.php       Plegado fonético del italiano
├── LeetspeakFolding.php            Sustitución numérica compartida por los folders
├── PhoneticFolderRegistry.php      Qué idioma usa qué folder
├── PhoneticFusionDetector.php      Fusión nombre+apellido y variantes ortográficas
└── ValidationResult.php            Resultado con trazabilidad por idioma y método

config/
├── risk-categories.php             Los 10 tipos de riesgo
├── language-families.php           Familias y afinidades
└── languages/
    ├── supported-languages.php     Catálogo ISO 639-3 + alias 639-1
    └── spa.php eng.php por.php …   30 diccionarios

tests/    examples/
```

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
idioma quede cubierto en las diez categorías de riesgo. Marcar
`nameCollision => true` en todo término que también sea nombre o apellido
documentado — es lo que evita que la lista negra borre linajes reales.

## Tests

```bash
./vendor/bin/phpunit
```

---

## Limitaciones

- La detección literal es por término completo o frase de hasta tres palabras;
  no encuentra palabras incrustadas dentro de una sola palabra sin cruce de
  frontera (ver la sección de fusión fonética más arriba). La transliteración
  numérica de un solo carácter sí se cubre (ver «Evasión cubierta y no
  cubierta»).
- La fusión fonética sólo cubre español, portugués e italiano — el resto de
  idiomas no tiene folder registrado en `PhoneticFolderRegistry`, y en
  cualquiera de los tres sólo cubre el cruce entre nombre y apellido, no la
  re-segmentación dentro de un único campo.
- Ningún diccionario queda en `basic`, pero `moderate` (24 de los 30) sigue
  necesitando revisión de hablante nativo antes de producción — es una base
  verificable, no una traducción exhaustiva.
- El árabe dialectal y las variedades regionales del chino no están cubiertos.
- La distancia de edición («Cerrda») no está cubierta: ver el docblock de
  `EvasionTest::testEditDistanceEvasionIsADeliberateGap()` para el porqué.
- Las afinidades son aproximaciones, no medidas.
- El módulo no decide por la plataforma: `decide()` propone, y los casos
  `review` requieren persona.

## Licencia

MIT
