<?php

namespace Tests\Normalization;

use DefamatoryContentReview\Chat\Inflection\TopicInflector;
use DefamatoryContentReview\Normalization\EvasionCleaner;
use DefamatoryContentReview\Normalization\PlainLatin;
use PHPUnit\Framework\TestCase;

/** El atajo de EvasionCleaner::clean() para PlainLatin da lo mismo que el camino largo (deepClean). */
class PlainLatinTest extends TestCase
{
    private const CONFIG = __DIR__ . '/../../config';

    public function testEveryLetterIsUnchangedByTheDeepPathAloneAndNextToAnA(): void
    {
        foreach (mb_str_split(PlainLatin::LETTERS) as $letter) {
            foreach ([$letter, "a$letter", "{$letter}a", "$letter$letter"] as $probe) {
                foreach (['spa', 'tur', 'rus'] as $language) {
                    $this->assertSame($probe, EvasionCleaner::deepClean($probe, $language), "$letter ($language)");
                }
            }
        }
    }

    public function testShortcutEqualsTheDeepPathOnTheWholeVocabulary(): void
    {
        $checked = 0;
        foreach (glob(self::CONFIG . '/languages/*.php') ?: [] as $file) {
            $code = basename($file, '.php');
            $words = (require $file)['words'];
            foreach (TopicInflector::expand($words, $code, true) as $entries) {
                foreach ($entries as $entry) {
                    $word = (string) $entry['word'];
                    if (PlainLatin::matches($word)) {
                        $this->assertSame(EvasionCleaner::deepClean($word, $code), EvasionCleaner::clean($word, $code), $word);
                        $checked++;
                    }
                }
            }
        }
        $this->assertGreaterThan(5000, $checked);
    }

    public function testShortcutEqualsTheDeepPathOnRandomStrings(): void
    {
        mt_srand(11);
        $alphabet = array_merge(range('a', 'z'), range('A', 'Z'), mb_str_split(PlainLatin::LETTERS));
        for ($i = 0; $i < 2000; $i++) {
            $text = '';
            for ($n = mt_rand(1, 12); $n > 0; $n--) {
                $text .= $alphabet[mt_rand(0, count($alphabet) - 1)];
            }
            $this->assertTrue(PlainLatin::matches($text), $text);
            $this->assertSame(EvasionCleaner::deepClean($text, 'tur'), EvasionCleaner::clean($text, 'tur'), $text);
        }
    }

    public function testAnythingElseKeepsTakingTheDeepPath(): void
    {
        foreach (['İbne', 'ǆ', 'ŉa', 'pu ta', "pu\u{200B}ta", 'çok!', 'ç̶o̶k', 'ｐｕｔａ', 'рuta', 'café1'] as $text) {
            $this->assertFalse(PlainLatin::matches($text), $text);
        }
        $this->assertSame('puta', EvasionCleaner::clean("pu\u{200B}ta", 'spa'));
        $this->assertSame('puta', EvasionCleaner::clean('ｐｕｔａ', 'spa'));
        $this->assertSame('çok', EvasionCleaner::clean('çok', 'tur'));
    }
}
