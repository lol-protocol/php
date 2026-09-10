<?php

namespace DefamatoryContentReview;

class WordList
{
    /** @var array<string,array> normalizado => datos del término */
    private array $words = [];
    private array $meta = [];
    private string $language;
    /** @var array<string,array>|null forma fonética => datos del primer término que la produce */
    private ?array $phoneticIndex = null;

    /**
     * @param array  $config   Diccionario en formato ['meta' => [...], 'words' => [...]].
     *                         Se acepta también el formato plano heredado (categoría => términos).
     * @param string $language Código ISO 639-3. Si el config trae `meta.code`, ese tiene prioridad.
     */
    public function __construct(array $config, string $language = 'spa')
    {
        $this->meta = $config['meta'] ?? [];
        $this->language = $this->meta['code'] ?? $language;

        $this->loadWords($config['words'] ?? $config);
    }

    public static function fromLanguageFile(string $path, string $language = 'spa'): self
    {
        return new self(require $path, $language);
    }

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

        $this->words[$this->normalize($term)] = [
            'original' => $term,
            'category' => $category,
            'riskType' => $data['riskType'] ?? 'ordinario',
            'severity' => $data['severity'] ?? 'medium',
            // Términos que también son apellidos o nombres legítimos: la
            // coincidencia se registra pero no basta para rechazar por sí sola.
            'nameCollision' => $data['nameCollision'] ?? false,
        ];
    }

    /**
     * Minúsculas, plegado de diacríticos y de sustitución numérica ("leet"),
     * para que "Cérda", "CERDA", "cerda" y "c3rda" lleguen a la misma clave.
     * Los espacios internos se conservan: hay entradas multipalabra ("hijo de
     * puta") que deben poder buscarse tal cual.
     */
    public function normalize(string $word): string
    {
        $word = mb_strtolower(trim($word), 'UTF-8');
        $word = preg_replace('/\s+/u', ' ', $word);
        $word = strtr($word, self::LEETSPEAK);

        return strtr($word, self::FOLDING);
    }

    /**
     * Sustituciones numéricas/simbólicas más comunes para esquivar un
     * filtro literal ("c3rda", "v4g1na", "put@"). No exhaustivo — no cubre
     * variantes multi-carácter como "|)" por "d" o "ph" por "f" — pero cubre
     * las de un solo carácter que de verdad se usan para esto. Se omite "1"
     * como posible "l" (queda sólo como "i", su lectura más frecuente): una
     * sustitución ambigua entre dos letras no puede resolverse sin contexto,
     * así que se prioriza la lectura más común en vez de intentar las dos.
     */
    private const LEETSPEAK = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
        '7' => 't', '8' => 'b', '@' => 'a', '$' => 's',
    ];

    private const FOLDING = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a', 'ą' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ī' => 'i', 'į' => 'i', 'ı' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ø' => 'o', 'ō' => 'o', 'ő' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ū' => 'u', 'ů' => 'u', 'ű' => 'u', 'ų' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c',
        'ś' => 's', 'š' => 's', 'ş' => 's',
        'ź' => 'z', 'ż' => 'z', 'ž' => 'z',
        'ł' => 'l', 'ĺ' => 'l', 'ľ' => 'l',
        'ř' => 'r', 'ŕ' => 'r',
        'ť' => 't', 'ţ' => 't',
        'ď' => 'd', 'đ' => 'd',
        'ğ' => 'g',
        'ß' => 'ss', 'æ' => 'ae', 'œ' => 'oe',
    ];

    public function search(string $word): ?array
    {
        return $this->words[$this->normalize($word)] ?? null;
    }

    /**
     * Busca todos los términos del diccionario presentes en un texto.
     *
     * Además de cada token suelto, prueba las ventanas de 2 y 3 palabras
     * consecutivas, porque hay entradas multipalabra ("hijo de puta",
     * "vieja fille") que se perderían token a token.
     *
     * @return array<int,array> coincidencias con la forma hallada en el texto
     */
    public function findInText(string $text): array
    {
        $tokens = preg_split('/[\s\-.,_·]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $matches = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            for ($span = min(3, $count - $i); $span >= 1; $span--) {
                $phrase = implode(' ', array_slice($tokens, $i, $span));
                $found = $this->search($phrase);

                if ($found !== null) {
                    $matches[] = $found + ['found' => $phrase];
                    // Un token ya consumido por una frase larga no vuelve a
                    // contarse por separado.
                    $i += $span - 1;
                    break;
                }
            }
        }

        return $matches;
    }

    public function getByRiskType(string $riskType): array
    {
        return array_filter($this->words, fn(array $w) => $w['riskType'] === $riskType);
    }

    public function getByCategory(string $category): array
    {
        return array_filter($this->words, fn(array $w) => $w['category'] === $category);
    }

    public function getBySeverity(string $severity): array
    {
        return array_filter($this->words, fn(array $w) => $w['severity'] === $severity);
    }

    /** Términos que colisionan con nombres o apellidos legítimos. */
    public function getNameCollisions(): array
    {
        return array_filter($this->words, fn(array $w) => $w['nameCollision'] === true);
    }

    public function getAllWords(): array
    {
        return $this->words;
    }

    public function getWordCount(): int
    {
        return count($this->words);
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getMeta(): array
    {
        return $this->meta;
    }

    public function getCoverage(): string
    {
        return $this->meta['coverage'] ?? 'basic';
    }

    /** Idiomas sin separación por espacios que necesitan segmentador externo. */
    public function requiresTokenizer(): bool
    {
        return (bool) ($this->meta['requiresTokenizer'] ?? false);
    }

    /** Si este idioma tiene reglas de plegado fonético (ver PhoneticFolderRegistry). */
    public function supportsPhoneticFolding(): bool
    {
        return PhoneticFolderRegistry::isSupported($this->language);
    }

    /**
     * Pliega un texto con las reglas fonéticas de este idioma. Si el idioma
     * no tiene reglas registradas, devuelve el texto sin tocar — llamar antes
     * a supportsPhoneticFolding() para saber si el resultado es significativo.
     */
    public function fold(string $text): string
    {
        return PhoneticFolderRegistry::fold($this->language, $text);
    }

    /** @return array<string,array> forma fonética => datos del primer término que la produce */
    private function phoneticIndex(): array
    {
        if (!$this->supportsPhoneticFolding()) {
            return [];
        }

        if ($this->phoneticIndex === null) {
            $this->phoneticIndex = [];
            foreach ($this->words as $word) {
                $folded = $this->fold($word['original']);
                $this->phoneticIndex[$folded] ??= $word;
            }
        }

        return $this->phoneticIndex;
    }

    /**
     * Coincidencia fonética exacta: mismo sonido que un término del
     * diccionario aunque la ortografía sea distinta (p. ej. "Cojes" frente a
     * la entrada "Coges"). A diferencia de search(), no exige la misma grafía.
     *
     * Devuelve null si el idioma no tiene reglas de plegado (ver
     * supportsPhoneticFolding()).
     */
    public function searchPhoneticExact(string $word): ?array
    {
        if (!$this->supportsPhoneticFolding()) {
            return null;
        }

        return $this->phoneticIndex()[$this->fold($word)] ?? null;
    }

    /**
     * Candidatos para detectar fusiones nombre+apellido: forma fonética de
     * cada término del diccionario, filtrados por longitud mínima para no
     * disparar con fragmentos demasiado comunes (p. ej. "ano", que aparece
     * dentro de "Mariano" o "Luciano" sin que eso sea el fenómeno buscado).
     *
     * Vacío si el idioma no tiene reglas de plegado.
     *
     * @return array<int,array{phonetic:string,data:array}>
     */
    public function getFusionCandidates(int $minLength = 4): array
    {
        $candidates = [];

        foreach ($this->phoneticIndex() as $folded => $data) {
            if (mb_strlen($folded) >= $minLength) {
                $candidates[] = ['phonetic' => $folded, 'data' => $data];
            }
        }

        return $candidates;
    }

    public function getStatistics(): array
    {
        $stats = [
            'language' => $this->language,
            'coverage' => $this->getCoverage(),
            'totalWords' => $this->getWordCount(),
            'nameCollisions' => count($this->getNameCollisions()),
            'byRiskType' => [],
            'byCategory' => [],
            'bySeverity' => [],
        ];

        foreach ($this->words as $word) {
            foreach (['riskType', 'category', 'severity'] as $field) {
                $key = $field === 'riskType' ? 'byRiskType' : ($field === 'category' ? 'byCategory' : 'bySeverity');
                $stats[$key][$word[$field]] = ($stats[$key][$word[$field]] ?? 0) + 1;
            }
        }

        arsort($stats['byRiskType']);
        arsort($stats['bySeverity']);

        return $stats;
    }
}
