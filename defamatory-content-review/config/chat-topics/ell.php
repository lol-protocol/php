<?php

/** Greek chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'ell', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'legit' => ['σκύλλα'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['εσύ', 'είσαι', 'ρε', 'βρε', 'μωρή', 'μωρέ', 'βρωμιάρη', 'βρωμιάρα', 'χοντρέ', 'χοντρή', 'ηλίθιε', 'ηλίθια', 'παλιο', 'παλιό'],
        'after' => ['είσαι', 'ρε'],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'πορνό', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'πορνογραφία', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'στοματικό σεξ', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'αυνανισμός', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'οργασμός', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'πέος', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'βυζιά', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'γυμνός', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'γυμνές φωτογραφίες', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'σεξ', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'βιασμός', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'βιάζω', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'θα σε βιάσω', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'πόλεμος', 'riskType' => 'belico', 'severity' => 'low', 'also' => ['πολέμου', 'πόλεμο']],
            ['word' => 'βομβαρδισμός', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'πύραυλος', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'γενοκτονία', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'σφαγή', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'εξόντωση', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'τρομοκράτης', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'εισβολή', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'στρατός', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'εκτέλεση', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
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
