<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Difamar no es sólo insultar: es imputar hechos. «Fulano estafó a mi familia»
 * no tiene ninguna palabrota y es lo que más daño hace en una plataforma de
 * genealogía (ver docs/ORIGEN.md). Estas frases van a revisión humana, nunca
 * se bloquean solas: pueden ser ciertas. Los nombres son inventados.
 */
class AccusationPatternsTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string,string}> idioma, línea, etiqueta */
    public static function accusations(): array
    {
        return [
            'estafa con verbo' => ['spa', 'Fulano Tal estafó a mi familia', 'acusación de delito'],
            'robo con objeto' => ['spa', 'Mengano robó la plata de la iglesia', 'acusación de delito'],
            'abuso de menores' => ['spa', 'Zutano abusa de niños', 'acusación de delito'],
            'condena' => ['spa', 'Perengano fue condenado por violación', 'acusación de delito'],
            'cárcel' => ['spa', 'Fulano estuvo en la cárcel', 'acusación de delito'],
            'es narco' => ['spa', 'Mengano es narco', 'acusación de delito'],
            'salud' => ['spa', 'Zutano tiene sida', 'dato sensible (salud)'],
            'infidelidad' => ['spa', 'Fulano le pone los cuernos a su señora', 'vida privada'],
            'funa' => ['spa', 'funen a ese tipo', 'funa o escrache'],
            'stole' => ['eng', 'John Doe stole money from the church', 'accusation of a crime'],
            'convicted' => ['eng', 'He was convicted of fraud', 'accusation of a crime'],
            'health' => ['eng', 'Jane Roe has HIV', 'sensitive data (health)'],
            'affair' => ['eng', 'he is cheating on his wife', 'private life'],
        ];
    }

    #[DataProvider('accusations')]
    public function testAccusationGoesToHumanReview(string $language, string $line, string $label): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line);

        $this->assertSame('review', $result->getDecision(), $line);
        $this->assertContains('difamatorio', $result->getContentTypes());
        $this->assertContains($label, array_column($result->getMatches(), 'original'));
    }

    /** @return array<string,array{string,string}> */
    public static function neutralLines(): array
    {
        return [
            'nacimiento' => ['spa', 'Fulano nació en Concepción en 1950'],
            'causa de muerte' => ['spa', 'Mi bisabuelo murió de cáncer en 1950'],
            'el robo como sustantivo' => ['spa', 'el robo del banco fue en 1920'],
            'la estafa como sustantivo' => ['spa', 'la estafa a los jubilados salió en la tele'],
            'oficio' => ['spa', 'Mi abuelo fue juez en el caso de estafa'],
            'hospital' => ['spa', 'El hospital trata pacientes con sida'],
            'stole a glance' => ['eng', 'I stole a glance at her'],
            'stole the show' => ['eng', 'she stole the show'],
        ];
    }

    #[DataProvider('neutralLines')]
    public function testOrdinaryLinesPass(string $language, string $line): void
    {
        $this->assertSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), $line);
    }
}
