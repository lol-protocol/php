<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Conjugación regular por tablas, común a las lenguas romances: cada idioma
 * declara sus terminaciones por grupo (`ar`/`er`/`ir`, `are`/`ere`/`ire`…),
 * las formas que admiten pronombre pegado («matarlos», «ammazzarti»,
 * «baise-moi») y los cambios de ortografía que conservan el sonido. Las
 * terminaciones van sin tilde porque WordList las pliega. Lo irregular va a
 * mano en `also`.
 */
abstract class RomanceVerbs
{
    /** @var array<string,array<int,string>> grupo (terminación del infinitivo) => terminaciones */
    protected const ENDINGS = [];
    /** @var array<string,array<int,string>> grupo => terminaciones a las que se pega un pronombre */
    protected const HOSTS = [];
    /** @var array<int,string> */
    protected const CLITICS = [];

    /** @return array<int,string> la forma base y sus formas regulares; sólo la base si no es de ningún grupo */
    public static function forms(string $lemma): array
    {
        foreach (static::ENDINGS as $class => $endings) {
            if (!str_ends_with($lemma, $class) || strlen($lemma) <= strlen($class)) {
                continue;
            }
            $stem = substr($lemma, 0, -strlen($class));
            $forms = [$lemma];
            foreach ($endings as $ending) {
                $forms[] = static::spell($stem, $ending, $class) . $ending;
            }
            foreach (static::HOSTS[$class] ?? [] as $host) {
                foreach (static::CLITICS as $clitic) {
                    $forms[] = static::spell($stem, $host, $class) . $host . $clitic;
                }
            }

            return array_values(array_unique($forms));
        }

        return [$lemma];
    }

    /** La raíz ante esa terminación: sin cambios salvo que el idioma los declare. */
    protected static function spell(string $stem, string $ending, string $class): string
    {
        return $stem;
    }
}
