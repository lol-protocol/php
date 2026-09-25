# Changelog

## [4.3.1] - 2026-09-25

### Cambiado (refactor sin cambio de comportamiento)

- **PHPStan nivel 6**, sin errores (era nivel 5). Cerró los 118 avisos de
  tipado de arrays que quedaron documentados como "siguiente paso posible"
  en el 4.3.0: `@param`/`@return`/`@var` con forma de valor en todos los
  métodos y propiedades que devuelven o reciben arrays, más un
  `@phpstan-type` para el shape de un término del diccionario (`WordEntry`,
  en `WordListIndex`) y otro para un término ya marcado (`FlaggedEntry`, en
  `FlaggedTermCollection`), reutilizados con `@phpstan-import-type` en vez
  de repetir la forma en cada archivo. El tipado más preciso encontró un
  `?? 1.0` en `NameEvaluator::applyPhoneticChecks()` que ya nunca podía
  dispararse (`confidence` siempre está presente en un `FlaggedEntry`); se
  quitó.
- 358 tests, 21.232 aserciones, sin cambios de comportamiento.

## [4.3.0] - 2026-09-25

### Arreglado

- **Falsos positivos con nombres reales comunes en la fusión.** El nuevo
  `CommonNamesFalsePositiveTest` (12×12 nombres y apellidos reales por
  idioma) encontró casos que el barrido manual anterior no vio: «Emine
  Kaya» → «inek», «Barbara Nowak» → «baran», «Katalin Kovács» → «link»,
  «Siti Kusuma» → «tikus», «José Gomes» → «cego», «Juliana Oliveira» →
  «anão». Todos iban a `review`. Ahora la fusión exige que el término
  **toque el principio o el final** del nombre completo y tome **al menos 2
  letras de cada lado** de la unión: así se leen los chistes reales («Elba
  Gina», «Felipe Lotas», «Susana Oria» siguen detectándose). Coste: un
  término enterrado en medio de los dos campos ya no se detecta. Los tests
  de fusión por idioma usaban nombres sintéticos con el término en medio
  («Alp Raseson»); se ajustaron para que el término quede anclado,
  detectando el mismo término que antes.
- **Todas las «Ana» en portugués iban a revisión.** «anã» (enana) pierde la
  tilde al normalizarse y queda igual que «Ana», uno de los nombres más
  comunes del idioma. Se quitó del diccionario; «anão» se mantiene.
- README y CONTRIBUTING decían 30 idiomas; son 33 (6 `comprehensive`, 24
  `moderate`, 3 `basic`).

### Añadido

- **Explicaciones legibles**: `ValidationResult::getExplanations()` (también
  en `toArray()` y `getDetailedReport()`) da una frase por término: qué
  coincidió, con qué entrada del diccionario, idioma, tipo y severidad, y si
  es apellido documentado. Cada término trae además `matchedEntry` y, en
  fusiones, `fusedFrom`. Nueva clase `TermExplanation`.
- `CommonNamesFalsePositiveTest` + `tests/fixtures/common-names.php`: los 26
  idiomas con fusión, más inglés y árabe para la búsqueda literal.
- `FileSizeLimitTest`: la regla de 100 líneas deja de ser convención. Ya
  encontró una violación (`NameCollisionRegressionTest`, 130 líneas), que se
  dividió en `EthnicTermsTrackingTest`.
- `ExamplesRunTest`: los 9 ejemplos se ejecutan en cada corrida y fallan si
  la API cambia o emiten avisos.
- PHPStan nivel 5 (`phpstan.neon.dist`), sin errores, en el CI. El nivel 6
  sólo pide anotar tipos de arrays (118 avisos): siguiente paso posible.
- `bin/benchmark.php`: nombres validados por segundo, por idioma; corre en
  CI como prueba de humo.

### Cambiado (refactor sin cambio de comportamiento)

- `AbstractPhoneticFolder` como base de los 17 folders y
  `CommonPhoneticAccents` con los mapas de acentos compartidos.
- `FusionSupport`: búsqueda O(1) y caché de la lista de idiomas. Medido con
  el benchmark nuevo, la validación completa no cambia de forma medible
  (~25.000 nombres/s antes y después): la mejora es de la microfunción, no
  del camino completo.
- Se quitó `StringUtils` (sólo envolvía `mb_strlen(..., 'UTF-8')` en 3
  llamadas); vuelve a usarse `mb_strlen` directamente.
- Se borró `_Garbage/` (~84.000 líneas de CSV y generadores que nunca se
  conectaron al motor); sigue en el historial de git.

### Evaluado y no aplicado

- **Fusión en inglés y árabe.** Con la regla nueva, «Chris Hitt» y
  «محمد منصور» ya no disparan, pero «Dustin King» → «stinking» sí (anclado,
  4 letras por lado). El árabe da cero falsos positivos en el corpus, pero
  144 combinaciones no bastan para un idioma sin vocales escritas. Ambos
  siguen fuera hasta tener corpus mayor y revisión nativa (CONTRIBUTING).

### Historial del umbral de términos étnicos

Movido desde el comentario de `NameCollisionRegressionTest`. Umbral inicial
tras corregir oláh, tót, negro, negrão, polak y szwab; subidas posteriores,
todas con términos revisados como no-apellidos: 162 (cantonés: 黑鬼, 死鬼佬,
棒子, 阿差, 賓妹, 大陸妹, 北姑), 167 (spa: chola, naca, panchita, charnega,
maketa), 170 (youpine, zingara, crioula), 172 (Zigeunerin, Negerin), 173
(țigancă), 181 (rus: негритянка, жидовка, хохлушка, инородка, узкоглазая;
pol: murzynka, czarnucha, skośnooka), 191 (ces: cikánka, ruska, cizačka;
slk: negerka, cigánka, šikmooká; ukr: жидівка, вузькоока, кацапка; bul:
циганка), 192 (γύφτισσα), 195 (heb: צוענייה; ara: زنجية, غجرية).

---
## [4.2.0] - 2026-09-23

### Arreglado

- **Variantes ortográficas estándar de scripts no latinos evadían la
  búsqueda literal.** Verificado con la API pública: «ΜΑΛΑΚΑΣ» (griego en
  mayúsculas: sin tonos por convención y con sigma medial), «козел» (ruso
  con «е» en vez de «ё», que es como se escribe casi siempre), «مـدمـن»
  (árabe con kashida), «مُدْمِن» (harakat), «غبيه» («ه» por «ة»), «احمق»
  (alef sin hamza) y «מַמְזֵר» (hebreo con niqqud) pasaban como válidos. El
  caso griego es crítico en genealogía, donde los registros suelen ir en
  mayúsculas. Nuevo `ScriptFolding`, aplicado en `WordList::normalize()`:
  sólo equivalencias que los hablantes tratan como la misma palabra. Cero
  colisiones de claves en los 6 diccionarios afectados y ningún nombre
  real común nuevo marcado.
- **«أمية» sin `nameCollision`.** Añadido en 4.1.x como femenino de «أمي»
  (analfabeto), pero es también Umayya, nombre propio real (Banu Umayya,
  la dinastía omeya). Ahora va a revisión humana en vez de rechazo.

### Añadido

- **Fusión nombre+apellido en 9 idiomas más** (ruso, ucraniano, búlgaro,
  griego, hindi, coreano, islandés, swahili, tagalo): cobertura de 17 a 26
  de 33. La fusión no necesita reglas fonéticas — es unir los dos campos y
  buscar un término que cruce la unión — así que `FusionSupport` la hace
  sobre la forma literal normalizada donde no hay folder. Cada idioma
  entró tras un barrido de combinaciones de nombres reales comunes con
  cero falsos positivos. Excluidos con motivo documentado: inglés y árabe
  (falsos positivos reales: «Chris Hitt» → «shit», «محمد منصور» →
  «مدمن»), hebreo (no escribe vocales, como el árabe), japonés, tailandés
  y cantonés (sin espacios entre palabras) y vietnamita (tono fonémico).
- 217 tests (34 nuevos en `ScriptNormalizationTest` y `LiteralFusionTest`;
  18 de ellos fallan sin los cambios).

---
## [4.1.4] - 2026-09-19

### Arreglado

- **El inglés quedaba sin cobertura fonética, sin que nada lo dijera.** De
  los 30 idiomas soportados, es el único de script latino sin folder en
  `PhoneticFolderRegistry` — y a diferencia del resto (documentado como
  exclusión deliberada para el vietnamita y los 11 de script no latino),
  no había ninguna nota explicando por qué. Es además uno de los 6
  diccionarios en nivel `comprehensive`. Verificado: los chistes de fusión
  conocidos en inglés ("Mike Rotch", "Anna Sasin") pasan sin detectarse
  (`validateFullName('Mike', 'Rotch')->isValid()` da `true`), y es un gap
  real de motivo distinto a los demás: el resto de folders sustituye una
  grafía alternativa real y consolidada (ae/æ, ß/ss); el inglés no tiene
  ese tipo de variante — su distancia entre sonido y letra es irregular
  caso por caso, y esos chistes dependen de un parecido fonético
  aproximado, no de una equivalencia ortográfica verificable. Documentado
  ahora en `PhoneticFolderRegistry`, el README y el test que ya
  verificaba el comportamiento (sin cambio de comportamiento: seguía sin
  soporte, ahora se sabe por qué). De paso, el griego —también script no
  latino— faltaba en esa misma lista en ambos lugares (10 idiomas
  contados en vez de 11).
- 179 tests, sin cambios en el comportamiento — corrección puramente
  documental sobre un hueco real.

---

## [4.1.3] - 2026-09-18

### Arreglado

- **Un separador metido dentro de un término evadía el filtro literal, en
  los 30 idiomas**: "pu-ta", "pu.ta", "pu_ta", "pu,ta", "pu·ta" y "pu'ta"
  pasaban como nombre válido. `findInText()` partía por esos caracteres y
  buscaba "pu ta", que no es la clave de nada. Es la misma evasión que el
  plegado fonético ya cerraba con `stripSeparators()`, pero el camino
  literal —el único que tienen los 13 idiomas sin reglas fonéticas— la
  seguía teniendo abierta. Ahora cada palabra se busca también sin sus
  separadores intercalados (`WordListScanner`), y `normalize()` los quita
  de la clave para que ambos lados coincidan.
- **8 entradas del diccionario con guion eran inalcanzables** por la misma
  causa: existían en el índice pero ninguna búsqueda producía su clave, así
  que `half-breed` (eng), `blanc-bec`, `casse-pieds` (fra),
  `kekanak-kanakan` (ind), `vira-lata`, `dedo-duro` (por) nunca se
  detectaban — sólo `lèche-cul` y `puxa-saco`, y de rebote, por un
  subtérmino. Verificado antes y después: las 8 se detectan ahora.
- **`ScoringWeights::scoreOf()` puntuaba 0 al renombrar las severidades.**
  Si la severidad del diccionario no está en el mapa configurado, caía a
  las etiquetas `'high'`/`'medium'` — que tampoco están cuando se
  renombran con `withSeverityWeights()`, así que el peso terminaba en 0.0:
  con una policy de etiquetas propias, "bastardo" puntuaba 0 y **el filtro
  aceptaba todo en silencio**. Ahora una severidad desconocida usa el peso
  mayor declarado: ante una configuración incoherente, un filtro debe
  sobremarcar, no dejar pasar.
- **`riskAnalysis[...]['isSevere']` comparaba dos vocabularios distintos**:
  la severidad del diccionario (`high`) contra la etiqueta de banda
  (`topSeverityLabel()`). Con las bandas por defecto coinciden por
  casualidad; al renombrarlas quedaba siempre en `false` aunque la decisión
  fuera `reject`. Ahora se comparan los pesos. De paso, `level` ya no
  arranca en un `'low'` hardcodeado que no tiene por qué existir en una
  configuración propia.
- 4 tests nuevos. 179 tests, 0 regresiones, y 0 falsos positivos nuevos
  sobre un barrido de 60 nombres legítimos de 20 idiomas (con énfasis en
  compuestos con guion y apóstrofo: "Jean-Luc", "O'Brien", "Hans-Peter",
  "García-López").

### Límites conocidos (sin cambios)

- Intercalar un carácter que no es separador (`pu*ta`, `pu/ta`) o un
  espacio (`pu ta`) sigue evadiendo el camino literal: cerrarlo exigiría
  coincidencia difusa, descartada a propósito por los falsos positivos que
  genera sobre ~4.600 términos en 30 idiomas. El caso del espacio sí lo
  cubre el detector de fusión en los 17 idiomas con reglas fonéticas.

---

## [4.1.2] - 2026-09-18

### Arreglado

- **`batchValidateFullNames()` no aplicaba detección de fusión fonética**,
  a diferencia de `validateFullName()`: trataba cada nombre completo como
  un único campo en vez de separar nombre/apellido, así que evasiones como
  "Elba Gina" pasaban el filtro en lote aunque `validateFullName('Elba',
  'Gina')` sí las detectaba. Ahora separa por el primer espacio y llama a
  `validateFullName()` internamente, igual que la versión singular.
- **Dos apariciones idénticas del mismo término en un nombre se contaban
  como una sola**, incluso dentro de un mismo idioma (`validateName('puta
  puta')` marcaba un solo término, no dos), lo que además rompía en
  silencio el modo de agregación `'sum'` (`ScoringPolicy::withAggregation`)
  para el caso que ese modo existe para cubrir: acumular todo lo
  encontrado. La deduplicación pensada para "el mismo término aparece en
  varios diccionarios de una familia de idiomas" no distinguía esa
  situación de "el mismo término aparece dos veces en el texto". Ahora la
  clave de deduplicación incluye la posición de la aparición.
- `PhoneticFusionDetector` y `WordListPhonetics` pasaban `mb_strlen()` /
  `mb_strpos()` sin el argumento de codificación explícito, a diferencia
  de todos los folders fonéticos de este mismo módulo — inconsistente, y
  en hosts con `default_charset` distinto de UTF-8 podía desalinear el
  punto de unión nombre/apellido. Se agrega `'UTF-8'` explícito en los
  cuatro sitios.
- `WordListIndex::statistics()` ordenaba `byRiskType` y `bySeverity` de
  mayor a menor pero dejaba `byCategory` sin ordenar — asimetría no
  intencional en el contrato de salida. Ahora las tres se ordenan igual.
- 2 tests nuevos (fusión fonética en lote, término repetido no se
  colapsa). 175 tests, 0 regresiones.

---

## [4.1.1] - 2026-09-15

### Cambiado

- **Pasada de DRY sobre el módulo completo** (revisión en 4 ángulos:
  reutilización, simplificación, eficiencia, altitud):
  - Los 17 folders fonéticos compartían dos líneas idénticas (sustitución
    de leetspeak y borrado de separadores antes de la fusión
    nombre+apellido). Se movieron a `LeetspeakFolding::stripSeparators()`,
    junto al `unleet()` que ya vivía ahí — cero cambio de comportamiento,
    el regex es el mismo.
  - `WordListIndex` y `FlaggedTermCollection` tenían tres/cuatro métodos
    `byX()` casi idénticos (mismo `array_filter` con distinto campo). Se
    consolidan en un `filterBy(campo, valor)` privado por clase.
  - `WordListPhonetics::fusionCandidates()` recorría y filtraba el índice
    fonético en cada llamada; ahora memoiza por `$minLength`, igual que
    `buildIndex()` ya memoiza el índice base una fila más arriba.
  - `PhoneticFusionDetector::detectFusion()` extrae la condición de cruce
    de frontera a `crossesBoundary()`, con nombre y comentario propios en
    vez de una expresión inline.
  - Se elimina `LanguageRegistry::getDefaultThreshold()` y
    `LanguageAffinity::defaultThreshold()`: sin llamadores (ni en `src/`,
    `tests/`, `examples/` ni documentados en el README) tras verificar en
    todo el repositorio, no sólo en el árbol fuente.
  - Se evalúa y se descarta consolidar los mapas `ACCENTS` de cada folder
    en `AccentFolding::MAP`: varios idiomas (p. ej. francés, con "ç" → "s"
    por regla fonética, no "c" por accent-stripping genérico) necesitan
    una tabla propia — unificarlas cambiaría resultados, no sólo
    reorganizaría código.
  - Se evalúa y se descarta una clase base para el `CONFIG_DIR`/`setUp()`
    repetido en los tests: la variación real entre archivos (idioma,
    nombre de variable, parámetros de política) hace que el ahorro no
    compense la indirección.
  - 173 tests, 0 regresiones.

---

## [4.1.0] - 2026-09-15

### Cambiado

- **Ningún archivo de `src/` o `tests/` supera 100 líneas.** Se corrigió de
  paso un bug real de compatibilidad encontrado en el camino: una
  constante declarada directamente en un trait (`LeetspeakFolding`) exige
  PHP 8.2+, y el `composer.json` del proyecto declara `">=8.1"` — el job
  8.1 de la CI habría fallado en tiempo de compilación en los 17 folders
  fonéticos que usan ese trait. Se extrae a `Leetspeak`, una clase normal.
- **`src/` (6 archivos grandes → 15):** mismo patrón en casi todos —
  colaboradores internos, cero cambio de API pública, los tests existentes
  no necesitaron tocarse:
  - `ScoringPolicy` (344→98) delega en `ScoringWeights`, `SeverityBands`,
    `DecisionTable`.
  - `WordList` (285→98) delega en `WordListIndex` y `WordListPhonetics`;
    el mapa de diacríticos sale a `AccentFolding`.
  - `ValidationResult` (239→89) delega en `FlaggedTermCollection`.
  - `LanguageRegistry` (174→98) delega en `LanguageAffinity`.
  - **`DefamatoryContentReviewer` (431→95) es la única rotura de API
    deliberada**: los métodos de idiomas emparentados y acceso a
    diccionarios se mueven a `$reviewer->related()` y
    `$reviewer->languages()` — ver la sección "API" del README para la
    tabla de equivalencias antes/después. `validateName`,
    `validateFullName`, `decide`, `getDetailedReport`, `getPolicy`,
    `setPolicy`, `getLanguage`, `setLanguage` no cambian.
- **`tests/` (8 archivos de hasta 444 líneas → 41):** se reparten por
  idioma (fusión fonética), por colaborador (ScoringPolicy) o por tema
  (validación cruzada, acceso a diccionarios...), sin perder ni un test:
  173 antes, 173 después.
- **`examples/usage.php` (185 líneas) → 9 scripts numerados** en
  `examples/`, cada uno ejecutable por separado.
- `config/languages/*.php` queda exento a propósito: son diccionarios de
  datos, no lógica — mismo criterio que usan la mayoría de linters de LOC
  para fixtures.

---

## [4.0.0] - 2026-09-14

### Cambiado (rotura de compatibilidad menor)

- **`ScoringPolicy` (nueva clase): pesos, bandas, reglas de decisión y modo
  de agregación salen de `DefamatoryContentReviewer` a un objeto de
  configuración independiente e inmutable.** Antes vivían repartidos en una
  constante (`SEVERITY_VALUE`), una propiedad (`$highSeverityRiskTypes`) y
  dos métodos privados (`scoreOf()`, `severityFromScore()`) del motor —
  ajustar la sensibilidad del filtro exigía editar esa clase. Ahora:
  - `ScoringPolicy::default()` reproduce el comportamiento exacto de
    siempre (pesos none=0/low=1/medium=2/high=3, cortes en 1.5/2.5,
    agregación por el peor término) — no pasar ninguna política a
    `DefamatoryContentReviewer::create()`/`__construct()` es idéntico a
    antes de este cambio.
  - Se puede ajustar: pesos por severidad, multiplicador por tipo de
    riesgo (subir la sensibilidad a `etnico` sin tocar la severidad de
    cada palabra), bandas propias (no sólo 3), qué decisión corresponde a
    cada banda, en qué bandas `nameCollision`/detección-sólo-fonética
    degradan `reject` a `review`, y el modo de agregación: `'max'` (por
    defecto, el peor término manda, igual que siempre) o `'sum'` (se
    acumulan todos los términos, más parecido a un filtro de spam
    aditivo — opt-in, nunca el default, porque sumar sin criterio castiga
    más a un nombre con muchas palabras leves que a uno con una sola grave).
  - `ValidationResult::getScore()` (nuevo): expone el puntaje numérico
    crudo antes de discretizar en severidad, para quien prefiera un
    umbral propio en vez de las 4 bandas fijas.
- **`DefamatoryContentReviewer::setHighSeverityRiskTypes()` se elimina.**
  La reemplaza `$reviewer->setPolicy($reviewer->getPolicy()->withHighSeverityRiskTypes([...]))`
  — no había ningún uso de este método fuera de la propia clase, y
  mantenerlo junto a `ScoringPolicy` habría reintroducido una segunda
  fuente de verdad para la misma configuración (el mismo error que motivó
  el refactor de `coverage` en la 3.4.0).
- `DefamatoryContentReviewer::create()` y el constructor aceptan un
  `?ScoringPolicy $policy = null` opcional como último argumento.
  `getPolicy()`/`setPolicy()` (nuevos) para leer o cambiar la política de
  un reviewer ya creado.

### Tests

173 tests (17 nuevos en `ScoringPolicyTest`), todo en verde — sin
regresiones: la batería completa existente pasa sin modificar un solo test,
porque `ScoringPolicy::default()` reproduce el comportamiento anterior al
milímetro.

---

## [3.10.0] - 2026-09-10

### Corregido

- **Seis apellidos reales rechazaban en automático.** Auditoría de la lógica
  fonética y el diccionario: un barrido de ~6.800 combinaciones de nombre y
  apellido reales sobre los 17 idiomas con fusión fonética (10-20 nombres ×
  10-20 apellidos por idioma) encontró que `decide()` devolvía `reject` para
  seis apellidos reales y documentados, porque su entrada en el diccionario
  tenía severidad alta y categoría `etnico` sin `nameCollision => true`:
  - `oláh` y `tót` (húngaro) — el primero, en particular, muy frecuente
    entre familias romaníes húngaras.
  - `negro` (italiano, frecuente en Piemonte).
  - `negrão` (portugués/brasileño).
  - `polak` (francés — checo/polaco/diáspora judía).
  - `szwab` (polaco).

  Los seis quedan marcados con `nameCollision => true`: la coincidencia se
  sigue registrando, pero ahora manda a revisión humana en vez de rechazar
  automáticamente un linaje real, que es exactamente para lo que existe ese
  mecanismo.
- `tests/NameCollisionRegressionTest.php` (nuevo): fija los seis casos como
  regresión, y añade una prueba de seguimiento que cuenta cuántos términos
  de severidad alta en categoría `etnico` siguen sin `nameCollision`
  evaluado (160 al momento de escribir esto) — no falla si crece, pero deja
  el número visible para que agregar uno nuevo sin pensar en la colisión no
  pase desapercibido. El resto de esa lista no se tocó: requiere la misma
  revisión por hablante nativo que ya documenta `CONTRIBUTING.md`, no
  conjeturas sobre idiomas que este proyecto no habla con certeza.

### Verificado

- Cero colisiones fonéticas problemáticas entre términos distintos del
  mismo diccionario en los 17 idiomas con fusión fonética (una sola
  colisión encontrada, inocua: `gerizekalı`/`geri zekalı` en turco son dos
  grafías del mismo insulto).
- Cero salidas vacías o degeneradas al plegar cualquier palabra de los 17
  diccionarios fonéticos.
- Tras las seis correcciones, cero decisiones `reject` en el barrido de
  ~6.800 combinaciones reales — sólo quedan casos ya esperados en `review`
  (colisión de apellido o fusión fonética, ambos limitados a revisión por
  diseño) y un `accept_with_flag` de severidad baja ("Francisco Costa" →
  "cocô" en portugués, un artefacto de frontera genuino pero de la
  severidad más baja posible — el sistema lo acota correctamente, no
  requiere corrección).

### Tests

156 tests (10 nuevos), todo en verde — sin regresiones.

---

## [3.9.0] - 2026-09-10

### Añadido

- **Fusión fonética extendida a 12 idiomas más** (checo, eslovaco, danés,
  noruego, sueco, finlandés, húngaro, indonesio, turco, polaco, neerlandés,
  rumano) — de 5 a 17 de los 30 idiomas soportados, todos los que usan
  script latino salvo el vietnamita. Cada folder cubre sólo la ambigüedad (o
  el par) verdaderamente sistemática del idioma, casi siempre la que un
  hablante nativo aprendió de memoria en la escuela porque el oído no la
  resuelve solo:
  - "y"/"i" en checo y eslovaco (homófonos reales; cuál se escribe es regla
    histórica).
  - "å/æ/ø" → "aa/ae/oe" en danés, noruego y sueco — grafía alternativa real
    y consolidada (histórica, no una aproximación).
  - "ä/ö" → "a/o" en finlandés — sin el dígrafo codificado del alemán, la
    práctica real es simplificar a la vocal base.
  - "ly"→"j" y ő/ű→ö/ü en húngaro.
  - La reforma ortográfica indonesia de 1972 completa: oe→u, dj/tj/nj/sj/
    ch→j/c/ny/sy/kh, j suelta→y ("Soekarno"/"Sukarno").
  - Sustitución sin teclado turco: ı/ş/ç/ö/ü/ğ→i/s/c/o/u/g
    ("Erdoğan"/"Erdogan").
  - "ó"→"u", rz/ż y ch/h unificados en polaco — los tres pares son
    homófonos reales.
  - "ei"/"ij" y "au"/"ou" unificados en neerlandés — la confusión
    ortográfica más famosa del idioma.
  - "â"/"î" unificados en rumano (mismo sonido, posición histórica); ş/ţ
    normalizados a ș/ț (variantes de codificación de la misma letra, no
    fonética).
  - **Vietnamita excluido a propósito**: es de script latino, pero el tono
    es fonémico (seis tonos distinguen palabras) y no hay grafía
    alternativa real que plegar sin colapsar significados. Los 10 idiomas
    en script no latino (árabe, búlgaro, hebreo, hindi, japonés, coreano,
    ruso, tailandés, ucraniano, chino) quedan fuera por la misma disciplina
    que ya rige el resto del proyecto: el mecanismo de plegado por
    sustitución de caracteres no tiene equivalente verificable sin una
    romanización propia confirmada por un hablante nativo.
  - `PhoneticFolderRegistry::FOLDERS` pasa de 5 a 17 entradas; ningún otro
    componente cambió.
- Tests nuevos en `PhoneticFusionLatinScriptExtendedTest` (nuevo archivo,
  separado de `PhoneticFusionMultiLanguageTest` por volumen): reglas de
  plegado unitarias, fusión que cruza la frontera con ejemplos construidos,
  y guardas de falso positivo con nombres reales de cada idioma (Jan Novák,
  Anders Hansen, Budi Santoso, Mehmet Yılmaz, Jan Kowalski, Ion Popescu,
  entre otros).

### Tests

146 tests (43 nuevos), todo en verde — sin regresiones.

---

## [3.8.0] - 2026-09-10

### Corregido

- **`composer.lock` se versiona.** Estaba en `.gitignore`; cada `composer
  install` (incluido el de la CI) resolvía versiones frescas contra los
  rangos de `composer.json` sin nada que garantizara reproducibilidad entre
  corridas. Ahora el lock queda commiteado y la CI instala exactamente lo
  que él fija.
- **`"php": ">=8.0"` corregido a `">=8.1"`.** El mínimo declarado nunca se
  había probado: PHPUnit `^10` (única forma de testear el proyecto) exige
  PHP 8.1+, y la matriz de CI ya arrancaba en 8.1. El constraint ahora
  coincide con lo que realmente se verifica.

---

## [3.7.0] - 2026-09-09

### Añadido

- **CI de GitHub Actions** (`.github/workflows/tests.yml`): corre
  `composer install` + `./vendor/bin/phpunit` en cada push y pull request,
  en una matriz de PHP 8.1 a 8.4 (el mínimo real que exige PHPUnit ^10, ya
  instalado). Badge de estado agregado al README.

---

## [3.6.0] - 2026-09-09

### Añadido

- **`CONTRIBUTING.md`**: documenta el proceso de revisión por hablante
  nativo para los 24 diccionarios en nivel `moderate` — qué revisar en cada
  entrada (`word`, `riskType`, `severity`, `nameCollision`), la tabla de
  mínimos de palabras por nivel de `coverage` (basic 60 / moderate 120 /
  comprehensive 200) y por qué el mínimo de palabras es condición necesaria
  pero no suficiente para subir a `comprehensive`. Enlazado desde el
  README, sección "Ampliar un diccionario existente".

---

## [3.5.0] - 2026-09-09

### Añadido

- **Fusión fonética extendida a francés y alemán.** `FrenchPhoneticFolder` y
  `GermanPhoneticFolder` se suman a español, portugués e italiano, cada uno
  con reglas propias de su ortografía real (no copiadas de los otros):
  - Francés: ç/c(e,i,y)→s, ph→f, qu→k, protege el dígrafo "ch" (sonido
    "sh", no la africada del español), g(e,i,y) se unifica a un sonido
    audible en vez de borrarse, h siempre muda.
  - Alemán: deliberadamente el folder más restringido — ä/ö/ü/ß se expanden
    a su grafía alternativa real (ae/oe/ue/ss, no una aproximación), w→v sin
    excepciones. No toca "v" ni "h": ambas son bimodales en alemán ("Vater"
    /f/ vs. "Vase" /v/; "h" alarga vocal o es inicial audible) y sin
    diccionario de origen por palabra, plegarlas a ciegas arriesgaba más
    colisiones falsas de las que resolvía.
  - `PhoneticFolderRegistry::FOLDERS` pasa de 3 a 5 entradas; ningún otro
    componente cambió — confirma que el diseño de extensión (folder nuevo +
    una fila de registro) escala sin tocar `DefamatoryContentReviewer` ni
    `PhoneticFusionDetector`.
- Batería de tests nueva en `PhoneticFusionMultiLanguageTest`: reglas de
  plegado unitarias, fusión que cruza la frontera nombre/apellido con
  ejemplos construidos, y guardas de falso positivo con nombres reales
  (Jean Dupont, Hans Müller, Wolfgang Meyer, entre otros).

### Cambiado

- README: la tabla comparativa de fonéticas pasa de 3 a 5 columnas, con una
  fila nueva para las reglas propias de francés y alemán que no encajan en
  las filas compartidas (qu→k, protección de "ch", expansión de umlauts).

### Tests

103 tests (11 nuevos), todo en verde — sin regresiones.

---

## [3.4.0] - 2026-09-09

### Cambiado

- **`coverage` tiene ahora una única fuente de verdad.** Vivía en dos sitios
  — `meta.coverage` de cada archivo de idioma, y una copia en
  `config/languages/supported-languages.php` — y se desincronizaron la
  primera vez que sólo uno de los dos se actualizó (el bug que motivó
  `DictionaryIntegrityTest` en la 3.3.0). Se quitó la copia del catálogo:
  `LanguageRegistry` vuelve a ser sólo identidad y parentesco (nombre, alias,
  familia), nunca estado de contenido.
- `LanguageRegistry::getLanguagesByCoverage()` se elimina. La reemplaza
  `DefamatoryContentReviewer::getLanguagesByCoverage()`, que consulta
  `WordList::getCoverage()` de cada idioma directamente — el mismo objeto que
  ya es la fuente real, sin copia intermedia.
- `DictionaryIntegrityTest::testCoverageMatchesBetweenCatalogAndLanguageFile()`
  (comparaba las dos copias) se reemplaza por
  `testCatalogHasNoDuplicatedCoverageField()`: ahora impide que el catálogo
  vuelva a declarar `coverage` en absoluto, en vez de sólo detectar cuándo
  las dos copias difieren.

### Tests

92 tests (sin nuevos, dos actualizados para el método movido), todo en
verde — sin regresiones.

---

## [3.3.0] - 2026-09-09

### Ampliado

- **Los 11 diccionarios `basic` suben a `moderate`.** slk, bul, ara, heb, hin,
  jpn, kor, zho, tha, vie, ind pasan de 105-125 términos a 122-155,
  reforzando sobre todo `genero` y `etnico` (las categorías más desiguales en
  los 11). Ningún idioma queda ya en `basic`: 6 `comprehensive` + 24
  `moderate`, ~4.800 términos en total (antes ~4.600).
- **`config/languages/supported-languages.php`** actualizado en paralelo — su
  `coverage` es una copia independiente de la de cada archivo de idioma, y
  quedaba desincronizada si sólo se actualizaba una de las dos.

### Corregido

- **3 duplicados preexistentes** encontrados al construir el verificador de
  este trabajo: `путка` en `bul.php` (genero/ordinario), `baran` en `pol.php`
  (animal/intelectual), y `burro`+`baleia` en `por.php` (animal/intelectual,
  animal/fisico) — estos dos últimos introducidos en esta misma sesión de
  ampliación. Como `WordList` indexa por palabra normalizada, la entrada
  posterior pisaba a la primera en silencio: la categoría más antigua quedaba
  huérfana sin que nada lo señalara.
- **Documentación desactualizada** en la sección de Limitaciones del README:
  seguía listando la transliteración numérica como no cubierta después de
  haberla cubierto en la 3.2.0, y la fusión fonética como exclusiva de
  español después de extenderla a portugués e italiano en la misma versión.

### Añadido

- **`DictionaryIntegrityTest`**: cuatro chequeos transversales a los 30
  diccionarios, para que la clase de error de arriba se detecte sola en vez
  de a mano. Verifica que ninguna palabra se repita en dos categorías del
  mismo idioma, que `coverage` coincida entre el catálogo y cada archivo, que
  los 30 diccionarios carguen sin error, y que el conteo de términos cumpla
  el mínimo de su nivel declarado (`moderate` ≥120, `comprehensive` ≥200).

### Tests

4 tests nuevos. 92 tests, 15.563 aserciones, todo en verde.

---

## [3.2.0] - 2026-09-09

### Añadido

- **Fusión fonética en portugués e italiano**, además de español.
  `PhoneticFolderRegistry` mapea idioma → clase de plegado;
  `PortuguesePhoneticFolder` e `ItalianPhoneticFolder` tienen reglas propias,
  no una copia de las del español — b/v y s/z se confunden en español y
  portugués pero no en italiano; la "h" es muda en español/portugués pero
  endurece la c/g en italiano (che, chi). `WordList::supportsPhoneticFolding()`
  reemplaza el chequeo hardcodeado a `'spa'` en
  `DefamatoryContentReviewer::applyPhoneticChecks()`.
- **Cobertura de evasión numérica ("leet")**: `WordList::normalize()` sustituye
  `0/1/3/4/5/7/8/@/$` por sus letras más probables antes de comparar
  (`c3rda`→`cerda`, `v4g1na`→`vagina`). Los tres folders fonéticos hacen lo
  mismo (trait `LeetspeakFolding` compartido), así que la evasión numérica
  combinada con la fusión también se detecta (`Elb4`+`G1na` → "vagina").

### Corregido

- **Bug**: los folders fonéticos no quitaban guiones ni apóstrofos, sólo
  espacios — un apellido compuesto como "Pérez-García" conservaba el guion
  literal en la forma plegada, descuadrando el cálculo de la frontera entre
  nombre y apellido para la detección de fusión. Ahora se quitan igual que
  los espacios en los tres folders.

### Documentado (límites deliberados, no implementados)

- **Variantes por distancia de edición** ("Cerrda", "Certa"): colapsar letras
  dobles cerraría este caso puntual, pero no hay forma de verificar a mano,
  a través de ~4.600 palabras en 30 idiomas, qué colisiones no deseadas
  produciría (p. ej. "Serrano", apellido real, se volvería "Serano"). Se
  documenta como límite en vez de implementarse a medias.

### Tests

23 tests nuevos (`PhoneticFusionMultiLanguageTest.php`, `EvasionTest.php`):
las reglas de cada folder por separado, dos ejemplos de fusión construidos
para portugués e italiano (no chistes documentados como los del español),
el resguardo de nombres corrientes en los tres idiomas, el fix de
guion/apóstrofo, la evasión numérica simple y combinada con fusión, y la
confirmación explícita de que la distancia de edición sigue sin cubrirse.
88 tests, 10.281 aserciones, todo en verde — sin regresiones.

---

## [3.1.0] - 2026-09-08

### Añadido

- **Detección de fusión fonética.** "Elba Gina" ("el vagina"), "Felipe Lotas"
  ("Feli-pelotas"), "Susana Oria" ("su zanahoria"): nombre y apellido, ninguno
  ofensivo por separado, que al leerse seguidos y sin pausa componen otra
  palabra. Es el fenómeno detrás de los clásicos "nombres graciosos con doble
  sentido" — y, cuando el resultado es vulgar u ofensivo, un vector real de
  difamación que la validación literal por sí sola no cubre.
- **`PhoneticFolder`** — plegado fonético del español: unifica b/v, s/z/c
  suave, ll/y, h muda, y el sonido de j y de g suave (ge, gi). Aproximación
  deliberadamente mínima: cubre exactamente las confusiones que producen
  coincidencias reales para este módulo.
- **`PhoneticFusionDetector`** — `detectFusion()` busca términos del
  diccionario que crucen la frontera entre nombre y apellido plegados
  fonéticamente; `detectVariant()` encuentra variantes ortográficas de un
  campo completo que suenan igual a una entrada del diccionario (p. ej.
  "Cojes" frente a "Coges").
- **La exigencia de cruce de frontera como salvaguarda central.** Un
  fragmento de riesgo que cae entero dentro de un único campo no cuenta —
  "ano" aparece en "Mariano", "Luciano", "Adriano", "Cristiano", "Emiliano";
  sin esa exigencia, cualquiera de esos apellidos corrientes dispararía una
  alerta falsa. Verificado explícitamente con 8 nombres reales de este tipo,
  ninguno se marca.
- Nuevo tipo de riesgo `fonetico` en `config/risk-categories.php`.
- `ValidationResult::hasOnlyPhoneticDetections()`, `getPhoneticFusionTerms()`,
  `getPhoneticVariantTerms()`, `getTermsByDetectionMethod()`. Cada término
  marcado ahora lleva `detectionMethod` (`literal` / `phonetic_fusion` /
  `phonetic_variant`).
- `WordList::searchPhoneticExact()` y `getFusionCandidates()`.
- Cuatro entradas nuevas en `config/languages/spa.php`: `vagina`, `pelotas`,
  `zanahoria`, `coges` (esta última cubre también la grafía "cojes" vía
  plegado fonético).

### Cambiado

- **`decide()` nunca rechaza en automático sólo por inferencia fonética.**
  Igual que con la colisión de apellidos, una detección `high` cuya única
  fuente sea `phonetic_fusion`/`phonetic_variant` baja a `review`: es
  inferencia, no una coincidencia literal directa.
- `validateFullName()` y `validateFullNameAcrossRelated()` ejecutan la
  comprobación fonética automáticamente (sólo cuando el idioma activo es
  español y ambos campos traen texto); `validateName()` no cambia, ya que
  necesita conocer la frontera entre nombre y apellido.
- `analyzeRisks()` añade `detectionMethods` por tipo de riesgo.

### Tests

21 tests nuevos en `tests/PhoneticFusionTest.php`: unidades de
`PhoneticFolder`, los tres ejemplos reales de fusión, la variante ortográfica
de "Cojes", ocho nombres reales que confirman que el resguardo de cruce de
frontera no genera falsos positivos, el tope de `decide()` en `review`, y que
la comprobación queda fuera de español. 65 tests, 10.234 aserciones, todo en
verde — sin regresiones sobre la suite anterior.

---

## [3.0.0] - 2026-09-08

Cambio incompatible: los idiomas pasan a identificarse por ISO 639-3.

### Añadido

- **`LanguageRegistry`** — resuelve códigos, agrupa idiomas por familia y modela
  la afinidad léxica entre pares.
- **Validación cruzada entre idiomas asociados** — `validateAcrossRelated()`
  consulta el idioma principal y los emparentados; una coincidencia en un idioma
  asociado lleva la afinidad como `confidence` y esa confianza descuenta la
  severidad. `validateInLanguages()` permite un conjunto explícito sin descuento.
- **`config/language-families.php`** — 14 familias y 60 pares de afinidad,
  incluidos pares sin parentesco directo pero con préstamo intenso
  (`jpn`↔`zho`, `ron`↔`bul`, `tur`↔`ell`).
- **Protección de apellidos legítimos** — `nameCollision` marca términos que
  también son nombres documentados (`cerda`, `moro`, `calvo`, `bastard`,
  `savage`, `hogg`). Una coincidencia grave sobre ellos baja de `reject` a
  `review` en vez de rechazarse en automático.
- **`decide()`** — traduce severidad y colisión en acción concreta:
  `accept` / `accept_with_flag` / `review` / `reject`.
- **Detección de frases** — `findInText()` prueba ventanas de hasta tres
  palabras, de modo que "hijo de puta" cuenta como un término y no como tres.
- **`coverage` por diccionario** — `comprehensive` / `moderate` / `basic`, para
  saber qué idiomas necesitan revisión de hablante nativo antes de producción.
- **`requiresTokenizer`** — `jpn`, `zho` y `tha` no separan palabras con
  espacios y declaran que necesitan un segmentador externo para texto libre.
- Términos de ridiculización por rasgo neutro (`zurdo`, `diestro`), con
  severidad baja: se marcan sin bloquear.

### Cambiado

- **Los idiomas usan ISO 639-3 (tres letras).** `es`→`spa`, `en`→`eng`,
  `de`→`deu`, `zh`→`zho`, `el`→`ell`, `cs`→`ces`… Los códigos de dos letras se
  siguen aceptando como alias de entrada y se normalizan al entrar, así que una
  integración existente puede seguir pasando `es`.
- **Diccionarios ampliados de ~600 a ~4.600 términos.** Ningún idioma queda como
  stub: el mínimo son 105 términos y los seis principales pasan de 200.
  Español 415, inglés 266, francés 226, portugués 222, alemán 208, italiano 206.
- **Formato de los diccionarios**: `['meta' => [...], 'words' => [...]]`. El
  formato plano anterior se sigue leyendo.
- **Severidad por palabra**, no por categoría: cada entrada declara la suya y la
  del nombre es la del peor término hallado.
- `addFlaggedTerm()` toma un array de datos en lugar de cuatro escalares.
- Normalización ampliada a diacríticos eslavos, nórdicos y turcos, más `ß`→`ss`,
  `æ`→`ae`, `œ`→`oe`.
- El constructor del reviewer toma un `LanguageRegistry` y un directorio de
  diccionarios; `create()` monta todo desde el directorio de configuración.

### Eliminado

- `config/defamatory-words.php` — sustituido por `config/languages/spa.php`.
- Los 30 archivos de idioma con nombre de dos letras.
- `README_v2.md` y `USAGE_GUIDE.md` — documentaban la API de la v1 con ejemplos
  que ya no funcionan; su contenido vigente está en `README.md`.

### Tests

44 tests, 9.440 aserciones. Cubren resolución de códigos y alias, simetría de
afinidades, validación cruzada y descuento por confianza, colisión con
apellidos, frases multipalabra, plegado de diacríticos, y verificación de que
los 30 diccionarios existen, declaran su propio código y usan sólo tipos de
riesgo y severidades válidos.

---

## [2.0.0] - 2026-09-08

### Añadido

- 10 tipos de riesgo (`animal`, `intelectual`, `discapacidad`, `fisico`,
  `moral`, `genero`, `ordinario`, `burlesco`, `etnico`, `religioso`).
- Soporte multiidioma con carga dinámica de diccionarios.
- Análisis por tipo de riesgo en los informes detallados.
- Estadísticas de diccionario.

### Conocido

- 23 de los 30 idiomas eran stubs de tres palabras. Resuelto en la 3.0.0.

---

## [1.0.0] - 2026-09-08

Versión inicial: validación de nombres contra una lista de términos en español,
con severidad por categoría y validación por lotes.
