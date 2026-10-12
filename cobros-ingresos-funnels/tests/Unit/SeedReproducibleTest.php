<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * database/recrear_con_datos_de_ejemplo.php promete "datos de ejemplo reproducibles" y arranca con
 * mt_srand(2024), pero random_int() y random_bytes() no se pueden sembrar: con
 * cinco llamadas a random_int() cada corrida daba clientes, boletas y pagos
 * distintos, y un test que dependia de esos datos podia pasar o fallar segun
 * la corrida. Correr el seed dos veces para compararlo borraria la base de
 * quien corre los tests, asi que se vigila el codigo: el seed solo puede usar
 * azar que mt_srand() controle (mt_rand, array_rand, shuffle...).
 */
final class SeedReproducibleTest extends TestCase
{
    /** Fuentes de azar (o de tiempo) que mt_srand() no controla. */
    private const NO_SEMBRABLES = ['random_int', 'random_bytes', 'uniqid', 'lcg_value', 'openssl_random_pseudo_bytes', 'microtime', 'hrtime'];

    private static function codigoDelSeed(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/database/recrear_con_datos_de_ejemplo.php');
    }

    public function testElSeedSoloUsaAzarQueSePuedeSembrar(): void
    {
        $tokens = token_get_all(self::codigoDelSeed());
        $usos = [];

        foreach ($tokens as $posicion => $token) {
            if (!is_array($token) || $token[0] !== T_STRING || !in_array(strtolower($token[1]), self::NO_SEMBRABLES, true)) {
                continue;
            }
            // Solo las llamadas: el siguiente token que no es un espacio es "(".
            $siguiente = $tokens[$posicion + 1] ?? null;
            while (is_array($siguiente) && $siguiente[0] === T_WHITESPACE) {
                $siguiente = $tokens[++$posicion + 1] ?? null;
            }
            if ($siguiente === '(') {
                $usos[] = "{$token[1]}() en la linea {$token[2]}";
            }
        }

        self::assertSame([], $usos, 'usar mt_rand() en su lugar: mt_srand(2024) no controla estas funciones');
    }

    public function testElSeedSiembraElGeneradorUnaSolaVez(): void
    {
        self::assertSame(1, preg_match_all('/\bmt_srand\(\s*\d+\s*\)/', self::codigoDelSeed()));
    }
}
