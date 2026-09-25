<?php

namespace DefamatoryContentReview;

/**
 * El almacén normalizado => datos del término y las consultas de sólo
 * lectura sobre él (filtros y estadísticas). Colaborador interno de
 * WordList — separado porque cargar el diccionario y consultar el índice
 * ya cargado son dos momentos distintos.
 *
 * @phpstan-type WordEntry array{original: string, category: string, riskType: string, severity: string, nameCollision: bool}
 */
final class WordListIndex
{
    /** @var array<string,WordEntry> */
    private array $words = [];

    /** @param WordEntry $data */
    public function set(string $key, array $data): void { $this->words[$key] = $data; }
    /** @return WordEntry|null */
    public function get(string $key): ?array { return $this->words[$key] ?? null; }
    /** @return array<string,WordEntry> */
    public function all(): array { return $this->words; }
    public function count(): int { return count($this->words); }

    /** @return array<string,WordEntry> */
    public function byRiskType(string $riskType): array { return $this->filterBy('riskType', $riskType); }
    /** @return array<string,WordEntry> */
    public function byCategory(string $category): array { return $this->filterBy('category', $category); }
    /** @return array<string,WordEntry> */
    public function bySeverity(string $severity): array { return $this->filterBy('severity', $severity); }
    /**
     * Términos que colisionan con nombres o apellidos legítimos.
     * @return array<string,WordEntry>
     */
    public function nameCollisions(): array { return array_filter($this->words, fn(array $w) => $w['nameCollision'] === true); }

    /** @return array<string,WordEntry> */
    private function filterBy(string $field, string $value): array
    {
        return array_filter($this->words, fn(array $w) => $w[$field] === $value);
    }

    /** @return array<string,mixed> */
    public function statistics(string $language, string $coverage): array
    {
        $stats = [
            'language' => $language, 'coverage' => $coverage,
            'totalWords' => $this->count(), 'nameCollisions' => count($this->nameCollisions()),
            'byRiskType' => [], 'byCategory' => [], 'bySeverity' => [],
        ];

        foreach ($this->words as $word) {
            foreach (['riskType', 'category', 'severity'] as $field) {
                $key = $field === 'riskType' ? 'byRiskType' : ($field === 'category' ? 'byCategory' : 'bySeverity');
                $stats[$key][$word[$field]] = ($stats[$key][$word[$field]] ?? 0) + 1;
            }
        }

        arsort($stats['byRiskType']);
        arsort($stats['byCategory']);
        arsort($stats['bySeverity']);

        return $stats;
    }
}
