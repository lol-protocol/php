<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\EntregaRepository;
use App\Support\Config;
use App\Support\ErrorPage;
use App\Support\RateLimiter;

/**
 * /e/{token}/ — where a persona reads, with a key, what the site holds about her, including what it hides from a
 * visitor (see EntregaRepository). The owner makes the link and the key (bin/persona.php entrega) after checking who
 * asks, and sends them by two different means.
 *
 * A link that does not exist, has expired, has run out of views or has been locked by wrong keys answers the same 404 as a
 * link made up: nothing tells which one it was. The page is never cached and never tells where it was linked from (the
 * token is in the URL), and what it shows is not logged: only that a delivery was opened.
 */
class EntregaController extends BaseController
{
    /** Keys a visitor may try per hour, over every link (ENTREGA_MAX_POR_HORA). One hour is RateLimiter::VENTANA_MAXIMA. */
    private const MAX_POR_HORA = 10;

    /**
     * @param array<string, mixed> $params
     */
    public function ver(array $params = []): string
    {
        $denegado = $this->exigirMetodo('GET', 'HEAD', 'POST');
        if ($denegado !== null) {
            return $denegado;
        }

        $token = (string)($params['ruta'][0] ?? '');
        $repo = new EntregaRepository($this->db());
        if (strlen($token) !== 64 || !ctype_xdigit($token) || !$repo->vigente($token)) {
            return $this->handleNotFound();
        }
        header('Referrer-Policy: no-referrer');

        $datos = ['token' => $token, 'error' => ''];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return view('genealogy/entrega/clave', $datos);
        }
        if (!$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        $limitador = RateLimiter::getInstance();
        if (!$limitador->isAllowed('entrega:' . $limitador->getClientIp(), Config::getInt('ENTREGA_MAX_POR_HORA', self::MAX_POR_HORA), RateLimiter::VENTANA_MAXIMA)) {
            http_response_code(429);
            header('Retry-After: ' . max(1, $limitador->retryAfter()));

            return ErrorPage::page(429, 'Demasiados intentos', 'Probaste varias claves seguidas. Vuelve a intentarlo más tarde.');
        }

        $persona = $repo->abrir($token, $this->postString('clave'));
        if ($persona === null) {
            return $this->handleNotFound();
        }
        if ($persona === 'clave') {
            http_response_code(422);

            return view('genealogy/entrega/clave', ['error' => 'La clave no es correcta. Revísala (no importan las mayúsculas ni los guiones). Después de varios intentos, el enlace deja de servir.'] + $datos);
        }

        $contenido = $repo->contenido($persona);
        if ($contenido === null) {
            return $this->handleNotFound();
        }
        // That a delivery was opened, and for which persona: never what it showed.
        log_info('entrega abierta', ['id' => $persona]);

        return view('genealogy/entrega/datos', ['contenido' => $contenido]);
    }
}
