<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The shopping cart lives in the session, not the database: there's no
 * login, so it's tied to the browser, not an account.
 */
final class Cart
{
    private const MAX_CANTIDAD = 99;

    /** @return array<string, int> sku => cantidad */
    public function items(): array
    {
        return $_SESSION['carrito'] ?? [];
    }

    public function estaVacio(): bool
    {
        return $this->items() === [];
    }

    public function totalArticulos(): int
    {
        return array_sum($this->items());
    }

    public function cantidadDe(string $sku): int
    {
        return $this->items()[$sku] ?? 0;
    }

    /** Sets the absolute quantity for a SKU; 0 or less removes it. */
    public function actualizar(string $sku, int $cantidad): void
    {
        if ($cantidad <= 0) {
            $this->quitar($sku);
            return;
        }

        $_SESSION['carrito'][$sku] = min($cantidad, self::MAX_CANTIDAD);
    }

    public function quitar(string $sku): void
    {
        unset($_SESSION['carrito'][$sku]);
    }

    public function vaciar(): void
    {
        $_SESSION['carrito'] = [];
    }
}
