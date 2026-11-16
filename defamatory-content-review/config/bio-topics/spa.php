<?php

/**
 * Temas de BIOGRAFÍA en español: lo que alguien dice de SÍ MISMO («tengo cáncer», «soy evangélica»,
 * «soy gay»). Es opcional y NO forma parte del modo normal: `config/chat-topics/` está escrito para frases
 * sobre otra persona («Fulano tiene cáncer») y no las marca, a propósito, porque en un chat o en una nota
 * contar algo de uno mismo no es difamar a nadie. Este modo sirve para quien revisa un texto que la persona
 * escribió sobre sí misma (la biografía de un perfil) y quiere saber si cuenta un dato sensible (Ley 21.719)
 * antes de guardarlo o publicarlo.
 *
 * Mismo formato que los `patterns` de `chat-topics/spa.php`: expresiones regulares sin delimitadores,
 * escritas contra el texto plegado (minúsculas, sin tildes, puntuación como espacios). Nunca bloquean:
 * severidad `medium`, van a revisión. Se piden con `ChatLineReviewer::create($config, 'spa', 'bio-topics')`
 * o `TextMarks::of($texto, $config, ['spa'], 'bio-topics')`.
 *
 * Son listas CORTAS: que no marquen nada no quiere decir que no haya un dato sensible.
 */
return [
    'meta' => ['code' => 'spa', 'kind' => 'bio-topics', 'collapseRepeats' => false],
    'patterns' => [
        ['pattern' => '\b(tengo|tenemos|padezco|padecemos|sufro\s+de|sufrimos\s+de|vivo\s+con|vivimos\s+con|me\s+diagnosticaron|me\s+detectaron|supere|sobrevivi\s+(a|al))\s+(el\s+|la\s+|un\s+|una\s+)?(sida|vih|cancer|diabetes|depresion|ansiedad|epilepsia|esquizofrenia|autismo|alzheimer|parkinson|esclerosis|bipolaridad|anorexia|bulimia|tdah|asperger|fibromialgia|lupus|hepatitis|tuberculosis)\b|\bsoy\s+(bipolar|diabetic[oa]|epileptic[oa]|autista|esquizofrenic[oa]|sidos[oa]|seropositiv[oa])\b|\b(sobreviviente|paciente)\s+de\s+(cancer|sida|vih)\b', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'dato sensible (salud)'],
        ['pattern' => '\bsoy\s+(un\s+|una\s+)?(catolic[oa]|evangelic[oa]|musulman(a|es|as)?|judi[oa]|mormon(a|es|as)?|testigo\s+de\s+jehova|ate[oa]|budista|protestante|cristian[oa])\b|\b(catolic[oa]|evangelic[oa]|musulman[a]?|judi[oa]|cristian[oa])\s+practicante\b|\bmi\s+fe\s+(catolica|evangelica|musulmana|judia|cristiana)\b', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'dato sensible (religión)'],
        ['pattern' => '\bsoy\s+(un\s+|una\s+)?(comunista|socialista|derechista|izquierdista|anarquista|fascista|pinochetista|allendista|militante)\b|\bmilitante\s+(del|de\s+la|de\s+los)\s+\w+', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'dato sensible (opinión política)'],
        ['pattern' => '\bsoy\s+(un\s+|una\s+)?(mapuche|aymara|indigena|gitan[oa]|mestiz[oa]|afrodescendiente)\b|\b(soy\s+)?de\s+origen\s+(arabe|judio|gitano|mapuche|indigena|africano|aymara|afrodescendiente)\b', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'dato sensible (origen étnico)'],
        ['pattern' => '\bsoy\s+(un\s+|una\s+)?(gay|lesbiana|bisexual|homosexual|transexual|transgenero|pansexual|asexual)\b|\bsoy\s+(una\s+)?persona\s+trans\b', 'riskType' => 'privado', 'severity' => 'medium', 'label' => 'dato sensible (orientación sexual)'],
    ],
];
