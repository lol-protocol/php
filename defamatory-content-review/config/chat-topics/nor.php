<?php

/** Norwegian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'nor', 'kind' => 'chat-topics'],
    'everyday' => ['tyve', 'krype'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['du', 'du er', 'din', 'ditt', 'dine', 'dere', 'jævla', 'dumme', 'feite', 'ekle', 'satans'],
        'after' => [],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['pornoen', 'pornofilm']],
            ['word' => 'pornografi', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'avsugning', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'onanere', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'orgasme', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'pupper', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'naken', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'adj'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nakenbilder', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'oralsex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'voldtekt', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'voldta', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'knulle', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
        ],
        'belico' => [
            ['word' => 'krig', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombing', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'rakett', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'folkemord', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massakre', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'utrydde', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terrorist', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'terrorangrep', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'invasjon', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'hær', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'henrette', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'halshogge', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'etnisk rensing', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'hellig krig', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bombe', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bjeg\s+(?:skal\s+|vil\s+)?(?:drepe|myrde|stikke|skyte)\s+deg\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'trussel'],
        ['pattern' => '\bjeg\s+(?:skal\s+|vil\s+)?voldta\s+deg\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'seksuell trussel'],
    ],
];
