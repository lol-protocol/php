<?php

namespace Marcalibros;

/**
 * Limpia el texto opcional de un marcalibro.
 *
 * Quita caracteres de control y de formato (saltos de línea, ceros de ancho,
 * marcas que invierten el sentido del texto), junta los espacios repetidos y
 * recorta al largo máximo. No escapa HTML: quien muestre el texto debe
 * escaparlo al imprimirlo, como con cualquier dato de un usuario.
 */
final class Label
{
    public const MAX_LENGTH = 40;

    public static function clean(?string $raw, int $maxLength = self::MAX_LENGTH): ?string
    {
        if ($raw === null) {
            return null;
        }
        if (!mb_check_encoding($raw, 'UTF-8')) {
            throw new BookmarkRejectedException(RejectionReason::InvalidLabel);
        }

        // Formato (ceros de ancho, inversión de sentido): se eliminan sin dejar hueco.
        $text = preg_replace('/\p{Cf}+/u', '', $raw);
        // Control (incluye saltos de línea y tabulaciones) y separadores de línea o párrafo: espacio.
        $text = $text === null ? null : preg_replace('/[\p{Cc}\p{Zl}\p{Zp}]+/u', ' ', $text);
        $text = $text === null ? null : preg_replace('/\s+/u', ' ', $text);
        if ($text === null) {
            throw new BookmarkRejectedException(RejectionReason::InvalidLabel);
        }

        $text = trim(mb_substr(trim($text), 0, max(0, $maxLength)));

        return $text === '' ? null : $text;
    }
}
