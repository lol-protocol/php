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
     * @param string $title plain text: it is escaped here
     * @param string $detailHtml an optional paragraph under the title, HTML already escaped by the caller (a link may be in it)
     */
    public static function body(int $status, string $title, string $detailHtml = ''): string
    {
        return "<h1>{$status} - " . htmlspecialchars($title) . '</h1>' . ($detailHtml === '' ? '' : "\n<p>{$detailHtml}</p>");
    }
}
