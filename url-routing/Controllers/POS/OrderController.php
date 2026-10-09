<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\OrdenRepository;

/**
 * /order/{id}/ — an order holds the shipping address and what was bought, and
 * nothing here ties an order to the browser that placed it (the checkout pages
 * create none), so a link alone must not open it: orders are owner-only.
 */
class OrderController extends BaseController
{
    private function render(array $params, string $view, callable $extra): string
    {
        return $this->soloPropietario(function () use ($params, $view, $extra): string {
            $repo = new OrdenRepository($this->db());
            return $this->renderFound($params, $repo->find(...), $view, 'orden', $extra);
        });
    }

    public function show(array $params = []): string
    {
        return $this->render($params, 'pos/order/show', function (int $id): array {
            $repo = new OrdenRepository($this->db());
            return ['items' => $repo->items($id), 'eventos' => $repo->eventos($id)];
        });
    }

    public function invoice(array $params = []): string
    {
        return $this->render($params, 'pos/order/invoice',
            fn(int $id) => ['items' => (new OrdenRepository($this->db()))->items($id)]);
    }

    public function track(array $params = []): string
    {
        return $this->render($params, 'pos/order/track',
            fn(int $id) => ['eventos' => (new OrdenRepository($this->db()))->eventos($id)]);
    }

    public function devolucion(array $params = []): string
    {
        return $this->render($params, 'pos/order/devolucion',
            fn(int $id) => ['items' => (new OrdenRepository($this->db()))->items($id)]);
    }
}
