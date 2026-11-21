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
}
