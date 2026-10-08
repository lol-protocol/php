<?php

/** Bulgarian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'bul', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'порно', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'порнография', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'орален секс', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'мастурбация', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'мастурбирам', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'оргазъм', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'пенис', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'вагина', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'цици', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'гол', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'голи снимки', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'секс', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'изнасилване', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'изнасиля', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'война', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'бомбардировка', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ракета', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'геноцид', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'клане', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'изтребление', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'терорист', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'атентат', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'инвазия', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'армия', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'екзекуция', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'обезглавяване', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'етническо прочистване', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'свещена война', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'бомба', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'чукам', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => 'ще\s+те\s+(?:убия|заколя|пребия|застрелям|удуша)', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'заплаха'],
        ['pattern' => 'ще\s+те\s+изнасиля', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'сексуална заплаха'],
    ],
];
