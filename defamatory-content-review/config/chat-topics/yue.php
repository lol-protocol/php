<?php

/** Cantonese chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'yue', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => '色情', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '鹹片', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '手淫', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '打飛機', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '口交', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '性交', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => '做愛', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => '裸照', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '裸體', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => '強姦', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '性侵', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => '戰爭', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => '轟炸', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '導彈', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '種族滅絕', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '屠殺', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '大屠殺', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '恐怖分子', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '恐襲', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '入侵', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => '軍隊', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => '處決', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '斬首', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '殺死你', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '殺你', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => '炸彈', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
