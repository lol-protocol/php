<?php

namespace DefamatoryContentReview\Http;

use DefamatoryContentReview\Chat\ChatLineResult;
use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\CombinedReview;

/**
 * Qué encuentra el filtro en un texto de cualquier largo, revisado en varios idiomas a la vez y con una etiqueta
 * por hallazgo, la misma para quien lo muestre: «acusación de delito», «dato personal (RUT)» o «insulto».
 * Lo usan el filtro de pedidos de retiro (url-routing) y el de notas (sistema-nuevo), que antes repetían esto.
 */
final class TextMarks
{
    /** Categorías cuyo 'original' ya dice qué se encontró; en las demás es el insulto mismo, que no se repite. */
    private const DESCRIPTIVE = ['patterns', 'datos personales'];

    /** @var array<string,ChatLineReviewer> Un revisor por carpeta e idioma: crearlo lee y compila los diccionarios. */
    private static array $reviewers = [];

    /**
     * @param list<string> $languages p. ej. ['spa', 'eng']
     * @return array{decision: string, marks: list<string>} decision: approve | review | reject; marks, sin repetir
     */
    public static function of(string $text, string $configDir, array $languages): array
    {
        $result = LongText::review($text, static fn(string $part): ChatLineResult => CombinedReview::of(
            $part,
            array_map(static fn(string $language): ChatLineResult => self::reviewer($configDir, $language)->review($part), $languages)
        ));
        $marks = array_map(
            static fn(array $match): string => in_array($match['category'] ?? null, self::DESCRIPTIVE, true) ? (string) $match['original'] : 'insulto',
            $result->getMatches()
        );

        return ['decision' => $result->getDecision(), 'marks' => array_values(array_unique($marks))];
    }

    private static function reviewer(string $configDir, string $language): ChatLineReviewer
    {
        return self::$reviewers["{$configDir}|{$language}"] ??= ChatLineReviewer::create($configDir, $language);
    }
}
