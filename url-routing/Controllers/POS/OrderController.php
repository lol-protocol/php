<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\OrdenRepository;

/**
 * /order/{id}/ — visible only to the order's owner. Anyone else gets a 404,
 * not a 403: order ids are sequential and a 403 would confirm which exist.
 */
class OrderController extends BaseController
{
    private function propia(int $id): ?array
    {
        $orden = (new OrdenRepository($this->db()))->find($id);
        return $orden !== null && $this->isOwner($orden['usuario_id']) ? $orden : null;
    }

    private function render(array $params, string $view, callable $extra): string
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Inicia sesión para ver tus órdenes');
        }

        return $this->renderFound($params, $this->propia(...), $view, 'orden', $extra);
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
