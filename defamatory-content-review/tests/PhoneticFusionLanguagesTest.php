<?php

namespace Tests;

use DefamatoryContentReview\CzechPhoneticFolder;
use DefamatoryContentReview\DanishPhoneticFolder;
use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\DutchPhoneticFolder;
use DefamatoryContentReview\FinnishPhoneticFolder;
use DefamatoryContentReview\FrenchPhoneticFolder;
use DefamatoryContentReview\GermanPhoneticFolder;
use DefamatoryContentReview\HungarianPhoneticFolder;
use DefamatoryContentReview\IndonesianPhoneticFolder;
use DefamatoryContentReview\ItalianPhoneticFolder;
use DefamatoryContentReview\NorwegianPhoneticFolder;
use DefamatoryContentReview\PolishPhoneticFolder;
use DefamatoryContentReview\PortuguesePhoneticFolder;
use DefamatoryContentReview\RomanianPhoneticFolder;
use DefamatoryContentReview\SlovakPhoneticFolder;
use DefamatoryContentReview\SwedishPhoneticFolder;
use DefamatoryContentReview\TurkishPhoneticFolder;
use PHPUnit\Framework\TestCase;

/**
 * Reglas de plegado y fusión nombre+apellido de cada idioma con folder
 * fonético propio. El español vive en SpanishPhoneticFolderRulesTest y
 * PhoneticFusionSpanishJokesTest porque sus casos comprueban más cosas.
 */
class PhoneticFusionLanguagesTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../config';

    /** Las dos grafías deben plegarse a la misma forma. */
    public static function equivalentSpellings(): array
    {
        return [
            'ces: y suena como i' => [CzechPhoneticFolder::class, 'mýlit', 'mílit'],
            'dan: å equivale a su grafía histórica aa' => [DanishPhoneticFolder::class, 'Kierkegård', 'Kierkegaard'],
            'nld: ei y ij suenan igual' => [DutchPhoneticFolder::class, 'wij', 'wei'],
            'nld: au y ou suenan igual' => [DutchPhoneticFolder::class, 'koud', 'kaud'],
            'fin: la diéresis cae a la vocal base' => [FinnishPhoneticFolder::class, 'Väinö', 'Vaino'],
            'fra: ç suena /s/' => [FrenchPhoneticFolder::class, 'garçon', 'garson'],
            'fra: ph suena /f/' => [FrenchPhoneticFolder::class, 'phare', 'fare'],
            'fra: la h es muda' => [FrenchPhoneticFolder::class, 'homme', 'omme'],
            'deu: w suena /v/' => [GermanPhoneticFolder::class, 'Wagen', 'Vagen'],
            'hun: ly suena como j' => [HungarianPhoneticFolder::class, 'Bajor', 'Balyor'],
            'hun: el doble acento sólo marca longitud' => [HungarianPhoneticFolder::class, 'győr', 'györ'],
            'ind: oe de la ortografía Van Ophuijsen es u' => [IndonesianPhoneticFolder::class, 'Soekarno', 'Sukarno'],
            'ind: la j antigua es y' => [IndonesianPhoneticFolder::class, 'Jusuf', 'Yusuf'],
            'ind: la ch antigua es kh' => [IndonesianPhoneticFolder::class, 'Achmad', 'Akhmad'],
            'nor: å equivale a su grafía histórica aa' => [NorwegianPhoneticFolder::class, 'Åberg', 'Aaberg'],
            'pol: ó suena como u' => [PolishPhoneticFolder::class, 'mój', 'muj'],
            'pol: rz suena como ż' => [PolishPhoneticFolder::class, 'morze', 'może'],
            'pol: ch suena como h' => [PolishPhoneticFolder::class, 'chleb', 'hleb'],
            // Confusión real s/z, sobre todo en el portugués brasileño.
            'por: cozer y coser suenan casi igual' => [PortuguesePhoneticFolder::class, 'cozer', 'coser'],
            'ron: â e î son la misma vocal' => [RomanianPhoneticFolder::class, 'câine', 'cîine'],
            'ron: ş con cedilla y ș con coma son la misma letra' => [RomanianPhoneticFolder::class, 'ştefan', 'ștefan'],
            'ron: ţ con cedilla y ț con coma son la misma letra' => [RomanianPhoneticFolder::class, 'rațiune', 'raţiune'],
            'slk: y suena como i' => [SlovakPhoneticFolder::class, 'bystrý', 'bistrí'],
            'swe: å equivale a su grafía histórica aa' => [SwedishPhoneticFolder::class, 'Åberg', 'Aaberg'],
            'tur: la ğ suave se pliega a g' => [TurkishPhoneticFolder::class, 'Erdoğan', 'Erdogan'],
        ];
    }

    /** Sonidos distintos que el plegado no debe confundir. */
    public static function distinctSpellings(): array
    {
        return [
            // "chat" (gato) no debe sonar como "cat" con una c(a) suelta.
            'fra: el dígrafo ch se protege' => [FrenchPhoneticFolder::class, 'chat', 'cat'],
            'ita: b y v no se confunden' => [ItalianPhoneticFolder::class, 'vino', 'bino'],
            // El italiano no tiene la confusión s/z del español y el portugués.
            'ita: s y z no se confunden' => [ItalianPhoneticFolder::class, 'mezzo', 'messo'],
            // A diferencia del español, en portugués b/v son sonidos distintos.
            'por: b y v no se confunden' => [PortuguesePhoneticFolder::class, 'vaca', 'baca'],
            // Sin proteger lh, "filho" (hijo) colisionaría con "filo".
            'por: el dígrafo lh se protege' => [PortuguesePhoneticFolder::class, 'filho', 'filo'],
        ];
    }

    /** Forma plegada exacta. */
    public static function exactFolds(): array
    {
        return [
            'deu: ü se expande a ue' => [GermanPhoneticFolder::class, 'Müller', 'mueller'],
            'deu: ß se expande a ss' => [GermanPhoneticFolder::class, 'Straße', 'strasse'],
            // "Vater" suena /f/ y "Vase" /v/: sin diccionario de origen no se
            // puede distinguir un caso del otro, así que la v no se pliega.
            'deu: la v nativa no se toca' => [GermanPhoneticFolder::class, 'Vater', 'vater'],
            'ita: sólo quita acentos' => [ItalianPhoneticFolder::class, 'perché', 'perche'],
            'ita: sólo quita espacios y guiones' => [ItalianPhoneticFolder::class, 'Rossi-Bianchi', 'rossibianchi'],
            'por: ç suena siempre /s/' => [PortuguesePhoneticFolder::class, 'caça', 'casa'],
        ];
    }

    /**
     * Nombre y apellido, ninguno ofensivo por separado, que al leerse
     * seguidos forman el término. Casos construidos para probar el
     * mecanismo, no chistes documentados.
     */
    public static function fusionAcrossBoundary(): array
    {
        return [
            'ces' => ['ces', 'Alp', 'Raseson', 'prase'],
            'dan' => ['dan', 'Als', 'Vinson', 'svin'],
            'nld' => ['nld', 'Alz', 'Weinson', 'zwijn'],
            'fin' => ['fin', 'Als', 'Ikason', 'sika'],
            'fra' => ['fra', 'Aubi', 'Termont', 'bite'],
            'deu' => ['deu', 'Konrad', 'Ummerath', 'dumm'],
            'hun' => ['hun', 'Aldi', 'Sznóson', 'disznó'],
            'ind' => ['ind', 'Alb', 'Abison', 'babi'],
            'ita' => ['ita', 'Roma', 'Grossi', 'magro'],
            'nor' => ['nor', 'Als', 'Vinson', 'svin'],
            'pol' => ['pol', 'Als', 'Winiason', 'świnia'],
            'por' => ['por', 'Isabu', 'Rroso', 'burro'],
            'ron' => ['ron', 'Alp', 'Orcson', 'porc'],
            'slk' => ['slk', 'Alp', 'Rasason', 'prasa'],
            'swe' => ['swe', 'Als', 'Vinson', 'svin'],
            'tur' => ['tur', 'Aldo', 'Muzson', 'domuz'],
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

    /** @dataProvider equivalentSpellings */
    public function testFoldUnifiesEquivalentSpellings(string $folder, string $a, string $b): void
    {
        $this->assertSame($folder::fold($a), $folder::fold($b));
    }

    /** @dataProvider distinctSpellings */
    public function testFoldKeepsDistinctSoundsApart(string $folder, string $a, string $b): void
    {
        $this->assertNotSame($folder::fold($a), $folder::fold($b));
    }

    /** @dataProvider exactFolds */
    public function testFoldProducesExactForm(string $folder, string $input, string $expected): void
    {
        $this->assertSame($expected, $folder::fold($input));
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
