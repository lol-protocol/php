<?php

namespace Tests\Chat;

use DefamatoryContentReview\Chat\ChatLineReviewer;
use DefamatoryContentReview\Chat\CliticPrefixes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Artículo y preposiciones pegados en hebreo y árabe: se encuentra la palabra, pero nunca bloquea sola. */
class CliticPrefixesTest extends TestCase
{
    private const CONFIG_DIR = __DIR__ . '/../../config';

    public function testSearcherStripsUpToThreePrefixesAndMarksTheEntry(): void
    {
        $search = fn(string $w): ?array => $w === 'טיפש' ? ['original' => 'טיפש'] : null;
        $find = CliticPrefixes::searcher($search, 'heb');

        $this->assertSame(['original' => 'טיפש'], $find('טיפש'), 'tal cual: sin marca');
        $this->assertSame(['original' => 'טיפש', 'prefix' => 'clitic'], $find('הטיפש'));
        $this->assertSame(['original' => 'טיפש', 'prefix' => 'clitic'], $find('וכהטיפש'));
        $this->assertNull($find('וכהוטיפש'), 'más de tres no');
        $this->assertNull($find('הטי'), 'el resto necesita tres letras');
        $this->assertNull($find('מטיפש'), 'la «מ» no se quita: «מחזיר» es «devuelve», no «de cerdo»');
        $this->assertNull($find('ה טיפש'), 'una frase de varias palabras no');
    }

    public function testOnlyHebrewAndArabicHavePrefixes(): void
    {
        $this->assertTrue(CliticPrefixes::applies('heb'));
        $this->assertTrue(CliticPrefixes::applies('ara'));
        $this->assertFalse(CliticPrefixes::applies('spa'));
    }

    /** @return array<string,array{string,string,string}> idioma, línea, decisión */
    public static function lines(): array
    {
        return [
            'hebreo: sola' => ['heb', 'טיפש', 'review'],
            'hebreo: el tonto' => ['heb', 'הטיפש', 'review'],
            'hebreo: y el tonto' => ['heb', 'והטיפש', 'review'],
            'hebreo: en la mierda' => ['heb', 'בחרא', 'review'],
            'hebreo: la puta no bloquea' => ['heb', 'הזונה', 'review'],
            'hebreo: puta sola sí bloquea' => ['heb', 'זונה', 'reject'],
            'hebreo: vivir' => ['heb', 'אני רוצה לחיות', 'approve'],
            'hebreo: devolver' => ['heb', 'אתה מחזיר את זה', 'approve'],
            'hebreo: cocina' => ['heb', 'אני במטבח', 'approve'],
            'árabe: el tonto' => ['ara', 'الغبي', 'review'],
            'árabe: con el tonto' => ['ara', 'بالغبي', 'review'],
            'árabe: y el tonto' => ['ara', 'والغبي', 'review'],
            'árabe: gotas para el ojo' => ['ara', 'قطرة للعين', 'approve'],
            'árabe: normal' => ['ara', 'مرحبا بكم', 'approve'],
        ];
    }

    #[DataProvider('lines')]
    public function testDecision(string $language, string $line, string $decision): void
    {
        $this->assertSame($decision, ChatLineReviewer::create(self::CONFIG_DIR, $language)->review($line)->getDecision(), $line);
    }
}
