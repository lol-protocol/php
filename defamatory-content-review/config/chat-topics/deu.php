<?php

/** German chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'deu', 'kind' => 'chat-topics', 'collapseRepeats' => true],
    'legit' => ['pollack'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['du', 'du bist', 'bist', 'ihr', 'ihr seid', 'dummes', 'dumme', 'dummer', 'blödes', 'blöde', 'blöder', 'dreckiges', 'dreckige', 'dreckiger', 'fettes', 'fette', 'fetter', 'was für ein', 'was für eine', 'so ein', 'so eine', 'alter', 'alte', 'scheiß', 'verdammtes', 'verdammter', 'verdammte'],
        'after' => ['du'],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografie', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'blowjob', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'wichsen', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'masturbieren', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasmus', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'titten', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'brüste', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'nackt', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'adj'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'nacktfoto', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['nacktfotos']],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'oralsex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'vergewaltigung', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'vergewaltigen', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ficken', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'krieg', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardierung', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'rakete', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'völkermord', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'massaker', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'ausrotten', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorist', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'anschlag', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasion', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'armee', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'erschießen', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'köpfen', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'enthaupten', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'ethnische säuberung', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'heiliger krieg', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'dschihad', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
        'ambiguous' => [
            ['word' => 'bombe', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bich\s+(?:werde\s+dich\s+)?(?:toten|erschiessen|erstechen|erwurgen|umbringen)\b|\bich\s+bring\w*\s+dich\s+um\b|\bich\s+mach\w*\s+dich\s+(?:kalt|fertig)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'drohung'],
        ['pattern' => '\bich\s+(?:werde\s+dich\s+)?vergewaltigen\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'sexuelle drohung'],
    ],
];
