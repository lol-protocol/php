<?php

namespace DefamatoryContentReview;

/**
 * Los términos marcados de un ValidationResult y las consultas sobre ellos
 * (por tipo de riesgo, idioma, método de detección, colisión de nombre).
 * Colaborador interno — ValidationResult expone los mismos métodos de
 * siempre, delegando aquí.
 *
 * @phpstan-type FlaggedEntry array{term: string, category: string, riskType: string, severity: string, nameCollision: bool, sourceLanguage: string, confidence: float, detectionMethod: string, matchedEntry: string|null, fusedFrom: string|null}
 */
final class FlaggedTermCollection
{
    /** @var array<int,FlaggedEntry> */ private array $terms = [];
    /** @var array<int,string> */ private array $categories = [];
    /** @var array<int,string> */ private array $riskTypes = [];

    public function __construct(private readonly string $defaultLanguage)
    {
    }

    /** @param array<string,mixed> $term Datos del término tal como los devuelve WordList, más
     * `sourceLanguage`/`confidence` si viene de un idioma asociado, y `detectionMethod`
     * ('literal' por defecto; 'phonetic_fusion'/'phonetic_variant' si viene de PhoneticFusionDetector). */
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
            'matchedEntry' => $term['original'] ?? null,
            'fusedFrom' => $term['fusedFrom'] ?? null,
        ];

        $this->terms[] = $entry;

        if (!in_array($entry['category'], $this->categories, true)) {
            $this->categories[] = $entry['category'];
        }

        if (!in_array($entry['riskType'], $this->riskTypes, true)) {
            $this->riskTypes[] = $entry['riskType'];
        }
    }

    /** @return array<int,FlaggedEntry> */ public function all(): array { return $this->terms; }
    /** @return array<int,string> */ public function categories(): array { return $this->categories; }
    /** @return array<int,string> */ public function riskTypes(): array { return $this->riskTypes; }

    /** @return array<int,FlaggedEntry> */ public function byRiskType(string $riskType): array { return $this->filterBy('riskType', $riskType); }
    /** @return array<int,FlaggedEntry> */ public function byLanguage(string $language): array { return $this->filterBy('sourceLanguage', $language); }
    /** @return array<int,FlaggedEntry> */ public function byDetectionMethod(string $method): array { return $this->filterBy('detectionMethod', $method); }

    /** @return array<int,FlaggedEntry> */
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

    /** @return array<int,FlaggedEntry> */ public function nameCollisionTerms(): array { return array_values(array_filter($this->terms, fn(array $t) => $t['nameCollision'])); }

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
