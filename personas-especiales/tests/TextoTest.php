<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PersonasEspeciales\Texto;
use PHPUnit\Framework\TestCase;

final class TextoTest extends TestCase
{
    public function testQuitaLosEspaciosDeLosExtremos(): void
    {
        self::assertSame('p-1', Texto::limpio("  p-1\n", 'id', 64));
    }

    public function testElLargoSeCuentaEnCaracteresNoEnBytes(): void
    {
        self::assertSame(str_repeat('é', 64), Texto::limpio(str_repeat('é', 64), 'id', 64));

        $this->expectException(InvalidArgumentException::class);
        Texto::limpio(str_repeat('é', 65), 'id', 64);
    }

    /** @dataProvider invalidos */
    public function testRechazaLoQueNoSirveParaUnIdentificador(string $valor): void
    {
        $this->expectException(InvalidArgumentException::class);
        Texto::limpio($valor, 'id', 64);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidos(): iterable
    {
        yield 'vacío' => [''];
        yield 'solo espacios' => ["   \t "];
        yield 'NUL' => ["con\0nul"];
        yield 'salto de línea dentro' => ["uno\ndos"];
        yield 'tabulador dentro' => ["uno\tdos"];
        yield 'DEL' => ["uno\x7Fdos"];
        yield 'UTF-8 inválido' => ["\xC3\x28"];
    }

    public function testUnTextoLibreAdmiteSaltosDeLineaPeroNoNul(): void
    {
        self::assertSame("Cédula:\nvista en la oficina", Texto::limpio("Cédula:\nvista en la oficina", 'prueba', 1000, true));

        $this->expectException(InvalidArgumentException::class);
        Texto::limpio("Cédula\0", 'prueba', 1000, true);
    }

    public function testUnTextoLibreRechazaOtrosControles(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Texto::limpio("pru\x1Beba", 'prueba', 1000, true);
    }
}
