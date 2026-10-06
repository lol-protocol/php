<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Config;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfigTest extends TestCase
{
    private const VARIABLES = ['APP_ENV', 'APP_TIMEZONE', 'PRUEBA_UNO', 'PRUEBA_DOS'];

    /** @var array<string, string|false> */
    private array $originales = [];

    protected function setUp(): void
    {
        foreach (self::VARIABLES as $nombre) {
            $this->originales[$nombre] = getenv($nombre);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originales as $nombre => $valor) {
            putenv($valor === false ? $nombre : "{$nombre}={$valor}");
        }
    }

    public function testSoloAppEnvDevEsDesarrollo(): void
    {
        putenv('APP_ENV=dev');
        self::assertTrue(Config::esDesarrollo());

        putenv('APP_ENV=produccion');
        self::assertFalse(Config::esDesarrollo());

        putenv('APP_ENV');
        self::assertFalse(Config::esDesarrollo(), 'sin APP_ENV es produccion: la opcion segura no hay que acordarse de activarla');
    }

    public function testEnDesarrolloLasObligatoriasQueFaltanUsanSuValorPorDefecto(): void
    {
        putenv('APP_ENV=dev');
        putenv('PRUEBA_UNO=definida');
        putenv('PRUEBA_DOS');

        self::assertSame(
            ['PRUEBA_UNO' => 'definida', 'PRUEBA_DOS' => 'por-defecto'],
            Config::variablesObligatorias(['PRUEBA_UNO' => 'no-se-usa', 'PRUEBA_DOS' => 'por-defecto'])
        );
    }

    /**
     * Reproduce el caso real: con DB_PASS en vez de DB_PASSWORD, la app se
     * conectaba igual con la clave de desarrollo y nadie se enteraba. Fuera
     * de desarrollo tiene que fallar, nombrando todas las que faltan juntas.
     */
    public function testEnProduccionFaltarUnaObligatoriaEsUnErrorQueLasNombraATodas(): void
    {
        putenv('APP_ENV');
        putenv('PRUEBA_UNO');
        putenv('PRUEBA_DOS');

        try {
            Config::variablesObligatorias(['PRUEBA_UNO' => 'x', 'PRUEBA_DOS' => 'y']);
            self::fail('sin las variables y fuera de desarrollo tiene que fallar');
        } catch (RuntimeException $e) {
            self::assertStringContainsString('PRUEBA_UNO, PRUEBA_DOS', $e->getMessage());
            self::assertStringNotContainsString('x', explode('.', $e->getMessage())[0], 'no filtra los valores por defecto');
        }
    }

    public function testEnProduccionUnaObligatoriaDefinidaSeUsaTalCual(): void
    {
        putenv('APP_ENV');
        putenv('PRUEBA_UNO=valor-real');

        self::assertSame(['PRUEBA_UNO' => 'valor-real'], Config::variablesObligatorias(['PRUEBA_UNO' => 'no-se-usa']));
    }

    public function testLaZonaHorariaPorDefectoEsUtc(): void
    {
        putenv('APP_TIMEZONE');

        self::assertSame('UTC', Config::zonaHoraria());
    }

    public function testAceptaNombresIana(): void
    {
        putenv('APP_TIMEZONE=America/Argentina/Buenos_Aires');

        self::assertSame('America/Argentina/Buenos_Aires', Config::zonaHoraria());
    }

    /**
     * Un offset suelto se rechaza: en un SET TIME ZONE, Postgres lee "-03:00"
     * con la convencion POSIX (signo invertido), y PHP y la base quedarian 6
     * horas desfasados justo cuando se queria alinearlos.
     */
    public function testRechazaOffsetsSueltosYNombresInventados(): void
    {
        foreach (['-03:00', 'Marte/Olympus_Mons'] as $zona) {
            putenv("APP_TIMEZONE={$zona}");
            try {
                Config::zonaHoraria();
                self::fail("{$zona} no deberia aceptarse");
            } catch (RuntimeException $e) {
                self::assertStringContainsString($zona, $e->getMessage());
            }
        }
    }
}
