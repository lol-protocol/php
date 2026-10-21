<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\ColeccionRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Repositories\Genealogy\RegistroRepository;
use App\Repositories\Genealogy\RetiroRepository;
use App\Repositories\UsuarioRepository;

/**
 * Account area (/0/) — there are no user accounts, so this is a fixed account
 * (DEFAULT_USER_ID), not a per-session one. It shows that user's e-mail and
 * contributions, so only the owner may open it, and this is
 * where the owner logs in: anyone else is asked for the token (401).
 */
class CuentaController extends BaseController
{
    private function usuario(): ?array
    {
        return (new UsuarioRepository($this->db()))->find(self::DEFAULT_USER_ID);
    }

    public function index(array $params = []): string
    {
        return $this->pedirPropietario(fn() => $this->conUsuario('genealogy/cuenta/index'));
    }

    public function colecciones(array $params = []): string
    {
        return $this->pedirPropietario(fn() => view('genealogy/cuenta/colecciones', [
            'colecciones' => (new ColeccionRepository($this->db(), $this->privacidad()))->deUsuario(self::DEFAULT_USER_ID),
        ]));
    }

    public function aportes(array $params = []): string
    {
        return $this->pedirPropietario(fn() => view('genealogy/cuenta/aportes', [
            'personas' => (new PersonaRepository($this->db(), $this->privacidad()))->aportadasPor(self::DEFAULT_USER_ID),
            'registros' => (new RegistroRepository($this->db(), $this->privacidad()))->aportadosPor(self::DEFAULT_USER_ID),
        ]));
    }

    /**
     * /0/3/ — the pedidos de retiro and what was decided about each one. The owner decides here:
     * hide the page while reviewing it, withdraw it, or reject the pedido (the page shows again
     * unless another pedido keeps it hidden). The form posts to this same URL.
     */
    public function retiros(array $params = []): string
    {
        $denegado = $this->exigirMetodo('GET', 'HEAD', 'POST') ?? $this->exigirPropietario(ocultar: false);
        if ($denegado !== null) {
            return $denegado;
        }

        $repo = new RetiroRepository($this->db());
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return view('genealogy/cuenta/retiros', ['pedidos' => $repo->todos()]);
        }
        if (!$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $numero = $this->postString('pedido');
        $decision = $this->postString('decision');
        $nota = trim($this->postString('nota'));
        if (!ctype_digit($numero) || !isset(RetiroRepository::DECISIONES[$decision]) || mb_strlen($nota) > 2000) {
            return $this->handleBadRequest('Pedido o decisión inválidos');
        }
        if (!$repo->decidir((int)$numero, $decision, $nota === '' ? null : $nota)) {
            return $this->handleNotFound('No existe ese pedido');
        }

        log_info('pedido de retiro decidido', ['pedido' => (int)$numero, 'decision' => $decision]);
        $this->flash("Pedido n.º {$numero}: " . RetiroRepository::DECISIONES[$decision] . '.');

        return $this->redirect(cuenta(3));
    }

    /** A page that shows the account's user, or a 404 when that user doesn't exist. */
    private function conUsuario(string $view): string
    {
        $usuario = $this->usuario();
        if ($usuario === null) {
            return $this->handleNotFound($this->sinUsuario());
        }

        return view($view, ['usuario' => $usuario]);
    }
}
