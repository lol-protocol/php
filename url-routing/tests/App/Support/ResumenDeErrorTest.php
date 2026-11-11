<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\ResumenDeError;
use PHPUnit\Framework\TestCase;

class ResumenDeErrorTest extends TestCase
{
    private function error(): \PDOException
    {
        $e = new \PDOException('connection to server at "10.0.0.5", user "app_prod" failed: Key (id)=(6128473105) already exists');
        $e->errorInfo = ['23505'];

        return $e;
    }

    public function testElLogNoGuardaElMensaje(): void
    {
        $texto = ResumenDeError::de($this->error(), false);

        $this->assertStringContainsString('PDOException', $texto);
        $this->assertStringContainsString('ResumenDeErrorTest.php:', $texto, 'where it happened');
        foreach (['10.0.0.5', 'app_prod', '6128473105', 'connection to server'] as $secreto) {
            $this->assertStringNotContainsString($secreto, $texto);
        }
        $this->assertStringNotContainsString(__DIR__, $texto, 'the file, not the path of the server');
    }

    public function testConDepuracionSiLlevaElMensajeYLaRuta(): void
    {
        $texto = ResumenDeError::de($this->error(), true);

        $this->assertStringContainsString('app_prod', $texto);
        $this->assertStringContainsString(__FILE__, $texto);
    }

    public function testUnCodigoSeConserva(): void
    {
        $this->assertStringContainsString('[42]', ResumenDeError::de(new \RuntimeException('x', 42), false));
        $this->assertStringNotContainsString('[0]', ResumenDeError::de(new \RuntimeException('x'), false), 'no code, no brackets');
    }
}
