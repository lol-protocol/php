<?php

namespace DefamatoryContentReview;

/**
 * Detecta contenido ofensivo que sólo existe en la fusión de nombre y
 * apellido: ninguno de los dos, por separado, es un término del diccionario,
 * pero leídos seguidos —sin la pausa que marca dónde termina uno y empieza
 * el otro— componen otro. Es el fenómeno de "Elba Gina" ("el vagina") o
 * "Felipe Lotas" ("Feli-pelotas"): el agravio vive exactamente en el punto
 * de unión.
 *
 * Por eso sólo cuentan las coincidencias que CRUZAN ese punto de unión. Un
 * término que cae entero dentro de un único nombre o apellido (p. ej. "ano"
 * dentro de "Mariano") no es este fenómeno — es sólo una palabra frecuente
 * que contiene esas letras, y de aceptarse sin esta restricción, arrasaría
 * con nombres perfectamente comunes (Mariano, Luciano, Adriano, Emiliano...).
 *
 * También cubre la variante de un solo campo: un nombre o apellido cuya
 * ortografía difiere de la del diccionario pero suena igual (p. ej. "Cojes"
 * frente a la entrada "Coges").
 *
 * Implementado sólo para español: las reglas de PhoneticFolder son
 * específicas de esa fonética.
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
        $firstFold = PhoneticFolder::fold($firstName);
        $lastFold = PhoneticFolder::fold($lastName);

        if ($firstFold === '' || $lastFold === '') {
            return [];
        }

        $full = $firstFold . $lastFold;
        $boundary = mb_strlen($firstFold);
        $matches = [];

        foreach ($this->wordList->getFusionCandidates($this->minLength) as $candidate) {
            $needle = $candidate['phonetic'];
            $needleLen = mb_strlen($needle);
            $searchFrom = 0;

            while (($index = self::mbStrpos($full, $needle, $searchFrom)) !== null) {
                $end = $index + $needleLen;

                if ($index < $boundary && $end > $boundary) {
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
        $pos = mb_strpos($haystack, $needle, $offset);

        return $pos === false ? null : $pos;
    }
}
