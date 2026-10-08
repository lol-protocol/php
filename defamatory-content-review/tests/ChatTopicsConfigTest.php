<?php

namespace Tests;

use DefamatoryContentReview\ChatTopics;
use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\TopicInflector;
use DefamatoryContentReview\WordList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Integridad de config/chat-topics/*.php, como DictionaryIntegrityTest lo hace con los diccionarios. */
class ChatTopicsConfigTest extends TestCase
{
    private const TYPES = ['sexual', 'belico'];
    private const SEVERITIES = ['low', 'medium', 'high'];

    public static function topicFiles(): array
    {
        $cases = [];
        foreach (glob(dirname(__DIR__) . '/config/chat-topics/*.php') as $file) {
            $cases[basename($file, '.php')] = [$file];
        }

        return $cases;
    }

    #[DataProvider('topicFiles')]
    public function testEntriesAreWellFormed(string $file): void
    {
        $config = require $file;

        $this->assertSame(basename($file, '.php'), $config['meta']['code']);
        foreach ($config['words'] as $category => $entries) {
            $this->assertContains($category, ['sexual', 'belico', 'ambiguous']);
            foreach ($entries as $entry) {
                $this->assertNotSame('', trim($entry['word']), "$category: palabra vacía");
                $this->assertContains($entry['riskType'], self::TYPES, $entry['word']);
                $this->assertContains($entry['severity'], self::SEVERITIES, $entry['word']);
                $this->assertContains($entry['forms'] ?? 'noun', ['noun', 'adj', 'verb'], $entry['word']);
            }
        }
    }

    #[DataProvider('topicFiles')]
    public function testPatternsCompileAndAreWellFormed(string $file): void
    {
        $this->addToAssertionCount(1);
        foreach ((require $file)['patterns'] as $pattern) {
            $this->assertNotFalse(@preg_match('~' . $pattern['pattern'] . '~u', ''), $pattern['pattern']);
            $this->assertSame($pattern['pattern'], strtolower($pattern['pattern']), 'el texto plegado va en minúsculas');
            $this->assertContains($pattern['riskType'], self::TYPES, $pattern['pattern']);
            $this->assertContains($pattern['severity'], self::SEVERITIES, $pattern['pattern']);
        }
    }

    #[DataProvider('topicFiles')]
    public function testNoFormIsInTwoCategories(string $file): void
    {
        $config = require $file;
        $list = new WordList(['words' => []], $config['meta']['code']);
        $seen = [];

        foreach (TopicInflector::expand($config['words'], $config['meta']['code']) as $category => $entries) {
            foreach ($entries as $entry) {
                $key = $list->normalize($entry['word']);
                $this->assertSame($category, $seen[$key] ?? $category, "«{$entry['word']}» está en dos categorías");
                $seen[$key] = $category;
            }
        }
    }

    #[DataProvider('topicFiles')]
    public function testLegitWordsAreOnlyUsedWithCollapseRepeats(string $file): void
    {
        $config = require $file;

        if (!($config['meta']['collapseRepeats'] ?? false)) {
            $this->assertSame([], $config['legit'] ?? [], 'legit sólo se usa con meta.collapseRepeats');
        }
        $this->assertTrue(true);
    }

    #[DataProvider('topicFiles')]
    public function testLegitWordsHaveADoubleLetterAndAreStillNeeded(string $file): void
    {
        $config = require $file;
        $language = $config['meta']['code'];
        $dictionary = DefamatoryContentReviewer::create(dirname($file, 2), $language)->languages()->wordList($language);
        $unexempted = new ChatTopics(['legit' => []] + $config, $language);
        $this->addToAssertionCount(1);

        foreach ($config['legit'] ?? [] as $word) {
            $this->assertSame(strtolower($word), $word, 'en minúsculas');
            $this->assertMatchesRegularExpression('/(\p{L})\1/u', $word, "«{$word}» no tiene letra doble: no hace falta");
            $flagged = $unexempted->scan($dictionary, $word) !== [] || $unexempted->find($word) !== [];
            $this->assertTrue($flagged, "«{$word}» ya no se marca al leerla reducida: quítala de legit");
        }
    }
}
