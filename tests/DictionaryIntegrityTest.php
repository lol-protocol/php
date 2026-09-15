<?php

namespace Tests;

use DefamatoryContentReview\WordList;
use PHPUnit\Framework\TestCase;

/**
 * Chequeos de integridad sobre los 30 diccionarios, sin depender de ningún
 * idioma en concreto. Nacen de errores reales: una misma palabra en dos
 * categorías del mismo idioma pisa en silencio la primera en el índice de
 * WordList, y `coverage` llegó a vivir duplicado (meta del idioma + copia
 * en el catálogo) hasta desincronizarse — `testCatalogHasNoDuplicatedCoverageField()`
 * impide que esa segunda copia vuelva sin darse cuenta.
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
