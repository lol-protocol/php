<?php

/** Korean chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'kor', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => '포르노', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '야동', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '자위', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '오럴', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '섹스', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => '강간', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '성폭행', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '알몸', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => '누드', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => '누드사진', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => '오르가즘', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => '음경', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
        'belico' => [
            ['word' => '전쟁', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => '폭격', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '미사일', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '학살', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '대량학살', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '인종청소', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '테러', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '테러리스트', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '침공', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => '군대', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => '처형', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '참수', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '성전', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '죽일거야', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '죽인다', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '죽여버린다', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => '죽여버릴거야', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => '폭탄', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => '가슴', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => '질', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
