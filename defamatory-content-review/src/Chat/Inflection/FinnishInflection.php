<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Casos regulares del finés para sustantivos y adjetivos que acaban en vocal o en -nen / -us / -ys:
 * genitivo, partitivo, inesivo, elativo, ilativo, adesivo, alativo, ablativo, esivo y traslativo, en
 * singular y plural, con armonía vocálica (a/ä) y las dos raíces de la alternancia de consonantes
 * (FinnishGradation): una terminación con «~» admite también la raíz débil. Hay formas inventadas;
 * llegan a revisión y se miden. Los verbos quedan literales.
 */
final class FinnishInflection implements TopicInflection
{
    private const BACK = ['~n', '~t', 'a', 'ja', 'n', '~ssa', '~sta', '~lla', '~lle', '~lta', 'na', '~ksi', 'jen', '~issa', 'ihin'];

    public function forms(string $lemma, string $kind): array
    {
        if ($kind === 'verb') {
            return [$lemma];
        }
        $front = preg_match('/[äöy]/u', $lemma) === 1;
        $forms = match (true) {
            (bool) preg_match('/^(.+)nen$/u', $lemma, $m) => $this->plain($m[1] . 's', ['en', 'et', 'ta', 'ia', 'een', 'essa', 'esta', 'ella', 'elle', 'elta', 'ena', 'eksi', 'ien', 'eissa'], $front),
            (bool) preg_match('/^(.+)([uy])s$/u', $lemma, $m) => $this->plain($m[1] . $m[2], ['ksen', 'kset', 'sta', 'ksia', 'kseen', 'ksessa', 'ksesta', 'ksella', 'kselle', 'kselta', 'ksena', 'kseksi', 'ksien'], $front),
            (bool) preg_match('/^(.*)(aa|ää|ee|ii|oo|uu|yy|öö)$/u', $lemma, $m) => array_merge($this->plain($lemma, ['n', 't', 'ta', 'ssa', 'sta', 'lla', 'lle', 'lta', 'na', 'ksi', 'h' . mb_substr($m[2], 0, 1) . 'n'], $front), $this->plain($m[1] . mb_substr($m[2], 0, 1), ['ita', 'iden', 'issa', 'ihin', 'ille'], $front)),
            (bool) preg_match('/^(.+)i$/u', $lemma, $m) => $this->graded($m[1], ['~in', '~it', 'ia', 'eja', 'iin', '~issa', '~ista', '~illa', '~ille', '~ilta', 'ina', '~iksi', 'ien', '~eissa', 'eihin'], $front),
            (bool) preg_match('/^(.+)[aä]$/u', $lemma, $m) => $this->graded($m[1], ['~an', '~at', 'aa', 'oja', 'aan', '~assa', '~asta', '~alla', '~alle', '~alta', 'ana', '~aksi', 'ojen', '~oissa', 'oihin', 'ia', 'ien'], $front, ['oja', 'ojen', 'oissa', 'oihin']),
            (bool) preg_match('/^(.+)([ouyö])$/u', $lemma, $m) => $this->graded($m[1], array_map(fn(string $e): string => ($e[0] === '~' ? '~' : '') . ($e === 'n' ? $m[2] : '') . $m[2] . ltrim($e, '~'), self::BACK), $front),
            default => [],
        };

        return array_values(array_unique([$lemma, ...$forms]));
    }

    /**
     * @param list<string> $endings
     * @return list<string>
     */
    private function plain(string $stem, array $endings, bool $front): array
    {
        return array_map(fn(string $e): string => $stem . $this->harmony($e, $front), $endings);
    }

    /**
     * @param list<string> $endings
     * @param list<string> $backOnly terminaciones de -a que en una palabra con vocales anteriores («lehmä») no existen
     * @return list<string>
     */
    private function graded(string $stem, array $endings, bool $front, array $backOnly = []): array
    {
        $forms = [];
        foreach (FinnishGradation::stems($stem) as $i => $variant) {
            foreach ($endings as $ending) {
                if (($i === 0 || $ending[0] === '~') && !($front && in_array(ltrim($ending, '~'), $backOnly, true))) {
                    $forms[] = $variant . $this->harmony(ltrim($ending, '~'), $front);
                }
            }
        }

        return $forms;
    }

    private function harmony(string $ending, bool $front): string
    {
        return $front ? strtr($ending, ['a' => 'ä', 'o' => 'ö', 'u' => 'y']) : $ending;
    }
}
