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

        $scores = [];
        $seen = [];

        foreach ($languageSet as $code => $confidence) {
            foreach (array_values($this->languages->wordList($code)->findInText($name)) as $i => $match) {
                // El mismo término, en la misma posición de aparición, puede
                // estar en varios diccionarios de una familia; se conserva la
                // aparición de mayor confianza. La posición entra en la clave
                // para no colapsar dos apariciones distintas del mismo
                // término dentro de un único idioma (p. ej. "puta puta").
                $key = $i . '|' . $match['found'] . '|' . $match['riskType'];
                if (isset($seen[$key]) && $seen[$key] >= $confidence) {
                    continue;
                }
                $seen[$key] = $confidence;

                $result->addFlaggedTerm($match + ['sourceLanguage' => $code, 'confidence' => $confidence]);
                $scores[] = $policy->scoreOf($match) * $confidence;
            }
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
            $result->addFlaggedTerm($match + ['sourceLanguage' => $language, 'confidence' => 1.0, 'fusedFrom' => "$first $last"]);
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
                fn(array $term) => $policy->scoreOf($term) * $term['confidence'],
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
