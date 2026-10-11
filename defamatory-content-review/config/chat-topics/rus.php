<?php

/** Russian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'rus', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'порно', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'порнография', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'минет', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'мастурбация', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'мастурбировать', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'оргазм', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'пенис', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'вагина', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'сиськи', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'голая', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'нюдсы', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'секс', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'оральный секс', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'изнасилование', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'изнасиловать', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'война', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'бомбардировка', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ракета', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'геноцид', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'резня', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'массовое убийство', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'истребление', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'террорист', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'теракт', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'вторжение', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'армия', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'расстрел', 'riskType' => 'belico', 'severity' => 'high'],
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
