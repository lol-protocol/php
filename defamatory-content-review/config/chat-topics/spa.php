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
 *   ChatTopicsConfigTest verifica que cada una siga haciendo falta.
 *
 * Severidad: high => bloquear, medium => revisión humana, low => sólo se informa.
 */
return [
    'meta' => ['code' => 'spa', 'kind' => 'chat-topics', 'collapseRepeats' => true],
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
