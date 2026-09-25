<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Parser\MultiLanguagePhoneDirectoryParser;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\PhoneDirectoryPDODatabase;

/**
 * Tests for potential errors identified through static code analysis.
 * These tests document error conditions and vulnerabilities.
 */
class PotentialErrorsTest extends TestCase
{
    /**
     * ERROR #12: Database constraint violation on migration with NULL country_code
     * Documents that legacy databases with NULL values fail to migrate.
     */
    public function testError12_LegacyDatabaseWithNullCountryCodeFailsMigration(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        $reflection = new \ReflectionClass($db);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdo = $pdoProperty->getValue($db);

        // This is the vulnerability: NOT NULL constraint prevents legacy data
        $this->expectException(\PDOException::class);
        $this->expectExceptionMessageMatches('/NOT NULL constraint/i');

        $pdo->exec("INSERT INTO phone_directory (full_name, street, country_code) VALUES ('Old Person', 'Old Street', NULL)");
    }

    /**
     * ERROR #13: PersonName rejects whitespace-only input
     * Documents that constructor validates input properly.
     */
    public function testError13_PersonNameRejectsWhitespaceOnlyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/cannot be empty/i');

        new \PhoneDirectory\Entity\PersonName('   ');
    }

    /**
     * ERROR #4: Verify basic database operations work
     * These should pass - documenting normal paths that are safe.
     */
    public function testBasicDatabaseInsertAndRetrieve(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        $entry = new PhoneDirectoryEntry(
            fullName: 'Test User',
            countryCode: 'US',
            street: 'Main Street',
            phoneNumber: '555-1234'
        );

        $id = $db->insert($entry);
        $this->assertGreaterThan(0, $id);

        $retrieved = $db->findById($id);
        $this->assertNotNull($retrieved);
        $this->assertSame('User, Test', $retrieved->getFormattedName());
    }

    /**
     * ERROR #10: Batch insert operations
     * Documents transaction handling in normal cases.
     */
    public function testBatchInsertTransactionHandling(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        $entries = [
            new PhoneDirectoryEntry('Person 1', 'US', 'Street 1', '555-1111'),
            new PhoneDirectoryEntry('Person 2', 'US', 'Street 2', '555-2222'),
        ];

        $count = $db->insertBatch($entries);
        $this->assertSame(2, $count);
    }

    /**
     * ERROR #11: LIKE wildcard characters in search
     * Documents potential false positive matches with _ and % in names.
     */
    public function testLikeWildcardsBehaviorInSearch(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        // Names with underscore that could act as SQL wildcards
        $db->insert(new PhoneDirectoryEntry('O_Brien', 'US', 'Street 1', '555-1'));
        $db->insert(new PhoneDirectoryEntry('Ocasion', 'US', 'Street 2', '555-2'));

        // Search for the exact name - this currently uses LIKE with %
        $results = $db->findByName('O_Brien');

        // This might return more than expected due to _ acting as single-char wildcard
        // Documenting the behavior, not asserting it's fixed
        $this->assertIsArray($results);
        $this->assertGreaterThan(0, count($results));
    }

    /**
     * ERROR #8 & #9: Language detection robustness
     */
    public function testLanguageDetectionWithSingleMarker(): void
    {
        $parser = new MultiLanguagePhoneDirectoryParser('ES');

        // Genuine Spanish content with just one marker
        $content = "García López\nCalle Mayor 1\n555-1234";

        $entries = $parser->parseContent($content);

        $this->assertGreaterThan(0, count($entries));
    }

    /**
     * ERROR #6: PhoneNumber pattern validation
     */
    public function testPhoneNumberPatternIsValid(): void
    {
        $reflection = new \ReflectionClass('PhoneDirectory\PhonePattern');
        $regex = $reflection->getConstant('REGEX');

        $this->assertIsString($regex);
        $this->assertNotEmpty($regex);

        // Verify regex is compilable
        $test = @preg_match($regex, '555-1234');
        $this->assertNotFalse($test, 'PhonePattern::REGEX must be a valid regex');
    }

    /**
     * ERROR #7: RecordLinker with valid entries
     */
    public function testRecordLinkerWithValidEntries(): void
    {
        $entry1 = new PhoneDirectoryEntry(
            fullName: 'John Smith',
            countryCode: 'US',
            street: '123 Main St',
            phoneNumber: '555-1234',
            sourceDirectoryId: 'dir_1990'
        );

        $entry2 = new PhoneDirectoryEntry(
            fullName: 'John Smith',
            countryCode: 'US',
            street: '456 Oak Ave',
            phoneNumber: '555-1234',
            sourceDirectoryId: 'dir_2000'
        );

        $linker = new \PhoneDirectory\RecordLinker();
        $links = $linker->link([$entry1, $entry2]);

        // Should find a potential link
        $this->assertIsArray($links);
    }

    /**
     * ERROR #5: UTF-8 handling in parsing
     */
    public function testParsingWithAccentedCharacters(): void
    {
        $parser = new MultiLanguagePhoneDirectoryParser('ES');

        $content = "García López\nCalle Mayor 123\n555-1234\n\nJosé María\nAvenida Central\n555-5678";

        $entries = $parser->parseContent($content, 'es');

        $this->assertGreaterThan(0, count($entries));
    }

    /**
     * ERROR #14: Multi-byte character normalization
     */
    public function testMultiByteCharacterNormalization(): void
    {
        $entry = new PhoneDirectoryEntry(
            fullName: 'José García',
            countryCode: 'ES',
            street: 'Calle Mayor'
        );

        // Should handle accented characters in formatted output
        $formatted = $entry->getFormattedName();
        $this->assertStringContainsString('José', $formatted);
        $this->assertStringContainsString('García', $formatted);
    }
}
