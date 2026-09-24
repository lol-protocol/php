<?php

namespace App\Controllers\POS;

/**
 * Producto — identificador numerico de 8 digitos.
 * El largo del segmento (8) es lo que hace que el router llegue aqui.
 */
class ProductoController
{
    public function show($params = [])
    {
        $producto = []; // TODO: fetch from DB by $params['id']

        return view('pos/producto/show', ['producto' => $producto]);
    }

    public function variantes($params = [])
    {
        $variantes = [];

        return view('pos/producto/variantes', [
            'id' => $params['id'],
            'variantes' => $variantes,
        ]);
    }

    public function atributos($params = [])
    {
        $atributos = [];

        return view('pos/producto/atributos', [
            'id' => $params['id'],
            'atributos' => $atributos,
        ]);
    }
}
