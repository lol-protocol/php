<?php

/** Indonesian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ind', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'seks oral', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbasi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'onani', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasme', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'payudara', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'telanjang', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'foto telanjang', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'seks', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'pemerkosaan', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'memerkosa', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'perang', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'pengeboman', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'rudal', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'genosida', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'pembantaian', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'pembunuhan massal', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'teroris', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'serangan teroris', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasi', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'tentara', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'eksekusi', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'pemenggalan', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'pembersihan etnis', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'perang suci', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'jihad', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
        'ambiguous' => [
            ['word' => 'bom', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bgue?\s+(?:akan\s+|mau\s+|bakal\s+)?(?:bunuh|habisi|hajar|tusuk|tembak)\s+(?:lo|lu|kamu|kau|elu)\b|\b(?:aku|saya)\s+(?:akan\s+|mau\s+)?(?:bunuh|habisi|tusuk|tembak)\s+(?:kamu|kau|mu)\b|\bakan\s+kubunuh\b|\bkubunuh\s+kau\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'ancaman'],
        ['pattern' => '\b(?:aku|gue|gua|saya)\s+(?:akan\s+|mau\s+)?perkosa\s+(?:kamu|lo|lu|kau)\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'ancaman seksual'],
    ],
];
