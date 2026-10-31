<?php

/** Finnish chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'fin', 'kind' => 'chat-topics'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['sinä', 'sä', 'sää', 'olet', 'oot', 'te', 'ootte', 'senkin', 'sitä', 'saatanan', 'vitun', 'perkeleen', 'likainen', 'lihava', 'tyhmä', 'ruma'],
        'after' => ['olet', 'oot'],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'runkata', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturboida', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasmi', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'emätin', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'rinnat', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'alasti', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'alastonkuvat', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'seksi', 'riskType' => 'sexual', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'suuseksi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'raiskaus', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'raiskata', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'naida', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'sota', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'pommitus', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'ohjus', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'kansanmurha', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'joukkomurha', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'verilöyly', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'terroristi', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'terrori-isku', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'armeija', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'teloittaa', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'mestata', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'etninen puhdistus', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'pyhä sota', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'pommi', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bmina\s+(?:tapan|murhaan|puukotan|ammun)\s+(?:sinut|sut)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'uhkaus'],
        ['pattern' => '\bmina\s+raiskaan\s+(?:sinut|sut)\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'seksuaalinen uhkaus'],
    ],
];
