<?php

namespace Tests\PhoneDirectory;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use PhoneDirectory\Entity\JuridicalEntity;
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\Entity\PhoneDirectoryEntry;
use PhoneDirectory\PhoneDirectoryPDODatabase;

/**
 * Runs against SQLite always, and against PostgreSQL / MySQL when these are set:
 *   PHONEDIR_PGSQL_DSN   e.g. pgsql:host=127.0.0.1;port=5432;dbname=phonedir_test;user=postgres
 *   PHONEDIR_MYSQL_DSN   e.g. mysql:host=127.0.0.1;dbname=phonedir_test;charset=utf8mb4
 *   PHONEDIR_MYSQL_USER, PHONEDIR_MYSQL_PASSWORD
 */
class CrossDatabaseTest extends TestCase
{
    private const LEGACY_ID = [
        'sqlite' => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
        'pgsql' => 'SERIAL PRIMARY KEY',
    ];

    public static function databases(): array
    {
        $databases = [
            'sqlite' => ['sqlite:' . sys_get_temp_dir() . '/phonedir_cross_test.sqlite', null, null],
        ];

        if ($dsn = getenv('PHONEDIR_PGSQL_DSN')) {
            $databases['pgsql'] = [$dsn, null, null];
        }

        if ($dsn = getenv('PHONEDIR_MYSQL_DSN')) {
            $databases['mysql'] = [$dsn, getenv('PHONEDIR_MYSQL_USER') ?: null, getenv('PHONEDIR_MYSQL_PASSWORD') ?: null];
        }

        return $databases;
    }

    private function rawConnection(string $dsn, ?string $user, ?string $password): \PDO
    {
        $pdo = new \PDO($dsn, $user, $password, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('DROP TABLE IF EXISTS phone_directory');
        $pdo->exec('DROP TABLE IF EXISTS juridical_entities');

        return $pdo;
    }

    #[DataProvider('databases')]
    public function testEntryRoundTripWithHistoricalDate(string $dsn, ?string $user, ?string $password): void
    {
        $this->rawConnection($dsn, $user, $password);
        $database = new PhoneDirectoryPDODatabase($dsn, $user, $password);
        $database->createTable();
        $database->createTable();

        $entry = new PhoneDirectoryEntry(
            fullName: 'GARCÍA LÓPEZ, José',
            countryCode: 'ES',
            street: 'Calle Mayor 12',
            phoneNumber: '555-123-4567',
            zone: 'Madrid',
            city: 'Madrid',
            recordDate: new \DateTime('1878-03-01 00:00:00'),
            sourceDirectoryId: 'es_1930_madrid',
            language: 'es',
            sourceLine: 42
        );
        $id = $database->insert($entry);

        $found = $database->findById($id);
        $this->assertEquals('GARCÍA LÓPEZ, José', $found->getRawName());
        $this->assertEquals(['García', 'López'], $found->getLastNames());
        $this->assertEquals('es', $found->getLanguage());
        $this->assertEquals('ES', $found->getCountryCode());
        $this->assertEquals('Madrid', $found->getCity());
        $this->assertEquals('1878-03-01', $found->getRecordDate()->format('Y-m-d'));
        $this->assertEquals('es_1930_madrid', $found->getSourceDirectoryId());
        $this->assertSame(42, $found->getSourceLine());

        $this->assertCount(1, $database->findBySourceDirectory('es_1930_madrid'));
        $this->assertCount(1, $database->findBySurnameSound('Garsia', 'es'));

        $this->assertTrue($database->update($found), 'saving an unchanged entry still reports success');
        $this->assertTrue($database->delete($id));
        $this->assertSame(0, $database->count());
        $database->disconnect();
    }

    #[DataProvider('databases')]
    public function testSearchIsCaseInsensitiveAndLiteral(string $dsn, ?string $user, ?string $password): void
    {
        $this->rawConnection($dsn, $user, $password);
        $database = new PhoneDirectoryPDODatabase($dsn, $user, $password);
        $database->createTable();
        $database->insert(new PhoneDirectoryEntry('GARCÍA LÓPEZ, José', 'ES', 'Calle Mayor 12'));
        $database->insert(new PhoneDirectoryEntry('SMITH, John', 'US', '1 Oak_Avenue'));
        $database->insert(new PhoneDirectoryEntry('JONES, Ann', 'US', '2 Oak Avenue'));

        $this->assertCount(1, $database->findByName('garcía'));
        $this->assertCount(1, $database->findByName('SMITH'));
        $this->assertCount(1, $database->findByStreet('OAK_AVENUE'));
        $this->assertCount(2, $database->findByStreet('oak'));
        $this->assertCount(0, $database->findByName('%'));
        $this->assertCount(1, $database->search(['name' => 'jones', 'street' => 'oak avenue']));
        $database->disconnect();
    }

    #[DataProvider('databases')]
    public function testLegacyTableIsMigrated(string $dsn, ?string $user, ?string $password): void
    {
        $pdo = $this->rawConnection($dsn, $user, $password);
        $pdo->exec('CREATE TABLE phone_directory (
            id ' . self::LEGACY_ID[$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME)] . ',
            full_name VARCHAR(255) NOT NULL,
            street VARCHAR(255) NOT NULL,
            phone_number VARCHAR(255),
            record_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )');
        $pdo->exec("INSERT INTO phone_directory (full_name, street) VALUES ('John Smith', '123 Main Street')");
        $pdo = null;

        $database = new PhoneDirectoryPDODatabase($dsn, $user, $password);
        $database->createTable();
        $database->createTable();

        $legacy = $database->findByName('John Smith')[0];
        $this->assertEquals('US', $legacy->getCountryCode());
        $this->assertNull($legacy->getSourceLine());

        $id = $database->insert(new PhoneDirectoryEntry(fullName: 'ROSSI, Mario', countryCode: 'IT', street: 'Via Roma 1', sourceLine: 9));
        $this->assertSame(9, $database->findById($id)->getSourceLine());
        $database->disconnect();
    }

    #[DataProvider('databases')]
    public function testJuridicalEntityRoundTrip(string $dsn, ?string $user, ?string $password): void
    {
        $this->rawConnection($dsn, $user, $password);
        $database = new JuridicalEntityPDODatabase($dsn, $user, $password);
        $database->createTable();
        $database->createTable();

        $id = $database->insert(new JuridicalEntity(
            businessName: 'FARMACIA CENTRAL',
            street: 'Calle Luna 3',
            businessType: 'farmacia',
            phoneNumber: '555-444-5555',
            recordDate: new \DateTime('1930-06-15 00:00:00'),
            countryCode: 'es',
            sourceDirectoryId: 'es_1930_madrid',
            sourceLine: 7
        ));

        $found = $database->findById($id);
        $this->assertEquals('FARMACIA CENTRAL', $found->getBusinessName());
        $this->assertEquals('ES', $found->getCountryCode());
        $this->assertEquals('es_1930_madrid', $found->getSourceDirectoryId());
        $this->assertSame(7, $found->getSourceLine());
        $this->assertEquals('1930-06-15', $found->getRecordDate()->format('Y-m-d'));
        $this->assertCount(1, $database->findByBusinessName('farmacia'));
        $this->assertCount(1, $database->search(['businessType' => 'FARMACIA']));
        $this->assertTrue($database->update($found));
        $database->disconnect();
    }

    #[DataProvider('databases')]
    public function testSurnameSoundSearch(string $dsn, ?string $user, ?string $password): void
    {
        $this->rawConnection($dsn, $user, $password);
        $database = new PhoneDirectoryPDODatabase($dsn, $user, $password);
        $database->createTable();
        $database->insert(new PhoneDirectoryEntry(fullName: 'Juan Valdez Ruiz', countryCode: 'MX', street: 'Calle Hidalgo 4', language: 'es'));
        $database->insert(new PhoneDirectoryEntry(fullName: 'Ana Baldez Soto', countryCode: 'MX', street: 'Calle Juárez 9', language: 'es'));
        $database->insert(new PhoneDirectoryEntry('SMYTH, Mary', 'US', '2 Oak Avenue'));

        $this->assertCount(2, $database->findBySurnameSound('Valdez', 'es'));
        $this->assertCount(1, $database->findBySurnameSound('Smith'));
        $database->disconnect();
    }

    #[DataProvider('databases')]
    public function testAccentInsensitiveSearchIsConsistentAcrossDatabases(string $dsn, ?string $user, ?string $password): void
    {
        $this->rawConnection($dsn, $user, $password);
        $database = new PhoneDirectoryPDODatabase($dsn, $user, $password);
        $database->createTable();
        $database->insert(new PhoneDirectoryEntry('GARCÍA LÓPEZ, José', 'ES', 'Calle Mayor 12'));

        $this->assertCount(1, $database->findByName('garcia'));
        $this->assertCount(1, $database->findByStreet('calle mayor'));
        $database->disconnect();
    }

    #[DataProvider('databases')]
    public function testJuridicalEntityAccentInsensitiveSearch(string $dsn, ?string $user, ?string $password): void
    {
        $this->rawConnection($dsn, $user, $password);
        $database = new JuridicalEntityPDODatabase($dsn, $user, $password);
        $database->createTable();
        $database->insert(new JuridicalEntity(businessName: 'FARMACIA ÁGUILA', street: 'Avenida Sánchez 3'));

        $this->assertCount(1, $database->findByBusinessName('aguila'));
        $this->assertCount(1, $database->findByStreet('sanchez'));
        $database->disconnect();
    }
}
