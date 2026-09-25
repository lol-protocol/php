<?php

declare(strict_types=1);

namespace App\Support;

class UrlHelper
{
    private Router $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    public function enlace(string $tipo, int|string $id): string
    {
        if (!ctype_digit((string) $id)) {
            throw new \InvalidArgumentException("Id no numerico para {$tipo}: {$id}");
        }

        $largo = $this->router->typeLength($tipo);

        if ($largo === null) {
            throw new \InvalidArgumentException("Tipo desconocido: {$tipo}");
        }

        if (strlen((string) $id) > $largo) {
            throw new \InvalidArgumentException("Id demasiado largo para {$tipo} ({$largo} digitos): {$id}");
        }

        return '/' . str_pad((string) $id, $largo, '0', STR_PAD_LEFT) . '/';
    }

    public function accion(string $tipo, int|string $id, int|string $codigo): string
    {
        return rtrim($this->enlace($tipo, $id), '/') . '/' . $codigo . '/';
    }

    public function enlaceLugar(array $codes): string
    {
        if (empty($codes) || count($codes) > 3) {
            throw new \InvalidArgumentException('Un lugar tiene entre 1 y 3 codigos');
        }

        foreach ($codes as $code) {
            if (!ctype_alpha($code)) {
                throw new \InvalidArgumentException("Codigo de lugar invalido: {$code}");
            }
        }

        return '/' . implode('/', array_map('strtolower', $codes)) . '/';
    }

    public function cuenta(int|string|null $codigo = null): string
    {
        return $codigo === null ? '/0/' : "/0/{$codigo}/";
    }

    public function esc(mixed $text): string
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}
