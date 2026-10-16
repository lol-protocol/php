<?php

declare(strict_types=1);

namespace App\Support;

/** One-shot message stored in the session: set after a redirect, read and cleared by the next page render. */
final class Flash
{
    public function set(string $mensaje): void
    {
        $_SESSION['flash'] = $mensaje;
    }

    public function consumir(): ?string
    {
        $mensaje = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $mensaje;
    }
}
