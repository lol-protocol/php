<?php

namespace DefamatoryContentReview;

use InvalidArgumentException;

/**
 * Resuelve códigos de idioma y modela el parentesco entre ellos.
 *
 * El módulo trabaja internamente con ISO 639-3 (tres letras). Los códigos de
 * dos letras se aceptan como alias de entrada y se normalizan al entrar, de
 * modo que integraciones existentes puedan seguir pasando 'es' o 'pt'.
 */
class LanguageRegistry
{
    private array $languages;
    private array $families;
    private array $affinity = [];
    private array $aliases = [];
    private float $defaultThreshold;

    public function __construct(array $languages, array $familiesConfig)
    {
        $this->languages = $languages;
        $this->families = $familiesConfig['families'] ?? [];
        $this->defaultThreshold = $familiesConfig['defaultAffinityThreshold'] ?? 0.60;

        foreach ($languages as $code => $meta) {
            if (!empty($meta['iso639_1'])) {
                $this->aliases[$meta['iso639_1']] = $code;
            }
        }

        $this->loadAffinity($familiesConfig['affinity'] ?? []);
    }

    public static function fromConfigDirectory(string $configDir): self
    {
        return new self(
            require rtrim($configDir, '/') . '/languages/supported-languages.php',
            require rtrim($configDir, '/') . '/language-families.php'
        );
    }

    /**
     * La configuración declara cada par una sola vez; aquí se simetriza para
     * que la búsqueda sea directa en ambos sentidos.
     */
    private function loadAffinity(array $pairs): void
    {
        foreach ($pairs as $pair => $score) {
            [$a, $b] = explode('|', $pair);
            $this->affinity[$a][$b] = $score;
            $this->affinity[$b][$a] = $score;
        }
    }

    /**
     * Normaliza cualquier código aceptado a ISO 639-3.
     */
    public function resolve(string $code): string
    {
        $code = strtolower(trim($code));

        if (isset($this->languages[$code])) {
            return $code;
        }

        if (isset($this->aliases[$code])) {
            return $this->aliases[$code];
        }

        throw new InvalidArgumentException("Código de idioma no soportado: '{$code}'.");
    }

    public function isSupported(string $code): bool
    {
        $code = strtolower(trim($code));

        return isset($this->languages[$code]) || isset($this->aliases[$code]);
    }

    public function getMetadata(string $code): array
    {
        return $this->languages[$this->resolve($code)];
    }

    public function getAll(): array
    {
        return $this->languages;
    }

    public function getCodes(): array
    {
        return array_keys($this->languages);
    }

    public function getFamily(string $code): string
    {
        return $this->getMetadata($code)['family'];
    }

    public function getFamilies(): array
    {
        return $this->families;
    }

    /**
     * Idiomas de la misma rama genealógica, excluido el propio.
     */
    public function getFamilyMembers(string $code): array
    {
        $resolved = $this->resolve($code);
        $family = $this->getFamily($resolved);
        $members = $this->families[$family]['languages'] ?? [];

        return array_values(array_diff($members, [$resolved]));
    }

    /**
     * Afinidad léxica aproximada entre dos idiomas.
     * 1.0 para un idioma consigo mismo; 0.0 si no hay par declarado.
     */
    public function getAffinity(string $a, string $b): float
    {
        $a = $this->resolve($a);
        $b = $this->resolve($b);

        if ($a === $b) {
            return 1.0;
        }

        return $this->affinity[$a][$b] ?? 0.0;
    }

    /**
     * Idiomas asociados a uno dado, ordenados de mayor a menor afinidad.
     *
     * @param float|null $threshold Afinidad mínima; null usa la del config.
     * @return array<string,float> código => afinidad
     */
    public function getRelated(string $code, ?float $threshold = null): array
    {
        $resolved = $this->resolve($code);
        $threshold ??= $this->defaultThreshold;

        $related = array_filter(
            $this->affinity[$resolved] ?? [],
            fn(float $score) => $score >= $threshold
        );

        arsort($related);

        return $related;
    }

    /**
     * El idioma consultado más sus asociados, con la afinidad de cada uno.
     * Útil para armar el conjunto de diccionarios de una validación cruzada.
     *
     * @return array<string,float>
     */
    public function getValidationSet(string $code, ?float $threshold = null): array
    {
        $resolved = $this->resolve($code);

        return [$resolved => 1.0] + $this->getRelated($resolved, $threshold);
    }

    public function getDefaultThreshold(): float
    {
        return $this->defaultThreshold;
    }

    /**
     * Idiomas cuyo diccionario aún necesita revisión de hablante nativo.
     */
    public function getLanguagesByCoverage(string $coverage): array
    {
        return array_keys(array_filter(
            $this->languages,
            fn(array $meta) => ($meta['coverage'] ?? 'basic') === $coverage
        ));
    }
}
