<?php

namespace App\Controllers\Genealogy;

/**
 * Persona — identificador numerico de 10 digitos.
 * El largo del segmento (10) es lo que hace que el router llegue aqui.
 */
class PersonaController
{
    public function show($params = [])
    {
        $persona = []; // TODO: fetch from DB by $params['id']

        return view('genealogy/persona/show', ['persona' => $persona]);
    }

    public function ascendencia($params = [])
    {
        $ascendencia = [];

        return view('genealogy/persona/ascendencia', [
            'id' => $params['id'],
            'ascendencia' => $ascendencia,
        ]);
    }

    public function descendencia($params = [])
    {
        $descendencia = [];

        return view('genealogy/persona/descendencia', [
            'id' => $params['id'],
            'descendencia' => $descendencia,
        ]);
    }

    public function vinculos($params = [])
    {
        $vinculos = [];

        return view('genealogy/persona/vinculos', [
            'id' => $params['id'],
            'vinculos' => $vinculos,
        ]);
    }

    public function cronologia($params = [])
    {
        $eventos = [];

        return view('genealogy/persona/cronologia', [
            'id' => $params['id'],
            'eventos' => $eventos,
        ]);
    }
}
