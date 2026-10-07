<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\ProductoRepository;
use App\Support\Cart;

/** /cart/ — the cart lives in the session: there's no login, so it's tied to the browser, not an account. */
class CartController extends BaseController
{
    private function carrito(): Cart
    {
        return new Cart();
    }

    private function postSku(): string
    {
        $sku = $_POST['sku'] ?? '';
        return is_string($sku) ? $sku : '';
    }

    public function show(array $params = []): string
    {
        $repo = new ProductoRepository($this->db());
        $carrito = $this->carrito();
        $lineas = [];
        $total = 0;

        foreach ($carrito->items() as $sku => $cantidad) {
            $variante = $repo->variantePorSku($sku);
            if ($variante === null || !$variante['activo']) {
                // Removed from the catalog, or discontinued, since it was added.
                $carrito->quitar($sku);
                continue;
            }

            // Stock may have dropped below what's in the cart since it was added; re-clamp
            // and persist it so the session stays consistent with what's actually buyable.
            if ($cantidad > $variante['stock']) {
                $cantidad = $variante['stock'];
                $carrito->actualizar($sku, $cantidad); // 0 removes the line
                if ($cantidad < 1) {
                    continue;
                }
            }

            $subtotal = $variante['precio_centavos'] * $cantidad;
            $total += $subtotal;
            $lineas[] = ['cantidad' => $cantidad, 'subtotal_centavos' => $subtotal] + $variante;
        }

        return view('pos/cart/show', [
            'lineas' => $lineas,
            'total_centavos' => $total,
            'moneda' => $lineas[0]['moneda'] ?? 'MXN',
        ]);
    }

    public function agregar(array $params = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $sku = $this->postSku();
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));

        $repo = new ProductoRepository($this->db());
        $variante = $repo->variantePorSku($sku);
        if ($variante === null || !$variante['activo'] || $variante['stock'] < 1) {
            return $this->handleBadRequest('Variante no disponible');
        }

        $carrito = $this->carrito();

        // The cart doesn't total across currencies, so a product in a different
        // currency than what's already in the cart can't be added alongside it.
        foreach ($carrito->items() as $otraSku => $otraCantidad) {
            $otra = $repo->variantePorSku((string)$otraSku);
            if ($otra !== null && $otra['moneda'] !== $variante['moneda']) {
                return $this->handleBadRequest('El carrito no puede mezclar monedas');
            }
        }

        $carrito->actualizar($sku, min($carrito->cantidadDe($sku) + $cantidad, $variante['stock']));

        return $this->redirect('/cart/');
    }

    public function actualizar(array $params = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $sku = $this->postSku();
        $cantidad = (int)($_POST['cantidad'] ?? 0);

        $repo = new ProductoRepository($this->db());
        $variante = $repo->variantePorSku($sku);
        if ($variante === null || !$variante['activo']) {
            // Removed from the catalog, or discontinued, since it was added; drop the line.
            $this->carrito()->quitar($sku);
            return $this->redirect('/cart/');
        }

        $this->carrito()->actualizar($sku, min($cantidad, $variante['stock']));

        return $this->redirect('/cart/');
    }

    public function quitar(array $params = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $this->carrito()->quitar($this->postSku());

        return $this->redirect('/cart/');
    }

    public function vaciar(array $params = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $this->carrito()->vaciar();

        return $this->redirect('/cart/');
    }
}
