<?php

/** Thai chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'tha', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'โป๊', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'หนังโป๊', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ภาพโป๊', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ช่วยตัวเอง', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ออรัลเซ็กส์', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'เซ็กส์', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'ข่มขืน', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ภาพเปลือย', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'เปลือย', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'นู้ด', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
        'belico' => [
            ['word' => 'สงคราม', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'ทิ้งระเบิด', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ขีปนาวุธ', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ล้างเผ่าพันธุ์', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'สังหารหมู่', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'ผู้ก่อการร้าย', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'ก่อการร้าย', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'รุกราน', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'กองทัพ', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'ประหารชีวิต', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'ตัดหัว', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'ฆ่ามึง', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'จะฆ่า', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
        'ambiguous' => [
            ['word' => 'ระเบิด', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
