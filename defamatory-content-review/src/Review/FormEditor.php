<?php

namespace DefamatoryContentReview\Review;

/**
 * Fija la severidad de una forma generada en el `by` de su entrada:
 * «cerdas → high» en `cerdo` queda `'by' => ['f.pl' => 'high']` (ver
 * TopicInflector). Igual que ConfigEditor, toca sólo esa entrada. Null si la
 * entrada no está o no declara `forms`: sin formas, `by` no haría nada.
 */
final class FormEditor
{
    public static function setBy(string $text, string $word, string $tag, string $severity): ?string
    {
        $entry = ConfigEditor::entry($text, $word);
        if ($entry === null || !str_contains($entry, "'forms' => ")) {
            return null;
        }
        $pair = "'{$tag}' => '{$severity}'";
        $new = match (true) {
            (bool) preg_match("/'{$tag}' => '\\w+'/", $entry) && str_contains($entry, "'by' => [")
                => (string) preg_replace("/'" . preg_quote($tag, '/') . "' => '\\w+'/", $pair, $entry, 1),
            str_contains($entry, "'by' => [") => str_replace("'by' => [", "'by' => [{$pair}, ", $entry),
            default => substr($entry, 0, -1) . ", 'by' => [{$pair}]]",
        };
        $start = (int) strpos($text, $entry);

        return substr_replace($text, $new, $start, strlen($entry));
    }
}
