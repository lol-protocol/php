<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\LanguageRegistry;
use PHPUnit\Framework\TestCase;

class LanguageKinshipTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    private LanguageRegistry $registry;

    protected function setUp(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');
        $this->registry = $reviewer->languages()->registry();
    }

    public function testAffinityIsSymmetric(): void
    {
        $this->assertSame(
            $this->registry->getAffinity('spa', 'por'),
            $this->registry->getAffinity('por', 'spa')
        );
    }

    public function testAffinityWithItselfIsOne(): void
    {
        $this->assertSame(1.0, $this->registry->getAffinity('rus', 'rus'));
    }

    public function testUnrelatedLanguagesHaveZeroAffinity(): void
    {
        $this->assertSame(0.0, $this->registry->getAffinity('spa', 'jpn'));
    }

    public function testRelatedLanguagesAreSortedByAffinity(): void
    {
        $related = $this->registry->getRelated('spa');

        $this->assertArrayHasKey('por', $related);
        $this->assertSame('por', array_key_first($related), 'Portugués es el más cercano al español.');
        $this->assertSame($related, array_slice($related, 0, count($related), true));
        $this->assertGreaterThanOrEqual(array_values($related)[1], $related['por']);
    }

    public function testRussianAndUkrainianAreAssociated(): void
    {
        $this->assertArrayHasKey('ukr', $this->registry->getRelated('rus'));
        $this->assertArrayHasKey('rus', $this->registry->getRelated('ukr'));
    }

    public function testThresholdNarrowsTheRelatedSet(): void
    {
        $wide = $this->registry->getRelated('spa', 0.60);
        $narrow = $this->registry->getRelated('spa', 0.85);

        $this->assertGreaterThan(count($narrow), count($wide));
        $this->assertArrayHasKey('por', $narrow);
        $this->assertArrayNotHasKey('ron', $narrow);
    }

    public function testValidationSetIncludesTheLanguageItself(): void
    {
        $set = $this->registry->getValidationSet('spa');

        $this->assertSame(1.0, $set['spa']);
        $this->assertArrayHasKey('por', $set);
    }

    public function testFamilyMembersExcludeTheLanguageItself(): void
    {
        $members = $this->registry->getFamilyMembers('ces');

        $this->assertContains('slk', $members);
        $this->assertNotContains('ces', $members);
    }
}
