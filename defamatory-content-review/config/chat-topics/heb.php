<?php

/** Hebrew chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'heb', 'kind' => 'chat-topics'],
    'targeted' => [
        'types' => ['animal', 'discapacidad'],
        'before' => ['אתה', 'אתם', 'אתן', 'יא', 'ממש', 'איזה', 'איזו', 'מסריח', 'מסריחה', 'מטומטם', 'מטומטמת'],
        'after' => ['אתה', 'מסריח', 'מסריחה'],
    ],
    'words' => [
        'sexual' => [
            ['word' => 'פורנו', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'פורנוגרפיה', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'מציצה', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'אוננות', 'riskType' => 'sexual', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'אורגזמה', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'פין', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'נרתיק', 'riskType' => 'sexual', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'שדיים', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'עירום', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'תמונות עירום', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'סקס', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'סקס אוראלי', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'אונס', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'לאנוס', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'אני אאנוס אותך', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'מלחמה', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'הפצצה', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'טיל', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'רצח עם', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'טבח', 'riskType' => 'belico', 'severity' => 'high', 'forms' => 'noun'],
            ['word' => 'השמדה', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'טרוריסט', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'פיגוע', 'riskType' => 'belico', 'severity' => 'medium', 'forms' => 'noun'],
            ['word' => 'פלישה', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'צבא', 'riskType' => 'belico', 'severity' => 'low', 'forms' => 'noun'],
            ['word' => 'הוצאה להורג', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'עריפת ראש', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'טיהור אתני', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'מלחמת קודש', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'אני אהרוג אותך', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'אני ארצח אותך', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'אהרוג אותך', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'אשחט אותך', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'פצצה', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
