<?php

/** Swedish chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'swe', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porr', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['porren', 'porrfilm', 'porrfilmer']],
            ['word' => 'pornografi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'avsugning', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'onanera', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasm', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'slida', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'bröst', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'tuttar', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'naken', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nakenbilder', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['nudes']],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'oralsex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'våldtäkt', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'våldta', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'knulla', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'krig', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombning', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'missil', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'folkmord', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massaker', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'utrota', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorist', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'attentat', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasion', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'armé', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'avrätta', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'halshugga', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'etnisk rensning', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'heligt krig', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomb', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bjag\s+(?:ska\s+|kommer\s+att\s+)?(?:doda|morda|knivhugga|skjuta|slakta)\s+dig\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'hot'],
        ['pattern' => '\bjag\s+(?:ska\s+)?valdta\s+dig\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'sexuellt hot'],
    ],
];
