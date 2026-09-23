<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\PhoneDirectoryEntry;
use PhoneDirectory\PhoneDirectoryManager;
use PhoneDirectory\PhoneDirectoryPDODatabase;

class PhoneDirectoryManagerTest extends TestCase
{
    private PhoneDirectoryManager $manager;

    protected function setUp(): void
    {
        $db = new PhoneDirectoryPDODatabase('sqlite::memory:');
        $db->connect();
        $db->createTable();
        $this->manager = new PhoneDirectoryManager(database: $db);
    }

    protected function tearDown(): void
    {
        $this->manager->disconnect();
    }

    public function testProcessFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'phone_');
        $content = <<<TXT
        ANDERSON, John
        123 Main Street
        555-123-4567

        BAKER, Sarah
        456 Oak Avenue
        555-234-5678

        GARCIA, María
        789 Maple Road
        TXT;

        file_put_contents($tempFile, $content);

        $result = $this->manager->processFile($tempFile);

        $this->assertEquals(3, $result['totalParsed']);
        $this->assertGreaterThan(0, $result['insertedCount']);
        unlink($tempFile);
    }

    public function testAddEntry(): void
    {
        $entry = new PhoneDirectoryEntry(
            fullName: 'SMITH, John',
            street: '123 Main Street',
            phoneNumber: '555-123-4567'
        );

        $id = $this->manager->addEntry($entry);

        $this->assertGreaterThan(0, $id);
    }

    public function testGetEntry(): void
    {
        $entry = new PhoneDirectoryEntry('JOHNSON, Mary', '456 Oak Avenue');
        $id = $this->manager->addEntry($entry);

        $found = $this->manager->getEntry($id);

        $this->assertNotNull($found);
        $this->assertEquals('JOHNSON, Mary', $found->getFullName());
    }

    public function testFindByName(): void
    {
        $entry1 = new PhoneDirectoryEntry('WILLIAMS, Robert', '321 Pine Drive');
        $entry2 = new PhoneDirectoryEntry('WILLIAMS, Patricia', '654 Elm Lane');

        $this->manager->addEntry($entry1);
        $this->manager->addEntry($entry2);

        $results = $this->manager->findByName('WILLIAMS');

        $this->assertCount(2, $results);
    }

    public function testFindByStreet(): void
    {
        $entry1 = new PhoneDirectoryEntry('MILLER, Betty', '246 Spruce Lane');
        $entry2 = new PhoneDirectoryEntry('WILSON, Charles', '246 Spruce Lane');
        $entry3 = new PhoneDirectoryEntry('MOORE, Dorothy', '467 Birch Avenue');

        $this->manager->addEntry($entry1);
        $this->manager->addEntry($entry2);
        $this->manager->addEntry($entry3);

        $results = $this->manager->findByStreet('246 Spruce Lane');

        $this->assertCount(2, $results);
    }

    public function testFindByPhone(): void
    {
        $entry = new PhoneDirectoryEntry(
            fullName: 'TAYLOR, George',
            street: '654 Walnut Road',
            phoneNumber: '555-567-8901'
        );

        $this->manager->addEntry($entry);

        $found = $this->manager->findByPhone('555-567-8901');

        $this->assertNotNull($found);
        $this->assertEquals('TAYLOR, George', $found->getFullName());
    }

    public function testGetAllEntries(): void
    {
        $entry1 = new PhoneDirectoryEntry('DAVIS, Jennifer', '987 Chestnut Avenue');
        $entry2 = new PhoneDirectoryEntry('THOMPSON, Edward', '246 Hickory Street');
        $entry3 = new PhoneDirectoryEntry('JACKSON, Susan', '890 Poplar Lane');

        $this->manager->addEntry($entry1);
        $this->manager->addEntry($entry2);
        $this->manager->addEntry($entry3);

        $all = $this->manager->getAllEntries();

        $this->assertCount(3, $all);
    }

    public function testSearchCriteria(): void
    {
        $entry1 = new PhoneDirectoryEntry('WHITE, Donald', '123 Sycamore Street', '555-789-0123');
        $entry2 = new PhoneDirectoryEntry('HARRIS, Christine', '456 Laurel Avenue', '555-890-1234');

        $this->manager->addEntry($entry1);
        $this->manager->addEntry($entry2);

        $results = $this->manager->search(['name' => 'WHITE']);

        $this->assertCount(1, $results);
    }

    public function testUpdateEntry(): void
    {
        $entry = new PhoneDirectoryEntry('MARTIN, Michael', '789 Magnolia Road');
        $id = $this->manager->addEntry($entry);

        $updated = new PhoneDirectoryEntry(
            fullName: 'MARTIN, Michael S.',
            street: '789 Magnolia Road, Apt. 10',
            id: $id
        );

        $result = $this->manager->updateEntry($updated);

        $this->assertTrue($result);
    }

    public function testDeleteEntry(): void
    {
        $entry = new PhoneDirectoryEntry('THOMAS, Richard', '567 Locust Boulevard');
        $id = $this->manager->addEntry($entry);

        $this->assertNotNull($this->manager->getEntry($id));

        $result = $this->manager->deleteEntry($id);

        $this->assertTrue($result);
        $this->assertNull($this->manager->getEntry($id));
    }

    public function testGetTotalCount(): void
    {
        $this->assertEquals(0, $this->manager->getTotalCount());

        $entry1 = new PhoneDirectoryEntry('ANDERSON, John', '123 Main Street');
        $entry2 = new PhoneDirectoryEntry('BAKER, Sarah', '456 Oak Avenue');

        $this->manager->addEntry($entry1);
        $this->assertEquals(1, $this->manager->getTotalCount());

        $this->manager->addEntry($entry2);
        $this->assertEquals(2, $this->manager->getTotalCount());
    }
}
