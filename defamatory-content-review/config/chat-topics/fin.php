<?php

/** Finnish chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'fin', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'runkata', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturboida', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasmi', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'emätin', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'rinnat', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'alasti', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'alastonkuvat', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'seksi', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'suuseksi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'raiskaus', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'raiskata', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'naida', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'sota', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'pommitus', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ohjus', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'kansanmurha', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'joukkomurha', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'verilöyly', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terroristi', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'terrori-isku', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'armeija', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'teloittaa', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'mestata', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'etninen puhdistus', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'pyhä sota', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'pommi', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bmina\s+(?:tapan|murhaan|puukotan|ammun)\s+(?:sinut|sut)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'uhkaus'],
        ['pattern' => '\bmina\s+raiskaan\s+(?:sinut|sut)\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'seksuaalinen uhkaus'],
    ],
];
