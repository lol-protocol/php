<?php

namespace App\Support;

class UrlHelper
{
    private $router;

    public function __construct($router)
    {
        $this->router = $router;
    }

    /**
     * URL for a numeric-id resource. $id is zero-padded to $tipo's fixed
     * digit width before it's used — an unpadded id would silently resolve
     * as a different (shorter) type, so callers never hand-format this.
     */
    public function enlace($tipo, $id)
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

    /**
     * URL for a sub-action on a numeric-id resource (see enlace()).
     */
    public function accion($tipo, $id, $codigo)
    {
        return rtrim($this->enlace($tipo, $id), '/') . '/' . $codigo . '/';
    }

    /**
     * URL for a place, from its list of hierarchical text codes (pais/region/ciudad).
     * Mirrors the router's own shape check (1-3 alphabetic codes) so a bad
     * call fails here, at generation time, instead of producing a link that
     * 404s when someone clicks it.
     */
    public function enlaceLugar(array $codes)
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

    /**
     * URL for the account area, optionally a sub-section by its action code.
     */
    public function cuenta($codigo = null)
    {
        return $codigo === null ? '/0/' : "/0/{$codigo}/";
    }

    /**
     * Escape HTML output.
     */
    public function esc($text)
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
