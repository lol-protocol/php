<?php

namespace Tests;

use DefamatoryContentReview\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ChatLineReviewerTest extends TestCase
{
    private static function reviewer(string $language = 'spa'): ChatLineReviewer
    {
        return ChatLineReviewer::create(__DIR__ . '/../config', $language);
    }

    public static function flaggedLines(): array
    {
        return [
            'difamatorio' => ['spa', 'Eres un mentiroso y un idiota', 'difamatorio'],
            'sexual' => ['spa', 'Mándame nudes ahora', 'sexual'],
            'bélico' => ['spa', 'Hay que bombardear ese pueblo', 'belico'],
            'sexual eng' => ['eng', 'send me nudes', 'sexual'],
            'bélico eng' => ['eng', 'that was a genocide', 'belico'],
        ];
    }

    #[DataProvider('flaggedLines')]
    public function testDetectsEachContentType(string $language, string $line, string $type): void
    {
        $result = self::reviewer($language)->review($line);

        $this->assertTrue($result->hasContentType($type), json_encode($result->toArray()));
        $this->assertTrue($result->shouldCensor());
    }

    public function testBurlescoTermsAreReportedAsBurlesco(): void
    {
        $this->assertTrue(self::reviewer()->review('Callate vejestorio')->hasContentType('burlesco'));
    }

    public function testCleanLineIsApproved(): void
    {
        $result = self::reviewer()->review('Hola, ¿cómo estás? Mañana viene la abuela');

        $this->assertSame('approve', $result->getDecision());
        $this->assertSame([], $result->getContentTypes());
        $this->assertFalse($result->shouldCensor());
    }

    public function testMentioningWarIsReportedButNotCensored(): void
    {
        $result = self::reviewer()->review('Mi abuelo luchó en la guerra');

        $this->assertTrue($result->hasContentType('belico'));
        $this->assertSame('approve', $result->getDecision());
        $this->assertSame('Mi abuelo luchó en la guerra', $result->censored());
    }

    public function testSeparatorsAndLeetDoNotEvadeTheFilter(): void
    {
        $this->assertTrue(self::reviewer()->review('p0rn0 gratis')->hasContentType('sexual'));
        $this->assertTrue(self::reviewer()->review('vamos a f-o-l-l-a-r')->hasContentType('sexual'));
    }

    public function testCensoredLineMasksTheOffendingWords(): void
    {
        $this->assertSame('Eres un ******', self::reviewer()->review('Eres un idiota')->censored());
    }

    public function testLanguageWithoutTopicListStillDetectsInsults(): void
    {
        $result = self::reviewer('ita')->review('sei un idiota');

        $this->assertTrue($result->hasContentType('difamatorio'));
    }

    public function testAmbiguousWordsOnlyCountNextToSomethingFirm(): void
    {
        $this->assertSame('approve', self::reviewer()->review('vamos a coger el bus')->getDecision());
        $this->assertTrue(self::reviewer()->review('quiero coger, mándame nudes')->hasContentType('sexual'));
    }

    public function testATermThatIsAlsoASurnameGoesToReviewInsteadOfBlocking(): void
    {
        $result = self::reviewer('eng')->review('Hi, I am John Savage');

        $this->assertSame('review', $result->getDecision());
        $this->assertSame(['difamatorio'], $result->getContentTypes());
    }
}
