<?php

namespace Tests\Dictionary;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Dictionary\WordSegmenter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/** Japonés, cantonés y tailandés no separan palabras con espacios: WordSegmenter las parte con ICU. */
#[RequiresPhpExtension('intl')]
class WordSegmenterTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    public function testSplitsUnspacedTextIntoWordsWithTheirByteOffsets(): void
    {
        $this->assertSame([['お前', 10], ['は', 16], ['バカ', 19], ['だ', 25]], WordSegmenter::split(['お前はバカだ', 10]));
    }

    public function testLeavesSpacedScriptsAlone(): void
    {
        $this->assertSame([["l'idiot", 0]], WordSegmenter::split(["l'idiot", 0]));
        $this->assertSame([['p@ta', 3]], WordSegmenter::split(['p@ta', 3]));
    }

    /** @return array<string,array{string,string,string,string}> idioma, línea, decisión, línea censurada */
    public static function sentences(): array
    {
        return [
            'insulto dentro de la frase (jpn)' => ['jpn', 'お前はバカだ', 'review', 'お前は**だ'],
            'palabra que contiene el insulto (jpn)' => ['jpn', 'バカンスに行く', 'approve', 'バカンスに行く'],
            'cotidiana con kanji de animal (jpn)' => ['jpn', '牛丼を食べる', 'approve', '牛丼を食べる'],
            'amenaza (yue)' => ['yue', '我要殺死你', 'reject', '我要***'],
            'amenaza (tha)' => ['tha', 'ฉันจะฆ่าคุณ', 'reject', 'ฉัน********'],
            'insulto dentro de la frase (tha)' => ['tha', 'ไอ้เลวเอ๊ย', 'reject', '******เอ๊ย'],
            '«no está mal» (tha)' => ['tha', 'ก็ไม่เลว', 'approve', 'ก็ไม่เลว'],
            '«adiós» contiene «burro» (tha)' => ['tha', 'ลาก่อนนะ', 'approve', 'ลาก่อนนะ'],
        ];
    }

    #[DataProvider('sentences')]
    public function testFindsWordsInsideSentencesAndCensorsOnlyThem(string $language, string $line, string $decision, string $censored): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line);

        $this->assertSame($decision, $result->getDecision(), $line);
        $this->assertSame($censored, $result->censored(), $line);
    }
}
