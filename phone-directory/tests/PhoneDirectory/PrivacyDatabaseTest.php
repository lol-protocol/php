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

    // --- a person recorded as dead -------------------------------------------------------------------------

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testARecordedDeathMakesTheEntryVisibleToVisitorsThroughEveryFinder(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $this->assertNull($visitor->findById($ids['recent']));

        $this->assertTrue($owner->markDeceased($ids['recent'], 1988, 'Esquela, La Vanguardia, 12/03/1988'));

        $this->assertSame('GARCÍA RUIZ, José', $visitor->findById($ids['recent'])?->getRawName());
        $this->assertSame(1988, $visitor->findById($ids['recent'])?->getDeceasedYear());
        $this->assertSame(['GARCÍA LÓPEZ, José', 'GARCÍA RUIZ, José'], self::names($visitor->findByName('garcia')));
        $this->assertSame(['GARCÍA RUIZ, José'], self::names($visitor->findBySourceDirectory(self::RECENT)));
        $this->assertSame(['GARCÍA LÓPEZ, José', 'GARCÍA RUIZ, José'], self::names($visitor->findBySurnameSound('Garcia', 'es')));
        $this->assertCount(2, $visitor->getAll());
        $this->assertSame(['GARCÍA RUIZ, José'], self::names($visitor->search(['street' => 'calle mayor 12', 'name' => 'ruiz'])));
        // The other recent entries are still hidden: the mark is per person.
        $this->assertNull($visitor->findById($ids['recentOnly']));
        $this->assertSame([], $visitor->findByName('martin'));
        $visitor->disconnect();
        $owner->disconnect();
    }

    /** The first row for a number is the recent García, now visible; the old one must not be lost behind it. */
    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testAMarkedEntryDoesNotHideTheOtherOneThatSharesItsPhoneNumber(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $owner->markDeceased($ids['recentOnly'], 1990, 'Acta de defunción n.º 5, Registro Civil de Madrid');

        $this->assertSame('MARTÍN SOTO, Ana', $visitor->findByPhone('555-0002')?->getRawName());
        $this->assertNotNull($visitor->findByPhone('555-0001'));
        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testTakingTheMarkBackHidesTheEntryAgain(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $owner->markDeceased($ids['recent'], 1988, 'Esquela');
        $this->assertNotNull($visitor->findById($ids['recent']));

        $this->assertTrue($owner->clearDeceased($ids['recent']));

        $this->assertNull($visitor->findById($ids['recent']));
        $this->assertNull($owner->findById($ids['recent'])?->getDeceasedYear());
        $this->assertNull($owner->getDeceasedEvidence($ids['recent']), 'the evidence goes with the mark');
        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testOnlyTheOwnerCanRecordOrTakeBackADeath(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $owner->markDeceased($ids['old'], 1935, 'Esquela');

        foreach ([
            fn() => $visitor->markDeceased($ids['recent'], 1988, 'Esquela'),
            fn() => $visitor->clearDeceased($ids['old']),
            fn() => $visitor->getDeceasedEvidence($ids['old']),
        ] as $i => $call) {
            try {
                $call();
                $this->fail("call {$i}: a database opened for visitors must refuse");
            } catch (\LogicException $e) {
                $this->assertStringContainsString('opened for visitors', $e->getMessage());
            }
        }

        $this->assertNull($visitor->findById($ids['recent']), 'the refused mark left nothing behind');
        $this->assertSame(1935, $owner->findById($ids['old'])?->getDeceasedYear(), 'nor was the other one taken back');
        $forgetful = new PhoneDirectoryPDODatabase($dsn, $user, $password);
        $this->expectException(\LogicException::class);
        $forgetful->markDeceased($ids['recent'], 1988, 'Esquela');
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testADeathNeedsEvidenceAndAYearThatFits(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $thisYear = (int) date('Y');

        $refused = [
            'empty evidence' => [$ids['recent'], 1988, '', 'Say where the death is documented'],
            'blank evidence' => [$ids['recent'], 1988, " \n ", 'Say where the death is documented'],
            'evidence that is a whole document' => [$ids['recent'], 1988, str_repeat('a', 1001), 'longer than 1000'],
            'a year in the future' => [$ids['recent'], $thisYear + 1, 'Esquela', 'must be between 1800 and ' . $thisYear],
            'a year before the catalog' => [$ids['recent'], 1799, 'Esquela', 'must be between 1800'],
            'a death two years before the 1975 directory' => [$ids['recent'], 1973, 'Esquela', 'a different person'],
            'an id of 0' => [0, 1988, 'Esquela', ''],
        ];
        foreach ($refused as $name => [$id, $year, $evidence, $message]) {
            try {
                $owner->markDeceased($id, $year, $evidence);
                $this->fail("{$name}: should have been refused");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $name);
            }
        }
        $this->assertNull($visitor->findById($ids['recent']), 'no refused mark published anything');

        // The year before the directory is the earliest that fits: it lists the people alive when it was compiled.
        $this->assertTrue($owner->markDeceased($ids['recent'], 1974, 'Esquela'));
        // A well-formed mark on an entry that does not exist is not an error: there is nothing to mark.
        $this->assertFalse($owner->markDeceased(987654, 1988, 'Esquela'));
        $this->assertFalse($owner->clearDeceased(987654));
        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testTheEvidenceIsOnlyForTheOwnerAndNeverPartOfTheEntry(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $owner->markDeceased($ids['recent'], 1988, '  Esquela de su viuda Carmen, La Vanguardia  ');

        $this->assertSame('Esquela de su viuda Carmen, La Vanguardia', $owner->getDeceasedEvidence($ids['recent']));
        $this->assertNull($owner->getDeceasedEvidence($ids['old']), 'no mark, no evidence');
        $seen = $visitor->findById($ids['recent']);
        $this->assertNotNull($seen);
        $this->assertStringNotContainsString('viuda', json_encode($seen->toArray(), JSON_UNESCAPED_UNICODE));
        $this->assertSame(1988, $seen->toArray()['deceasedYear']);
        $visitor->disconnect();
        $owner->disconnect();
    }

    /** Editing an entry must not publish or hide a person by accident, and a stored entry cannot carry a mark in. */
    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testUpdateAndInsertLeaveTheMarkAlone(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $owner->markDeceased($ids['recent'], 1988, 'Esquela');

        $edited = $owner->findById($ids['recent']);
        $this->assertNotNull($edited);
        $this->assertTrue($owner->update($edited));
        $this->assertSame(1988, $owner->findById($ids['recent'])?->getDeceasedYear(), 'update() keeps the mark');
        $this->assertNotNull($visitor->findById($ids['recent']));

        $unchecked = $owner->insert((new PhoneDirectoryEntry(
            fullName: 'RUIZ, Pablo',
            countryCode: 'ES',
            street: 'Calle Sol 9',
            phoneNumber: '555-0004',
            sourceDirectoryId: self::RECENT,
            language: 'es'
        ))->withDeceasedYear(1999));
        $this->assertNull($owner->findById($unchecked)?->getDeceasedYear(), 'insert() does not store a death nobody documented');
        $this->assertNull($visitor->findById($unchecked));
        $visitor->disconnect();
        $owner->disconnect();
    }

    /** Joining the entries of a person who is recorded as dead builds no profile of someone who may be alive. */
    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testARecordedDeathLetsTheLinkerJoinThatPersonsEditions(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $owner->markDeceased($ids['recent'], 1988, 'Esquela');

        $links = (new RecordLinker(minimumScore: 0.3, privacy: Privacy::forVisitors(self::today())))->link($owner->getAll());

        $this->assertCount(1, $links);
        $this->assertSame($ids['recent'], $links[0]->later->getId());
        $visitor->disconnect();
        $owner->disconnect();
    }

    /** A database made before the columns existed gets them the next time createTable() runs. */
    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testADatabaseFromBeforeTheMarkExistedIsUpgraded(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner, $ids] = $this->loaded($dsn, $user, $password);
        $pdo = new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('ALTER TABLE phone_directory DROP COLUMN deceased_year');
        $pdo->exec('ALTER TABLE phone_directory DROP COLUMN deceased_evidence');

        $owner->createTable();

        $this->assertNull($owner->findById($ids['old'])?->getDeceasedYear());
        $this->assertTrue($owner->markDeceased($ids['recent'], 1988, 'Esquela'));
        $this->assertNotNull($visitor->findById($ids['recent']));
        $this->assertCount(4, $owner->getAll(), 'the rows that were there are still there');
        $visitor->disconnect();
        $owner->disconnect();
    }
}
