<?php

namespace Tests\Chat\Inflection;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Posesivos del turco y los casos que se les añaden (TurkishInflection), medidos contra UniMorph. */
class TurkishPossessivesTest extends TestCase
{
    /** @return array<string,array{string,list<string>}> lema, formas que deben estar */
    public static function nouns(): array
    {
        return [
            'tras consonante, con sonorización' => ['kitap', ['kitabım', 'kitabın', 'kitabı', 'kitabımız', 'kitabınız', 'kitapları']],
            'casos tras 1.ª y 2.ª persona' => ['kitap', ['kitabımı', 'kitabıma', 'kitabımda', 'kitabımdan', 'kitabımın', 'kitabınla']],
            'casos tras 3.ª persona, con «n» de unión' => ['kitap', ['kitabını', 'kitabına', 'kitabında', 'kitabından', 'kitabının', 'kitabıyla']],
            'posesivos del plural' => ['kitap', ['kitaplarım', 'kitaplarımız', 'kitaplarını', 'kitaplarımdan']],
            'tras vocal' => ['araba', ['arabam', 'araban', 'arabası', 'arabamız', 'arabasını', 'arabasına']],
            'armonía anterior' => ['göt', ['götüm', 'götümüz', 'götünü', 'götünden']],
            'sin sonorizar en monosílabas' => ['top', ['topum', 'topu']],
        ];
    }

    /** @param list<string> $expected */
    #[DataProvider('nouns')]
    public function testPossessiveForms(string $lemma, array $expected): void
    {
        $forms = TopicInflector::forms('tur', $lemma, 'noun');

        $this->assertSame([], array_diff($expected, $forms), $lemma);
        $this->assertSame($lemma, $forms[0]);
        $this->assertSame($forms, array_values(array_unique($forms)));
    }

    public function testEverydayWordsThatCollideWithPossessivesPassThrough(): void
    {
        $chat = ChatLineReviewer::create(__DIR__ . '/../../../config', 'tur');

        foreach (['katılım', 'kadınsın', 'kadınsınız'] as $word) {
            $this->assertSame('approve', $chat->review("bu $word iyi")->getDecision(), $word);
        }
        $this->assertSame('review', $chat->review('bu götüm iyi')->getDecision());
    }

    public function testVerbsStayLiteral(): void
    {
        $this->assertSame(['gelmek'], TopicInflector::forms('tur', 'gelmek', 'verb'));
    }
}
