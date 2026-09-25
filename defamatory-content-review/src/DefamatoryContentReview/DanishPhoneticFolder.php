<?php

namespace DefamatoryContentReview;

/**
 * Aproximación fonética para el danés.
 *
 * Igual que en el folder alemán, "æ", "ø" y "å" tienen una grafía
 * alternativa real y consolidada, no una aproximación inventada: "å" es
 * oficialmente "aa" (así se escribía antes de la reforma de 1948, y sigue
 * siendo válida y común en apellidos y topónimos — "Kierkegaard" nunca pasó
 * a escribirse con "å"), y "æ"/"ø" se expanden a "ae"/"oe" en el mismo
 * contexto (pasaportes, sistemas sin esos caracteres).
 *
 * No se toca la "d" suave (muda tras vocal en muchas palabras, p. ej.
 * "mad") ni el "stød" (golpe de glotis): ninguno de los dos tiene una
 * grafía alternativa sistemática — intentar plegarlos sería adivinar, no
 * normalizar.
 */
class DanishPhoneticFolder extends AbstractPhoneticFolder
{
    /** @return array<string,string> */
    protected static function getAccents(): array { return CommonPhoneticAccents::NORDIC_VOWELS; }

    protected static function applyLanguageRules(string $text): string { return $text; }
}
