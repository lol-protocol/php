<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controllers\ClienteController;
use App\Repositories\ClienteRepository;
use PHPUnit\Framework\TestCase;

final class ClienteControllerTest extends TestCase
{
    public function testLosGenerosQueOfreceElFormularioSonValidos(): void
    {
        foreach (ClienteRepository::GENEROS as $genero) {
            self::assertTrue(ClienteController::generoEsValido($genero), $genero);
        }
    }

    /** Antes solo lo impedia el <select> del navegador: un POST a mano guardaba cualquier texto. */
    public function testUnGeneroFueraDeLaListaNoEsValido(): void
    {
        foreach (['Alienigena', '', 'masculino', 'Masculino '] as $genero) {
            self::assertFalse(ClienteController::generoEsValido($genero), "'{$genero}'");
        }
    }

    public function testLosSegmentosQueOfreceElFormularioSonValidos(): void
    {
        foreach (ClienteRepository::SEGMENTOS as $segmento) {
            self::assertTrue(ClienteController::segmentoEsValido($segmento), $segmento);
        }
    }

    public function testUnSegmentoFueraDeLaListaNoEsValido(): void
    {
        foreach (['vip', '', 'Pro', 'pro '] as $segmento) {
            self::assertFalse(ClienteController::segmentoEsValido($segmento), "'{$segmento}'");
        }
    }

    /** El valor por defecto de la base ('general') y el que usa el alta sin elegir tienen que estar en la lista. */
    public function testLosValoresPorDefectoEstanEnLasListas(): void
    {
        self::assertContains('general', ClienteRepository::SEGMENTOS);
        self::assertContains('No especifica', ClienteRepository::GENEROS);
    }
}
