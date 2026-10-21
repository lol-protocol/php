<?php

namespace Tests\Dictionary;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Cada entrada de cada diccionario se encuentra a sí misma en un texto. Antes,
 * las de más de 3 palabras («son of a bitch», «concha de tu madre») y las de
 * guion («lèche-cul») no se encontraban nunca enteras.
 */
class DictionarySelfDetectionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string}> */
    public static function languages(): array
    {
        $cases = [];
        foreach (glob(self::CONFIG_DIR . '/languages/*.php') as $file) {
            if (basename($file) !== 'supported-languages.php') {
                $cases[basename($file, '.php')] = [basename($file, '.php')];
            }
        }

        return $cases;
    }

    #[DataProvider('languages')]
    public function testEveryEntryIsFoundInText(string $language): void
    {
        $list = DefamatoryContentReviewer::create(self::CONFIG_DIR, $language)->languages()->wordList($language);
        $missing = [];
        foreach ((require self::CONFIG_DIR . "/languages/{$language}.php")['words'] as $entries) {
            foreach ($entries as $entry) {
                if (!in_array($entry['word'], array_column($list->findInText($entry['word']), 'original'), true)) {
                    $missing[] = $entry['word'];
                }
            }
        }

        $this->assertSame([], $missing, 'entradas que no se encuentran a sí mismas');
    }
}
