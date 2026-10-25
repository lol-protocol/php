<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\Privacy;

/**
 * The rule: a directory of year Y lists adults, so nobody in it was born after Y - 18; that person is
 * presumed alive for 110 years, so an entry may be shown only when Y - 18 + 110 is already in the past.
 */
class PrivacyTest extends TestCase
{
    private static function entry(?string $directory, string $name = 'GARCÍA, José'): PhoneDirectoryEntry
    {
        return new PhoneDirectoryEntry(
            fullName: $name,
            countryCode: 'ES',
            street: 'Calle Mayor 12',
            phoneNumber: '555-123-4567',
            sourceDirectoryId: $directory
        );
    }

    /** @return array<string, array{string, int}> [today, latest publishable year] */
    public static function limits(): array
    {
        return [
            'hoy' => ['2026-10-20', 1933],
            'primer día del año' => ['2026-01-01', 1933],
            'último día del año' => ['2026-12-31', 1933],
            'el año siguiente' => ['2027-01-01', 1934],
            'bisiesto' => ['2028-02-29', 1935],
        ];
    }

    #[DataProvider('limits')]
    public function testTheLimitMovesOneYearPerYear(string $today, int $latest): void
    {
        $privacy = Privacy::forVisitors(new \DateTimeImmutable($today));

        $this->assertSame($latest, $privacy->latestPublishableYear());
        $this->assertTrue($privacy->hidesLiving());
    }

    public function testTheConstantsAreTheOnesTheRuleIsBuiltOn(): void
    {
        $this->assertSame(110, Privacy::LIVING_YEARS);
        $this->assertSame(18, Privacy::MINIMUM_SUBSCRIBER_AGE);
    }

    /** Y - 18 + 110 must be strictly in the past: the last year the rule allows is this year - 93. */
    public function testTheBoundaryYearIsHiddenAndTheOneBeforeItIsNot(): void
    {
        $privacy = Privacy::forVisitors(new \DateTimeImmutable('2026-10-20'));

        $this->assertTrue($privacy->allows(self::entry('custom_1933_madrid')));
        $this->assertTrue($privacy->mayBeAlive(self::entry('custom_1934_madrid')));
        $this->assertTrue($privacy->mayBeAlive(self::entry('custom_1975_madrid')));
        $this->assertTrue($privacy->allows(self::entry('custom_1900_madrid')));
    }

    public function testTheCatalogsYearsDecideTheirDirectories(): void
    {
        $privacy = Privacy::forVisitors(new \DateTimeImmutable('2026-10-20'));

        $this->assertTrue($privacy->allows(self::entry('es_1930_madrid')), '1930 is before the limit');
        $this->assertTrue($privacy->allows(self::entry('us_1878_ny')));
        $this->assertTrue($privacy->mayBeAlive(self::entry('es_1975_national')), '1975 is not');
    }

    /** Nothing says how old these people are, and a mistake publishes a phone number: hide them. */
    public function testAnEntryWhoseDirectoryHasNoKnownYearIsHidden(): void
    {
        $privacy = Privacy::forVisitors(new \DateTimeImmutable('2026-10-20'));

        $this->assertTrue($privacy->mayBeAlive(self::entry(null)));
        $this->assertTrue($privacy->mayBeAlive(self::entry('')));
        $this->assertTrue($privacy->mayBeAlive(self::entry('mi_guia_sin_anio')));
        $this->assertTrue($privacy->mayBeAlive(self::entry('guia_12345_x')), 'digits that are not a year');
    }

    public function testTheOwnerSeesEverythingAndHasNoLimit(): void
    {
        $owner = Privacy::forOwner();

        $this->assertFalse($owner->hidesLiving());
        $this->assertNull($owner->latestPublishableYear());
        foreach ([null, 'es_1975_national', 'custom_2020_x', 'sin_anio'] as $directory) {
            $this->assertFalse($owner->mayBeAlive(self::entry($directory)), (string) $directory);
            $this->assertTrue($owner->allows(self::entry($directory)));
        }
    }

    public function testFilterKeepsTheOrderAndDropsOnlyTheHidden(): void
    {
        $privacy = Privacy::forVisitors(new \DateTimeImmutable('2026-10-20'));
        $a = self::entry('es_1930_madrid', 'A, a');
        $b = self::entry('es_1975_national', 'B, b');
        $c = self::entry('us_1878_ny', 'C, c');
        $d = self::entry(null, 'D, d');

        $this->assertSame([$a, $c], $privacy->filter([$a, $b, $c, $d]));
        $this->assertSame([], $privacy->filter([]));
        $this->assertSame([$a], $privacy->filter((static function () use ($a, $b) {
            yield 'x' => $a;
            yield 'y' => $b;
        })()), 'any iterable, and the result is a list');
        $this->assertSame([$a, $b, $c, $d], Privacy::forOwner()->filter([$a, $b, $c, $d]));
    }

    public function testAPersonRecordedAsDeadMayBeShownWhateverTheDirectoryYear(): void
    {
        $privacy = Privacy::forVisitors(new \DateTimeImmutable('2026-10-20'));
        $recent = self::entry('es_1975_national');
        $dead = $recent->withDeceasedYear(1988);

        $this->assertTrue($privacy->mayBeAlive($recent));
        $this->assertFalse($privacy->mayBeAlive($dead));
        $this->assertTrue($privacy->allows($dead));
        $this->assertSame(1988, $dead->getDeceasedYear());
        $this->assertSame([$dead], $privacy->filter([$recent, $dead]));
    }

    /** No directory, or one with no year, says nothing about when the person lived; a recorded death does. */
    public function testARecordedDeathAlsoLiftsAnEntryWithoutAKnownYear(): void
    {
        $privacy = Privacy::forVisitors(new \DateTimeImmutable('2026-10-20'));

        $this->assertTrue($privacy->allows(self::entry(null)->withDeceasedYear(1990)));
        $this->assertTrue($privacy->allows(self::entry('mi_guia_sin_anio')->withDeceasedYear(1990)));
    }

    public function testWithDeceasedYearReturnsACopyAndNullTakesTheMarkBack(): void
    {
        $recent = self::entry('es_1975_national');
        $dead = $recent->withDeceasedYear(1988);
        $privacy = Privacy::forVisitors(new \DateTimeImmutable('2026-10-20'));

        $this->assertNull($recent->getDeceasedYear(), 'the original is not changed');
        $this->assertNotSame($recent, $dead);
        $this->assertTrue($privacy->mayBeAlive($dead->withDeceasedYear(null)));
        $this->assertSame(1988, $dead->toArray()['deceasedYear']);
        $this->assertNull($recent->toArray()['deceasedYear']);
    }
}
