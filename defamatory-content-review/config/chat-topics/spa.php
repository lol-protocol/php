<?php

/**
 * Temas que se censuran en el chat además de los insultos del diccionario
 * del idioma: contenido sexual explícito y contenido bélico/violento.
 * Mismo formato que config/languages/*.php para reutilizar WordList.
 * Severidad: high => bloquear, medium => revisión humana, low => sólo se informa.
 */
return [
    'meta' => ['code' => 'spa', 'kind' => 'chat-topics'],
    'words' => [
        'sexual' => [
            ['word' => 'porno', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'pornografia', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'follar', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'coger', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'chingar', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'mamada', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'paja', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'masturbar', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'orgasmo', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'pene', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'verga', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'polla', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'vagina', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'concha', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'tetas', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'desnuda', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'desnudo', 'riskType' => 'sexual', 'severity' => 'medium'],
            ['word' => 'sexo', 'riskType' => 'sexual', 'severity' => 'low'],
            ['word' => 'nudes', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'fotos intimas', 'riskType' => 'sexual', 'severity' => 'high'],
            ['word' => 'sexo oral', 'riskType' => 'sexual', 'severity' => 'high'],
        ],
        'belico' => [
            ['word' => 'guerra', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'bomba', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'bombardear', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'bombardeo', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'misil', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'genocidio', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'masacre', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'exterminar', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'terrorista', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'atentado', 'riskType' => 'belico', 'severity' => 'medium'],
            ['word' => 'invasion', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'ejercito', 'riskType' => 'belico', 'severity' => 'low'],
            ['word' => 'fusilar', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'degollar', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'matarlos', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'hay que matar', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'limpieza etnica', 'riskType' => 'belico', 'severity' => 'high'],
            ['word' => 'guerra santa', 'riskType' => 'belico', 'severity' => 'high'],
        ],
    ],
];
