<?php
/**
 * Copyright Laws Database Importer
 * Imports CSV data into normalized MySQL database
 *
 * Usage:
 *   php import_database.php --create-schema     # Create fresh schema
 *   php import_database.php --import            # Import data from CSV
 *   php import_database.php --validate          # Validate data integrity
 *   php import_database.php --all               # Create schema + import
 */

class CopyrightLawsImporter {
    private $pdo;
    private $dbName = 'copyright_laws_db';
    private $csvPath = './jurisdictions/copyright_laws_master.csv';
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
            $jurisdictions = [];

            // Prepare statements
            $jurisdictionStmt = $this->pdo->prepare(
                "INSERT IGNORE INTO jurisdictions (code, name, tld, region) VALUES (?, ?, ?, ?)"
            );
            $lawStmt = $this->pdo->prepare(
                "INSERT INTO copyright_laws
                (jurisdiction_id, law_name, protection_type, term_of_protection,
                 author_rights, moral_rights, orphan_works, digital_protection,
                 fair_use_exceptions, registration_required, enforcement_body,
                 treaties_signatory, linked_resources)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            // Read and import rows
            while (($row = fgetcsv($file)) !== false) {
                $data = array_combine($headers, $row);

                // Insert jurisdiction if not exists
                $countryCode = trim($data['country_code']);
                if (!isset($jurisdictions[$countryCode])) {
                    $region = trim($data['region']);
                    $tld = strtolower($countryCode);

                    $jurisdictionStmt->execute([
                        $countryCode,
                        $data['country_name'],
                        $tld,
                        $region
                    ]);

                    // Get jurisdiction_id
                    $idStmt = $this->pdo->prepare("SELECT jurisdiction_id FROM jurisdictions WHERE code = ?");
                    $idStmt->execute([$countryCode]);
                    $jurisdictions[$countryCode] = $idStmt->fetch()['jurisdiction_id'];
                }

                // Insert law
                $lawStmt->execute([
                    $jurisdictions[$countryCode],
                    $data['law_name'],
                    $data['protection_type'],
                    $data['term_of_protection'],
                    $data['author_rights'],
                    $data['moral_rights'],
                    $data['orphan_works'],
                    $data['digital_protection'],
                    $data['fair_use_exceptions'],
                    $data['registration_required'],
                    $data['enforcement_body'],
                    $data['treaties_signatory'],
                    $data['linked_resources']
                ]);

                $imported++;
            }

            fclose($file);

            $this->log("  ✓ Imported {$imported} copyright laws from " . count($jurisdictions) . " jurisdictions");
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

            // Check jurisdiction count
            $jurisdictionCount = $this->pdo->query("SELECT COUNT(*) as count FROM jurisdictions")
                ->fetch()['count'];
            $this->log("  ✓ Jurisdictions: $jurisdictionCount");

            // Check law count
            $lawCount = $this->pdo->query("SELECT COUNT(*) as count FROM copyright_laws")
                ->fetch()['count'];
            $this->log("  ✓ Copyright Laws: $lawCount");

            // Check for orphaned laws
            $orphaned = $this->pdo->query(
                "SELECT COUNT(*) as count FROM copyright_laws WHERE jurisdiction_id NOT IN (SELECT jurisdiction_id FROM jurisdictions)"
            )->fetch()['count'];

            if ($orphaned > 0) {
                $this->warn("  ⚠ Orphaned laws found: $orphaned");
            } else {
                $this->log("  ✓ No orphaned records");
            }

            // Check for null enforcement_body
            $nulls = $this->pdo->query(
                "SELECT COUNT(*) as count FROM copyright_laws WHERE enforcement_body IS NULL OR enforcement_body = ''"
            )->fetch()['count'];

            if ($nulls > 0) {
                $this->warn("  ⚠ Missing enforcement_body: $nulls");
            }

            // Laws by region
            $regions = $this->pdo->query(
                "SELECT region, COUNT(*) as count FROM jurisdictions GROUP BY region ORDER BY region"
            )->fetchAll();

            foreach ($regions as $r) {
                $this->log("  ✓ {$r['region']}: {$r['count']} jurisdictions");
            }

            // Protection types
            $types = $this->pdo->query(
                "SELECT protection_type, COUNT(*) as count FROM copyright_laws GROUP BY protection_type ORDER BY count DESC"
            )->fetchAll();

            foreach ($types as $t) {
                $this->log("  ✓ {$t['protection_type']}: {$t['count']} laws");
            }

            // Treaty memberships
            $treaties = $this->pdo->query(
                "SELECT
                    TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(treaties_signatory, '/', numbers.n), '/', -1)) as treaty,
                    COUNT(*) as count
                FROM copyright_laws
                CROSS JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3) numbers
                WHERE treaties_signatory LIKE CONCAT('%', TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(treaties_signatory, '/', numbers.n), '/', -1)), '%')
                GROUP BY treaty"
            )->fetchAll();

            if (!empty($treaties)) {
                $this->log("  ✓ Treaty memberships:");
                foreach ($treaties as $t) {
                    $this->log("    - {$t['treaty']}: {$t['count']}");
                }
            }

            $this->log("  ✓ Validation complete!");
            return true;
        } catch (Exception $e) {
            $this->error("Validation failed: " . $e->getMessage());
        }
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
$importer = new CopyrightLawsImporter();
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
        echo "Copyright Laws Database Importer\n\n";
        echo "Usage:\n";
        echo "  php import_database.php --create-schema     Create fresh database schema\n";
        echo "  php import_database.php --import            Import data from CSV\n";
        echo "  php import_database.php --validate          Validate data integrity\n";
        echo "  php import_database.php --all               All of the above\n";
}
?>
