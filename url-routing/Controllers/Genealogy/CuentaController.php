<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\ColeccionRepository;
use App\Repositories\Genealogy\EntregaRepository;
use App\Repositories\Genealogy\PersonaRepository;
use App\Repositories\Genealogy\RectificacionRepository;
use App\Repositories\Genealogy\RegistroRepository;
use App\Repositories\Genealogy\RetiroRepository;
use App\Repositories\Genealogy\SolicitudRepository;
use App\Repositories\UsuarioRepository;

/**
 * Account area (/0/) — there are no user accounts, so this is a fixed account
 * (DEFAULT_USER_ID), not a per-session one. It shows that user's e-mail and
 * contributions, so only the owner may open it, and this is
 * where the owner logs in: anyone else is asked for the token (401).
 */
class CuentaController extends BaseController
{
    /**
     * @return array<string, mixed>|null
     */
    private function usuario(): ?array
    {
        return (new UsuarioRepository($this->db()))->find(self::DEFAULT_USER_ID);
    }

    /**
     * @param array<string, mixed> $params
     */
    public function index(array $params = []): string
    {
        return $this->pedirPropietario(fn() => $this->conUsuario('genealogy/cuenta/index'));
    }

    /**
     * @param array<string, mixed> $params
     */
    public function colecciones(array $params = []): string
    {
        return $this->pedirPropietario(fn() => view('genealogy/cuenta/colecciones', [
            'colecciones' => (new ColeccionRepository($this->db(), $this->privacidad()))->deUsuario(self::DEFAULT_USER_ID),
        ]));
    }

    /**
     * @param array<string, mixed> $params
     */
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
     *
     * @param array<string, mixed> $params
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

    /**
     * /0/4/ — the solicitudes of access, correction or erasure of a persona's data that anyone made at /s/{id}/. The owner
     * reads them here, answers by e-mail (docs/SOLICITUDES.md: check who asks first) and closes each one as answered
     * or refused. Closing gives, changes and erases nothing: that is done with bin/persona.php. The forms post to this URL:
     * `accion` is «cerrar» (the default), «entrega» (makes a delivery with a key for the persona of a pending solicitud) or
     * «simular» (shows what correcting a field would do, and changes nothing: doing it stays in bin/persona.php).
     *
     * @param array<string, mixed> $params
     */
    public function solicitudes(array $params = []): string
    {
        $denegado = $this->exigirMetodo('GET', 'HEAD', 'POST') ?? $this->exigirPropietario(ocultar: false);
        if ($denegado !== null) {
            return $denegado;
        }

        $repo = new SolicitudRepository($this->db());
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return view('genealogy/cuenta/solicitudes', ['solicitudes' => $repo->todas()]);
        }
        if (!$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $accion = $this->postString('accion', 'cerrar');
        if ($accion === 'entrega' || $accion === 'simular') {
            return $this->accionSobreLaPersona($repo, $accion);
        }

        $numero = $this->postString('solicitud');
        $decision = $this->postString('decision');
        $nota = trim($this->postString('nota'));
        if (!ctype_digit($numero) || !isset(SolicitudRepository::CIERRES[$decision]) || mb_strlen($nota) > 2000) {
            return $this->handleBadRequest('Solicitud o decisión inválidas');
        }
        if (!$repo->cerrar((int)$numero, $decision, $nota === '' ? null : $nota)) {
            return $this->handleNotFound('No hay una solicitud pendiente con ese número');
        }

        // The number and the decision only: never who asked nor what they wrote.
        log_info('solicitud de datos cerrada', ['solicitud' => (int)$numero, 'decision' => $decision]);
        $this->flash("Solicitud n.º {$numero}: " . SolicitudRepository::CIERRES[$decision] . '.');

        return $this->redirect(cuenta(4));
    }

    /**
     * The two things the owner can do from the panel about the persona of a pending solicitud, after checking who asks.
     * The persona is the one the solicitud names, never one typed in the form. The result is the response itself and is
     * never stored (a delivery's link and key cannot be recovered), and the page is private and no-store like every page
     * of the owner. Applying a correction stays in bin/persona.php: here it is only shown.
     */
    private function accionSobreLaPersona(SolicitudRepository $repo, string $accion): string
    {
        $numero = $this->postString('solicitud');
        $solicitud = ctype_digit($numero) ? $repo->ver((int)$numero) : null;
        if ($solicitud === null || $solicitud['estado'] !== 'pendiente') {
            return $this->handleNotFound('No hay una solicitud pendiente con ese número');
        }
        $persona = (int)$solicitud['persona_id'];
        $datos = ['solicitud' => (int)$numero, 'persona' => $persona, 'accion' => $accion, 'error' => null];

        try {
            if ($accion === 'entrega') {
                $dias = $this->postString('dias', (string)EntregaRepository::VIGENCIA_DIAS);
                if (!ctype_digit($dias)) {
                    throw new \DomainException('La vigencia es un número de días, de 1 a 90.');
                }
                $entrega = (new EntregaRepository($this->db()))->crear($persona, (int)$dias);
                $base = rtrim((string)\App\Support\Config::get('SITE_URL_GENEALOGY', ''), '/');
                if ($base === '') {
                    $base = (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
                }
                // The number and the persona only: never the link nor the key.
                log_info('entrega con clave creada desde el panel', ['solicitud' => (int)$numero, 'entrega' => $entrega['id'], 'persona' => $persona]);
                $datos['entrega'] = $entrega + ['enlace' => "{$base}/e/{$entrega['token']}/"];
            } else {
                $suceso = $this->postString('suceso');
                if ($suceso !== '' && !ctype_digit($suceso)) {
                    throw new \DomainException('El suceso es un número.');
                }
                $campo = $this->postString('campo');
                $datos['campo'] = $campo;
                $datos['simulacion'] = (new RectificacionRepository($this->db()))->rectificar($persona, $campo, $this->postString('valor'), $suceso === '' ? null : (int)$suceso, null, false);
                $datos['frase'] = RectificacionRepository::loQueVeUnVisitante($datos['simulacion']);
            }
        } catch (\DomainException $e) {
            $datos['error'] = $e->getMessage();
            http_response_code(422);
        }

        return view('genealogy/cuenta/solicitud-resultado', $datos);
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
