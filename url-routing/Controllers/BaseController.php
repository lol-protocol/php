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

    /** Rows per page of the listing/search page (see listadoDeBusqueda()). */
    protected const POR_PAGINA = 50;

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
            // The owner browsing public pages is activity too: only private pages used to count, and
            // the session could run out an hour after the last one while the owner kept reading.
            AccessPolicy::renovar();
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
     * What renderFound() takes as $movida for an entity that can be merged into another: an id that was
     * merged away ($aliasDe gives the id it became, null if it is not an alias) leads to the same page of
     * the one that stays. $accion is that page's action code, null for the entity's own page.
     *
     * @param callable(int): ?int $aliasDe
     * @return callable(int): ?string
     */
    protected static function movida(string $entidad, callable $aliasDe, ?int $accion): callable
    {
        return static function (int $id) use ($entidad, $aliasDe, $accion): ?string {
            $destino = $aliasDe($id);
            if ($destino === null) {
                return null;
            }

            return $accion === null ? enlace($entidad, $destino) : accion($entidad, $destino, $accion);
        };
    }

    /**
     * The standard read action: validate the route id (400), load the
     * resource with $find (404 when missing) and render $view with it under
     * $key, plus whatever $extra returns for that id. When the id is missing
     * but $movida gives the URL it moved to, that's a 301 there instead.
     *
     * @param callable(int): (array<string, mixed>|null) $find
     * @param (callable(int, array<string, mixed>): array<string, mixed>)|null $extra
     * @param (callable(int): ?string)|null $movida
     * @param array<string, mixed> $params
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
     * @param list<string> $ruta
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

        $ancho = self::entero($digitos, 2);
        $numero = self::entero($pagina, 6);
        if ($ancho === null || $texto === null || !mb_check_encoding($texto, 'UTF-8') || $numero === null || $numero < 1) {
            return null;
        }

        return [$ancho, mb_substr($texto, 0, 255), $numero];
    }

    /**
     * $valor as an int when it is nothing but 1 to $maxDigitos digits, else null: the one check behind the
     * digit width (2 digits at most) and the page number (6) of a search, in its path and in its form.
     */
    private static function entero(mixed $valor, int $maxDigitos): ?int
    {
        return is_string($valor) && ctype_digit($valor) && strlen($valor) <= $maxDigitos ? (int)$valor : null;
    }

    /**
     * The search box is a plain GET form, so it can only send /?t=&q=&p=:
     * that becomes a 301 to its /b/ URL. Null when there is no t; a 400 when
     * t isn't a digit width.
     */
    protected function busquedaDeFormulario(): ?string
    {
        if (!isset($_GET['t'])) {
            return null;
        }
        $t = self::entero($_GET['t'], 2);
        if ($t === null) {
            return $this->handleBadRequest('Tipo de listado inválido');
        }

        $q = is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 255) : '';
        $p = max(1, self::entero($_GET['p'] ?? null, 6) ?? 1);
        if (!mb_check_encoding($q, 'UTF-8')) {
            return $this->handleBadRequest('Texto inválido');
        }

        return $this->redirect(busqueda($t, $q, $p), 301);
    }

    /** A 301 to the canonical spelling of this search, when the request used another one. */
    protected function busquedaNoCanonica(int $digitos, string $texto, int $pagina): ?string
    {
        $canonica = busqueda($digitos, $texto, $pagina);
        $pedida = (string)strtok($_SERVER['REQUEST_URI'] ?? '/', '?');

        return $canonica === $pedida ? null : $this->redirect($canonica, 301);
    }

    /**
     * Whether the request asks for the fragment of a listing (its list and its navigation, with no page around
     * them) instead of the page: the header `X-Fragmento: 1`, which the script at /b.js sends to load the next
     * page without leaving this one. The URL is the same either way, so there is no second shape to learn or to
     * link; a request without the header, such as a crawler's or a browser without JavaScript, gets the page.
     */
    protected static function pideFragmento(): bool
    {
        return ($_SERVER['HTTP_X_FRAGMENTO'] ?? '') === '1';
    }

    /**
     * The listing/search page /b/{digitos}/[{texto}/][{pagina}/] of either site: a 404 when the path
     * isn't one, a 301 to the canonical spelling when it was written another way, a 400 when the digit
     * width names no type (or one of $noListables, which have a width but are never listed), and
     * otherwise the rows $buscar returns, rendered with $vista.
     *
     * $buscar receives the type, the text, how many rows to fetch and from which one. It is asked for
     * one row more than a page: having it tells whether a next page exists without a COUNT(*) (hayMas).
     * It returns null for a type that has no listing, which is a 404. With `X-Fragmento: 1` (see pideFragmento())
     * the view renders only its list and navigation.
     *
     * @param array<string, mixed> $params the route's parameters ('ruta': the segments after "b")
     * @param callable(string, string, int, int): (list<array<string, mixed>>|null) $buscar [tipo, texto, limite, desplazamiento] => rows
     * @param list<string> $noListables
     */
    protected function listadoDeBusqueda(array $params, string $vista, callable $buscar, array $noListables = []): string
    {
        $pedida = $this->busquedaPedida($params['ruta'] ?? []);
        if ($pedida === null) {
            return $this->handleNotFound();
        }
        [$digitos, $query, $pagina] = $pedida;
        $redirigir = $this->busquedaNoCanonica($digitos, $query, $pagina);
        if ($redirigir !== null) {
            return $redirigir;
        }

        $tipo = ServiceLocator::getInstance()->getRouter()->typeForLength($digitos);
        if ($tipo === null || in_array($tipo, $noListables, true)) {
            return $this->handleBadRequest('Tipo de listado inválido');
        }

        $resultados = $buscar($tipo, $query, self::POR_PAGINA + 1, ($pagina - 1) * self::POR_PAGINA);
        if ($resultados === null) {
            return $this->handleNotFound('Este tipo no tiene listado');
        }

        // The same URL answers two ways, so the answer depends on a request header: a cache must know it.
        header('Vary: X-Fragmento', false);

        return view($vista, [
            'fragmento' => self::pideFragmento(),
            'tipo' => $tipo,
            'digitos' => $digitos,
            'query' => $query,
            'pagina' => $pagina,
            'hayMas' => count($resultados) > self::POR_PAGINA,
            'resultados' => array_slice($resultados, 0, self::POR_PAGINA),
        ]);
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

    /** Null when this request is a POST with a valid CSRF token; otherwise the 405 or 403 that refuses it. */
    protected function exigirPost(): ?string
    {
        return $this->exigirMetodo('POST') ?? ($this->validateCsrfToken() ? null : $this->handleForbidden());
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
