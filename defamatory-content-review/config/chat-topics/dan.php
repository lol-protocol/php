<?php

/** Danish chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'dan', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'blowjob', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'onanere', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasme', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'skede', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'bryster', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nøgen', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nøgenbilleder', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'oralsex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'voldtægt', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'voldtage', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'kneppe', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'krig', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombning', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'missil', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'folkemord', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massakre', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'udrydde', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorist', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'attentat', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasion', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'hær', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'henrette', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'halshugge', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'etnisk udrensning', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'hellig krig', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bombe', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bjeg\s+(?:vil\s+|skal\s+)?(?:draebe|myrde|stikke|skyde)\s+dig\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'trussel'],
        ['pattern' => '\bjeg\s+(?:vil\s+|skal\s+)?voldtage\s+dig\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'seksuel trussel'],
    ],
];
