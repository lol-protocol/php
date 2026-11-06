<?php
/**
 * Privacy Laws Database Importer
 * Imports the master CSV into a normalized MySQL / MariaDB database.
 *
 * Usage:
 *   php import_database.php --create-schema --yes   # Drop and recreate the database schema
 *   php import_database.php --import                # Replace the tables' contents with the CSV (can be repeated)
 *   php import_database.php --validate              # Validate data integrity
 *   php import_database.php --all --yes             # All of the above
 *
 * Connection: DB_HOST (localhost), DB_PORT (3306), DB_USER (root), DB_PASSWORD (empty).
 * The importer itself is shared with copyright-laws/: leyes-comunes/importador-core.php.
 */

require __DIR__ . '/../leyes-comunes/importador-core.php';

ImportadorLeyes::ejecutar([
    'titulo' => 'Privacy Laws',
    'db' => 'privacy_laws_db',
    'csv' => './countries/privacy_laws_master.csv',
    'esquema' => './database_schema.sql',
    'padre' => 'countries',
    'clavePadre' => 'country_id',
    'unidad' => 'countries',
    'ley' => 'privacy_laws',
    'leyes' => 'laws',
    'columnas' => [
        'law_name', 'jurisdiction', 'enactment_date', 'effective_date', 'scope', 'applies_to',
        'key_requirements', 'data_categories', 'retention_period', 'enforcement_authority',
        'penalties_range', 'exemptions', 'deceased_data', 'research_exemption', 'genetic_data',
        'website_url', 'language', 'frameworks', 'frameworks_not', 'notes', 'pending_change',
        'pending_date', 'data_status', 'confidence', 'last_checked',
    ],
    'obligatorias' => [
        'country_code', 'country_name', 'region', 'law_name', 'jurisdiction', 'enactment_date',
        'effective_date', 'scope', 'applies_to', 'key_requirements', 'data_categories',
        'retention_period', 'enforcement_authority', 'penalties_range', 'exemptions', 'website_url', 'language',
    ],
    'fechas' => ['enactment_date', 'effective_date', 'pending_date', 'last_checked'],
    'porDefecto' => ['data_status' => 'curated'],
    'campoNoVacio' => 'enforcement_authority',
], $argv);
