<?php

namespace Tests\Normalization;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\DefamatoryContentReviewer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Evasión con la forma del texto, no con las letras: frases pegadas, sílabas
 * sueltas, saltos de línea, HTML, letras en cuadros o banderas, texto
 * invertido o cabeza abajo, la «İ» turca. Y los insultos de otros países.
 */
class LayoutEvasionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string,string,string}> idioma, línea, término */
    public static function disguised(): array
    {
        return [
            'frase pegada' => ['spa', 'hijodeputa', 'hijo de puta'],
            'consigna pegada' => ['eng', 'siegheil', 'sieg heil'],
            'letras sueltas que forman una frase' => ['spa', 'p.u.t.a.m.a.d.r.e', 'puta madre'],
            'sílabas' => ['spa', 'hi jo de pu ta', 'hijo de puta'],
            'sílabas con varios espacios' => ['spa', 'pu   ta', 'puta'],
            'sílabas en dos líneas' => ['spa', "pu\nta", 'puta'],
            'una letra por línea' => ['spa', "p\nu\nt\na", 'puta'],
            'etiqueta HTML en medio' => ['spa', 'pu<b></b>ta', 'puta'],
            'entidad HTML' => ['spa', 'eres una pu&#116;a', 'puta'],
            'letras en cuadros' => ['spa', '🅿🆄🆃🅰', 'puta'],
            'indicadores regionales' => ['spa', '🇵🇺🇹🇦', 'puta'],
            'forzado de derecha a izquierda' => ['spa', "\u{202E}atup ed ojih", 'hijo de puta'],
            'cabeza abajo' => ['spa', 'ɐʇnd', 'puta'],
            'cabeza abajo en una frase' => ['spa', 'eres un ɐʇoᴉpᴉ', 'idiota'],
            'İ turca' => ['tur', 'İBNE', 'ibne'],
            'Colombia' => ['spa', 'careverga', 'careverga'],
            'Colombia, dirigido' => ['spa', 'eres un gonorrea', 'gonorrea'],
            'Perú' => ['spa', 'conchatumadre', 'conchatumadre'],
            'Venezuela' => ['spa', 'mamaguevo', 'mamaguevo'],
            'Cuba' => ['spa', 'comemierda', 'comemierda'],
            'México' => ['spa', 'chinga tu madre', 'chinga tu madre'],
        ];
    }

    #[DataProvider('disguised')]
    public function testDisguisedInsultIsFound(string $language, string $line, string $term): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line);

        $this->assertNotSame('approve', $result->getDecision(), $line);
        $this->assertContains($term, array_column($result->getMatches(), 'original'), $line);
    }

    public function testNamesSeeThroughTheLayoutToo(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');

        foreach (['hijodeputa', '🅿🆄🆃🅰', 'ɐʇnd', 'pu<b></b>ta'] as $name) {
            $this->assertFalse($reviewer->validateName($name)->isValid(), $name);
        }
    }

    /** @return array<string,array{string,string}> idioma, línea */
    public static function ordinaryLines(): array
    {
        return [
            'dos sílabas que son palabras' => ['fra', 'la mer de Chine'],
            'otra en francés' => ['fra', "j'ai pu te voir"],
            'forro de ropa' => ['spa', 'el forro del abrigo'],
            'enfermedad, sin dirigir' => ['spa', 'la gonorrea es una infección'],
            'entidad corriente' => ['spa', 'Tom &amp; Jerry'],
            'bandera' => ['spa', '¡vamos 🇨🇱!'],
            'HTML corriente' => ['spa', '<b>hola</b> a todos'],
            'İ en una ciudad' => ['tur', 'İstanbul çok güzel'],
            'ı sin punto' => ['tur', 'IŞIK'],
        ];
    }

    #[DataProvider('ordinaryLines')]
    public function testOrdinaryLinesAreApproved(string $language, string $line): void
    {
        $this->assertSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), $line);
    }

    public function testTheMaskedLineKeepsWhatWasWritten(): void
    {
        $this->assertSame('eres una ****', ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review('eres una puta')->censored());
    }
}
