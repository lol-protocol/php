<?php

namespace Tests;

use DefamatoryContentReview\CzechPhoneticFolder;
use DefamatoryContentReview\DanishPhoneticFolder;
use DefamatoryContentReview\DutchPhoneticFolder;
use DefamatoryContentReview\FinnishPhoneticFolder;
use DefamatoryContentReview\FrenchPhoneticFolder;
use DefamatoryContentReview\GermanPhoneticFolder;
use DefamatoryContentReview\HungarianPhoneticFolder;
use DefamatoryContentReview\IndonesianPhoneticFolder;
use DefamatoryContentReview\NorwegianPhoneticFolder;
use DefamatoryContentReview\PolishPhoneticFolder;
use DefamatoryContentReview\PortuguesePhoneticFolder;
use DefamatoryContentReview\RomanianPhoneticFolder;
use DefamatoryContentReview\SlovakPhoneticFolder;
use DefamatoryContentReview\SwedishPhoneticFolder;
use DefamatoryContentReview\TurkishPhoneticFolder;
use PHPUnit\Framework\TestCase;

/**
 * Dos grafías de la misma palabra que cada folder debe plegar a la misma
 * forma, un idioma con folder propio por caso. Los casos donde el plegado
 * NO debe unificar dos sonidos distintos, o produce una forma exacta
 * concreta, viven en PhoneticFoldingEdgeCasesTest — separado para que
 * ningún archivo pase de 100 líneas.
 */
class PhoneticFoldingRulesTest extends TestCase
{
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

    /** @dataProvider equivalentSpellings */
    public function testFoldUnifiesEquivalentSpellings(string $folder, string $a, string $b): void
    {
        $this->assertSame($folder::fold($a), $folder::fold($b));
    }
}
