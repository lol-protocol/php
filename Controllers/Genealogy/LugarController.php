<?php

namespace App\Controllers\Genealogy;

/**
 * Lugar — codigos de texto jerarquicos (pais/region/ciudad), no un id
 * numerico. $params['codes'] trae entre 1 y 3 codigos en minusculas,
 * ya validados por el router (ver Router::matchPlace()).
 */
class LugarController
{
    public function show($params = [])
    {
        $lugar = []; // TODO: fetch from DB by $params['codes']

        return view('genealogy/lugar/show', [
            'codes' => $params['codes'],
            'lugar' => $lugar,
        ]);
    }

    public function personas($params = [])
    {
        $personas = [];

        return view('genealogy/lugar/personas', [
            'codes' => $params['codes'],
            'personas' => $personas,
        ]);
    }

    public function sucesos($params = [])
    {
        $sucesos = [];

        return view('genealogy/lugar/sucesos', [
            'codes' => $params['codes'],
            'sucesos' => $sucesos,
        ]);
    }
}
