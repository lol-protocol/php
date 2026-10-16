<?php

namespace Tests\Review;

use DefamatoryContentReview\Review\ReviewSheet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Una planilla viene de fuera: los nombres que acaban en un archivo sólo valen si los generó bin/review-sheets.php. */
class SheetVocabularyTest extends TestCase
{
    private const HEADER = ['seccion', 'termino', 'origen', 'categoria', 'riskType', 'severidad', 'marcas', 'rango', 'decision_chat', 'correcto', 'comentario'];

    /** @param array<int,array<int,string>> $rows */
    private function sheet(array $rows): ReviewSheet
    {
        return new ReviewSheet([self::HEADER, ...$rows]);
    }

    /** @return array<string,array{string}> lo que `categoria` nunca trae en una planilla generada */
    public static function notLists(): array
    {
        return [
            'otra lista' => ['words'],
            'con comilla' => ["everyday'"],
            'cierra y abre' => ["x'] , 'y"],
            'metacaracteres' => ['.*'],
            'con espacio' => ['every day'],
            'otra caja' => ['EVERYDAY'],
            'sin nada' => [''],
        ];
    }

    #[DataProvider('notLists')]
    public function testRefusesAListTheGeneratorNeverWrites(string $categoria): void
    {
        $sheet = $this->sheet([['excepción', 'bomberos', 'temas', $categoria, '', '', '', '', 'review', 'no', '']]);

        $this->assertSame([], $sheet->changes, 'ni siquiera llega a ser un cambio');
        $this->assertCount(1, $sheet->problems);
        $this->assertStringContainsString('everyday, legit', $sheet->problems[0]);
    }

    public function testAcceptsTheTwoListsItGenerates(): void
    {
        $sheet = $this->sheet([
            ['excepción', 'bomberos', 'temas', 'everyday', '', '', '', '', 'review', 'no', ''],
            ['excepción', 'calle', 'temas', 'legit', '', '', '', '', 'review', 'no', ''],
        ]);

        $this->assertSame([], $sheet->problems);
        $this->assertSame(['everyday', 'legit'], array_column($sheet->changes, 'value'));
    }

    public function testAnExceptionKeptAsIsNeedsNoValidList(): void
    {
        $sheet = $this->sheet([['excepción', 'bomberos', 'temas', "x'", '', '', '', '', 'review', 'sí', '']]);

        $this->assertSame([], [...$sheet->problems, ...$sheet->changes], 'sin cambio no se escribe nada');
    }

    /** @return array<string,array{string}> */
    public static function notTags(): array
    {
        return [
            'con comilla' => ["f'"],
            'cierra y abre' => ["pl'],'x"],
            'con igual' => ['f=high'],
            'mayúsculas' => ['F.PL'],
            'punto doble' => ['f..pl'],
            'tres rasgos' => ['m.sg.x'],
            'diminutivo con cuatro' => ['dim.f.pl.x'],
            'con corchete' => ['f]'],
        ];
    }

    #[DataProvider('notTags')]
    public function testRefusesAFormTagThatIsNotATag(string $tag): void
    {
        $sheet = $this->sheet([['forma', 'cerdos', 'diccionario', 'animal', 'animal', 'medium', "forma:{$tag} de:cerdo", '', 'review', 'alta', '']]);

        $this->assertSame([], $sheet->changes);
        $this->assertCount(1, $sheet->problems);
        $this->assertStringContainsString('m.sg', $sheet->problems[0], 'dice qué forma tienen las etiquetas');
    }

    public function testAcceptsTheTagsTheGeneratorWrites(): void
    {
        $sheet = $this->sheet([
            ['forma', 'cerdos', 'diccionario', 'animal', 'animal', 'medium', 'forma:pl de:cerdo', '', 'review', 'alta', ''],
            ['forma', 'cerdas', 'diccionario', 'animal', 'animal', 'medium', 'forma:f.pl de:cerdo', '', 'review', 'no', ''],
            ['forma', 'putitas', 'diccionario', 'genero', 'genero', 'high', 'forma:dim.f.pl de:puta', '', 'reject', 'media', ''],
        ]);

        $this->assertSame([], $sheet->problems);
        $this->assertSame(['pl=high', 'f.pl=none', 'dim.f.pl=medium'], array_column($sheet->changes, 'value'));
    }
}
