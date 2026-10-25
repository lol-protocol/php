<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Declinación regular del griego: sustantivos en -ας, -ης, -ος, -α, -η, -ο, -ι, -μα y adjetivos
 * en -ος (con -η/-α y -ο). La tilde no importa: el diccionario la pliega. Ver SuffixInflection.
 */
final class GreekInflection extends SuffixInflection
{
    protected function nouns(): array
    {
        return [
            '/^(.+)[αά]ς$/u' => ['α', 'ες', 'ων'],
            '/^(.+)[ηή]ς$/u' => ['η', 'ες', 'ηδες', 'ηδων', 'ων'],
            '/^(.+)[οό]ς$/u' => ['ου', 'ο', 'οι', 'ους', 'ων', 'ε'],
            '/^(.+)μα$/u' => ['μα', 'ματος', 'ματα', 'μάτων'],
            '/^(.+)[αά]$/u' => ['ας', 'ες', 'ων'],
            '/^(.+)[ηή]$/u' => ['ης', 'ες', 'ων'],
            '/^(.+)[ιί]$/u' => ['ιου', 'ια', 'ιων'],
            '/^(.+)[οό]$/u' => ['ου', 'α', 'ων'],
        ];
    }

    protected function adjectives(): array
    {
        return [
            '/^(.+)[οό]ς$/u' => ['ος', 'η', 'α', 'ο', 'ου', 'ης', 'ας', 'οι', 'ες', 'ων', 'ους', 'ον', 'ε'],
        ];
    }
}
