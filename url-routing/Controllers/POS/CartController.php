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
        $lineas = [];
        $total = 0;

        foreach ($this->carrito()->items() as $sku => $cantidad) {
            $variante = $repo->variantePorSku($sku);
            if ($variante === null) {
                // Removed from the catalog since it was added; drop it silently.
                $this->carrito()->quitar($sku);
                continue;
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

        $sku = (string)($_POST['sku'] ?? '');
        $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));

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

        $sku = (string)($_POST['sku'] ?? '');
        $cantidad = (int)($_POST['cantidad'] ?? 0);

        $repo = new ProductoRepository($this->db());
        $variante = $repo->variantePorSku($sku);
        if ($variante === null) {
            // Removed from the catalog since it was added; drop the line instead of erroring.
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

        $this->carrito()->quitar((string)($_POST['sku'] ?? ''));

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
