<?php

/** Icelandic chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'isl', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'klám', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'klámmynd', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sjálfsfróun', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'fullnæging', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'typpi', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'píka', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'brjóst', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nakin', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nektarmyndir', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'kynlíf', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'munnmök', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nauðgun', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nauðga', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'stríð', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'eldflaug', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'þjóðarmorð', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'fjöldamorð', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'útrýma', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'hryðjuverkamaður', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'innrás', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'her', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'hálshöggva', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'aftaka', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'heilagt stríð', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'sprengja', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
