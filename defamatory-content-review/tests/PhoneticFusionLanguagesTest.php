<?php

namespace Tests;

use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\TestCase;

/**
 * Fusión nombre+apellido de cada idioma con folder fonético propio. Las
 * reglas de plegado en sí viven en PhoneticFoldingRulesTest, y el español
 * aparte en SpanishPhoneticFolderRulesTest y PhoneticFusionSpanishJokesTest
 * porque sus casos comprueban más cosas.
 */
class PhoneticFusionLanguagesTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    /**
     * Nombre y apellido, ninguno ofensivo por separado, que al leerse
     * seguidos forman el término. Casos construidos para probar el
     * mecanismo, no chistes documentados.
     */
    public static function fusionAcrossBoundary(): array
    {
        return [
            'ces' => ['ces', 'Alpr', 'Ase', 'prase'],
            'dan' => ['dan', 'Alsv', 'In', 'svin'],
            'nld' => ['nld', 'Alzw', 'Ein', 'zwijn'],
            'fin' => ['fin', 'Alsi', 'Ka', 'sika'],
            'fra' => ['fra', 'Aubi', 'Te', 'bite'],
            'deu' => ['deu', 'Konradu', 'Mm', 'dumm'],
            'hun' => ['hun', 'Aldi', 'Sznó', 'disznó'],
            'ind' => ['ind', 'Alba', 'Bi', 'babi'],
            'ita' => ['ita', 'Roma', 'Gro', 'magro'],
            'nor' => ['nor', 'Alsv', 'In', 'svin'],
            'pol' => ['pol', 'Alsw', 'Inia', 'świnia'],
            'por' => ['por', 'Isabu', 'Rro', 'burro'],
            'ron' => ['ron', 'Alpo', 'Rc', 'porc'],
            'slk' => ['slk', 'Alpr', 'Asa', 'prasa'],
            'swe' => ['swe', 'Alsv', 'In', 'svin'],
            'tur' => ['tur', 'Aldo', 'Muz', 'domuz'],
        ];
    }

    public static function commonNames(): array
    {
        $names = [
            'ces' => [['Jan', 'Novák'], ['Petr', 'Svoboda'], ['Josef', 'Dvořák']],
            'dan' => [['Anders', 'Hansen'], ['Mette', 'Jensen'], ['Lars', 'Nielsen']],
            'nld' => [['Jan', 'de Vries'], ['Anna', 'Bakker'], ['Willem', 'Jansen']],
            'fin' => [['Matti', 'Virtanen'], ['Anna', 'Korhonen'], ['Juha', 'Mäkinen']],
            'fra' => [['Jean', 'Dupont'], ['Marie', 'Martin'], ['Luciano', 'Fernandez']],
            'deu' => [['Hans', 'Müller'], ['Anna', 'Schmidt'], ['Wolfgang', 'Meyer'], ['Ingrid', 'Wagner'], ['Mariano', 'Schulz']],
            'hun' => [['János', 'Kovács'], ['Katalin', 'Nagy'], ['Béla', 'Szabó']],
            'ind' => [['Budi', 'Santoso'], ['Siti', 'Rahayu'], ['Agus', 'Wijaya']],
            'ita' => [['Giulia', 'Ferrari'], ['Marco', 'Rossi'], ['Luciano', 'Pavarotti']],
            'nor' => [['Ole', 'Hansen'], ['Kari', 'Olsen'], ['Erik', 'Johansen']],
            'pol' => [['Jan', 'Kowalski'], ['Anna', 'Nowak'], ['Piotr', 'Wiśniewski']],
            'por' => [['Mariana', 'Silva'], ['João', 'Santos'], ['Mariano', 'Souza']],
            'ron' => [['Ion', 'Popescu'], ['Maria', 'Ionescu'], ['Andrei', 'Stan']],
            'slk' => [['Ján', 'Kováč'], ['Peter', 'Horváth'], ['Mária', 'Nováková']],
            'swe' => [['Anders', 'Andersson'], ['Karin', 'Johansson'], ['Lars', 'Eriksson']],
            'tur' => [['Mehmet', 'Yılmaz'], ['Ayşe', 'Kaya'], ['Ahmet', 'Demir']],
        ];

        $cases = [];
        foreach ($names as $language => $pairs) {
            foreach ($pairs as [$first, $last]) {
                $cases["{$language}: {$first} {$last}"] = [$language, $first, $last];
            }
        }

        return $cases;
    }

    /** @dataProvider fusionAcrossBoundary */
    public function testFusionCrossesBoundary(string $language, string $first, string $last, string $term): void
    {
        $result = DefamatoryContentReviewer::create(self::CONFIG_DIR, $language)->validateFullName($first, $last);

        $this->assertFalse($result->isValid());
        $this->assertSame([$term], array_column($result->getPhoneticFusionTerms(), 'term'));
    }

    /** @dataProvider commonNames */
    public function testCommonNamesAreNotFlagged(string $language, string $first, string $last): void
    {
        $this->assertTrue(
            DefamatoryContentReviewer::create(self::CONFIG_DIR, $language)->validateFullName($first, $last)->isValid(),
            "'{$first} {$last}' no debería marcarse."
        );
    }
}
