<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Tagalo: los sustantivos y adjetivos no se declinan (el plural es la palabra «mga» aparte), pero
 * llevan el ligamento -ng al unirse a lo que describen: «tanga» → «tangang», «gago» → «gagong»,
 * «ulol» → «ulol na», que son dos palabras. Tras -n, el ligamento es -g. Los verbos, con sus afijos
 * de aspecto y de foco, quedan literales. Ver TopicInflection.
 */
final class TagalogInflection implements TopicInflection
{
    public function forms(string $lemma, string $kind): array
    {
        return match (true) {
            $kind === 'verb' => [$lemma],
            (bool) preg_match('/[aeiou]$/u', $lemma) => [$lemma, $lemma . 'ng'],
            str_ends_with($lemma, 'n') => [$lemma, $lemma . 'g'],
            default => [$lemma],
        };
    }
}
