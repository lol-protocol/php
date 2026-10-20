<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\DirectoryDatabaseInterface;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\Privacy;

/**
 * The calls the two databases have in common (DirectoryDatabaseInterface) behave the same on both, so
 * code written against that interface does not care which one it is given.
 */
class DirectoryDatabaseInterfaceTest extends TestCase
{
    /** @return array<string, array{DirectoryDatabaseInterface<object>, list<object>}> */
    public static function databases(): array
    {
        return [
            'natural persons' => [
                new PhoneDirectoryPDODatabase('sqlite::memory:', privacy: Privacy::forOwner()),
                [
                    new PhoneDirectoryEntry('SMITH, John', 'US', '1 Oak Avenue', '555-0100'),
                    new PhoneDirectoryEntry('JONES, Ann', 'US', '2 Oak Avenue'),
                ],
            ],
            'businesses' => [
                new JuridicalEntityPDODatabase('sqlite::memory:'),
                [
                    new JuridicalEntity(businessName: 'Farmacia Central', street: '1 Oak Avenue', phoneNumber: '555-0100'),
                    new JuridicalEntity(businessName: 'Hotel Plaza', street: '2 Oak Avenue'),
                ],
            ],
        ];
    }

    /**
     * @param DirectoryDatabaseInterface<object> $database
     * @param list<object> $entities
     */
    #[DataProvider('databases')]
    public function testTheCommonCallsWorkTheSameOnBothDatabases(DirectoryDatabaseInterface $database, array $entities): void
    {
        $database->connect();
        $database->createTable();

        $this->assertSame(2, $database->insertBatch($entities));
        $this->assertSame(2, $database->count());
        $this->assertCount(2, $database->findByStreet('oak'));
        $this->assertNotNull($database->findByPhone('555-0100'));
        $this->assertNull($database->findByPhone('000-0000'));

        $id = $database->getAll()[0]->getId();
        $this->assertNotNull($database->findById($id));
        $this->assertTrue($database->delete($id));
        $this->assertNull($database->findById($id));
        $this->assertSame(1, $database->count());

        $this->assertTrue($database->clear());
        $this->assertSame(0, $database->count());

        $database->disconnect();
        $this->assertFalse($database->isConnected());
    }

    public function testInsertBatchSkipsWhatIsNotTheDatabasesEntity(): void
    {
        $natural = new PhoneDirectoryPDODatabase('sqlite::memory:', privacy: Privacy::forOwner());
        $natural->createTable();

        $inserted = $natural->insertBatch([
            new PhoneDirectoryEntry('SMITH, John', 'US', '1 Oak Avenue'),
            new JuridicalEntity(businessName: 'Hotel Plaza', street: '2 Oak Avenue'),
        ]);

        $this->assertSame(1, $inserted);
    }
}
