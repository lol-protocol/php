<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Las rachas de 3 o más letras se leen en todos los idiomas; reducir las dobles,
 * sólo donde `meta.collapseRepeats => true` (ver ChatTopics).
 */
class RepeatedLettersByLanguageTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string,string}> idioma, línea, decisión */
    public static function lines(): array
    {
        return [
            'italiano alargado' => ['ita', 'sei un cazzzzo', 'reject'],
            'italiano: la doble es de la palabra' => ['ita', 'buon anno a tutti', 'approve'],
            'italiano: penne' => ['ita', 'penne al pomodoro', 'approve'],
            'alemán alargado' => ['deu', 'du Arschloooch', 'reject'],
            'alemán: tres f legítimas' => ['deu', 'die Schifffahrt', 'approve'],
            'alemán: Pollack es un pez' => ['deu', 'Pollack mit Kartoffeln', 'approve'],
            'ruso alargado' => ['rus', 'сууука', 'reject'],
            'portugués: doble reducida va a revisión' => ['por', 'porraa', 'review'],
            'indonesio: sesaat es «un momento»' => ['ind', 'tunggu sesaat', 'approve'],
            'árabe: للعين es «para el ojo»' => ['ara', 'قطرة للعين', 'approve'],
        ];
    }

    #[DataProvider('lines')]
    public function testDecision(string $language, string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), $line);
    }
}
