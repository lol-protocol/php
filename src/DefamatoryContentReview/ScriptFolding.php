<?php

namespace DefamatoryContentReview;

/**
 * Variantes ortográficas estándar de scripts no latinos, para que la misma
 * palabra escrita de dos formas habituales llegue a la misma clave. Sólo
 * normalizaciones que los propios hablantes tratan como equivalentes (y que
 * los buscadores de cada idioma ya aplican), no aproximaciones fonéticas:
 *
 * - Griego: las mayúsculas se escriben sin tonos por convención ("ΜΑΛΑΚΑΣ"),
 *   y minusculizarlas da sigma medial "σ" donde el diccionario tiene la
 *   final "ς". Sin esto, un apellido en mayúsculas —lo normal en registros
 *   genealógicos— no coincidía nunca.
 * - Cirílico: "ё" se escribe "е" en casi todo el texto ruso corriente.
 * - Árabe: kashida (ـ, alarga el trazo sin cambiar la palabra), harakat
 *   (vocales opcionales), alef con/sin hamza, "ى"/"ي" y "ة"/"ه" final —
 *   la normalización estándar de búsqueda en árabe.
 * - Hebreo: niqqud y signos de cantilación (vocales opcionales).
 */
final class ScriptFolding
{
    private const MAP = [
        // griego
        'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω',
        'ΐ' => 'ι', 'ΰ' => 'υ', 'ϊ' => 'ι', 'ϋ' => 'υ', 'ς' => 'σ',
        // cirílico
        'ё' => 'е',
        // árabe
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ة' => 'ه',
    ];

    /** kashida, harakat y superíndice alef (árabe); niqqud y cantilación (hebreo) */
    private const STRIP = '/[\x{0640}\x{064B}-\x{065F}\x{0670}\x{0591}-\x{05BD}\x{05BF}-\x{05C7}]/u';

    /** Espera texto ya en minúsculas. */
    public static function fold(string $text): string
    {
        return strtr(preg_replace(self::STRIP, '', $text), self::MAP);
    }
}
