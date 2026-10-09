<?php

/** Italian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ita', 'kind' => 'chat-topics'],
    'everyday' => ['mostrò', 'fucili'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pompino', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masturbazione', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbare', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'orgasmo', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'pene', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'tette', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudo', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'adj'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'foto intime', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sesso', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'sesso orale', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'stupro', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'stuprare', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
        ],
        'belico' => [
            ['word' => 'guerra', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardamento', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'missile', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocidio', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacro', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'massacrare', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'sterminare', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terrorista', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'attentato', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'invasione', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'esercito', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'fucilare', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'sgozzare', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'pulizia etnica', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'guerra santa', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'jihad', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'sega', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'scopare', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'verb'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bti\s+(?:ammazzo|ammazzero|uccido|uccidero|sgozzo|sgozzero|massacro)\b|\bti\s+(?:voglio|vado\s+a|devo)\s+(?:ammazzare|uccidere|sgozzare)\b|\b(?:ammazzarti|ucciderti|sgozzarti)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'minaccia'],
        ['pattern' => '\bti\s+(?:stupro|stuprero)\b|\bstuprarti\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'minaccia sessuale'],
    ],
];
