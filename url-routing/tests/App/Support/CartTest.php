<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\Cart;
use PHPUnit\Framework\TestCase;

class CartTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testStartsEmpty(): void
    {
        $carrito = new Cart();

        $this->assertTrue($carrito->estaVacio());
        $this->assertSame([], $carrito->items());
        $this->assertSame(0, $carrito->totalArticulos());
        $this->assertSame(0, $carrito->cantidadDe('SKU1'));
    }

    public function testActualizarSetsTheAbsoluteQuantity(): void
    {
        $carrito = new Cart();

        $carrito->actualizar('SKU1', 2);
        $carrito->actualizar('SKU1', 5);

        $this->assertSame(5, $carrito->cantidadDe('SKU1'));
        $this->assertSame(['SKU1' => 5], $carrito->items());
    }

    public function testActualizarWithZeroOrLessRemovesTheLine(): void
    {
        $carrito = new Cart();
        $carrito->actualizar('SKU1', 3);

        $carrito->actualizar('SKU1', 0);

        $this->assertTrue($carrito->estaVacio());
    }

    public function testActualizarClampsToAHardCeiling(): void
    {
        $carrito = new Cart();

        $carrito->actualizar('SKU1', 500);

        $this->assertLessThanOrEqual(99, $carrito->cantidadDe('SKU1'));
    }

    public function testQuitarRemovesOnlyThatLine(): void
    {
        $carrito = new Cart();
        $carrito->actualizar('SKU1', 1);
        $carrito->actualizar('SKU2', 2);

        $carrito->quitar('SKU1');

        $this->assertSame(['SKU2' => 2], $carrito->items());
    }

    public function testVaciarRemovesEverything(): void
    {
        $carrito = new Cart();
        $carrito->actualizar('SKU1', 1);
        $carrito->actualizar('SKU2', 2);

        $carrito->vaciar();

        $this->assertTrue($carrito->estaVacio());
    }

    public function testTotalArticulosSumsAcrossLines(): void
    {
        $carrito = new Cart();
        $carrito->actualizar('SKU1', 2);
        $carrito->actualizar('SKU2', 3);

        $this->assertSame(5, $carrito->totalArticulos());
    }

    public function testActualizarReturnsTrueAndSucceedsUnderTheLineCap(): void
    {
        $carrito = new Cart();

        $this->assertTrue($carrito->actualizar('SKU1', 1));
    }

    public function testActualizarRejectsANewLineOnceAtTheLineCapButAllowsUpdatingExistingOnes(): void
    {
        $carrito = new Cart();
        for ($i = 0; $i < Cart::MAX_LINEAS; $i++) {
            $carrito->actualizar("SKU{$i}", 1);
        }

        $this->assertFalse($carrito->actualizar('SKU-EXTRA', 1));
        $this->assertSame(Cart::MAX_LINEAS, count($carrito->items()));

        $this->assertTrue($carrito->actualizar('SKU0', 5));
        $this->assertSame(5, $carrito->cantidadDe('SKU0'));
    }

    public function testActualizarToZeroAtTheLineCapStillRemovesTheLine(): void
    {
        $carrito = new Cart();
        for ($i = 0; $i < Cart::MAX_LINEAS; $i++) {
            $carrito->actualizar("SKU{$i}", 1);
        }

        $this->assertTrue($carrito->actualizar('SKU0', 0));
        $this->assertSame(Cart::MAX_LINEAS - 1, count($carrito->items()));
    }

    /** @return array<string, array<string, mixed>> */
    private static function variante(int $stock, bool $activo = true): array
    {
        return ['activo' => $activo, 'stock' => $stock, 'precio_centavos' => 1000, 'moneda' => 'MXN'];
    }

    /**
     * PHP vuelve int la clave «12345»: un SKU que es un código de barras llegaba a quitar()/actualizar() como int y,
     * con strict_types, era un TypeError (un 500 en /c/ y /p/) justo cuando la variante se desactivaba o se agotaba.
     */
    public function testRevisarDropsALineWhoseNumericSkuNoLongerExists(): void
    {
        $carrito = new Cart();
        $carrito->actualizar('7501031311309', 2);

        [$lineas, $totales] = $carrito->revisar([]);

        $this->assertSame([], $lineas);
        $this->assertSame([], $totales);
        $this->assertTrue($carrito->estaVacio());
    }

    public function testRevisarCutsANumericSkuDownToTheStockLeft(): void
    {
        $carrito = new Cart();
        $carrito->actualizar('12345', 5);

        [$lineas, $totales] = $carrito->revisar(['12345' => self::variante(2)]);

        $this->assertSame(2, $lineas[0]['cantidad']);
        $this->assertSame(2000, $lineas[0]['subtotal_centavos']);
        $this->assertSame(['MXN' => 2000], $totales);
        $this->assertSame(2, $carrito->cantidadDe('12345'));
    }

    public function testRevisarDropsANumericSkuWhoseVariantIsInactiveOrSoldOut(): void
    {
        $carrito = new Cart();
        $carrito->actualizar('12345', 1);
        $carrito->actualizar('67890', 1);

        [$lineas] = $carrito->revisar(['12345' => self::variante(3, activo: false), '67890' => self::variante(0)]);

        $this->assertSame([], $lineas);
        $this->assertTrue($carrito->estaVacio());
    }

    public function testSkusAreStringsEvenWhenTheyAreNumbers(): void
    {
        $carrito = new Cart();
        $carrito->actualizar('12345', 1);
        $carrito->actualizar('CAM-AZ-S', 1);

        $this->assertSame(['12345', 'CAM-AZ-S'], $carrito->skus());
    }
}
