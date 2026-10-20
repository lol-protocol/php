<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\AccessPolicy;
use App\Support\Database;
use App\Support\Flash;
use App\Support\Privacidad;
use App\Support\ServiceLocator;

class BaseController
{
    /**
     * This app has no user accounts: it's a single-tenant addon, not a
     * multi-user product, so account pages act on a fixed user
     * instead of a session identity. Those pages hold that user's
     * personal data, so they are owner-only (see AccessPolicy).
     */
    protected const DEFAULT_USER_ID = 1;

    /** Whether this request is the owner's (see AccessPolicy). */
    protected function esPropietario(): bool
    {
        return AccessPolicy::esPropietario();
    }

    /**
     * The three ways a controller can gate an action to the owner, all built on this one:
     * `soloPropietario()` wraps it with $ocultar = true (404, hides the page) and
     * `pedirPropietario()` with $ocultar = false (401, asks for the token). Call this one
     * directly only when the action needs to branch on more than "run or refuse" (see
     * PersonaController::viva() and CartController, which also check the HTTP method or CSRF).
     *
     * Null when the owner is asking; otherwise the response that refuses:
     * a 404 that hides the page ($ocultar, for private data whose existence
     * must not be confirmed) or a 401 that asks the browser for the token.
     */
    protected function exigirPropietario(bool $ocultar = true): ?string
    {
        if (!AccessPolicy::esPropietario()) {
            return $ocultar ? AccessPolicy::ocultar() : AccessPolicy::pedirCredenciales();
        }

        AccessPolicy::recordar();
        AccessPolicy::marcarPrivada();
        return null;
    }

    /**
     * What this request may see about living people: everything for the owner,
     * a visitor's view otherwise. The owner's page differs from the visitor's
     * at the same URL, so no shared cache may keep it.
     */
    protected function privacidad(): Privacidad
    {
        $privacidad = Privacidad::paraSolicitud();
        if (!$privacidad->ocultaVivas()) {
            AccessPolicy::marcarPrivada();
        }

        return $privacidad;
    }

    /**
     * The 404 text for the account area when the fixed user does not exist. Only the owner reaches
     * it (she has already proved who she is), so it can say what is missing: on a new database
     * /0/ would otherwise answer 404 to the right credentials and look like a wrong token.
     */
    protected function sinUsuario(): string
    {
        return 'No existe el usuario ' . self::DEFAULT_USER_ID . ' de esta base de datos (ver docs/DESPLIEGUE.md, paso 4)';
    }

    /** Runs $accion only for the owner; anyone else gets a 404, as if the page did not exist. */
    protected function soloPropietario(callable $accion): string
    {
        return $this->exigirPropietario() ?? (string)$accion();
    }

    /** Runs $accion only for the owner; anyone else is asked to log in (401). */
    protected function pedirPropietario(callable $accion): string
    {
        return $this->exigirPropietario(ocultar: false) ?? (string)$accion();
    }

    protected function validateCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        // is_string: "csrf_token[]=x" arrives as an array and must be a 403, not a TypeError.
        if (is_string($token) && ServiceLocator::getInstance()->getSessionManager()->validateCsrfToken($token)) {
            return true;
        }

        http_response_code(403);
        return false;
    }

    protected function getCsrfToken(): string
    {
        return ServiceLocator::getInstance()->getSessionManager()->setCsrfToken();
    }

    protected function validateId(mixed $id): string|null
    {
        if (!isset($id) || !ctype_digit((string)$id)) {
            http_response_code(400);
            return null;
        }
        return (string)$id;
    }

    protected function db(): Database
    {
        return ServiceLocator::getInstance()->getDatabase();
    }

    /**
     * The standard read action: validate the route id (400), load the
     * resource with $find (404 when missing) and render $view with it under
     * $key, plus whatever $extra returns for that id. When the id is missing
     * but $movida gives the URL it moved to, that's a 301 there instead.
     *
     * @param callable(int): ?array $find
     * @param (callable(int, array): array)|null $extra
     * @param (callable(int): ?string)|null $movida
     */
    protected function renderFound(
        array $params,
        callable $find,
        string $view,
        string $key,
        ?callable $extra = null,
        ?callable $movida = null
    ): string {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Identificador inválido');
        }

        $row = $find((int)$id);
        if ($row === null) {
            $destino = $movida ? $movida((int)$id) : null;
            return $destino === null ? $this->handleNotFound() : $this->redirect($destino, 301);
        }

        return view($view, [$key => $row] + ($extra ? $extra((int)$id, $row) : []));
    }

    protected function handleNotFound(string $message = 'Página no encontrada'): string
    {
        http_response_code(404);
        return '<h1>404 - ' . htmlspecialchars($message) . '</h1>';
    }

    protected function handleBadRequest(string $message = 'Bad Request'): string
    {
        http_response_code(400);
        return '<h1>400 - ' . htmlspecialchars($message) . '</h1>';
    }

    /** A POST field as a string; '' (or $default) when missing or sent as an array ("sku[]=x"). */
    protected function postString(string $name, string $default = ''): string
    {
        $value = $_POST[$name] ?? $default;
        return is_string($value) ? $value : $default;
    }

    /**
     * A search, from /b/{digitos}/[{texto}/][{pagina}/] (params['ruta'], the
     * segments after "b"): [digitos, texto, pagina], or null when the path
     * isn't one. Two segments are a page when the second is all digits, a
     * text otherwise; three are always text and page (see busqueda()).
     *
     * @return array{int, string, int}|null
     */
    protected function busquedaPedida(array $ruta): ?array
    {
        $digitos = $ruta[0] ?? '';
        [$texto, $pagina] = match (count($ruta)) {
            1 => ['', '1'],
            2 => ctype_digit($ruta[1]) ? ['', $ruta[1]] : [rawurldecode($ruta[1]), '1'],
            3 => [rawurldecode($ruta[1]), $ruta[2]],
            default => [null, ''],
        };

        if (!ctype_digit($digitos) || strlen($digitos) > 2 || $texto === null || !mb_check_encoding($texto, 'UTF-8')
            || !ctype_digit($pagina) || strlen($pagina) > 6 || (int)$pagina < 1) {
            return null;
        }

        return [(int)$digitos, mb_substr($texto, 0, 255), (int)$pagina];
    }

    /**
     * The search box is a plain GET form, so it can only send /?t=&q=&p=:
     * that becomes a 301 to its /b/ URL. Null when there is no t; a 400 when
     * t isn't a digit width.
     */
    protected function busquedaDeFormulario(): ?string
    {
        $t = $_GET['t'] ?? null;
        if ($t === null) {
            return null;
        }
        if (!is_string($t) || !ctype_digit($t) || strlen($t) > 2) {
            return $this->handleBadRequest('Tipo de listado inválido');
        }

        $q = is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 255) : '';
        $p = is_string($_GET['p'] ?? null) && ctype_digit($_GET['p']) && strlen($_GET['p']) <= 6 ? max(1, (int)$_GET['p']) : 1;
        if (!mb_check_encoding($q, 'UTF-8')) {
            return $this->handleBadRequest('Texto inválido');
        }

        return $this->redirect(busqueda((int)$t, $q, $p), 301);
    }

    /** A 301 to the canonical spelling of this search, when the request used another one. */
    protected function busquedaNoCanonica(int $digitos, string $texto, int $pagina): ?string
    {
        $canonica = busqueda($digitos, $texto, $pagina);
        $pedida = (string)strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

        return $canonica === $pedida ? null : $this->redirect($canonica, 301);
    }

    /** A one-shot message shown on the next page the user lands on (e.g. after a redirect). */
    protected function flash(string $mensaje): void
    {
        (new Flash())->set($mensaje);
    }

    /** Null when this request's method is one of $permitidos; otherwise a 405 that names them (Allow). */
    protected function exigirMetodo(string ...$permitidos): ?string
    {
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $permitidos, true)) {
            return null;
        }

        header('Allow: ' . implode(', ', $permitidos));
        http_response_code(405);
        return '<h1>405 - Método no permitido</h1>';
    }

    protected function handleForbidden(string $message = 'Forbidden'): string
    {
        http_response_code(403);
        return '<h1>403 - ' . htmlspecialchars($message) . '</h1>';
    }

    /** 303 (the default) so a browser re-fetches the target with GET instead of replaying the POST; 301 for a URL that moved for good. */
    protected function redirect(string $location, int $status = 303): string
    {
        header("Location: {$location}", true, $status);
        http_response_code($status);
        return '';
    }
}
