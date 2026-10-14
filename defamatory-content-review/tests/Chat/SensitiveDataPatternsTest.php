<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Orientación sexual, filiación, suicidio, violencia en la familia e
 * internación psiquiátrica de un tercero: datos sensibles o vida privada
 * aunque sean ciertos. Nunca bloquean: van a revisión.
 */
class SensitiveDataPatternsTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string,string}> idioma, línea, etiqueta */
    public static function sensitive(): array
    {
        return [
            'orientación' => ['spa', 'mi tío era homosexual', 'dato sensible (orientación sexual)'],
            'salir del clóset' => ['spa', 'Fulano salió del clóset', 'dato sensible (orientación sexual)'],
            'hijo ilegítimo' => ['spa', 'Fulano es hijo ilegítimo', 'vida privada (filiación)'],
            'adopción' => ['spa', 'la Juana fue adoptada', 'vida privada (filiación)'],
            'paternidad' => ['spa', 'Pedro no es hijo de Juan', 'vida privada (filiación)'],
            'suicidio' => ['spa', 'su hermano se suicidó', 'dato sensible (suicidio)'],
            'violencia en la familia' => ['spa', 'le pega a su señora', 'acusación de delito'],
            'psiquiátrico' => ['spa', 'estuvo internada en el psiquiátrico', 'dato sensible (salud)'],
            'enfermedad' => ['spa', 'Fulano tiene cáncer', 'dato sensible (salud)'],
            'aborto' => ['spa', 'Fulana se hizo un aborto', 'dato sensible (salud)'],
            'una familia entera' => ['spa', 'los Pérez son unos ladrones', 'acusación de delito'],
            'familia de' => ['spa', 'esa familia es de delincuentes', 'acusación de delito'],
            'antecedentes' => ['spa', 'Fulano tiene antecedentes penales', 'acusación de delito'],
            'amante' => ['spa', 'Fulano se acuesta con su secretaria', 'vida privada'],
            'deudas' => ['spa', 'Fulano está en DICOM', 'vida privada (deudas)'],
            'moroso' => ['spa', 'Fulano no paga la pensión', 'vida privada (deudas)'],
            'a whole family' => ['eng', 'the Smiths are all crooks', 'accusation of a crime'],
            'abortion' => ['eng', 'she had an abortion', 'sensitive data (health)'],
            'orientation' => ['eng', "he's gay", 'sensitive data (sexual orientation)'],
            'parentage' => ['eng', "he isn't her real father", 'private life (parentage)'],
            'suicide' => ['eng', 'he killed himself', 'sensitive data (suicide)'],
            'domestic violence' => ['eng', 'he beats his wife', 'accusation of a crime'],
        ];
    }

    #[DataProvider('sensitive')]
    public function testSensitiveDataGoesToReview(string $language, string $line, string $label): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line);

        $this->assertSame('review', $result->getDecision(), $line);
        $this->assertContains($label, array_column($result->getMatches(), 'original'), $line);
    }

    /** @return array<string,array{string,string}> */
    public static function ordinaryLines(): array
    {
        return [
            'accidente' => ['spa', 'se mató en un accidente de tránsito'],
            'pegar algo' => ['spa', 'la pega a su casa'],
            'música' => ['spa', 'me gusta el pop gay de los ochenta'],
            'récord' => ['eng', 'I beat my record'],
            'murió de' => ['spa', 'murió de cáncer en 1950'],
            'antecedentes de familia' => ['spa', 'tiene antecedentes de la familia en Italia'],
            'aborto como tema' => ['spa', 'el aborto es un tema difícil'],
            'acostarse' => ['spa', 'se acuesta temprano'],
            'died of' => ['eng', 'he died of cancer'],
        ];
    }

    #[DataProvider('ordinaryLines')]
    public function testOrdinaryLinesAreApproved(string $language, string $line): void
    {
        $this->assertSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), $line);
    }
}
