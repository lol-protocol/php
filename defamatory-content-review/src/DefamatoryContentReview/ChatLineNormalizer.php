<?php

namespace DefamatoryContentReview;

/**
 * Preparación de una línea de chat antes de buscar en ella: lo que el
 * diccionario de nombres no necesita («p u t a» no es un nombre) pero un
 * mensaje sí. Colaborador interno de ChatLineReviewer.
 */
final class ChatLineNormalizer
{
    /** Racha de 3 o más letras sueltas separadas por espacios: «p u t a». */
    private const SPACED_LETTERS = '/(?<![\p{L}\p{N}])(?:\p{L}[ \t]+){2,}\p{L}(?![\p{L}\p{N}])/u';
    /** Letras sueltas que también son palabras: la «a» de «vamos a f o l l a r» no es de «follar». */
    private const CONNECTORS = ['a', 'e', 'i', 'o', 'u', 'y'];

    /**
     * Las lecturas de la línea con las letras sueltas unidas. La primera une
     * cada racha entera; las demás —sólo si la línea tiene alguna racha—
     * dejan aparte hasta dos conectoras por extremo, porque «vamos a f o l l a
     * r» no es «afollar» y «p u t a y m…» no es «putay». Cada lectura trae
     * `unida => original` (para devolverle a `found` el texto que escribió la
     * persona).
     *
     * @return array<int,array{0:string,1:array<string,string>}>
     */
    public static function variants(string $line): array
    {
        if (!preg_match(self::SPACED_LETTERS, $line)) {
            return [[$line, []]];
        }
        $variants = [];
        for ($lead = 0; $lead <= 2; $lead++) {
            for ($tail = 0; $tail <= 2; $tail++) {
                $variant = self::joinSpacedLetters($line, $lead, $tail);
                $variants[$variant[0]] ??= $variant;
            }
        }

        return array_values($variants);
    }

    /** @return array{0:string,1:array<string,string>} */
    private static function joinSpacedLetters(string $line, int $maxLead, int $maxTail): array
    {
        $joined = [];
        $text = preg_replace_callback(self::SPACED_LETTERS, function (array $run) use (&$joined, $maxLead, $maxTail): string {
            $parts = preg_split('/([ \t]+)/', $run[0], -1, PREG_SPLIT_DELIM_CAPTURE); // letra, espacio, letra…
            $lead = $tail = '';
            for ($i = 0; $i < $maxLead && count($parts) > 5 && self::isConnector($parts[0]); $i++) {
                $lead .= array_shift($parts) . array_shift($parts);
            }
            for ($i = 0; $i < $maxTail && count($parts) > 5 && self::isConnector(end($parts)); $i++) {
                $tail = implode('', array_splice($parts, -2)) . $tail;
            }
            $core = implode('', $parts);
            $word = preg_replace('/[ \t]+/', '', $core);
            $joined[$word] ??= $core;

            return $lead . $word . $tail;
        }, $line);

        return [$text ?? $line, $joined];
    }

    private static function isConnector(string $letter): bool
    {
        return in_array(mb_strtolower($letter), self::CONNECTORS, true);
    }

    /**
     * Minúsculas, sin tildes, leet resuelto y cada carácter que no sea letra
     * ni dígito convertido en un espacio. Un carácter por carácter, así la
     * posición de un hallazgo vale también en la línea original (salvo si
     * había «ß», «æ» o «œ», que el plegado alarga).
     */
    public static function foldForPatterns(string $line): string
    {
        $folded = Leetspeak::unleet(AccentFolding::fold(mb_strtolower($line)));

        return preg_replace('/[^a-z0-9]/u', ' ', $folded) ?? $folded;
    }
}
