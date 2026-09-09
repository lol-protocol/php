# Changelog

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
