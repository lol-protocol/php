<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controllers\ClientesController;
use App\Repositories\ClienteRepository;
use PHPUnit\Framework\TestCase;

final class ClientesControllerTest extends TestCase
{
    public function testLosGenerosQueOfreceElFormularioSonValidos(): void
    {
        foreach (ClienteRepository::GENEROS as $genero) {
            self::assertTrue(ClientesController::generoEsValido($genero), $genero);
        }
    }

    /** Antes solo lo impedia el <select> del navegador: un POST a mano guardaba cualquier texto. */
    public function testUnGeneroFueraDeLaListaNoEsValido(): void
    {
        foreach (['Alienigena', '', 'masculino', 'Masculino '] as $genero) {
            self::assertFalse(ClientesController::generoEsValido($genero), "'{$genero}'");
        }
    }

    public function testLosSegmentosQueOfreceElFormularioSonValidos(): void
    {
        foreach (ClienteRepository::SEGMENTOS as $segmento) {
            self::assertTrue(ClientesController::segmentoEsValido($segmento), $segmento);
        }
    }

    public function testUnSegmentoFueraDeLaListaNoEsValido(): void
    {
        foreach (['vip', '', 'Pro', 'pro '] as $segmento) {
            self::assertFalse(ClientesController::segmentoEsValido($segmento), "'{$segmento}'");
        }
    }

    /** Antes el alta aceptaba cualquier fecha real, futura incluida, y los reportes la contaban como "18-24". */
    public function testElNacimientoNoPuedeSerPosteriorAHoy(): void
    {
        self::assertTrue(ClientesController::nacimientoNoEsFuturo('1990-05-05', '2026-10-05'));
        self::assertTrue(ClientesController::nacimientoNoEsFuturo('2026-10-05', '2026-10-05'), 'nacer hoy es posible');
        self::assertFalse(ClientesController::nacimientoNoEsFuturo('2026-10-06', '2026-10-05'), 'mañana');
        self::assertFalse(ClientesController::nacimientoNoEsFuturo('2030-01-01', '2026-10-05'));
    }

    /** No hay piso de edad: un menor es un cliente posible y los reportes lo muestran en su propio tramo. */
    public function testUnMenorDeEdadSiPuedeSerCliente(): void
    {
        self::assertTrue(ClientesController::nacimientoNoEsFuturo('2015-03-01', '2026-10-05'));
    }

    /** El valor por defecto de la base ('general') y el que usa el alta sin elegir tienen que estar en la lista. */
    public function testLosValoresPorDefectoEstanEnLasListas(): void
    {
        self::assertContains('general', ClienteRepository::SEGMENTOS);
        self::assertContains('No especifica', ClienteRepository::GENEROS);
    }

    /** "ingles", "INGLES" y " Ingles " eran tres filas distintas en el dashboard. */
    public function testElIdiomaSeNormalizaAMayusculaInicialSinEspaciosSobrantes(): void
    {
        self::assertSame('Ingles', ClientesController::normalizarIdioma('ingles'));
        self::assertSame('Ingles', ClientesController::normalizarIdioma('INGLES'));
        self::assertSame('Ingles', ClientesController::normalizarIdioma("   Ingles \t"));
        self::assertSame('Portugues De Brasil', ClientesController::normalizarIdioma("portugues   de\n brasil"));
        self::assertSame('Inglés', ClientesController::normalizarIdioma('inglés'), 'las tildes se respetan');
        self::assertSame('', ClientesController::normalizarIdioma("  \t "), 'vacio sigue vacio (el alta usa el valor por defecto)');
    }
}
