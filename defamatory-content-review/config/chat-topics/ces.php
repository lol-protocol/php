<?php

/** Czech chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ces', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'legit' => ['sleepy'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['ty', 'jsi', 'jste', 'vy', 'ten', 'ta', 'ty jsi', 'debilní', 'zasranej', 'zasraná', 'hnusnej', 'hnusná', 'starej', 'stará'],
        'after' => ['ty', 'jeden', 'jedna', 'jsi'],
    ],
    'everyday' => ['moc', 'nemá', 'pero'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografie', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'orální sex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbace', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masturbovat', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasmus', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'vagína', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'prsa', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nahý', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nahé fotky', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'znásilnění', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'znásilnit', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'šukat', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'válka', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardování', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'raketa', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'genocida', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masakr', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'vyvraždit', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terorista', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'atentát', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'invaze', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'armáda', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'popravit', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'etnická čistka', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'svatá válka', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\b(?:zabiju|zabijim|zastrelim|podriznu|uskrtim|zmlatim)\s+te\b|\bte\s+(?:zabiju|zastrelim|podriznu)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'hrozba'],
        ['pattern' => '\bznasilnim\s+te\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'sexualni hrozba'],
    ],
];
