<?php

namespace DefamatoryContentReview;

/**
 * Validación contra el idioma principal y sus emparentados —
 * `$reviewer->related()`. Antes eran tres métodos de
 * `DefamatoryContentReviewer` (`validateAcrossRelated`,
 * `validateFullNameAcrossRelated`, `getRelatedLanguages`); viven aparte
 * porque son un caso de uso propio, no el camino principal de validación.
 *
 * En una plataforma genealógica los registros de una región traen
 * apellidos de las lenguas vecinas: un árbol español contiene ramas
 * portuguesas, uno ruso ramas ucranianas. Una coincidencia hallada en un
 * idioma asociado pesa menos que una del principal — su confianza es la
 * afinidad léxica entre ambos.
 *
 * Se construye de nuevo en cada llamada a `related()`: captura el idioma y
 * la policy vigentes en ese momento, así que nunca queda desactualizado si
 * el reviewer cambia de idioma o de policy entre usos.
 */
final class RelatedLanguageValidator
{
    public function __construct(
        private readonly LanguageRegistry $registry,
        private readonly string $language,
        private readonly NameEvaluator $evaluator,
        private readonly ScoringPolicy $policy
    ) {
    }

    /** @param float|null $threshold Afinidad mínima para incluir un idioma. */
    public function validate(string $name, ?float $threshold = null): ValidationResult
    {
        $languageSet = $this->registry->getValidationSet($this->language, $threshold);

        return $this->evaluator->evaluate($name, $languageSet, $this->policy, $this->language);
    }

    public function validateFullName(string $first, string $last, ?float $threshold = null): ValidationResult
    {
        $result = $this->validate(trim($first . ' ' . $last), $threshold);
        $this->evaluator->applyPhoneticChecks($result, $first, $last, $this->language, $this->policy);

        return $result;
    }

    /** @return array<string,float> idiomas asociados => afinidad */
    public function languages(?float $threshold = null): array
    {
        return $this->registry->getRelated($this->language, $threshold);
    }
}
