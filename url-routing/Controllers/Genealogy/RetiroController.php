<?php

declare(strict_types=1);

namespace App\Controllers\Genealogy;

use App\Controllers\BaseController;
use App\Repositories\Genealogy\RetiroRepository;
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

        $numero = $repo->crear($tipo, (int)$id, $valores['motivo'], $valores['detalle'], $valores['contacto']);
        // The number and the page only: never who asked nor what they wrote.
        log_info('pedido de retiro recibido', ['pedido' => $numero, 'tipo' => $tipo, 'id' => (int)$id]);
        $this->flash("Recibimos tu pedido de retiro n.º {$numero}. Lo revisa una persona y te responde a {$valores['contacto']}.");

        return $this->redirect(enlace($tipo, $id));
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
