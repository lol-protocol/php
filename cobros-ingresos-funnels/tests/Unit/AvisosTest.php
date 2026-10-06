<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Avisos;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AvisosTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testUnAvisoLlevaSuTipoYSuTexto(): void
    {
        self::assertSame(['tipo' => 'ok', 'texto' => 'Hecho.'], Avisos::ok('Hecho.'));
        self::assertSame(['tipo' => 'atencion', 'texto' => 'Ojo.'], Avisos::atencion('Ojo.'));
    }

    /** El tipo es una clase de CSS (.aviso-ok / .aviso-atencion): si cambia el nombre, cambia el estilo. */
    public function testLosTiposTienenSuEstiloEnLaHojaDeEstilos(): void
    {
        $css = (string) file_get_contents(dirname(__DIR__, 2) . '/public/assets/style.css');

        foreach ([Avisos::OK, Avisos::ATENCION] as $tipo) {
            self::assertStringContainsString(".aviso-{$tipo}", $css, $tipo);
        }
    }

    /** @return array<string, array{string, string, string}> pantalla, parametro, mensaje esperado para el id 12 */
    public static function confirmaciones(): array
    {
        return [
            'boleta creada' => ['cobros', 'creada', 'Boleta #12 creada.'],
            'boleta editada' => ['cobros', 'editada', 'Boleta #12 actualizada.'],
            'boleta anulada' => ['cobros', 'anulada', 'Boleta #12 anulada.'],
            'pago registrado' => ['pagos', 'creado', 'Pago #12 registrado.'],
            'pago editado' => ['pagos', 'editado', 'Pago #12 actualizado.'],
            'pago anulado' => ['pagos', 'anulado', 'Pago #12 anulado.'],
            'cliente creado' => ['cliente', 'creado', 'Cliente creado.'],
        ];
    }

    /** Las redirecciones llevaban estos parametros y ninguna pantalla los leia. */
    #[DataProvider('confirmaciones')]
    public function testCadaRedireccionSeConfirmaEnSuPantalla(string $pagina, string $parametro, string $mensaje): void
    {
        $_GET[$parametro] = '12';

        self::assertSame([Avisos::ok($mensaje)], Avisos::confirmaciones($pagina));
    }

    /** Los parametros son de una pantalla: ?creado= en Cobros (donde las boletas son "creada") no confirma nada. */
    public function testUnParametroDeOtraPantallaNoConfirmaNada(): void
    {
        $_GET['creado'] = '12';
        self::assertSame([], Avisos::confirmaciones('cobros'));

        $_GET = ['creada' => '12'];
        self::assertSame([], Avisos::confirmaciones('pagos'));
        self::assertSame([], Avisos::confirmaciones('cliente'));
        self::assertSame([], Avisos::confirmaciones('no-existe'));
    }

    /** Solo vale un id entero positivo, y el mensaje nunca repite texto de la URL. */
    public function testUnIdQueNoEsUnEnteroPositivoNoConfirmaNada(): void
    {
        foreach (['', ' ', '0', '-1', 'abc', '1.5', '12abc', '<b>1</b>', '99999999999999999999', '2147483648', '007'] as $valor) {
            $_GET['creada'] = $valor;

            self::assertSame([], Avisos::confirmaciones('cobros'), var_export($valor, true));
        }
    }

    public function testSiVienenVariosGanaElPrimeroDeLaPantalla(): void
    {
        $_GET = ['anulada' => '5', 'creada' => '7'];

        self::assertSame([Avisos::ok('Boleta #7 creada.')], Avisos::confirmaciones('cobros'));
    }
}
