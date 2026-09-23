<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\PhoneDirectoryParser;

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
        $this->assertEquals('SMITH, John', $entries[0]->getFullName());
        $this->assertStringContainsString('Main Street', $entries[0]->getStreet());
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
        $this->assertEquals('ANDERSON, John', $entries[0]->getFullName());
        $this->assertEquals('BAKER, Sarah', $entries[1]->getFullName());
        $this->assertEquals('GARCIA, María', $entries[2]->getFullName());
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
}
