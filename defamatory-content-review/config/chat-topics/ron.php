<?php

/** Romanian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ron', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografie', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex oral', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbare', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasm', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagin', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'sani', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nud', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'poze intime', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'viol', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'razboi', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardament', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'racheta', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'genocid', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'masacru', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'extermina', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terorist', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'atentat', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invazie', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'armata', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'executa', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'decapita', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'curatenie etnica', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'razboi sfant', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'viola', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\b(?:o\s+sa\s+|am\s+sa\s+)?te\s+(?:omor|ucid|injunghii|impusc|spintec)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'amenintare'],
        ['pattern' => '\b(?:o\s+sa\s+|am\s+sa\s+)?te\s+violez\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'amenintare sexuala'],
    ],
];
