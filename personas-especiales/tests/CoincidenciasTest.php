<?php

declare(strict_types=1);

namespace Tests;

use PersonasEspeciales\Coincidencias;
use PHPUnit\Framework\TestCase;

final class CoincidenciasTest extends TestCase
{
    /** @dataProvider variantes */
    public function testVariantesDeGrafiaCoinciden(string $consultado, string $excluido): void
    {
        self::assertTrue(Coincidencias::coincide($consultado, $excluido));
    }

    /** @return iterable<string, array{string, string}> */
    public static function variantes(): iterable
    {
        yield 'mayúsculas' => ['GONZÁLEZ', 'gonzalez'];
        yield 'tilde' => ['Pérez', 'Perez'];
        yield 'eñe' => ['Muñoz', 'Munoz'];
        yield 'espacios de más' => ['  de   la   Fuente ', 'de la fuente'];
        yield 'guion o apóstrofo' => ["O'Brien", 'OBrien'];
        yield 'guion' => ['Sánchez-Vidal', 'sanchez vidal'];
    }

    public function testTransliteracionAlemanaNoSeHace(): void
    {
        // Límite conocido: «Müller» no es «Mueller». Hacerlo pediría reglas por idioma
        // (y más falsos positivos); queda para decidirlo con la fonética, si se hace.
        self::assertFalse(Coincidencias::coincide('Müller', 'MUELLER'));
    }

    public function testApellidosDistintosNoCoinciden(): void
    {
        self::assertFalse(Coincidencias::coincide('Pérez', 'Pereira'));
        self::assertFalse(Coincidencias::coincide('Garcia', 'García Lorca'));
    }

    public function testApellidoCortoNoCoincide(): void
    {
        // Un apellido de 2 letras (p. ej. «Li») no debe ocultar a todo el que lo lleva.
        self::assertFalse(Coincidencias::coincide('Li', 'li'));
        self::assertFalse(Coincidencias::coincide('Ng', 'ng'));
    }

    public function testApellidoVacioNoCoincide(): void
    {
        self::assertFalse(Coincidencias::coincide('', ''));
        self::assertFalse(Coincidencias::coincide('  ', '...'));
    }

    public function testAlfabetoNoLatinoSeConserva(): void
    {
        // Antes de usar \p{L}, un apellido en cirílico se vaciaba y nunca coincidía.
        self::assertSame('иванов', Coincidencias::normalizar('Иванов'));
        self::assertTrue(Coincidencias::coincide('Иванов', 'иванов'));
    }

    public function testIndicesCoincidentes(): void
    {
        $excluidos = ['Pérez', 'Soto', 'PEREZ', 'Muñoz'];

        self::assertSame([0, 2], Coincidencias::indicesCoincidentes('perez', $excluidos));
        self::assertSame([], Coincidencias::indicesCoincidentes('Rojas', $excluidos));
    }

    public function testNormalizarQuitaTildesYSignos(): void
    {
        self::assertSame('sanchez vidal', Coincidencias::normalizar('Sánchez-Vidal.'));
    }

    public function testUnaTildeDescompuestaCoincideConLaPrecompuesta(): void
    {
        // «e» + U+0301 (como la dan algunos teclados y sistemas) es lo mismo que «é».
        self::assertSame('perez', Coincidencias::normalizar("Pe\u{301}rez"));
        self::assertTrue(Coincidencias::coincide("Pe\u{301}rez", "P\u{e9}rez"));
        self::assertTrue(Coincidencias::coincide("Mun\u{303}oz", "Mu\u{f1}oz")); // n + tilde, ñ
    }

    public function testVariasMarcasSeguidasSeQuitanDetrasDeUnaLetraLatina(): void
    {
        // Vietnamita: «ệ» descompuesta lleva dos marcas seguidas.
        self::assertSame('nguyen', Coincidencias::normalizar("Nguye\u{323}\u{302}n"));
    }

    public function testLasMarcasDeOtrosAlfabetosNoPartenLaPalabra(): void
    {
        // Antes de quitar solo las de las letras latinas, una marca suelta se volvía un espacio.
        $iv = "\u{418}\u{432}\u{430}\u{301}\u{43d}\u{43e}\u{432}"; // «Ива́нов» con el acento descompuesto
        self::assertStringNotContainsString(' ', Coincidencias::normalizar($iv));
        self::assertTrue(Coincidencias::coincide($iv, $iv));
        // Devanagari: las vocales son marcas y forman parte de la palabra.
        $singh = "\u{938}\u{93F}\u{902}\u{939}"; // «सिंह»
        self::assertStringNotContainsString(' ', Coincidencias::normalizar($singh));
    }

    /** @dataProvider apostrofos */
    public function testTodasLasFormasDelApostrofoSeBorran(string $variante): void
    {
        self::assertTrue(Coincidencias::coincide("O{$variante}Brien", 'OBrien'));
    }

    /** @return iterable<string, array{string}> */
    public static function apostrofos(): iterable
    {
        yield 'recto' => ["'"];
        yield 'curvo derecho' => ["\u{2019}"];
        yield 'curvo izquierdo' => ["\u{2018}"];
        yield 'acento agudo suelto' => ["\u{B4}"];
        yield 'acento grave' => ['`'];
        yield 'modificador' => ["\u{2BC}"];
        yield 'okina' => ["\u{2BB}"];
    }
}
