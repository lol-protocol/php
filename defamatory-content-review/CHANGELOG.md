# Changelog

## [Sin publicar]

### Añadido

- **Frases que no deben dar alerta** (`tests/fixtures/safe-phrases.php`,
  `SafePhrasesTest`): las frases corrientes que antes se marcaban por error
  («la mer de Chine», «matou a saudade», «el forro del abrigo», «antecedentes de la
  familia»…) y una lista de control que sigue marcándose. Al arreglar un falso
  positivo, la frase se agrega ahí.
- **`public/moderar.php` limita las peticiones por IP** (`Http\RateLimiter`):
  30 por minuto por omisión (`MODERAR_POR_MINUTO`, `0` lo apaga); al pasarse,
  429 con `Retry-After`. La IP se guarda como HMAC con una clave del servidor.
  Antes el README sólo recomendaba hacerlo en nginx. `RateLimiterTest`.
- **README al día**: datos sensibles, datos personales (`privado`) y
  acusaciones en seis idiomas, que ya estaban en el código pero no en el README.
- **Textos largos en el endpoint**: `text` acepta hasta 10.000 caracteres (antes
  2.000) y el cuerpo, hasta 64 KB. Lo que pasa de 2.000 se revisa por partes
  (`LongText`): por párrafos y, si uno es muy largo, por oraciones. Manda la
  parte más grave. Un texto corto se sigue revisando entero («pu⏎ta» se sigue
  viendo). `LongTextTest`.
- **Acusaciones y datos sensibles en portugués, francés, italiano y alemán**:
  delito («roubou dinheiro», «a été condamné», «è un ladro», «hat Geld
  gestohlen»), salud, vida privada y orientación sexual van a revisión, como en
  español e inglés. Son los patrones básicos, sin revisión nativa; «pão e café»,
  «l'oiseau a volé haut» o «er hat Recht» se siguen aprobando.
  `AccusationPatternsEuropeanTest`.
- **Religión, origen étnico y opinión política de alguien** (español e inglés):
  «la familia era judía», «Fulano es mapuche», «su abuelo era de origen árabe»,
  «Fulano es comunista», «he's a republican» van a revisión. Son datos sensibles
  aunque sean ciertos; en genealogía son frecuentes, por eso no se bloquean.
  «el vino es negro», «la iglesia evangélica de Concepción» o «el partido
  comunista» se siguen aprobando. `SensitiveDataPatternsTest`.

### Cambiado

- **Tope de letras sueltas por línea** (`SpacedRunTerms`): más de 120 letras
  deletreadas en una línea van a revisión como «texto deletreado», sin buscar
  insultos dentro. Buscar adentro costaba hasta 1 s por cada 2.000 caracteres;
  ahora un texto adversario de 10.000 caracteres se revisa en unos 100 ms.

### Arreglado

- **README y CONTRIBUTING al día**: los dos ejemplos de la fusión fonética (`Elba Gina`) mostraban
  `riskType: 'ordinario'` y «tipo ordinario» cuando desde 4.3.2 devuelven `fonetico`; «~7.800 términos» pasa a
  «más de 8.000» (hoy son 8.206) y las cifras de islandés, swahili y tagalo de CONTRIBUTING (85, 86 y 101) a las
  de ahora (94, 94 y 109).
- **Lo que caía sobre un corte de un texto largo se aprobaba** (`LongText`).
  Por dos huecos: el corte sólo se hacía en el espacio ASCII, así que con
  saltos de línea, tabuladores o espacios no separables entre palabras partía
  a la fuerza el insulto que cayera en el carácter 2.000 («pu | ta»); y aun
  cortando en un espacio, una frase cuyas palabras sueltas son inocentes
  («heil hitler», «white power», «concha de tu madre») quedaba repartida en
  dos partes. Ahora se corta en el último espacio de cualquier tipo, y la
  costura de cada corte —200 caracteres de cada lado, `LongTextSeam`— se
  revisa también; de ella sólo se suma lo que ninguna de sus dos mitades
  tiene por separado, para no contar dos veces lo que ya halló cada parte.
  Para que las costuras sean pocas, los párrafos seguidos comparten parte
  mientras quepan en 2.000 caracteres (antes cada párrafo era una parte), y
  una costura sin hallazgos no revisa sus mitades. Cuesta algo más: una
  biografía de 10.000 caracteres pasa de unos 310 a unos 480 ms, y el texto
  deletreado más adversario, de 20 a 170 ms. `LongTextCutsTest`.

## [5.0.0] - 2026-10-15

Versión mayor: las clases cambian de namespace (ver «Cambiado (incompatible)»).

### Cambiado (incompatible)

- **Las clases se agrupan en subnamespaces**, uno por carpeta de `src/`. El
  nombre de cada clase no cambia; sólo su `use`:

  | Antes (`DefamatoryContentReview\…`) | Ahora (`DefamatoryContentReview\…`) |
  |---|---|
  | `DefamatoryContentReviewer`, `NameEvaluator` | sin cambio |
  | `LanguageRegistry`, `LanguageAffinity`, `LanguageAccess`, `RelatedLanguageValidator` | `Language\…` |
  | `WordList`, `WordListIndex`, `WordListPhonetics`, `WordListScanner` | `Dictionary\…` |
  | `AccentFolding`, `ScriptFolding`, `Leetspeak`, `LeetspeakFolding` | `Normalization\…` |
  | `PhoneticFolderRegistry`, `AbstractPhoneticFolder`, `CommonPhoneticAccents`, `FusionSupport`, `PhoneticFusionDetector` | `Phonetic\…` |
  | `SpanishPhoneticFolder` … `TurkishPhoneticFolder` (17) | `Phonetic\Folders\…` |
  | `ScoringPolicy`, `ScoringWeights`, `SeverityBands`, `DecisionTable` | `Scoring\…` |
  | `ValidationResult`, `FlaggedTermCollection`, `TermExplanation`, `RiskReportBuilder` | `Report\…` |
  | `ChatLineReviewer`, `ChatLineResult`, `ChatTopics`, `ChatMatches`, `ChatPatternMatcher`, `SpacedLetters`, `RepeatedLetters`, `RepeatedReadings` | `Chat\…` |
  | `TopicInflector`, `TopicInflection`, `SpanishInflection`, `SpanishVerbs`, `EnglishInflection` | `Chat\Inflection\…` |

  Las clases nuevas de esta versión van en `Chat\EverydayWords` y
  `Http\ModerationEndpoint`. Para migrar basta cambiar los `use` (por ejemplo
  `use DefamatoryContentReview\Chat\ChatLineReviewer;`). `phone-directory`
  ya está migrado y pide `^5.0`.
- **Se quita el alias deprecado `PhoneticFolder`** (nombre anterior a 4.3.0
  de `SpanishPhoneticFolder`): su ruta vieja deja de existir con los
  subnamespaces. Usar `Phonetic\Folders\SpanishPhoneticFolder`.
- **`config/languages/supported-languages.php` pasa a
  `config/supported-languages.php`**, junto a `language-families.php` y
  `risk-categories.php`: `languages/` queda sólo con los diccionarios. Quien
  use su propia copia de `config/` debe mover ese archivo.
- PSR-4 apunta a `src/` (antes `src/DefamatoryContentReview/`, que repetía
  el nombre del namespace).
- `tests/` sigue la misma división (`tests/Chat/`, `tests/Phonetic/`…, con
  namespace `Tests\<Carpeta>`). `EdgeCasesValidationTest` pasa a
  `Language/LanguageAffinityFormatTest`, `TopicInflectionTest` a
  `Chat/Inflection/InflectedFormsTest`, y las fixtures `chat-lines.php` y
  `chat-topics-lines.php` a `chat-lines-detection.php` y
  `chat-lines-coverage.php`, por el test que las usa. `FileSizeLimitTest` recorre subcarpetas.

### Añadido

- **Flexión en 13 idiomas más** (además de alemán, neerlandés y turco, que ya
  la tenían): sueco, danés, noruego (`NordicInflection`), ruso, ucraniano, polaco,
  checo, eslovaco, búlgaro, rumano (`SuffixInflection`), húngaro, griego y hebreo.
  Cada uno genera las clases regulares —plural y, donde se declina, los casos
  («идиотом», «idiotę», «seggfejek», «אידיוטים»)— de unas 150–200 entradas del
  diccionario (sin etnia, religión ni discapacidad) y de las listas de chat; los
  verbos regulares del ruso, el ucraniano, el polaco y los nórdicos. Lo generado
  llega como mucho a revisión. Medido contra las 50 000 palabras más frecuentes de
  cada idioma, las palabras nuevas son declinaciones de insultos que ya se
  detectaban; las que chocaban con palabras comunes («tyve» = veinte, «черта» =
  rasgo, «moči» = poder, «גנבה» = robo) se excluyeron. Rasgo nuevo `infl` para la
  forma declinada. Faltan árabe, finés, islandés, hindi, suajili y tagalo (sin
  reglas seguras o sin lista de frecuencias para medirlas). Planillas de
  revisión regeneradas. `DeclensionTest`.
- **Flexión en finés, árabe, hindi y tagalo** (`FinnishInflection`, `ArabicInflection`,
  `HindiInflection`, `TagalogInflection`): finés con los casos y la armonía vocálica
  («paskaa», «vittuun», «idiootin»), con las dos raíces de la alternancia de consonantes
  (`FinnishGradation`); árabe con plurales sanos y una tabla de plurales rotos
  («كلاب», «أغبياء», `ArabicPlurals`); hindi con los casos oblicuos y los plurales;
  tagalo con el ligamento («tangang», «gagong»). También se incluyen ahora las entradas
  con `nameCollision` de los 20 idiomas con flexión (las formas generadas no heredan la marca:
  «Козлов» es apellido, pero «козла» no). Medido contra las 50 000 palabras más
  frecuentes (6 033 en tagalo y 2 968 en hindi): casi todo lo nuevo son declinaciones de
  insultos. Se excluyeron las que chocaban con palabras comunes («قتله» = su asesinato,
  «roskaa» = basura, «koen» = nombre, «козлов» = el apellido Kozlov). Faltan islandés y suajili.
- **Artículo y preposiciones pegados, en hebreo y árabe** (`CliticPrefixes`,
  `SearchStrategy`): «הטיפש» (el tonto), «ובחרא», «الغبي», «بالغبي» no se
  encontraban, porque la palabra no estaba tal cual. Ahora, si no está, se prueba
  sin hasta tres letras iniciales (hebreo: ו ה ב כ; árabe: ال و ب ل ف ك) y un resto
  de tres letras o más. Lo hallado así trae `prefix => clitic` y nunca bloquea solo
  (baja a revisión, como las letras dobles). En hebreo no se quitan «ל», «מ» ni
  «ש»: con ellas, «לחיות» (vivir), «להחזיר» (devolver), «במטבח» (en la cocina) y
  «שנוכל» (que podamos) daban alerta. Medido con las 50 000 palabras más frecuentes:
  hebreo 232 → 450 y árabe 165 → 320, casi todo «el/la + insulto» (la «ودودة»,
  «amistosa», es la excepción conocida). «للعين» (para el ojo) pasa de `legit` a
  `everyday`. `CliticPrefixesTest`.
- **Una forma generada no pisa una entrada escrita** ni entra en la búsqueda
  fonética de nombres. Con los acentos plegados «prasě» y «prase» son la misma
  clave, y la forma generada reemplazaba al lema (y su severidad); además, cada
  forma de más multiplicaba el índice fonético: `validateName` iba 3–5 veces más
  lento en ruso, polaco y checo. `GeneratedFormsTest`.
- **Palabras de varios países en el español base** (el idioma sigue siendo
  `spa`, sin variantes por país): «puto» (`targeted`: «eres un puto» se bloquea,
  «los putos deberes» pasa), «joto» y «trolo» se bloquean; «pija» y «pico» se
  suman a «coger» y «concha» en `ambiguous`, porque en otros países son
  cotidianas. `RegionalWordsTest`.
- **Respuestas de `review/urgente/spa-genero.csv`**: «vaca» y «marrana»
  dirigidas se bloquean, como «cerda»; «gorda» dirigida va a revisión (antes sólo
  se informaba); «¡cojones!» baja a `low`, porque es una exclamación común; se añaden
  «pendeja» (high), «lagarta» (medium) y «pájara» (low). El resto se confirma
  como estaba.
- **`'whenTargeted' => 'high'`** en una entrada: sube a esa severidad cuando va
  dirigida (`TargetContext`, `context => targeted`). «cerda» la usa: sola sigue en
  revisión porque también es apellido, pero «eres una cerda» se bloquea y «María
  Cerda» se aprueba.
- **Letras repetidas en todos los idiomas.** Las rachas de 3 o más («cazzzo»,
  «Arschloooch», «сууука») se leen siempre: contra las 50 000 palabras más
  frecuentes de 26 idiomas, ningún falso positivo nuevo. Reducir las dobles
  («puuta», a revisión) pasa de español e inglés a 18 idiomas más (portugués,
  alemán, ruso, polaco, turco, árabe, sueco, danés, rumano, indonesio, checo,
  eslovaco, húngaro, búlgaro, ucraniano, griego, coreano, vietnamita), donde la
  misma medición casi no cambia; lo legítimo que apareció («Pollack», «Scott»,
  «sesaat», «للعين») va a `legit`. Italiano, francés, neerlandés, noruego, finés,
  islandés y hebreo no reducen dobles: ahí son parte de la palabra («anno» →
  «ano», «penne» → «pene»). `meta.collapseRepeats` acepta ahora `true`, el
  valor por defecto (sólo rachas de 3 o más) o `false`.
- **Plurales y declinación en alemán, neerlandés y turco** (`GermanInflection`,
  `DutchInflection`, `TurkishInflection`): «Huren», «Idioten», «dumme/dummer…»,
  «kutten», «apen», «domme», «orospular», «salaklar». En alemán y neerlandés el
  adjetivo declinado lleva el rasgo `infl`. `forms` en 470 entradas, fuera de
  etnia, religión y discapacidad y sin las que chocan con palabras comunes
  («pik» → «pikken», «nicht» → «nichten»); lo generado llega como mucho a
  revisión. Las formas nuevas que aparecen en las listas de frecuencia son
  todas declinaciones de términos que ya se detectaban.

- **Diminutivos y plurales de insultos sexualizados en portugués, italiano
  y francés** (#91). `Diminutives` genera los diminutivos regulares del
  español (-ito/-ita, -cito/-cita) y del portugués (-inho/-inha,
  -zinho/-zinha) con sus rasgos (`dim.sg`, `dim.f.pl`…). Cambian el tono en
  cualquier dirección («putita» degrada más, «cerdita» es cariñoso), así
  que sólo se generan si `by` lo pide con una clave `dim`. Español: putita,
  zorrita. Portugués: putas, putinha, vagabundas, rameiras. Italiano:
  puttane, troie, zoccole, mignotte, baldracche y el femenino de `porco`.
  Francés: putes, putains, salopes, garces, catins. Quedan fuera los
  plurales con otro uso cotidiano (piranhas, galinhas, vacche, traînées).
  `PortugueseInflection` reconoce «-ão» con tilde («ladrões»). Medido con
  las 50.000 palabras más frecuentes de cada idioma: cambian exactamente
  las formas declaradas. Las planillas de revisión traen ahora también las
  filas de diminutivo (`forma:dim.sg de:puta`), y `SheetVocabulary` acepta
  esas etiquetas al aplicarlas (antes sólo admitía rasgos de un punto, así
  que una respuesta sobre «putitas» se habría rechazado). Planillas de spa,
  por, ita y fra regeneradas.

- **Frases con forma deletreadas**: «t e v o y a m a t a r», «i w i l l k i l l y o u»
  se bloquean como la amenaza escrita normal (`SpacedRunTerms`).
- **Más evasión**: letras encerradas («p⃝u⃝t⃝a⃝», «p⃣u⃣t⃣a⃣»), letras parecidas del
  alfabeto fonético, armenio y cherokee («putɑ», «idiօta», «ᏢUTA») y «+» por «t» («pu+a»).
- **Más acusaciones y datos sensibles** (español e inglés): a una familia entera
  («los Pérez son unos ladrones», «esa familia es de delincuentes»), antecedentes
  penales, deudas («está en DICOM», «no paga la pensión»), enfermedades («tiene
  cáncer»), aborto y vida privada («se acuesta con su secretaria», «tiene otra familia»).
- **Marcalibros revisa el texto de cada marcalibro** con esta librería
  (`marcalibros-plataforma-pos-brand-identity/app/src/LabelPolicy.php`): se comparte
  por enlace desde el dominio de la plataforma. Lo que no se aprueba no se guarda.
- **Datos personales en el chat** (tipo `privado`, `PersonalData`): un RUT con
  dígito verificador válido, un correo, un teléfono con prefijo o anunciado
  («fono», «llámalo al») y una dirección anunciada («vive en la calle…») van
  a revisión, en cualquier idioma. Las fechas («1939-1945») no. `PersonalDataTest`.
- **Más datos sensibles y vida privada** (español e inglés): orientación sexual
  («era homosexual», «salió del clóset»), filiación («es hijo ilegítimo», «fue
  adoptada», «no es hijo de»), suicidio, violencia en la familia («le pega a su
  señora») e internación psiquiátrica. Van a revisión. `SensitiveDataPatternsTest`.
- **Letras tachadas, subrayadas, zalgo, con punto y versalitas**: «p̶u̶t̶a̶»,
  «ṗụṭạ», «ᴘᴜᴛᴀ» (`StyledLetters`; `AccentFolding` lleva el latín extendido
  adicional a su letra base, salvo en vietnamita). `MarkEvasionTest`.
- **Evasión con la forma del texto** (`LayoutEvasionTest`):
  - frases del diccionario escritas pegadas: «hijodeputa», «putamadre», «siegheil»;
  - sílabas sueltas: «hi jo de pu ta», «pu   ta», «pu» y «ta» en dos líneas. Con
    sólo dos trozos separados por un espacio no se unen: «la mer de Chine» no es «merde»;
  - letras sueltas separadas por saltos de línea («p⏎u⏎t⏎a»);
  - etiquetas y entidades HTML («pu<b></b>ta», «pu&#116;a»);
  - letras en cuadros e indicadores regionales («🅿🆄🆃🅰», «🇵🇺🇹🇦»);
  - texto forzado de derecha a izquierda («\u202Eatup» se ve «puta») y texto
    cabeza abajo («ɐʇnd», `UpsideDown`);
  - la «İ» turca: «İBNE» en minúsculas dejaba un punto combinante.

  El chat hace una segunda pasada con la línea entera limpia, que sólo suma lo
  que la primera no vio: así el texto censurado sigue siendo el que se escribió.
- **Insultos de otros países hispanohablantes** (categoría `latinoamericanos`):
  «careverga», «gonorrea» (sólo dirigido), «conchatumadre», «mamaguevo»,
  «comemierda», «chinga tu madre», «cojudo», «forro» (sólo dirigido)…
- **Siglas y consignas de odio en los 33 idiomas**: «nazi» en la forma de
  cada idioma («nazist», «natsi», «náci», «нацист», «ναζί», «נאצי», «نازي»,
  «नाज़ी», «phát xít», «mnazi»…, con vocativos como «nazisto» o «nazisin»),
  «neonazi», «KKK», «ACAB», «sieg heil», «heil hitler», «white power», «14/88»,
  consignas locales («зиг хайль», «biała siła», «witte macht»), y «facho»,
  «poder blanco», «poder branco». En japonés, coreano, cantonés y tailandés
  sólo las consignas: sin espacios no se sabe si «nazi» va dirigido a alguien.
  «Nazi» y «Náci» (hipocorístico húngaro de Ignác) llevan `nameCollision`. «Nazi» lleva `targeted`: «eres un
  nazi» va a revisión, «mi abuelo luchó contra los nazis» se aprueba. «14 88»
  con espacio o guion no se marca: es un rango de páginas en una cita.
  `HateTermsTest`. `EthnicTermsTrackingTest` deja de contar las frases (una
  consigna no es un apellido) y su umbral baja de 195 a 165.
- **Separadores y símbolos de hacker**: «p~u~t~a», «p|u|t|a», «p(u)t(a)»,
  «p|_|ta», «1d!0t4», «€stúpido», «N.4.Z.|» (`HackerSpelling` en el nuevo paso
  `WholeWords` de `WordListScanner`), y rachas de caracteres sueltos separados
  por espacios o símbolos: «p - u - t - a», «N 4 Z 1», «N. A. Z. I»
  (`SpacedLetters`). «!» y «|» sólo se leen como letras dentro de una palabra.
  `SymbolEvasionTest`.

- **Acusaciones contra una persona en el chat** (español e inglés): «Fulano
  estafó a mi familia», «fue condenado por…», «es narco», «tiene sida», «le pone
  los cuernos», «funen a ese tipo». Antes se aprobaban: no traen palabrotas.
  Ahora son `difamatorio` con etiqueta `acusación de delito`, `dato sensible
  (salud)`, `vida privada` o `funa o escrache`, y van siempre a revisión humana
  (pueden ser ciertas). `AccusationPatternsTest`.
- **Chilenismos** en el diccionario español: «conchetumare», «concha de tu
  madre», «culiao», «aweonao», «saco de wea», «maraco», «flaite»… «weón»,
  «huevón», «boludo» y «ctm» también son trato de confianza o exclamación: en
  el chat sólo cuentan dirigidos a alguien («eres un weón» sí, «hola weón» no),
  y «huevón» y «boludo» bajan de `high` a `medium`. `ChileanVocabularyTest`.
- **`EvasionCleaner`**: letras cirílicas o griegas camufladas («рuta»), espacios
  de ancho cero, guion blando, ancho completo («ｐｕｔａ»), negrita matemática
  («𝐩𝐮𝐭𝐚»), emoji en medio («pu🙂ta») y selectores de variante ya no esconden
  un insulto, ni en el chat ni en los nombres. `UnicodeEvasionTest`.
- **`MaskedTerms`**: «p*ta», «m*erda», «c*brón» van a revisión. `MaskedTermsTest`.
- **`docs/ORIGEN.md`** (por qué existe la librería) y **`docs/RIESGOS.md`**
  (qué cubre, qué no y qué tiene que hacer la plataforma, con el marco legal
  chileno a título orientativo).
- **Severidad por forma: el plural y el femenino no ofenden lo mismo.**
  «Cerdo» suena mal y «cerda» horrible; «zorros» son animales; «¡cojones!»
  es una interjección. Hasta ahora, de los pares masculino/femenino de los
  diccionarios, 166 de 168 tenían la misma severidad en español (96/97 en
  portugués, 80/81 en italiano, 48/48 en francés), y las listas de chat
  copiaban la severidad de la base a todas sus formas. Ahora:
  - cada forma generada lleva sus rasgos (`m.sg`, `f.pl`, `sg`, `pl`…) y
    la entrada le da su severidad con `by` (`FormSeverity`; gana el más
    específico, `none` la deja fuera);
  - lo escrito manda: una forma que ya es entrada propia no se genera;
  - las formas generadas no heredan `nameCollision` («Cerda» es apellido,
    «cerdas» no);
  - los diccionarios también aceptan `forms`, y lo generado sin `by` llega
    como mucho a `medium`: lo que nadie revisó lo ve una persona.
  En español: «cerdas» y «guarras» se bloquean y «cerdos»/«guarros» van a
  revisión; «putas», «zorras», «perras» ya no pasan limpias. Medido con las
  50.000 palabras más frecuentes: cambian exactamente esas cinco. Las
  planillas de revisión traen una fila por forma (`forma`), y
  `apply-review.php` escribe la respuesta en el `by` de la entrada
  (`FormEditor`). `review/urgente/spa-genero.csv`: 22 decisiones de género
  y número para un hablante nativo. `AsymmetricFormsTest`, `FormReviewTest`.

- **En el chat, animales, discapacidad, identidad, brujería y palabrotas suaves
  sólo cuentan dirigidos a alguien** (`TargetContext`): «eres un cerdo», «seu
  porco», «ты свинья» van a revisión; «comí cerdo», «das Schwein im Stall»,
  «mi abuelo es cojo» se aprueban, con el hallazgo en `low` y `context =>
  untargeted`. Marcas por idioma en la clave `targeted` de 29 listas de temas;
  `'targeted' => true|false` en una entrada del diccionario la suma o la saca
  de la regla. Una palabra sola, las frases de varias palabras («hijo de
  perra») y las líneas con otro insulto firme cuentan como antes. Medido con las
  listas de frecuencia: 28 palabras frecuentes dejan de censurarse («porco-espinho»,
  «hot-dog», «mù-tạt», «membabi-buta»…) y ninguna palabra sola cambia de decisión.
  Resuelve las cinco decisiones de criterio que CONTRIBUTING dejaba abiertas.

- **Diccionario inglés: plurales y formas coloquiales** (46 entradas):
  «bastards», «idiots», «bitches», «cunts», «retards»… y «fuckin»,
  «motherfucking», «cocksucker», «dumbass», «dipshit», «bullshit»,
  «son of a bitch», cada una con la categoría y severidad de su forma base
  (`dicks` con `nameCollision`, como `dick`). Los plurales pasaban limpios
  en el chat porque los diccionarios no generan formas. Medido con las
  50.000 palabras más frecuentes: las 41 nuevas que se censuran son
  exactamente esas; ninguna palabra cotidiana. Quedan fuera las que tienen
  otro uso común («pricks», «pussies», «jerky»).

- **`บ้า` (tailandés, «loco»), cambio provisional hasta la revisión
  nativa.** Era el 45 % de las palabras frecuentes que el chat censuraba,
  casi todas exclamaciones («บ้าจริง», «maldita sea»): el chat lo ignora
  suelto (`ambiguous`) y sus apelativos (`ไอ้บ้า`, y nuevos `คนบ้า`,
  `ยัยบ้า`, `อีบ้า`, `นังบ้า`, `medium`) siguen yendo a revisión. Las 20.000
  palabras más frecuentes censuradas pasan de 212 a 126. `validateName()`
  no cambia. Planillas cortas en `review/urgente/` (tha: `บ้า`, sus
  apelativos y `แขก`; jpn: `魔女`, `おし`) con la pregunta concreta y frases
  reales; `apply-review.php` acepta ahora `sin ambiguous` para revertirlo.

- **Letras sueltas que juntan varias palabras** («h o l a p u t a»,
  «p u t a p u t a», «p u t a m a d r e»): antes la racha se unía entera y,
  al no ser una palabra del diccionario, se aprobaba. `SpacedRunTerms`
  busca dentro de la racha los términos de 4 letras o más (sin solaparse,
  de más largo a más corto) y los manda a revisión, nunca a bloqueo,
  porque una palabra deletreada puede contener un insulto («c o m p u t a d o
  r a»): medido, el 0,7 % de las palabras frecuentes de español e inglés.
  La racha que es entera un insulto sigue bloqueando. `SpacedRunTermsTest`.

- **`bin/apply-review.php`: de la planilla revisada a `config/`.** Lee una
  planilla de `review/` llenada por un hablante nativo y aplica sus
  respuestas: quitar un término, cambiarle la severidad, marcarlo
  `ambiguous`/`nameCollision`, mandar una palabra frecuente a `everyday` o
  sacar una excepción. Sin `--apply` sólo muestra el plan. Edita únicamente
  la línea de cada entrada (`Review\ConfigEditor`), y no aplica nada si
  hay respuestas que no entiende o si la planilla es más vieja que
  `config/`. Patrones y comentarios quedan listados para hacerlos a mano.
  Las planillas de jpn, tha, yue, por, ita y fra se regeneraron con la
  segmentación y la inflexión nuevas; `หมู` («cerdo», también «carne de
  cerdo») pasa a `ambiguous`. `ConfigEditorTest`, `ReviewSheetTest`.

- **Plurales, géneros y conjugación en portugués, italiano y francés**
  (`PortugueseInflection`, `ItalianInflection`, `FrenchInflection`). La
  mecánica de conjugación por tablas pasa a `RomanceVerbs`, que comparten
  con `SpanishVerbs` (sin cambio de comportamiento en español). Incluyen los
  cambios de ortografía regulares (ficar→fique, cercare→cerchi,
  manger→mangeons) y los pronombres pegados («matá-lo», «ammazzarti»,
  «baise-moi»). Sus listas de chat declaran `forms` como la española.
  Medido con las 50.000 palabras más frecuentes de OpenSubtitles: las que
  se censurarían pasan de 220 a 235 (por), de 208 a 242 (ita) y de 251 a
  284 (fra), casi todas formas reales («massacraram», «stuprata», «égorgé»).
  Las cinco que eran palabras cotidianas van a `everyday`: «violent»,
  «violons», «viola», «fucili», «pôr-nos». `RomanceInflectionTest`.

- **Japonés, cantonés y tailandés se buscan dentro de la frase**
  (`Dictionary\WordSegmenter`, extensión `intl` opcional). ICU parte los
  tramos sin espacios en palabras («お前はバカだ» → お前 | は | バカ | だ) y
  las piezas contiguas se vuelven a unir en las ventanas de búsqueda
  («仆|街» → «仆街»). Antes sólo se reconocía la palabra suelta. La censura
  ya no exige frontera de palabra junto a esas escrituras. Sin `intl` el
  comportamiento es el de antes. La CI instala `intl`.
  Medido con las 20.000 palabras más frecuentes de OpenSubtitles: las que
  se censurarían pasan de 36 a 60 (jpn), de 49 a 212 (tha) y de 60 a 62
  (yue), casi todas insultos y amenazas reales que antes no se veían
  («ฉันจะฆ่าคุณ», «くそ野郎»). Los falsos positivos que aparecieron eran
  palabras cotidianas del diccionario dentro de otras («牛» en «牛丼», «ลา»
  en «ลาก่อน», «สัตว์» en «สวนสัตว์»): 18 entradas pasan a `ambiguous` y se
  añade «ไอ้เลว» para que «ไม่เลว» («no está mal») deje de marcarse.
  Quedan para revisión nativa `บ้า` (88 de las 212, «loco»), `แขก`, `おし`
  y `魔女`, de categorías que no se tocan sin ella. Amenazas directas en
  tailandés («จะฆ่าคุณ»…) como frases `high`. `WordSegmenterTest`.

- **Listas de temas de chat en los 33 idiomas** (`config/chat-topics/`):
  `sexual`, `belico`, `ambiguous` y, en 18 idiomas de alfabeto latino y
  cirílico, `patterns` de amenaza y de amenaza de violación (fra, ita, por,
  deu, nld, swe, dan, nor, fin, ron, rus, ukr, pol, ces, slk, bul, hun, tur,
  ind). Antes sólo existían spa y eng: en el resto «te voy a matar» se
  aprobaba. Los otros 12 (ara, ell, heb, hin, isl, jpn, kor, swa, tgl, tha,
  vie, yue) no tienen patrones y usan frases literales de hasta 3 palabras.
  `ChatTopicsCoverageTest` comprueba por idioma una línea sexual (censurada),
  una amenaza (`reject`) y una cotidiana (`approve`). Escritas **sin hablante nativo**, sólo con forma
  base y `also` a mano (no hay inflexión fuera de spa/eng), y en jpn, yue y
  tha sólo reconocen la palabra suelta. Ver «Lo que no cubre» en el README.
- **Marca `'ambiguous' => true` en las entradas de los diccionarios**:
  palabras cotidianas que también son vulgares («яйца», «leche», «כוס»,
  «butt», «sega»…). `ChatLineReviewer` las ignora —«Купи яйца и хлеб» pasa
  de `review` a `approve`, «כוס מים» de `reject` a `approve`—; los nombres
  las siguen marcando. Marcadas ~60 entradas en 15 idiomas.
- **Auditoría y ampliación de los 27 diccionarios `moderate` y `basic`**
  (~1.500 términos nuevos, ~7.800 en total). Hecha **sin hablante nativo**:
  los idiomas siguen en su nivel y `coverage` no cambia. Protocolo: sólo
  términos de alta confianza, ninguno étnico, religioso ni de discapacidad,
  y cruce contra nombres reales (corpus ampliado a 20×20 o más en los 33
  idiomas, ahora también heb, jpn, yue, tha, vie). `NameCollisionAuditTest`
  fija las colisiones auditadas. Ver `CONTRIBUTING.md` para lo que sigue
  pendiente de revisión nativa.
- **Vocabulario ampliado en los 3 diccionarios `basic`**: islandés
  (60 → 65 términos), swahili (61 → 71) y tagalo (60 → 80). Términos reales
  nuevos en `animal`, `intelectual`, `fisico`, `discapacidad`, `moral`,
  `genero`, `ordinario` y `burlesco` (categoría nueva en swahili y tagalo).
  Ninguno se generó por plantilla ni traducción automática de otro idioma.
  Sin revisión de hablante nativo todavía (ver `CONTRIBUTING.md`): sigue
  siendo la prioridad antes de subir cualquiera de los tres a `moderate`.
  `DictionaryIntegrityTest` y `CommonNamesFalsePositiveTest` verificados.
- **`ChatLineReviewer`**: revisa una línea de chat y dice si censurarla,
  clasificando lo encontrado como `difamatorio`, `burlesco`, `sexual` o
  `belico`, con decisión (`approve`/`review`/`reject`) y la línea censurada.
  Listas de temas en `config/chat-topics/` para español e inglés.
  Entiende plurales, géneros y conjugaciones regulares (campo `forms` de las
  listas: «desnudas», «fóllame», «masturbándose», «they massacred»); amenazas
  y frases con forma (`patterns`: «te voy a matar», «ojalá te mueras»,
  «I will kill you», «kill yourself»); letras sueltas («p u t a», «vamos a
  f o l l a r»); y palabras ambiguas (categoría `ambiguous`: «vamos a coger
  el bus» o «está de bomba» ya no se marcan, «quiero coger, mándame nudes»
  sí). Un término que también es apellido (`nameCollision`) baja a revisión
  en vez de bloquear, igual que con los nombres. `censored()` busca por
  palabra entera sin distinguir mayúsculas acentuadas ni el separador entre
  las palabras de una frase. Ver `examples/10-chat-lines.php`.
- **Letras repetidas en el chat** («puuuuta», «mmmierda», «te voy a
  mataaaar»): cada racha de letras iguales se lee como 1 letra o como 2, sin
  tocar el texto, así «follarr» sigue siendo «follar». Tres o más iguales
  conservan su severidad; dos sólo bajan a revisión (`repeat => doubled`)
  porque pueden ser legítimas («calle», «Pratt»). Cada idioma lista en
  `legit` las palabras que nunca se leen reducidas, medidas contra ~1 millón de
  palabras reales (0 falsos positivos con tres o más; los pocos restantes con
  dos son erratas evidentes). Sólo español e inglés (`meta.collapseRepeats`).
- **`js/limit-repeated-letters.js`**: tope de 2 letras iguales seguidas para
  los campos de texto del front (nunca 3). Recorta lo que se escribe o pega,
  conserva el cursor, respeta los IME y se engancha solo a `data-max-repeat`.
  18 tests con `node --test`, verificado además en Chromium.
- **Ampliación de los 6 diccionarios `comprehensive`** en los idiomas donde
  hay confianza real de hablante fluido/nativo — español ya tenía 601
  términos (el más grande con diferencia) y no se tocó:
  - Alemán: 258 → 282 (+24; una entrada añadida, `scheisse`, era duplicado
    normalizado de `scheiße` ya existente — `ß` pliega a `ss` — y se retiró)
  - Inglés: 400 → 416 (+16)
  - Francés: 298 → 312 (+14)
  - Italiano: 303 → 315 (+12)
  - Portugués: 323 → 333 (+10)

  En los cinco, deliberadamente **no** se tocaron las categorías de
  discapacidad, étnico ni religioso: ya tienen buena cobertura y el costo
  de categorizar mal uno de esos términos supera el beneficio de sumar uno
  más sin que un hablante nativo lo confirme. Verificado en cada idioma
  contra nombres reales comunes (`Hans Müller`, `John Smith`, `Jean
  Dupont`, `Giuseppe Russo`, `João Silva`, etc.) sin falsos positivos.
- **Medición de falsos positivos del chat** contra las 50.000 palabras más
  usadas de 32 idiomas (`bin/false-positives.php`, listas de FrequencyWords) y
  **planillas de revisión nativa** por idioma (`bin/review-sheets.php` →
  `review/<código>.csv`): cada término con categoría, severidad, marcas, rango
  de frecuencia y decisión actual del chat, más las palabras frecuentes que se
  censurarían sin ser un término. Ver `review/README.md`.
- **Endpoint HTTP de moderación** (`public/moderar.php` + `ModerationEndpoint`):
  `POST {"text", "language"}` → decisión, tipos de contenido, línea censurada y
  términos encontrados, en JSON. Límites de entrada (sólo POST, 16 KB de cuerpo,
  2.000 caracteres, idioma soportado) con códigos de error propios. Más una
  **demo** (`php -S localhost:8000 public/router.php`) que junta el tope de
  letras repetidas del front con la decisión del servidor.
  `ModerationEndpointTest` y `ModerationServerTest` (con `php -S`).
- **Clave `everyday` en `config/chat-topics/`**: palabras cotidianas que sin
  tildes coinciden con un término («moc» → «moč», «katıl» → «katil», «possède»
  → «possédé», «santa» → «sánta») y que, escritas así, el chat no busca
  (`EverydayWords`). 21 palabras en 9 idiomas; `ChatTopicsExemptionsTest`
  verifica que cada una siga haciendo falta.

### Arreglado

- **Hallazgos duplicados por letras sueltas**: en «p u t a, eres una puta» la
  «puta» escrita normal también se rotulaba como deletreada, y salían tres
  hallazgos (uno «p u ta»). `ChatMatches::restore` devuelve el texto deletreado
  sólo tantas veces como rachas se unieron, y sólo a palabras enteras.
- **El endpoint mostraba dos veces una palabra que es de dos tipos**: «follar»
  (insulto y contenido sexual) salía una vez por tipo. Ahora sale una por
  aparición, con `contentTypes` (y el primero en `contentType`, como antes).

- **Un byte UTF-8 inválido aprobaba la línea entera**: «eres una puta \xff»
  hacía fallar todas las expresiones regulares y no se encontraba nada. Lo mismo
  con los nombres. Ahora el texto pasa por `mb_scrub` antes de revisarse.
- **La fusión de nombre y apellido se evadía con Unicode**: «Elba Gіna» (con
  «і» cirílica), un espacio de ancho cero o letras de ancho completo dejaban
  pasar «el vagina». `NameEvaluator::applyPhoneticChecks()` limpia nombre y
  apellido con `EvasionCleaner` antes de buscar fusiones.
- `PRIVACIDAD-Y-HONRA.md` en la raíz del repositorio: por qué la privacidad y
  la honra son la condición del proyecto, qué está resuelto y los cabos sueltos
  (`url-routing` sin filtro cuando haya escritura, personas vivas en
  `phone-directory`, denuncia y registro). Enlazado desde los README.

- **Un insulto se colaba eligiendo otro idioma**: el endpoint revisaba sólo el
  `language` que manda quien escribe, así que «Fulano es un estafador, hijo de
  puta» con `"eng"` se aprobaba. Ahora revisa además los idiomas de la
  plataforma (`spa` y `eng` por defecto) y responde `checkedLanguages`
  (`CombinedReview`, `ModerationLanguagesTest`).
- **Entradas que nunca se detectaban enteras**: las de más de 3 palabras
  («concha de tu madre», «son of a bitch», «walang utang na loob») y las de
  guion («lèche-cul», «puxa-saco», de las que sólo se veía «cul» o «saco»).
  `WordListScanner` busca frases de hasta 5 palabras y, en una palabra con
  guion, la palabra entera gana a su parte. `DictionarySelfDetectionTest` exige
  que cada entrada de los 33 diccionarios se encuentre a sí misma.

- **Un insulto pegado a un signo no se detectaba**: «puta!», «idiota?»,
  «¡idiota!», «(rata)» y ««imbécil»» se aprobaban, porque `WordListScanner`
  sólo cortaba palabras en espacios, guiones, puntos y comas. Ahora también en
  `! ¡ ? ¿ ; : " “ ” „ « » ( ) [ ] { } …`.

- **Acentos escritos aparte esquivaban el diccionario.** «pu» + «́» + «ta»
  (la «ú» descompuesta que dejan algunos teclados y el copiar y pegar)
  pasaba como «púta» sin plegar: se aprobaba en el chat y en
  `validateName()`. `AccentFolding` compone el texto (NFC, con `intl`; sin
  ella quita las marcas que siguen a una letra latina) antes de plegar.
  Los nombres reales escritos así («José García») siguen siendo válidos.
- **Vocales que son marcas combinantes** (tailandés, hindi, árabe
  vocalizado): la censura podía tapar media palabra («सूअरों» → «****ों»)
  porque la frontera de palabra no las contaba como parte de ella;
  `everyday` no tapaba esas palabras; `ChatPatternMatcher::fold()` las
  cambiaba por espacios, así que un patrón en hindi nunca habría
  coincidido; y las letras sueltas no las admitían. Ahora todas cuentan
  `\p{M}` como parte de la palabra. `CombiningMarksTest`.

- **Endpoint de moderación: revisión de seguridad.** `public/moderar.php`
  ya no puede mostrar rutas ni trazas: desactiva `display_errors` y ante un
  fallo responde un 500 genérico (`error_interno`) y deja el detalle en el
  log. Exige `Content-Type: application/json` (415 si no), así una página
  de otro origen no puede mandarlo como formulario simple, y añade
  `Content-Security-Policy: default-src 'none'`. Medido el coste por
  petición en el peor caso (2.000 caracteres de letras sueltas, repetidas,
  japonés, tailandés): ≤ 45 ms. El README explica cómo limitar peticiones
  en nginx, que es lo que el endpoint no puede hacer solo.

- **Falsos positivos del chat en palabras muy usadas**, encontrados con la
  medición de arriba: de 5.368 a 5.159 palabras frecuentes censuradas, y de 213
  a 166 entre las 1.000 más usadas de cada idioma.
  - `AccentFolding` ya no pliega las letras propias del alfabeto en danés y
    noruego (`æ ø å`), sueco y finés (`å ä ö`) ni vietnamita (vocales con tono
    y `đ`): «høre» (oír) se leía «hore», «når» (cuando) «nar», «höra» «hora»,
    «tai» (o) «täi» (piojo), «dài» (largo) «dái». Contrapartida: «hore» escrito
    «høre» ya no se marca.
  - `'ambiguous' => true` en 78 términos de 29 diccionarios que ante todo son
    palabras cotidianas: «crazy», «verrückt», «fou», «louco», «preto», «negro»,
    «kanker» (también «cáncer»), «أمي» (mi madre), «หนู» (ratón; «yo» al hablar
    una mujer)… El chat los ignora; `validateName()` los sigue marcando.
  - búlgaro «гол» (desnudo) pasa a `ambiguous` en los temas de chat: también
    es «gol».

- **`ChatPatternMatcher::fold()` borraba todo lo que no fuera `[a-z0-9]`**,
  así que ningún patrón en cirílico, griego, árabe, hebreo, CJK, etc. podía
  coincidir nunca. Ahora sólo sustituye por espacio lo que no es letra ni
  número.
- **Apellidos y nombres reales se rechazaban en automático** por términos
  de severidad alta sin `nameCollision`. El peor caso: en árabe, `عبد`
  («Abd», primer elemento de Abdullah, Abdulrahman, Abdulaziz…) rechazaba
  los nombres más comunes; se quitó. También `כלב` (Kalev, hebreo),
  `Çolak`/`Topal` (turco), `Cioară` (rumano), `Горбань` (ucraniano),
  `Alfons` (polaco, danés), `Abe`/`So`/`Lam`/`Tai`/`Pina`… Se marcaron
  ~250 colisiones.
- **Mismo barrido en los 6 `comprehensive`** (~110 marcas `nameCollision`:
  «Dick», «Cock», «Hooker», «Coon», «Kraut», «Depp», «Teufel», «Gobbo»,
  «Troia», «Vacca», «Nègre», «Verdugo»…). Se quitan `ano` (español: «año»
  se pliega a «ano» por la ñ) y `sarasa`, que disparaba la fusión con
  «Sara Sánchez/Santos».
- **Entradas que no eran insultos**: `мамка` (mamá, búlgaro), `ruska`
  (rusa, checo), `top` (pelota, turco), `αδερφή` (hermana, griego),
  `תחת` («debajo», hebreo), `bóng` (pelota, vietnamita), `ममी` (mamá,
  hindi) y términos neutros de orientación (`лесбиянка`, `homosexual`,
  `समलैंगिक`, `게이`…), de nacionalidad o de raza (`maďar`, `黒人`, `흑인`).
  `hawara` (swahili) disparaba la fusión con «Hawa» + «Rashidi».
- **Vietnamita: `đ` se plegaba a `d`**, así que «Dần» (nombre propio)
  coincidía con «đần» (tonto). Ahora `đ` es letra propia, como `ă â ê ô`.
- **Un término presente en varios idiomas emparentados se contaba dos
  veces** en `$reviewer->related()->validate()`. La deduplicación usaba como
  clave la posición del hallazgo en la lista de cada idioma, que se corre
  cuando un idioma encuentra algo que otro no: en «Cerdo Idiota», «idiota»
  es el 2º hallazgo en español pero el 1º en portugués («cerdo» no es
  portugués), así que quedaba dos veces. Con agregación `sum` el puntaje
  salía 5.78 en vez de 4.0; con `max` (por defecto) el puntaje era correcto
  pero el reporte contaba términos e idiomas de más. Ahora la clave es la
  n-ésima aparición del término, estable entre idiomas; las repeticiones
  reales («Idiota Idiota») siguen contando dos veces.
- **`PhoneticFolder` desapareció en 4.3.0 sin aviso.** Se renombró a
  `SpanishPhoneticFolder` (coherente con los otros 16 folders), pero el
  cambio no figuraba aquí y rompía a quien usara el nombre viejo. Se
  restaura `PhoneticFolder` como alias deprecado de `SpanishPhoneticFolder`.
- Conteos desactualizados en la documentación: `WordListScanner` decía 13
  idiomas sin reglas fonéticas (son 16); `PhoneticFolderRegistry` no
  listaba islandés, suajili ni tagalo entre los excluidos y llamaba «chino»
  al cantonés; el README decía que el plegado cubre «los 17 idiomas en
  script latino» cuando son 17 de 22.
- **Un término corto hallado en OTRA palabra podía apagar uno más grave
  aquí**, en `WordListScanner::scan()`. El paso de palabra-pegada comparaba
  texto contra todos los hallazgos del mensaje entero («¿aparece este
  substring en algún sitio?»), no posición: en «esa rata, ne-grata», «rata»
  (animal/medium) hallado en otra palabra apagaba a «negrata» (etnico/high)
  sólo porque su texto es substring de «ne-grata» ya pegada. Ahora compara
  rango de posiciones en el texto original, no texto libre.
- **`AccentFolding` colapsaba vocales vietnamitas que no son acentos, sino
  letras distintas del alfabeto** (`ă`, `â`, `ê`, `ô` son fonemas propios en
  vietnamita, no variantes de `a`/`e`/`o`). Colisionaba «hói» (calvo) con
  «hôi» (que huele mal) en la misma clave del índice. El plegado ahora
  excluye esas cuatro letras sólo para `vie`.
- `DictionaryIntegrityTest::testNoLanguageHasTheSameWordInTwoCategories`
  comparaba con `strtolower(trim())` en vez de `WordList::normalize()`, más
  débil que la clave real del índice: dejaba pasar duplicados por plegado de
  acentos. Ya destapó uno real: `scheiße`/`scheisse` en alemán (`ß` pliega a
  `ss`), que se retiró del diccionario.
- `RiskReportBuilder` mantenía su propia copia de las descripciones de cada
  `riskType`, ya desincronizada en las 11 categorías de
  `config/risk-categories.php` pese a que el README decía que las
  definiciones "viven" ahí. Ahora las lee de ese archivo, única fuente real.
- Tabla de rangos de términos del README desactualizada tras las últimas
  ampliaciones: decía `comprehensive: 200–418` (real: 282–601, español tiene
  601) y `moderate: 120–160` (real: 120–245, varios idiomas la superan).
- **Una planilla de `review/` ya no puede escribir en `config/` nada que no
  sea un dato**: `bin/apply-review.php` pasaba al archivo, sin validar, la lista
  de una fila `excepción` (`categoria`) y la etiqueta de una fila `forma`, y
  después leía el resultado con `require`, también en la vista previa. Ahora
  `categoria` sólo admite `everyday` y `legit`; la etiqueta, sólo rasgos como
  `pl` o `f.pl`; `ConfigEditor::editList` y `FormEditor::setBy` rechazan lo que
  no sea un nombre o una severidad conocida; y el resultado se comprueba con el
  tokenizador (`Review\ConfigData`, una lista de lo permitido que cumplen los 69
  archivos de `config/`) antes de evaluarlo. Las palabras ya se escapaban.

### Cambiado

- **`nameCollision` en 30 apellidos y nombres frecuentes**, elegidos con datos
  de frecuencia y no a ojo: los apellidos ingleses con 1.000 personas o más en
  el censo de EE. UU. de 2010 (Outlaw, Coward, Dyke, Leech…) y los españoles
  entre los 8.000 más frecuentes (Chaparro, Payo, Cansino, Rufián, Bastardo…),
  más Mona e India. Se siguen detectando, pero ya no se rechazan solos ni
  bloquean un saludo en el chat. Por debajo de esos umbrales no se marcan: cada
  marca baja también el insulto a revisión. Ver `FrequentSurnameCollisionTest`.
  Los ejemplos y tests que usaban «Luis Bastardo» como insulto grave que se
  rechaza pasan a «Luis Gilipollas».
- Se restauró `_Garbage/`, borrado en 4.3.0: la decisión es conservar como
  referencia lo obsoleto en vez de borrarlo. Su README explica por qué
  ninguno de esos archivos debe reconectarse al motor.

## [4.3.2] - 2026-09-27

### Arreglado

- **`riskType => 'fonetico'` era inalcanzable.** Estaba declarado en
  `config/risk-categories.php` (con nombre, descripción y ejemplos) y
  descrito de nuevo en `RiskReportBuilder::DESCRIPTIONS`, pero
  `PhoneticFusionDetector::detectFusion()` heredaba el `riskType` del
  término del diccionario que cruzaba la unión (`genero`, `ordinario`,
  etc.), así que la categoría "Fonético" nunca salía en un reporte real.
  Ahora una fusión detectada sí reporta `riskType => 'fonetico'`; el tema
  original del término matcheado sigue disponible en `category`, sin
  cambios. Efecto secundario: `getExplanations()` ahora dice "tipo
  fonetico" en vez del tema original (p. ej. "tipo ordinario") para estos
  casos — es coherente con el `riskType` real, pero menos específico
  sobre qué clase de insulto formó la fusión.
- 358 tests, sin regresiones. No afecta el puntaje ni la severidad: esos
  siguen viniendo del campo `severity` del término matcheado, no de
  `riskType`.

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
