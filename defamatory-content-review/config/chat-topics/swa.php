<?php

/** Swahili chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'swa', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'ponografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'picha za ngono', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ngono', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'ubakaji', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'kubaka', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'punyeto', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'uchi', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'picha za uchi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'kujamiiana', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nitakubaka', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'vita', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'mabomu', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'kombora', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'mauaji ya halaiki', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'mauaji', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ugaidi', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'gaidi', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'uvamizi', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'jeshi', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'kunyongwa', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'kukata kichwa', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'utakaso wa kikabila', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'jihadi', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'nitakuua', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'nitakuchinja', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'nakuua', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomu', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
