<?php

require_once __DIR__ . '/../vendor/autoload.php';

use PhoneDirectory\PhoneDirectoryManagerV2;
use PhoneDirectory\PhoneDirectoryPDODatabase;
use PhoneDirectory\JuridicalEntityPDODatabase;
use PhoneDirectory\PhoneDirectoryEntry;
use PhoneDirectory\JuridicalEntity;

// Crear instancias de base de datos
$dbPath = sys_get_temp_dir() . '/genealogy_multilingual.db';
$dsn = 'sqlite:' . $dbPath;

$naturalDb = new PhoneDirectoryPDODatabase($dsn);
$juridicalDb = new JuridicalEntityPDODatabase($dsn);

// Crear manager V2 que maneja ambos tipos de entidades
$manager = new PhoneDirectoryManagerV2(
    naturalDatabase: $naturalDb,
    juridicalDatabase: $juridicalDb
);

echo "========================================\n";
echo "PHONE DIRECTORY PARSER - MULTILINGUAL\n";
echo "========================================\n\n";

// Crear archivos de ejemplo en diferentes idiomas
$spanishExample = <<<'TXT'
GARCÍA, Juan José
Calle Principal 123
555-1234567

MARTÍNEZ COMERCIAL S.L.
Avenida Central 456
555-2345678
TXT;

$englishExample = <<<'TXT'
SMITH, John Robert
123 Main Street
555-001-2345

ABC CORPORATION INC.
456 Oak Avenue
555-002-3456
TXT;

// Guardar ejemplos en archivos temporales
$spanishFile = tempnam(sys_get_temp_dir(), 'es_');
$englishFile = tempnam(sys_get_temp_dir(), 'en_');

file_put_contents($spanishFile, $spanishExample);
file_put_contents($englishFile, $englishExample);

echo "1. Processing Spanish Directory\n";
echo "================================\n";
$result = $manager->processFile($spanishFile, language: 'es', clearExisting: true);

echo "   Detected Language: {$result['language']}\n";
echo "   Total Parsed: {$result['totalParsed']}\n";
echo "   Natural People: {$result['naturalPeople']}\n";
echo "   Juridical Entities: {$result['juridicalEntities']}\n";
echo "   Successfully Inserted: {$result['totalInserted']}\n\n";

echo "2. Processing English Directory\n";
echo "================================\n";
$result = $manager->processFile($englishFile, language: 'en', clearExisting: false);

echo "   Detected Language: {$result['language']}\n";
echo "   Total Parsed: {$result['totalParsed']}\n";
echo "   Natural People: {$result['naturalPeople']}\n";
echo "   Juridical Entities: {$result['juridicalEntities']}\n";
echo "   Successfully Inserted: {$result['totalInserted']}\n\n";

echo "3. Database Statistics\n";
echo "======================\n";
$stats = $manager->getStatistics();
echo "   Total Records: {$stats['total']}\n";
echo "   Natural People: {$stats['natural_people']}\n";
echo "   Juridical Entities: {$stats['juridical_entities']}\n";
echo "   Ratio: {$stats['ratio_natural_to_juridical']}\n\n";

echo "4. Natural People\n";
echo "=================\n";
$allNatural = $manager->getAllNaturalPeople();
foreach ($allNatural as $person) {
    echo "   - {$person->getFullName()}\n";
    echo "     Address: {$person->getStreet()}\n";
    if ($person->getPhoneNumber()) {
        echo "     Phone: {$person->getPhoneNumber()}\n";
    }
    echo "\n";
}

echo "5. Juridical Entities\n";
echo "====================\n";
$allEntities = $manager->getAllJuridicalEntities();
foreach ($allEntities as $entity) {
    echo "   - {$entity->getBusinessName()}\n";
    echo "     Address: {$entity->getStreet()}\n";
    if ($entity->getBusinessType()) {
        echo "     Type: {$entity->getBusinessType()}\n";
    }
    if ($entity->getPhoneNumber()) {
        echo "     Phone: {$entity->getPhoneNumber()}\n";
    }
    echo "\n";
}

echo "6. Search Examples\n";
echo "==================\n";

echo "   Search for 'GARCÍA' (natural people):\n";
$results = $manager->searchNaturalPeople(['name' => 'GARCÍA']);
foreach ($results as $person) {
    echo "      - {$person->getFullName()}, {$person->getStreet()}\n";
}

echo "\n   Search for 'Street' (in any table):\n";
$results = $manager->findByStreet('Street');
foreach ($results['natural'] as $person) {
    echo "      - Natural: {$person->getFullName()}, {$person->getStreet()}\n";
}
foreach ($results['juridical'] as $entity) {
    echo "      - Juridical: {$entity->getBusinessName()}, {$entity->getStreet()}\n";
}

echo "\n   Search for phone '555-1234567':\n";
$results = $manager->findByPhone('555-1234567');
if ($results['natural']) {
    echo "      - Found: {$results['natural']->getFullName()}\n";
}
if ($results['juridical']) {
    echo "      - Found: {$results['juridical']->getBusinessName()}\n";
}

// Agregar entrada manual
echo "\n7. Adding Manual Entries\n";
echo "========================\n";

$newPerson = new PhoneDirectoryEntry(
    fullName: 'RODRIGUEZ, Carlos Miguel',
    street: '789 Family Lane',
    phoneNumber: '555-3456789'
);
$personId = $manager->addNaturalPerson($newPerson);
echo "   Added natural person with ID: {$personId}\n";

$newEntity = new JuridicalEntity(
    businessName: 'FAMILY RESTAURANT LLC',
    street: '321 Dining Avenue',
    businessType: 'Restaurant',
    phoneNumber: '555-4567890'
);
$entityId = $manager->addJuridicalEntity($newEntity);
echo "   Added juridical entity with ID: {$entityId}\n\n";

echo "8. Final Statistics\n";
echo "===================\n";
$finalStats = $manager->getStatistics();
echo "   Total Records: {$finalStats['total']}\n";
echo "   Natural People: {$finalStats['natural_people']}\n";
echo "   Juridical Entities: {$finalStats['juridical_entities']}\n\n";

// Cleanup
unlink($spanishFile);
unlink($englishFile);
$manager->disconnect();

echo "Database saved to: {$dbPath}\n";
echo "Process completed successfully!\n";
