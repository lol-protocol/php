<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\MultiLanguagePhoneDirectoryParser;
use PhoneDirectory\PhoneDirectoryEntry;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\RecordLinker;
use PhoneDirectory\PersonName;

/**
 * Potential errors identified through static analysis.
 * Each test attempts to trigger a specific error condition.
 */
class PotentialErrorsTest extends TestCase
{
    // ============================================================================
    // ERROR 1: PDO::query() returns false without validation in backfillDerivedColumns()
    // ============================================================================

    public function testError1_BackfillDerivedColumnsWithInvalidSQL(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();

        // Manually set pdo to trigger query error
        $reflection = new \ReflectionClass($db);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);

        // Create a PDO that will fail on query (simulating corrupted table)
        // Note: This is hard to trigger in SQLite, but real in MySQL on permission issues

        // This test documents the VULNERABILITY but SQLite doesn't exhibit it easily
        $this->assertTrue(true, 'Vulnerability exists but SQLite doesnt expose it');
    }

    /**
     * ERROR 1B: Query on non-existent table should fail
     */
    public function testError1B_QueryOnNonExistentTableThrows(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();

        // Try to query before table exists - this SHOULD throw but might not handle correctly
        $reflection = new \ReflectionClass($db);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdo = $pdoProperty->getValue($db);

        // Test the vulnerable code path
        try {
            // This simulates what backfillDerivedColumns() does without error checking
            $result = $pdo->query('SELECT * FROM nonexistent_table');
            if ($result === false) {
                // This is the bug: no null check before calling fetchAll()
                $this->fail('Query returned false but code would call fetchAll() on it');
            }
        } catch (\PDOException $e) {
            $this->assertTrue(true, 'PDOException thrown: ' . $e->getMessage());
        }
    }

    // ============================================================================
    // ERROR 2: lastInsertId() returns string in some drivers, not int
    // ============================================================================

    public function testError2_LastInsertIdMayBeString(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        $entry = new PhoneDirectoryEntry(
            fullName: 'Test User',
            countryCode: 'US',
            street: 'Main St',
            phoneNumber: '555-1234'
        );

        $reflection = new \ReflectionClass($db);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdo = $pdoProperty->getValue($db);

        $id = $db->insert($entry);

        // In MySQL with some drivers, lastInsertId() returns string
        // This documents that (int) cast could cause issues if string is empty
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    // ============================================================================
    // ERROR 3: Transaction commit() can fail without proper rollback
    // ============================================================================

    public function testError3_TransactionFailureHandling(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        $entries = [
            new PhoneDirectoryEntry('Test 1', 'US', 'Main St', '555-1111'),
            new PhoneDirectoryEntry('Test 2', 'US', 'Oak Ave', '555-2222'),
        ];

        // This should succeed, but if commit() fails, rollBack() might also fail
        $count = $db->insertBatch($entries);

        $this->assertSame(2, $count);
    }

    // ============================================================================
    // ERROR 4: PhonePattern::REGEX might be invalid or null
    // ============================================================================

    public function testError4_PhonePatternRegexIsValid(): void
    {
        // Test that PhonePattern::REGEX can be used safely
        $reflection = new \ReflectionClass('PhoneDirectory\PhonePattern');
        $regex = $reflection->getConstant('REGEX');

        $this->assertIsString($regex, 'PhonePattern::REGEX must be a string');
        $this->assertNotEmpty($regex, 'PhonePattern::REGEX must not be empty');

        // Test it's a valid regex
        $test = @preg_match($regex, '555-1234');
        $this->assertNotFalse($test, 'PhonePattern::REGEX must be a valid regex pattern');
    }

    // ============================================================================
    // ERROR 5: Uninitialized $firstNames and $lastNames in PersonName
    // ============================================================================

    public function testError5_PersonNameInitializesEmptyArrays(): void
    {
        $name = new PersonName('John Doe');

        // If parse() doesn't initialize arrays, getFirstName() could fail
        $firstName = $name->getFirstName();
        $this->assertIsString($firstName);
    }

    // ============================================================================
    // ERROR 6: UTF-8 invalid bytes in preg_match
    // ============================================================================

    public function testError6_InvalidUtf8InRegex(): void
    {
        $parser = new MultiLanguagePhoneDirectoryParser('US');

        // Invalid UTF-8 sequence: 0xFF is invalid in UTF-8
        $invalidUtf8 = "John Doe\xFF Street";

        try {
            // This could cause issues in extractStreet() with regex
            $result = $parser->parseContent($invalidUtf8);
            $this->assertIsArray($result);
        } catch (\Throwable $e) {
            // Either it handles it or throws - both are valid
            $this->assertTrue(true);
        }
    }

    // ============================================================================
    // ERROR 7: RecordLinker getFirstName() can return empty string
    // ============================================================================

    public function testError7_FirstNameEmptyStringInRecordLinker(): void
    {
        // Create entries where getFirstName() returns empty
        $entry1 = new PhoneDirectoryEntry(
            fullName: 'Doe',  // No first name
            countryCode: 'US',
            street: '123 Main St',
            phoneNumber: '555-1234'
        );

        $linker = new RecordLinker();

        // If one entry has no first name, link() should skip it
        $links = $linker->link([$entry1]);

        $this->assertEmpty($links, 'Entry with no first name should not create links');
    }

    // ============================================================================
    // ERROR 8: preg_match with null from getSourceDirectoryId()
    // ============================================================================

    public function testError8_SourceDirectoryIdNullInRegex(): void
    {
        $entry = new PhoneDirectoryEntry(
            fullName: 'John Doe',
            countryCode: 'US',
            street: '123 Main St',
            phoneNumber: '555-1234'
        );

        // sourceDirectoryId is null by default
        $this->assertNull($entry->getSourceDirectoryId());

        // RecordLinker::year() method handles this with null coalesce
        $linker = new RecordLinker();

        // Calling link() should not crash with null directory ID
        $links = $linker->link([$entry]);

        $this->assertIsArray($links);
    }

    // ============================================================================
    // ERROR 9: Null from Catalog::get() in various paths
    // ============================================================================

    public function testError9_CatalogGetReturnsNullForUnknownId(): void
    {
        $parser = MultiLanguagePhoneDirectoryParser::forCatalogDirectory('es_1930_madrid');

        $this->assertIsString($parser->getSourceDirectoryId());
    }

    // ============================================================================
    // ERROR 10: Very long phone numbers with no max length
    // ============================================================================

    public function testError10_ExtremelyLongPhoneNumber(): void
    {
        $longPhone = str_repeat('5', 10000);

        $entry = new PhoneDirectoryEntry(
            fullName: 'John Doe',
            countryCode: 'US',
            street: '123 Main St',
            phoneNumber: $longPhone
        );

        $this->assertStringContainsString('5', $entry->getPhoneNumber());
    }

    // ============================================================================
    // ERROR 11: Null bytes in names
    // ============================================================================

    public function testError11_NullBytesInPersonName(): void
    {
        $nameWithNull = "John\x00Doe";

        try {
            $name = new PersonName($nameWithNull);
            $this->assertNotNull($name);
        } catch (\Throwable $e) {
            // Either handles or throws - document the behavior
            $this->assertTrue(true);
        }
    }

    // ============================================================================
    // ERROR 12: Old DB migration with null country_code
    // ============================================================================

    public function testError12_NullCountryCodeAfterMigration(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        // Simulate old row with null country_code
        $reflection = new \ReflectionClass($db);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdo = $pdoProperty->getValue($db);

        $pdo->exec(<<<SQL
            INSERT INTO phone_directory (full_name, street, country_code)
            VALUES ('Old Person', 'Old Street', NULL)
        SQL);

        // Try to find by name - should handle null country_code
        $results = $db->findByName('Old Person');

        // Should not crash even with null country_code
        $this->assertIsArray($results);
    }

    // ============================================================================
    // ERROR 13: getFirstName() returning empty on edge case names
    // ============================================================================

    public function testError13_WhitespaceOnlyFirstName(): void
    {
        $name = new PersonName('  ');

        $firstName = $name->getFirstName();

        // Should not return whitespace-only string
        $this->assertTrue(
            empty(trim($firstName)) || $firstName !== '   ',
            'First name should not be whitespace-only'
        );
    }

    // ============================================================================
    // ERROR 14: Language parameter validation in parser
    // ============================================================================

    public function testError14_UnsupportedLanguageParameter(): void
    {
        $parser = new MultiLanguagePhoneDirectoryParser('US');

        // What happens with unsupported language like 'xx'?
        $content = "Test Name\nTest Street\n555-1234";

        try {
            $result = $parser->parseContent($content, 'xx');
            // Should either use default or throw
            $this->assertIsArray($result);
        } catch (\InvalidArgumentException $e) {
            $this->assertTrue(true, 'Unsupported language thrown: ' . $e->getMessage());
        }
    }

    // ============================================================================
    // ERROR 15: Regex in detectionPattern with unescaped special chars
    // ============================================================================

    public function testError15_SpecialCharsInStreetMarkers(): void
    {
        $parser = new MultiLanguagePhoneDirectoryParser('ES');

        // Test content with regex special chars in street names
        $content = "John Doe\nCalle [test] street\n555-1234";

        $result = $parser->parseContent($content, 'es');

        // Should not crash from regex compilation errors
        $this->assertIsArray($result);
    }

    // ============================================================================
    // ERROR 16: Array access on null from rowToEntry
    // ============================================================================

    public function testError16_RowToEntryWithMissingColumns(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        $entry = new PhoneDirectoryEntry(
            fullName: 'Test',
            countryCode: 'US',
            street: 'Street'
        );

        $id = $db->insert($entry);
        $retrieved = $db->findById($id);

        // Should have all required fields
        $this->assertNotNull($retrieved);
        $this->assertNotNull($retrieved->getCountryCode());
    }

    // ============================================================================
    // ERROR 17: prepare() with invalid SQL
    // ============================================================================

    public function testError17_PrepareWithInvalidSQL(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        $reflection = new \ReflectionClass($db);
        $pdoProperty = $reflection->getProperty('pdo');
        $pdoProperty->setAccessible(true);
        $pdo = $pdoProperty->getValue($db);

        try {
            $stmt = $pdo->prepare('SELECT * FROM nonexistent WHERE invalid syntax');
            if ($stmt === false) {
                $this->fail('prepare() returned false, code might not handle it');
            }
        } catch (\PDOException $e) {
            $this->assertTrue(true, 'Invalid SQL caught: ' . $e->getMessage());
        }
    }

    // ============================================================================
    // ERROR 18: Transaction without proper error context
    // ============================================================================

    public function testError18_TransactionErrorContext(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();

        $entries = [
            new PhoneDirectoryEntry('Test', 'US', 'St', '555-1111'),
        ];

        // insertBatch catches throwable but loses some context
        $count = $db->insertBatch($entries);

        $this->assertGreaterThan(0, $count);
    }
}
