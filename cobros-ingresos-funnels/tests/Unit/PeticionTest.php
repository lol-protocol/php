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

    public function testMontoLeeElCampoDelFormulario(): void
    {
        $_POST['monto'] = '1500.50';
        self::assertSame(1500.5, Peticion::monto());
    }

    /**
     * Antes (#388) "10.005" se redondeaba a 10.01 y "1.500" se leia como 1.5. Tres decimales justos tienen la
     * forma de un grupo de miles: "1.500" es el mil quinientos de quien usa el punto de miles, y guardar 1.5
     * es una boleta mil veces menor. No se adivina: se rechaza.
     */
    public function testMontoConTresDecimalesJustosNoSeLeeNiSeRedondea(): void
    {
        foreach (['10.005', '1.500', '1.000', '0.001', '1500.505'] as $texto) {
            $_POST['monto'] = $texto;
            self::assertSame(0.0, Peticion::monto(), "'{$texto}'");
        }
    }

    /**
     * Con cuatro decimales o mas no hay ambiguedad de miles: se redondea al centavo, como decidio #388. Sin casos
     * justo en la mitad (10.0050): round() de PHP 8.4 los resuelve distinto que el de 8.3, y la CI corre las dos.
     */
    public function testMontoConCuatroDecimalesOMasSeRedondeaAlCentavo(): void
    {
        foreach (['10.0051' => 10.01, '10.0049' => 10.0, '1.5000' => 1.5, '1500.5051' => 1500.51] as $texto => $esperado) {
            $_POST['monto'] = (string) $texto;
            self::assertSame($esperado, Peticion::monto(), "'{$texto}'");
        }
    }

    public function testMontoConUnoODosDecimalesSeLeeTalCual(): void
    {
        foreach (['10.5' => 10.5, '10.05' => 10.05, '0.01' => 0.01, '1500.00' => 1500.0] as $texto => $esperado) {
            $_POST['monto'] = (string) $texto;
            self::assertSame($esperado, Peticion::monto(), "'{$texto}'");
        }
    }

    /** 0 es lo que Validacion::faltanCampos() ya rechaza como "monto valido": el formulario vuelve con su error. */
    public function testMontoQueNoEsUnMontoEsCero(): void
    {
        foreach (['1.500,50', '1500,50', 'abc', '', '1e3'] as $texto) {
            $_POST['monto'] = $texto;
            self::assertSame(0.0, Peticion::monto(), "'{$texto}'");
        }
        $_POST = [];
        self::assertSame(0.0, Peticion::monto(), 'sin el campo');
    }
}
