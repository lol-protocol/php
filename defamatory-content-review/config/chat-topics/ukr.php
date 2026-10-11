<?php

/** Ukrainian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ukr', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'порно', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'порнографія', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'мінет', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'мастурбація', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'мастурбувати', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'оргазм', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'пеніс', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'вагіна', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'цицьки', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'голий', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'нюдси', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'секс', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'оральний секс', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'зґвалтування', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'зґвалтувати', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ґвалтувати', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'війна', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'бомбардування', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ракета', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'геноцид', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'різанина', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'масове вбивство', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'терорист', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'теракт', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'вторгнення', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'армія', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'розстріл', 'riskType' => 'belico', 'severity' => 'high'],
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
