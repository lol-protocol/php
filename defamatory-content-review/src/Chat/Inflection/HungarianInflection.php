<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Casos regulares del húngaro con armonía vocálica: plural (-k), acusativo (-t), plural acusativo,
 * dativo, causal, inesivo, elativo, sublativo, delativo, ablativo, alativo, superesivo e instrumental
 * («barom» → «baromok», «baromot», «baromnak», «baromban», «baromval»), según la última vocal. La
 * vocal de enlace del plural y del acusativo no se deduce de la forma («gitárok», «földek»): se dan
 * las candidatas. Tras «a» o «e» finales, esta se alarga («kurva» → «kurvák»). Los adjetivos,
 * cuando se usan como sustantivos, igual. Ver TopicInflection.
 */
final class HungarianInflection implements TopicInflection
{
    private const VOWELS = 'aáeéiíoóöőuúüű';
    /** clase => [vocales de enlace, vocal del plural acusativo, nak, ért, ban, ból, ra, ról, tól, hoz, on, val] */
    private const CLASSES = [
        'back' => [['a', 'o'], 'a', 'nak', 'ért', 'ban', 'ból', 'ra', 'ról', 'tól', 'hoz', 'on', 'al'],
        'front' => [['e'], 'e', 'nek', 'ért', 'ben', 'ből', 're', 'ről', 'től', 'hez', 'en', 'el'],
        'rounded' => [['ö', 'e'], 'e', 'nek', 'ért', 'ben', 'ből', 're', 'ről', 'től', 'höz', 'ön', 'el'],
    ];

    public function forms(string $lemma, string $kind): array
    {
        if ($kind === 'verb' || preg_match('/([' . self::VOWELS . '])[^' . self::VOWELS . ']*$/u', $lemma, $m) !== 1) {
            return [$lemma];
        }
        [$links, $plural, $dat, $for, $in, $from, $onto, $about, $away, $to, $on, $with] = self::CLASSES[$this->kind($m[1])];
        $vowelFinal = mb_strpos(self::VOWELS, mb_substr($lemma, -1)) !== false;
        $base = $vowelFinal ? (string) preg_replace(['/a$/u', '/e$/u'], ['á', 'é'], $lemma) : $lemma;
        $forms = [$lemma];
        foreach ($vowelFinal ? [''] : $links as $link) {
            array_push($forms, $base . $link . 'k', $base . $link . 't', $base . $link . 'k' . $plural . 't');
        }
        foreach ([$dat, $for, $in, $from, $onto, $about, $away, $to] as $suffix) {
            $forms[] = $base . $suffix;
        }
        array_push($forms, $vowelFinal ? $base . 'n' : $lemma . $on, $vowelFinal ? $base . 'v' . $with : $this->doubled($lemma) . $with);

        return array_values(array_unique($forms));
    }

    /** La consonante final doblada, como en «házzal» o «fasszal» (un dígrafo doblado se escribe «ssz», «ccs»…). */
    private function doubled(string $word): string
    {
        if (preg_match('/(sz|cs|zs|dz|ny|gy|ty|ly)$/u', $word, $digraph) === 1) {
            return substr($word, 0, -strlen($digraph[1])) . $digraph[1][0] . $digraph[1];
        }

        return $word . mb_substr($word, -1);
    }

    private function kind(string $vowel): string
    {
        return match (true) {
            mb_strpos('öőüű', $vowel) !== false => 'rounded',
            mb_strpos('eéií', $vowel) !== false => 'front',
            default => 'back',
        };
    }
}
