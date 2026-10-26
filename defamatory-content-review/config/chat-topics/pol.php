<?php

/** Polish chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'pol', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'legit' => ['sleepy'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['ty', 'jesteś', 'jesteście', 'wy', 'ten', 'ta', 'ci', 'głupi', 'głupia', 'brudny', 'brudna', 'stary', 'stara', 'jebany', 'jebana', 'pierdolony', 'pierdolona', 'zasrany', 'zasrana'],
        'after' => ['ty', 'jeden', 'jedna', 'jebany', 'jebana', 'pierdolony', 'pierdolona'],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masturbacja', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masturbować', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgazm', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'cycki', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nagi', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudesy', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nagie zdjęcia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'seks', 'riskType' => 'sexual', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'seks oralny', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'gwałt', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'gwałcić', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'wojna', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardowanie', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'rakieta', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'ludobójstwo', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'masakra', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'rzeź', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'eksterminacja', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'terrorysta', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'zamach', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'inwazja', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'armia', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
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
