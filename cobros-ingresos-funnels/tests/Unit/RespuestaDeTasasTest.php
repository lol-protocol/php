<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Tasas\RespuestaDeTasas;
use App\Tasas\TasasInvalidas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Lee el JSON de una fuente de tasas: lo que acepta, lo que deja afuera con su motivo y lo que rechaza entero. */
final class RespuestaDeTasasTest extends TestCase
{
    public function testLeeElFormatoDeExchangeRateApi(): void
    {
        $respuesta = RespuestaDeTasas::desdeJson((string) json_encode([
            'result' => 'success',
            'provider' => 'https://www.exchangerate-api.com',
            'time_last_update_unix' => 1791331200,
            'base_code' => 'USD',
            'rates' => ['USD' => 1, 'EUR' => 0.86, 'CLP' => 953.1, 'JPY' => 150],
        ]));

        self::assertSame(['USD' => '1', 'EUR' => '0.86', 'CLP' => '953.1', 'JPY' => '150'], $respuesta->unidadesPorUsd);
        self::assertSame([], $respuesta->rechazadas);
        self::assertNotNull($respuesta->fecha);
        self::assertSame('2026-10-07 00:00:00', $respuesta->fecha->format('Y-m-d H:i:s'));
        self::assertSame('UTC', $respuesta->fecha->getTimezone()->getName());
    }

    public function testLeeElFormatoDeOpenExchangeRates(): void
    {
        $respuesta = RespuestaDeTasas::desdeJson('{"disclaimer": "x", "timestamp": 1791331200, "base": "USD", "rates": {"EUR": 0.86}}');

        self::assertSame(['EUR' => '0.86'], $respuesta->unidadesPorUsd);
        self::assertSame('2026-10-07', $respuesta->fecha?->format('Y-m-d'));
    }

    public function testLeeElFormatoDeFrankfurterQueSoloTraeUnDia(): void
    {
        $respuesta = RespuestaDeTasas::desdeJson('{"amount": 1.0, "base": "USD", "date": "2026-10-06", "rates": {"EUR": 0.86}}');

        self::assertSame('2026-10-06 00:00:00', $respuesta->fecha?->format('Y-m-d H:i:s'));
    }

    public function testSinFechaEnLaRespuestaLaFechaEsNula(): void
    {
        self::assertNull(RespuestaDeTasas::desdeJson('{"base": "USD", "rates": {"EUR": 0.86}}')->fecha);
    }

    public function testUnaFechaQueNoEsUnaFechaSeIgnora(): void
    {
        self::assertNull(RespuestaDeTasas::desdeJson('{"base": "USD", "date": "2026-02-30", "rates": {"EUR": 0.86}}')->fecha);
        self::assertNull(RespuestaDeTasas::desdeJson('{"base": "USD", "timestamp": "ayer", "rates": {"EUR": 0.86}}')->fecha);
    }

    public function testLosCodigosEnMinusculaSePasanAMayuscula(): void
    {
        $respuesta = RespuestaDeTasas::desdeJson('{"base": "usd", "rates": {"eur": 0.86}}');

        self::assertSame(['EUR' => '0.86'], $respuesta->unidadesPorUsd);
    }

    public function testUnNumeroEscritoComoTextoSeAcepta(): void
    {
        self::assertSame(['EUR' => '0.86'], RespuestaDeTasas::desdeJson('{"base": "USD", "rates": {"EUR": "0.86"}}')->unidadesPorUsd);
    }

    /** Los numeros chicos se escriben como exponente en JSON y Postgres los lee igual como numeric. */
    public function testUnNumeroConExponenteSeConserva(): void
    {
        $respuesta = RespuestaDeTasas::desdeJson('{"base": "USD", "rates": {"EUR": 1.0e-5}}');

        self::assertEqualsWithDelta(1.0e-5, (float) $respuesta->unidadesPorUsd['EUR'], 1e-18);
    }

    /** @return iterable<string, array{string, string}> el valor en el JSON y el motivo del rechazo */
    public static function valoresQueNoSirven(): iterable
    {
        yield 'cero' => ['0', 'no es mayor que cero'];
        yield 'negativo' => ['-1.5', 'no es mayor que cero'];
        yield 'nulo' => ['null', 'no es un número'];
        yield 'texto' => ['"abc"', 'no es un número'];
        yield 'texto vacio' => ['""', 'no es un número'];
        yield 'verdadero' => ['true', 'no es un número'];
        yield 'lista' => ['[1]', 'no es un número'];
        yield 'objeto' => ['{"a": 1}', 'no es un número'];
        yield 'infinito escrito como texto' => ['"INF"', 'no es un número'];
    }

    /** Un valor malo deja afuera a esa moneda, con su motivo, y no tira las demas. */
    #[DataProvider('valoresQueNoSirven')]
    public function testUnValorMaloSeRechazaSinTirarLasDemas(string $valor, string $motivo): void
    {
        $respuesta = RespuestaDeTasas::desdeJson('{"base": "USD", "rates": {"EUR": 0.86, "CLP": ' . $valor . '}}');

        self::assertSame(['EUR' => '0.86'], $respuesta->unidadesPorUsd);
        self::assertSame(['CLP' => $motivo], $respuesta->rechazadas);
    }

    public function testUnCodigoQueNoEsDeTresLetrasSeRechaza(): void
    {
        $respuesta = RespuestaDeTasas::desdeJson('{"base": "USD", "rates": {"EUR": 0.86, "EURO": 1, "E1R": 1, "": 1}}');

        self::assertSame(['EUR' => '0.86'], $respuesta->unidadesPorUsd);
        self::assertSame(['EURO', 'E1R', ''], array_keys($respuesta->rechazadas));
    }

    /** @return iterable<string, array{string, string}> el JSON y un pedazo del mensaje */
    public static function respuestasQueNoSirven(): iterable
    {
        yield 'no es JSON' => ['<html>502 Bad Gateway</html>', 'no es un JSON válido'];
        yield 'vacia' => ['', 'no es un JSON válido'];
        yield 'es un numero' => ['42', 'no es un objeto'];
        yield 'es una lista' => ['[1, 2, 3]', 'no es un objeto'];
        yield 'la fuente avisa un error' => ['{"result": "error", "error-type": "quota-reached"}', 'quota-reached'];
        yield 'el error no dice cual' => ['{"result": "error"}', 'sin detalle'];
        yield 'no dice la base' => ['{"rates": {"EUR": 0.86}}', 'base'];
        yield 'la base no es USD' => ['{"base": "EUR", "rates": {"USD": 1.16}}', 'respecto de EUR'];
        yield 'la base no es un texto' => ['{"base": 1, "rates": {"EUR": 0.86}}', 'base'];
        yield 'sin rates' => ['{"base": "USD"}', 'no trae tasas'];
        yield 'rates vacias' => ['{"base": "USD", "rates": {}}', 'no trae tasas'];
        yield 'rates no es un objeto' => ['{"base": "USD", "rates": "EUR=0.86"}', 'no trae tasas'];
        yield 'un dolar no vale un dolar' => ['{"base": "USD", "rates": {"USD": 2, "EUR": 0.86}}', 'inconsistente'];
    }

    #[DataProvider('respuestasQueNoSirven')]
    public function testUnaRespuestaQueNoSeEntiendeSeRechazaEntera(string $json, string $mensaje): void
    {
        $this->expectException(TasasInvalidas::class);
        $this->expectExceptionMessage($mensaje);

        RespuestaDeTasas::desdeJson($json);
    }

    public function testUnJsonDemasiadoProfundoSeRechaza(): void
    {
        $this->expectException(TasasInvalidas::class);

        RespuestaDeTasas::desdeJson(str_repeat('[', 100) . str_repeat(']', 100));
    }
}
