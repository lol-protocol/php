<?php

namespace App\Controllers;

class BaseController
{
    protected function requireAuth()
    {
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            return false;
        }
        return true;
    }

    protected function getCurrentUserId()
    {
        return $_SESSION['user_id'] ?? null;
    }

    protected function validateResourceOwnership($resourceOwnerId)
    {
        $userId = $this->getCurrentUserId();
        if ($userId === null || $userId !== $resourceOwnerId) {
            http_response_code(403);
            return false;
        }
        return true;
    }

    protected function validateId($id)
    {
        if (!isset($id) || !ctype_digit($id)) {
            http_response_code(400);
            return null;
        }
        return $id;
    }

    protected function handleUnauthorized($message = 'Unauthorized')
    {
        http_response_code(401);
        return '<h1>401 - ' . htmlspecialchars($message) . '</h1>';
    }

    protected function handleForbidden($message = 'Forbidden')
    {
        http_response_code(403);
        return '<h1>403 - ' . htmlspecialchars($message) . '</h1>';
    }

    protected function handleBadRequest($message = 'Bad Request')
    {
        http_response_code(400);
        return '<h1>400 - ' . htmlspecialchars($message) . '</h1>';
    }
}
