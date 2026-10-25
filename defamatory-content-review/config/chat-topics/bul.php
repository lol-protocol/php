<?php

/** Bulgarian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'bul', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['ти', 'ти си', 'вие', 'тоя', 'тая', 'този', 'тази', 'такъв', 'такава', 'мръсен', 'мръсна', 'тъп', 'тъпа', 'дебел', 'дебела'],
        'after' => ['такъв', 'такава', 'ти', 'мръсен', 'мръсна'],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'порно', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'порнография', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'орален секс', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'мастурбация', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'мастурбирам', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'оргазъм', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'пенис', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'вагина', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'цици', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'голи снимки', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'секс', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'изнасилване', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'изнасиля', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'война', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'бомбардировка', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'ракета', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'геноцид', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'клане', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'изтребление', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'терорист', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'атентат', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'инвазия', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'армия', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'екзекуция', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'обезглавяване', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'етническо прочистване', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'свещена война', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'гол', 'riskType' => 'sexual', 'severity' => 'medium'], // también «gol» (fútbol)
            ['word' => 'бомба', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'чукам', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => 'ще\s+те\s+(?:убия|заколя|пребия|застрелям|удуша)', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'заплаха'],
        ['pattern' => 'ще\s+те\s+изнасиля', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'сексуална заплаха'],
    ],
];
