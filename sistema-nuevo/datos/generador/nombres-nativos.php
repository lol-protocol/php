<?php

declare(strict_types=1);

/**
 * Nombres en su escritura nativa (no romanizados). Se mezclan con nombres.php
 * al generar usuarios (generar-usuarios.php) para probar que el sistema lee y
 * muestra bien otras escrituras además de español/inglés — japonés, árabe y
 * hebreo en particular (árabe/hebreo además son RTL, ver css/base.css).
 *
 * @return array<string, array{first: string[], last: string[]}>
 */

return [
    'ja' => [
        'first' => ['太郎', '花子', '健二', '美咲', '大輔', '由美', '翔太', '愛子'],
        'last' => ['田中', '鈴木', '佐藤', '高橋', '渡辺', '伊藤', '山本', '中村'],
    ],
    'ar' => [
        'first' => ['محمد', 'فاطمة', 'أحمد', 'عائشة', 'علي', 'مريم', 'يوسف', 'ليلى'],
        'last' => ['العلي', 'الحسن', 'المصري', 'الخطيب', 'النجار', 'حداد'],
    ],
    'he' => [
        'first' => ['דוד', 'שרה', 'משה', 'רחל', 'יעקב', 'לאה', 'אברהם', 'מרים'],
        'last' => ['כהן', 'לוי', 'מזרחי', 'פרץ', 'ביטון'],
    ],
    'zh' => [
        'first' => ['伟', '芳', '秀英', '敏', '静', '强', '磊', '洋'],
        'last' => ['王', '李', '张', '刘', '陈', '杨'],
    ],
    'ko' => [
        'first' => ['민준', '서연', '도윤', '지우', '하은', '지호'],
        'last' => ['김', '이', '박', '최', '정'],
    ],
    'ru' => [
        'first' => ['Иван', 'Анна', 'Дмитрий', 'Мария', 'Сергей', 'Ольга'],
        'last' => ['Иванов', 'Петров', 'Смирнова', 'Кузнецов', 'Соколова'],
    ],
];
