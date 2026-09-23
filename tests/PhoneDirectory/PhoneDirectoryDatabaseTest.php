<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\PhoneDirectoryEntry;
use PhoneDirectory\PhoneDirectoryPDODatabase;

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
            street: '456 Oak Avenue',
            phoneNumber: '555-234-5678'
        );

        $id = $this->database->insert($entry);
        $found = $this->database->findById($id);

        $this->assertNotNull($found);
        $this->assertEquals('JOHNSON, Mary', $found->getFullName());
        $this->assertEquals('456 Oak Avenue', $found->getStreet());
    }

    public function testFindByName(): void
    {
        $entry1 = new PhoneDirectoryEntry('SMITH, John', '123 Main Street');
        $entry2 = new PhoneDirectoryEntry('SMITH, Mary', '456 Oak Avenue');
        $entry3 = new PhoneDirectoryEntry('JOHNSON, Robert', '789 Pine Road');

        $this->database->insert($entry1);
        $this->database->insert($entry2);
        $this->database->insert($entry3);

        $results = $this->database->findByName('SMITH');

        $this->assertCount(2, $results);
    }

    public function testFindByStreet(): void
    {
        $entry1 = new PhoneDirectoryEntry('ANDERSON, John', '123 Main Street');
        $entry2 = new PhoneDirectoryEntry('BAKER, Sarah', '456 Main Street');
        $entry3 = new PhoneDirectoryEntry('GARCIA, María', '789 Oak Avenue');

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
            street: '321 Pine Drive',
            phoneNumber: '555-456-7890'
        );

        $this->database->insert($entry);
        $found = $this->database->findByPhone('555-456-7890');

        $this->assertNotNull($found);
        $this->assertEquals('WILLIAMS, Robert', $found->getFullName());
    }

    public function testGetAll(): void
    {
        $entry1 = new PhoneDirectoryEntry('MILLER, Betty', '246 Elm Lane');
        $entry2 = new PhoneDirectoryEntry('WILSON, Charles', '913 Birch Street');
        $entry3 = new PhoneDirectoryEntry('MOORE, Dorothy', '467 Spruce Avenue');

        $this->database->insert($entry1);
        $this->database->insert($entry2);
        $this->database->insert($entry3);

        $all = $this->database->getAll();

        $this->assertCount(3, $all);
    }

    public function testInsertBatch(): void
    {
        $entries = [
            new PhoneDirectoryEntry('TAYLOR, George', '654 Walnut Road'),
            new PhoneDirectoryEntry('DAVIS, Jennifer', '987 Chestnut Avenue'),
            new PhoneDirectoryEntry('THOMPSON, Edward', '246 Hickory Street'),
        ];

        $count = $this->database->insertBatch($entries);

        $this->assertEquals(3, $count);
        $this->assertEquals(3, $this->database->count());
    }

    public function testUpdateEntry(): void
    {
        $entry = new PhoneDirectoryEntry('JACKSON, Susan', '890 Poplar Lane');
        $id = $this->database->insert($entry);

        $entry->setId($id);
        $updated = new PhoneDirectoryEntry(
            fullName: 'JACKSON, Susan K.',
            street: '890 Poplar Lane, Unit B',
            phoneNumber: '555-678-9012',
            id: $id
        );

        $result = $this->database->update($updated);

        $this->assertTrue($result);
        $found = $this->database->findById($id);
        $this->assertEquals('JACKSON, Susan K.', $found->getFullName());
    }

    public function testDeleteEntry(): void
    {
        $entry = new PhoneDirectoryEntry('WHITE, Donald', '123 Sycamore Street');
        $id = $this->database->insert($entry);

        $this->assertEquals(1, $this->database->count());

        $result = $this->database->delete($id);

        $this->assertTrue($result);
        $this->assertEquals(0, $this->database->count());
        $this->assertNull($this->database->findById($id));
    }

    public function testSearchByCriteria(): void
    {
        $entry1 = new PhoneDirectoryEntry('HARRIS, Christine', '456 Laurel Avenue', '555-890-1234');
        $entry2 = new PhoneDirectoryEntry('MARTIN, Michael', '789 Magnolia Avenue', '555-901-2345');
        $entry3 = new PhoneDirectoryEntry('THOMAS, Richard', '567 Locust Boulevard');

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
        $entry1 = new PhoneDirectoryEntry('ANDERSON, John', '123 Main Street');
        $entry2 = new PhoneDirectoryEntry('BAKER, Sarah', '456 Oak Avenue');

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

        $entry1 = new PhoneDirectoryEntry('SMITH, John', '123 Main Street');
        $entry2 = new PhoneDirectoryEntry('JOHNSON, Mary', '456 Oak Avenue');

        $this->database->insert($entry1);
        $this->assertEquals(1, $this->database->count());

        $this->database->insert($entry2);
        $this->assertEquals(2, $this->database->count());
    }
}
