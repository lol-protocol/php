<?php

/** Greek chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ell', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'πορνό', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'πορνογραφία', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'στοματικό σεξ', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'αυνανισμός', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'οργασμός', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'πέος', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'βυζιά', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'γυμνός', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'γυμνές φωτογραφίες', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'σεξ', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'βιασμός', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'βιάζω', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'θα σε βιάσω', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'πόλεμος', 'riskType' => 'belico', 'severity' => 'low', 'also' => ['πολέμου', 'πόλεμο']],
            ['word' => 'βομβαρδισμός', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'πύραυλος', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'γενοκτονία', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'σφαγή', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'εξόντωση', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'τρομοκράτης', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'εισβολή', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'στρατός', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'εκτέλεση', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'αποκεφαλισμός', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'εθνοκάθαρση', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'ιερός πόλεμος', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'θα σε σκοτώσω', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'θα σε σφάξω', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'βόμβα', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
