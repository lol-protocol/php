<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Una entrada `'ambiguous' => true` de un diccionario de idioma es casi
 * siempre la palabra cotidiana («huevos», «un vaso»): el chat la ignora,
 * pero validateName() sigue marcándola.
 */
class ChatAmbiguousWordsTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string}> */
    public static function everydayLines(): array
    {
        return [
            'ruso: huevos y pan' => ['rus', 'Купи яйца и хлеб'],
            'tagalo: leche flan' => ['tgl', 'Gusto ko ng leche flan'],
            'hebreo: un vaso de agua' => ['heb', 'כוס מים'],
            'inglés: butt of the joke' => ['eng', 'He was the butt of the joke'],
        ];
    }

    #[DataProvider('everydayLines')]
    public function testEverydayLineIsApproved(string $lang, string $line): void
    {
        $this->assertSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, $lang)->review($line)->getDecision());
    }

    public function testRealInsultNextToAnAmbiguousWordStillRejects(): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, 'rus')->review('Купи яйца, мудак');

        $this->assertSame('reject', $result->getDecision());
    }

    public function testNamesStillFlagTheAmbiguousWord(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'rus');

        $this->assertFalse($reviewer->validateName('яйца')->isValid());
    }
}
