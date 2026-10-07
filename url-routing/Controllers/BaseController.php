<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Database;
use App\Support\ServiceLocator;

class BaseController
{
    /**
     * This app has no login: it's a single-tenant addon, not a
     * multi-user product, so account pages act on a fixed user
     * instead of a session identity.
     */
    protected const DEFAULT_USER_ID = 1;

    protected function validateCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($token !== null && ServiceLocator::getInstance()->getSessionManager()->validateCsrfToken($token)) {
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
     * $key, plus whatever $extra returns for that id.
     *
     * @param callable(int): ?array $find
     * @param (callable(int, array): array)|null $extra
     */
    protected function renderFound(
        array $params,
        callable $find,
        string $view,
        string $key,
        ?callable $extra = null
    ): string {
        $id = $this->validateId($params['id'] ?? null);
        if ($id === null) {
            return $this->handleBadRequest('Identificador inválido');
        }

        $row = $find((int)$id);
        if ($row === null) {
            return $this->handleNotFound();
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

    protected function handleForbidden(string $message = 'Forbidden'): string
    {
        http_response_code(403);
        return '<h1>403 - ' . htmlspecialchars($message) . '</h1>';
    }

    /** 303 so a browser re-fetches the target with GET instead of replaying the POST. */
    protected function redirect(string $location): string
    {
        header("Location: {$location}", true, 303);
        return '';
    }
}
