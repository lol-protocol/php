<?php

namespace DefamatoryContentReview;

/**
 * Detecta contenido ofensivo que sólo existe en la fusión de nombre y
 * apellido: ninguno por separado es un término del diccionario, pero
 * leídos seguidos —sin la pausa que marca dónde termina uno y empieza el
 * otro— componen otro ("Elba Gina" → "el vagina"). Por eso sólo cuentan las
 * coincidencias que CRUZAN ese punto de unión: "ano" entero dentro de
 * "Mariano" no cuenta, o arrasaría con nombres comunes.
 *
 * También cubre la variante de un solo campo: un nombre cuya ortografía
 * difiere de la del diccionario pero suena igual ("Cojes" vs. "Coges").
 *
 * La fusión funciona para los idiomas de FusionSupport; la variante, sólo
 * para los que tienen reglas en PhoneticFolderRegistry. Para el resto,
 * ambos métodos devuelven vacío.
 */
class PhoneticFusionDetector
{
    private WordList $wordList;
    private int $minLength;

    public function __construct(WordList $wordList, int $minLength = 4)
    {
        $this->wordList = $wordList;
        $this->minLength = $minLength;
    }

    /**
     * @return array<int,array> coincidencias, cada una con los datos del
     *                          término del diccionario más 'found' y
     *                          'detectionMethod' => 'phonetic_fusion'
     */
    public function detectFusion(string $firstName, string $lastName): array
    {
        if (!$this->wordList->supportsFusion()) {
            return [];
        }

        $firstFold = $this->wordList->fusionFold($firstName);
        $lastFold = $this->wordList->fusionFold($lastName);

        if ($firstFold === '' || $lastFold === '') {
            return [];
        }

        $full = $firstFold . $lastFold;
        $boundary = StringUtils::len($firstFold);
        $matches = [];

        foreach ($this->wordList->getFusionCandidates($this->minLength) as $candidate) {
            $needle = $candidate['phonetic'];
            $needleLen = StringUtils::len($needle);
            $searchFrom = 0;

            while (($index = self::mbStrpos($full, $needle, $searchFrom)) !== null) {
                if (self::crossesBoundary($index, $index + $needleLen, $boundary)) {
                    $matches[] = $candidate['data'] + [
                        'found' => $candidate['data']['original'],
                        'detectionMethod' => 'phonetic_fusion',
                    ];
                }

                $searchFrom = $index + 1;
            }
        }

        return $matches;
    }

    /**
     * Coincidencia fonética exacta de un campo completo (no una fusión de
     * dos: un único nombre o apellido que suena igual a un término del
     * diccionario, con otra grafía).
     */
    public function detectVariant(string $word): ?array
    {
        $match = $this->wordList->searchPhoneticExact($word);

        if ($match === null) {
            return null;
        }

        return $match + ['found' => $word, 'detectionMethod' => 'phonetic_variant'];
    }

    private static function mbStrpos(string $haystack, string $needle, int $offset): ?int
    {
        $pos = mb_strpos($haystack, $needle, $offset, 'UTF-8');
        return $pos === false ? null : $pos;
    }

    /** true si el rango [start,end) tiene la unión nombre/apellido estrictamente en su interior. */
    private static function crossesBoundary(int $start, int $end, int $boundary): bool
    {
        return $start < $boundary && $end > $boundary;
    }
}
