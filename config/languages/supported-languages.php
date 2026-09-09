<?php

/**
 * Catálogo de idiomas soportados, indexado por código ISO 639-3 (tres letras).
 *
 * `iso639_1` conserva el código de dos letras para interoperar con sistemas que
 * lo usen (cabeceras HTTP Accept-Language, columnas heredadas, etc.).
 * LanguageRegistry acepta ambos como entrada y siempre normaliza a 639-3.
 *
 * `coverage` describe el estado del diccionario y es información operativa,
 * no cosmética: indica dónde hace falta revisión de hablante nativo antes de
 * usar el módulo en producción para ese idioma.
 *   - comprehensive: cobertura amplia de las diez categorías de riesgo.
 *   - moderate:      categorías principales cubiertas; faltan matices.
 *   - basic:         núcleo de términos verificables; requiere ampliación.
 */

return [
    'spa' => ['iso639_1' => 'es', 'name' => 'Spanish',    'nativeName' => 'Español',           'family' => 'romance',       'coverage' => 'comprehensive'],
    'eng' => ['iso639_1' => 'en', 'name' => 'English',    'nativeName' => 'English',           'family' => 'germanic',      'coverage' => 'comprehensive'],
    'por' => ['iso639_1' => 'pt', 'name' => 'Portuguese', 'nativeName' => 'Português',         'family' => 'romance',       'coverage' => 'comprehensive'],
    'fra' => ['iso639_1' => 'fr', 'name' => 'French',     'nativeName' => 'Français',          'family' => 'romance',       'coverage' => 'comprehensive'],
    'ita' => ['iso639_1' => 'it', 'name' => 'Italian',    'nativeName' => 'Italiano',          'family' => 'romance',       'coverage' => 'comprehensive'],
    'deu' => ['iso639_1' => 'de', 'name' => 'German',     'nativeName' => 'Deutsch',           'family' => 'germanic',      'coverage' => 'comprehensive'],
    'ron' => ['iso639_1' => 'ro', 'name' => 'Romanian',   'nativeName' => 'Română',            'family' => 'romance',       'coverage' => 'moderate'],
    'nld' => ['iso639_1' => 'nl', 'name' => 'Dutch',      'nativeName' => 'Nederlands',        'family' => 'germanic',      'coverage' => 'moderate'],
    'swe' => ['iso639_1' => 'sv', 'name' => 'Swedish',    'nativeName' => 'Svenska',           'family' => 'germanic',      'coverage' => 'moderate'],
    'dan' => ['iso639_1' => 'da', 'name' => 'Danish',     'nativeName' => 'Dansk',             'family' => 'germanic',      'coverage' => 'moderate'],
    'nor' => ['iso639_1' => 'no', 'name' => 'Norwegian',  'nativeName' => 'Norsk',             'family' => 'germanic',      'coverage' => 'moderate'],
    'rus' => ['iso639_1' => 'ru', 'name' => 'Russian',    'nativeName' => 'Русский',           'family' => 'slavic',        'coverage' => 'moderate'],
    'ukr' => ['iso639_1' => 'uk', 'name' => 'Ukrainian',  'nativeName' => 'Українська',        'family' => 'slavic',        'coverage' => 'moderate'],
    'pol' => ['iso639_1' => 'pl', 'name' => 'Polish',     'nativeName' => 'Polski',            'family' => 'slavic',        'coverage' => 'moderate'],
    'ces' => ['iso639_1' => 'cs', 'name' => 'Czech',      'nativeName' => 'Čeština',           'family' => 'slavic',        'coverage' => 'moderate'],
    'slk' => ['iso639_1' => 'sk', 'name' => 'Slovak',     'nativeName' => 'Slovenčina',        'family' => 'slavic',        'coverage' => 'moderate'],
    'bul' => ['iso639_1' => 'bg', 'name' => 'Bulgarian',  'nativeName' => 'Български',         'family' => 'slavic',        'coverage' => 'moderate'],
    'ell' => ['iso639_1' => 'el', 'name' => 'Greek',      'nativeName' => 'Ελληνικά',          'family' => 'hellenic',      'coverage' => 'moderate'],
    'hun' => ['iso639_1' => 'hu', 'name' => 'Hungarian',  'nativeName' => 'Magyar',            'family' => 'uralic',        'coverage' => 'moderate'],
    'fin' => ['iso639_1' => 'fi', 'name' => 'Finnish',    'nativeName' => 'Suomi',             'family' => 'uralic',        'coverage' => 'moderate'],
    'tur' => ['iso639_1' => 'tr', 'name' => 'Turkish',    'nativeName' => 'Türkçe',            'family' => 'turkic',        'coverage' => 'moderate'],
    'ara' => ['iso639_1' => 'ar', 'name' => 'Arabic',     'nativeName' => 'العربية',            'family' => 'semitic',       'coverage' => 'moderate'],
    'heb' => ['iso639_1' => 'he', 'name' => 'Hebrew',     'nativeName' => 'עברית',              'family' => 'semitic',       'coverage' => 'moderate'],
    'hin' => ['iso639_1' => 'hi', 'name' => 'Hindi',      'nativeName' => 'हिन्दी',              'family' => 'indo_aryan',    'coverage' => 'moderate'],
    'jpn' => ['iso639_1' => 'ja', 'name' => 'Japanese',   'nativeName' => '日本語',              'family' => 'japonic',       'coverage' => 'moderate'],
    'kor' => ['iso639_1' => 'ko', 'name' => 'Korean',     'nativeName' => '한국어',              'family' => 'koreanic',      'coverage' => 'moderate'],
    'zho' => ['iso639_1' => 'zh', 'name' => 'Chinese',    'nativeName' => '中文',                'family' => 'sinitic',       'coverage' => 'moderate'],
    'tha' => ['iso639_1' => 'th', 'name' => 'Thai',       'nativeName' => 'ไทย',                'family' => 'tai_kadai',     'coverage' => 'moderate'],
    'vie' => ['iso639_1' => 'vi', 'name' => 'Vietnamese', 'nativeName' => 'Tiếng Việt',        'family' => 'austroasiatic', 'coverage' => 'moderate'],
    'ind' => ['iso639_1' => 'id', 'name' => 'Indonesian', 'nativeName' => 'Bahasa Indonesia',  'family' => 'austronesian',  'coverage' => 'moderate'],
];
