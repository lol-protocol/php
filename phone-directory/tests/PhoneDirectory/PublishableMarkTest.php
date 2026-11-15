<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\Privacy;

/**
 * The owner can say that a business whose name holds a given name ("Farmacia Rosa") is not a person's, so a
 * visitor sees it (issue #233). Like the mark of a death, it asks for evidence, only the owner can set it, and
 * editing the business never changes it. "Today" is 2026-10-20 (limit 1933).
 */
class PublishableMarkTest extends TestCase
{
    private const RECENT = 'es_1975_national';

    /** @return array{JuridicalEntityPDODatabase, JuridicalEntityPDODatabase, int} [visitor, owner, the id of Farmacia Rosa] */
    private function loaded(string $dsn, ?string $user, ?string $password): array
    {
        $pdo = new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('DROP TABLE IF EXISTS juridical_entities');

        $owner = new JuridicalEntityPDODatabase($dsn, $user, $password, Privacy::forOwner());
        $owner->createTable();
        $owner->insert(new JuridicalEntity(businessName: 'Panadería Juan Pérez', street: 'Calle Luna 1', countryCode: 'ES', sourceDirectoryId: self::RECENT));
        $id = $owner->insert(new JuridicalEntity(
            businessName: 'Farmacia Rosa',
            street: 'Calle Mayor 12',
            businessType: 'farmacia',
            phoneNumber: '555-0100',
            countryCode: 'ES',
            sourceDirectoryId: self::RECENT
        ));

        return [new JuridicalEntityPDODatabase($dsn, $user, $password, Privacy::forVisitors(new \DateTimeImmutable('2026-10-20'))), $owner, $id];
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testAVisitorSeesAMarkedBusinessThroughEveryFinderAndOnlyThatOne(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $id] = $this->loaded($dsn, $user, $password);

        $this->assertNull($visitor->findById($id), 'hidden before the mark: "Rosa" is a given name');
        $this->assertSame([], $visitor->getAll());

        $this->assertTrue($owner->markPublishable($id, 'Razón social: Farmacia Rosa, S.L. (Registro Mercantil de Madrid)'));

        $this->assertSame('Farmacia Rosa', $visitor->findById($id)?->getBusinessName());
        $this->assertCount(1, $visitor->getAll(), 'the other one carries a given name and stays hidden');
        $this->assertCount(1, $visitor->findByBusinessName('farmacia'));
        $this->assertCount(1, $visitor->findByStreet('calle mayor'));
        $this->assertCount(1, $visitor->findByBusinessType('farmacia'));
        $this->assertCount(1, $visitor->search(['businessName' => 'rosa']));
        $this->assertSame($id, $visitor->findByPhone('555-0100')?->getId());
        $this->assertSame(1, $visitor->count());
        $this->assertTrue($visitor->findById($id)?->isMarkedPublishable());
        $this->assertTrue($visitor->findById($id)?->toArray()['publishable']);
        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testTakingTheMarkBackHidesTheBusinessAgain(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $id] = $this->loaded($dsn, $user, $password);
        $owner->markPublishable($id, 'Registro Mercantil');

        $this->assertTrue($owner->clearPublishable($id));

        $this->assertNull($visitor->findById($id));
        $this->assertNull($owner->getPublishableEvidence($id));
        $this->assertFalse($owner->findById($id)?->isMarkedPublishable());
        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testTheMarkAsksForEvidenceAndOnlyTheOwnerCanSetIt(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $id] = $this->loaded($dsn, $user, $password);

        foreach (['', '   ', str_repeat('x', 1001)] as $evidence) {
            try {
                $owner->markPublishable($id, $evidence);
                $this->fail('the evidence should be refused');
            } catch (\InvalidArgumentException $e) {
                $this->assertNotSame('', $e->getMessage());
            }
        }
        $this->assertNull($visitor->findById($id), 'a refused mark leaves nothing');

        foreach ([
            fn() => $visitor->markPublishable($id, 'Registro'),
            fn() => $visitor->clearPublishable($id),
            fn() => $visitor->getPublishableEvidence($id),
            fn() => (new JuridicalEntityPDODatabase($dsn, $user, $password))->markPublishable($id, 'Registro'),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('a database opened for visitors must refuse');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('Privacy::forOwner()', $e->getMessage());
            }
        }

        $this->assertFalse($owner->markPublishable(987654, 'Registro'), 'no such business');
        $this->assertFalse($owner->clearPublishable(987654));
        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testEditingOrInsertingNeverChangesTheMark(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $id] = $this->loaded($dsn, $user, $password);

        // An entity that says it is publishable is not stored as such: the mark comes only with its evidence.
        $copy = $owner->insert((new JuridicalEntity(businessName: 'Taller Rosa', street: 'Calle Sol 5', countryCode: 'ES', sourceDirectoryId: self::RECENT))->withPublishable(true));
        $this->assertFalse($owner->findById($copy)?->isMarkedPublishable());
        $this->assertNull($visitor->findById($copy));

        // Editing a marked business keeps its mark, and editing an unmarked one does not make it publishable.
        $owner->markPublishable($id, 'Registro Mercantil');
        $edited = new JuridicalEntity(businessName: 'Farmacia Rosa 2', street: 'Calle Mayor 14', id: $id, countryCode: 'ES', sourceDirectoryId: self::RECENT);
        $this->assertTrue($owner->update($edited));
        $this->assertTrue($owner->findById($id)?->isMarkedPublishable());
        $this->assertSame('Registro Mercantil', $owner->getPublishableEvidence($id));
        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testTheEvidenceIsNotPartOfTheBusiness(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $id] = $this->loaded($dsn, $user, $password);
        $owner->markPublishable($id, '  Registro Mercantil de Madrid, hoja M-12345  ');

        $this->assertSame('Registro Mercantil de Madrid, hoja M-12345', $owner->getPublishableEvidence($id));
        $this->assertStringNotContainsString('Registro', json_encode($visitor->findById($id)?->toArray(), JSON_THROW_ON_ERROR));
        $visitor->disconnect();
        $owner->disconnect();
    }

    /** A database made before the column existed gets it the next time createTable() runs. */
    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testADatabaseFromBeforeTheMarkExistedIsUpgraded(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $id] = $this->loaded($dsn, $user, $password);
        $pdo = new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('ALTER TABLE juridical_entities DROP COLUMN publishable_evidence');

        $owner->createTable();

        $this->assertFalse($owner->findById($id)?->isMarkedPublishable());
        $this->assertTrue($owner->markPublishable($id, 'Registro'));
        $this->assertNotNull($visitor->findById($id));
        $this->assertCount(2, $owner->getAll(), 'the rows that were there are still there');
        $visitor->disconnect();
        $owner->disconnect();
    }
}
