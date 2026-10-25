<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\RetiroRepository;
use App\Support\Config;
use App\Support\FiltroPedidos;
use App\Support\RateLimiter;
use App\Support\ServiceLocator;

/**
 * /r/{id}/ — anyone, with an account or without one, can ask for a page to be withdrawn: the
 * person it talks about, a relative of a deceased one, or whoever thinks it breaks the terms of use
 * (see TERMINOS-DE-USO.md at the root of the repository). The form posts to this same URL. The
 * pedido waits for the owner in /0/3/: receiving it hides nothing by itself, or anyone could take
 * any page down.
 */
class RetiroController extends BaseController
{
    private const MAX_DETALLE = 2000;
    private const MAX_CONTACTO = 254;
    /** A pedido a visitor may send per hour (RETIRO_MAX_POR_HORA). One hour is the longest window RateLimiter remembers. */
    private const MAX_POR_HORA = 3;
    /** Pedidos waiting on one page before it stops taking more (RETIRO_MAX_PENDIENTES). */
    private const MAX_PENDIENTES = 10;
    /** The form's decoy field: invisible to a person, filled by a bot that fills every field. */
    private const CAMPO_TRAMPA = 'sitio_web';

    public function pedir(array $params = []): string
    {
        $denegado = $this->exigirMetodo('GET', 'HEAD', 'POST');
        if ($denegado !== null) {
            return $denegado;
        }

        $id = $params['ruta'][0] ?? '';
        $tipo = ctype_digit($id) ? ServiceLocator::getInstance()->getRouter()->typeForLength(strlen($id)) : null;
        $repo = new RetiroRepository($this->db());
        if ($tipo === null || !$repo->existe($tipo, (int)$id)) {
            return $this->handleNotFound();
        }

        $datos = ['tipo' => $tipo, 'id' => $id, 'motivos' => RetiroRepository::MOTIVOS, 'errores' => [], 'valores' => []];
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return view('genealogy/retiro/pedir', $datos);
        }
        if (!$this->validateCsrfToken()) {
            return $this->handleForbidden();
        }

        if ($this->postString(self::CAMPO_TRAMPA) !== '') {
            // A bot: the same answer a person gets, without storing anything or saying why.
            log_info('pedido de retiro descartado: campo trampa', ['tipo' => $tipo, 'id' => (int)$id]);
            $this->flash('Recibimos tu pedido de retiro. Lo revisa una persona.');

            return $this->redirect(enlace($tipo, $id));
        }

        $valores = [
            'motivo' => $this->postString('motivo'),
            'detalle' => trim($this->postString('detalle')),
            'contacto' => trim($this->postString('contacto')),
        ];
        $errores = self::errores($valores);
        if ($errores !== []) {
            http_response_code(422);
            return view('genealogy/retiro/pedir', ['errores' => $errores, 'valores' => $valores] + $datos);
        }

        $limitador = RateLimiter::getInstance();
        if (!$limitador->isAllowed('retiro:' . $limitador->getClientIp(), Config::getInt('RETIRO_MAX_POR_HORA', self::MAX_POR_HORA), 3600)) {
            return $this->demasiados('Enviaste varios pedidos seguidos. Vuelve a intentarlo más tarde.', $limitador->retryAfter());
        }
        if ($repo->pendientesDe($tipo, (int)$id) >= Config::getInt('RETIRO_MAX_PENDIENTES', self::MAX_PENDIENTES)) {
            return $this->demasiados('Esta página ya tiene varios pedidos de retiro esperando a que una persona los revise.', 3600);
        }

        $numero = $repo->crear($tipo, (int)$id, $valores['motivo'], $valores['detalle'], $valores['contacto'], FiltroPedidos::marcas($valores['detalle']));
        // The number and the page only: never who asked nor what they wrote.
        log_info('pedido de retiro recibido', ['pedido' => $numero, 'tipo' => $tipo, 'id' => (int)$id]);
        $this->flash("Recibimos tu pedido de retiro n.º {$numero}. Lo revisa una persona y te responde a {$valores['contacto']}.");

        return $this->redirect(enlace($tipo, $id));
    }

    /** A 429 with the reason in a sentence and when to try again, for a visitor who sent too many pedidos. */
    private function demasiados(string $mensaje, int $segundos): string
    {
        http_response_code(429);
        header('Retry-After: ' . max(1, $segundos));

        return '<h1>429 - Demasiados pedidos</h1><p>' . htmlspecialchars($mensaje) . '</p>';
    }

    /** @param array{motivo: string, detalle: string, contacto: string} $valores @return array<string, string> field => message */
    private static function errores(array $valores): array
    {
        $errores = [];
        if (!isset(RetiroRepository::MOTIVOS[$valores['motivo']])) {
            $errores['motivo'] = 'Elige un motivo.';
        }
        if ($valores['detalle'] === '' || mb_strlen($valores['detalle']) > self::MAX_DETALLE || !mb_check_encoding($valores['detalle'], 'UTF-8')) {
            $errores['detalle'] = 'Cuenta qué hay que retirar y por qué, en hasta ' . self::MAX_DETALLE . ' caracteres.';
        }
        if (strlen($valores['contacto']) > self::MAX_CONTACTO || filter_var($valores['contacto'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['contacto'] = 'Escribe un correo donde podamos responderte.';
        }

        return $errores;
    }
}
