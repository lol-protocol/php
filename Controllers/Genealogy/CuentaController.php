<?php

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;

class CuentaController extends BaseController
{
    public function index($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Login required to access account');
        }

        return view('genealogy/cuenta/index', ['userId' => $this->getCurrentUserId()]);
    }

    public function colecciones($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Login required to access collections');
        }

        // TODO: fetch user collections from DB filtered by getCurrentUserId()
        $colecciones = [];

        return view('genealogy/cuenta/colecciones', ['colecciones' => $colecciones]);
    }

    public function aportes($params = [])
    {
        if (!$this->requireAuth()) {
            return $this->handleUnauthorized('Login required to view contributions');
        }

        // TODO: fetch user contributions from DB filtered by getCurrentUserId()
        $aportes = [];

        return view('genealogy/cuenta/aportes', ['aportes' => $aportes]);
    }
}
