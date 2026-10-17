<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\ProductoRepository;
use App\Support\Cart;

class CheckoutController extends BaseController
{
    public function index(array $params = []): string
    {
        $carrito = new Cart();
        if ($carrito->estaVacio()) {
            $this->flash('Tu carrito está vacío.');
            return $this->redirect('/c/');
        }

        $repo = new ProductoRepository($this->db());
        $variantes = $repo->variantesPorSkus(array_keys($carrito->items()));
        $monedas = array_unique(array_column($variantes, 'moneda'));
        if (count($monedas) > 1) {
            // Checkout doesn't yet support an order that mixes currencies.
            $this->flash('Tu carrito tiene productos en distintas monedas: paga cada moneda por separado.');
            return $this->redirect('/c/');
        }

        return view('pos/checkout/index');
    }

    public function shipping(array $params = []): string
    {
        return view('pos/checkout/shipping');
    }

    public function payment(array $params = []): string
    {
        return view('pos/checkout/payment');
    }

    public function confirm(array $params = []): string
    {
        return view('pos/checkout/confirm');
    }
}
