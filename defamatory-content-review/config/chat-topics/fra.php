<?php

/** French chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'fra', 'kind' => 'chat-topics'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['t\'es', 'tu es', 'tu', 'vous êtes', 'espèce de', 'sale', 'sales', 'gros', 'grosse', 'c\'est un', 'c\'est une', 'ce', 'cette', 'pauvre', 'sombre', 'putain de', 'connard de', 'bande de'],
        'after' => ['de merde', 'va', 'toi'],
    ],
    'everyday' => ['drogue', 'rate', 'possède', 'demeure', 'violent', 'violons', 'viola'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pornographie', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'fellation', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'sodomie', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbation', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturber', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'orgasme', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagin', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'seins', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nichons', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'photo intime', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['photos intimes']],
            ['word' => 'sexe', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'sexe oral', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'viol', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'violer', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
        ],
        'belico' => [
            ['word' => 'guerre', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardement', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'missile', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocide', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacre', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacrer', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'exterminer', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terroriste', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'attentat', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'invasion', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'armee', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'fusiller', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'egorger', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'nettoyage ethnique', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'guerre sainte', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'djihad', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
        'ambiguous' => [
            ['word' => 'bombe', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'pipe', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'baiser', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'verb'],
            ['word' => 'bander', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'verb'],
        ],
    ],
    'patterns' => [
        // Accusations contre une personne et données sensibles (santé, vie privée, orientation sexuelle) :
        // elles peuvent être vraies ; jamais bloquées, elles vont en revue humaine. Voir docs/RIESGOS.md.
        ['pattern' => '\b(a|ont|avait|avaient)\s+(escroque|arnaque|viole|assassine|tue|detourne|extorque|soudoye|agresse|abuse\s+de)\b|\b(a|ont|avait)\s+vole\s+(de|du|des|l|la|le|les|son|sa|ses|mon|ma|mes|leur|leurs|ton|ta|tes)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusation de délit'],
        ['pattern' => '\b(a\s+ete|est|etait)\s+(condamnee?s?|arretee?s?|incarceree?s?|inculpee?s?|mise?s?\s+en\s+examen|en\s+prison)\b|\ba\s+(un\s+)?casier\s+judiciaire\b|\ba\s+fait\s+de\s+la\s+prison\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusation de délit'],
        ['pattern' => '\b(est|etait|sont|etaient|c\s+est)\s+((un|une|des|tous|toutes)\s+)?(voleur|voleuse|voleurs|escrocs?|arnaqueur|arnaqueuse|arnaqueurs|violeurs?|pedophiles?|assassins?|criminel|criminelle|criminels|dealers?|trafiquants?|corrompue?s?|delinquants?)\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'accusation de délit'],
        ['pattern' => '\b(a|avait|a\s+eu|a\s+attrape)\s+(le\s+|la\s+|un\s+|une\s+)?(sida|vih|syphilis|gonorrhee|herpes|hepatite|cancer|tuberculose|schizophrenie|depression)\b|\best\s+(seropositif|seropositive|schizophrene|bipolaire)\b|\ba\s+avorte\b|\bs\s+est\s+fait\s+avorter\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'donnée sensible (santé)'],
        ['pattern' => '\b(trompe|trompait|a\s+trompe)\s+(sa|son)\s+(femme|mari|epouse|copine|copain)\b|\ba\s+(une|un)\s+(maitresse|amant)\b|\best\s+infidele\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'vie privée'],
        ['pattern' => '\b(est|etait|sont)\s+(gay|gays|lesbienne|lesbiennes|homosexuel|homosexuelle|homosexuels|bisexuel|bisexuelle)\b|\ba\s+fait\s+son\s+coming\s+out\b', 'riskType' => 'difamatorio', 'severity' => 'medium', 'label' => 'donnée sensible (orientation sexuelle)'],
        ['pattern' => '\b(?:je|j)\s*(?:vais\s+)?te\s+(?:tuer|tue|buter|crever|massacrer|egorger)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'menace'],
        ['pattern' => '\b(?:je|j)\s*(?:vais\s+)?te\s+(?:violer|viole)\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'menace sexuelle'],
    ],
];
