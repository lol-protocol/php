<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\Manager\PhoneDirectoryManagerV2;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\Privacy;
use PhoneDirectory\RecordLinker;

/**
 * What a reader gets out of the database, on every engine CrossDatabaseTest runs on.
 *
 * "Today" is 2026-10-20, so the limit is 1933: the 1930 Madrid directory may be shown, the 1975 one may not.
 * The data has an old and a recent García, and the same phone number in both (numbers are reused from one
 * edition to the next).
 */
class PrivacyDatabaseTest extends TestCase
{
    private const OLD = 'es_1930_madrid';
    private const RECENT = 'es_1975_national';

    private static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-20');
    }

    /** @return array{PhoneDirectoryPDODatabase, PhoneDirectoryPDODatabase, array<string, int>} [visitor, owner, ids] */
    private function loaded(string $dsn, ?string $user, ?string $password): array
    {
        $pdo = new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('DROP TABLE IF EXISTS phone_directory');
        $pdo->exec('DROP TABLE IF EXISTS juridical_entities');

        $owner = new PhoneDirectoryPDODatabase($dsn, $user, $password, Privacy::forOwner());
        $owner->createTable();

        $ids = [];
        // The recent entry goes in first, so a lookup that takes "the first row" for a number finds the hidden one.
        $rows = [
            'recent' => ['GARCÍA RUIZ, José', 'Calle Mayor 12', '555-0001', self::RECENT],
            'old' => ['GARCÍA LÓPEZ, José', 'Calle Mayor 12', '555-0001', self::OLD],
            'recentOnly' => ['MARTÍN SOTO, Ana', 'Calle Luna 3', '555-0002', self::RECENT],
            'noSource' => ['PÉREZ, Luis', 'Calle Sol 5', '555-0003', null],
        ];
        foreach ($rows as $key => [$name, $street, $phone, $directory]) {
            $ids[$key] = $owner->insert(new PhoneDirectoryEntry(
                fullName: $name,
                countryCode: 'ES',
                street: $street,
                phoneNumber: $phone,
                sourceDirectoryId: $directory,
                language: 'es'
            ));
        }

        $visitor = new PhoneDirectoryPDODatabase($dsn, $user, $password, Privacy::forVisitors(self::today()));

        return [$visitor, $owner, $ids];
    }

    /** @param list<PhoneDirectoryEntry> $entries */
    private static function names(array $entries): array
    {
        return array_map(static fn(PhoneDirectoryEntry $e): string => $e->getRawName(), $entries);
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testAVisitorOnlyGetsTheOldDirectoriesFromEveryFinder(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor] = $this->loaded($dsn, $user, $password);

        $this->assertSame(['GARCÍA LÓPEZ, José'], self::names($visitor->findByName('garcia')));
        $this->assertSame(['GARCÍA LÓPEZ, José'], self::names($visitor->findByStreet('calle mayor')));
        $this->assertSame(['GARCÍA LÓPEZ, José'], self::names($visitor->findBySurnameSound('Garcia', 'es')));
        $this->assertSame(['GARCÍA LÓPEZ, José'], self::names($visitor->getAll()));
        $this->assertSame(['GARCÍA LÓPEZ, José'], self::names($visitor->search([])), 'no criteria means everything allowed');
        $this->assertSame(['GARCÍA LÓPEZ, José'], self::names($visitor->search(['name' => 'garcia'])));
        $this->assertSame([], $visitor->findByName('martin'), 'a recent entry cannot be found by its name');
        $this->assertSame([], $visitor->findByStreet('luna'));
        $this->assertSame([], $visitor->search(['phone' => '555-0002']));
        $this->assertSame(['GARCÍA LÓPEZ, José'], self::names($visitor->findBySourceDirectory(self::OLD)));
        $this->assertSame([], $visitor->findBySourceDirectory(self::RECENT));
        $visitor->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testAVisitorCannotFetchAHiddenEntryByItsId(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, , $ids] = $this->loaded($dsn, $user, $password);

        $this->assertSame('GARCÍA LÓPEZ, José', $visitor->findById($ids['old'])?->getRawName());
        $this->assertNull($visitor->findById($ids['recent']));
        $this->assertNull($visitor->findById($ids['noSource']), 'no directory, no known year: hidden');
        $visitor->disconnect();
    }

    /** The first row for a number can be a hidden one: the lookup must go on to the next. */
    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testAPhoneNumberReusedInALaterEditionStillFindsTheOldEntry(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);

        $this->assertSame('GARCÍA LÓPEZ, José', $visitor->findByPhone('555-0001')?->getRawName());
        $this->assertNull($visitor->findByPhone('555-0002'), 'only a recent entry has this number');
        $this->assertNotNull($owner->findByPhone('555-0002'));
        $this->assertNotNull($owner->findByPhone('555-0001'));
        $visitor->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testTheOwnerSeesEverythingThatIsStored(string $dsn, ?string $user, ?string $password): void
    {
        [, $owner, $ids] = $this->loaded($dsn, $user, $password);

        $this->assertCount(4, $owner->getAll());
        $this->assertCount(2, $owner->findByName('garcia'));
        $this->assertCount(2, $owner->findBySourceDirectory(self::RECENT));
        $this->assertNotNull($owner->findById($ids['recent']));
        $owner->disconnect();
    }

    /** Hiding is about what is shown, not about what is stored: the owner can still count, edit and delete. */
    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testHidingDoesNotChangeWhatIsStoredOrCounted(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);

        $this->assertSame(4, $visitor->count());
        $recent = $owner->findById($ids['recent']);
        $this->assertTrue($owner->delete($ids['recent']));
        $this->assertSame(3, $owner->count());
        $this->assertNotNull($recent);
        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testADatabaseBuiltWithoutSayingWhoIsAskingHidesTheLiving(string $dsn, ?string $user, ?string $password): void
    {
        [, $owner] = $this->loaded($dsn, $user, $password);
        $forgetful = new PhoneDirectoryPDODatabase($dsn, $user, $password);

        $this->assertTrue($forgetful->getPrivacy()->hidesLiving());
        $this->assertSame([], $forgetful->findBySourceDirectory(self::RECENT), 'forgetting hides too much, never too little');
        $this->assertCount(4, $owner->getAll());
        $forgetful->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testTheManagerAnswersWithWhatItsDatabaseMayShow(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $manager = new PhoneDirectoryManagerV2(naturalDatabase: $visitor);

        $this->assertSame(['GARCÍA LÓPEZ, José'], self::names($manager->findNaturalPeopleByName('garcia')));
        $this->assertNull($manager->findNaturalPersonByPhone('555-0002'));
        $this->assertCount(1, $manager->getAllNaturalPeople());
        $this->assertNull($manager->getNaturalPerson($ids['recent']));
        $visitor->disconnect();
        $owner->disconnect();
    }

    /** Joining a person's entries from two editions builds a profile neither had: the same rule applies. */
    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testNoLinkInvolvesAnEntryThatMayBeAlive(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner] = $this->loaded($dsn, $user, $password);
        $all = $owner->getAll();

        $this->assertCount(1, (new RecordLinker(minimumScore: 0.3, privacy: Privacy::forOwner()))->link($all), 'the owner can link García 1930 with García 1975');
        $this->assertSame([], (new RecordLinker(minimumScore: 0.3, privacy: Privacy::forVisitors(self::today())))->link($all));
        $this->assertSame([], (new RecordLinker(minimumScore: 0.3))->link($all), 'the default is the visitor');
        $visitor->disconnect();
        $owner->disconnect();
    }
}
