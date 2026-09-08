<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\WordList;

$config = require __DIR__ . '/../config/defamatory-words.php';
$wordList = new WordList($config);
$reviewer = new DefamatoryContentReviewer($wordList);

echo "=== Ejemplos de Validación de Nombres ===\n\n";

$testCases = [
    ['firstName' => 'Zoila', 'lastName' => 'Cerda'],
    ['firstName' => 'Juan', 'lastName' => 'Pérez'],
    ['firstName' => 'Carlos', 'lastName' => 'Idiota García'],
    ['firstName' => 'María', 'lastName' => 'González'],
];

foreach ($testCases as $case) {
    $result = $reviewer->validateFullName($case['firstName'], $case['lastName']);
    $report = $reviewer->getDetailedReport($result);

    echo "Nombre: {$report['name']}\n";
    echo "Válido: " . ($report['valid'] ? 'Sí' : 'No') . "\n";
    echo "Severidad: {$report['severity']}\n";
    echo "Recomendación: {$report['recommendation']}\n";

    if (!$report['valid']) {
        echo "Términos marcados:\n";
        foreach ($report['flaggedTerms'] as $term) {
            echo "  - '{$term['found']}' (categoría: {$term['category']})\n";
        }
    }
    echo "\n";
}

echo "=== Validación por Lotes ===\n\n";

$names = [
    'Zoila Cerda',
    'Juan Pérez',
    'Carlos Imbécil García',
    'María González',
    'Roberto Canalla Mendez',
];

$results = $reviewer->batchValidateFullNames($names);

echo "Resumen de resultados:\n";
foreach ($results as $result) {
    $status = $result->isValid() ? '✓' : '✗';
    echo "{$status} {$result->getFullName()} [{$result->getSeverity()}]\n";
}

echo "\n=== Estadísticas ===\n";
echo "Total de palabras en la lista negra: {$wordList->getWordCount()}\n";
