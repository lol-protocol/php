<?php

/**
 * Modelo de parentesco lingüístico.
 *
 * `families`  agrupa idiomas por rama genealógica.
 * `affinity`  expresa cercanía léxica aproximada entre pares (0.0 - 1.0).
 *
 * Las cifras de afinidad son aproximaciones basadas en estimaciones publicadas
 * de similitud léxica / inteligibilidad mutua. No son medidas exactas: sirven
 * como peso relativo para decidir qué diccionarios vale la pena consultar en
 * cruz y con cuánta confianza tratar una coincidencia hallada en ellos.
 *
 * Sólo se declara cada par una vez; LanguageRegistry lo simetriza al cargar.
 */

return [
    'families' => [
        'romance' => [
            'name' => 'Romance',
            'languages' => ['spa', 'por', 'fra', 'ita', 'ron'],
        ],
        'germanic' => [
            'name' => 'Germánica',
            'languages' => ['eng', 'deu', 'nld', 'swe', 'dan', 'nor'],
        ],
        'slavic' => [
            'name' => 'Eslava',
            'languages' => ['rus', 'ukr', 'bul', 'pol', 'ces', 'slk'],
        ],
        'uralic' => [
            'name' => 'Urálica',
            'languages' => ['fin', 'hun'],
        ],
        'hellenic' => [
            'name' => 'Helénica',
            'languages' => ['ell'],
        ],
        'turkic' => [
            'name' => 'Túrquica',
            'languages' => ['tur'],
        ],
        'semitic' => [
            'name' => 'Semítica',
            'languages' => ['ara', 'heb'],
        ],
        'indo_aryan' => [
            'name' => 'Indoaria',
            'languages' => ['hin'],
        ],
        'japonic' => [
            'name' => 'Japónica',
            'languages' => ['jpn'],
        ],
        'koreanic' => [
            'name' => 'Coreánica',
            'languages' => ['kor'],
        ],
        'sinitic' => [
            'name' => 'Sinítica',
            'languages' => ['zho'],
        ],
        'tai_kadai' => [
            'name' => 'Tai-Kadai',
            'languages' => ['tha'],
        ],
        'austroasiatic' => [
            'name' => 'Austroasiática',
            'languages' => ['vie'],
        ],
        'austronesian' => [
            'name' => 'Austronesia',
            'languages' => ['ind'],
        ],
    ],

    // Pares con cercanía suficiente para justificar validación cruzada.
    'affinity' => [
        // --- Romance ---
        'spa|por' => 0.89,
        'spa|ita' => 0.82,
        'spa|fra' => 0.75,
        'spa|ron' => 0.71,
        'por|ita' => 0.80,
        'por|fra' => 0.75,
        'por|ron' => 0.72,
        'fra|ita' => 0.89,
        'fra|ron' => 0.75,
        'ita|ron' => 0.77,

        // --- Germánica ---
        'dan|nor' => 0.90,
        'swe|nor' => 0.88,
        'swe|dan' => 0.85,
        'deu|nld' => 0.84,
        'eng|nld' => 0.63,
        'eng|deu' => 0.60,
        'nld|swe' => 0.55,
        'nld|dan' => 0.54,
        'deu|swe' => 0.54,
        'deu|dan' => 0.53,
        'eng|swe' => 0.50,
        'eng|dan' => 0.50,
        'eng|nor' => 0.50,
        'deu|nor' => 0.53,
        'nld|nor' => 0.54,

        // --- Eslava ---
        'ces|slk' => 0.91,
        'rus|ukr' => 0.86,
        'rus|bul' => 0.74,
        'ukr|pol' => 0.70,
        'ukr|bul' => 0.72,
        'pol|ces' => 0.72,
        'pol|slk' => 0.70,
        'rus|pol' => 0.66,
        'rus|ces' => 0.62,
        'rus|slk' => 0.62,
        'ukr|ces' => 0.62,
        'ukr|slk' => 0.62,
        'bul|pol' => 0.60,
        'bul|ces' => 0.60,
        'bul|slk' => 0.60,

        // --- Urálica ---
        'fin|hun' => 0.25,

        // --- Semítica ---
        'ara|heb' => 0.35,

        // --- Contacto / préstamo intenso, sin parentesco directo ---
        // Turco y búlgaro/griego comparten préstamos otomanos.
        'tur|bul' => 0.30,
        'tur|ell' => 0.30,
        // Rumano tiene sustrato eslavo relevante.
        'ron|bul' => 0.40,
        'ron|ukr' => 0.33,
        // Griego aportó vocabulario culto al romance.
        'ell|ita' => 0.30,
        // Japonés y coreano comparten léxico sinojaponés/sinocoreano.
        'jpn|zho' => 0.40,
        'kor|zho' => 0.40,
        'jpn|kor' => 0.35,
        'vie|zho' => 0.40,
        // Hindi y árabe/persa vía préstamos.
        'hin|ara' => 0.25,
        // Indonesio con préstamos árabes y neerlandeses.
        'ind|ara' => 0.22,
        'ind|nld' => 0.20,
        // Tailandés con préstamos sánscritos/pali compartidos con hindi.
        'tha|hin' => 0.20,
    ],

    /**
     * Umbral por defecto para considerar dos idiomas "asociados" a efectos de
     * validación cruzada. Bajarlo amplía la cobertura y sube los falsos
     * positivos; subirlo hace lo contrario.
     */
    'defaultAffinityThreshold' => 0.60,
];
