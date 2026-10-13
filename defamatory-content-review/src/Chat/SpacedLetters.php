<?php

namespace DefamatoryContentReview\Chat;

/**
 * Letras sueltas como evasión: «p u t a», «vamos a f o l l a r». Une cada
 * racha en una palabra para que el diccionario la vea. Colaborador interno
 * de ChatLineNormalizer.
 */
final class SpacedLetters
{
    /**
     * Racha de 3 o más caracteres sueltos separados por espacios o por símbolos con
     * espacios alrededor: «p u t a», «p - u - t - a», «N. A. Z. I», «N 4 Z 1». Un
     * carácter es una letra, un dígito o un símbolo de leet («@», «$», «!», «|»).
     */
    private const RUN = '/(?<![\p{L}\p{M}\p{N}])(?:[\p{L}\p{N}@$!|]\p{M}*' . self::SEP . '){2,}[\p{L}\p{N}@$!|]\p{M}*(?![\p{L}\p{M}\p{N}])/u';
    /** Entre dos caracteres sueltos: espacios o saltos de línea, o símbolos de separación con algún espacio. */
    private const SEP = '(?:\s*[\-–—_.·~\/+=•…:;,]\s*(?=\S)|\s+)';
    private const SEPARATOR = '(?:\s*[\-–—_.·~\/+=•…:;,]\s*|\s+)';
    private const SPLIT = '/' . self::SEPARATOR . '/u';
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

    /** @return array<int,array<int,string>> las letras de cada racha de la línea («p u t a» → p, u, t, a) */
    public static function runs(string $line): array
    {
        preg_match_all(self::RUN, $line, $runs);

        $runs = array_filter($runs[0], fn(string $run): bool => preg_match('/\p{L}/u', $run) === 1);

        return array_values(array_map(fn(string $run): array => preg_split(self::SPLIT, $run) ?: [], $runs));
    }

    /** @return array{0:string,1:array<string,string>} */
    private static function join(string $line, int $maxLead, int $maxTail): array
    {
        $joined = [];
        $text = preg_replace_callback(self::RUN, function (array $run) use (&$joined, $maxLead, $maxTail): string {
            if (preg_match('/\p{L}/u', $run[0]) !== 1) {
                return $run[0]; // «1 2 3 4» no es una palabra camuflada
            }
            $parts = preg_split('/(' . self::SEPARATOR . ')/u', $run[0], -1, PREG_SPLIT_DELIM_CAPTURE); // letra, separador, letra…
            $lead = $tail = '';
            for ($i = 0; $i < $maxLead && count($parts) > 5 && self::isConnector($parts[0]); $i++) {
                $lead .= array_shift($parts) . array_shift($parts);
            }
            for ($i = 0; $i < $maxTail && count($parts) > 5 && self::isConnector(end($parts)); $i++) {
                $tail = implode('', array_splice($parts, -2)) . $tail;
            }
            $core = implode('', $parts);
            $word = preg_replace(self::SPLIT, '', $core);
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
