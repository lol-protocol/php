<?php

namespace DefamatoryContentReview\Chat;

use DefamatoryContentReview\Dictionary\WordList;

/**
 * Cómo busca ChatTopics cada frase de una línea: el término tal cual, y si no, sus lecturas
 * con letras repetidas reducidas (RepeatedLetters) o sin las preposiciones pegadas
 * (CliticPrefixes). Lo que se halla por estas lecturas nunca bloquea solo (ChatMatches).
 */
final class SearchStrategy
{
    /**
     * @param array<string,true> $legit palabras que no se leen con letras reducidas
     * @param bool|string $collapse meta `collapseRepeats`: true (dobles y largas), 'elongated' (solo largas) o false
     * @return callable(string):?array<string,mixed>
     */
    public static function of(WordList $list, string $language, bool|string $collapse, array $legit): callable
    {
        $search = fn(string $phrase): ?array => $list->search($phrase);
        if ($collapse !== false) {
            $search = RepeatedLetters::searcher($search, $legit, $collapse === true);
        }

        return CliticPrefixes::applies($language) ? CliticPrefixes::searcher($search, $language) : $search;
    }
}
