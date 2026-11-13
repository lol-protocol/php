<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\RetiroRepository;
use App\Repositories\Genealogy\SolicitudRepository;
use App\Support\Config;
use App\Support\ErrorPage;
use App\Support\RateLimiter;
use App\Support\ServiceLocator;

/**
 * /s/{id}/ — anyone, with an account or without one, can ask what the site holds about a persona, to correct it or to
 * erase it (the access, rectification and erasure rights; see CUMPLIMIENTO-LEY-21719.md at the root). The form posts to
 * this same URL. The solicitud waits for the owner (bin/solicitudes.php): receiving it gives, changes and erases
 * nothing, because who asks has not been checked yet.
 *
 * It answers 404 for what /r/{id}/ does: an id that is not a persona. A living persona's page shows only «Persona
 * viva», but the form is there, because she is exactly who asks.
 */
class SolicitudController extends BaseController
{
    private const MAX_DETALLE = 2000;
    private const MAX_CONTACTO = 254;
    /** A solicitud a visitor may send per hour (SOLICITUD_MAX_POR_HORA). One hour is RateLimiter::VENTANA_MAXIMA. */
    private const MAX_POR_HORA = 3;
    /** Solicitudes waiting about one persona before it stops taking more (SOLICITUD_MAX_PENDIENTES). */
    private const MAX_PENDIENTES = 5;
    /** The form's decoy field: invisible to a person, filled by a bot that fills every field. */
    private const CAMPO_TRAMPA = 'sitio_web';

    /**
     * @param array<string, mixed> $params
     */
    public function pedir(array $params = []): string
    {
        $denegado = $this->exigirMetodo('GET', 'HEAD', 'POST');
        if ($denegado !== null) {
            return $denegado;
        }

        $id = $params['ruta'][0] ?? '';
        $tipo = ctype_digit($id) ? ServiceLocator::getInstance()->getRouter()->typeForLength(strlen($id)) : null;
        if ($tipo !== 'persona' || !(new RetiroRepository($this->db()))->existe('persona', (int)$id)) {
            return $this->handleNotFound();
        }
        $repo = new SolicitudRepository($this->db());

        $datos = ['id' => $id, 'derechos' => SolicitudRepository::DERECHOS, 'errores' => [], 'valores' => []];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return view('genealogy/solicitud/pedir', $datos);
        }
        if (!$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        if ($this->postString(self::CAMPO_TRAMPA) !== '') {
            // A bot: the same answer a person gets, without storing anything or saying why.
            log_info('solicitud de datos descartada: campo trampa', ['id' => (int)$id]);
            $this->flash('Recibimos tu solicitud. La revisa una persona.');

            return $this->redirect(enlace($tipo, $id));
        }

        $valores = [
            'derecho' => $this->postString('derecho'),
            'detalle' => trim($this->postString('detalle')),
            'contacto' => trim($this->postString('contacto')),
        ];
        $errores = self::errores($valores);
        if ($errores !== []) {
            http_response_code(422);
            return view('genealogy/solicitud/pedir', ['errores' => $errores, 'valores' => $valores] + $datos);
        }

        $limitador = RateLimiter::getInstance();
        if (!$limitador->isAllowed('solicitud:' . $limitador->getClientIp(), Config::getInt('SOLICITUD_MAX_POR_HORA', self::MAX_POR_HORA), RateLimiter::VENTANA_MAXIMA)) {
            return $this->demasiados('Enviaste varias solicitudes seguidas. Vuelve a intentarlo más tarde.', $limitador->retryAfter());
        }
        if ($repo->pendientesDe((int)$id) >= Config::getInt('SOLICITUD_MAX_PENDIENTES', self::MAX_PENDIENTES)) {
            return $this->demasiados('Ya hay varias solicitudes sobre esta persona esperando a que una persona las revise.', 3600);
        }

        $numero = $repo->crear((int)$id, $valores['derecho'], $valores['detalle'], $valores['contacto']);
        // The number and the persona only: never who asked nor what they wrote.
        log_info('solicitud de datos recibida', ['solicitud' => $numero, 'id' => (int)$id]);
        $this->flash("Recibimos tu solicitud n.º {$numero}. La revisa una persona y te escribe a {$valores['contacto']}; antes de entregar o cambiar nada te pedirá un dato para comprobar que eres quien dice.");

        return $this->redirect(enlace($tipo, $id));
    }

    /** A 429 with the reason in a sentence and when to try again, for a visitor who sent too many solicitudes. */
    private function demasiados(string $mensaje, int $segundos): string
    {
        http_response_code(429);
        header('Retry-After: ' . max(1, $segundos));

        return ErrorPage::page(429, 'Demasiadas solicitudes', htmlspecialchars($mensaje));
    }

    /**
     * @param array{derecho: string, detalle: string, contacto: string} $valores
     * @return array<string, string> field => message
     */
    private static function errores(array $valores): array
    {
        $errores = [];
        if (!isset(SolicitudRepository::DERECHOS[$valores['derecho']])) {
            $errores['derecho'] = 'Elige qué quieres pedir.';
        }
        if ($valores['detalle'] === '' || mb_strlen($valores['detalle']) > self::MAX_DETALLE || !mb_check_encoding($valores['detalle'], 'UTF-8')) {
            $errores['detalle'] = 'Cuéntanos qué datos y de qué persona, en hasta ' . self::MAX_DETALLE . ' caracteres.';
        }
        if (strlen($valores['contacto']) > self::MAX_CONTACTO || filter_var($valores['contacto'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['contacto'] = 'Escribe un correo donde podamos responderte.';
        }

        return $errores;
    }
}
