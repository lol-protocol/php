<?php

declare(strict_types=1);

namespace Tests\App\Support;

use App\Support\APM;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * APM_SAMPLE_RATE: 0 es «no muestrear nada». `(float)getenv(...) ?: 1.0` volvía 1.0 el 0, así que quien lo ponía en 0
 * para apagar el muestreo lo dejaba al máximo.
 */
class APMTest extends TestCase
{
    private string|false $tasaAntes;

    protected function setUp(): void
    {
        $this->tasaAntes = getenv('APM_SAMPLE_RATE');
        $this->reiniciar();
    }

    protected function tearDown(): void
    {
        putenv($this->tasaAntes === false ? 'APM_SAMPLE_RATE' : 'APM_SAMPLE_RATE=' . $this->tasaAntes);
        $this->reiniciar();
    }

    /** El APM es un singleton: se olvida la instancia para que la siguiente lea el entorno de nuevo. */
    private function reiniciar(): void
    {
        (new ReflectionClass(APM::class))->getProperty('instance')->setValue(null, null);
    }

    private function tasaConfigurada(string|false $valor): float
    {
        putenv($valor === false ? 'APM_SAMPLE_RATE' : 'APM_SAMPLE_RATE=' . $valor);
        $this->reiniciar();

        return APM::getInstance()->getConfig('sample_rate');
    }

    private function muestrea(float $tasa, int $veces): int
    {
        $this->tasaConfigurada((string)$tasa);
        $apm = APM::getInstance();
        $metodo = (new ReflectionClass(APM::class))->getMethod('shouldSample');
        $si = 0;
        for ($i = 0; $i < $veces; $i++) {
            $si += $metodo->invoke($apm) ? 1 : 0;
        }

        return $si;
    }

    public function testSinLaVariableSeMuestreaTodo(): void
    {
        $this->assertSame(1.0, $this->tasaConfigurada(false));
        $this->assertSame(1.0, $this->tasaConfigurada(''));
        $this->assertSame(1.0, $this->tasaConfigurada('abc'));
    }

    public function testCeroEsNoMuestrearNada(): void
    {
        $this->assertSame(0.0, $this->tasaConfigurada('0'));
        $this->assertSame(0.0, $this->tasaConfigurada('0.0'));
    }

    public function testUnaTasaIntermediaSeRespetaYLasQueSePasanSeAcotan(): void
    {
        $this->assertSame(0.25, $this->tasaConfigurada('0.25'));
        $this->assertSame(1.0, $this->tasaConfigurada('1.5'));
        $this->assertSame(0.0, $this->tasaConfigurada('-1'));
    }

    public function testConTasaCeroNuncaSeMuestreaYConTasaUnoSiempre(): void
    {
        $this->assertSame(0, $this->muestrea(0.0, 20000));
        $this->assertSame(20000, $this->muestrea(1.0, 20000));
    }

    public function testConTasaMediaSeMuestreaMasOMenosLaMitad(): void
    {
        mt_srand(20261107);
        $muestreadas = $this->muestrea(0.5, 40000);

        $this->assertEqualsWithDelta(0.5, $muestreadas / 40000, 0.01);
    }
}
