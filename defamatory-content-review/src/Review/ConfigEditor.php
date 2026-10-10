<?php

namespace DefamatoryContentReview\Review;

/** Cambios de texto sobre un archivo de config/languages/ o config/chat-topics/ sin reescribirlo: se conservan
 * comentarios y orden, y el diff es sólo la línea tocada. Una entrada va de `['word' => '…'` al corchete que la
 * cierra (puede ocupar varias líneas). Null si la entrada no está: quien llama lo informa en vez de adivinar. */
final class ConfigEditor
{
    public static function setSeverity(string $text, string $word, string $severity): ?string
    {
        $change = fn(string $entry): string => preg_replace("/'severity' => '\\w+'/", "'severity' => '{$severity}'", $entry, 1) ?? $entry;

        return self::editEntry($text, $word, $change);
    }

    /** Pone (`$on`) o quita `'flag' => true` en la entrada; al ponerla va antes del corchete que la cierra. */
    public static function setFlag(string $text, string $word, string $flag, bool $on = true): ?string
    {
        return self::editEntry($text, $word, function (string $entry) use ($flag, $on): string {
            $entry = str_replace(", '{$flag}' => true", '', $entry);
            return $on ? substr($entry, 0, -1) . ", '{$flag}' => true]" : $entry;
        });
    }

    /** Quita la entrada con su línea. */
    public static function remove(string $text, string $word): ?string
    {
        [$start, $end] = self::locate($text, $word) ?? [null, null];
        if ($start === null) {
            return null;
        }
        $lineStart = strrpos(substr($text, 0, $start), "\n");
        $lineEnd = strpos($text, "\n", $end);

        return substr($text, 0, $lineStart === false ? 0 : $lineStart) . substr($text, $lineEnd === false ? strlen($text) : $lineEnd);
    }

    /** Añade o quita una palabra de una lista de una línea (`'everyday' => [...]`, `'legit' => [...]`). */
    public static function editList(string $text, string $key, string $word, bool $add): ?string
    {
        $quoted = self::quote($word);
        if (!preg_match("/^(\\s*)'{$key}' => \\[(.*)\\],$/mu", $text, $m, PREG_OFFSET_CAPTURE)) {
            return $add ? preg_replace("/^(\\s*)'words' => \\[$/mu", "\$1'{$key}' => [{$quoted}],\n\$0", $text, 1) : null;
        }
        $items = $m[2][0] === '' ? [] : array_map('trim', explode(',', $m[2][0]));
        if ($add === in_array($quoted, $items, true)) {
            return $add ? $text : null;
        }
        $items = $add ? [...$items, $quoted] : array_values(array_diff($items, [$quoted]));

        return substr_replace($text, "{$m[1][0]}'{$key}' => [" . implode(', ', $items) . '],', $m[0][1], strlen($m[0][0]));
    }

    /** El texto de la entrada, o null si no está. */
    public static function entry(string $text, string $word): ?string
    {
        [$start, $end] = self::locate($text, $word) ?? [null, null];

        return $start === null ? null : substr($text, $start, $end - $start);
    }

    /** @param callable(string):string $change */
    private static function editEntry(string $text, string $word, callable $change): ?string
    {
        [$start, $end] = self::locate($text, $word) ?? [null, null];

        return $start === null ? null : substr($text, 0, $start) . $change(substr($text, $start, $end - $start)) . substr($text, $end);
    }

    /** @return array{int,int}|null posición del `[` de la entrada y la siguiente al `]` que la cierra */
    private static function locate(string $text, string $word): ?array
    {
        $start = strpos($text, "['word' => " . self::quote($word) . ',');
        if ($start === false) {
            return null;
        }
        [$depth, $quoted] = [0, false];
        for ($i = $start, $n = strlen($text); $i < $n; $i++) {
            $char = $text[$i];
            if ($char === '\\' && $quoted) {
                $i++;
            } elseif ($char === "'") {
                $quoted = !$quoted;
            } elseif (!$quoted && ($char === '[' || $char === ']')) {
                $depth += $char === '[' ? 1 : -1;
                if ($depth === 0) {
                    return [$start, $i + 1];
                }
            }
        }

        return null;
    }

    private static function quote(string $word): string
    {
        return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $word) . "'";
    }
}
