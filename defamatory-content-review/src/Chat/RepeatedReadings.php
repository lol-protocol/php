<?php

namespace DefamatoryContentReview\Chat;

/**
 * Las lecturas de una frase con sus rachas de letras iguales reducidas. Cada
 * racha puede valer 1 letra o 2, porque ningún diccionario tiene 3 iguales
 * seguidas y casi ninguna palabra tiene más de 2: «follarr» se lee «follar»
 * (2 «l», 1 «r»), no «folar». Colaborador interno de RepeatedLetters.
 */
final class RepeatedReadings
{
    /** Con más rachas que éstas sólo se prueban «todas a 1» y «todas a 2»: 2^6 lecturas ya son muchas. */
    private const MAX_RUNS = 6;

    /**
     * @param array<string,true> $legit palabras que no se tocan (claves de RepeatedLetters::key())
     * @return array{0:array<int,string>,1:int} las lecturas, sin la original, y la racha más larga (en letras)
     */
    public static function of(string $phrase, array $legit): array
    {
        $runs = self::runs($phrase, $legit);
        $count = count($runs);
        $masks = $count === 0 ? [] : ($count <= self::MAX_RUNS ? range(0, 2 ** $count - 1) : [0, 2 ** $count - 1]);
        $readings = [];

        foreach ($masks as $mask) {
            $reading = $phrase;
            for ($i = $count - 1; $i >= 0; $i--) { // de atrás hacia delante, para no mover las posiciones que faltan
                $letters = ($mask >> $i) & 1 ? 1 : 2;
                $reading = substr_replace($reading, str_repeat($runs[$i]['letter'], $letters), $runs[$i]['offset'], $runs[$i]['bytes']);
            }
            $readings[$reading] = true;
        }
        unset($readings[$phrase]);

        return [array_map('strval', array_keys($readings)), $count === 0 ? 0 : max(array_column($runs, 'length'))];
    }

    /**
     * Las rachas de letras (o dígitos) iguales de la frase, fuera de las palabras de `$legit`.
     *
     * @param array<string,true> $legit
     * @return array<int,array{offset:int,bytes:int,length:int,letter:string}> posición y tamaño en bytes, largo en letras
     */
    private static function runs(string $phrase, array $legit): array
    {
        $runs = [];
        preg_match_all('/[\p{L}\p{N}]+/u', $phrase, $words, PREG_OFFSET_CAPTURE);
        foreach ($words[0] as [$word, $start]) {
            if (isset($legit[RepeatedLetters::key($word)])) {
                continue;
            }
            preg_match_all('/([\p{L}\p{N}])\1+/iu', $word, $found, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
            foreach ($found as $run) {
                $runs[] = ['offset' => $start + $run[0][1], 'bytes' => strlen($run[0][0]), 'length' => mb_strlen($run[0][0]), 'letter' => $run[1][0]];
            }
        }

        return $runs;
    }
}
