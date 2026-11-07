<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\PersonaRepository;
use App\Support\Privacidad;
use App\Support\ServiceLocator;

/**
 * Persona — 10-digit id. A living persona is hidden from anyone but the owner
 * (see Privacidad): its page still exists, so a link from a tree doesn't break,
 * but it carries no name, dates, relatives or events. Hiding only the name
 * would not be enough: a living person's parents and events identify them.
 */
class PersonaController extends BaseController
{
    /** What the owner's form may send for personas.viva: yes, no, or "deduce it from the dates". */
    private const MARCAS = ['si' => true, 'no' => false, 'deducir' => null];

    private function repo(): PersonaRepository
    {
        return new PersonaRepository($this->db(), $this->privacidad());
    }

    /**
     * What a persona page lists about the persona: $lista($id) normally,
     * $vacio when the persona is hidden.
     *
     * @param callable(int): array<string, list<array<string, mixed>>> $lista
     * @param array<string, list<array<string, mixed>>> $vacio
     * @return callable(int, array<string, mixed>): array<string, list<array<string, mixed>>>
     */
    private static function salvoOculta(callable $lista, array $vacio): callable
    {
        return static fn(int $id, array $persona): array => $persona['oculta'] ? $vacio : $lista($id);
    }

    /**
     * A merged-away id (persona_alias) leads to the same page of the persona
     * it was merged into: $accion is that page's action code, null for the
     * persona's own page.
     *
     * @return callable(int): ?string
     */
    private static function movida(PersonaRepository $repo, ?int $accion): callable
    {
        return static function (int $id) use ($repo, $accion): ?string {
            $destino = $repo->aliasDe($id);
            if ($destino === null) {
                return null;
            }

            return $accion === null ? enlace('persona', $destino) : accion('persona', $destino, $accion);
        };
    }

    /**
     * The code of one of the persona's own actions in config/routes (/{id}/{code}/), asked of the router
     * that dispatched the request: the number is the config's to say, not this class's to repeat.
     */
    private static function codigoDe(string $accion): int
    {
        $codigo = ServiceLocator::getInstance()->getRouter()->actionCode('persona', $accion);
        if ($codigo === null) {
            throw new \LogicException("La acción «{$accion}» de persona no está en config/routes.");
        }

        return $codigo;
    }

    /**
     * One of the persona's pages: the standard read action (see renderFound()) with what $datos lists about
     * her ($vacio, with the same keys, when she is hidden). $accion is the name of the page's action in
     * config/routes, null for the persona's own page: a merged-away id leads to the same page of the persona
     * it was merged into.
     *
     * @param array<string, mixed> $params
     * @param callable(PersonaRepository, int): array<string, list<array<string, mixed>>> $datos
     * @param array<string, list<array<string, mixed>>> $vacio
     */
    private function pestana(array $params, ?string $accion, string $vista, callable $datos, array $vacio): string
    {
        $repo = $this->repo();

        return $this->renderFound(
            $params,
            $repo->find(...),
            "genealogy/persona/{$vista}",
            'persona',
            self::salvoOculta(static fn(int $id): array => $datos($repo, $id), $vacio),
            self::movida($repo, $accion === null ? null : self::codigoDe($accion))
        );
    }

    /**
     * @param array<string, mixed> $params
     */
    public function show(array $params = []): string
    {
        return $this->pestana($params, null, 'show',
            static fn(PersonaRepository $repo, int $id): array => ['vinculos' => $repo->vinculos($id), 'cronologia' => $repo->cronologia($id)],
            ['vinculos' => [], 'cronologia' => []]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function ascendencia(array $params = []): string
    {
        return $this->pestana($params, 'ascendencia', 'ascendencia',
            static fn(PersonaRepository $repo, int $id): array => ['ancestros' => $repo->ascendencia($id)],
            ['ancestros' => []]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function descendencia(array $params = []): string
    {
        return $this->pestana($params, 'descendencia', 'descendencia',
            static fn(PersonaRepository $repo, int $id): array => ['descendientes' => $repo->descendencia($id)],
            ['descendientes' => []]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function vinculos(array $params = []): string
    {
        return $this->pestana($params, 'vinculos', 'vinculos',
            static fn(PersonaRepository $repo, int $id): array => ['vinculos' => $repo->vinculos($id)],
            ['vinculos' => []]);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function cronologia(array $params = []): string
    {
        return $this->pestana($params, 'cronologia', 'cronologia',
            static fn(PersonaRepository $repo, int $id): array => ['eventos' => $repo->cronologia($id)],
            ['eventos' => []]);
    }

    /**
     * The owner's page to say whether a persona is alive (personas.viva): "si" and "no" override
     * what the dates say, "deducir" goes back to the dates. Anyone else is asked for the
     * credentials (401), as on a collection's edit page: the persona is public, so asking
     * confirms nothing. The form posts to this same URL.
     *
     * @param array<string, mixed> $params
     */
    public function viva(array $params = []): string
    {
        $denegado = $this->exigirMetodo('GET', 'HEAD', 'POST') ?? $this->exigirPropietario(ocultar: false);
        if ($denegado !== null) {
            return $denegado;
        }

        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Identificador inválido');
        }

        $repo = $this->repo();
        $persona = $repo->find((int)$id);
        if ($persona === null) {
            $destino = self::movida($repo, self::codigoDe('viva'))((int)$id);
            return $destino === null ? $this->handleNotFound() : $this->redirect($destino, 301);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            return $this->guardarMarcaViva($repo, (int)$id);
        }

        return view('genealogy/persona/privacidad', [
            'persona' => $persona,
            'marca' => $repo->marcaViva((int)$id),
            // What a visitor gets for this persona right now, whatever the reason.
            'comoLaVe' => (new PersonaRepository($this->db(), Privacidad::publica()))->find((int)$id),
        ]);
    }

    private function guardarMarcaViva(PersonaRepository $repo, int $id): string
    {
        if (!$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $valor = $this->postString('viva');
        if (!array_key_exists($valor, self::MARCAS)) {
            return $this->handleBadRequest('Valor inválido');
        }

        $repo->fijarViva($id, self::MARCAS[$valor]);
        // The id and the new value only: never a name.
        log_info('personas.viva changed', ['persona_id' => $id, 'viva' => $valor]);
        $this->flash('Guardado.');

        return $this->redirect(accion('persona', $id, 5));
    }
}
