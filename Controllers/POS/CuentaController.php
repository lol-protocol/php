<?php

namespace App\Controllers\POS;

class CuentaController
{
    public function index($params = [])
    {
        // TODO: requiere sesion iniciada
        return view('pos/cuenta/index');
    }

    public function perfil($params = [])
    {
        return view('pos/cuenta/perfil');
    }

    public function ordenes($params = [])
    {
        $ordenes = [];

        return view('pos/cuenta/ordenes', ['ordenes' => $ordenes]);
    }

    public function deseos($params = [])
    {
        $deseos = [];

        return view('pos/cuenta/deseos', ['deseos' => $deseos]);
    }

    public function direcciones($params = [])
    {
        $direcciones = [];

        return view('pos/cuenta/direcciones', ['direcciones' => $direcciones]);
    }

    public function preferencias($params = [])
    {
        return view('pos/cuenta/preferencias');
    }
}
