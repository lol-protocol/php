<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\PhoneDirectoryEntry;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\JuridicalEntityPDODatabase;

class PhoneDirectoryDatabaseTest extends TestCase
{
    private PhoneDirectoryPDODatabase $database;

    protected function setUp(): void
    {
        $this->database = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $this->database->connect();
        $this->database->createTable();
    }

    protected function tearDown(): void
    {
        $this->database->disconnect();
    }

    public function testDatabaseConnection(): void
    {
        $this->assertTrue($this->database->isConnected());
    }

    public function testCreateTable(): void
    {
        $this->database->disconnect();
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();
        $this->assertTrue($db->isConnected());
    }

    public function testInsertEntry(): void
    {
        $entry = new PhoneDirectoryEntry(
            fullName: 'SMITH, John',
            countryCode: 'US',
            street: '123 Main Street',
            phoneNumber: '555-123-4567'
        );

        $id = $this->database->insert($entry);

        $this->assertGreaterThan(0, $id);
        $this->assertEquals(1, $this->database->count());
    }

    public function testFindById(): void
    {
        $entry = new PhoneDirectoryEntry(
            fullName: 'JOHNSON, Mary',
            countryCode: 'US',
            street: '456 Oak Avenue',
            phoneNumber: '555-234-5678'
        );

        $id = $this->database->insert($entry);
        $found = $this->database->findById($id);

        $this->assertNotNull($found);
        $this->assertEquals('Johnson, Mary', $found->getFormattedName());
        $this->assertEquals('456 Oak Avenue', $found->getStreet());
    }

    public function testFindByName(): void
    {
        $entry1 = new PhoneDirectoryEntry('SMITH, John', 'US', '123 Main Street');
        $entry2 = new PhoneDirectoryEntry('SMITH, Mary', 'US', '456 Oak Avenue');
        $entry3 = new PhoneDirectoryEntry('JOHNSON, Robert', 'US', '789 Pine Road');

        $this->database->insert($entry1);
        $this->database->insert($entry2);
        $this->database->insert($entry3);

        $results = $this->database->findByName('SMITH');

        $this->assertCount(2, $results);
    }

    public function testFindByStreet(): void
    {
        $entry1 = new PhoneDirectoryEntry('ANDERSON, John', 'US', '123 Main Street');
        $entry2 = new PhoneDirectoryEntry('BAKER, Sarah', 'US', '456 Main Street');
        $entry3 = new PhoneDirectoryEntry('GARCIA, María', 'US', '789 Oak Avenue');

        $this->database->insert($entry1);
        $this->database->insert($entry2);
        $this->database->insert($entry3);

        $results = $this->database->findByStreet('Main Street');

        $this->assertCount(2, $results);
    }

    public function testFindByPhone(): void
    {
        $entry = new PhoneDirectoryEntry(
            fullName: 'WILLIAMS, Robert',
            countryCode: 'US',
            street: '321 Pine Drive',
            phoneNumber: '555-456-7890'
        );

        $this->database->insert($entry);
        $found = $this->database->findByPhone('555-456-7890');

        $this->assertNotNull($found);
        $this->assertEquals('Williams, Robert', $found->getFormattedName());
    }

    public function testGetAll(): void
    {
        $entry1 = new PhoneDirectoryEntry('MILLER, Betty', 'US', '246 Elm Lane');
        $entry2 = new PhoneDirectoryEntry('WILSON, Charles', 'US', '913 Birch Street');
        $entry3 = new PhoneDirectoryEntry('MOORE, Dorothy', 'US', '467 Spruce Avenue');

        $this->database->insert($entry1);
        $this->database->insert($entry2);
        $this->database->insert($entry3);

        $all = $this->database->getAll();

        $this->assertCount(3, $all);
    }

    public function testInsertBatch(): void
    {
        $entries = [
            new PhoneDirectoryEntry('TAYLOR, George', 'US', '654 Walnut Road'),
            new PhoneDirectoryEntry('DAVIS, Jennifer', 'US', '987 Chestnut Avenue'),
            new PhoneDirectoryEntry('THOMPSON, Edward', 'US', '246 Hickory Street'),
        ];

        $count = $this->database->insertBatch($entries);

        $this->assertEquals(3, $count);
        $this->assertEquals(3, $this->database->count());
    }

    public function testUpdateEntry(): void
    {
        $entry = new PhoneDirectoryEntry('JACKSON, Susan', 'US', '890 Poplar Lane');
        $id = $this->database->insert($entry);

        $entry->setId($id);
        $updated = new PhoneDirectoryEntry(
            fullName: 'JACKSON, Susan K.',
            countryCode: 'US',
            street: '890 Poplar Lane, Unit B',
            phoneNumber: '555-678-9012',
            id: $id
        );

        $result = $this->database->update($updated);

        $this->assertTrue($result);
        $found = $this->database->findById($id);
        $this->assertEquals('Jackson, Susan K.', $found->getFormattedName());
    }

    public function testDeleteEntry(): void
    {
        $entry = new PhoneDirectoryEntry('WHITE, Donald', 'US', '123 Sycamore Street');
        $id = $this->database->insert($entry);

        $this->assertEquals(1, $this->database->count());

        $result = $this->database->delete($id);

        $this->assertTrue($result);
        $this->assertEquals(0, $this->database->count());
        $this->assertNull($this->database->findById($id));
    }

    public function testSearchByCriteria(): void
    {
        $entry1 = new PhoneDirectoryEntry('HARRIS, Christine', 'US', '456 Laurel Avenue', '555-890-1234');
        $entry2 = new PhoneDirectoryEntry('MARTIN, Michael', 'US', '789 Magnolia Avenue', '555-901-2345');
        $entry3 = new PhoneDirectoryEntry('THOMAS, Richard', 'US', '567 Locust Boulevard');

        $this->database->insert($entry1);
        $this->database->insert($entry2);
        $this->database->insert($entry3);

        $results = $this->database->search(['name' => 'HARRIS']);
        $this->assertCount(1, $results);

        $results = $this->database->search(['street' => 'Avenue']);
        $this->assertCount(2, $results);

        $results = $this->database->search(['phone' => '555-901-2345']);
        $this->assertCount(1, $results);
    }

    public function testClearDatabase(): void
    {
        $entry1 = new PhoneDirectoryEntry('ANDERSON, John', 'US', '123 Main Street');
        $entry2 = new PhoneDirectoryEntry('BAKER, Sarah', 'US', '456 Oak Avenue');

        $this->database->insert($entry1);
        $this->database->insert($entry2);

        $this->assertEquals(2, $this->database->count());

        $result = $this->database->clear();

        $this->assertTrue($result);
        $this->assertEquals(0, $this->database->count());
    }

    public function testCountEntries(): void
    {
        $this->assertEquals(0, $this->database->count());

        $entry1 = new PhoneDirectoryEntry('SMITH, John', 'US', '123 Main Street');
        $entry2 = new PhoneDirectoryEntry('JOHNSON, Mary', 'US', '456 Oak Avenue');

        $this->database->insert($entry1);
        $this->assertEquals(1, $this->database->count());

        $this->database->insert($entry2);
        $this->assertEquals(2, $this->database->count());
    }

    public function testCommaSurnamesSurviveRoundTrip(): void
    {
        $id = $this->database->insert(new PhoneDirectoryEntry('GARCÍA LÓPEZ, Juan', 'ES', 'Calle Mayor 12'));

        $found = $this->database->findById($id);

        $this->assertEquals('GARCÍA LÓPEZ, Juan', $found->getRawName());
        $this->assertEquals(['García', 'López'], $found->getLastNames());
        $this->assertEquals('ES', $found->getCountryCode());
    }

    public function testLanguageSurvivesRoundTrip(): void
    {
        $entry = new PhoneDirectoryEntry(fullName: 'Juan García López', countryCode: 'ES', street: 'Calle Mayor 12', language: 'es');

        $found = $this->database->findById($this->database->insert($entry));

        $this->assertEquals('es', $found->getLanguage());
        $this->assertEquals(['García', 'López'], $found->getLastNames());
    }

    public function testSourceProvenanceSurvivesInsertAndUpdate(): void
    {
        $entry = new PhoneDirectoryEntry(
            fullName: 'SMITH, John',
            countryCode: 'US',
            street: '123 Main Street',
            sourceDirectoryId: 'us_1878_ny',
            sourceLine: 42
        );
        $id = $this->database->insert($entry);

        $found = $this->database->findById($id);
        $this->assertEquals('us_1878_ny', $found->getSourceDirectoryId());
        $this->assertSame(42, $found->getSourceLine());

        $this->database->update(new PhoneDirectoryEntry(
            fullName: 'SMITH, John A.',
            countryCode: 'US',
            street: '123 Main Street',
            id: $id,
            sourceDirectoryId: 'us_1915_national',
            sourceLine: 7
        ));

        $updated = $this->database->findById($id);
        $this->assertEquals('SMITH, John A.', $updated->getRawName());
        $this->assertEquals('us_1915_national', $updated->getSourceDirectoryId());
        $this->assertSame(7, $updated->getSourceLine());
    }

    public function testCreateTableAddsMissingColumnsToOldDatabase(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'phonedir_');
        try {
            $legacy = new \PDO("sqlite:{$file}");
            $legacy->exec('CREATE TABLE phone_directory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                street TEXT NOT NULL,
                phone_number TEXT,
                record_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');
            $legacy->exec("INSERT INTO phone_directory (full_name, street) VALUES ('John Smith', '123 Main Street')");
            $legacy = null;

            $database = new PhoneDirectoryPDODatabase("sqlite:{$file}");
            $database->createTable();
            $database->createTable();

            $old = $database->findById(1);
            $this->assertEquals('John Smith', $old->getRawName());
            $this->assertEquals('US', $old->getCountryCode());
            $this->assertNull($old->getSourceDirectoryId());

            $id = $database->insert(new PhoneDirectoryEntry(
                fullName: 'GARCÍA LÓPEZ, Juan',
                countryCode: 'ES',
                street: 'Calle Mayor 12',
                sourceDirectoryId: 'es_1930_madrid',
                sourceLine: 3
            ));
            $new = $database->findById($id);
            $this->assertEquals('ES', $new->getCountryCode());
            $this->assertEquals(['García', 'López'], $new->getLastNames());
            $this->assertSame(3, $new->getSourceLine());
            $database->disconnect();
        } finally {
            unlink($file);
        }
    }

    public function testSearchTreatsWildcardCharactersLiterally(): void
    {
        $this->database->insert(new PhoneDirectoryEntry('SMITH, John', 'US', '1 Oak Avenue'));
        $this->database->insert(new PhoneDirectoryEntry('JONES, Ann', 'US', '2 Oak_Avenue'));

        $this->assertCount(0, $this->database->findByName('%'));
        $this->assertCount(0, $this->database->findByName('_'));
        $this->assertCount(1, $this->database->findByStreet('Oak_Avenue'));
        $this->assertCount(1, $this->database->search(['street' => 'Oak_Avenue']));
        $this->assertCount(2, $this->database->findByStreet('Oak'));
    }

    public function testIndexesDoNotCollideWithJuridicalTableInSharedDatabase(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'phonedir_');
        try {
            $natural = new PhoneDirectoryPDODatabase("sqlite:{$file}");
            $natural->createTable();
            $juridical = new JuridicalEntityPDODatabase("sqlite:{$file}");
            $juridical->createTable();
            $natural->disconnect();
            $juridical->disconnect();

            $pdo = new \PDO("sqlite:{$file}");
            $indexedColumns = $pdo->query("SELECT il.name FROM sqlite_master m, pragma_index_list(m.name) l, pragma_index_info(l.name) il WHERE m.name = 'juridical_entities'")
                ->fetchAll(\PDO::FETCH_COLUMN);
            $pdo = null;

            $this->assertContains('street', $indexedColumns);
            $this->assertContains('phone_number', $indexedColumns);
        } finally {
            unlink($file);
        }
    }

    private function surnames(array $entries): array
    {
        $names = array_map(fn($e) => $e->getFormattedName(), $entries);
        sort($names);

        return $names;
    }

    public function testFindBySurnameSoundMatchesSpellingVariants(): void
    {
        $this->database->insert(new PhoneDirectoryEntry('SMITH, John', 'US', '1 Oak Avenue'));
        $this->database->insert(new PhoneDirectoryEntry('SMYTH, Mary', 'US', '2 Oak Avenue'));
        $this->database->insert(new PhoneDirectoryEntry('JONES, Ann', 'US', '3 Oak Avenue'));

        $this->assertEquals(['Smith, John', 'Smyth, Mary'], $this->surnames($this->database->findBySurnameSound('Smithe')));
    }

    public function testFindBySurnameSoundUsesLanguageRules(): void
    {
        $this->database->insert(new PhoneDirectoryEntry(fullName: 'Juan Valdez Ruiz', countryCode: 'MX', street: 'Calle Hidalgo 4', language: 'es'));
        $this->database->insert(new PhoneDirectoryEntry(fullName: 'Ana Baldez Soto', countryCode: 'MX', street: 'Calle Juárez 9', language: 'es'));

        $this->assertCount(1, $this->database->findBySurnameSound('Valdez'));
        $this->assertEquals(
            ['Baldez Soto, Ana', 'Valdez Ruiz, Juan'],
            $this->surnames($this->database->findBySurnameSound('Valdez', 'es'))
        );
    }

    public function testFindBySurnameSoundUsesSurnameRoot(): void
    {
        $this->database->insert(new PhoneDirectoryEntry('DE LA CRUZ, María', 'ES', 'Calle Mayor 1'));
        $this->database->insert(new PhoneDirectoryEntry('CRUZ, Pedro', 'ES', 'Calle Mayor 2'));
        $this->database->insert(new PhoneDirectoryEntry('DELGADO, Luis', 'ES', 'Calle Mayor 3'));

        $expected = ['Cruz, Pedro', 'de la Cruz, María'];
        $this->assertEquals($expected, $this->surnames($this->database->findBySurnameSound('de la Cruz')));
        $this->assertEquals($expected, $this->surnames($this->database->findBySurnameSound('Cruz')));
        $this->assertSame([], $this->database->findBySurnameSound('de la'));
    }

    public function testSurnameKeysAreBackfilledForExistingRows(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'phonedir_');
        try {
            $legacy = new \PDO("sqlite:{$file}");
            $legacy->exec('CREATE TABLE phone_directory (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                full_name TEXT NOT NULL,
                street TEXT NOT NULL,
                phone_number TEXT,
                record_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )');
            $legacy->exec("INSERT INTO phone_directory (full_name, street) VALUES ('John Smyth', '123 Main Street')");
            $legacy = null;

            $database = new PhoneDirectoryPDODatabase("sqlite:{$file}");
            $database->createTable();

            $this->assertEquals(['Smyth, John'], $this->surnames($database->findBySurnameSound('Smith')));
            $database->disconnect();
        } finally {
            unlink($file);
        }
    }

    public function testUpdateRecomputesSurnameKeys(): void
    {
        $id = $this->database->insert(new PhoneDirectoryEntry('SMITH, John', 'US', '1 Oak Avenue'));
        $this->database->update(new PhoneDirectoryEntry(fullName: 'JONES, John', countryCode: 'US', street: '1 Oak Avenue', id: $id));

        $this->assertSame([], $this->database->findBySurnameSound('Smith'));
        $this->assertCount(1, $this->database->findBySurnameSound('Jones'));
    }
}
