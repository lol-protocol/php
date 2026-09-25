<?php

namespace DefamatoryContentReview;

/**
 * Los términos marcados de un ValidationResult y las consultas sobre ellos
 * (por tipo de riesgo, idioma, método de detección, colisión de nombre).
 * Colaborador interno — ValidationResult expone los mismos métodos de
 * siempre, delegando aquí.
 */
final class FlaggedTermCollection
{
    /** @var array<int,array> */
    private array $terms = [];
    private array $categories = [];
    private array $riskTypes = [];

    public function __construct(private readonly string $defaultLanguage)
    {
    }

    /**
     * @param array $term Datos del término tal como los devuelve WordList,
     *                    más `sourceLanguage`/`confidence` si viene de un
     *                    idioma asociado, y `detectionMethod` ('literal'
     *                    por defecto; 'phonetic_fusion'/'phonetic_variant'
     *                    si viene de PhoneticFusionDetector).
     */
    public function add(array $term): void
    {
        $entry = [
            'term' => $term['found'] ?? $term['original'] ?? '',
            'category' => $term['category'] ?? 'desconocida',
            'riskType' => $term['riskType'] ?? 'ordinario',
            'severity' => $term['severity'] ?? 'medium',
            'nameCollision' => $term['nameCollision'] ?? false,
            'sourceLanguage' => $term['sourceLanguage'] ?? $this->defaultLanguage,
            'confidence' => $term['confidence'] ?? 1.0,
            'detectionMethod' => $term['detectionMethod'] ?? 'literal',
        ];

        $this->terms[] = $entry;

        if (!in_array($entry['category'], $this->categories, true)) {
            $this->categories[] = $entry['category'];
        }

        if (!in_array($entry['riskType'], $this->riskTypes, true)) {
            $this->riskTypes[] = $entry['riskType'];
        }
    }

    public function all(): array { return $this->terms; }
    public function categories(): array { return $this->categories; }
    public function riskTypes(): array { return $this->riskTypes; }

    public function byRiskType(string $riskType): array { return $this->filterBy('riskType', $riskType); }
    public function byLanguage(string $language): array { return $this->filterBy('sourceLanguage', $language); }
    public function byDetectionMethod(string $method): array { return $this->filterBy('detectionMethod', $method); }

    private function filterBy(string $field, string $value): array
    {
        return array_values(array_filter($this->terms, fn(array $t) => $t[$field] === $value));
    }

    /** Un término que además es apellido/nombre documentado — va a revisión humana, no a rechazo automático. */
    public function hasNameCollision(): bool
    {
        foreach ($this->terms as $term) {
            if ($term['nameCollision']) {
                return true;
            }
        }

        return false;
    }

    public function nameCollisionTerms(): array { return array_values(array_filter($this->terms, fn(array $t) => $t['nameCollision'])); }

    /** Todo lo marcado viene sólo de inferencia fonética, nada literal: la señal de menor certeza. */
    public function hasOnlyPhoneticDetections(): bool
    {
        if ($this->terms === []) {
            return false;
        }

        foreach ($this->terms as $term) {
            if ($term['detectionMethod'] === 'literal') {
                return false;
            }
        }

        return true;
    }
}
