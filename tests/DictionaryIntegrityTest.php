<?php

namespace Tests;

use DefamatoryContentReview\WordList;
use PHPUnit\Framework\TestCase;

/**
 * Chequeos de integridad sobre los 30 diccionarios que no dependen de ningún
 * idioma en concreto. Nacen de errores reales encontrados a mano durante la
 * ampliación de los diccionarios `basic`:
 *
 * - Dos entradas con la misma palabra en categorías distintas del mismo
 *   idioma (p. ej. "burro" a la vez en `animal` e `intelectual`): como
 *   WordList indexa por palabra normalizada, la segunda pisa a la primera en
 *   silencio y una de las dos categorías queda huérfana sin que nada avise.
 * - El campo `coverage` llegó a vivir en dos sitios (la propia `meta` del
 *   archivo de idioma, y una copia en el catálogo `supported-languages.php`)
 *   y se desincronizó la primera vez que sólo una de las dos se actualizó.
 *   Ahora `supported-languages.php` no declara `coverage` en absoluto —
 *   `testCatalogHasNoDuplicatedCoverageField()` es la guarda que impide que
 *   alguien reintroduzca esa segunda copia sin darse cuenta.
 */
class DictionaryIntegrityTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    /** @return array<int,string> */
    private function languageCodes(): array
    {
        return array_keys(require self::CONFIG_DIR . '/languages/supported-languages.php');
    }

    public function testNoLanguageHasTheSameWordInTwoCategories(): void
    {
        foreach ($this->languageCodes() as $code) {
            $config = require self::CONFIG_DIR . "/languages/{$code}.php";
            $seen = [];

            foreach ($config['words'] as $category => $terms) {
                foreach ($terms as $term) {
                    $word = is_array($term) ? $term['word'] : $term;
                    $key = mb_strtolower(trim($word), 'UTF-8');
                    $firstSeenIn = $seen[$key] ?? '';

                    $this->assertArrayNotHasKey(
                        $key,
                        $seen,
                        "'{$code}': '{$word}' aparece en '{$firstSeenIn}' y otra vez en '{$category}'. " .
                        "La segunda entrada pisa a la primera en el índice de WordList."
                    );

                    $seen[$key] = $category;
                }
            }
        }
    }

    /**
     * `coverage` tiene una única fuente: `meta.coverage` en cada archivo de
     * idioma. Si el catálogo vuelve a declarar su propia copia, las dos
     * pueden desincronizarse otra vez — exactamente el bug que motivó este
     * test.
     */
    public function testCatalogHasNoDuplicatedCoverageField(): void
    {
        $catalog = require self::CONFIG_DIR . '/languages/supported-languages.php';

        foreach ($catalog as $code => $meta) {
            $this->assertArrayNotHasKey(
                'coverage',
                $meta,
                "'{$code}' en supported-languages.php declara 'coverage': eso reintroduce la doble fuente."
            );
        }
    }

    public function testEveryWordListLoadsWithoutError(): void
    {
        foreach ($this->languageCodes() as $code) {
            $wordList = WordList::fromLanguageFile(self::CONFIG_DIR . "/languages/{$code}.php", $code);

            $this->assertGreaterThan(0, $wordList->getWordCount(), "'{$code}' no cargó ningún término.");
        }
    }

    /**
     * Umbral mínimo por nivel de cobertura, para que declarar 'moderate' o
     * 'comprehensive' siga significando algo concreto y no sólo una etiqueta.
     */
    public function testWordCountMatchesDeclaredCoverageLevel(): void
    {
        $minimums = ['basic' => 60, 'moderate' => 120, 'comprehensive' => 200];

        foreach ($this->languageCodes() as $code) {
            $wordList = WordList::fromLanguageFile(self::CONFIG_DIR . "/languages/{$code}.php", $code);
            $coverage = $wordList->getCoverage();

            $this->assertGreaterThanOrEqual(
                $minimums[$coverage],
                $wordList->getWordCount(),
                "'{$code}' se declara '{$coverage}' pero tiene menos términos de los que ese nivel exige."
            );
        }
    }
}
