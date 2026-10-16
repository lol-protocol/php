<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Acusaciones, datos sensibles y vida privada en portugués, francés, italiano
 * y alemán, como en español e inglés: siempre a revisión, nunca bloqueo.
 */
class AccusationPatternsEuropeanTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string,string}> idioma, línea, etiqueta */
    public static function accusations(): array
    {
        return [
            'por: delito' => ['por', 'Fulano roubou dinheiro da empresa', 'acusação de crime'],
            'por: condena' => ['por', 'Fulano foi condenado por fraude', 'acusação de crime'],
            'por: familia' => ['por', 'os Silva são todos ladrões', 'acusação de crime'],
            'por: salud' => ['por', 'Fulano tem aids', 'dado sensível (saúde)'],
            'por: vida privada' => ['por', 'Fulano trai a mulher', 'vida privada'],
            'fra: delito' => ['fra', "Fulano a volé de l'argent", 'accusation de délit'],
            'fra: condena' => ['fra', 'Fulano a été condamné', 'accusation de délit'],
            'fra: salud' => ['fra', 'Fulano a le sida', 'donnée sensible (santé)'],
            'fra: orientación' => ['fra', 'Fulano est gay', 'donnée sensible (orientation sexuelle)'],
            'ita: delito' => ['ita', 'Fulano ha rubato soldi', 'accusa di reato'],
            'ita: condena' => ['ita', 'Fulano è stato condannato', 'accusa di reato'],
            'ita: vida privada' => ['ita', 'Fulano tradisce la moglie', 'vita privata'],
            'deu: delito' => ['deu', 'Fulano hat Geld gestohlen', 'vorwurf einer straftat'],
            'deu: condena' => ['deu', 'Fulano wurde verurteilt', 'vorwurf einer straftat'],
            'deu: salud' => ['deu', 'Fulano hat Krebs', 'sensible daten (gesundheit)'],
            'deu: vida privada' => ['deu', 'Fulano betrügt seine Frau', 'privatleben'],
        ];
    }

    #[DataProvider('accusations')]
    public function testAccusationGoesToReview(string $language, string $line, string $label): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line);

        $this->assertSame('review', $result->getDecision(), $line);
        $this->assertContains($label, array_column($result->getMatches(), 'original'), $line);
    }

    /** @return array<string,array{string,string}> */
    public static function ordinaryLines(): array
    {
        return [
            'por: conjunción' => ['por', 'pão e café'],
            'por: saudade' => ['por', 'ele matou a saudade'],
            'fra: volar' => ['fra', "l'oiseau a volé haut"],
            'fra: preposición' => ['fra', 'je vais à la plage'],
            'ita: conjunción' => ['ita', 'il gatto e il cane'],
            'ita: fue bonito' => ['ita', 'è stato bello'],
            'deu: razón' => ['deu', 'er hat Recht'],
        ];
    }

    #[DataProvider('ordinaryLines')]
    public function testOrdinaryLinesAreApproved(string $language, string $line): void
    {
        $this->assertSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), $line);
    }
}
