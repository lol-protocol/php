<?php

declare(strict_types=1);

namespace Tests\App\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** bin/id.php as whoever creates ids runs it: a subprocess and its exit code. */
class IdCliTest extends TestCase
{
    /** @return array{0: int, 1: string, 2: string} exit code, stdout, stderr */
    private function correr(string ...$args): array
    {
        $proceso = proc_open(
            [PHP_BINARY, dirname(__DIR__, 3) . '/bin/id.php', ...$args],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            ['PATH' => (string)getenv('PATH')]
        );
        $this->assertIsResource($proceso);
        $salida = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proceso), (string)$salida, (string)$error];
    }

    public function testCompletarAddsTheCheckDigit(): void
    {
        $this->assertSame([0, "5724\n", ''], $this->correr('completar', '572'));
    }

    public function testCompletarPadsToTheWidthOfTheType(): void
    {
        $this->assertSame([0, "0000000475\n", ''], $this->correr('completar', '47', '10'));
        $this->assertSame([0, "6128473101\n", ''], $this->correr('completar', '612847310', '10'));
    }

    public function testCompletarRefusesANumberThatDoesNotFit(): void
    {
        [$codigo, , $error] = $this->correr('completar', '1234567890', '10');

        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('no cabe', $error);
    }

    public function testVerificarSaysWhetherTheLastDigitIsRight(): void
    {
        [$codigo, $salida] = $this->correr('verificar', '5724');
        $this->assertSame(0, $codigo);
        $this->assertStringContainsString('es válido', $salida);

        [$codigo, $salida] = $this->correr('verificar', '5274');
        $this->assertSame(1, $codigo);
        $this->assertStringContainsString('NO es válido', $salida);
    }

    /** @return array<string, array{list<string>}> */
    public static function malUso(): array
    {
        return [
            'nada' => [[]],
            'sin número' => [['completar']],
            'orden desconocida' => [['borrar', '5']],
            'no es un número' => [['completar', 'abc']],
            'ancho que no es número' => [['completar', '5', 'x']],
            'ancho en verificar' => [['verificar', '5724', '10']],
        ];
    }

    /** @param list<string> $args */
    #[DataProvider('malUso')]
    public function testBadUsageExitsWithTwo(array $args): void
    {
        [$codigo, , $error] = $this->correr(...$args);

        $this->assertSame(2, $codigo);
        $this->assertStringContainsString('Uso:', $error);
    }
}
