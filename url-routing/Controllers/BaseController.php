<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\Database;
use App\Support\ServiceLocator;

class BaseController
{
    protected function requireAuth(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            return false;
        }
        return true;
    }

    protected function validateCsrfToken(): bool
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($token === null) {
            http_response_code(403);
            return false;
        }

        return ServiceLocator::getInstance()->getSessionManager()->validateCsrfToken($token);
    }

    protected function getCsrfToken(): string
    {
        return ServiceLocator::getInstance()->getSessionManager()->setCsrfToken();
    }

    protected function getCurrentUserId(): int|null
    {
        return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    }

    /** Whether the logged-in user owns a resource whose owner id came from the DB. */
    protected function isOwner(int|string|null $resourceOwnerId): bool
    {
        $userId = $this->getCurrentUserId();
        return $userId !== null && $resourceOwnerId !== null && $userId === (int)$resourceOwnerId;
    }

    protected function validateResourceOwnership(int|string|null $resourceOwnerId): bool
    {
        if (!$this->isOwner($resourceOwnerId)) {
            http_response_code(403);
            return false;
        }
        return true;
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

    protected function handleUnauthorized(string $message = 'Unauthorized'): string
    {
        http_response_code(401);
        return '<h1>401 - ' . htmlspecialchars($message) . '</h1>';
    }

    protected function handleForbidden(string $message = 'Forbidden'): string
    {
        http_response_code(403);
        return '<h1>403 - ' . htmlspecialchars($message) . '</h1>';
    }

    protected function handleBadRequest(string $message = 'Bad Request'): string
    {
        http_response_code(400);
        return '<h1>400 - ' . htmlspecialchars($message) . '</h1>';
    }
}
