<?php

declare(strict_types=1);

/**
 * Pila de nombres/apellidos y distribución de género para los usuarios sintéticos.
 *
 * @return array{firstNames: string[], lastNames: string[], genders: string[], genderWeights: int[]}
 */

$firstNames = [
    'Sofía', 'Mateo', 'Valentina', 'Santiago', 'Camila', 'Sebastián', 'Isabella', 'Diego', 'Emma', 'Lucas',
    'Mariana', 'Daniel', 'Valeria', 'Gabriel', 'Renata', 'Andrés', 'Julia', 'Nicolás', 'Martina', 'Samuel',
    'Aiden', 'Olivia', 'Liam', 'Ava', 'Noah', 'Mia', 'Ethan', 'Amelia', 'Jack', 'Charlotte',
    'Yusuf', 'Fátima', 'Omar', 'Layla', 'Ahmed', 'Zainab', 'Ali', 'Noor', 'Hassan', 'Amara',
    'Wei', 'Mei', 'Hiroshi', 'Yuki', 'Min-jun', 'Seo-yeon', 'Arjun', 'Priya', 'Chen', 'Ling',
];
$lastNames = [
    'García', 'Rodríguez', 'Martínez', 'López', 'González', 'Pérez', 'Sánchez', 'Fernández', 'Torres', 'Díaz',
    'Smith', 'Johnson', 'Brown', 'Müller', 'Schmidt', 'Dubois', 'Rossi', 'Kowalski', 'Nowak', 'Ivanov',
    'Al-Sayed', 'Hassan', 'Khan', 'Rahman', 'Ibrahim', 'Silva', 'Costa', 'Almeida', 'Kim', 'Park',
    'Tanaka', 'Suzuki', 'Wang', 'Li', 'Zhang', 'Nguyen', 'Singh', 'Patel', 'Andersson', 'Nielsen',
];

return [
    'firstNames' => $firstNames,
    'lastNames' => $lastNames,
    'genders' => ['M', 'F', 'O'],
    'genderWeights' => [47, 47, 6],
];
