<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/** bin/migrar.php es lo que corre quien despliega: sus fallos tienen que ser legibles y con código de salida claro. */
final class MigrarScriptTest extends TestCase
{
    /** @return array{int, string, string} */
    private function correr(array $env): array
    {
        $proceso = proc_open(
            [PHP_BINARY, __DIR__ . '/../bin/migrar.php'],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
            null,
            $env + ['PATH' => (string) getenv('PATH')],
        );
        self::assertIsResource($proceso);
        $salida = (string) stream_get_contents($tuberias[1]);
        $error = (string) stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);

        return [proc_close($proceso), $salida, $error];
    }

    public function testSinPeDsnSaleConDosYDiceQueFalta(): void
    {
        [$codigo, , $error] = $this->correr([]);

        self::assertSame(2, $codigo);
        self::assertStringContainsString('Falta PE_DSN', $error);
    }

    public function testUnaConexionQueFallaSaleConUnoYUnMensajeDeUnaLineaSinTraza(): void
    {
        // El puerto 1 de la propia máquina rechaza la conexión al instante: no hace falta PostgreSQL.
        [$codigo, $salida, $error] = $this->correr(['PE_DSN' => 'pgsql:host=127.0.0.1;port=1;dbname=x', 'PE_USER' => 'u', 'PE_PASSWORD' => 'clave-que-no-debe-salir']);

        self::assertSame(1, $codigo);
        self::assertSame('', $salida);
        self::assertStringStartsWith('No se pudo migrar: ', $error);
        self::assertSame(1, substr_count(trim($error), "\n") + 1, 'El mensaje debe ser de una sola línea.');
        self::assertStringNotContainsString('Stack trace', $error);
        self::assertStringNotContainsString('Fatal error', $error);
        self::assertStringNotContainsString('clave-que-no-debe-salir', $error);
    }
}
