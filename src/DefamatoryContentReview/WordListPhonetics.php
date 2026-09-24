<?php

namespace DefamatoryContentReview;

/**
 * Plegado fonético, coincidencia fonética exacta y candidatos de fusión
 * para un idioma. Colaborador interno de WordList. La coincidencia exacta
 * sólo aplica a idiomas con reglas en PhoneticFolderRegistry; los
 * candidatos de fusión, a todos los de FusionSupport.
 */
final class WordListPhonetics
{
    /** @var array<string,array>|null forma fonética => datos del primer término que la produce */
    private ?array $index = null;

    /** @var array<int,array<int,array{phonetic:string,data:array}>> minLength => candidatos ya filtrados */
    private array $fusionCandidatesCache = [];

    public function __construct(private readonly string $language)
    {
    }

    public function supports(): bool { return PhoneticFolderRegistry::isSupported($this->language); }
    public function fold(string $text): string { return PhoneticFolderRegistry::fold($this->language, $text); }
    public function supportsFusion(): bool { return FusionSupport::isSupported($this->language); }
    public function fusionFold(string $text): string { return FusionSupport::fold($this->language, $text); }

    /**
     * Coincidencia fonética exacta: mismo sonido que un término del
     * diccionario aunque la ortografía sea distinta ("Cojes" vs. "Coges").
     * @param array<string,array> $words normalizado => datos, del WordList dueño
     */
    public function searchExact(string $word, array $words): ?array
    {
        return $this->supports() ? ($this->buildIndex($words)[$this->fusionFold($word)] ?? null) : null;
    }

    /**
     * Forma fonética de cada término, filtrada por longitud mínima para no
     * disparar con fragmentos comunes ("ano" dentro de "Mariano").
     * @return array<int,array{phonetic:string,data:array}>
     */
    public function fusionCandidates(array $words, int $minLength): array
    {
        if (isset($this->fusionCandidatesCache[$minLength])) {
            return $this->fusionCandidatesCache[$minLength];
        }

        $candidates = [];
        $index = $this->buildIndex($words);

        foreach ($index as $folded => $data) {
            if (StringUtils::lenGe($folded, $minLength)) {
                $candidates[] = ['phonetic' => $folded, 'data' => $data];
            }
        }

        return $this->fusionCandidatesCache[$minLength] = $candidates;
    }

    private function buildIndex(array $words): array
    {
        if (!$this->supportsFusion()) {
            return [];
        }

        if ($this->index === null) {
            $this->index = [];
            foreach ($words as $word) {
                $this->index[$this->fusionFold($word['original'])] ??= $word;
            }
        }

        return $this->index;
    }
}
