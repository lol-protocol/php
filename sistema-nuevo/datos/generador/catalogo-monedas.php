<?php

declare(strict_types=1);

/**
 * Moneda de curso legal por país y su cotización aproximada frente al USD
 * (unidades de esa moneda por 1 USD). Valores fijos e ilustrativos para esta
 * demo, no tasas de mercado en vivo — no hay acceso a un servicio de
 * cotizaciones en tiempo real.
 *
 * @return array{currencyByCountry: array<string,string>, rateToUsd: array<string,float>}
 */

$currencyByCountry = [
    'AT' => 'EUR',
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

return ['currencyByCountry' => $currencyByCountry, 'rateToUsd' => $rateToUsd];
