<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\PersonaRepository;

/**
 * Persona — 10-digit id. A living persona is hidden from anyone but the owner
 * (see Privacidad): its page still exists, so a link from a tree doesn't break,
 * but it carries no name, dates, relatives or events. Hiding only the name
 * would not be enough: a living person's parents and events identify them.
 */
class PersonaController extends BaseController
{
    private function repo(): PersonaRepository
    {
        return new PersonaRepository($this->db(), $this->privacidad());
    }

    /**
     * What a persona page lists about the persona: $lista($id) normally,
     * $vacio when the persona is hidden.
     *
     * @param callable(int): array $lista
     * @param array<string, list<array>> $vacio
     * @return callable(int, array): array
     */
    private static function salvoOculta(callable $lista, array $vacio): callable
    {
        return static fn(int $id, array $persona): array => $persona['oculta'] ? $vacio : $lista($id);
    }

    public function show(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/show', 'persona',
            self::salvoOculta(
                fn(int $id) => ['vinculos' => $repo->vinculos($id), 'cronologia' => $repo->cronologia($id)],
                ['vinculos' => [], 'cronologia' => []]
            ));
    }

    public function ascendencia(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/ascendencia', 'persona',
            self::salvoOculta(fn(int $id) => ['ancestros' => $repo->ascendencia($id)], ['ancestros' => []]));
    }

    public function descendencia(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/descendencia', 'persona',
            self::salvoOculta(fn(int $id) => ['descendientes' => $repo->descendencia($id)], ['descendientes' => []]));
    }

    public function vinculos(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/vinculos', 'persona',
            self::salvoOculta(fn(int $id) => ['vinculos' => $repo->vinculos($id)], ['vinculos' => []]));
    }

    public function cronologia(array $params = []): string
    {
        $repo = $this->repo();
        return $this->renderFound($params, $repo->find(...), 'genealogy/persona/cronologia', 'persona',
            self::salvoOculta(fn(int $id) => ['eventos' => $repo->cronologia($id)], ['eventos' => []]));
    }
}
