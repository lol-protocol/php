<?php
/**
 * Privacy Laws Database Importer
 * Imports CSV data into normalized MySQL database
 *
 * Usage:
 *   php import_database.php --create-schema     # Create fresh schema
 *   php import_database.php --import            # Import data from CSV
 *   php import_database.php --validate          # Validate data integrity
 *   php import_database.php --all               # Create schema + import
 */

class PrivacyLawsImporter {
    private $pdo;
    private $dbName = 'privacy_laws_db';
    private $csvPath = './countries/privacy_laws_master.csv';
    private $schemaPath = './database_schema.sql';

    public function __construct($dbHost = 'localhost', $dbUser = 'root', $dbPass = '') {
        try {
            $this->pdo = new PDO(
                "mysql:host=$dbHost",
                $dbUser,
                $dbPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            $this->error("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Create fresh database schema
     */
    public function createSchema() {
        if (!file_exists($this->schemaPath)) {
            $this->error("Schema file not found: {$this->schemaPath}");
        }

        $this->log("📋 Creating database schema...");

        try {
            // Drop existing database
            $this->pdo->exec("DROP DATABASE IF EXISTS {$this->dbName}");
            $this->log("  ✓ Dropped existing database");

            // Read and execute schema file
            $schema = file_get_contents($this->schemaPath);

            // Split SQL statements and execute them
            $statements = preg_split('/;(?=\s*$)/m', $schema);

            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $this->pdo->exec($statement);
                }
            }

            $this->log("  ✓ Schema created successfully");
            return true;
        } catch (Exception $e) {
            $this->error("Schema creation failed: " . $e->getMessage());
        }
    }

    /**
     * Import data from CSV file
     */
    public function importData() {
        if (!file_exists($this->csvPath)) {
            $this->error("CSV file not found: {$this->csvPath}");
        }

        $this->log("📥 Importing data from CSV...");

        try {
            $this->pdo->exec("USE {$this->dbName}");

            // Read CSV
            $file = fopen($this->csvPath, 'r');
            if (!$file) {
                $this->error("Cannot open CSV file: {$this->csvPath}");
            }

            $headers = fgetcsv($file);
            $imported = 0;
            $countries = [];

            // Prepare statements
            $countryStmt = $this->pdo->prepare(
                "INSERT IGNORE INTO countries (code, name, tld, region) VALUES (?, ?, ?, ?)"
            );
            $lawStmt = $this->pdo->prepare(
                "INSERT INTO privacy_laws
                (country_id, law_name, jurisdiction, enactment_date, effective_date,
                 scope, applies_to, key_requirements, data_categories, retention_period,
                 enforcement_authority, penalties_range, exemptions, website_url, language, frameworks, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            // Read and import rows
            while (($row = fgetcsv($file)) !== false) {
                $data = array_combine($headers, $row);

                // Insert country if not exists
                $countryCode = trim($data['country_code']);
                if (!isset($countries[$countryCode])) {
                    $region = trim($data['region']);
                    $tld = strtolower($countryCode);

                    $countryStmt->execute([
                        $countryCode,
                        $data['country_name'],
                        $tld,
                        $region
                    ]);

                    // Get country_id
                    $idStmt = $this->pdo->prepare("SELECT country_id FROM countries WHERE code = ?");
                    $idStmt->execute([$countryCode]);
                    $countries[$countryCode] = $idStmt->fetch()['country_id'];
                }

                // Insert law
                $lawStmt->execute([
                    $countries[$countryCode],
                    $data['law_name'],
                    $data['jurisdiction'],
                    $this->parseDate($data['enactment_date']),
                    $this->parseDate($data['effective_date']),
                    $data['scope'],
                    $data['applies_to'],
                    $data['key_requirements'],
                    $data['data_categories'],
                    $data['retention_period'],
                    $data['enforcement_authority'],
                    $data['penalties_range'],
                    $data['exemptions'],
                    $data['website_url'],
                    $data['language'],
                    $data['frameworks'] ?? '',
                    $data['notes'] ?? ''
                ]);

                $imported++;
            }

            fclose($file);

            $this->log("  ✓ Imported {$imported} laws from " . count($countries) . " countries");
            return true;
        } catch (Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
        }
    }

    /**
     * Validate data integrity
     */
    public function validate() {
        $this->log("✅ Validating data...");

        try {
            $this->pdo->exec("USE {$this->dbName}");

            // Check country count
            $countryCount = $this->pdo->query("SELECT COUNT(*) as count FROM countries")
                ->fetch()['count'];
            $this->log("  ✓ Countries: $countryCount");

            // Check law count
            $lawCount = $this->pdo->query("SELECT COUNT(*) as count FROM privacy_laws")
                ->fetch()['count'];
            $this->log("  ✓ Privacy Laws: $lawCount");

            // Check for orphaned laws
            $orphaned = $this->pdo->query(
                "SELECT COUNT(*) as count FROM privacy_laws WHERE country_id NOT IN (SELECT country_id FROM countries)"
            )->fetch()['count'];

            if ($orphaned > 0) {
                $this->warn("  ⚠ Orphaned laws found: $orphaned");
            } else {
                $this->log("  ✓ No orphaned records");
            }

            // Check for null enforcement_authority
            $nulls = $this->pdo->query(
                "SELECT COUNT(*) as count FROM privacy_laws WHERE enforcement_authority IS NULL OR enforcement_authority = ''"
            )->fetch()['count'];

            if ($nulls > 0) {
                $this->warn("  ⚠ Missing enforcement_authority: $nulls");
            }

            // Laws by region
            $regions = $this->pdo->query(
                "SELECT region, COUNT(*) as count FROM countries GROUP BY region ORDER BY region"
            )->fetchAll();

            foreach ($regions as $r) {
                $this->log("  ✓ {$r['region']}: {$r['count']} countries");
            }

            $this->log("  ✓ Validation complete!");
            return true;
        } catch (Exception $e) {
            $this->error("Validation failed: " . $e->getMessage());
        }
    }

    /**
     * Helper: Parse date from various formats
     */
    private function parseDate($date) {
        if (empty($date) || $date === 'N/A') {
            return null;
        }
        $timestamp = strtotime($date);
        return $timestamp ? date('Y-m-d', $timestamp) : null;
    }

    /**
     * Logging helpers
     */
    private function log($message) {
        echo "[" . date('H:i:s') . "] $message\n";
    }

    private function warn($message) {
        echo "\033[33m[" . date('H:i:s') . "] $message\033[0m\n";
    }

    private function error($message) {
        echo "\033[31m[" . date('H:i:s') . "] ERROR: $message\033[0m\n";
        exit(1);
    }
}

// Main execution
$importer = new PrivacyLawsImporter();
$action = $argv[1] ?? 'help';

switch ($action) {
    case '--create-schema':
        $importer->createSchema();
        break;
    case '--import':
        $importer->importData();
        break;
    case '--validate':
        $importer->validate();
        break;
    case '--all':
        $importer->createSchema();
        $importer->importData();
        $importer->validate();
        break;
    default:
        echo "Privacy Laws Database Importer\n\n";
        echo "Usage:\n";
        echo "  php import_database.php --create-schema     Create fresh database schema\n";
        echo "  php import_database.php --import            Import data from CSV\n";
        echo "  php import_database.php --validate          Validate data integrity\n";
        echo "  php import_database.php --all               All of the above\n";
}
?>
