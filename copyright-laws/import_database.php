<?php
/**
 * Copyright Laws Database Importer
 * Imports the master CSV into a normalized MySQL / MariaDB database.
 *
 * Usage:
 *   php import_database.php --create-schema --yes   # Drop and recreate the database schema
 *   php import_database.php --import                # Replace the tables' contents with the CSV (can be repeated)
 *   php import_database.php --validate              # Validate data integrity
 *   php import_database.php --all --yes             # All of the above
 *
 * Connection: DB_HOST (localhost), DB_PORT (3306), DB_USER (root), DB_PASSWORD (empty).
 * The importer itself is shared with privacy-laws/: leyes-comunes/importador-core.php.
 */

require __DIR__ . '/../leyes-comunes/importador-core.php';

ImportadorLeyes::ejecutar([
    'titulo' => 'Copyright Laws',
    'db' => 'copyright_laws_db',
    'csv' => './jurisdictions/copyright_laws_master.csv',
    'esquema' => './database_schema.sql',
    'padre' => 'jurisdictions',
    'clavePadre' => 'jurisdiction_id',
    'unidad' => 'jurisdictions',
    'ley' => 'copyright_laws',
    'leyes' => 'copyright laws',
    'columnas' => [
        'law_name', 'protection_type', 'term_of_protection', 'author_rights', 'moral_rights',
        'orphan_works', 'digital_protection', 'fair_use_exceptions', 'registration_required',
        'enforcement_body', 'treaties_signatory', 'treaties_not_party', 'linked_resources', 'notes',
        'pending_change', 'pending_date', 'data_status', 'confidence', 'last_checked',
    ],
    'obligatorias' => [
        'country_code', 'country_name', 'region', 'law_name', 'protection_type', 'term_of_protection',
        'author_rights', 'moral_rights', 'orphan_works', 'digital_protection', 'fair_use_exceptions',
        'registration_required', 'enforcement_body', 'treaties_signatory', 'linked_resources',
    ],
    'fechas' => ['pending_date', 'last_checked'],
    'porDefecto' => ['data_status' => 'curated'],
    'campoNoVacio' => 'enforcement_body',
    // Lo que solo valida este módulo: tipos de protección y pertenencia a tratados.
    'validacionExtra' => function (PDO $pdo, callable $log): void {
        foreach ($pdo->query(
            'SELECT protection_type, COUNT(*) AS count FROM copyright_laws GROUP BY protection_type ORDER BY count DESC'
        )->fetchAll() as $t) {
            $log("  ✓ {$t['protection_type']}: {$t['count']} laws");
        }

        // treaties_signatory es una lista separada por «/» de hasta cuatro valores.
        $tratados = $pdo->query(
            "SELECT
                TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(treaties_signatory, '/', numbers.n), '/', -1)) AS treaty,
                COUNT(*) AS count
            FROM copyright_laws
            CROSS JOIN (SELECT 1 n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) numbers
            WHERE treaties_signatory <> ''
              AND numbers.n <= 1 + LENGTH(treaties_signatory) - LENGTH(REPLACE(treaties_signatory, '/', ''))
            GROUP BY treaty
            ORDER BY count DESC"
        )->fetchAll();
        if ($tratados) {
            $log('  ✓ Treaty memberships:');
            foreach ($tratados as $t) {
                $log("    - {$t['treaty']}: {$t['count']}");
            }
        }
    },
], $argv);
