<?php

declare(strict_types=1);

namespace App\Controllers;

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

    protected function getCurrentUserId(): int|null
    {
        return $_SESSION['user_id'] ?? null;
    }

    protected function validateResourceOwnership(int|string|null $resourceOwnerId): bool
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null || $userId !== $resourceOwnerId) {
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
