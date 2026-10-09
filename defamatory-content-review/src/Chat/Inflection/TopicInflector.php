<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Expande las entradas de config/chat-topics/ que declaran `forms`
 * (noun, adj o verb) en una entrada por forma: «follar» deja de ser sólo
 * «follar» y pasa a cubrir «follamos», «follaron», «fóllame»… Sin esto la
 * lista sólo reconocería la forma exacta de cada palabra. `also` agrega a
 * mano las formas irregulares. Un idioma sin TopicInflection deja todo literal.
 */
final class TopicInflector
{
    private const BY_LANGUAGE = ['spa' => SpanishInflection::class, 'eng' => EnglishInflection::class];

    /** @return array<int,string> */
    public static function forms(string $language, string $lemma, string $kind): array
    {
        $class = self::BY_LANGUAGE[$language] ?? null;

        return $class === null ? [$lemma] : (new $class())->forms($lemma, $kind);
    }

    /**
     * @param array<string,array<int,array<string,mixed>>> $categories categoría => entradas
     * @return array<string,array<int,array<string,mixed>>>
     */
    public static function expand(array $categories, string $language): array
    {
        $expanded = [];
        foreach ($categories as $category => $entries) {
            foreach ($entries as $entry) {
                $forms = isset($entry['forms']) ? self::forms($language, $entry['word'], $entry['forms']) : [$entry['word']];
                $base = array_diff_key($entry, ['forms' => 1, 'also' => 1]);

                foreach (array_unique(array_merge($forms, $entry['also'] ?? [])) as $form) {
                    $expanded[$category][] = ['word' => $form] + $base;
                }
            }
        }

        return $expanded;
    }
}
