<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\Privacy;

/**
 * limit/offset on every finder, on every engine CrossDatabaseTest runs on.
 *
 * "Today" is 2026-10-20 (limit 1933). Four kinds of rows: an old catalog directory (shown), a recent one (cut in
 * SQL for a visitor), and two directories outside the catalog whose year is in their id (custom_1920_*: shown,
 * custom_1990_*: hidden), which only Privacy can judge in PHP. The pages of a visitor must be counted over what
 * the visitor sees, so none of them comes up short because of the rows PHP dropped.
 */
class PaginationTest extends TestCase
{
    private const DIRECTORIES = ['es_1930_madrid', 'es_1975_national', 'custom_1920_pueblo', 'custom_1990_pueblo'];

    private static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-20');
    }

    private static function reset(string $dsn, ?string $user, ?string $password): void
    {
        $pdo = new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('DROP TABLE IF EXISTS phone_directory');
        $pdo->exec('DROP TABLE IF EXISTS juridical_entities');
    }

    /** @return array{PhoneDirectoryPDODatabase, PhoneDirectoryPDODatabase} [visitor, owner]: 40 people, 10 per directory */
    private function people(string $dsn, ?string $user, ?string $password): array
    {
        self::reset($dsn, $user, $password);
        $owner = new PhoneDirectoryPDODatabase($dsn, $user, $password, Privacy::forOwner());
        $owner->createTable();

        for ($i = 1; $i <= 40; $i++) {
            $owner->insert(new PhoneDirectoryEntry(
                fullName: sprintf('GARCÍA, Juan %02d', $i),
                countryCode: 'ES',
                street: 'Calle Mayor ' . $i,
                phoneNumber: sprintf('555-%04d', $i),
                sourceDirectoryId: self::DIRECTORIES[$i % 4],
                language: 'es'
            ));
        }

        return [new PhoneDirectoryPDODatabase($dsn, $user, $password, Privacy::forVisitors(self::today())), $owner];
    }

    /** @return array{JuridicalEntityPDODatabase, JuridicalEntityPDODatabase} [visitor, owner]: 40 businesses, 20 with a given name */
    private function businesses(string $dsn, ?string $user, ?string $password): array
    {
        self::reset($dsn, $user, $password);
        $owner = new JuridicalEntityPDODatabase($dsn, $user, $password, Privacy::forOwner());
        $owner->createTable();

        for ($i = 1; $i <= 40; $i++) {
            $owner->insert(new JuridicalEntity(
                businessName: ($i % 2 === 0 ? 'Panadería Juan ' : 'Taller ') . sprintf('%02d', $i),
                street: 'Calle Mayor ' . $i,
                businessType: 'taller',
                countryCode: 'ES',
                sourceDirectoryId: self::DIRECTORIES[intdiv($i, 2) % 4]
            ));
        }

        return [new JuridicalEntityPDODatabase($dsn, $user, $password, Privacy::forVisitors(self::today())), $owner];
    }

    /**
     * Reads every page of $size with $page and checks them against the whole result: the same rows in the same order,
     * no page short except the last, and nothing past the end.
     *
     * @param callable(?int, int): array<int, object> $page
     * @return list<string> the whole result, as names
     */
    private function assertPagesAddUpTo(callable $page, int $size): array
    {
        $name = fn(object $e) => $e instanceof PhoneDirectoryEntry ? $e->getRawName() : $e->getBusinessName();
        $whole = array_map($name, $page(null, 0));
        $this->assertNotEmpty($whole);

        $paged = [];
        for ($offset = 0; ; $offset += $size) {
            $rows = array_map($name, $page($size, $offset));
            $paged = [...$paged, ...$rows];
            if (count($rows) < $size) {
                break;
            }
            $this->assertLessThan(1000, $offset, 'the pages never end');
        }

        $this->assertSame($whole, $paged);
        $this->assertSame([], $page($size, count($whole)), 'past the end');
        $this->assertSame(array_slice($whole, 3), array_map($name, $page(null, 3)), 'an offset with no limit');

        return $whole;
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testAVisitorsPagesOfPeopleAreCountedOverWhatTheyCanSee(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner] = $this->people($dsn, $user, $password);

        $seen = $this->assertPagesAddUpTo(fn($limit, $offset) => $visitor->getAll($limit, $offset), 4);

        $this->assertCount(20, $seen, 'the 1930 and 1920 directories');
        $this->assertCount(40, $owner->getAll());

        foreach ([
            'findByName' => fn($l, $o) => $visitor->findByName('garcia', $l, $o),
            'findByStreet' => fn($l, $o) => $visitor->findByStreet('calle mayor', $l, $o),
            'findBySurnameSound' => fn($l, $o) => $visitor->findBySurnameSound('Garcia', 'es', $l, $o),
            'search' => fn($l, $o) => $visitor->search(['name' => 'juan'], $l, $o),
            'search with no criteria' => fn($l, $o) => $visitor->search([], $l, $o),
        ] as $name => $finder) {
            $this->assertEqualsCanonicalizing($seen, $this->assertPagesAddUpTo($finder, 3), $name);
        }

        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testTheOwnersPagesAreInOrderAndTheLastOneIsShort(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner] = $this->people($dsn, $user, $password);

        $all = $this->assertPagesAddUpTo(fn($limit, $offset) => $owner->getAll($limit, $offset), 7);

        $this->assertCount(40, $all);
        $this->assertSame(['GARCÍA, Juan 01', 'GARCÍA, Juan 02', 'GARCÍA, Juan 03'], array_slice($all, 0, 3));
        $this->assertCount(3, $owner->findBySourceDirectory('es_1975_national', 3));
        $this->assertCount(4, $owner->findBySourceDirectory('es_1975_national', 5, 6), '10 rows in all: the page after 6 has 4');
        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testAVisitorsPagesOfBusinessesDoNotComeUpShortForTheOnesWithAGivenName(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner] = $this->businesses($dsn, $user, $password);

        $seen = $this->assertPagesAddUpTo(fn($limit, $offset) => $visitor->getAll($limit, $offset), 4);

        $this->assertCount(30, $seen, '20 with no given name and 10 with one in an old directory');
        $this->assertCount(40, $owner->getAll());

        foreach ([
            'findByBusinessName' => fn($l, $o) => $visitor->findByBusinessName('a', $l, $o),
            'findByStreet' => fn($l, $o) => $visitor->findByStreet('calle mayor', $l, $o),
            'findByBusinessType' => fn($l, $o) => $visitor->findByBusinessType('taller', $l, $o),
            'search' => fn($l, $o) => $visitor->search(['businessType' => 'taller'], $l, $o),
        ] as $name => $finder) {
            $this->assertEqualsCanonicalizing($seen, $this->assertPagesAddUpTo($finder, 3), $name);
        }

        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testAPageOfSizeOneWalksTheWholeResult(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner] = $this->people($dsn, $user, $password);

        $this->assertCount(20, $this->assertPagesAddUpTo(fn($limit, $offset) => $visitor->getAll($limit, $offset), 1));

        $visitor->disconnect();
        $owner->disconnect();
    }

    #[DataProviderExternal(CrossDatabaseTest::class, 'databases')]
    public function testAnInvalidLimitOrOffsetIsRefused(string $dsn, ?string $user, ?string $password): void
    {
        [$visitor, $owner] = $this->people($dsn, $user, $password);

        foreach ([[0, 0], [-1, 0], [5, -1]] as [$limit, $offset]) {
            try {
                $visitor->getAll($limit, $offset);
                $this->fail("limit {$limit}, offset {$offset} should be refused");
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('must', $e->getMessage());
            }
        }

        $visitor->disconnect();
        $owner->disconnect();
    }
}
