<?php

/**
 * English chat topics — see spa.php for the format ('forms', 'also', the
 * 'ambiguous' category, 'patterns', meta 'collapseRepeats' and 'legit') and the
 * meaning of severity.
 */
return [
    'meta' => ['code' => 'eng', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'legit' => ['annus', 'bonny', 'curr', 'hogg', 'jaap', 'looser', 'pigg', 'pratt'],
    'words' => [
        'sexual' => [
            ['word' => 'porn', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pornography', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'fuck', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'fucker', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'motherfucker', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'blowjob', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pussy', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'cock', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'tits', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'boobs', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'naked', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'horny', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'masturbate', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'masturbation', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'dick pic', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'suck my dick', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'war', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'missile', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocide', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacre', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'exterminate', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terrorist', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'terrorism', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasion', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'ethnic cleansing', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'holy war', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
        ],
        'ambiguous' => [
            ['word' => 'bomb', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'verb'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\b(ll|will|gonna|going\s+to|ima|imma)\s+(kill|murder|shoot|stab|strangle|behead)\s+(you|u|ya|them|everyone)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'threat'],
        ['pattern' => '\b(ll|will|gonna|going\s+to|ima|imma)\s+rape\s+(you|u|her|them)\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'sexual threat'],
        ['pattern' => '\b(kill\s+(your\s*self|urself)|kys|go\s+die|hope\s+you\s+(die|burn|rot))\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'incitement to suicide'],
        ['pattern' => '\bkill\s+(them|em)\s+all\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'incitement to kill'],
        ['pattern' => '\b(plant|planted|planting|drop|dropped|dropping|throw|threw|detonate|detonated)\s+(a\s+|the\s+)?bombs?\b|\bbomb\s+threat\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'bomb'],
        ['pattern' => '\b(bomb|bombs|bombed|bombing)\s+(the|a|an|our|their|this|that)\s+(city|town|village|hospital|school|base|embassy|building|market|church|capital)\b', 'riskType' => 'belico', 'severity' => 'medium', 'label' => 'bombing'],
        ['pattern' => '\b(nuclear|atomic|atom)\s+bombs?\b', 'riskType' => 'belico', 'severity' => 'low', 'label' => 'atomic bomb'],
    ],
];
