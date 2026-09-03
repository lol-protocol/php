<?php

declare(strict_types=1);

/**
 * Genera los datos semilla del backoffice en dos etapas:
 *
 *  1. Arma logs de acciones "crudos" (acciones-crudas.json): timestamps en formatos
 *     distintos, números a veces como texto, texto libre con HTML/scripts colados,
 *     algunos registros con campos inválidos o faltantes a propósito — simulando
 *     logs reales de fuentes heterogéneas.
 *  2. Pasa cada registro crudo por el saneador (saneador.php) para producir el
 *     esquema canónico que usa el resto del sistema: acciones.json (backend PHP),
 *     acciones-planas.csv (servicio de estadísticas en Java) y usuarios.json /
 *     grupos-de-paises.json / monedas.json.
 *
 * Uso: php generar-datos-semilla.php
 */

require __DIR__ . '/../servidor-php/codigo/saneador.php';

mt_srand(20260903); // semilla fija: datos reproducibles entre corridas

// ---------------------------------------------------------------------------
// Catálogos: países, presets de grupos, monedas
// ---------------------------------------------------------------------------

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

// Moneda de curso legal por país y su cotización aproximada frente al USD (unidades
// de esa moneda por 1 USD). Son valores fijos e ilustrativos para esta demo, no
// tasas de mercado en vivo — no hay acceso a un servicio de cotizaciones en tiempo real.
$currencyByCountry = [
    'AL' => 'ALL', 'BE' => 'EUR', 'BG' => 'BGN', 'CA' => 'CAD', 'HR' => 'EUR',
    'CZ' => 'CZK', 'DK' => 'DKK', 'EE' => 'EUR', 'FI' => 'EUR', 'FR' => 'EUR',
    'DE' => 'EUR', 'GR' => 'EUR', 'HU' => 'HUF', 'IS' => 'ISK', 'IT' => 'EUR',
    'LV' => 'EUR', 'LT' => 'EUR', 'LU' => 'EUR', 'ME' => 'EUR', 'NL' => 'EUR',
    'MK' => 'MKD', 'NO' => 'NOK', 'PL' => 'PLN', 'PT' => 'EUR', 'RO' => 'RON',
    'SK' => 'EUR', 'SI' => 'EUR', 'ES' => 'EUR', 'SE' => 'SEK', 'TR' => 'TRY',
    'GB' => 'GBP', 'US' => 'USD',
    'BR' => 'BRL', 'RU' => 'RUB', 'IN' => 'INR', 'CN' => 'CNY', 'ZA' => 'ZAR',
    'EG' => 'EGP', 'ET' => 'ETB', 'IR' => 'IRR', 'AE' => 'AED', 'SA' => 'SAR', 'ID' => 'IDR',
    'AR' => 'ARS', 'BO' => 'BOB', 'CL' => 'CLP', 'CO' => 'COP', 'CR' => 'CRC',
    'CU' => 'CUP', 'DO' => 'DOP', 'EC' => 'USD', 'SV' => 'USD', 'GT' => 'GTQ',
    'HN' => 'HNL', 'MX' => 'MXN', 'NI' => 'NIO', 'PA' => 'PAB', 'PY' => 'PYG',
    'PE' => 'PEN', 'UY' => 'UYU', 'VE' => 'VES',
    'PK' => 'PKR', 'IQ' => 'IQD', 'JO' => 'JOD', 'MA' => 'MAD', 'DZ' => 'DZD',
    'TN' => 'TND', 'MY' => 'MYR', 'QA' => 'QAR', 'KW' => 'KWD', 'BD' => 'BDT',
    'NG' => 'NGN', 'SN' => 'XOF',
    'CY' => 'EUR', 'IE' => 'EUR', 'MT' => 'EUR', 'LI' => 'CHF', 'CH' => 'CHF',
    'JP' => 'JPY', 'KR' => 'KRW', 'AU' => 'AUD', 'NZ' => 'NZD',
];

$rateToUsd = [
    'USD' => 1.0, 'EUR' => 0.92, 'GBP' => 0.79, 'CAD' => 1.36, 'CHF' => 0.88,
    'CZK' => 23.0, 'DKK' => 6.9, 'HUF' => 380.0, 'ISK' => 138.0, 'MKD' => 56.5,
    'NOK' => 10.6, 'PLN' => 4.0, 'RON' => 4.6, 'SEK' => 10.5, 'TRY' => 34.0, 'BGN' => 1.80, 'ALL' => 94.0,
    'BRL' => 5.4, 'RUB' => 92.0, 'INR' => 83.5, 'CNY' => 7.2, 'ZAR' => 18.7,
    'EGP' => 48.0, 'ETB' => 118.0, 'IRR' => 42000.0, 'AED' => 3.67, 'SAR' => 3.75, 'IDR' => 15700.0,
    'ARS' => 1400.0, 'BOB' => 6.9, 'CLP' => 980.0, 'COP' => 4100.0, 'CRC' => 520.0,
    'CUP' => 120.0, 'DOP' => 59.0, 'GTQ' => 7.8, 'HNL' => 24.7, 'MXN' => 18.5,
    'NIO' => 36.8, 'PAB' => 1.0, 'PYG' => 7300.0, 'PEN' => 3.75, 'UYU' => 40.0, 'VES' => 60.0,
    'PKR' => 278.0, 'IQD' => 1310.0, 'JOD' => 0.71, 'MAD' => 10.1, 'DZD' => 134.5,
    'TND' => 3.1, 'MYR' => 4.7, 'QAR' => 3.64, 'KWD' => 0.307, 'BDT' => 110.0,
    'NGN' => 1550.0, 'XOF' => 610.0,
    'JPY' => 150.0, 'KRW' => 1350.0, 'AUD' => 1.52, 'NZD' => 1.64,
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

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

function tal_vez(int $probabilidadPorc): bool
{
    return mt_rand(1, 100) <= $probabilidadPorc;
}

/** Misma marca de tiempo, en uno de varios formatos crudos posibles. */
function crudo_marca_temporal(int $segundosEpoch): int|string
{
    switch (mt_rand(1, 5)) {
        case 1:
            return gmdate('Y-m-d\TH:i:s\Z', $segundosEpoch);
        case 2:
            $offsets = [-5, -3, 0, 1, 2, 9];
            $offset = $offsets[array_rand($offsets)];
            $signo = $offset >= 0 ? '+' : '-';
            $horas = str_pad((string) abs($offset), 2, '0', STR_PAD_LEFT);
            return gmdate('Y-m-d\TH:i:s', $segundosEpoch + $offset * 3600) . $signo . $horas . ':00';
        case 3:
            return gmdate('Y-m-d H:i:s', $segundosEpoch); // estilo SQL, sin zona explícita
        case 4:
            return $segundosEpoch; // epoch en segundos
        default:
            return $segundosEpoch * 1000; // epoch en milisegundos
    }
}

/** Mismo número, a veces como texto con formato de moneda/miles, a veces con ruido. */
function crudo_numero(float $valor, int $decimales = 0): string|float
{
    return match (mt_rand(1, 4)) {
        1 => round($valor, $decimales),
        2 => (string) round($valor, $decimales),
        3 => '$' . number_format($valor, max($decimales, 2)),
        default => ' ' . round($valor, $decimales) . ' ',
    };
}

function crudo_may_min(string $s): string
{
    return match (mt_rand(1, 3)) {
        1 => strtoupper($s),
        2 => strtolower($s),
        default => $s,
    };
}

function crudo_espacios(string $s): string
{
    return tal_vez(20) ? ("  " . $s . "\n") : $s;
}

$comentariosBase = [
    'Excelente atención, todo perfecto.',
    'El proceso fue más lento de lo esperado.',
    'Muy buena relación precio-calidad.',
    'Tuve problemas para completar el pago.',
    'Volvería a comprar sin dudarlo.',
    'El soporte tardó bastante en responder.',
    'Interfaz clara y fácil de usar.',
    'No era lo que esperaba, pero el reembolso fue rápido.',
    'Todo llegó a tiempo, sin inconvenientes.',
    'La aplicación se sintió lenta durante el pago.',
];
$fragmentosInseguros = [
    '<script>alert(1)</script>',
    '<img src=x onerror=alert(1)>',
    '&lt;script&gt;document.cookie&lt;/script&gt;',
    '<b onmouseover=alert(1)>saludos</b>',
];

function crudo_comentario(array $base, array $inseguros): string
{
    $texto = $base[array_rand($base)];
    if (tal_vez(15)) {
        $texto .= ' ' . $inseguros[array_rand($inseguros)];
    }
    return crudo_espacios($texto);
}

// ---------------------------------------------------------------------------
// Usuarios (esto ya sale limpio: no es un "log", es el padrón de usuarios)
// ---------------------------------------------------------------------------

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
$usersById = array_column($users, null, 'id');

// ---------------------------------------------------------------------------
// Acciones crudas: mismo flujo narrativo de antes, con más tipos de acción y
// formatos deliberadamente inconsistentes (así el saneador tiene algo que hacer).
// ---------------------------------------------------------------------------

$tiposAccion = [
    'login'           => ['label' => 'Inicio de sesión', 'base_ms' => 1500],
    'password_reset'  => ['label' => 'Restablecer contraseña', 'base_ms' => 6000],
    'search'          => ['label' => 'Búsqueda', 'base_ms' => 4000],
    'view_product'    => ['label' => 'Visualización de producto', 'base_ms' => 8000],
    'api_call'        => ['label' => 'Llamada a la API', 'base_ms' => 300],
    'profile_update'  => ['label' => 'Actualización de perfil', 'base_ms' => 9000],
    'add_to_cart'     => ['label' => 'Agregar al carrito', 'base_ms' => 2000],
    'checkout_start'  => ['label' => 'Inicio de checkout', 'base_ms' => 5000],
    'payment'         => ['label' => 'Pago', 'base_ms' => 12000, 'monto_base' => 45.0],
    'refund'          => ['label' => 'Reembolso', 'base_ms' => 7000, 'monto_base' => 45.0],
    'review_submit'   => ['label' => 'Reseña enviada', 'base_ms' => 15000],
    'support_ticket'  => ['label' => 'Ticket de soporte', 'base_ms' => 20000],
    'file_upload'     => ['label' => 'Subida de archivo', 'base_ms' => 4000],
    'logout'          => ['label' => 'Cierre de sesión', 'base_ms' => 800],
];
$tiposConMonto = ['payment', 'refund'];

// Factor de "nivel de precio" determinístico por país (~0.7 - 1.6), para que el
// monto en USD equivalente varíe de forma realista entre países antes de
// convertirlo a moneda local.
$countryPriceFactor = [];
foreach ($allCountries as $c) {
    $countryPriceFactor[$c] = 0.7 + ((crc32($c) % 100) / 100) * 0.9;
}

$apiEndpoints = ['/api/pedidos', '/api/perfil', '/api/pagos', '/api/productos', '/api/carrito'];
$httpStatusPool = [200, 200, 200, 200, 201, 204, 400, 401, 404, 500];

$crudas = [];
$seq = 1;
foreach ($users as $user) {
    $speedFactor = max(0.5, 0.75 + (($user['age'] - 30) / 120) + (mt_rand(-15, 15) / 100));
    $priceFactorUsd = $countryPriceFactor[$user['country']];
    $monedaUsuario = $currencyByCountry[$user['country']] ?? 'USD';

    $sessions = mt_rand(1, 3);
    for ($s = 1; $s <= $sessions; $s++) {
        $cursor = time() - mt_rand(1, 30) * 86400 - mt_rand(0, 86399);

        $flow = [];
        if (tal_vez(6)) {
            $flow[] = 'password_reset';
        }
        $flow[] = 'login';

        $browsing = mt_rand(2, 5);
        for ($b = 0; $b < $browsing; $b++) {
            $flow[] = mt_rand(0, 1) ? 'view_product' : 'search';
            if (tal_vez(25)) {
                $flow[] = 'api_call';
            }
        }
        if (tal_vez(12)) {
            $flow[] = 'profile_update';
        }
        if (tal_vez(70)) {
            $flow[] = 'add_to_cart';
            if (tal_vez(60)) {
                $flow[] = 'checkout_start';
                $flow[] = 'payment';
                if (tal_vez(35)) {
                    $flow[] = 'review_submit';
                }
                if (tal_vez(10)) {
                    $flow[] = 'refund';
                }
            }
        }
        if (tal_vez(15)) {
            $flow[] = 'support_ticket';
            if (tal_vez(50)) {
                $flow[] = 'file_upload';
            }
        }
        $flow[] = 'logout';

        foreach ($flow as $tipo) {
            $meta = $tiposAccion[$tipo];
            $cursor += mt_rand(3, 90); // "tiempo de pensar" entre acciones
            $duracionMs = $meta['base_ms'] * $speedFactor * (mt_rand(60, 140) / 100);

            $registro = [
                'id' => sprintf('a%05d', $seq++),
                'user_id' => tal_vez(2) ? '' : crudo_espacios($user['id']),
                'type' => tal_vez(2) ? 'evento_desconocido' : crudo_espacios(crudo_may_min($tipo)),
                'timestamp' => tal_vez(2) ? 'fecha-invalida' : crudo_marca_temporal($cursor),
                'duration_ms' => tal_vez(3)
                    ? (tal_vez(50) ? 'N/D' : -$duracionMs)
                    : crudo_numero($duracionMs, 0),
            ];

            if (in_array($tipo, $tiposConMonto, true)) {
                $montoUsdObjetivo = $meta['monto_base'] * $priceFactorUsd * (mt_rand(50, 180) / 100);
                $montoLocal = $montoUsdObjetivo * ($rateToUsd[$monedaUsuario] ?? 1.0);
                $monedasInvalidas = ['xxx', 'N/A', ''];
                $registro['amount'] = tal_vez(3) ? 'N/D' : crudo_numero($montoLocal, 2);
                $registro['currency'] = tal_vez(5)
                    ? $monedasInvalidas[array_rand($monedasInvalidas)]
                    : crudo_may_min($monedaUsuario);
            }

            if ($tipo === 'review_submit' || ($tipo === 'support_ticket' && tal_vez(60))) {
                $registro['comment'] = crudo_comentario($comentariosBase, $fragmentosInseguros);
            }

            if ($tipo === 'api_call') {
                $registro['endpoint'] = $apiEndpoints[array_rand($apiEndpoints)];
                $registro['http_status'] = tal_vez(80)
                    ? $httpStatusPool[array_rand($httpStatusPool)]
                    : (string) $httpStatusPool[array_rand($httpStatusPool)];
            }

            if ($tipo === 'file_upload') {
                $tamanoKb = mt_rand(50, 8000) / 10;
                $registro['file_size_kb'] = tal_vez(30) ? ($tamanoKb . ' KB') : $tamanoKb;
            }

            $crudas[] = $registro;
            $cursor += (int) round($duracionMs / 1000);
        }
    }
}

// ---------------------------------------------------------------------------
// Saneamiento: crudas -> esquema canónico
// ---------------------------------------------------------------------------

$etiquetas = array_map(fn ($meta) => $meta['label'], $tiposAccion);
$monedasValidas = array_values(array_unique(array_values($currencyByCountry)));

$contexto = [
    'usuarios_por_id' => $usersById,
    'monedas_validas' => $monedasValidas,
    'moneda_por_pais' => $currencyByCountry,
    'tasa_por_moneda' => $rateToUsd,
    'etiquetas' => $etiquetas,
];

$acciones = [];
$descartadas = 0;
foreach ($crudas as $crudo) {
    $saneada = saneador_accion($crudo, $contexto);
    if ($saneada === null) {
        $descartadas++;
        continue;
    }
    $acciones[] = $saneada;
}
usort($acciones, fn ($a, $b) => $a['timestamp'] <=> $b['timestamp']);

// ---------------------------------------------------------------------------
// Escritura de archivos
// ---------------------------------------------------------------------------

$dataDir = __DIR__;

file_put_contents($dataDir . '/usuarios.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($dataDir . '/acciones-crudas.json', json_encode($crudas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($dataDir . '/acciones.json', json_encode($acciones, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$groups = [
    'presets' => array_map(
        fn ($key) => ['key' => $key, 'label' => $presets[$key]['label'], 'countries' => $presets[$key]['countries']],
        array_keys($presets)
    ),
    'countries' => $countryNames,
];
file_put_contents($dataDir . '/grupos-de-paises.json', json_encode($groups, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$monedas = [
    'currency_by_country' => $currencyByCountry,
    'rate_per_usd' => $rateToUsd,
];
file_put_contents($dataDir . '/monedas.json', json_encode($monedas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$csv = fopen($dataDir . '/acciones-planas.csv', 'w');
fputcsv($csv, ['user_id', 'type', 'duration_ms', 'amount_usd', 'country', 'age', 'gender', 'timestamp']);
foreach ($acciones as $a) {
    $u = $usersById[$a['user_id']];
    fputcsv($csv, [
        $a['user_id'], $a['type'], $a['duration_ms'], $a['amount_usd'] ?? '',
        $u['country'], $u['age'], $u['gender'], $a['timestamp'],
    ]);
}
fclose($csv);

fwrite(STDERR, sprintf(
    "Generados %d usuarios, %d acciones crudas -> %d saneadas, %d descartadas por datos inválidos.\n",
    count($users), count($crudas), count($acciones), $descartadas
));
