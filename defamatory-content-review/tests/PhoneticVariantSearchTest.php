<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/** Candidatos de fusión e índice fonético a nivel de WordList. */
class PhoneticVariantSearchTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private DefamatoryContentReviewer $reviewer;

    protected function setUp(): void
    {
        $this->reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
    }

    public function testFusionCandidatesRespectMinimumLength(): void
    {
        $wordList = $this->reviewer->languages()->wordList('spa');

        foreach ($wordList->getFusionCandidates(5) as $candidate) {
            $this->assertGreaterThanOrEqual(5, mb_strlen($candidate['phonetic']));
        }
    }

    public function testSearchPhoneticExactFindsSpellingVariant(): void
    {
        $wordList = $this->reviewer->languages()->wordList('spa');

        // "Serda" con s: mismo sonido que "Cerda" (c ante e -> s), otra grafía.
        $this->assertNotNull($wordList->searchPhoneticExact('serda'));
        $this->assertSame(
            $wordList->searchPhoneticExact('serda')['original'],
            $wordList->search('cerda')['original']
        );
    }
}
