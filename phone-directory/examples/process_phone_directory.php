<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhoneDirectory\Manager\PhoneDirectoryManager;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\Entity\PhoneDirectoryEntry;

// Crear una instancia del manager
$dbPath = sys_get_temp_dir() . '/genealogy_phone_directory.db';
$dsn = 'sqlite:' . $dbPath;
$database = new PhoneDirectoryPDODatabase($dsn);
$manager = new PhoneDirectoryManager(database: $database);

// Procesar archivo de ejemplo
$sampleFile = __DIR__ . '/sample_phone_directory.txt';
echo "Processing phone directory file: {$sampleFile}\n";
echo "================================\n\n";

$result = $manager->processFile($sampleFile, clearExisting: true);

echo "Processing Results:\n";
echo "  Total entries parsed: {$result['totalParsed']}\n";
echo "  Successfully inserted: {$result['insertedCount']}\n";
echo "  Parsing errors: {$result['errorCount']}\n\n";

if (!empty($result['errors'])) {
    echo "Errors encountered:\n";
    foreach ($result['errors'] as $error) {
        echo "  Line {$error['line']}: {$error['reason']}\n";
    }
    echo "\n";
}

// Ejemplos de búsqueda
echo "Database Queries Examples:\n";
echo "==========================\n\n";

// Encontrar por nombre
echo "1. Searching for entries with surname 'ANDERSON':\n";
$results = $manager->findByName('ANDERSON');
foreach ($results as $entry) {
    echo "   - {$entry->getFullName()}, {$entry->getStreet()}\n";
}
echo "\n";

// Encontrar por calle
echo "2. Searching for entries on 'Street':\n";
$results = $manager->findByStreet('Street');
echo "   Found " . count($results) . " entries on streets named 'Street'\n";
echo "\n";

// Obtener todas las entradas
echo "3. Total entries in database: {$manager->getTotalCount()}\n\n";

// Mostrar todas las entradas
echo "4. All entries in database:\n";
$allEntries = $manager->getAllEntries();
foreach ($allEntries as $entry) {
    $phone = $entry->getPhoneNumber() ? " ({$entry->getPhoneNumber()})" : '';
    echo "   - {$entry->getFullName()}, {$entry->getStreet()}{$phone}\n";
}
echo "\n";

// Agregar una entrada manualmente
echo "5. Adding a new entry manually:\n";
$newEntry = new PhoneDirectoryEntry(
    fullName: 'RODRIGUEZ, José Miguel',
    countryCode: 'US',
    street: '555 Family Lane',
    phoneNumber: '555-999-0000'
);
$newId = $manager->addEntry($newEntry);
echo "   Entry added with ID: {$newId}\n";
echo "   New total count: {$manager->getTotalCount()}\n\n";

// Buscar la entrada agregada
echo "6. Retrieving the newly added entry:\n";
$entry = $manager->getEntry($newId);
if ($entry) {
    echo "   ID: {$entry->getId()}\n";
    echo "   Name: {$entry->getFullName()}\n";
    echo "   Street: {$entry->getStreet()}\n";
    echo "   Phone: {$entry->getPhoneNumber()}\n";
    echo "   Record Date: {$entry->getRecordDate()->format('Y-m-d H:i:s')}\n";
}
echo "\n";

// Búsqueda avanzada
echo "7. Advanced search (by criteria):\n";
$searchResults = $manager->search([
    'name' => 'GARCIA'
]);
echo "   Found " . count($searchResults) . " entries with surname 'GARCIA'\n";
foreach ($searchResults as $entry) {
    echo "      - {$entry->getFullName()}, {$entry->getStreet()}\n";
}
echo "\n";

$manager->disconnect();

echo "Database file location: {$dbPath}\n";
echo "Process completed successfully!\n";
