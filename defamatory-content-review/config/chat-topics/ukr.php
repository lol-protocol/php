<?php

/** Ukrainian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ukr', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['ти', 'ви', 'тебе', 'твій', 'твоя', 'цей', 'ця', 'такий', 'така', 'тупий', 'тупа', 'брудний', 'брудна', 'смердючий'],
        'after' => ['ти', 'ви', 'смердючий', 'смердюча'],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'порно', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'порнографія', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'мінет', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'мастурбація', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'мастурбувати', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'оргазм', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'пеніс', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'вагіна', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'цицьки', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'голий', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'нюдси', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'секс', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'оральний секс', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'зґвалтування', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'зґвалтувати', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ґвалтувати', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'війна', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'бомбардування', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'ракета', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'геноцид', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'різанина', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'масове вбивство', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'терорист', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'теракт', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'вторгнення', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'армія', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'розстріл', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'обезголовити', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'етнічна чистка', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'священна війна', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'бомба', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '(?<!\w)я\s+тебе\s+(?:вб\s?ю|заріжу|пристрелю|задушу)(?!\w)', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'погроза'],
        ['pattern' => '(?<!\w)я\s+тебе\s+зґвалтую(?!\w)', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'сексуальна погроза'],
    ],
];
