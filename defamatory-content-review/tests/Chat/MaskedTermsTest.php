<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** «p*ta», «m*erda»: cada asterisco vale por una letra; a lo sumo va a revisión (MaskedTerms). */
class MaskedTermsTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string}> línea, término */
    public static function maskedInsults(): array
    {
        return [
            'una letra' => ['eres una p*ta', 'puta'],
            'dos letras' => ['p**a', 'puta'],
            'con tilde' => ['c*brón', 'cabrón'],
            'al principio' => ['*diota', 'idiota'],
        ];
    }

    #[DataProvider('maskedInsults')]
    public function testMaskedInsultGoesToReview(string $line, string $term): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review($line);

        $this->assertSame('review', $result->getDecision(), $line);
        $this->assertContains($term, array_column($result->getMatches(), 'original'));
        $this->assertContains(true, array_column($result->getMatches(), 'masked'));
    }

    /** @return array<string,array{string}> */
    public static function harmlessAsterisks(): array
    {
        return [['5*3 = 15'], ['**importante**'], ['***'], ['a*b'], ['nota*: ver abajo'], ['pa*a']];
    }

    #[DataProvider('harmlessAsterisks')]
    public function testOtherAsterisksAreLeftAlone(string $line): void
    {
        $this->assertSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review($line)->getDecision(), $line);
    }
}
