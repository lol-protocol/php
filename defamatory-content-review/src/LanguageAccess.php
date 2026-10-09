<?php

namespace DefamatoryContentReview;

use RuntimeException;

/**
 * Qué idiomas conoce un reviewer y cómo llegar a su diccionario — antes era
 * un método privado (`dictionary()`) y cuatro públicos de
 * `DefamatoryContentReviewer`. Vive aparte porque tanto el motor de
 * evaluación (`NameEvaluator`) como el propio reviewer necesitan resolver
 * un código de idioma a su `WordList`, y ninguno de los dos debería cargar
 * el archivo dos veces: el caché vive acá, una sola instancia por reviewer.
 *
 * Acceso: `$reviewer->languages()->wordList('spa')`.
 */
final class LanguageAccess
{
    /** @var array<string,WordList> diccionarios ya cargados, por código ISO 639-3 */
    private array $dictionaries = [];

    public function __construct(
        private readonly LanguageRegistry $registry,
        private readonly string $languageDir
    ) {
    }

    public function registry(): LanguageRegistry
    {
        return $this->registry;
    }

    public function wordList(string $code): WordList
    {
        $code = $this->registry->resolve($code);

        if (!isset($this->dictionaries[$code])) {
            $path = "{$this->languageDir}/{$code}.php";

            if (!is_file($path)) {
                throw new RuntimeException("No existe diccionario para el idioma '{$code}' en {$path}.");
            }

            $this->dictionaries[$code] = WordList::fromLanguageFile($path, $code);
        }

        return $this->dictionaries[$code];
    }

    /** @return array<string,mixed> */
    public function statistics(string $code): array
    {
        return $this->wordList($code)->getStatistics();
    }

    /**
     * Idiomas cuyo diccionario declara un nivel de cobertura concreto
     * ('basic' / 'moderate' / 'comprehensive'). Única fuente: el propio
     * `meta.coverage` de cada archivo, vía WordList::getCoverage().
     *
     * @return array<int,string>
     */
    public function byCoverage(string $coverage): array
    {
        $matches = [];

        foreach ($this->registry->getCodes() as $code) {
            if ($this->wordList($code)->getCoverage() === $coverage) {
                $matches[] = $code;
            }
        }

        return $matches;
    }
}
