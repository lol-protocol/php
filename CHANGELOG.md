# Changelog

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
