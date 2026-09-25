<?php

namespace DefamatoryContentReview;

/**
 * Detecta contenido ofensivo que sólo existe en la fusión de nombre y
 * apellido: ninguno por separado es un término del diccionario, pero
 * leídos seguidos —sin la pausa que marca dónde termina uno y empieza el
 * otro— componen otro ("Elba Gina" → "el vagina"). Sólo cuentan las
 * coincidencias que CRUZAN la unión ("ano" dentro de "Mariano" no) y que
 * tocan el principio o el final del nombre completo, con al menos 2 letras
 * a cada lado: así se lee el chiste. Un término enterrado en medio ("Emine
 * Kaya" → "inek") o que sólo roza la unión ("Ana O…" → "anão") no se
 * percibe al leer y disparaba con nombres reales comunes.
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

    /** @return array<int,array<string,mixed>> coincidencias, cada una con los datos del término del diccionario más 'found' y 'detectionMethod' => 'phonetic_fusion' */
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
        $boundary = mb_strlen($firstFold, 'UTF-8');
        $total = $boundary + mb_strlen($lastFold, 'UTF-8');
        $matches = [];

        foreach ($this->wordList->getFusionCandidates($this->minLength) as $candidate) {
            $needle = $candidate['phonetic'];
            $needleLen = mb_strlen($needle, 'UTF-8');
            $searchFrom = 0;

            while (($index = self::mbStrpos($full, $needle, $searchFrom)) !== null) {
                if (self::isReadableFusion($index, $index + $needleLen, $boundary, $total)) {
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

    /** @return array<string,mixed>|null Un único campo que suena igual a un término del diccionario con otra grafía ("Cojes"/"Coges"). */
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
        return ($pos = mb_strpos($haystack, $needle, $offset, 'UTF-8')) === false ? null : $pos;
    }

    private static function isReadableFusion(int $start, int $end, int $boundary, int $total): bool
    {
        return $boundary - $start >= 2 && $end - $boundary >= 2 && ($start === 0 || $end === $total);
    }
}
