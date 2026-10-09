<?php

namespace DefamatoryContentReview\Chat;

/**
 * Letras sueltas como evasión: «p u t a», «vamos a f o l l a r». Une cada
 * racha en una palabra para que el diccionario la vea. Colaborador interno
 * de ChatLineNormalizer.
 */
final class SpacedLetters
{
    /** Racha de 3 o más letras sueltas separadas por espacios: «p u t a». */
    private const RUN = '/(?<![\p{L}\p{N}])(?:\p{L}[ \t]+){2,}\p{L}(?![\p{L}\p{N}])/u';
    /** Letras sueltas que también son palabras: la «a» de «vamos a f o l l a r» no es de «follar». */
    private const CONNECTORS = ['a', 'e', 'i', 'o', 'u', 'y'];

    /**
     * Las lecturas con las rachas unidas. La primera une cada racha entera;
     * las demás —sólo si la línea tiene alguna racha— dejan aparte hasta dos
     * conectoras por extremo, porque «vamos a f o l l a r» no es «afollar» y
     * «p u t a y m…» no es «putay». Cada lectura trae `unida => original`
     * (para devolverle a `found` el texto que escribió la persona).
     *
     * @return array<int,array{0:string,1:array<string,string>}>
     */
    public static function variants(string $line): array
    {
        if (!preg_match(self::RUN, $line)) {
            return [[$line, []]];
        }
        $variants = [];
        for ($lead = 0; $lead <= 2; $lead++) {
            for ($tail = 0; $tail <= 2; $tail++) {
                $variant = self::join($line, $lead, $tail);
                $variants[$variant[0]] ??= $variant;
            }
        }

        return array_values($variants);
    }

    /** @return array{0:string,1:array<string,string>} */
    private static function join(string $line, int $maxLead, int $maxTail): array
    {
        $joined = [];
        $text = preg_replace_callback(self::RUN, function (array $run) use (&$joined, $maxLead, $maxTail): string {
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
}
