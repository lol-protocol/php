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

    public function show(array $params = []): string
    {
        $repo = new ProductoRepository($this->db());
        $carrito = $this->carrito();
        $lineas = [];
        $totales = [];

        foreach ($carrito->items() as $sku => $cantidad) {
            $variante = $repo->variantePorSku($sku);
            if ($variante === null || !$variante['activo']) {
                // Removed or deactivated since it was added: it can't be bought anymore.
                $carrito->quitar($sku);
                continue;
            }

            if ($cantidad > $variante['stock']) {
                // Stock dropped since it was added: show (and keep) what can actually be bought.
                $carrito->actualizar($sku, $variante['stock']);
                $cantidad = $carrito->cantidadDe($sku);
                if ($cantidad === 0) {
                    continue;
                }
            }

            $subtotal = $variante['precio_centavos'] * $cantidad;
            // One total per currency: adding centavos of different currencies is meaningless.
            $totales[$variante['moneda']] = ($totales[$variante['moneda']] ?? 0) + $subtotal;
            $lineas[] = ['cantidad' => $cantidad, 'subtotal_centavos' => $subtotal] + $variante;
        }

        return view('pos/cart/show', ['lineas' => $lineas, 'totales' => $totales]);
    }

    public function agregar(array $params = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $sku = $this->postString('sku');
        $cantidad = max(1, (int)$this->postString('cantidad', '1'));

        $repo = new ProductoRepository($this->db());
        $variante = $repo->variantePorSku($sku);
        if ($variante === null || !$variante['activo'] || $variante['stock'] < 1) {
            return $this->handleBadRequest('Variante no disponible');
        }

        $carrito = $this->carrito();
        $carrito->actualizar($sku, min($carrito->cantidadDe($sku) + $cantidad, $variante['stock']));

        return $this->redirect('/cart/');
    }

    public function actualizar(array $params = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $sku = $this->postString('sku');
        $cantidad = (int)$this->postString('cantidad', '0');

        $repo = new ProductoRepository($this->db());
        $variante = $repo->variantePorSku($sku);
        if ($variante === null || !$variante['activo']) {
            // Removed or deactivated since it was added; drop the line instead of erroring.
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

        $this->carrito()->quitar($this->postString('sku'));

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
