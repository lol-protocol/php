<?php

/**
 * Temas que se censuran en el chat además de los insultos del diccionario del
 * idioma: `sexual` y `belico` (guerra, violencia y amenazas). Mismo formato
 * que config/languages/*.php, más:
 *
 * - 'forms' => 'noun' | 'adj' | 'verb': genera los plurales, géneros o la
 *   conjugación regular de la palabra (ver TopicInflector). Escribe el
 *   sustantivo en singular, el adjetivo en masculino y el verbo en infinitivo.
 * - 'also' => [...]: formas irregulares, a mano.
 * - categoría 'ambiguous': palabras con otro uso cotidiano («vamos a coger el
 *   bus», «está de bomba»). Sólo cuentan si la misma línea trae algo firme
 *   del mismo riskType y de severidad medium o high (ver ChatTopics).
 * - 'patterns': frases con forma, como expresión regular sin delimitadores
 *   contra el texto plegado: minúsculas, sin tildes y sin puntuación.
 * - meta 'collapseRepeats' => true: lee también la línea sin letras repetidas
 *   («puuuuta»). Sólo se activa en los idiomas cuyos falsos positivos se midieron
 *   contra una lista de palabras reales: en otros (italiano, finés…) la doble
 *   letra es parte de la palabra.
 * - 'legit' => [...]: palabras con letra doble legítima (y apellidos) cuya lectura
 *   sin repetidas coincide con un insulto: «calle» → «calé». Nunca se leen colapsadas.
 * - 'everyday' => [...]: palabras cotidianas que, escritas exactamente así (da igual
 *   la mayúscula), no se buscan en el chat porque sin tildes coinciden con un término:
 *   «moño» → «mono», «calló» → «callo». Ver EverydayWords.
 *   ChatTopicsExemptionsTest verifica que cada palabra de legit y everyday siga haciendo falta.
 *
 * Severidad: high => bloquear, medium => revisión humana, low => sólo se informa.
 */
return [
    'meta' => ['code' => 'spa', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['eres', 'sos', 'vos', 'usted', 'ustedes', 'sois', 'pareces', 'es un', 'es una', 'son unos', 'son unas', 'ese', 'esa', 'esos', 'esas', 'maldito', 'maldita', 'malditos', 'malditas', 'pinche', 'pinches', 'puto', 'puta', 'pedazo de', 'cara de', 'estúpido', 'estúpida', 'grandísimo', 'grandísima', 'so', 'hijo de', 'hija de'],
        'after' => ['eres', 'sos', 'de mierda', 'asqueroso', 'asquerosa', 'inmundo', 'inmunda', 'de los cojones', 'culiao', 'culiado'],
    ],
    'everyday' => ['mudó', 'moño', 'calló'],
    'legit' => ['calle', 'morro', 'chollo', 'cholla', 'gorrilla', 'mulla', 'mullo', 'pellon'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'follar', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'chingar', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'mamada', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masturbar', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'orgasmo', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'pene', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'verga', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'polla', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'teta', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'desnudo', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'adj'],
            ['word' => 'sexo', 'riskType' => 'sexual', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'foto intima', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['fotos intimas']],
            ['word' => 'sexo oral', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'guerra', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'bombardear', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'bombardeo', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'misil', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocidio', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masacre', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'exterminar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terrorista', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'atentado', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'adj'],
            ['word' => 'invasion', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'ejercito', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'fusilar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'degollar', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb',
                'also' => ['degüello', 'degüellas', 'degüella', 'degüellan', 'degüelle', 'degüelles', 'degüellen', 'degüellalo', 'degüellalos']],
            ['word' => 'limpieza etnica', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'guerra santa', 'riskType' => 'belico', 'severity' => 'high', 'also' => ['guerras santas']],
        ],
        'ambiguous' => [
            ['word' => 'coger', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'verb'],
            ['word' => 'paja', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'concha', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
        ],
    ],
    'patterns' => [
        // Acusaciones contra una persona (difamación por imputar hechos, no por insultar):
        // «X estafó a mi familia», «fue condenado por…», «tiene sida», «le pone los cuernos».
        // Pueden ser ciertas (un registro judicial, una biografía): nunca bloquean, van a
        // revisión humana para verificar fuente y si la persona vive. Ver docs/RIESGOS.md.
        ['pattern' => '(?:^|\b(?!(?:el|un|la|una|los|del|al)\b)\w+\s+)(robo|robaba|robaron|roba|estafo|estafa|estafaba|estafaron|defraudo|defrauda|defraudaba|asesino|mato|violo|abuso|abusa|abusaba|abusaron|golpea|golpeaba|golpeo|maltrata|maltrataba|maltrato|secuestro|extorsiona|extorsiono|soborno|coimeo|malverso)\s+(a|al|la|los|las|de|del|mi|mis|tu|tus|su|sus|plata|dinero|ninos|ninas|menores|fondos|gente|clientes)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'acusación de delito'],
        ['pattern' => '\b(fue|ha\s+sido|esta|estuvo|estaba|quedo|salio|anda)\s+(condenad[oa]s?|pres[oa]s?|detenid[oa]s?|procesad[oa]s?|formalizad[oa]s?|imputad[oa]s?|encarcelad[oa]s?|arrestad[oa]s?)\b|\b(lo|la|los|las)\s+(condenaron|detuvieron|arrestaron|formalizaron|procesaron|metieron\s+pres[oa]s?)\b|\b(esta|estuvo|estaba|salio|paso)\s+(en|de|por)\s+(la\s+)?(carcel|cana|prision|penitenciaria)\b|\btiene\s+(antecedentes\s+(penales|policiales|criminales)|prontuario|causas\s+pendientes)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'acusación de delito'],
        ['pattern' => '\b(es|era|son|eran|fue)\s+((un|una|unos|unas|el|la|los|las|todos|todas|puros|puras)\s+)?(ladrones|estafadores|asesinos|violadores|abusadores|corruptos|criminales|rateros|coimeros|pedofilos|defraudadores|narco|narcos|narcotraficante|narcotraficantes|traficante|traficantes|proxeneta|cafiche|sicario|coimero|coimera|ratero|ratera|abusador|abusadora|violador|violadora|pedofilo|pedofila|estafador|estafadora|defraudador|defraudadora|delincuente|delincuentes|criminal|corrupto|corrupta|asesino|asesina|ladron|ladrona|reo)\b|\b(familia|gente)\s+(es\s+)?de\s+(delincuentes|ladrones|estafadores|narcos|asesinos|criminales)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'acusación de delito'],
        ['pattern' => '\b(tiene|tenia|tuvo|le\s+dio|contagio|contagiad[oa]\s+de|enferm[oa]\s+de|portador[a]?\s+de)\s+(el\s+)?(sida|vih|sifilis|gonorrea|herpes|clamidia|hepatitis|esquizofrenia|tuberculosis|cancer|depresion|alzheimer|demencia|parkinson|epilepsia|anorexia|bulimia|cirrosis)\b|\bes\s+(esquizofrenic[oa]|bipolar|sidos[oa]|epileptic[oa]|anorexic[oa])\b|\b(se\s+hizo|tuvo|le\s+hicieron)\s+un\s+aborto\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'dato sensible (salud)'],
        ['pattern' => '\b(le\s+)?(pone|ponia|puso|pusieron)\s+(el\s+gorro|los\s+cuernos)\b|\b(le\s+)?(es|fue|era)\s+infiel\b|\b(engana|enganaba|engano)\s+a\s+su\s+(esposa|esposo|mujer|marido|senora|pareja|novia|novio)\b|\btiene\s+(una|un)\s+amante\b|\bse\s+(acuesta|acostaba|acosto|mete|metia)\s+con\b|\btiene\s+otra\s+familia\b|\banda\s+con\s+(la\s+mujer|el\s+marido|la\s+esposa|el\s+esposo|la\s+senora|la\s+polola|el\s+pololo)\s+de\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'vida privada'],
        ['pattern' => '\b(esta|estuvo|aparece|sale)\s+en\s+(dicom|el\s+boletin\s+comercial)\b|\bno\s+paga\s+(sus\s+|la\s+)?(deudas|cuentas|pension)\b|\bes\s+(un\s+|una\s+)?moros[oa]\b|\bdeudor[a]?\s+de\s+pension\b|\bdebe\s+(la\s+)?pension\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'vida privada (deudas)'],
        // Datos sensibles y vida privada de un tercero, aunque sean ciertos (Ley 19.628): orientación
        // sexual, filiación, suicidio, violencia en la familia, internación psiquiátrica.
        ['pattern' => '\b(es|era|son|eran|fue)\s+(gay|gays|lesbiana|lesbianas|homosexual|homosexuales|bisexual|bisexuales|transexual|transexuales|travesti)\b|\b(salio|sale|saco)\s+del\s+(closet|armario)\b|\bes\s+de\s+closet\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'dato sensible (orientación sexual)'],
        ['pattern' => '\b(es|era|fue)\s+(un\s+|una\s+)?(hij[oa]\s+(ilegitim[oa]|natural|bastard[oa]|no\s+reconocid[oa]|fuera\s+del\s+matrimonio)|adoptad[oa])\b|\bno\s+(es|era)\s+(hij[oa]|el\s+padre|la\s+madre)\s+de\b|\bsu\s+verdader[oa]\s+(padre|madre)\b|\btuvo\s+(un|una)\s+hij[oa]\s+(con\s+otr[oa]|fuera\s+del\s+matrimonio)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'vida privada (filiación)'],
        ['pattern' => '\bse\s+(suicido|quito\s+la\s+vida|ahorco|colgo)\b|\bintento\s+suicidarse\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'dato sensible (suicidio)'],
        ['pattern' => '\b(pega|pegaba|pego|golpea|golpeaba|golpeo|maltrata|maltrataba)\s+a\s+(su|sus)\s+(esposa|esposo|mujer|marido|senora|pareja|polola|pololo|novia|novio|hijos|hijas|hijo|hija|mama|madre|papa|padre)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'acusación de delito'],
        ['pattern' => '\b(esta|estuvo|estaba)\s+(internad[oa]\s+en\s+(un|el)\s+(psiquiatrico|manicomio)|en\s+(el|un)\s+(psiquiatrico|manicomio))\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'dato sensible (salud)'],
        ['pattern' => '\b(funa|funar|funemos|funen|funenlo|funenla|escrache|escrachar|escrachemos|no\s+confien\s+en|no\s+le\s+crean\s+a)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'funa o escrache'],
        // Amenazas dirigidas a «te»: siempre bloquean.
        ['pattern' => '\bte\s+(voy|vamos|van|vas)\s+a\s+(matar|asesinar|degollar|reventar|destripar|apunalar)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'amenaza'],
        ['pattern' => '\bte\s+(mato|matare|matamos|asesino|asesinare|reviento|reventare|destripo)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'amenaza'],
        ['pattern' => '\b(matarte|asesinarte|degollarte|reventarte|destriparte)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'amenaza'],
        ['pattern' => '\b(te\s+(voy|vamos|van|vas)\s+a\s+violar|te\s+violo|te\s+violare|violarte)\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'amenaza sexual'],
        ['pattern' => '\bojala\s+(te\s+|se\s+)?(mueras|mueran|revientes|revienten)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'deseo de muerte'],
        ['pattern' => '\b(muerete|matate|suicidate)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'incitación al suicidio'],
        // Incitar a matar a un grupo: con «a todos» bloquea; sin objeto claro («mátalos») va a revisión.
        ['pattern' => '\b(hay\s+que\s+(matar|asesinar|exterminar|fusilar|degollar)|(matalos|matalas|matenlos|matenlas|matarlos|matarlas|asesinarlos|fusilarlos|exterminarlos|degollarlos))\s+a\s+(todos|todas|esos|esas|esa\s+gente)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'incitación a matar'],
        ['pattern' => '\b(matalos|matalas|matenlos|matenlas|matarlos|matarlas|asesinarlos|asesinarlas|fusilarlos|fusilarlas|exterminarlos|exterminarlas|degollarlos|degollarlas)\b(?!\s+a\s+(todos|todas|esos|esas|esa\s+gente)\b)', 'riskType' => 'belico', 'severity' => 'medium', 'label' => 'incitación a matar'],
        // Bombas: colocarlas o lanzarlas bloquea; nombrarlas con adjetivo va a revisión; la bomba atómica es historia.
        ['pattern' => '\b(poner|pongo|puse|pusieron|colocar|coloque|colocaron|tirar|tiraron|lanzar|lanzaron|detonar|detonaron|plantar|plantaron)\s+(una\s+|la\s+|unas\s+|las\s+)?bombas?\b|\bhay\s+una\s+bomba\b|\bamenaza\s+de\s+bomba\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'bomba'],
        ['pattern' => '\bbombas?\s+(molotov|caseras?|de\s+racimo)\b', 'riskType' => 'belico', 'severity' => 'medium', 'label' => 'bomba'],
        ['pattern' => '\bbombas?\s+(atomicas?|nucleares|nuclear)\b', 'riskType' => 'belico', 'severity' => 'low', 'label' => 'bomba atómica'],
    ],
];
