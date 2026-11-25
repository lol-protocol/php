<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Plural y casos del turco con armonía vocálica de dos y de cuatro vías: plural (-lar/-ler) y, en
 * singular y plural, acusativo (-ı/-i/-u/-ü), dativo (-a/-e), locativo (-da/-de/-ta/-te), ablativo
 * (-dan/-den/-tan/-ten), genitivo (-ın/-in/-un/-ün) e instrumental (-la/-le). Tras vocal, las
 * consonantes de unión (y, n); tras «k, p, ç, t», la forma con y sin la consonante sonorizada
 * (solo en polisílabas: «kitap» → «kitabı», pero «top» → «topu»; tras «n», «k» pasa a «g»: «pezevengi»). Los sustantivos
 * llevan además los posesivos y sus casos («kitabım», «kitabından»); los adjetivos, solo plural y casos. Los verbos, que se
 * aglutinan, quedan literales.
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

        $possessives = $kind === 'noun' ? $this->possessiveForms($lemma, $plural) : []; // el adjetivo: solo plural y casos

        return array_values(array_unique([$lemma, $plural, ...$this->cases($lemma), ...$this->cases($plural), ...$possessives]));
    }

    /**
     * Los posesivos y sus casos. Las personas 1.ª y 2.ª acaban en consonante y toman los casos de siempre
     * («kitabımı», «kitabıma», «kitabımda»); la 3.ª acaba en vocal y los toma con «n» de unión
     * («kitabını», «kitabına», «kitabında», «kitabından», «kitabıyla»).
     *
     * @return list<string>
     */
    private function possessiveForms(string $word, string $plural): array
    {
        $vowelFinal = mb_strpos(self::VOWELS, mb_substr($word, -1)) !== false;
        $stem = $this->softened($word) ?? $word;
        $link = $vowelFinal ? '' : self::FOUR[$this->lastVowel($word)]; // vocal de unión tras consonante
        $four = self::FOUR[$this->lastVowel($word)];
        $pf = self::FOUR[$this->lastVowel($plural)];
        $consonantFinal = [
            $stem . $link . 'm', $stem . $link . 'n', $stem . $link . 'm' . $four . 'z', $stem . $link . 'n' . $four . 'z',
            $plural . $pf . 'm', $plural . $pf . 'n', $plural . $pf . 'm' . $pf . 'z', $plural . $pf . 'n' . $pf . 'z',
        ];
        $thirdPerson = [$stem . ($vowelFinal ? 's' : '') . $four, $plural . $pf]; // «kitabı», «kitapları»
        $forms = [...$consonantFinal, ...$thirdPerson];
        foreach ($consonantFinal as $base) {
            array_push($forms, ...$this->cases($base));
        }
        foreach ($thirdPerson as $base) {
            array_push($forms, ...$this->casesAfterThirdPerson($base));
        }

        return $forms;
    }

    /** @return list<string> */
    private function casesAfterThirdPerson(string $word): array
    {
        $v = $this->lastVowel($word);
        [$four, $two] = [self::FOUR[$v], self::TWO[$v]];

        return [$word . 'n' . $four, $word . 'n' . $two, $word . 'n' . $four . 'n', $word . 'nd' . $two, $word . 'nd' . $two . 'n', $word . 'yl' . $two];
    }

    /** La palabra con la última consonante sonorizada («kitap» → «kitab»), solo en polisílabas; null si no cambia. */
    private function softened(string $word): ?string
    {
        $last = mb_substr($word, -1);
        if (mb_strpos(self::VOWELS, $last) !== false || !isset(self::SOFTEN[$last]) || preg_match_all('/[' . self::VOWELS . ']/u', $word) < 2) {
            return null;
        }

        return mb_substr($word, 0, -1) . (str_ends_with($word, 'nk') ? 'g' : self::SOFTEN[$last]);
    }

    /** @return list<string> */
    private function cases(string $word): array
    {
        $v = $this->lastVowel($word);
        [$four, $two] = [self::FOUR[$v], self::TWO[$v]];
        $vowelFinal = mb_strpos(self::VOWELS, mb_substr($word, -1)) !== false;
        $voiceless = preg_match('/[çfhkpsşt]$/u', $word) === 1;
        $softened = $this->softened($word); // «kitap» → «kitab-»: solo polisílabas
        $stems = $softened === null ? [$word] : [$word, $softened];
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
