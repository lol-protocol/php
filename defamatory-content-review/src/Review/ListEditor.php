<?php

namespace DefamatoryContentReview\Review;

/**
 * Añade o quita una palabra de una lista de una línea de config/chat-topics/
 * (`'everyday' => [...]`, `'legit' => [...]`). Colaborador de ConfigEditor.
 *
 * La clave va tal cual entre comillas en el archivo y dentro de una expresión
 * regular, así que tiene que ser un nombre (letras, dígitos y guion bajo):
 * cualquier otra cosa es null, como si la lista no estuviera.
 */
final class ListEditor
{
    public static function edit(string $text, string $key, string $word, bool $add): ?string
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $key) !== 1) {
            return null;
        }
        $quoted = ConfigEditor::quote($word);
        if (!preg_match("/^(\\s*)'" . preg_quote($key, '/') . "' => \\[(.*)\\],$/mu", $text, $m, PREG_OFFSET_CAPTURE)) {
            return $add ? preg_replace("/^(\\s*)'words' => \\[$/mu", "\$1'{$key}' => [{$quoted}],\n\$0", $text, 1) : null;
        }
        $items = $m[2][0] === '' ? [] : array_map('trim', explode(',', $m[2][0]));
        if ($add === in_array($quoted, $items, true)) {
            return $add ? $text : null;
        }
        $items = $add ? [...$items, $quoted] : array_values(array_diff($items, [$quoted]));

        return substr_replace($text, "{$m[1][0]}'{$key}' => [" . implode(', ', $items) . '],', $m[0][1], strlen($m[0][0]));
    }
}
