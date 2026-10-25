<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\RespuestaCondicional;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RespuestaCondicionalTest extends TestCase
{
    private const ETAG = '"abc123"';
    private const DESDE = 1_760_000_000; // Thu, 09 Oct 2025 08:53:20 GMT

    /** @return array<string, array{string|null, string|null, int|null, bool}> */
    public static function casos(): array
    {
        return [
            'nothing asked' => [null, null, self::DESDE, false],
            'same etag' => ['"abc123"', null, self::DESDE, true],
            'weak etag' => ['W/"abc123"', null, self::DESDE, true],
            'one of a list' => ['"zzz", "abc123"', null, self::DESDE, true],
            'any' => ['*', null, self::DESDE, true],
            'other etag' => ['"zzz"', null, self::DESDE, false],
            'other etag wins over a date that would match' => ['"zzz"', 'Thu, 09 Oct 2025 08:53:20 GMT', self::DESDE, false],
            'same date' => [null, 'Thu, 09 Oct 2025 08:53:20 GMT', self::DESDE, true],
            'later date' => [null, 'Fri, 10 Oct 2025 00:00:00 GMT', self::DESDE, true],
            'earlier date' => [null, 'Thu, 09 Oct 2025 08:53:19 GMT', self::DESDE, false],
            'not a date' => [null, 'ayer', self::DESDE, false],
            'date, but ours is unknown' => [null, 'Fri, 10 Oct 2025 00:00:00 GMT', null, false],
            'empty If-None-Match falls back to the date' => ['', 'Thu, 09 Oct 2025 08:53:20 GMT', self::DESDE, true],
        ];
    }

    #[DataProvider('casos')]
    public function testSinCambios(?string $siNoCoincide, ?string $siModificadoDesde, ?int $desde, bool $esperado): void
    {
        $this->assertSame($esperado, RespuestaCondicional::sinCambios($siNoCoincide, $siModificadoDesde, self::ETAG, $desde));
    }

    public function testFechaHttp(): void
    {
        $this->assertSame('Thu, 09 Oct 2025 08:53:20 GMT', RespuestaCondicional::fechaHttp(self::DESDE));
    }
}
