<?php

namespace Tests\Normalization;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Separadores raros y letras dibujadas con símbolos (HackerSpelling,
 * WholeWords, SpacedLetters): «p~u~t~a», «p - u - t - a», «p|_|ta», «N.4.Z.|».
 */
class SymbolEvasionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string}> línea, término */
    public static function disguised(): array
    {
        return [
            'guiones dobles' => ['p--u--t--a', 'puta'],
            'guiones con espacios' => ['p - u - t - a', 'puta'],
            'puntos con espacios' => ['p . u . t . a', 'puta'],
            'virgulilla' => ['p~u~t~a', 'puta'],
            'barra vertical' => ['p|u|t|a', 'puta'],
            'barra' => ['p/u/t/a', 'puta'],
            'más' => ['p+u+t+a', 'puta'],
            'viñeta' => ['p•u•t•a', 'puta'],
            'paréntesis' => ['p(u)t(a)', 'puta'],
            'comillas' => ['p"u"t"a', 'puta'],
            'u dibujada' => ['p|_|ta', 'puta'],
            'exclamación por i' => ['1d!0t4', 'idiota'],
            'euro por e' => ['€stúpido', 'estúpido'],
            'arroba y exclamación' => ['m!3rd@', 'mierda'],
            'en una frase' => ['h1j0 d3 p|_|t4', 'puta'],
        ];
    }

    #[DataProvider('disguised')]
    public function testDisguisedInsultIsFound(string $line, string $term): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review($line);

        $this->assertNotSame('approve', $result->getDecision(), $line);
        $this->assertContains($term, array_column($result->getMatches(), 'original'), $line);
    }

    public function testNamesSeeThroughSymbolsToo(): void
    {
        $this->assertFalse(DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa')->validateName('p|_|ta')->isValid());
    }

    /** @return array<string,array{string}> */
    public static function ordinaryLines(): array
    {
        return [['¡hola!'], ['1 2 3 4'], ['1 - 2 - 3'], ['a, b, c'], ['son las 10 - 11 - 12'], ['c/u'], ['1/2'],
            ['hola - chao - bye'], ['págs. 14-88'], ['nació en 1488'], ['vamos a la playa :)']];
    }

    #[DataProvider('ordinaryLines')]
    public function testOrdinarySymbolsAreLeftAlone(string $line): void
    {
        $this->assertSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review($line)->getDecision(), $line);
    }
}
