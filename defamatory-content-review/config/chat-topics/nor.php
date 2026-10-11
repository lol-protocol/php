<?php

/** Norwegian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'nor', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['pornoen', 'pornofilm']],
            ['word' => 'pornografi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'avsugning', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'onanere', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasme', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'pupper', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'naken', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nakenbilder', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'oralsex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'voldtekt', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'voldta', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'knulle', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'krig', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombing', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'rakett', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'folkemord', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massakre', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'utrydde', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorist', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'terrorangrep', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasjon', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'hær', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'henrette', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'halshogge', 'riskType' => 'belico', 'severity' => 'high'],
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
