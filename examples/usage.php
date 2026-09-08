<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\WordList;

$riskCategories = require __DIR__ . '/../config/risk-categories.php';
$config = require __DIR__ . '/../config/languages/es.php';
$wordList = new WordList($config, 'es', $riskCategories);
$reviewer = new DefamatoryContentReviewer($wordList, 'es', $riskCategories);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║   MÓDULO DE REVISIÓN DE CONTENIDO DIFAMATORIO v2.0        ║\n";
echo "║   Con Clasificación de Riesgos y Soporte Multiidioma       ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

echo "=== Ejemplos de Validación Simple ===\n\n";

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
    echo "Lenguaje: {$report['language']}\n";
    echo "Válido: " . ($report['valid'] ? 'Sí ✓' : 'No ✗') . "\n";
    echo "Severidad: {$report['severity']}\n";

    if (!$report['valid']) {
        echo "Tipos de riesgo detectados: " . implode(', ', $report['flaggedRiskTypes']) . "\n";
        echo "Categorías: " . implode(', ', $report['flaggedCategories']) . "\n";

        echo "Términos marcados por tipo de riesgo:\n";
        foreach ($report['termsByRiskType'] as $riskType => $terms) {
            echo "  🔴 $riskType:\n";
            foreach ($terms as $term) {
                echo "     - '{$term['term']}' [Severidad: {$term['severity']}]\n";
            }
        }

        echo "\nAnálisis de Riesgos:\n";
        foreach ($report['riskAnalysis'] as $riskType => $analysis) {
            $level = $analysis['isSevere'] ? '⚠️  ALTO' : '⚠️  MEDIO';
            echo "  $level: {$analysis['description']}\n";
        }
    }

    echo "Recomendación: {$report['recommendation']}\n";
    echo str_repeat("─", 60) . "\n\n";
}

echo "=== Validación por Lotes ===\n\n";

$names = [
    'Zoila Cerda',
    'Juan Pérez',
    'Carlos Imbécil García',
    'María González',
    'Roberto Canalla Mendez',
    'Anna Bastardo López',
];

$results = $reviewer->batchValidateFullNames($names);

echo "Resumen de resultados:\n";
echo str_repeat("─", 80) . "\n";
printf("%-30s | %-12s | %-30s\n", "Nombre", "Estado", "Tipos de Riesgo");
echo str_repeat("─", 80) . "\n";

foreach ($results as $result) {
    $status = $result->isValid() ? '✓ Válido' : '✗ Inválido';
    $riskTypes = $result->getFlaggedRiskTypes();
    $riskTypesStr = $result->isValid() ? 'N/A' : implode(', ', $riskTypes);

    printf("%-30s | %-12s | %-30s\n", substr($result->getFullName(), 0, 28), $status, substr($riskTypesStr, 0, 28));
}
echo str_repeat("─", 80) . "\n\n";

echo "=== Estadísticas del Diccionario ===\n\n";

$stats = $reviewer->getWordListStatistics();
echo "Total de palabras: {$stats['totalWords']}\n";
echo "Lenguaje: {$stats['language']}\n\n";

echo "Distribución por tipo de riesgo:\n";
foreach ($stats['byRiskType'] as $riskType => $count) {
    echo "  - $riskType: $count palabras\n";
}

echo "\nDistribución por severidad:\n";
foreach ($stats['bySeverity'] as $severity => $count) {
    echo "  - $severity: $count palabras\n";
}

echo "\n=== Demostración Multiidioma ===\n\n";

echo "Idiomas soportados:\n";
$supportedLanguages = require __DIR__ . '/../config/languages/supported-languages.php';
$languageCount = 0;
foreach ($supportedLanguages as $code => $lang) {
    echo "  - {$lang['nativeName']} ($code)\n";
    $languageCount++;
    if ($languageCount % 5 === 0) {
        echo "\n";
    }
}

echo "\nCargando diccionario en Inglés...\n";
$reviewerEN = new DefamatoryContentReviewer($wordList, 'en', $riskCategories);
if ($reviewerEN->loadLanguage('en')) {
    echo "✓ Diccionario Inglés cargado correctamente\n";
    $resultEN = $reviewerEN->validateName('idiot');
    echo "Prueba: 'idiot' - " . ($resultEN->isValid() ? 'Válido' : 'Inválido') . "\n";
    if (!$resultEN->isValid()) {
        echo "  Tipos de riesgo: " . implode(', ', $resultEN->getFlaggedRiskTypes()) . "\n";
    }
} else {
    echo "✗ No se pudo cargar el diccionario Inglés\n";
}

echo "\nCargando diccionario en Francés...\n";
if ($reviewerEN->loadLanguage('fr')) {
    echo "✓ Diccionario Francés cargado correctamente\n";
    $resultFR = $reviewerEN->validateName('idiot');
    echo "Prueba: 'idiot' - " . ($resultFR->isValid() ? 'Válido' : 'Inválido') . "\n";
    if (!$resultFR->isValid()) {
        echo "  Tipos de riesgo: " . implode(', ', $resultFR->getFlaggedRiskTypes()) . "\n";
    }
} else {
    echo "✗ No se pudo cargar el diccionario Francés\n";
}

echo "\n" . str_repeat("═", 60) . "\n";
echo "Fin de demostración\n";
echo str_repeat("═", 60) . "\n";
