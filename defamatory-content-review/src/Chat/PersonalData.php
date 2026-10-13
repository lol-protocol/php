<?php

namespace DefamatoryContentReview\Chat;

/**
 * Datos que identifican o ubican a una persona: un RUT válido, un correo, un
 * teléfono con prefijo internacional o anunciado («fono», «llámalo al»…) y
 * una dirección anunciada («vive en la calle…», «su dirección es…»).
 * Publicarlos sobre un tercero expone su vida privada aunque nadie lo insulte
 * (ver PRIVACIDAD-Y-HONRA.md): van a revisión, nunca se bloquean solos. No
 * dependen del idioma. Colaborador interno de ChatLineReviewer.
 */
final class PersonalData
{
    private const KINDS = [
        'RUT' => '/(?<![\w.])\d{1,2}\.?\d{3}\.?\d{3}-[\dkK](?!\w)/u',
        'correo' => '/[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}\-]+(?:\.[\p{L}\p{N}\-]+)*\.\p{L}{2,}/u',
        'teléfono' => '/(?<![\w+])\+\d(?:[\s\-.()]*\d){7,14}(?!\d)|\b(?:fono|tel[eé]fono|tel|celular|cel|whatsapp|wsp|wasap|ll[aá]ma(?:lo|la|le)\s+al|phone|call\s+(?:him|her|them)\s+at)\b[\s:.\-]*\+?\d(?:[\s\-.()]*\d){6,14}(?!\d)/iu',
        'dirección' => '/\b(?:viven?|viv[ií]a|lives|lived)\s+(?:en|at|on)\s+(?:la\s+|el\s+)?(?:calle|avenida|av\.?|pasaje|psje\.?|villa|poblaci[oó]n|block|depto\.?|departamento)\b|\b(?:lives|lived)\s+at\s+\d+\s+\p{L}|\b(?:su|la)\s+direcci[oó]n\s+es\b|\b(?:his|her|their)\s+(?:home\s+)?address\s+is\b/iu',
    ];

    /** @return array<int,array<string,mixed>> */
    public static function find(string $line): array
    {
        $matches = [];
        foreach (self::KINDS as $kind => $pattern) {
            preg_match_all($pattern, $line, $found);
            foreach ($found[0] as $text) {
                if ($kind !== 'RUT' || self::validRut($text)) {
                    $matches[] = [
                        'found' => $text, 'original' => 'dato personal (' . $kind . ')', 'category' => 'datos personales',
                        'riskType' => 'privado', 'contentType' => 'privado', 'severity' => 'medium',
                        'nameCollision' => false, 'ambiguous' => false, 'targeted' => null,
                    ];
                }
            }
        }

        return $matches;
    }

    /** Dígito verificador (módulo 11): «12.345.678-5» es un RUT, «1.234.567-0» cualquiera no. */
    private static function validRut(string $rut): bool
    {
        [$body, $check] = explode('-', str_replace('.', '', strtoupper($rut)));
        $sum = 0;
        foreach (array_reverse(str_split($body)) as $i => $digit) {
            $sum += (int) $digit * (2 + $i % 6);
        }
        $expected = 11 - $sum % 11;

        return $check === match ($expected) { 11 => '0', 10 => 'K', default => (string) $expected };
    }
}
