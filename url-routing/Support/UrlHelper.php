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

    /**
     * @param list<string> $codes
     */
    public function enlaceLugar(array $codes): string
    {
        if (empty($codes) || count($codes) > 3) {
            throw new \InvalidArgumentException('Un lugar tiene entre 1 y 3 codigos');
        }

        // El primero tiene que ser el pais (2 letras) y el resto, de 1 a 3
        // letras: es lo que PlaceMatchStrategy exige para reconocer esta URL
        // al reves. ctype_alpha solo no lo garantiza (aceptaria un pais de
        // 3 letras, por ejemplo), y generar un enlace que no se puede
        // resolver es peor que rechazarlo aqui.
        foreach ($codes as $i => $code) {
            $largoValido = $i === 0 ? strlen($code) === 2 : strlen($code) >= 1 && strlen($code) <= 3;
            if (!ctype_alpha($code) || !$largoValido) {
                throw new \InvalidArgumentException("Codigo de lugar invalido: {$code}");
            }
        }

        return '/' . implode('/', array_map('strtolower', $codes)) . '/';
    }

    /**
     * @param list<string> $codes
     */
    public function accionLugar(array $codes, int|string $codigo): string
    {
        return rtrim($this->enlaceLugar($codes), '/') . '/' . $codigo . '/';
    }

    /**
     * /b/{digitos}/[{texto}/][{pagina}/] — the canonical search URL. The text
     * is lowercased (search ignores case) and percent-encoded with lowercase
     * hex and "." as %2e, so it is already what Router::canonica() wants and
     * the two can never bounce a request between them. A text of only digits
     * always carries its page (/b/8/2026/1/): alone it would read as a page.
     */
    public function busqueda(int $digitos, string $texto = '', int $pagina = 1): string
    {
        $url = "/b/{$digitos}/";
        $texto = trim((string)preg_replace('/\s+/u', ' ', mb_strtolower($texto)));
        if ($texto !== '') {
            $url .= str_replace('.', '%2e', strtolower(rawurlencode($texto))) . '/';
        }

        return $pagina > 1 || ($texto !== '' && ctype_digit($texto)) ? "{$url}{$pagina}/" : $url;
    }

    public function cuenta(int|string|null $codigo = null): string
    {
        $base = '/' . Router::ACCOUNT_SEGMENT . '/';
        return $codigo === null ? $base : "{$base}{$codigo}/";
    }

    public function esc(mixed $text): string
    {
        return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    }
}
