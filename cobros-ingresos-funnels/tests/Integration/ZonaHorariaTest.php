<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra la base configurada por las env vars DB_*. No usa la
 * transaccion de IntegracionTestCase porque abre conexiones propias y cambia
 * la zona horaria del proceso, que restaura al terminar.
 */
final class ZonaHorariaTest extends TestCase
{
    private string|false $appTimezone;
    private string $zonaDePhp;

    protected function setUp(): void
    {
        $this->appTimezone = getenv('APP_TIMEZONE');
        $this->zonaDePhp = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        putenv($this->appTimezone === false ? 'APP_TIMEZONE' : "APP_TIMEZONE={$this->appTimezone}");
        date_default_timezone_set($this->zonaDePhp);
    }

    public function testPhpYPostgresUsanLaMismaZona(): void
    {
        $zonaDePostgres = Database::connection()->query('SHOW TimeZone')->fetchColumn();

        self::assertSame(date_default_timezone_get(), $zonaDePostgres);
    }

    /**
     * Con una zona lejos de UTC, "hoy" para PHP y para Postgres tiene que
     * ser el mismo dia: es lo que decide si una boleta esta vencida, tanto en
     * el listado (PHP) como en el grafico de antiguedad (SQL).
     */
    public function testAppTimezoneSeAplicaALosDosRelojes(): void
    {
        putenv('APP_TIMEZONE=Pacific/Kiritimati');   // UTC+14: casi siempre es otro dia que en UTC

        $conexion = Database::conectar();

        self::assertSame('Pacific/Kiritimati', date_default_timezone_get());
        self::assertSame('Pacific/Kiritimati', $conexion->query('SHOW TimeZone')->fetchColumn());
        self::assertSame(date('Y-m-d'), $conexion->query('SELECT CURRENT_DATE::text')->fetchColumn());
    }
}
