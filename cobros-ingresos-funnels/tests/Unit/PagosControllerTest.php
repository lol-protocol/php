<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controllers\PagosController;
use PHPUnit\Framework\TestCase;

final class PagosControllerTest extends TestCase
{
    public function testBoletaValidaDelMismoClienteYSinAnular(): void
    {
        $boleta = ['cliente_id' => 5, 'anulada' => false];

        self::assertTrue(PagosController::boletaEsValidaParaCliente($boleta, 5));
    }

    public function testBoletaInexistenteNoEsValida(): void
    {
        self::assertFalse(PagosController::boletaEsValidaParaCliente(null, 5));
    }

    public function testBoletaDeOtroClienteNoEsValida(): void
    {
        $boleta = ['cliente_id' => 5, 'anulada' => false];

        self::assertFalse(PagosController::boletaEsValidaParaCliente($boleta, 9));
    }

    public function testBoletaAnuladaNoEsValidaAunSiendoDelMismoCliente(): void
    {
        $boleta = ['cliente_id' => 5, 'anulada' => true];

        self::assertFalse(PagosController::boletaEsValidaParaCliente($boleta, 5));
    }
}
