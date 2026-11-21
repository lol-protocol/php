<?php

declare(strict_types=1);

namespace Tests;

use PersonasEspeciales\Fila;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class FilaTest extends TestCase
{
    public function testTextoDevuelveElValor(): void
    {
        self::assertSame('hola', Fila::texto(['a' => 'hola'], 'a'));
    }

    /** @dataProvider valoresQueNoSonTexto */
    public function testTextoRechazaLoQueNoEsTexto(mixed $valor): void
    {
        $this->expectException(UnexpectedValueException::class);
        Fila::texto(['a' => $valor], 'a');
    }

    /** @return iterable<string, array{mixed}> */
    public static function valoresQueNoSonTexto(): iterable
    {
        yield 'entero' => [5];
        yield 'nulo' => [null];
        yield 'arreglo' => [['x']];
        yield 'booleano' => [false];
    }

    public function testTextoRechazaUnaColumnaQueNoEsta(): void
    {
        $this->expectException(UnexpectedValueException::class);
        Fila::texto(['a' => 'x'], 'b');
    }

    public function testTextoONuloAceptaNuloYTexto(): void
    {
        self::assertNull(Fila::textoONulo(['a' => null], 'a'));
        self::assertSame('x', Fila::textoONulo(['a' => 'x'], 'a'));
    }

    public function testTextoONuloRechazaUnTipoRaro(): void
    {
        $this->expectException(UnexpectedValueException::class);
        Fila::textoONulo(['a' => 7], 'a');
    }

    public function testDeFetch(): void
    {
        self::assertNull(Fila::deFetch(false));
        self::assertSame(['a' => 1], Fila::deFetch(['a' => 1]));
    }

    public function testDeFetchRechazaAlgoQueNoEsUnaFila(): void
    {
        $this->expectException(UnexpectedValueException::class);
        Fila::deFetch('texto');
    }
}
