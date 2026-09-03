<?php

declare(strict_types=1);

/**
 * Genera los datos semilla del backoffice: usuarios, log de acciones (users.json /
 * actions.json, consumidos por el backend PHP), una vista desnormalizada en CSV
 * (actions_flat.csv, consumida por el motor de estadísticas en Java) y el catálogo
 * de países / presets de grupos (country_groups.json).
 *
 * Uso: php generate_seed_data.php
 */

mt_srand(20260903); // semilla fija: datos reproducibles entre corridas

$presets = [
    'otan' => [
        'label' => 'OTAN',
        'countries' => ['AL', 'BE', 'BG', 'CA', 'HR', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IT', 'LV', 'LT', 'LU', 'ME', 'NL', 'MK', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'TR', 'GB', 'US'],
    ],
    'brics' => [
        'label' => 'BRICS',
        'countries' => ['BR', 'RU', 'IN', 'CN', 'ZA', 'EG', 'ET', 'IR', 'AE', 'SA', 'ID'],
    ],
    'latam' => [
        'label' => 'LATAM',
        'countries' => ['AR', 'BO', 'BR', 'CL', 'CO', 'CR', 'CU', 'DO', 'EC', 'SV', 'GT', 'HN', 'MX', 'NI', 'PA', 'PY', 'PE', 'UY', 'VE'],
    ],
    'islamicos' => [
        'label' => 'Países islámicos (OCI)',
        'countries' => ['SA', 'AE', 'EG', 'TR', 'ID', 'PK', 'IR', 'IQ', 'JO', 'MA', 'DZ', 'TN', 'MY', 'QA', 'KW', 'BD', 'NG', 'SN'],
    ],
    'euro' => [
        'label' => 'Zona Euro',
        'countries' => ['AT', 'BE', 'HR', 'CY', 'EE', 'FI', 'FR', 'DE', 'GR', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PT', 'SK', 'SI', 'ES'],
    ],
    'schengen' => [
        'label' => 'Espacio Schengen',
        'countries' => ['AT', 'BE', 'BG', 'HR', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IS', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'NL', 'NO', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE', 'CH'],
    ],
];

$countryNames = [
    'AL' => 'Albania', 'BE' => 'Bélgica', 'BG' => 'Bulgaria', 'CA' => 'Canadá', 'HR' => 'Croacia',
    'CZ' => 'Chequia', 'DK' => 'Dinamarca', 'EE' => 'Estonia', 'FI' => 'Finlandia', 'FR' => 'Francia',
    'DE' => 'Alemania', 'GR' => 'Grecia', 'HU' => 'Hungría', 'IS' => 'Islandia', 'IT' => 'Italia',
    'LV' => 'Letonia', 'LT' => 'Lituania', 'LU' => 'Luxemburgo', 'ME' => 'Montenegro', 'NL' => 'Países Bajos',
    'MK' => 'Macedonia del Norte', 'NO' => 'Noruega', 'PL' => 'Polonia', 'PT' => 'Portugal', 'RO' => 'Rumania',
    'SK' => 'Eslovaquia', 'SI' => 'Eslovenia', 'ES' => 'España', 'SE' => 'Suecia', 'TR' => 'Turquía',
    'GB' => 'Reino Unido', 'US' => 'Estados Unidos',
    'BR' => 'Brasil', 'RU' => 'Rusia', 'IN' => 'India', 'CN' => 'China', 'ZA' => 'Sudáfrica',
    'EG' => 'Egipto', 'ET' => 'Etiopía', 'IR' => 'Irán', 'AE' => 'Emiratos Árabes Unidos', 'SA' => 'Arabia Saudita', 'ID' => 'Indonesia',
    'AR' => 'Argentina', 'BO' => 'Bolivia', 'CL' => 'Chile', 'CO' => 'Colombia', 'CR' => 'Costa Rica',
    'CU' => 'Cuba', 'DO' => 'Rep. Dominicana', 'EC' => 'Ecuador', 'SV' => 'El Salvador', 'GT' => 'Guatemala',
    'HN' => 'Honduras', 'MX' => 'México', 'NI' => 'Nicaragua', 'PA' => 'Panamá', 'PY' => 'Paraguay',
    'PE' => 'Perú', 'UY' => 'Uruguay', 'VE' => 'Venezuela',
    'PK' => 'Pakistán', 'IQ' => 'Irak', 'JO' => 'Jordania', 'MA' => 'Marruecos', 'DZ' => 'Argelia',
    'TN' => 'Túnez', 'MY' => 'Malasia', 'QA' => 'Catar', 'KW' => 'Kuwait', 'BD' => 'Bangladés',
    'NG' => 'Nigeria', 'SN' => 'Senegal',
    'CY' => 'Chipre', 'IE' => 'Irlanda', 'MT' => 'Malta', 'LI' => 'Liechtenstein', 'CH' => 'Suiza',
    'JP' => 'Japón', 'KR' => 'Corea del Sur', 'AU' => 'Australia', 'NZ' => 'Nueva Zelanda',
];

$allCountries = array_keys($countryNames);

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

$genders = ['M', 'F', 'O'];
$genderWeights = [47, 47, 6];

function weightedPick(array $items, array $weights)
{
    $total = array_sum($weights);
    $r = mt_rand(1, $total);
    $acc = 0;
    foreach ($items as $i => $item) {
        $acc += $weights[$i];
        if ($r <= $acc) {
            return $item;
        }
    }
    return $items[array_key_last($items)];
}

$actionTypes = [
    'login'          => ['label' => 'Inicio de sesión', 'amount' => false, 'base_ms' => 1500],
    'search'         => ['label' => 'Búsqueda', 'amount' => false, 'base_ms' => 4000],
    'view_product'   => ['label' => 'Visualización de producto', 'amount' => false, 'base_ms' => 8000],
    'add_to_cart'    => ['label' => 'Agregar al carrito', 'amount' => false, 'base_ms' => 2000],
    'checkout_start' => ['label' => 'Inicio de checkout', 'amount' => false, 'base_ms' => 5000],
    'payment'        => ['label' => 'Pago', 'amount' => true, 'base_ms' => 12000, 'base_amount' => 45.0],
    'support_ticket' => ['label' => 'Ticket de soporte', 'amount' => false, 'base_ms' => 20000],
    'logout'         => ['label' => 'Cierre de sesión', 'amount' => false, 'base_ms' => 800],
];

// Factor de "nivel de precio" determinístico por país (~0.7 - 1.6), para que las
// comparaciones de monto pagado tengan variación real entre universos de comparación.
$countryPriceFactor = [];
foreach ($allCountries as $c) {
    $countryPriceFactor[$c] = 0.7 + ((crc32($c) % 100) / 100) * 0.9;
}

$userCount = 60;
$users = [];
for ($i = 1; $i <= $userCount; $i++) {
    $country = $allCountries[array_rand($allCountries)];
    $users[] = [
        'id' => sprintf('u%03d', $i),
        'name' => $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)],
        'country' => $country,
        'age' => mt_rand(18, 65),
        'gender' => weightedPick($genders, $genderWeights),
    ];
}

$actions = [];
$actionSeq = 1;
foreach ($users as $user) {
    // Leve efecto de edad + ruido individual, así "cuánto demoró" no es plano entre usuarios.
    $speedFactor = max(0.5, 0.75 + (($user['age'] - 30) / 120) + (mt_rand(-15, 15) / 100));
    $priceFactor = $countryPriceFactor[$user['country']];

    $sessions = mt_rand(1, 3);
    for ($s = 1; $s <= $sessions; $s++) {
        $cursor = time() - mt_rand(1, 30) * 86400 - mt_rand(0, 86399);

        $flow = ['login'];
        $browsing = mt_rand(2, 5);
        for ($b = 0; $b < $browsing; $b++) {
            $flow[] = mt_rand(0, 1) ? 'view_product' : 'search';
        }
        if (mt_rand(1, 100) <= 70) {
            $flow[] = 'add_to_cart';
            if (mt_rand(1, 100) <= 60) {
                $flow[] = 'checkout_start';
                $flow[] = 'payment';
            }
        }
        if (mt_rand(1, 100) <= 15) {
            $flow[] = 'support_ticket';
        }
        $flow[] = 'logout';

        foreach ($flow as $type) {
            $meta = $actionTypes[$type];
            $cursor += mt_rand(3, 90); // "tiempo de pensar" entre acciones
            $durationMs = (int) round($meta['base_ms'] * $speedFactor * (mt_rand(60, 140) / 100));
            $amount = null;
            if ($meta['amount']) {
                $amount = round($meta['base_amount'] * $priceFactor * (mt_rand(50, 180) / 100), 2);
            }
            $actions[] = [
                'id' => sprintf('a%05d', $actionSeq++),
                'user_id' => $user['id'],
                'type' => $type,
                'label' => $meta['label'],
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z', $cursor),
                'duration_ms' => $durationMs,
                'amount_usd' => $amount,
            ];
            $cursor += (int) round($durationMs / 1000);
        }
    }
}

$dataDir = __DIR__;

file_put_contents($dataDir . '/users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($dataDir . '/actions.json', json_encode($actions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$groups = [
    'presets' => array_map(
        fn ($key) => ['key' => $key, 'label' => $presets[$key]['label'], 'countries' => $presets[$key]['countries']],
        array_keys($presets)
    ),
    'countries' => $countryNames,
];
file_put_contents($dataDir . '/country_groups.json', json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$usersById = array_column($users, null, 'id');
$csv = fopen($dataDir . '/actions_flat.csv', 'w');
fputcsv($csv, ['user_id', 'type', 'duration_ms', 'amount_usd', 'country', 'age', 'gender', 'timestamp']);
foreach ($actions as $a) {
    $u = $usersById[$a['user_id']];
    fputcsv($csv, [
        $a['user_id'], $a['type'], $a['duration_ms'], $a['amount_usd'] ?? '',
        $u['country'], $u['age'], $u['gender'], $a['timestamp'],
    ]);
}
fclose($csv);

fwrite(STDERR, sprintf("Generados %d usuarios y %d acciones.\n", count($users), count($actions)));
