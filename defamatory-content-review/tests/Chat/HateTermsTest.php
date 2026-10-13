<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Siglas y consignas de odio, también camufladas («N.A.Z.I», «N.4.Z.|»). «Nazi»
 * sólo cuenta dirigido a alguien: la historia familiar habla de los nazis.
 */
class HateTermsTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string,string}> idioma, línea, decisión */
    public static function lines(): array
    {
        return [
            'sigla con puntos' => ['spa', 'N.A.Z.I', 'review'],
            'sigla hacker' => ['spa', 'N.4.Z.|', 'review'],
            'leet dirigido' => ['spa', 'eres un n4z1', 'review'],
            'letras sueltas' => ['spa', 'N 4 Z 1', 'review'],
            'n dibujada' => ['spa', '|\\|4z1', 'review'],
            'facho' => ['spa', 'facho culiao', 'review'],
            'kkk' => ['spa', 'k.k.k', 'review'],
            'acab' => ['spa', 'A.C.A.B', 'review'],
            'consigna' => ['spa', 'sieg heil', 'reject'],
            'consigna en leet' => ['spa', 'S1eg H3il', 'reject'],
            'código' => ['spa', '14/88', 'review'],
            'alemán' => ['deu', 'Heil Hitler', 'reject'],
            'inglés' => ['eng', 'white power', 'reject'],
            'historia' => ['spa', 'mi abuelo luchó contra los nazis', 'approve'],
            'historia en alemán' => ['deu', 'Die Nazis besetzten Polen', 'approve'],
            'historia en inglés' => ['eng', 'my grandfather fought the nazis', 'approve'],
        ];
    }

    #[DataProvider('lines')]
    public function testDecision(string $language, string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), $line);
    }
}
