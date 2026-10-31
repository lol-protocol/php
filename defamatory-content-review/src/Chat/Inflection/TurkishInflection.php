<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Plural y casos del turco con armonía vocálica de dos y de cuatro vías: plural (-lar/-ler) y, en
 * singular y plural, acusativo (-ı/-i/-u/-ü), dativo (-a/-e), locativo (-da/-de/-ta/-te), ablativo
 * (-dan/-den/-tan/-ten), genitivo (-ın/-in/-un/-ün) e instrumental (-la/-le). Tras vocal, las
 * consonantes de unión (y, n); tras «k, p, ç, t», la forma con y sin la consonante sonorizada
 * (solo en polisílabas: «kitap» → «kitabı», pero «top» → «topu»; tras «n», «k» pasa a «g»: «pezevengi»). Los verbos, que se aglutinan, quedan literales.
 */
final class TurkishInflection implements TopicInflection
{
    private const VOWELS = 'aıoueiöüâîû';
    private const FOUR = ['â' => 'ı', 'î' => 'i', 'û' => 'u', 'a' => 'ı', 'ı' => 'ı', 'o' => 'u', 'u' => 'u', 'e' => 'i', 'i' => 'i', 'ö' => 'ü', 'ü' => 'ü'];
    private const TWO = ['â' => 'a', 'î' => 'e', 'û' => 'a', 'a' => 'a', 'ı' => 'a', 'o' => 'a', 'u' => 'a', 'e' => 'e', 'i' => 'e', 'ö' => 'e', 'ü' => 'e'];
    private const SOFTEN = ['k' => 'ğ', 'p' => 'b', 'ç' => 'c', 't' => 'd'];

    public function forms(string $lemma, string $kind): array
    {
        if ($kind === 'verb' || preg_match('/([' . self::VOWELS . '])[^' . self::VOWELS . ']*$/u', $lemma) !== 1) {
            return [$lemma];
        }
        $plural = $lemma . (self::TWO[$this->lastVowel($lemma)] === 'a' ? 'lar' : 'ler');
        if (mb_strlen($lemma) <= 2) { // «am» → «ama» (pero): con una o dos letras, el caso es otra palabra
            return [$lemma, $plural];
        }

        return array_values(array_unique([$lemma, $plural, ...$this->cases($lemma), ...$this->cases($plural)]));
    }

    /** @return list<string> */
    private function cases(string $word): array
    {
        $v = $this->lastVowel($word);
        [$four, $two] = [self::FOUR[$v], self::TWO[$v]];
        $vowelFinal = mb_strpos(self::VOWELS, mb_substr($word, -1)) !== false;
        $voiceless = preg_match('/[çfhkpsşt]$/u', $word) === 1;
        $stems = [$word];
        if (!$vowelFinal && isset(self::SOFTEN[mb_substr($word, -1)]) && preg_match_all('/[' . self::VOWELS . ']/u', $word) > 1) { // solo las polisílabas: «top» → «topu»
            $stems[] = mb_substr($word, 0, -1) . (str_ends_with($word, 'nk') ? 'g' : self::SOFTEN[mb_substr($word, -1)]); // kitap → kitab-, pezevenk → pezevengi
        }
        $forms = [];
        foreach ($stems as $stem) {
            $glide = $vowelFinal ? 'y' : '';
            array_push($forms, $stem . $glide . $four, $stem . $glide . $two, $stem . ($vowelFinal ? 'n' : '') . $four . 'n');
        }

        return [...$forms, $word . ($voiceless ? 't' : 'd') . $two, $word . ($voiceless ? 't' : 'd') . $two . 'n', $word . ($vowelFinal ? 'y' : '') . 'l' . $two];
    }

    private function lastVowel(string $word): string
    {
        preg_match('/([' . self::VOWELS . '])[^' . self::VOWELS . ']*$/u', $word, $m);

        return $m[1] ?? 'a';
    }
}
