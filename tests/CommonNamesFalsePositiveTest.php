<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La regla central del proyecto: no marcar linajes reales. Cruza todos los
 * nombres con todos los apellidos comunes de cada idioma con fusión (144
 * combinaciones por idioma) y exige cero detecciones. Si un término nuevo
 * del diccionario o una regla fonética nueva rompe esto, falla aquí antes
 * de llegar a producción.
 */
class CommonNamesFalsePositiveTest extends TestCase
{
    public static function corpus(): array
    {
        $cases = [];
        foreach (require __DIR__ . '/fixtures/common-names.php' as $lang => [$firsts, $lasts]) {
            $cases[$lang] = [$lang, $firsts, $lasts];
        }

        return $cases;
    }

    /** @param array<int,string> $firsts @param array<int,string> $lasts */
    #[DataProvider('corpus')]
    public function testCommonRealNamesAreNeverFlagged(string $lang, array $firsts, array $lasts): void
    {
        $reviewer = DefamatoryContentReviewer::create(__DIR__ . '/../config', $lang);
        $flagged = [];

        foreach ($firsts as $first) {
            foreach ($lasts as $last) {
                $result = $reviewer->validateFullName($first, $last);
                if (!$result->isValid()) {
                    $terms = array_column($result->getFlaggedTerms(), 'term');
                    $flagged[] = "$first $last → " . implode(', ', $terms);
                }
            }
        }

        $this->assertSame([], $flagged, "Falsos positivos en $lang:\n" . implode("\n", $flagged));
    }

    public function testCorpusCoversEveryFusionLanguage(): void
    {
        $corpus = array_keys(require __DIR__ . '/fixtures/common-names.php');
        $missing = array_diff(\DefamatoryContentReview\FusionSupport::supportedLanguages(), $corpus);

        $this->assertSame([], array_values($missing), 'Idiomas con fusión sin corpus de nombres reales.');
    }
}
