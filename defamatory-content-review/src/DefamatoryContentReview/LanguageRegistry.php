<?php

namespace DefamatoryContentReview;

use InvalidArgumentException;

/** Resuelve códigos de idioma (ISO 639-3; los de dos letras se aceptan como alias) y modela
 * el parentesco entre ellos. La afinidad en sí vive en `LanguageAffinity` — esta clase es
 * sólo identidad más los métodos que la usan. */
class LanguageRegistry
{
    /** @var array<string,array<string,mixed>> */ private array $languages;
    /** @var array<string,array<string,mixed>> */ private array $families;
    /** @var array<string,string> */ private array $aliases = [];
    private LanguageAffinity $affinity;

    /** @param array<string,array<string,mixed>> $languages
     * @param array<string,mixed> $familiesConfig */
    public function __construct(array $languages, array $familiesConfig)
    {
        $this->languages = $languages;
        $this->families = $familiesConfig['families'] ?? [];
        $this->affinity = new LanguageAffinity(
            $familiesConfig['affinity'] ?? [],
            $familiesConfig['defaultAffinityThreshold'] ?? 0.60
        );

        foreach ($languages as $code => $meta) {
            if (!empty($meta['iso639_1'])) {
                $this->aliases[$meta['iso639_1']] = $code;
            }
        }
    }

    public static function fromConfigDirectory(string $configDir): self
    {
        return new self(
            require rtrim($configDir, '/') . '/languages/supported-languages.php',
            require rtrim($configDir, '/') . '/language-families.php'
        );
    }

    /** Normaliza cualquier código aceptado a ISO 639-3. */
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

    /** @return array<string,mixed> */
    public function getMetadata(string $code): array { return $this->languages[$this->resolve($code)]; }
    /** @return array<string,array<string,mixed>> */
    public function getAll(): array { return $this->languages; }
    /** @return array<int,string> */
    public function getCodes(): array { return array_keys($this->languages); }
    public function getFamily(string $code): string { return $this->getMetadata($code)['family']; }
    /** @return array<string,array<string,mixed>> */
    public function getFamilies(): array { return $this->families; }

    /** @return array<int,string> Idiomas de la misma rama genealógica, excluido el propio. */
    public function getFamilyMembers(string $code): array
    {
        $resolved = $this->resolve($code);
        $members = $this->families[$this->getFamily($resolved)]['languages'] ?? [];

        return array_values(array_diff($members, [$resolved]));
    }

    /** Afinidad léxica aproximada; 1.0 para un idioma consigo mismo, 0.0 si no hay par declarado. */
    public function getAffinity(string $a, string $b): float
    {
        return $this->affinity->between($this->resolve($a), $this->resolve($b));
    }

    /** @return array<string,float> Idiomas asociados, de mayor a menor afinidad. threshold null usa la del config. */
    public function getRelated(string $code, ?float $threshold = null): array { return $this->affinity->relatedTo($this->resolve($code), $threshold); }

    /** @return array<string,float> El idioma consultado más sus asociados — arma el conjunto de una validación cruzada. */
    public function getValidationSet(string $code, ?float $threshold = null): array
    {
        $resolved = $this->resolve($code);
        return [$resolved => 1.0] + $this->getRelated($resolved, $threshold);
    }
}
