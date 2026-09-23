<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\TestCase;
use PhoneDirectory\PhoneDirectoryCatalog;

class PhoneDirectoryCatalogTest extends TestCase
{
    private PhoneDirectoryCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new PhoneDirectoryCatalog();
    }

    public function testCatalogHasDirectories(): void
    {
        $all = $this->catalog->getAll();

        $this->assertNotEmpty($all);
        $this->assertGreaterThan(10, count($all));
    }

    public function testGetAllReturnsArray(): void
    {
        $all = $this->catalog->getAll();

        $this->assertIsArray($all);
        foreach ($all as $dir) {
            $this->assertArrayHasKey('title', $dir);
            $this->assertArrayHasKey('year', $dir);
            $this->assertArrayHasKey('country', $dir);
            $this->assertArrayHasKey('estimatedEntries', $dir);
        }
    }

    public function testGetByCountry(): void
    {
        $usDirectories = $this->catalog->getByCountry('US');

        $this->assertNotEmpty($usDirectories);
        foreach ($usDirectories as $dir) {
            $this->assertEquals('US', $dir['country']);
        }
    }

    public function testGetByCountryCaseInsensitive(): void
    {
        $us1 = $this->catalog->getByCountry('US');
        $us2 = $this->catalog->getByCountry('us');

        $this->assertEquals(count($us1), count($us2));
    }

    public function testGetByYear(): void
    {
        $y1950 = $this->catalog->getByYear(1950);

        $this->assertNotEmpty($y1950);
        foreach ($y1950 as $dir) {
            $this->assertEquals(1950, $dir['year']);
        }
    }

    public function testGetByYearRange(): void
    {
        $range = $this->catalog->getByYearRange(1900, 1950);

        $this->assertNotEmpty($range);
        foreach ($range as $dir) {
            $this->assertGreaterThanOrEqual(1900, $dir['year']);
            $this->assertLessThanOrEqual(1950, $dir['year']);
        }
    }

    public function testGetStatistics(): void
    {
        $stats = $this->catalog->getStatistics();

        $this->assertArrayHasKey('totalDirectories', $stats);
        $this->assertArrayHasKey('totalCountries', $stats);
        $this->assertArrayHasKey('totalYears', $stats);
        $this->assertArrayHasKey('totalPages', $stats);
        $this->assertArrayHasKey('totalEntries', $stats);

        $this->assertGreaterThan(0, $stats['totalDirectories']);
        $this->assertGreaterThan(0, $stats['totalCountries']);
        $this->assertGreaterThan(0, $stats['totalPages']);
        $this->assertGreaterThan(0, $stats['totalEntries']);
    }

    public function testStatisticsConsistency(): void
    {
        $stats = $this->catalog->getStatistics();

        $this->assertEquals(
            $stats['totalDirectories'],
            count($this->catalog->getAll())
        );

        $this->assertLessThan(
            $stats['averagePagesPerDirectory'] * 2,
            $stats['totalPages']
        );
    }

    public function testGetCountriesList(): void
    {
        $countries = $this->catalog->getCountriesList();

        $this->assertNotEmpty($countries);
        $this->assertIsArray($countries);

        foreach ($countries as $country) {
            $this->assertEquals(2, strlen($country));
        }
    }

    public function testCountriesListSorted(): void
    {
        $countries = $this->catalog->getCountriesList();
        $sortedCountries = $countries;
        sort($sortedCountries);

        $this->assertEquals($sortedCountries, $countries);
    }

    public function testGetYearsList(): void
    {
        $years = $this->catalog->getYearsList();

        $this->assertNotEmpty($years);
        $this->assertIsArray($years);

        foreach ($years as $year) {
            $this->assertIsInt($year);
            $this->assertGreaterThanOrEqual(1870, $year);
            $this->assertLessThanOrEqual(2024, $year);
        }
    }

    public function testYearsListSorted(): void
    {
        $years = $this->catalog->getYearsList();
        $sortedYears = $years;
        sort($sortedYears);

        $this->assertEquals($sortedYears, $years);
    }

    public function testDirectoriesByCountryAndYear(): void
    {
        $usDirectories = $this->catalog->getByCountry('US');
        $this->assertNotEmpty($usDirectories);

        $us1950 = $this->catalog->getByYear(1950);
        $this->assertNotEmpty($us1950);
    }

    public function testDirectoryMetadata(): void
    {
        $all = $this->catalog->getAll();

        foreach ($all as $dir) {
            $this->assertNotEmpty($dir['title']);
            $this->assertNotEmpty($dir['country']);
            $this->assertGreaterThan(0, $dir['estimatedPages']);
            $this->assertGreaterThan(0, $dir['estimatedEntries']);
            $this->assertNotEmpty($dir['format']);
        }
    }

    public function testEstimatedDataRealistic(): void
    {
        $all = $this->catalog->getAll();

        foreach ($all as $dir) {
            $entriesPerPage = $dir['estimatedEntries'] / $dir['estimatedPages'];

            $this->assertGreaterThan(1, $entriesPerPage);
            $this->assertLessThan(1000, $entriesPerPage);
        }
    }

    public function testMultipleCountriesRepresented(): void
    {
        $countries = $this->catalog->getCountriesList();

        $expectedCountries = ['US', 'GB', 'FR', 'DE', 'ES'];
        foreach ($expectedCountries as $country) {
            $this->assertContains($country, $countries);
        }
    }

    public function testYearRangeSpansCenturies(): void
    {
        $stats = $this->catalog->getStatistics();

        $this->assertGreaterThanOrEqual(1870, $stats['yearsSpan']['earliest']);
        $this->assertLessThanOrEqual(2024, $stats['yearsSpan']['latest']);
    }
}
