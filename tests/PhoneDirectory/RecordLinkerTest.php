<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\PhoneDirectoryEntry;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\RecordLinker;

class RecordLinkerTest extends TestCase
{
    private function entry(string $name, string $street, string $directory, ?string $phone = null, string $country = 'US'): PhoneDirectoryEntry
    {
        return new PhoneDirectoryEntry(
            fullName: $name,
            countryCode: $country,
            street: $street,
            phoneNumber: $phone,
            sourceDirectoryId: $directory
        );
    }

    public function testLinksSamePersonAfterMoveOrderedByYear(): void
    {
        $later = $this->entry('SMITH, John', '40 Elm Road', 'us_1950_comprehensive');
        $earlier = $this->entry('SMITH, John', '12 Oak Street', 'us_1915_national');

        $links = (new RecordLinker())->link([$later, $earlier]);

        $this->assertCount(1, $links);
        $this->assertSame($earlier, $links[0]->earlier);
        $this->assertSame($later, $links[0]->later);
        $this->assertSame(0.6, $links[0]->score);
        $this->assertEquals(['same given name', 'same surname'], $links[0]->evidence);
    }

    public function testInitialAndAbbreviatedSameAddress(): void
    {
        $links = (new RecordLinker())->link([
            $this->entry('SMITH, J.', '12 Oak St.', 'us_1915_national'),
            $this->entry('SMITH, John', '12 Oak Street', 'us_1950_comprehensive'),
        ]);

        $this->assertCount(1, $links);
        $this->assertSame(0.7, $links[0]->score);
        $this->assertEquals(['matching initial', 'same surname', 'same address'], $links[0]->evidence);
    }

    public function testSimilarSurnameWithSamePhone(): void
    {
        $links = (new RecordLinker())->link([
            $this->entry('SMYTH, John', '12 Oak Street', 'us_1915_national', '555-123-4567'),
            $this->entry('SMITH, John', '8 Pine Road', 'us_1950_comprehensive', '(555) 123 4567'),
        ]);

        $this->assertCount(1, $links);
        $this->assertSame(0.6, $links[0]->score);
        $this->assertEquals(['same given name', 'similar-sounding surname', 'same phone number'], $links[0]->evidence);
    }

    public function testDifferentGivenNamesAreNotLinked(): void
    {
        $links = (new RecordLinker())->link([
            $this->entry('SMITH, John', '12 Oak Street', 'us_1915_national'),
            $this->entry('SMITH, Robert', '12 Oak Street', 'us_1950_comprehensive'),
        ]);

        $this->assertSame([], $links);
    }

    public function testSameDirectoryOrCountryMismatchIsNotLinked(): void
    {
        $links = (new RecordLinker())->link([
            $this->entry('SMITH, John', '12 Oak Street', 'us_1915_national'),
            $this->entry('SMITH, John', '40 Elm Road', 'us_1915_national'),
            $this->entry('SMITH, John', '12 Oak Street', 'uk_1930_comprehensive', null, 'GB'),
        ]);

        $this->assertSame([], $links);
    }

    public function testMinimumScoreIsConfigurable(): void
    {
        $entries = [
            $this->entry('SMYTH, J.', '12 Oak Street', 'us_1915_national'),
            $this->entry('SMITH, John', '8 Pine Road', 'us_1950_comprehensive'),
        ];

        $this->assertSame([], (new RecordLinker())->link($entries));
        $this->assertCount(1, (new RecordLinker(null, 0.3))->link($entries));
    }

    public function testLinksAreSortedByScore(): void
    {
        $links = (new RecordLinker())->link([
            $this->entry('SMITH, John', '12 Oak Street', 'us_1878_ny'),
            $this->entry('SMITH, John', '40 Elm Road', 'us_1915_national'),
            $this->entry('SMITH, John', '12 Oak Street', 'us_1950_comprehensive'),
        ]);

        $this->assertCount(3, $links);
        $this->assertSame(0.85, $links[0]->score);
        $this->assertEquals('us_1878_ny', $links[0]->earlier->getSourceDirectoryId());
        $this->assertEquals('us_1950_comprehensive', $links[0]->later->getSourceDirectoryId());
    }

    public function testLinksEntriesLoadedPerDirectoryFromDatabase(): void
    {
        $database = new PhoneDirectoryPDODatabase();
        $database->createTable();
        $database->insert($this->entry('GARCÍA LÓPEZ, José', 'Calle Mayor 12', 'es_1930_madrid', null, 'ES'));
        $database->insert($this->entry('GARCIA LOPEZ, Jose', 'C. Mayor 12', 'es_1975_national', null, 'ES'));
        $database->insert($this->entry('RUIZ, Ana', 'Calle Sol 1', 'es_1975_national', null, 'ES'));

        $entries = array_merge(
            $database->findBySourceDirectory('es_1930_madrid'),
            $database->findBySourceDirectory('es_1975_national')
        );
        $links = (new RecordLinker())->link($entries);

        $this->assertCount(1, $links);
        $this->assertSame(0.85, $links[0]->score);
        $this->assertEquals('es_1930_madrid', $links[0]->earlier->getSourceDirectoryId());
    }
}
