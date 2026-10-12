<?php

namespace Tests\Normalization;

use DefamatoryContentReview\Chat\{ChatLineReviewer, ChatPatternMatcher, EverydayWords};
use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/**
 * Marcas combinantes: el acento escrito aparte («u» + «́», como lo dejan
 * algunos teclados y el copiar y pegar) y las vocales del tailandés, el hindi
 * o el árabe vocalizado, que no son letras (\p{L}) sino marcas (\p{M}).
 */
class CombiningMarksTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    public function testADecomposedAccentDoesNotDodgeTheDictionary(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');

        $this->assertFalse($reviewer->validateName("Pu\u{301}ta")->isValid());
        $this->assertTrue($reviewer->validateFullName("Jose\u{301}", "Garci\u{301}a")->isValid(), 'un nombre real escrito así sigue siendo válido');
        $this->assertSame('reject', ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review("te voy a mata\u{301}r")->getDecision());
    }

    public function testEverydayWordsWithMarksAreMasked(): void
    {
        foreach (['ไม่เลว', 'मूर्ख', 'كَلْب'] as $word) {
            $masked = EverydayWords::mask("x {$word} y", [$word => true]);
            $this->assertSame('x ' . str_repeat(' ', mb_strlen($word)) . ' y', $masked, $word);
        }
    }

    public function testCensoringDoesNotCutAWordThatContinuesWithAMark(): void
    {
        // «सूअरों» («cerdos») empieza por «सूअर» («cerdo») y sigue con una vocal que es marca, no letra.
        // «तू» («tú») lo dirige a alguien: sin eso un animal no se censura (ver TargetContext).
        $result = ChatLineReviewer::create(self::CONFIG_DIR, 'hin')->review('तू सूअर और सूअरों');

        $this->assertSame('तू **** और सूअरों', $result->censored());
    }

    public function testPatternFoldingKeepsTheVowelsOfOtherScripts(): void
    {
        $this->assertSame('मैं तुझे मार दूंगा ', ChatPatternMatcher::fold('मैं तुझे मार दूंगा!'), 'los signos pasan a espacio; las vocales no');
    }
}
