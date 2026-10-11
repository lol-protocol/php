<?php

/** Tagalog chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'tgl', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porn', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornograpiya', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbasyon', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasm', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'hubad', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'hubad na litrato', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'panggagahasa', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'manggahasa', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'gagahasain kita', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'digmaan', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'pambobomba', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'misayl', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'patayan', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'pagpatay', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'terorista', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'pagsalakay', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'hukbo', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'pagbitay', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'pagpugot', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'banal na digmaan', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'papatayin kita', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
