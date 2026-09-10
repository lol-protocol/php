<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\ErrorHandler;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ErrorHandlerTest extends TestCase
{
    public function testFormatearIncluyeMensajeArchivoYLinea(): void
    {
        $e = new RuntimeException('detalle interno de prueba');
        $linea = __LINE__ - 1;

        $log = ErrorHandler::formatear($e);

        self::assertStringContainsString('detalle interno de prueba', $log);
        self::assertStringContainsString(__FILE__, $log);
        self::assertStringContainsString((string) $linea, $log);
    }

    public function testManejarPone500YMuestraUnMensajeGenericoSinElDetalleInterno(): void
    {
        ob_start();
        ErrorHandler::manejar(new RuntimeException('contraseña de la base: secreta123'));
        $salida = ob_get_clean();

        self::assertSame(500, http_response_code());
        self::assertStringContainsString('Ocurrió un error', $salida);
        self::assertStringNotContainsString('secreta123', $salida, 'el detalle interno nunca debe llegar a la respuesta');
    }
}
