<?php

/**
 * Catálogo de idiomas soportados, indexado por código ISO 639-3 (tres letras).
 *
 * `iso639_1` conserva el código de dos letras para interoperar con sistemas que
 * lo usen (cabeceras HTTP Accept-Language, columnas heredadas, etc.).
 * LanguageRegistry acepta ambos como entrada y siempre normaliza a 639-3.
 *
 * Este catálogo es sólo identidad y parentesco — nombre, alias, familia. El
 * estado del diccionario (`coverage`) vive únicamente en el propio archivo de
 * cada idioma (`meta.coverage` en `es.php`, `pt.php`...) y se consulta con
 * DefamatoryContentReviewer::getLanguagesByCoverage(); no se duplica aquí.
 * Guardarlo dos veces fue justamente el bug que motivó este comentario: las
 * dos copias se desincronizaron la primera vez que sólo una de ellas se
 * actualizó.
 */

return [
    'spa' => ['iso639_1' => 'es', 'name' => 'Spanish',    'nativeName' => 'Español',           'family' => 'romance'],
    'eng' => ['iso639_1' => 'en', 'name' => 'English',    'nativeName' => 'English',           'family' => 'germanic'],
    'por' => ['iso639_1' => 'pt', 'name' => 'Portuguese', 'nativeName' => 'Português',         'family' => 'romance'],
    'fra' => ['iso639_1' => 'fr', 'name' => 'French',     'nativeName' => 'Français',          'family' => 'romance'],
    'ita' => ['iso639_1' => 'it', 'name' => 'Italian',    'nativeName' => 'Italiano',          'family' => 'romance'],
    'deu' => ['iso639_1' => 'de', 'name' => 'German',     'nativeName' => 'Deutsch',           'family' => 'germanic'],
    'ron' => ['iso639_1' => 'ro', 'name' => 'Romanian',   'nativeName' => 'Română',            'family' => 'romance'],
    'nld' => ['iso639_1' => 'nl', 'name' => 'Dutch',      'nativeName' => 'Nederlands',        'family' => 'germanic'],
    'swe' => ['iso639_1' => 'sv', 'name' => 'Swedish',    'nativeName' => 'Svenska',           'family' => 'germanic'],
    'dan' => ['iso639_1' => 'da', 'name' => 'Danish',     'nativeName' => 'Dansk',             'family' => 'germanic'],
    'nor' => ['iso639_1' => 'no', 'name' => 'Norwegian',  'nativeName' => 'Norsk',             'family' => 'germanic'],
    'rus' => ['iso639_1' => 'ru', 'name' => 'Russian',    'nativeName' => 'Русский',           'family' => 'slavic'],
    'ukr' => ['iso639_1' => 'uk', 'name' => 'Ukrainian',  'nativeName' => 'Українська',        'family' => 'slavic'],
    'pol' => ['iso639_1' => 'pl', 'name' => 'Polish',     'nativeName' => 'Polski',            'family' => 'slavic'],
    'ces' => ['iso639_1' => 'cs', 'name' => 'Czech',      'nativeName' => 'Čeština',           'family' => 'slavic'],
    'slk' => ['iso639_1' => 'sk', 'name' => 'Slovak',     'nativeName' => 'Slovenčina',        'family' => 'slavic'],
    'bul' => ['iso639_1' => 'bg', 'name' => 'Bulgarian',  'nativeName' => 'Български',         'family' => 'slavic'],
    'ell' => ['iso639_1' => 'el', 'name' => 'Greek',      'nativeName' => 'Ελληνικά',          'family' => 'hellenic'],
    'hun' => ['iso639_1' => 'hu', 'name' => 'Hungarian',  'nativeName' => 'Magyar',            'family' => 'uralic'],
    'fin' => ['iso639_1' => 'fi', 'name' => 'Finnish',    'nativeName' => 'Suomi',             'family' => 'uralic'],
    'tur' => ['iso639_1' => 'tr', 'name' => 'Turkish',    'nativeName' => 'Türkçe',            'family' => 'turkic'],
    'ara' => ['iso639_1' => 'ar', 'name' => 'Arabic',     'nativeName' => 'العربية',            'family' => 'semitic'],
    'heb' => ['iso639_1' => 'he', 'name' => 'Hebrew',     'nativeName' => 'עברית',              'family' => 'semitic'],
    'hin' => ['iso639_1' => 'hi', 'name' => 'Hindi',      'nativeName' => 'हिन्दी',              'family' => 'indo_aryan'],
    'jpn' => ['iso639_1' => 'ja', 'name' => 'Japanese',   'nativeName' => '日本語',              'family' => 'japonic'],
    'kor' => ['iso639_1' => 'ko', 'name' => 'Korean',     'nativeName' => '한국어',              'family' => 'koreanic'],
    'zho' => ['iso639_1' => 'zh', 'name' => 'Chinese',    'nativeName' => '中文',                'family' => 'sinitic'],
    'tha' => ['iso639_1' => 'th', 'name' => 'Thai',       'nativeName' => 'ไทย',                'family' => 'tai_kadai'],
    'vie' => ['iso639_1' => 'vi', 'name' => 'Vietnamese', 'nativeName' => 'Tiếng Việt',        'family' => 'austroasiatic'],
    'ind' => ['iso639_1' => 'id', 'name' => 'Indonesian', 'nativeName' => 'Bahasa Indonesia',  'family' => 'austronesian'],
];
