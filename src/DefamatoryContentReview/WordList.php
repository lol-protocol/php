<?php

namespace DefamatoryContentReview;

/** Diccionario de un idioma: carga, normalización, búsqueda literal y fonética. Delega en WordListIndex/WordListPhonetics sin cambiar la API. */
class WordList
{
    private WordListIndex $index;
    private WordListPhonetics $phonetics;
    private array $meta = [];
    private string $language;

    /** $config: ['meta'=>[...], 'words'=>[...]] (o el formato plano heredado: categoría => términos). meta.code manda sobre $language. */
    public function __construct(array $config, string $language = 'spa')
    {
        $this->meta = $config['meta'] ?? [];
        $this->language = $this->meta['code'] ?? $language;
        $this->index = new WordListIndex();
        $this->phonetics = new WordListPhonetics($this->language);
        $this->loadWords($config['words'] ?? $config);
    }
    public static function fromLanguageFile(string $path, string $language = 'spa'): self { return new self(require $path, $language); }

    private function loadWords(array $categories): void
    {
        foreach ($categories as $category => $terms) {
            if (!is_array($terms)) {
                continue;
            }
            foreach ($terms as $term) {
                $this->addWord(is_array($term) ? $term : ['word' => $term], (string) $category);
            }
        }
    }

    private function addWord(array $data, string $category): void
    {
        $term = $data['word'] ?? '';
        if ($term === '') {
            return;
        }
        // nameCollision: también apellido/nombre legítimo — se registra pero no basta para rechazar solo.
        $this->index->set($this->normalize($term), [
            'original' => $term,
            'category' => $category,
            'riskType' => $data['riskType'] ?? 'ordinario',
            'severity' => $data['severity'] ?? 'medium',
            'nameCollision' => $data['nameCollision'] ?? false,
        ]);
    }

    /** Minúsculas + diacríticos + leet + separadores intercalados + variantes de script ("ΜΑΛΑΚΑΣ", "козел", "مـدمـن"), para que todas lleguen a la misma clave. Conserva espacios internos (entradas multipalabra). */
    public function normalize(string $word): string
    {
        $word = preg_replace('/\s+/u', ' ', mb_strtolower(trim($word), 'UTF-8'));
        return ScriptFolding::fold(AccentFolding::fold(Leetspeak::unleet(WordListScanner::stripInsideWord($word))));
    }
    public function search(string $word): ?array { return $this->index->get($this->normalize($word)); }
    /** Todos los términos presentes en un texto: ventanas de 1-3 palabras y la palabra sin separadores intercalados. Ver WordListScanner. */
    public function findInText(string $text): array { return WordListScanner::scan($text, fn(string $phrase) => $this->search($phrase)); }
    public function getByRiskType(string $riskType): array { return $this->index->byRiskType($riskType); }
    public function getByCategory(string $category): array { return $this->index->byCategory($category); }
    public function getBySeverity(string $severity): array { return $this->index->bySeverity($severity); }
    public function getNameCollisions(): array { return $this->index->nameCollisions(); }
    public function getAllWords(): array { return $this->index->all(); }
    public function getWordCount(): int { return $this->index->count(); }
    public function getStatistics(): array { return $this->index->statistics($this->language, $this->getCoverage()); }
    public function getLanguage(): string { return $this->language; }
    public function getMeta(): array { return $this->meta; }
    public function getCoverage(): string { return $this->meta['coverage'] ?? 'basic'; }
    /** Idiomas sin separación por espacios que necesitan segmentador externo. */
    public function requiresTokenizer(): bool { return (bool) ($this->meta['requiresTokenizer'] ?? false); }
    /** Si este idioma tiene reglas de plegado fonético (ver PhoneticFolderRegistry). */
    public function supportsPhoneticFolding(): bool { return $this->phonetics->supports(); }
    /** Pliega un texto con las reglas fonéticas; sin cambios si el idioma no tiene reglas registradas. */
    public function fold(string $text): string { return $this->phonetics->fold($text); }
    /** Coincidencia fonética exacta de un término completo. Null si el idioma no tiene reglas de plegado. */
    public function searchPhoneticExact(string $word): ?array { return $this->phonetics->searchExact($word, $this->index->all()); }
    /** Candidatos para fusiones nombre+apellido. Vacío si el idioma no tiene reglas de plegado. */
    public function getFusionCandidates(int $minLength = 4): array { return $this->phonetics->fusionCandidates($this->index->all(), $minLength); }
}
