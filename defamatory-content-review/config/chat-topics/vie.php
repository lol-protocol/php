<?php

/** Vietnamese chat topics — see spa.php for the format and severity meaning. */
return [
    'meta' => ['code' => 'vie', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'phim sex', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'phim khiêu dâm', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'khiêu dâm', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sex', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'thủ dâm', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'quan hệ tình dục', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'hiếp dâm', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ảnh nóng', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'ảnh khỏa thân', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'khỏa thân', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'dương vật', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'âm đạo', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'làm tình', 'riskType' => 'sexual', 'severity' => 'medium'],
        ],
        'belico' => [
            ['word' => 'chiến tranh', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'ném bom', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'tên lửa', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'diệt chủng', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'thảm sát', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'khủng bố', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'xâm lược', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'quân đội', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'hành quyết', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'chặt đầu', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'thanh trừng sắc tộc', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'thánh chiến', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'tao giết mày', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'giết mày', 'riskType' => 'belico', 'severity' => 'high'],
        ],
        'ambiguous' => [
            ['word' => 'bom', 'riskType' => 'belico', 'severity' => 'medium'],
        ],
    ],
    'patterns' => [
    ],
];
