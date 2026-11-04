<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\Privacy;

/**
 * Businesses named after a person are hidden from a visitor like a person who may be alive (issue #233).
 * "Today" is 2026-10-20, so the limit is 1933: the 1930 Madrid directory may be shown, the 1975 one may not.
 */
class BusinessPrivacyTest extends TestCase
{
    private const OLD = 'es_1930_madrid';
    private const RECENT = 'es_1975_national';

    private ?string $file = null;

    protected function tearDown(): void
    {
        if ($this->file !== null && file_exists($this->file)) {
            unlink($this->file);
        }
    }

    /** @return array{JuridicalEntityPDODatabase, JuridicalEntityPDODatabase} [visitor, owner] */
    private function loaded(): array
    {
        $file = tempnam(sys_get_temp_dir(), 'juridical');
        $this->assertNotFalse($file);
        $this->file = $file;
        $dsn = "sqlite:{$file}";

        $owner = new JuridicalEntityPDODatabase($dsn, privacy: Privacy::forOwner());
        $owner->createTable();
        $rows = [
            // The recent one goes in first, so a lookup that takes "the first row" for a number finds the hidden one.
            ['Panadería Juan Pérez', 'Calle Mayor 12', '555-0001', self::RECENT],
            ['Panadería Juan Pérez', 'Calle Mayor 12', '555-0001', self::OLD],
            ['Restaurante Siglo 21', 'Calle Luna 3', '555-0002', self::RECENT],
            ['Ferretería José', 'Calle Sol 5', '555-0003', null],
        ];
        foreach ($rows as [$name, $street, $phone, $directory]) {
            $owner->insert(new JuridicalEntity(
                businessName: $name,
                street: $street,
                phoneNumber: $phone,
                countryCode: 'ES',
                sourceDirectoryId: $directory
            ));
        }

        return [new JuridicalEntityPDODatabase($dsn, privacy: Privacy::forVisitors(new \DateTimeImmutable('2026-10-20'))), $owner];
    }

    public function testVisitorDoesNotSeeARecentBusinessNamedAfterAPerson(): void
    {
        [$visitor, $owner] = $this->loaded();

        $shown = array_map(fn($b) => [$b->getBusinessName(), $b->getSourceDirectoryId()], $visitor->getAll());

        $this->assertSame([['Panadería Juan Pérez', self::OLD], ['Restaurante Siglo 21', self::RECENT]], $shown);
        $this->assertCount(4, $owner->getAll());
    }

    public function testEveryFinderFilters(): void
    {
        [$visitor] = $this->loaded();

        $this->assertCount(1, $visitor->findByBusinessName('Juan'));
        $this->assertCount(1, $visitor->findByStreet('Calle Mayor'));
        $this->assertSame([], $visitor->findByStreet('Calle Sol'), 'sin año de guía, se oculta');
        $this->assertCount(1, $visitor->search(['businessName' => 'panaderia']));
        $this->assertNull($visitor->findById(1), 'la de 1975');
        $this->assertNotNull($visitor->findById(2), 'la de 1930');
        $this->assertNotNull($visitor->findById(3), 'sin nombre de persona');
    }

    public function testFindByPhoneSkipsTheHiddenEditionOfAReusedNumber(): void
    {
        [$visitor, $owner] = $this->loaded();

        $this->assertSame(self::OLD, $visitor->findByPhone('555-0001')?->getSourceDirectoryId());
        $this->assertSame(self::RECENT, $owner->findByPhone('555-0001')?->getSourceDirectoryId());
        $this->assertNull($visitor->findByPhone('555-0003'));
    }

    public function testDefaultIsTheVisitorView(): void
    {
        $this->assertTrue((new JuridicalEntityPDODatabase())->getPrivacy()->hidesLiving());
    }
}
