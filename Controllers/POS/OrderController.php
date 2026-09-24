<?php

declare(strict_types=1);

namespace App\Controllers\POS;

use App\Controllers\BaseController;

class OrderController extends BaseController
{
    public function show($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized();
        }

        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid order ID');
        }

        // TODO: fetch from DB by $id, verify ownership via getCurrentUserId()
        // if (!$this->validateResourceOwnership($order['user_id'])) {
        //     return $this->handleForbidden();
        // }
        $order = [];

        return view('pos/order/show', ['order' => $order]);
    }

    public function invoice($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized();
        }

        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid order ID');
        }

        return view('pos/order/invoice', ['id' => $id]);
    }

    public function track($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized();
        }

        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid order ID');
        }

        return view('pos/order/track', ['id' => $id]);
    }

    public function devolucion($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized();
        }

        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid order ID');
        }

        return view('pos/order/devolucion', ['id' => $id]);
    }
}
