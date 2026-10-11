<?php

namespace Tests;

use DefamatoryContentReview\RepeatedLetters;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RepeatedLettersTest extends TestCase
{
    /** Un diccionario de juguete: con doble «l» y «ss» legítimas, y «calé» como el «calle» de la calle. */
    private function searcher(array $legit = []): callable
    {
        $words = ['puta' => 1, 'follar' => 1, 'pussy' => 1, 'hijo de puta' => 1, 'cale' => 1, 'matar' => 1];

        return RepeatedLetters::searcher(fn(string $phrase): ?array => isset($words[strtolower($phrase)]) ? ['original' => $phrase] : null, $legit);
    }

    public static function readings(): array
    {
        return [
            'tres o más: se alarga' => ['puuuuta', 'elongated'],
            'tres o más en la doble legítima' => ['pusssy', 'elongated'],
            'sólo dobles' => ['puuta', 'doubled'],
            'dobles en todas partes' => ['ppuuttaa', 'doubled'],
            'conserva la doble legítima' => ['follarr', 'doubled'],
            'varias rachas, de largo distinto' => ['ffolllarrr', 'elongated'],
            'en una frase' => ['hijo de puuuta', 'elongated'],
            'mayúsculas' => ['PUUUTA', 'elongated'],
        ];
    }

    #[DataProvider('readings')]
    public function testReadsTheWordWithItsRunsReduced(string $written, string $repeat): void
    {
        $entry = ($this->searcher())($written);

        $this->assertNotNull($entry, $written);
        $this->assertSame($repeat, $entry['repeat']);
    }

    public function testAnExactHitIsReturnedUntouched(): void
    {
        $this->assertSame(['original' => 'pussy'], ($this->searcher())('pussy'));
    }

    public function testNeverAddsLettersSoALegitimateWordWithoutTheDoubleStaysClean(): void
    {
        $search = RepeatedLetters::searcher(fn(string $phrase): ?array => $phrase === 'perra' ? ['original' => 'perra'] : null, []);

        $this->assertNull($search('pera'));
        $this->assertNotNull($search('perrra'));
    }

    public function testLegitWordsAreNeverReadReduced(): void
    {
        $this->assertNotNull(($this->searcher())('calle'));
        $this->assertNull(($this->searcher([RepeatedLetters::key('Calle') => true]))('calle'));
        $this->assertNotNull(($this->searcher([RepeatedLetters::key('Calle') => true]))('calllle'), 'tres «l» no son la palabra legítima');
    }

    public function testManyRunsFallBackToAllToOneOrAllToTwo(): void
    {
        $this->assertNotNull(($this->searcher())('ppuuuutttaaaaaaa'), 'cuatro rachas: se prueban todas las combinaciones');
        $search = RepeatedLetters::searcher(fn(string $p): ?array => $p === 'abcdefg' ? ['original' => $p] : null, []);
        $this->assertNotNull($search('aabbccddeeffgg'), 'siete rachas: «todas a 1»');
    }

    public static function tolerantPatterns(): array
    {
        return [
            'literales' => ['te\s+voy', 't+e+\s+v+o+y+'],
            'escapes intactos' => ['\bmatar\b', '\bm+a+t+a+r+\b'],
            'alternativas' => ['(ll|will)', '(l+l+|w+i+l+l+)'],
            'letra opcional' => ['bombas?', 'b+o+m+b+a+s?'],
            'clase de caracteres' => ['[abc]x', '[abc]x+'],
            'lookahead' => ['a(?!\s+b)', 'a+(?!\s+b+)'],
        ];
    }

    #[DataProvider('tolerantPatterns')]
    public function testTolerantPatternsAcceptRepeatedLetters(string $pattern, string $expected): void
    {
        $this->assertSame($expected, RepeatedLetters::tolerant($pattern));
    }

    public function testATolerantPatternStillMatchesTheOriginalAndTheRepeatedForm(): void
    {
        $regex = '~' . RepeatedLetters::tolerant('\bte\s+voy\s+a\s+matar\b') . '~';

        $this->assertSame(1, preg_match($regex, 'te voy a matar'));
        $this->assertSame(1, preg_match($regex, 'tee voy a mataaaar'));
        $this->assertSame(0, preg_match($regex, 'te voy a llevar'));
    }
}
