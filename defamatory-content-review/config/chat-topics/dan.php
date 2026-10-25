<?php

/** Danish chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'dan', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'legit' => ['naar', 'koo', 'soo'],
    'everyday' => ['sjovere'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['du', 'du er', 'din', 'dit', 'dine', 'jer', 'dumme', 'fede', 'fucking', 'forbandede', 'klamme', 'ulækre', 'dit lede'],
        'after' => [],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografi', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'blowjob', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'onanere', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'orgasme', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'skede', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'bryster', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nøgen', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'adj'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nøgenbilleder', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'oralsex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'voldtægt', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'voldtage', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'kneppe', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'verb'],
        ],
        'belico' => [
            ['word' => 'krig', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombning', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'missil', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'folkemord', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massakre', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'udrydde', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'terrorist', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'attentat', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'invasion', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'hær', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'henrette', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
            ['word' => 'halshugge', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'verb'],
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
