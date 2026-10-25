<?php

/** Russian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'rus', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'legit' => ['скотт', 'скотта', 'скоттом', 'скотту', 'харри'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['ты', 'вы', 'тебя', 'тебе', 'твой', 'твоя', 'сам', 'сама', 'этот', 'эта', 'эти', 'такой', 'такая', 'какой', 'какая', 'грязный', 'грязная', 'тупой', 'тупая', 'жирный', 'жирная', 'старый', 'старая', 'вонючий', 'вонючая'],
        'after' => ['ты', 'вы', 'поганый', 'поганая', 'вонючий', 'вонючая'],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'порно', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'порнография', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'минет', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'мастурбация', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'мастурбировать', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'оргазм', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'пенис', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'вагина', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'сиськи', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'голая', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'нюдсы', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'секс', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'оральный секс', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'изнасилование', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'изнасиловать', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
        ],
        'belico' => [
            ['word' => 'война', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'бомбардировка', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'ракета', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'геноцид', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'резня', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'массовое убийство', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'истребление', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'террорист', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'теракт', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'вторжение', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'армия', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'расстрел', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'обезглавить', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'этническая чистка', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'священная война', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'джихад', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
        'ambiguous' => [
            ['word' => 'бомба', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '(?<!\w)я\s+(?:тебя\s+)?(?:убью|прикончу|зарежу|задушу|пристрелю|урою)(?!\w)|(?<!\w)(?:убью|зарежу|прикончу|пристрелю)\s+(?:тебя|тебе)(?!\w)', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'угроза'],
        ['pattern' => '(?<!\w)я\s+тебя\s+изнасилую(?!\w)|(?<!\w)изнасилую\s+тебя(?!\w)', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'сексуальная угроза'],
    ],
];
