<?php

namespace PhoneDirectory;

class PhoneDirectoryCatalog
{
    private array $directories = [];

    public function __construct()
    {
        $this->loadHistoricalDirectories();
    }

    private function loadHistoricalDirectories(): void
    {
        $this->directories = [
            // USA - Bell Telephone Directories
            'us_1878_ny' => [
                'title' => 'New York Telephone Directory',
                'year' => 1878,
                'country' => 'US',
                'zone' => 'New York',
                'city' => 'New York',
                'estimatedPages' => 150,
                'estimatedEntries' => 3000,
                'archiveUrl' => 'https://archive.org/details/newyorktelephone1878',
                'format' => 'PDF',
            ],
            'us_1915_national' => [
                'title' => 'Bell Telephone Directory - National',
                'year' => 1915,
                'country' => 'US',
                'zone' => 'Multiple',
                'city' => 'National',
                'estimatedPages' => 2000,
                'estimatedEntries' => 500000,
                'archiveUrl' => 'https://archive.org/search.php?query=telephone+directory+1915',
                'format' => 'PDF',
            ],
            'us_1950_comprehensive' => [
                'title' => 'AT&T Telephone Directory - Comprehensive',
                'year' => 1950,
                'country' => 'US',
                'zone' => 'Multiple',
                'city' => 'National',
                'estimatedPages' => 5000,
                'estimatedEntries' => 2000000,
                'archiveUrl' => 'https://archive.org/search.php?query=telephone+directory+1950',
                'format' => 'PDF',
            ],

            // UK
            'uk_1880_london' => [
                'title' => 'London Telephone Directory',
                'year' => 1880,
                'country' => 'GB',
                'zone' => 'England',
                'city' => 'London',
                'estimatedPages' => 200,
                'estimatedEntries' => 5000,
                'archiveUrl' => 'https://archive.org/details/londontelephone1880',
                'format' => 'PDF',
            ],
            'uk_1930_comprehensive' => [
                'title' => 'GPO Telephone Directory - UK',
                'year' => 1930,
                'country' => 'GB',
                'zone' => 'Multiple',
                'city' => 'National',
                'estimatedPages' => 3000,
                'estimatedEntries' => 1500000,
                'archiveUrl' => 'https://archive.org/search.php?query=GPO+telephone+directory+1930',
                'format' => 'PDF',
            ],

            // France
            'fr_1900_paris' => [
                'title' => 'Annuaire Téléphonique de Paris',
                'year' => 1900,
                'country' => 'FR',
                'zone' => 'Île-de-France',
                'city' => 'Paris',
                'estimatedPages' => 300,
                'estimatedEntries' => 10000,
                'archiveUrl' => 'https://archive.org/search.php?query=annuaire+telephonique+paris+1900',
                'format' => 'PDF',
            ],
            'fr_1960_national' => [
                'title' => 'Annuaire National - France',
                'year' => 1960,
                'country' => 'FR',
                'zone' => 'Multiple',
                'city' => 'National',
                'estimatedPages' => 8000,
                'estimatedEntries' => 3000000,
                'archiveUrl' => 'https://archive.org/search.php?query=annuaire+telephonique+1960+france',
                'format' => 'PDF',
            ],

            // Germany
            'de_1890_berlin' => [
                'title' => 'Berliner Adressbuch und Telefonverzeichnis',
                'year' => 1890,
                'country' => 'DE',
                'zone' => 'Berlin',
                'city' => 'Berlin',
                'estimatedPages' => 250,
                'estimatedEntries' => 8000,
                'archiveUrl' => 'https://archive.org/search.php?query=berliner+adressbuch+1890',
                'format' => 'PDF',
            ],
            'de_1970_national' => [
                'title' => 'Deutsche Telefonverzeichnis',
                'year' => 1970,
                'country' => 'DE',
                'zone' => 'Multiple',
                'city' => 'National',
                'estimatedPages' => 12000,
                'estimatedEntries' => 5000000,
                'archiveUrl' => 'https://archive.org/search.php?query=telefonverzeichnis+1970',
                'format' => 'PDF',
            ],

            // Spain
            'es_1930_madrid' => [
                'title' => 'Guía Telefónica de Madrid',
                'year' => 1930,
                'country' => 'ES',
                'zone' => 'Madrid',
                'city' => 'Madrid',
                'estimatedPages' => 400,
                'estimatedEntries' => 15000,
                'archiveUrl' => 'https://archive.org/search.php?query=guia+telefonica+madrid+1930',
                'format' => 'PDF',
            ],
            'es_1975_national' => [
                'title' => 'Guía Telefónica Nacional - España',
                'year' => 1975,
                'country' => 'ES',
                'zone' => 'Multiple',
                'city' => 'National',
                'estimatedPages' => 10000,
                'estimatedEntries' => 4000000,
                'archiveUrl' => 'https://archive.org/search.php?query=guia+telefonica+1975+españa',
                'format' => 'PDF',
            ],

            // Italy
            'it_1920_rome' => [
                'title' => 'Elenco Telefonico di Roma',
                'year' => 1920,
                'country' => 'IT',
                'zone' => 'Lazio',
                'city' => 'Rome',
                'estimatedPages' => 300,
                'estimatedEntries' => 12000,
                'archiveUrl' => 'https://archive.org/search.php?query=elenco+telefonico+roma+1920',
                'format' => 'PDF',
            ],

            // Latin America
            'mx_1960_mexico' => [
                'title' => 'Directorio Telefónico de México',
                'year' => 1960,
                'country' => 'MX',
                'zone' => 'Multiple',
                'city' => 'National',
                'estimatedPages' => 3000,
                'estimatedEntries' => 800000,
                'archiveUrl' => 'https://archive.org/search.php?query=directorio+telefonico+mexico+1960',
                'format' => 'PDF',
            ],
            'ar_1970_buenos_aires' => [
                'title' => 'Guía Telefónica de Buenos Aires',
                'year' => 1970,
                'country' => 'AR',
                'zone' => 'Buenos Aires',
                'city' => 'Buenos Aires',
                'estimatedPages' => 2500,
                'estimatedEntries' => 600000,
                'archiveUrl' => 'https://archive.org/search.php?query=guia+telefonica+buenos+aires+1970',
                'format' => 'PDF',
            ],

            // Canada
            'ca_1940_toronto' => [
                'title' => 'Bell Canada Telephone Directory - Toronto',
                'year' => 1940,
                'country' => 'CA',
                'zone' => 'Ontario',
                'city' => 'Toronto',
                'estimatedPages' => 1200,
                'estimatedEntries' => 250000,
                'archiveUrl' => 'https://archive.org/search.php?query=bell+canada+toronto+1940',
                'format' => 'PDF',
            ],

            // Australia
            'au_1950_sydney' => [
                'title' => 'Postmaster-General Telephone Directory - Sydney',
                'year' => 1950,
                'country' => 'AU',
                'zone' => 'New South Wales',
                'city' => 'Sydney',
                'estimatedPages' => 800,
                'estimatedEntries' => 200000,
                'archiveUrl' => 'https://archive.org/search.php?query=telephone+directory+sydney+1950',
                'format' => 'PDF',
            ],
        ];
    }

    public function getAll(): array
    {
        return $this->directories;
    }

    public function getByCountry(string $countryCode): array
    {
        return array_filter(
            $this->directories,
            fn($dir) => $dir['country'] === strtoupper($countryCode)
        );
    }

    public function getByYear(int $year): array
    {
        return array_filter(
            $this->directories,
            fn($dir) => $dir['year'] === $year
        );
    }

    public function getByYearRange(int $startYear, int $endYear): array
    {
        return array_filter(
            $this->directories,
            fn($dir) => $dir['year'] >= $startYear && $dir['year'] <= $endYear
        );
    }

    public function getStatistics(): array
    {
        $totalPages = array_sum(array_column($this->directories, 'estimatedPages'));
        $totalEntries = array_sum(array_column($this->directories, 'estimatedEntries'));
        $countries = array_unique(array_column($this->directories, 'country'));
        $years = array_unique(array_column($this->directories, 'year'));

        return [
            'totalDirectories' => count($this->directories),
            'totalCountries' => count($countries),
            'totalYears' => count($years),
            'yearsSpan' => [
                'earliest' => min($years),
                'latest' => max($years),
            ],
            'totalPages' => $totalPages,
            'totalEntries' => $totalEntries,
            'averagePagesPerDirectory' => (int) ($totalPages / count($this->directories)),
            'averageEntriesPerDirectory' => (int) ($totalEntries / count($this->directories)),
        ];
    }

    public function getCountriesList(): array
    {
        $countries = array_unique(array_column($this->directories, 'country'));
        sort($countries);
        return $countries;
    }

    public function getYearsList(): array
    {
        $years = array_unique(array_column($this->directories, 'year'));
        sort($years);
        return $years;
    }
}
