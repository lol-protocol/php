<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\JuridicalEntityPDODatabase;

class JuridicalEntityDatabaseTest extends TestCase
{
    private JuridicalEntityPDODatabase $database;

    protected function setUp(): void
    {
        $this->database = new JuridicalEntityPDODatabase('sqlite::memory:');
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

    public function testInsertEntity(): void
    {
        $entity = new JuridicalEntity(
            businessName: 'Farmacia Central',
            street: '123 Main Street',
            phoneNumber: '555-123-4567'
        );

        $id = $this->database->insert($entity);

        $this->assertGreaterThan(0, $id);
        $this->assertEquals(1, $this->database->count());
    }

    public function testFindById(): void
    {
        $entity = new JuridicalEntity(
            businessName: 'Banco Nacional',
            street: '456 Oak Avenue',
            legalName: 'Banco Nacional S.A.',
            businessType: 'bank'
        );

        $id = $this->database->insert($entity);
        $found = $this->database->findById($id);

        $this->assertNotNull($found);
        $this->assertEquals('Banco Nacional', $found->getBusinessName());
        $this->assertEquals('Banco Nacional S.A.', $found->getLegalName());
        $this->assertEquals('456 Oak Avenue', $found->getStreet());
    }

    public function testFindByIdReturnsNullWhenMissing(): void
    {
        $this->assertNull($this->database->findById(999));
    }

    public function testFindByBusinessName(): void
    {
        $this->database->insert(new JuridicalEntity(businessName: 'Farmacia Central', street: '123 Main Street'));
        $this->database->insert(new JuridicalEntity(businessName: 'Farmacia Águila', street: '456 Oak Avenue'));
        $this->database->insert(new JuridicalEntity(businessName: 'Hotel Plaza', street: '789 Pine Road'));

        $results = $this->database->findByBusinessName('Farmacia');

        $this->assertCount(2, $results);
    }

    public function testFindByStreet(): void
    {
        $this->database->insert(new JuridicalEntity(businessName: 'Café Luna', street: '123 Main Street'));
        $this->database->insert(new JuridicalEntity(businessName: 'Panadería Sol', street: '456 Main Street'));
        $this->database->insert(new JuridicalEntity(businessName: 'Ferretería Norte', street: '789 Oak Avenue'));

        $results = $this->database->findByStreet('Main Street');

        $this->assertCount(2, $results);
    }

    public function testFindByPhone(): void
    {
        $entity = new JuridicalEntity(
            businessName: 'Restaurante El Sol',
            street: '321 Pine Drive',
            phoneNumber: '555-456-7890'
        );

        $this->database->insert($entity);
        $found = $this->database->findByPhone('555-456-7890');

        $this->assertNotNull($found);
        $this->assertEquals('Restaurante El Sol', $found->getBusinessName());
    }

    public function testFindByBusinessType(): void
    {
        $this->database->insert(new JuridicalEntity(businessName: 'Farmacia Central', street: '1 A St', businessType: 'pharmacy'));
        $this->database->insert(new JuridicalEntity(businessName: 'Farmacia Águila', street: '2 B St', businessType: 'pharmacy'));
        $this->database->insert(new JuridicalEntity(businessName: 'Hotel Plaza', street: '3 C St', businessType: 'hotel'));

        $results = $this->database->findByBusinessType('pharmacy');

        $this->assertCount(2, $results);
    }

    public function testGetAll(): void
    {
        $this->database->insert(new JuridicalEntity(businessName: 'Alpha Corp', street: '1 A St'));
        $this->database->insert(new JuridicalEntity(businessName: 'Beta Corp', street: '2 B St'));
        $this->database->insert(new JuridicalEntity(businessName: 'Gamma Corp', street: '3 C St'));

        $this->assertCount(3, $this->database->getAll());
    }

    public function testInsertBatch(): void
    {
        $entities = [
            new JuridicalEntity(businessName: 'Alpha Corp', street: '1 A St'),
            new JuridicalEntity(businessName: 'Beta Corp', street: '2 B St'),
            new JuridicalEntity(businessName: 'Gamma Corp', street: '3 C St'),
        ];

        $count = $this->database->insertBatch($entities);

        $this->assertEquals(3, $count);
        $this->assertEquals(3, $this->database->count());
    }

    public function testInsertBatchCountReflectsOnlyMatchingEntityType(): void
    {
        // A mistakenly mixed-type array must not be counted as if every row were inserted.
        $entities = [
            new JuridicalEntity(businessName: 'Alpha Corp', street: '1 A St'),
            new PhoneDirectoryEntry('SMITH, John', 'US', '2 B St'),
            new JuridicalEntity(businessName: 'Gamma Corp', street: '3 C St'),
        ];

        $count = $this->database->insertBatch($entities);

        $this->assertEquals(2, $count);
        $this->assertEquals(2, $this->database->count());
    }

    public function testUpdateEntity(): void
    {
        $entity = new JuridicalEntity(businessName: 'Old Name', street: '1 A St');
        $id = $this->database->insert($entity);

        $updated = new JuridicalEntity(
            businessName: 'New Name',
            street: '1 A St, Unit B',
            phoneNumber: '555-999-0000',
            id: $id
        );

        $result = $this->database->update($updated);

        $this->assertTrue($result);
        $found = $this->database->findById($id);
        $this->assertEquals('New Name', $found->getBusinessName());
        $this->assertEquals('555-999-0000', $found->getPhoneNumber());
    }

    public function testUpdateWithoutIdThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->database->update(new JuridicalEntity(businessName: 'No Id', street: '1 A St'));
    }

    public function testDeleteEntity(): void
    {
        $id = $this->database->insert(new JuridicalEntity(businessName: 'To Delete', street: '1 A St'));

        $this->assertEquals(1, $this->database->count());

        $result = $this->database->delete($id);

        $this->assertTrue($result);
        $this->assertEquals(0, $this->database->count());
        $this->assertNull($this->database->findById($id));
    }

    public function testCountEntities(): void
    {
        $this->assertEquals(0, $this->database->count());

        $this->database->insert(new JuridicalEntity(businessName: 'Alpha Corp', street: '1 A St'));
        $this->assertEquals(1, $this->database->count());

        $this->database->insert(new JuridicalEntity(businessName: 'Beta Corp', street: '2 B St'));
        $this->assertEquals(2, $this->database->count());
    }

    public function testSearchByCriteria(): void
    {
        $this->database->insert(new JuridicalEntity(businessName: 'Farmacia Central', street: '456 Laurel Avenue', phoneNumber: '555-890-1234', businessType: 'pharmacy'));
        $this->database->insert(new JuridicalEntity(businessName: 'Hotel Plaza', street: '789 Magnolia Avenue', phoneNumber: '555-901-2345', businessType: 'hotel'));
        $this->database->insert(new JuridicalEntity(businessName: 'Banco Central', street: '567 Locust Boulevard', businessType: 'bank'));

        $this->assertCount(1, $this->database->search(['businessName' => 'Farmacia']));
        $this->assertCount(2, $this->database->search(['street' => 'Avenue']));
        $this->assertCount(1, $this->database->search(['phone' => '555-901-2345']));
        $this->assertCount(1, $this->database->search(['businessType' => 'pharmacy']));
    }

    public function testSearchWithEmptyCriteriaReturnsAll(): void
    {
        $this->database->insert(new JuridicalEntity(businessName: 'Alpha Corp', street: '1 A St'));
        $this->database->insert(new JuridicalEntity(businessName: 'Beta Corp', street: '2 B St'));

        $this->assertCount(2, $this->database->search([]));
    }

    public function testClearDatabase(): void
    {
        $this->database->insert(new JuridicalEntity(businessName: 'Alpha Corp', street: '1 A St'));
        $this->database->insert(new JuridicalEntity(businessName: 'Beta Corp', street: '2 B St'));

        $this->assertEquals(2, $this->database->count());

        $result = $this->database->clear();

        $this->assertTrue($result);
        $this->assertEquals(0, $this->database->count());
    }

    public function testSearchIsAccentInsensitive(): void
    {
        $this->database->insert(new JuridicalEntity(businessName: 'Farmacia Águila', street: 'Calle Mayor 12'));

        $this->assertCount(1, $this->database->findByBusinessName('aguila'));
        $this->assertCount(1, $this->database->search(['businessName' => 'AGUILA']));
    }
}
