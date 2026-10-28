<?php

/** Slovak chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'slk', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['ty', 'si', 'ste', 'vy', 'ten', 'ta', 'debilný', 'debilná', 'zasraný', 'zasraná', 'starý', 'stará', 'hnusný', 'hnusná'],
        'after' => ['ty', 'jeden', 'jedna', 'si'],
    ],
    'everyday' => ['moc', 'nemá'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'orálny sex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbácia', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masturbovať', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgazmus', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'vagína', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'prsia', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nahý', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nahé fotky', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'znásilnenie', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'znásilniť', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'šukať', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'vojna', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardovanie', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'raketa', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocída', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masaker', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'vyvraždiť', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terorista', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'atentát', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'invázia', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'armáda', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'popraviť', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'etnická čistka', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'svätá vojna', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\b(?:zabijem|zastrelim|podrezem|uskrtim|zmlatim)\s+ta\b|\bta\s+(?:zabijem|zastrelim|podrezem)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'hrozba'],
        ['pattern' => '\bznasilnim\s+ta\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'sexualna hrozba'],
    ],
];
