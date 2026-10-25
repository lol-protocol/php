<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Base de los idiomas que declinan por terminación (ruso, polaco, checo, rumano…):
 * cada idioma da una tabla `patrón => terminaciones`; gana la primera que encaja con
 * la palabra, y cada terminación reemplaza a la del patrón. Solo cubre las clases
 * regulares: lo irregular (vocal fugaz, cambios de raíz) va a mano en `also`. Las
 * formas inventadas no suelen chocar con palabras reales; FalsePositives lo mide.
 * Los verbos quedan literales. Colaborador de TopicInflector.
 */
abstract class SuffixInflection implements TopicInflection
{
    /** @return array<string,list<string>> patrón (con grupo 1 = raíz) => terminaciones que se añaden a la raíz */
    abstract protected function nouns(): array;

    /** @return array<string,list<string>> */
    abstract protected function adjectives(): array;

    public function forms(string $lemma, string $kind): array
    {
        if ($kind === 'verb') {
            return $this->verb($lemma);
        }
        foreach ($kind === 'adj' ? $this->adjectives() : $this->nouns() as $pattern => $endings) {
            if (preg_match($pattern, $lemma, $m) === 1) {
                return array_values(array_unique([$lemma, ...array_map(fn(string $ending): string => $m[1] . $ending, $endings)]));
            }
        }

        return [$lemma];
    }

    /** @return array<int,string> */
    protected function verb(string $lemma): array
    {
        return [$lemma];
    }
}
