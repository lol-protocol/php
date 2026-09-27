<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\OrdenRepository;

/** /order/{id}/ — there is no login, so any order id is reachable by anyone with the link. */
class OrderController extends BaseController
{
    private function render(array $params, string $view, callable $extra): string
    {
        $repo = new OrdenRepository($this->db());
        return $this->renderFound($params, $repo->find(...), $view, 'orden', $extra);
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
