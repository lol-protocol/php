<?php

namespace DefamatoryContentReview\Chat\Inflection;

/**
 * Alternancia de consonantes del finés (kk → k, tt → t, p → v…): la raíz fuerte («idiootti») pasa a débil
 * («idiootin») delante de una sílaba cerrada. No se sabe por la forma si una palabra alterna («paska» no,
 * «mulkku» sí), así que se dan las dos raíces. Colaborador de FinnishInflection.
 */
final class FinnishGradation
{
    private const WEAK = ['kk' => 'k', 'pp' => 'p', 'tt' => 't', 'nk' => 'ng', 'mp' => 'mm', 'lt' => 'll', 'nt' => 'nn', 'rt' => 'rr', 'rp' => 'rv', 'p' => 'v', 't' => 'd'];

    /** @return list<string> la raíz (sin la vocal final) tal cual y, si alterna, con la consonante débil */
    public static function stems(string $stem): array
    {
        if (preg_match('/^(.*[aeiouyäö])(kk|pp|tt|nk|mp|lt|nt|rt|rp|p|t)$/u', $stem, $m) !== 1) {
            return [$stem];
        }

        return [$stem, $m[1] . self::WEAK[$m[2]]];
    }
}
