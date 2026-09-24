<?php

namespace App\Controllers\POS;

use App\Controllers\BaseController;

class CuentaController extends BaseController
{
    public function index($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Login required to access account');
        }

        return view('pos/cuenta/index', ['userId' => $this->getCurrentUserId()]);
    }

    public function perfil($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Login required to access profile');
        }

        return view('pos/cuenta/perfil', ['userId' => $this->getCurrentUserId()]);
    }

    public function ordenes($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Login required to view orders');
        }

        // TODO: fetch user orders from DB filtered by getCurrentUserId()
        $ordenes = [];

        return view('pos/cuenta/ordenes', ['ordenes' => $ordenes]);
    }

    public function deseos($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Login required to view wishlist');
        }

        // TODO: fetch user wishlist from DB filtered by getCurrentUserId()
        $deseos = [];

        return view('pos/cuenta/deseos', ['deseos' => $deseos]);
    }

    public function direcciones($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Login required to manage addresses');
        }

        // TODO: fetch user addresses from DB filtered by getCurrentUserId()
        $direcciones = [];

        return view('pos/cuenta/direcciones', ['direcciones' => $direcciones]);
    }

    public function preferencias($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Login required to manage preferences');
        }

        return view('pos/cuenta/preferencias', ['userId' => $this->getCurrentUserId()]);
    }
}
