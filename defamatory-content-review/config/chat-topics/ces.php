<?php

/** Czech chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ces', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografie', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orální sex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbace', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbovat', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasmus', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagína', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'prsa', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nahý', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nahé fotky', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'znásilnění', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'znásilnit', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'šukat', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'válka', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardování', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'raketa', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'genocida', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'masakr', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'vyvraždit', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terorista', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'atentát', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invaze', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'armáda', 'riskType' => 'belico', 'severity' => 'low'],
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
