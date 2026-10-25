<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Las frases corrientes de tests/fixtures/safe-phrases.php no se marcan, en su idioma: cada falso positivo
 * que se arregló queda aquí para que no vuelva. Y las de 'still' siguen marcándose, para que una lista
 * que lo aprueba todo no pase por buena.
 */
class SafePhrasesTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string}> idioma, frase */
    private static function casos(string $grupo): array
    {
        $cases = [];
        foreach ((require __DIR__ . '/../fixtures/safe-phrases.php')[$grupo] as $language => $phrases) {
            foreach ($phrases as $phrase) {
                $cases["{$language}: {$phrase}"] = [$language, $phrase];
            }
        }

        return $cases;
    }

    /** @return array<string,array{string,string}> */
    public static function safe(): array
    {
        return self::casos('safe');
    }

    /** @return array<string,array{string,string}> */
    public static function still(): array
    {
        return self::casos('still');
    }

    #[DataProvider('safe')]
    public function testAnOrdinaryPhraseIsApproved(string $language, string $phrase): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($phrase);

        $this->assertSame('approve', $result->getDecision(), $phrase . ' => ' . json_encode(array_column($result->getMatches(), 'original'), JSON_UNESCAPED_UNICODE));
    }

    #[DataProvider('still')]
    public function testWhatMustBeFlaggedStillIs(string $language, string $phrase): void
    {
        $this->assertNotSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($phrase)->getDecision(), $phrase);
    }

    public function testBothListsHaveContent(): void
    {
        $this->assertGreaterThan(30, count(self::safe()));
        $this->assertGreaterThan(8, count(self::still()));
    }
}
