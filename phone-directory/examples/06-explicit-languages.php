<?php

// 6. Conjunto explícito de idiomas.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');

// Cuando ya se sabe qué lenguas concurren en un fondo documental, se pasan
// directamente y todas cuentan con confianza plena.
$result = $reviewer->validateInLanguages('Hans Scheisse', ['spa', 'deu']);
printf(
    "Hans Scheisse en [spa, deu]: severidad=%s decisión=%s\n",
    $result->getSeverity(),
    $reviewer->decide($result)
);
