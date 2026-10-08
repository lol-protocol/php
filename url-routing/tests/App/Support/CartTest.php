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
}
