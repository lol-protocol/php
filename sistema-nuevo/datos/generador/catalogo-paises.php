<?php

declare(strict_types=1);

/**
 * Presets de grupos de países (OTAN, BRICS, LATAM, etc.) y catálogo de nombres.
 *
 * @return array{presets: array, countryNames: array<string,string>}
 */

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

return ['presets' => $presets, 'countryNames' => $countryNames];
