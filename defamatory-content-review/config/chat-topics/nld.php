<?php

/** Dutch chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'nld', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografie', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pijpen', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturberen', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasme', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'tieten', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'borsten', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'naakt', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'naaktfoto', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['naaktfotos']],
            ['word' => 'seks', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'orale seks', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'verkrachting', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'verkrachten', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'neuken', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'oorlog', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardement', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'raket', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'genocide', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'volkerenmoord', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'bloedbad', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'uitroeien', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorist', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'aanslag', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasie', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'leger', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'executeren', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'onthoofden', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'etnische zuivering', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'heilige oorlog', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bom', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bik\s+(?:ga|zal)\s+je\s+(?:vermoorden|doodmaken|afmaken|neersteken|neerschieten)\b|\bik\s+(?:maak|steek|schiet)\s+je\s+(?:dood|af|neer)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'dreiging'],
        ['pattern' => '\bik\s+(?:ga|zal)\s+je\s+verkrachten\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'seksuele dreiging'],
    ],
];
