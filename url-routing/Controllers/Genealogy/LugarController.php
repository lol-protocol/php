<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\LugarRepository;

/**
 * Lugar — hierarchical letter codes (country/region/city), not a numeric id.
 * $params['codes'] holds 1 to 3 lowercase codes, already shape-checked by
 * PlaceMatchStrategy.
 */
class LugarController extends BaseController
{
    /** @return array{0: ?string, 1: ?string} [ruta, error response] */
    private function ruta(array $params): array
    {
        $codes = $params['codes'] ?? null;
        if (!is_array($codes) || $codes === [] || count($codes) > 3) {
            return [null, $this->handleBadRequest('Código de lugar inválido')];
        }
        foreach ($codes as $code) {
            if (!is_string($code) || !ctype_alpha($code)) {
                return [null, $this->handleBadRequest('Código de lugar inválido')];
            }
        }
        return [LugarRepository::ruta($codes), null];
    }

    private function render(array $params, string $view, callable $extra): string
    {
        [$ruta, $error] = $this->ruta($params);
        if ($error !== null) {
            return $error;
        }

        $repo = new LugarRepository($this->db());
        $lugar = $repo->find($ruta);
        if ($lugar === null) {
            return $this->handleNotFound();
        }

        return view($view, ['lugar' => $lugar, 'jerarquia' => $repo->jerarquia($ruta)] + $extra($repo, $ruta));
    }

    public function show(array $params = []): string
    {
        return $this->render($params, 'genealogy/lugar/show',
            fn(LugarRepository $repo, string $ruta) => ['hijos' => $repo->hijos($ruta)]);
    }

    public function personas(array $params = []): string
    {
        return $this->render($params, 'genealogy/lugar/personas',
            fn(LugarRepository $repo, string $ruta) => ['personas' => $repo->personas($ruta)]);
    }

    public function sucesos(array $params = []): string
    {
        return $this->render($params, 'genealogy/lugar/sucesos',
            fn(LugarRepository $repo, string $ruta) => ['sucesos' => $repo->sucesos($ruta)]);
    }
}
