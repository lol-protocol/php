<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;

/**
 * Persona — identificador numerico de 10 digitos.
 * El largo del segmento (10) es lo que hace que el router llegue aqui.
 */
class PersonaController extends BaseController
{
    public function show($params = [])
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid person ID');
        }

        // TODO: fetch from DB by $id
        $persona = [];

        return view('genealogy/persona/show', ['persona' => $persona]);
    }

    public function ascendencia($params = [])
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid person ID');
        }

        $ascendencia = [];

        return view('genealogy/persona/ascendencia', [
            'id' => $id,
            'ascendencia' => $ascendencia,
        ]);
    }

    public function descendencia($params = [])
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid person ID');
        }

        $descendencia = [];

        return view('genealogy/persona/descendencia', [
            'id' => $id,
            'descendencia' => $descendencia,
        ]);
    }

    public function vinculos($params = [])
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid person ID');
        }

        $vinculos = [];

        return view('genealogy/persona/vinculos', [
            'id' => $id,
            'vinculos' => $vinculos,
        ]);
    }

    public function cronologia($params = [])
    {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Invalid person ID');
        }

        $eventos = [];

        return view('genealogy/persona/cronologia', [
            'id' => $id,
            'eventos' => $eventos,
        ]);
    }
}
