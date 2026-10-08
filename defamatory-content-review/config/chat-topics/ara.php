<?php

/** Arabic chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ara', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'إباحي', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'بورنو', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'استمناء', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'هزة الجماع', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'قضيب', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'مهبل', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'ثدي', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'عاري', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'صور عارية', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'جنس', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'جنس فموي', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'اغتصاب', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'يغتصب', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'سأغتصبك', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'حرب', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'قصف', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'صاروخ', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'إبادة جماعية', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'مجزرة', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'إرهابي', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'هجوم إرهابي', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'غزو', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'جيش', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'إعدام', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'قطع الرأس', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'تطهير عرقي', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'حرب مقدسة', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'سأقتلك', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'سوف أقتلك', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'سأذبحك', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'اقتلك', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'قنبلة', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
