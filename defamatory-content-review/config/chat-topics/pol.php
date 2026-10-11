<?php

/** Polish chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'pol', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbacja', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbować', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgazm', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'cycki', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nagi', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudesy', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nagie zdjęcia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'seks', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'seks oralny', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'gwałt', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'gwałcić', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'wojna', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardowanie', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'rakieta', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ludobójstwo', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'masakra', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'rzeź', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'eksterminacja', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorysta', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'zamach', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'inwazja', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'armia', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'rozstrzelać', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'czystki etniczne', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'święta wojna', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'pochwa', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\b(?:zabije|zamorduje|zadzgam|zastrzele|udusze|zarzne)\s+(?:cie|ciebie)\b|\bcie\s+(?:zabije|zamorduje|zadzgam|zastrzele|udusze|zarzne)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'grozba'],
        ['pattern' => '\bzgwalce\s+(?:cie|ciebie)\b|\bcie\s+zgwalce\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'grozba seksualna'],
    ],
];
