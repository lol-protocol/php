<?php

/** Hungarian chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'hun', 'kind' => 'chat-topics'],
    'everyday' => ['szemet', 'örült', 'santa', 'sértés'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['pornót', 'pornók', 'pornóra', 'pornófilm']],
            ['word' => 'pornográfia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'szopás', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'maszturbáció', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'maszturbál', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgazmus', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'pénisz', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'mellek', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'meztelen', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'meztelen képek', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'szex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'orális szex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nemi erőszak', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'megerőszakol', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'háború', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombázás', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'rakéta', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'népirtás', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'mészárlás', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'kiirt', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorista', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'merénylet', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invázió', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'hadsereg', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'kivégez', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'lefejez', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'etnikai tisztogatás', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'szent háború', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\b(?:megollek|megfoglak\s+olni|meg\s+foglak\s+olni|le\s+foglak\s+szurni|lelovelek|megfojtalak)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'fenyegetes'],
        ['pattern' => '\b(?:megeroszakollak|meg\s+foglak\s+eroszakolni)\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'szexualis fenyegetes'],
    ],
];
