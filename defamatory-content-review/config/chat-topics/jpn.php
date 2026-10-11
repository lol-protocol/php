<?php

/** Japanese chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'jpn', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'ポルノ', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'エロ動画', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '裸', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'ヌード', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => '自慰', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'オナニー', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'フェラ', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'セックス', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => '強姦', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'レイプ', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '性交', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => '乱交', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ハメ撮り', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => '戦争', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => '爆撃', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ミサイル', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '虐殺', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '大量虐殺', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'ジェノサイド', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'テロ', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'テロリスト', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '侵略', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => '軍隊', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => '処刑', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '斬首', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '民族浄化', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '聖戦', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '殺すぞ', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'ぶっ殺す', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '殺してやる', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => '爆弾', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
