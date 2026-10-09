<?php

/** Italian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ita', 'kind' => 'chat-topics'],
    'everyday' => ['mostrò'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pompino', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbazione', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbarsi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasmo', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'pene', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'tette', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudo', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'foto intime', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sesso', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'sesso orale', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'stupro', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'stuprare', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'guerra', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardamento', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'missile', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'genocidio', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massacro', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massacrare', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'sterminare', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorista', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'attentato', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasione', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'esercito', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'fucilare', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'sgozzare', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'pulizia etnica', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'guerra santa', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'jihad', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'sega', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'scopare', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bti\s+(?:ammazzo|ammazzero|uccido|uccidero|sgozzo|sgozzero|massacro)\b|\bti\s+(?:voglio|vado\s+a|devo)\s+(?:ammazzare|uccidere|sgozzare)\b|\b(?:ammazzarti|ucciderti|sgozzarti)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'minaccia'],
        ['pattern' => '\bti\s+(?:stupro|stuprero)\b|\bstuprarti\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'minaccia sessuale'],
    ],
];
