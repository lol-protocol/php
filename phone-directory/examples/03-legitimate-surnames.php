<?php

// 3. Apellidos legítimos que coinciden con el diccionario.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');
$pad = fn(string $s, int $width) => $s . str_repeat(' ', max(0, $width - mb_strlen($s)));

// "Cerda", "Moro" o "Calvo" son linajes documentados. Una coincidencia grave
// sobre ellos va a revisión humana, nunca a rechazo automático.
foreach ([['Juan', 'Moro'], ['Ana', 'Cerda'], ['Luis', 'Bastardo']] as [$first, $last]) {
    $result = $reviewer->validateFullName($first, $last);

    printf(
        "%s severidad=%-6s colisión con apellido=%s → %s\n",
        $pad("{$first} {$last}", 16),
        $result->getSeverity(),
        $pad($result->hasNameCollision() ? 'sí' : 'no', 3),
        $reviewer->decide($result)
    );
}
