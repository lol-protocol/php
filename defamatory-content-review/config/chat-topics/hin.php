<?php

/** Hindi chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'hin', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'पोर्न', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'अश्लील', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'हस्तमैथुन', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ओरल सेक्स', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'संभोग', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'योनि', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'स्तन', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'नंगा', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'नंगी तस्वीरें', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'सेक्स', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'बलात्कार', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'बलात्कार करूंगा', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'युद्ध', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'बमबारी', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'मिसाइल', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'नरसंहार', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'कत्लेआम', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'आतंकवादी', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'आतंकी हमला', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'आक्रमण', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'सेना', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'सिर कलम करना', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'जातीय सफाया', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'धर्मयुद्ध', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'जिहाद', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'मार डालूंगा', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'तुझे मार डालूंगा', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'बम', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'लिंग', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
