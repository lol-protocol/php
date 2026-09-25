<?php

namespace DefamatoryContentReview;

/**
 * Busca coincidencias literales y de fusión fonética, y traduce lo
 * encontrado a severidad con la ScoringPolicy que se le pase (no se guarda,
 * así `setPolicy()` no exige reconstruir nada). Colaborador interno
 * compartido por `DefamatoryContentReviewer` y `RelatedLanguageValidator`.
 */
final class NameEvaluator
{
    public function __construct(private readonly LanguageAccess $languages) { }

    /** @param array<string,float> $languageSet código => confianza (1.0 = idioma principal) */
    public function evaluate(string $name, array $languageSet, ScoringPolicy $policy, string $primaryLanguage): ValidationResult
    {
        $result = new ValidationResult($name, true, $primaryLanguage);
        $result->setLanguagesChecked($languageSet);

        $best = [];

        foreach ($languageSet as $code => $confidence) {
            $occurrence = [];

            foreach ($this->languages->wordList($code)->findInText($name) as $match) {
                // El mismo término puede estar en varios diccionarios de una
                // familia: se conserva el de mayor confianza. La clave es la
                // n-ésima aparición de ese término, no su índice en la lista
                // de cada idioma — ese índice se corre cuando un idioma
                // encuentra algo que otro no. Contar apariciones mantiene
                // separadas las repeticiones reales ("puta puta").
                $base = $match['found'] . '|' . $match['riskType'];
                $key = $base . '|' . ($occurrence[$base] = ($occurrence[$base] ?? -1) + 1);

                if (isset($best[$key]) && $best[$key]['confidence'] >= $confidence) {
                    continue;
                }

                $best[$key] = $match + ['sourceLanguage' => $code, 'confidence' => $confidence];
            }
        }

        $scores = [];

        foreach ($best as $term) {
            $result->addFlaggedTerm($term);
            $scores[] = $policy->scoreOf($term) * $term['confidence'];
        }

        return $this->finalizeScore($result, $scores, $policy);
    }

    /** "Elba Gina" ("el vagina"): se suma a lo ya detectado por evaluate(). Sólo idiomas de FusionSupport. */
    public function applyPhoneticChecks(ValidationResult $result, string $first, string $last, string $language, ScoringPolicy $policy): void
    {
        $wordList = $this->languages->wordList($language);

        if (!$wordList->supportsFusion() || trim($first) === '' || trim($last) === '') {
            return;
        }

        $detector = new PhoneticFusionDetector($wordList);
        $added = false;

        foreach ($detector->detectFusion($first, $last) as $match) {
            $result->addFlaggedTerm($match + ['sourceLanguage' => $language, 'confidence' => 1.0]);
            $added = true;
        }

        foreach ([$first, $last] as $field) {
            if ($wordList->search($field) !== null) {
                continue; // ya cubierto por la búsqueda literal de evaluate()
            }

            $variant = $detector->detectVariant($field);

            if ($variant !== null) {
                $result->addFlaggedTerm($variant + ['sourceLanguage' => $language, 'confidence' => 1.0]);
                $added = true;
            }
        }

        if ($added) {
            $scores = array_map(
                fn(array $term) => $policy->scoreOf($term) * ($term['confidence'] ?? 1.0),
                $result->getFlaggedTerms()
            );
            $this->finalizeScore($result, $scores, $policy);
        }
    }

    /** @param array<int,float> $scores */
    private function finalizeScore(ValidationResult $result, array $scores, ScoringPolicy $policy): ValidationResult
    {
        $score = $policy->aggregate($scores);
        $result->setScore($score);

        if ($score <= 0.0) {
            return $result->setValid(true)->setSeverity('none');
        }

        return $result->setValid(false)->setSeverity($policy->severityFromScore($score));
    }
}
