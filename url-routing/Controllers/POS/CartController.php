<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\ProductoRepository;
use App\Support\Cart;

/** /c/ — the cart lives in the session: there's no login, so it's tied to the browser, not an account. */
class CartController extends BaseController
{
    private function carrito(): Cart
    {
        return new Cart();
    }

    /** A variant usable for buying: exists and is active; null otherwise. */
    private function variante(ProductoRepository $repo, string $sku): ?array
    {
        $variante = $repo->variantePorSku($sku);
        return ($variante === null || !$variante['activo']) ? null : $variante;
    }

    public function show(array $params = []): string
    {
        $repo = new ProductoRepository($this->db());
        $carrito = $this->carrito();
        $variantes = $repo->variantesPorSkus(array_keys($carrito->items()));
        $lineas = [];
        $totales = [];

        foreach ($carrito->items() as $sku => $cantidad) {
            $variante = $variantes[$sku] ?? null;
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
        $variante = $this->variante($repo, $sku);
        if ($variante === null || $variante['stock'] < 1) {
            return $this->handleBadRequest('Variante no disponible');
        }

        $carrito = $this->carrito();
        $deseado = $carrito->cantidadDe($sku) + $cantidad;
        $final = min($deseado, $variante['stock']);

        if (!$carrito->actualizar($sku, $final)) {
            return $this->handleBadRequest('El carrito ya tiene demasiados productos distintos');
        }

        if ($final < $deseado) {
            $this->flash('Se agregó menos de lo pedido: no hay suficiente stock.');
        }

        return $this->redirect('/c/');
    }

    public function actualizar(array $params = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $sku = $this->postString('sku');
        $cantidadRaw = $this->postString('cantidad');
        if (!ctype_digit($cantidadRaw)) {
            return $this->handleBadRequest('Cantidad inválida');
        }
        $cantidad = (int)$cantidadRaw;

        $repo = new ProductoRepository($this->db());
        $variante = $this->variante($repo, $sku);
        if ($variante === null) {
            // Removed or deactivated since it was added; drop the line instead of erroring.
            $this->carrito()->quitar($sku);
            return $this->redirect('/c/');
        }

        $this->carrito()->actualizar($sku, min($cantidad, $variante['stock']));

        return $this->redirect('/c/');
    }

    public function quitar(array $params = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $this->carrito()->quitar($this->postString('sku'));

        return $this->redirect('/c/');
    }

    public function vaciar(array $params = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $this->carrito()->vaciar();

        return $this->redirect('/c/');
    }
}
