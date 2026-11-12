<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The body of an error response (the status code is the caller's: http_response_code() next to it), built in
 * one place so every refusal of the site has the same shape and the same language. The hidden page of
 * AccessPolicy is a 404 byte for byte like a missing one, and that only holds if both come from here.
 */
final class ErrorPage
{
    public const NOT_FOUND = 'Página no encontrada';

    /**
     * The error as a whole page, with the header and footer of the site that answers (views/{sitio}/error.php),
     * noindex and with no canonical link (an error has no address of its own). Same arguments as body().
     *
     * It never fails: a page that cannot be drawn (a broken layout, a request that is not through public/index.php)
     * is body(), and the log says why, because an error page that throws would hide the very error it reports.
     * The status is set here too, before the layout runs: it reads it (no «pedir el retiro» link on an error).
     * Everything that answers a refusal of the site uses this one, so a hidden page and a missing one are the
     * same bytes (ResponseHeadersTest). The limiter's 429 in public/index.php is the exception: it answers an
     * abusive client, so it stays the bare body() and costs nothing.
     */
    public static function page(int $status, string $title, string $detailHtml = ''): string
    {
        http_response_code($status);
        try {
            $sitio = HostParser::sitio($_SERVER['HTTP_HOST'] ?? 'localhost');

            return Vista::dibujar(dirname(__DIR__) . "/views/{$sitio}/error.php", ['estado' => $status, 'titulo' => $title, 'detalleHtml' => $detailHtml]);
        } catch (\Throwable $e) {
            Logger::getInstance()->warning('Página de error sin diseño', ['estado' => $status, 'error' => ResumenDeError::de($e)]);

            return self::body($status, $title, $detailHtml);
        }
    }

    /**
     * @param string $title plain text: it is escaped here
     * @param string $detailHtml an optional paragraph under the title, HTML already escaped by the caller (a link may be in it)
     */
    public static function body(int $status, string $title, string $detailHtml = ''): string
    {
        return "<h1>{$status} - " . htmlspecialchars($title) . '</h1>' . ($detailHtml === '' ? '' : "\n<p>{$detailHtml}</p>");
    }
}
