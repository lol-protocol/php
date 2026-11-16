<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Parser\PhoneDirectoryParser;

class PhoneDirectoryParserTest extends TestCase
{
    private PhoneDirectoryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new PhoneDirectoryParser();
    }

    public function testParseSimpleEntry(): void
    {
        $content = <<<TXT
        SMITH, John
        123 Main Street
        555-123-4567
        TXT;

        $entries = $this->parser->parseContent($content);

        $this->assertCount(1, $entries);
        $this->assertEquals('Smith, John', $entries[0]->getFormattedName());
        $this->assertStringContainsString('Main Street', $entries[0]->getStreet());
    }

    public function testRecordsWithoutBlankLineBetweenThem(): void
    {
        $content = "HALL, Ruth\n3 Main Street\n555-123-4567\nLEE, Ken\n8 Oak Lane\n555-987-6543";

        $entries = $this->parser->parseContent($content);

        $this->assertCount(2, $entries);
        $this->assertSame('8 Oak Lane', $entries[1]->getStreet());
        $this->assertSame('555-987-6543', $entries[1]->getPhoneNumber());
        $this->assertSame(4, $entries[1]->getSourceLine());
    }

    public function testDwellingLineIsTheStreetAfterTheName(): void
    {
        $entries = $this->parser->parseContent("TAYLOR, Jim\nApt 4 Building 7\n555-123-4567");

        $this->assertCount(1, $entries);
        $this->assertSame('Apt 4 Building 7', $entries[0]->getStreet());
    }

    public function testPhoneOnTheNameLineAndMisreadDigits(): void
    {
        $entries = $this->parser->parseContent("SMITH, John ........ 555-l23-4567\n12 Oak Street");

        $this->assertCount(1, $entries);
        $this->assertSame('Smith, John', $entries[0]->getFormattedName());
        $this->assertSame('555-123-4567', $entries[0]->getPhoneNumber());
    }

    public function testCityLineStaysInItsRecord(): void
    {
        $entries = $this->parser->parseContent("SMITH, John\n12 Oak Street\nSpringfield\n555-123-4567");

        $this->assertCount(1, $entries);
        $this->assertSame('555-123-4567', $entries[0]->getPhoneNumber());
    }

    public function testParseMultipleEntries(): void
    {
        $content = <<<TXT
        ANDERSON, John
        123 Main Street
        555-123-4567

        BAKER, Sarah
        456 Oak Avenue
        555-234-5678

        GARCIA, María
        789 Maple Road
        555-345-6789
        TXT;

        $entries = $this->parser->parseContent($content);

        $this->assertCount(3, $entries);
        $this->assertEquals('Anderson, John', $entries[0]->getFormattedName());
        $this->assertEquals('Baker, Sarah', $entries[1]->getFormattedName());
        $this->assertEquals('Garcia, María', $entries[2]->getFormattedName());
    }

    public function testParseWithSeparators(): void
    {
        $content = <<<TXT
        SMITH, John
        123 Main Street
        555-123-4567
        =========================================

        JOHNSON, Mary
        456 Oak Avenue
        555-234-5678
        TXT;

        $entries = $this->parser->parseContent($content);

        $this->assertCount(2, $entries);
    }

    public function testExtractStreetAddress(): void
    {
        $content = <<<TXT
        WILLIAMS, Robert
        321 Pine Street
        555-456-7890
        TXT;

        $entries = $this->parser->parseContent($content);

        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertStringContainsString('Pine', $entry->getStreet());
        $this->assertStringContainsString('Street', $entry->getStreet());
    }

    public function testExtractPhoneNumber(): void
    {
        $content = <<<TXT
        DAVIS, Jennifer
        987 Elm Avenue
        555-789-0123
        TXT;

        $entries = $this->parser->parseContent($content);

        $this->assertCount(1, $entries);
        $this->assertNotNull($entries[0]->getPhoneNumber());
        $this->assertStringContainsString('555', $entries[0]->getPhoneNumber());
    }

    public function testParseEntriesWithoutPhone(): void
    {
        $content = <<<TXT
        TAYLOR, George
        654 Birch Road
        TXT;

        $entries = $this->parser->parseContent($content);

        $this->assertCount(1, $entries);
        $this->assertNull($entries[0]->getPhoneNumber());
    }

    public function testHandleInvalidEntries(): void
    {
        $content = <<<TXT
        ANDERSON, John
        123 Main Street
        555-123-4567

        Invalid Entry Without Street

        BAKER, Sarah
        456 Oak Avenue
        555-234-5678
        TXT;

        $entries = $this->parser->parseContent($content);

        $this->assertCount(2, $entries);
        $this->assertCount(1, $this->parser->getErrors());
    }

    public function testParseFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phone_');
        $content = <<<TXT
        MILLER, Betty
        246 Spruce Lane
        555-012-3456

        WILSON, Charles
        913 Oak Street
        555-123-4567
        TXT;

        file_put_contents($tempFile, $content);

        $entries = $this->parser->parseFile($tempFile);

        $this->assertCount(2, $entries);
        unlink($tempFile);
    }

    public function testParseFileInBatchesGivesTheSameEntriesAsParseFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phone_batches_');
        $records = [];
        for ($i = 1; $i <= 7; $i++) {
            $records[] = "PERSON{$i}, John\n{$i} Main Street\n555-123-" . sprintf('%04d', $i);
        }
        file_put_contents($tempFile, implode("\n\n", $records));

        try {
            $whole = (new PhoneDirectoryParser())->parseFile($tempFile);

            $parser = new PhoneDirectoryParser();
            $batches = iterator_to_array($parser->parseFileInBatches($tempFile, 3), false);
        } finally {
            unlink($tempFile);
        }

        $this->assertSame([3, 3, 1], array_map('count', $batches));
        $this->assertEquals(
            array_map(fn($e) => $e->getRawName(), $whole),
            array_map(fn($e) => $e->getRawName(), array_merge(...$batches))
        );
        $this->assertSame([], $parser->getEntries(), 'what was handed over is not kept');
    }

    public function testParseFileInBatchesChecksItsArgumentsWhenCalled(): void
    {
        foreach ([
            [fn() => $this->parser->parseFileInBatches('/nonexistent/file.txt'), \RuntimeException::class],
            [fn() => $this->parser->parseFileInBatches(__FILE__, 0), \InvalidArgumentException::class],
        ] as [$call, $exception]) {
            try {
                $call();
                $this->fail("{$exception} expected before the first batch is read");
            } catch (\Throwable $e) {
                $this->assertInstanceOf($exception, $e);
            }
        }
    }

    public function testParseFileNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');
        $this->parser->parseFile('/nonexistent/file.txt');
    }

    public function testResetParser(): void
    {
        $content = <<<TXT
        SMITH, John
        123 Main Street
        555-123-4567
        TXT;

        $this->parser->parseContent($content);
        $this->assertCount(1, $this->parser->getEntries());

        $this->parser->reset();
        $this->assertCount(0, $this->parser->getEntries());
    }

    public function testGetEntriesCount(): void
    {
        $content = <<<TXT
        ANDERSON, John
        123 Main Street
        555-123-4567

        BAKER, Sarah
        456 Oak Avenue
        555-234-5678
        TXT;

        $this->parser->parseContent($content);
        $this->assertEquals(2, $this->parser->getEntriesCount());
    }

    public function testGetErrorsCount(): void
    {
        $content = <<<TXT
        ANDERSON, John
        123 Main Street
        555-123-4567

        Invalid Entry Without Address

        BAKER, Sarah
        456 Oak Avenue
        555-234-5678
        TXT;

        $this->parser->parseContent($content);
        $this->assertEquals(1, $this->parser->getErrorsCount());
    }

    public function testSurnamesContainingStreetAbbreviationsAreNames(): void
    {
        $content = <<<TXT
        JOHNSTON, Robert
        12 Oak Avenue
        555-111-2222

        RICHARDS, Ann
        9 Elm Road
        555-333-4444

        CHRISTENSEN, Lars
        4 Birch Lane
        555-555-6666
        TXT;

        $entries = $this->parser->parseContent($content);

        $this->assertCount(3, $entries);
        $this->assertCount(0, $this->parser->getErrors());
        $this->assertEquals('Johnston, Robert', $entries[0]->getFormattedName());
        $this->assertEquals('12 Oak Avenue', $entries[0]->getStreet());
        $this->assertEquals('Richards, Ann', $entries[1]->getFormattedName());
        $this->assertEquals('Christensen, Lars', $entries[2]->getFormattedName());
    }

    public function testAbbreviatedStreetTypeIsDetected(): void
    {
        $entries = $this->parser->parseContent("MOORE, Paul\nOak St.\n555-111-2222");

        $this->assertCount(1, $entries);
        $this->assertEquals('Oak St.', $entries[0]->getStreet());
    }

    public function testAccentedUppercaseNameIsNormalized(): void
    {
        $entries = $this->parser->parseContent("GARCÍA, JOSÉ\n789 Maple Road\n555-345-6789");

        $this->assertCount(1, $entries);
        $this->assertEquals('García, José', $entries[0]->getFormattedName());
    }

    public function testSeparatorLinesOfMixedCharacters(): void
    {
        $content = "SMITH, John\n123 Main Street\n- - - - -\nJONES, Ann\n456 Oak Avenue\n***\nBROWN, Tom\n7 Pine Road";

        $this->assertCount(3, $this->parser->parseContent($content));
    }

    public function testLineEndingWithEqualsIsNotSeparator(): void
    {
        $entries = $this->parser->parseContent("RIVERA, Ana\n45 Pine Street ==\n555-000-1111");

        $this->assertCount(1, $entries);
        $this->assertStringContainsString('Pine Street', $entries[0]->getStreet());
    }

    public function testParseVariousPhoneFormats(): void
    {
        $content = <<<TXT
        SMITH, John
        123 Main Street
        555-123-4567

        JOHNSON, Mary
        456 Oak Avenue
        5551234567

        WILLIAMS, Robert
        789 Pine Road
        555.345.6789
        TXT;

        $entries = $this->parser->parseContent($content);

        $this->assertCount(3, $entries);
        $this->assertNotNull($entries[0]->getPhoneNumber());
        $this->assertNotNull($entries[1]->getPhoneNumber());
        $this->assertNotNull($entries[2]->getPhoneNumber());
    }

    public function testDefaultsToUnitedStatesWithoutSource(): void
    {
        $entries = $this->parser->parseContent("SMITH, John\n123 Main Street");

        $this->assertEquals('US', $entries[0]->getCountryCode());
        $this->assertNull($entries[0]->getSourceDirectoryId());
    }

    public function testCountryAndSourceDirectoryAreApplied(): void
    {
        $parser = new PhoneDirectoryParser('mx', 'mx_1960_mexico');

        $entries = $parser->parseContent("HERNÁNDEZ, Luis\n45 Reforma Avenue");

        $this->assertEquals('MX', $entries[0]->getCountryCode());
        $this->assertEquals('mx_1960_mexico', $entries[0]->getSourceDirectoryId());
    }

    public function testForCatalogDirectoryUsesCatalogCountry(): void
    {
        $parser = PhoneDirectoryParser::forCatalogDirectory('uk_1880_london');

        $entries = $parser->parseContent("BROWN, Alfred\n12 Fleet Street");

        $this->assertEquals('GB', $entries[0]->getCountryCode());
        $this->assertEquals('uk_1880_london', $entries[0]->getSourceDirectoryId());
    }

    public function testForCatalogDirectoryRejectsUnknownId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PhoneDirectoryParser::forCatalogDirectory('xx_0000_nowhere');
    }

    public function testInvalidCountryCodeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PhoneDirectoryParser('USA');
    }

    public function testSourceLineNumbersIncludingLastBlock(): void
    {
        $content = "\nSMITH, John\n123 Main Street\n\nJONES, Ann\n456 Oak Avenue\n=====\nBROWN, Tom\n7 Pine Road";

        $entries = $this->parser->parseContent($content);

        $this->assertSame([2, 5, 8], array_map(fn($e) => $e->getSourceLine(), $entries));
    }

    public function testRawNameIsPreserved(): void
    {
        $entries = $this->parser->parseContent("GARCÍA LÓPEZ, JOSÉ\n789 Maple Road");

        $this->assertEquals('GARCÍA LÓPEZ, JOSÉ', $entries[0]->getRawName());
        $this->assertEquals('García López, José', $entries[0]->getFormattedName());
    }

    public function testSingleLineEntriesWithDotLeaders(): void
    {
        $content = "SMITH John 12 Oak St ........ 555-111-2222\nJONES Mary 40 Elm Rd ........ 555-333-4444";

        $entries = $this->parser->parseContent($content);

        $this->assertCount(2, $entries);
        $this->assertCount(0, $this->parser->getErrors());
        $this->assertEquals('12 Oak St', $entries[0]->getStreet());
        $this->assertEquals('555-111-2222', $entries[0]->getPhoneNumber());
        $this->assertEquals('40 Elm Rd', $entries[1]->getStreet());
    }

    public function testSingleLineEntryWithSingleSpaces(): void
    {
        $entries = $this->parser->parseContent('SMITH John 12 Oak Street 555-123-4567');

        $this->assertCount(1, $entries);
        $this->assertEquals('12 Oak Street', $entries[0]->getStreet());
        $this->assertEquals('555-123-4567', $entries[0]->getPhoneNumber());
    }

    public function testSingleLineEntryWithoutPhone(): void
    {
        $entries = $this->parser->parseContent('SMITH John 12 Oak Street');

        $this->assertCount(1, $entries);
        $this->assertEquals('12 Oak Street', $entries[0]->getStreet());
        $this->assertNull($entries[0]->getPhoneNumber());
    }

    public function testSingleLineEntryCommaDelimitedWithHistoricalExchangePhone(): void
    {
        $entries = $this->parser->parseContent('BROWN, Alfred, 12 Fleet Street, BUtterfield 8-4521');

        $this->assertCount(1, $entries);
        $this->assertEquals('Brown, Alfred', $entries[0]->getFormattedName());
        $this->assertEquals('12 Fleet Street', $entries[0]->getStreet());
        $this->assertEquals('BUtterfield 8-4521', $entries[0]->getPhoneNumber());
    }

    public function testHistoricalExchangePhoneAcrossMultipleLines(): void
    {
        $entries = $this->parser->parseContent("BROWN, Alfred\n12 Fleet Street\nBUtterfield 8-4521");

        $this->assertCount(1, $entries);
        $this->assertEquals('BUtterfield 8-4521', $entries[0]->getPhoneNumber());
    }

    public function testPhoneLineWithSpacesIsNotAlsoReadAsStreet(): void
    {
        $entries = $this->parser->parseContent("JONES, Ann\n555 234 5678\n45 Pine Street");

        $this->assertCount(1, $entries);
        $this->assertEquals('45 Pine Street', $entries[0]->getStreet());
        $this->assertEquals('555 234 5678', $entries[0]->getPhoneNumber());
    }

    public function testMultiLineBlocksStillWorkAlongsideSingleLineEntries(): void
    {
        $content = "ANDERSON, John\n123 Main Street\n555-123-4567\n\nBAKER Sarah 456 Oak Avenue 555-234-5678";

        $entries = $this->parser->parseContent($content);

        $this->assertCount(2, $entries);
        $this->assertEquals('Anderson, John', $entries[0]->getFormattedName());
        $this->assertEquals('Baker, Sarah', $entries[1]->getFormattedName());
        $this->assertEquals('456 Oak Avenue', $entries[1]->getStreet());
    }

    public function testWidowTitleIsNotReadAsGivenName(): void
    {
        $entries = $this->parser->parseContent("SMITH, Mrs. John, 12 Oak Street, 555-111-2222");

        $this->assertCount(1, $entries);
        $this->assertEquals('Smith, John', $entries[0]->getFormattedName());
        $this->assertEquals('Mrs.', $entries[0]->getTitle());
    }
}
