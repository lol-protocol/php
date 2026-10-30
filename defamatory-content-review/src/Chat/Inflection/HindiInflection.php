<?php

namespace DefamatoryContentReview\Chat\Inflection;

/** Casos oblicuos y plurales regulares del hindi (देवनागरी): -ा → -े / -ों, -ी → -ियाँ, consonante → -ें / -ों. Ver SuffixInflection. */
final class HindiInflection extends SuffixInflection
{
    protected function nouns(): array
    {
        return [
            '/^(.+)िया$/u' => ['ियाँ', 'ियां', 'ियों'],
            '/^(.+)ी$/u' => ['ियाँ', 'ियां', 'ियों'],
            '/^(.+)ा$/u' => ['े', 'ों'],
            '/^(.+)[ुू]$/u' => ['ुओं', 'ूएँ'],
            '/^(.+[^ािीुूेैोौंँ़्ृ])$/u' => ['ें', 'ों'],
        ];
    }

    protected function adjectives(): array
    {
        return [
            '/^(.+)ा$/u' => ['े', 'ी', 'ों', 'ियाँ'],
            '/^(.+[^ािीुूेैोौंँ़्ृ])$/u' => ['ों'],
        ];
    }
}
