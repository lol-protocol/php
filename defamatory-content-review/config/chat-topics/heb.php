<?php

/** Hebrew chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'heb', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'פורנו', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'פורנוגרפיה', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'מציצה', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'אוננות', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'אורגזמה', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'פין', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'נרתיק', 'riskType' => 'sexual', 'severity' => 'medium'],
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
            ['word' => 'הפצצה', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'טיל', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'רצח עם', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'טבח', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'השמדה', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'טרוריסט', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'פיגוע', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'פלישה', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'צבא', 'riskType' => 'belico', 'severity' => 'low'],
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
