<?php

require_once __DIR__ . '/../vendor/autoload.php';

use DefamatoryContentReview\DefamatoryContentReviewer;

$reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', 'spa');
$registry = $reviewer->getRegistry();

$rule = fn(string $t = '') => print("\n" . ($t ? "── {$t} " : '') . str_repeat('─', 72 - mb_strlen($t)) . "\n\n");

// printf cuenta bytes, no caracteres: con acentos las columnas se desalinean.
$pad = fn(string $s, int $width) => $s . str_repeat(' ', max(0, $width - mb_strlen($s)));

$rule('1. Validación en el idioma principal');

foreach ([['Zoila', 'Cerda'], ['Juan', 'Pérez'], ['Zurdo', 'Diestro'], ['Luis', 'Bastardo']] as [$first, $last]) {
    $report = $reviewer->getDetailedReport($reviewer->validateFullName($first, $last));

    printf("%s %s\n", $first, $last);
    printf("  severidad: %-8s decisión: %s\n", $report['severity'], $report['decision']);
    printf("  %s\n", $report['recommendation']);

    foreach ($report['riskAnalysis'] as $riskType => $analysis) {
        printf("    · %s %s [%s]\n", $pad($riskType, 14), $pad($analysis['description'], 42), $analysis['level']);
    }
    echo "\n";
}

$rule('2. Fusión fonética: nombre y apellido componen otra palabra al unirse');

// Ninguno de los dos campos es ofensivo por separado; el agravio vive en el
// punto de unión. El detector exige que la coincidencia CRUCE esa frontera,
// por eso "Mariano" o "Luciano" (que contienen "ano" enteros dentro de un
// único apellido) nunca se disparan.
foreach ([['Elba', 'Gina'], ['Felipe', 'Lotas'], ['Susana', 'Oria'], ['Mariano', 'Rajoy']] as [$first, $last]) {
    $result = $reviewer->validateFullName($first, $last);
    $report = $reviewer->getDetailedReport($result);

    printf("%s severidad=%-6s decisión=%s\n", $pad("{$first} {$last}", 16), $report['severity'], $report['decision']);

    foreach ($result->getPhoneticFusionTerms() as $term) {
        printf("    fusión → '%s' (%s)\n", $term['term'], $term['riskType']);
    }
}

$rule('3. Apellidos legítimos que coinciden con el diccionario');

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

$rule('4. Idiomas asociados');

printf("Idioma activo: %s (%s)\n\n", $reviewer->getLanguage(), $registry->getMetadata('spa')['nativeName']);
echo "Asociados por afinidad léxica:\n";

foreach ($reviewer->getRelatedLanguages() as $code => $affinity) {
    printf("  %s  %s afinidad %.2f\n", $code, $pad($registry->getMetadata($code)['nativeName'], 12), $affinity);
}

echo "\nUn término de un idioma asociado se detecta, pero pesa menos:\n\n";

foreach (['João Porco', 'Marco Stronzo', 'Ana Salope'] as $name) {
    $solo = $reviewer->validateName($name);
    $crossed = $reviewer->validateAcrossRelated($name);
    $term = $crossed->getFlaggedTerms()[0] ?? null;

    printf("  %s sólo español: %-8s cruzado: %-8s", $pad($name, 16), $solo->getSeverity(), $crossed->getSeverity());

    if ($term) {
        printf("  (%s '%s', confianza %.2f)", $term['sourceLanguage'], $term['term'], $term['confidence']);
    }
    echo "\n";
}

$rule('5. Familias lingüísticas');

foreach ($registry->getFamilies() as $family) {
    if (count($family['languages']) < 2) {
        continue;
    }
    printf("  %s %s\n", $pad($family['name'], 14), implode(', ', $family['languages']));
}

echo "\nPares más cercanos de cada familia:\n";
foreach (['spa' => 'por', 'ces' => 'slk', 'dan' => 'nor', 'rus' => 'ukr', 'deu' => 'nld'] as $a => $b) {
    printf("  %s ↔ %s   %.2f\n", $a, $b, $registry->getAffinity($a, $b));
}

$rule('6. Conjunto explícito de idiomas');

// Cuando ya se sabe qué lenguas concurren en un fondo documental, se pasan
// directamente y todas cuentan con confianza plena.
$result = $reviewer->validateInLanguages('Hans Scheisse', ['spa', 'deu']);
printf(
    "Hans Scheisse en [spa, deu]: severidad=%s decisión=%s\n",
    $result->getSeverity(),
    $reviewer->decide($result)
);

$rule('7. Estado de los diccionarios');

printf("%-5s %-20s %-15s %8s %12s\n", 'CÓD', 'IDIOMA', 'COBERTURA', 'TÉRMINOS', 'COLISIONES');
echo str_repeat('─', 72) . "\n";

$total = 0;
foreach ($registry->getAll() as $code => $meta) {
    $stats = $reviewer->getWordListStatistics($code);
    $total += $stats['totalWords'];

    printf(
        "%-5s %s %-15s %8d %12d\n",
        $code,
        $pad($meta['name'], 20),
        $stats['coverage'],
        $stats['totalWords'],
        $stats['nameCollisions']
    );
}

echo str_repeat('─', 72) . "\n";
printf("%d términos en %d idiomas\n", $total, count($registry->getCodes()));

$basic = $registry->getLanguagesByCoverage('basic');
if ($basic) {
    printf("\nPendientes de revisión por hablante nativo: %s\n", implode(', ', $basic));
}

$rule('8. Reparto por tipo de riesgo (español)');

$stats = $reviewer->getWordListStatistics('spa');
foreach ($stats['byRiskType'] as $riskType => $count) {
    printf("  %-14s %3d  %s\n", $riskType, $count, str_repeat('█', (int) round($count / 4)));
}
