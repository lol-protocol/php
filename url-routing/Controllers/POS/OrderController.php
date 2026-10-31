<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;
use App\Repositories\POS\OrdenRepository;
use App\Support\AccessPolicy;

/**
 * /{codigo}/ — 16 random digits (ordenes.codigo), never the order's row id.
 * An order holds the shipping address and what was bought, so a link alone
 * must not open it: only the owner, and the session that placed it (see
 * CheckoutController::confirm()), can.
 */
class OrderController extends BaseController
{
    /**
     * @param array<string, mixed> $params
     */
    private function render(array $params, string $view, callable $extra): string
    {
        $mostrar = function () use ($params, $view, $extra): string {
            $repo = new OrdenRepository($this->db());
            // $extra gets the row id: items and events hang off it, not off the code.
            return $this->renderFound($params, $repo->porCodigo(...), $view, 'orden',
                static fn(int $codigo, array $orden): array => $extra((int)$orden['id']));
        };

        // The browser that placed the order may open it too; it holds the
        // address, so no shared cache may keep the page.
        $deEstaSesion = array_map('strval', $_SESSION[CheckoutController::SESION_ORDENES] ?? []);
        if (in_array((string)($params['id'] ?? ''), $deEstaSesion, true)) {
            AccessPolicy::marcarPrivada();
            return $mostrar();
        }

        return $this->soloPropietario($mostrar);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function show(array $params = []): string
    {
        return $this->render($params, 'pos/order/show', function (int $id): array {
            $repo = new OrdenRepository($this->db());
            return ['items' => $repo->items($id), 'eventos' => $repo->eventos($id)];
        });
    }

    /**
     * @param array<string, mixed> $params
     */
    public function invoice(array $params = []): string
    {
        return $this->render($params, 'pos/order/invoice',
            fn(int $id) => ['items' => (new OrdenRepository($this->db()))->items($id)]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function track(array $params = []): string
    {
        return $this->render($params, 'pos/order/track',
            fn(int $id) => ['eventos' => (new OrdenRepository($this->db()))->eventos($id)]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function devolucion(array $params = []): string
    {
        return $this->render($params, 'pos/order/devolucion',
            fn(int $id) => ['items' => (new OrdenRepository($this->db()))->items($id)]);
    }
}
