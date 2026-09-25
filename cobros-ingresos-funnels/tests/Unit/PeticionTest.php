<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Peticion;
use PHPUnit\Framework\TestCase;

final class PeticionTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
        $_POST = [];
    }

    public function testIdLeeElParametroDeLaQuery(): void
    {
        $_GET['id'] = '42';
        self::assertSame(42, Peticion::id());
    }

    public function testIdTambienLoLeeDeUnFormulario(): void
    {
        $_POST['id'] = '7';
        self::assertSame(7, Peticion::id());
    }

    public function testIdEsCeroSiNoVino(): void
    {
        self::assertSame(0, Peticion::id());
    }

    /**
     * Reproduce el caso real: "?page[]=dashboard" hacia estallar
     * Router::dispatch(string $page) con un TypeError -un 500 alcanzable
     * incluso sin sesion- y "?estado[]=x" hacia lo mismo en Cobros. Nadie usa
     * parametros repetidos, asi que se descartan y cada pantalla cae en su
     * valor por defecto.
     */
    public function testNormalizarDescartaLosParametrosDeTipoArray(): void
    {
        $_GET = ['page' => ['dashboard'], 'estado' => ['pendiente'], 'pagina' => '2'];
        $_POST = ['monto' => ['100'], 'metodo' => 'tarjeta'];

        Peticion::normalizarParametros();

        self::assertSame(['pagina' => '2'], $_GET);
        self::assertSame(['metodo' => 'tarjeta'], $_POST);
    }

    public function testNormalizarNoTocaLosParametrosNormales(): void
    {
        $_GET = ['page' => 'cobros', 'meses' => '6', 'pagina' => '3'];

        Peticion::normalizarParametros();

        self::assertSame(['page' => 'cobros', 'meses' => '6', 'pagina' => '3'], $_GET);
    }
}
