<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Avisos;
use PHPUnit\Framework\TestCase;

final class AvisosTest extends TestCase
{
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
}
