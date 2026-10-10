<?php

/** Turkish chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'tur', 'kind' => 'chat-topics'],
    'everyday' => ['sık', 'şık', 'katıl', 'çinli'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high', 'also' => ['pornolar', 'pornoları', 'pornoya', 'pornoyu', 'pornoyla', 'pornodan']],
            ['word' => 'pornografi', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'oral seks', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'mastürbasyon', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgazm', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'penis', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'vajina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'çıplak', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'çıplak fotoğraf', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'seks', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'tecavüz', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sikişmek', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'savaş', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bombardıman', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'füze', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'soykırım', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'katliam', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terörist', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'terör saldırısı', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'işgal', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'ordu', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'idam', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'kafa kesmek', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'etnik temizlik', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'kutsal savaş', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'cihat', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
        'ambiguous' => [
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
        ['pattern' => '\bseni\s+(?:oldurecegim|oldururum|gebertecegim|gebertirim|bogacagim|keserim|kesecegim|vuracagim)\b', 'riskType' => 'belico', 'severity' => 'high', 'label' => 'tehdit'],
        ['pattern' => '\bsana\s+tecavuz\s+edecegim\b', 'riskType' => 'sexual', 'severity' => 'high', 'label' => 'cinsel tehdit'],
    ],
];
