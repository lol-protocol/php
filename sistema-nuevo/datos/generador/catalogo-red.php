<?php

declare(strict_types=1);

/**
 * Datos de red: huso horario aproximado por país (para calcular "hora local"
 * a partir de la IP) y una pila de proveedores/ISP ficticios. Offsets fijos e
 * ilustrativos (ignoran horario de verano), no una base de geolocalización real.
 *
 * @return array{offsetPorPais: array<string,float>, proveedoresIsp: string[]}
 */

$offsetPorPais = [
    'AT' => 1,
    'AL' => 1, 'BE' => 1, 'BG' => 2, 'CA' => -5, 'HR' => 1,
    'CZ' => 1, 'DK' => 1, 'EE' => 2, 'FI' => 2, 'FR' => 1,
    'DE' => 1, 'GR' => 2, 'HU' => 1, 'IS' => 0, 'IT' => 1,
    'LV' => 2, 'LT' => 2, 'LU' => 1, 'ME' => 1, 'NL' => 1,
    'MK' => 1, 'NO' => 1, 'PL' => 1, 'PT' => 0, 'RO' => 2,
    'SK' => 1, 'SI' => 1, 'ES' => 1, 'SE' => 1, 'TR' => 3,
    'GB' => 0, 'US' => -5,
    'BR' => -3, 'RU' => 3, 'IN' => 5.5, 'CN' => 8, 'ZA' => 2,
    'EG' => 2, 'ET' => 3, 'IR' => 3.5, 'AE' => 4, 'SA' => 3, 'ID' => 7,
    'AR' => -3, 'BO' => -4, 'CL' => -4, 'CO' => -5, 'CR' => -6,
    'CU' => -5, 'DO' => -4, 'EC' => -5, 'SV' => -6, 'GT' => -6,
    'HN' => -6, 'MX' => -6, 'NI' => -6, 'PA' => -5, 'PY' => -4,
    'PE' => -5, 'UY' => -3, 'VE' => -4,
    'PK' => 5, 'IQ' => 3, 'JO' => 2, 'MA' => 1, 'DZ' => 1,
    'TN' => 1, 'MY' => 8, 'QA' => 3, 'KW' => 3, 'BD' => 6,
    'NG' => 1, 'SN' => 0,
    'CY' => 2, 'IE' => 0, 'MT' => 1, 'LI' => 1, 'CH' => 1,
    'JP' => 9, 'KR' => 9, 'AU' => 10, 'NZ' => 12,
];

$proveedoresIsp = [
    'FibraExpress', 'NetConecta S.A.', 'Andes NET', 'Orbital Broadband',
    'CloudHost Networks', 'DataLine ISP', 'Nébula Cloud', 'GlobalTel Comunicaciones',
];

return ['offsetPorPais' => $offsetPorPais, 'proveedoresIsp' => $proveedoresIsp];
