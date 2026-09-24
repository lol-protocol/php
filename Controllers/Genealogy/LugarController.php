<?php

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;

/**
 * Lugar — codigos de texto jerarquicos (pais/region/ciudad), no un id
 * numerico. $params['codes'] trae entre 1 y 3 codigos en minusculas,
 * ya validados por el router (ver Router::matchPlace()).
 */
class LugarController extends BaseController
{
    private function validateCodes($codes)
    {
        if (!isset($codes) || !is_array($codes) || empty($codes)) {
            return null;
        }
        if (count($codes) > 3) {
            return null;
        }
        foreach ($codes as $code) {
            if (!ctype_alpha($code)) {
                return null;
            }
        }
        return $codes;
    }

    public function show($params = [])
    {
        $codes = $this->validateCodes($params['codes'] ?? null);
        if ($codes === null) {
            return $this->handleBadRequest('Invalid place codes');
        }

        // TODO: fetch from DB by $codes
        $lugar = [];

        return view('genealogy/lugar/show', [
            'codes' => $codes,
            'lugar' => $lugar,
        ]);
    }

    public function personas($params = [])
    {
        $codes = $this->validateCodes($params['codes'] ?? null);
        if ($codes === null) {
            return $this->handleBadRequest('Invalid place codes');
        }

        $personas = [];

        return view('genealogy/lugar/personas', [
            'codes' => $codes,
            'personas' => $personas,
        ]);
    }

    public function sucesos($params = [])
    {
        $codes = $this->validateCodes($params['codes'] ?? null);
        if ($codes === null) {
            return $this->handleBadRequest('Invalid place codes');
        }

        $sucesos = [];

        return view('genealogy/lugar/sucesos', [
            'codes' => $codes,
            'sucesos' => $sucesos,
        ]);
    }
}
