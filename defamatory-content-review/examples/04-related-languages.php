<?php

// 4. Idiomas asociados.

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');
$registry = $reviewer->languages()->registry();
$pad = fn(string $s, int $width) => $s . str_repeat(' ', max(0, $width - mb_strlen($s)));

printf("Idioma activo: %s (%s)\n\n", $reviewer->getLanguage(), $registry->getMetadata('spa')['nativeName']);
echo "Asociados por afinidad léxica:\n";

foreach ($reviewer->related()->languages() as $code => $affinity) {
    printf("  %s  %s afinidad %.2f\n", $code, $pad($registry->getMetadata($code)['nativeName'], 12), $affinity);
}

echo "\nUn término de un idioma asociado se detecta, pero pesa menos:\n\n";

foreach (['João Porco', 'Marco Stronzo', 'Ana Salope'] as $name) {
    $solo = $reviewer->validateName($name);
    $crossed = $reviewer->related()->validate($name);
    $term = $crossed->getFlaggedTerms()[0] ?? null;

    printf("  %s sólo español: %-8s cruzado: %-8s", $pad($name, 16), $solo->getSeverity(), $crossed->getSeverity());

    if ($term) {
        printf("  (%s '%s', confianza %.2f)", $term['sourceLanguage'], $term['term'], $term['confidence']);
    }
    echo "\n";
}
