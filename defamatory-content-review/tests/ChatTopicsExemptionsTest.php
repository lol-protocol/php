<?php

namespace Tests;

use DefamatoryContentReview\ChatTopics;
use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\WordList;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

/**
 * Las excepciones de config/chat-topics/*.php (`legit`, `everyday`) tienen
 * que seguir haciendo falta: si una palabra ya no se marca sin la excepción,
 * sobra y hay que quitarla.
 */
class ChatTopicsExemptionsTest extends TestCase
{
    #[DataProviderExternal(ChatTopicsConfigTest::class, 'topicFiles')]
    public function testLegitWordsHaveADoubleLetterAndAreStillNeeded(string $file): void
    {
        $config = require $file;
        $unexempted = new ChatTopics(['legit' => []] + $config, $config['meta']['code']);
        $dictionary = $this->dictionary($file, $config['meta']['code']);
        $this->addToAssertionCount(1);

        foreach ($config['legit'] ?? [] as $word) {
            $this->assertSame(strtolower($word), $word, 'en minúsculas');
            $this->assertMatchesRegularExpression('/(\p{L})\1/u', $word, "«{$word}» no tiene letra doble: no hace falta");
            $flagged = $unexempted->scan($dictionary, $word) !== [] || $unexempted->find($word) !== [];
            $this->assertTrue($flagged, "«{$word}» ya no se marca al leerla reducida: quítala de legit");
        }
    }

    #[DataProviderExternal(ChatTopicsConfigTest::class, 'topicFiles')]
    public function testEverydayWordsAreLowercaseAndStillNeeded(string $file): void
    {
        $config = require $file;
        $unmasked = new ChatTopics(['everyday' => []] + $config, $config['meta']['code']);
        $dictionary = $this->dictionary($file, $config['meta']['code']);
        $this->addToAssertionCount(1);

        foreach ($config['everyday'] ?? [] as $word) {
            $this->assertSame(mb_strtolower($word), $word, 'en minúsculas');
            $flagged = $unmasked->scan($dictionary, $word) !== [] || $unmasked->find($word) !== [];
            $this->assertTrue($flagged, "«{$word}» ya no se marca aunque no esté en everyday: quítala");
        }
    }

    private function dictionary(string $file, string $language): WordList
    {
        return DefamatoryContentReviewer::create(dirname($file, 2), $language)->languages()->wordList($language);
    }
}
