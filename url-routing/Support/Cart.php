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

    /** Distinct SKU lines; a cart with lots of different products stops growing instead of becoming unusable. */
    public const MAX_LINEAS = 20;

    /**
     * @return array<string, int> sku => cantidad. Ojo: PHP vuelve `int` toda clave que sea un número escrito
     *         («12345», un código de barras), así que el SKU de un foreach sobre esto no siempre es un string:
     *         para recorrer SKUs, skus().
     */
    public function items(): array
    {
        return $_SESSION['carrito'] ?? [];
    }

    /** @return list<string> los SKU del carrito, siempre como string (el que piden el catálogo y los métodos de esta clase) */
    public function skus(): array
    {
        return array_map('strval', array_keys($this->items()));
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

    /**
     * Sets the absolute quantity for a SKU; 0 or less removes it.
     * Returns false, without changing anything, if this would add a new line
     * past MAX_LINEAS (updating an existing line is always allowed).
     */
    public function actualizar(string $sku, int $cantidad): bool
    {
        if ($cantidad <= 0) {
            $this->quitar($sku);
            return true;
        }

        if (!isset($_SESSION['carrito'][$sku]) && count($this->items()) >= self::MAX_LINEAS) {
            return false;
        }

        SessionManager::getInstance()->ensureStarted();
        $_SESSION['carrito'][$sku] = min($cantidad, self::MAX_CANTIDAD);
        return true;
    }

    /**
     * The cart checked against the catalog ($variantes: sku => variant row, as
     * ProductoRepository::variantesPorSkus() returns them): a line whose
     * product was removed or deactivated leaves the cart, and one above the
     * current stock is cut down to it (and kept that way). Returns the lines
     * that can be bought, each variant row plus `cantidad` and
     * `subtotal_centavos`, and one total per currency: adding centavos of
     * different currencies is meaningless.
     *
     * @param array<string, array<string, mixed>> $variantes
     * @return array{list<array<string, mixed>>, array<string, int>} [lineas, totales]
     */
    public function revisar(array $variantes): array
    {
        $lineas = [];
        $totales = [];

        foreach ($this->items() as $sku => $cantidad) {
            $sku = (string)$sku;
            $variante = $variantes[$sku] ?? null;
            if ($variante === null || !$variante['activo']) {
                $this->quitar($sku);
                continue;
            }

            if ($cantidad > $variante['stock']) {
                $this->actualizar($sku, (int)$variante['stock']);
                $cantidad = $this->cantidadDe($sku);
                if ($cantidad === 0) {
                    continue;
                }
            }

            $subtotal = $variante['precio_centavos'] * $cantidad;
            $totales[$variante['moneda']] = ($totales[$variante['moneda']] ?? 0) + $subtotal;
            $lineas[] = ['cantidad' => $cantidad, 'subtotal_centavos' => $subtotal] + $variante;
        }

        return [$lineas, $totales];
    }

    public function quitar(string $sku): void
    {
        SessionManager::getInstance()->ensureStarted();
        unset($_SESSION['carrito'][$sku]);
    }

    public function vaciar(): void
    {
        SessionManager::getInstance()->ensureStarted();
        $_SESSION['carrito'] = [];
    }
}
