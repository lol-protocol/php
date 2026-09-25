<?php

// 9. Ajustar la política de puntuación (ScoringPolicy).

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\ScoringPolicy;

$configDir = __DIR__ . '/../config';

// Por defecto: el PEOR término encontrado manda (no se suman), con cortes
// en 1.5/2.5 y "etnico" pesando como 'high' aunque la palabra concreta no
// declare severidad. Cambiarlo es construir una ScoringPolicy nueva —
// nunca hace falta tocar DefamatoryContentReviewer.
$default = DefamatoryContentReviewer::create($configDir, 'spa');

$laxo = ScoringPolicy::default()->withDecisionRules([
    'none' => 'accept', 'low' => 'accept_with_flag', 'medium' => 'accept_with_flag', 'high' => 'reject',
]);
$sensibleAlEtnico = DefamatoryContentReviewer::create($configDir, 'spa', ScoringPolicy::default()->withRiskTypeWeight('etnico', 1.5));
$aditivo = DefamatoryContentReviewer::create($configDir, 'spa', ScoringPolicy::default()->withAggregation('sum'));
$reviewerLaxo = DefamatoryContentReviewer::create($configDir, 'spa', $laxo);

$result = $default->validateName('Cerda');
printf("Por defecto: 'Cerda' → %s (score=%.1f)\n", $default->decide($result), $result->getScore());

$result = $reviewerLaxo->validateName('Cerda');
printf("Política laxa (medium ya no va a revisión): 'Cerda' → %s (score=%.1f)\n", $reviewerLaxo->decide($result), $result->getScore());

$result = $sensibleAlEtnico->validateName('gitano');
printf(
    "Peso extra a 'etnico' (x1.5): 'gitano' → severidad=%s (score=%.1f; sin el peso sería medium/2.0)\n",
    $result->getSeverity(),
    $result->getScore()
);

$result = $aditivo->validateName('puta cerda maldita');
printf(
    "Agregación 'sum' (se acumulan los términos en vez de quedarse con el peor): score=%.1f severidad=%s\n",
    $result->getScore(),
    $result->getSeverity()
);
