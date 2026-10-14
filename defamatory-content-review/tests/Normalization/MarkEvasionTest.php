<?php

namespace Tests\Normalization;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\DefamatoryContentReviewer;
use DefamatoryContentReview\Normalization\AccentFolding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Letras tachadas, subrayadas, encerradas o con marcas apiladas (zalgo), letras con
 * punto («ṗụṭạ»), versalitas («ᴘᴜᴛᴀ»), parecidas de otros alfabetos («putɑ»), «pu+a» y bytes UTF-8 inválidos, que antes
 * apagaban todas las expresiones regulares y dejaban pasar la línea entera.
 */
class MarkEvasionTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    /** @return array<string,array{string}> */
    public static function disguised(): array
    {
        return [
            'tachada' => ["p\u{336}u\u{336}t\u{336}a\u{336}"],
            'tachada en diagonal' => ["p\u{337}u\u{337}t\u{337}a\u{337}"],
            'subrayada' => ["p\u{332}u\u{332}t\u{332}a\u{332}"],
            'zalgo' => ["p\u{334}\u{321}\u{322}u\u{338}\u{327}t\u{337}\u{330}a\u{335}\u{339}"],
            'con puntos' => ['ṗụṭạ'],
            'versalitas' => ['ᴘᴜᴛᴀ'],
            'encerradas en círculos' => ["p\u{20DD}u\u{20DD}t\u{20DD}a\u{20DD}"],
            'teclas' => ["p\u{20E3}u\u{20E3}t\u{20E3}a\u{20E3}"],
            'alfa latina' => ['putɑ'],
            'cherokee' => ['ᏢUTA'],
            'más por t' => ['pu+a'],
            'byte inválido al final' => ["eres una puta \xff"],
            'byte inválido pegado' => ["puta\xc3"],
        ];
    }

    #[DataProvider('disguised')]
    public function testChatSeesTheInsult(string $line): void
    {
        $result = ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review($line);

        $this->assertSame('reject', $result->getDecision(), bin2hex($line));
        $this->assertContains('puta', array_column($result->getMatches(), 'original'));
    }

    public function testNamesWithAnInvalidByteAreNotValid(): void
    {
        $reviewer = DefamatoryContentReviewer::create(self::CONFIG_DIR, 'spa');

        $this->assertFalse($reviewer->validateName("Puta\xff")->isValid());
        $this->assertFalse($reviewer->validateFullName('Juan', "Puta\xff")->isValid());
        $this->assertFalse($reviewer->validateName("p\u{336}u\u{336}t\u{336}a\u{336}")->isValid());
    }

    public function testRealAccentsAndOtherAlphabetsStay(): void
    {
        $this->assertSame('cancion', AccentFolding::fold('canción', 'spa'));
        $this->assertSame('ạ', AccentFolding::fold('ạ', 'vie'), 'en vietnamita el punto es parte de la letra');
        $this->assertSame('approve', ChatLineReviewer::create(self::CONFIG_DIR, 'spa')->review('el pingüino y la canción')->getDecision());
    }
}
