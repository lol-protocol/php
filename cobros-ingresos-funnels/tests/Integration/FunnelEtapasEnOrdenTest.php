<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * La migracion 013 exige que las etapas del funnel vayan en orden (visita, registro, lead, conversion) y que
 * una etapa solo exista si existe la anterior. Sin eso, FunnelRepository, que cuenta cada etapa por su cuenta,
 * daba tasas mayores que 100 %, tiempos de conversion negativos y conversiones en el mes 0 de una cohorte.
 */
final class FunnelEtapasEnOrdenTest extends IntegracionTestCase
{
    /** @return array<string, array{0: string|null, 1: string|null, 2: string|null}> registro, lead, conversion (la visita es 2026-03-10) */
    public static function filasFueraDeOrden(): array
    {
        return [
            'registro antes de la visita' => ['2026-03-09', null, null],
            'lead sin registro' => [null, '2026-03-12', null],
            'lead antes del registro' => ['2026-03-12', '2026-03-11', null],
            'cliente sin lead' => ['2026-03-11', null, '2026-03-13'],
            'cliente antes del lead' => ['2026-03-11', '2026-03-14', '2026-03-13'],
            'cliente sin registro ni lead' => [null, null, '2026-03-13'],
        ];
    }

    /** @return array<string, array{0: string|null, 1: string|null, 2: string|null}> */
    public static function filasEnOrden(): array
    {
        return [
            'solo visita' => [null, null, null],
            'visita y registro' => ['2026-03-10', null, null],
            'hasta lead' => ['2026-03-11', '2026-03-11', null],
            'hasta cliente, el mismo dia' => ['2026-03-10', '2026-03-10', '2026-03-10'],
            'hasta cliente' => ['2026-03-11', '2026-03-15', '2026-04-02'],
        ];
    }

    private function insertar(?string $registro, ?string $lead, ?string $conversion): void
    {
        $pais = Database::connection()->query('SELECT codigo FROM paises ORDER BY codigo LIMIT 1')->fetchColumn();
        Database::connection()->prepare(
            "INSERT INTO usuarios_funnel (nombre, email, canal, pais_codigo, ciudad, idioma, genero, fecha_nacimiento,
                                          fecha_visita, fecha_registro, fecha_lead, fecha_conversion)
             VALUES ('Prueba de etapas', :email, 'organico', :pais, 'X', 'Espanol', 'No especifica', DATE '1990-01-01',
                     DATE '2026-03-10', :registro, :lead, :conversion)"
        )->execute([':email' => 'etapas-' . uniqid() . '@example.com', ':pais' => $pais, ':registro' => $registro, ':lead' => $lead, ':conversion' => $conversion]);
    }

    #[DataProvider('filasFueraDeOrden')]
    public function testLaBaseRechazaUnaEtapaFueraDeOrden(?string $registro, ?string $lead, ?string $conversion): void
    {
        try {
            Database::transaccion(fn () => $this->insertar($registro, $lead, $conversion));
            self::fail('la base aceptó un recorrido fuera de orden');
        } catch (PDOException $e) {
            self::assertSame('23514', $e->getCode(), 'tiene que ser una violacion de CHECK');
            self::assertStringContainsString('usuarios_funnel_etapas_en_orden', $e->getMessage());
        }
    }

    #[DataProvider('filasEnOrden')]
    public function testLaBaseAceptaUnRecorridoEnOrden(?string $registro, ?string $lead, ?string $conversion): void
    {
        $antes = (int) Database::connection()->query('SELECT COUNT(*) FROM usuarios_funnel')->fetchColumn();

        Database::transaccion(fn () => $this->insertar($registro, $lead, $conversion));

        self::assertSame($antes + 1, (int) Database::connection()->query('SELECT COUNT(*) FROM usuarios_funnel')->fetchColumn());
    }

    public function testLosDatosDeEjemploYaCumplenLaRegla(): void
    {
        $fuera = (int) Database::connection()->query(
            'SELECT COUNT(*) FROM usuarios_funnel WHERE NOT (
                (fecha_registro IS NULL OR fecha_registro >= fecha_visita)
                AND (fecha_lead IS NULL OR (fecha_registro IS NOT NULL AND fecha_lead >= fecha_registro))
                AND (fecha_conversion IS NULL OR (fecha_lead IS NOT NULL AND fecha_conversion >= fecha_lead)))'
        )->fetchColumn();

        self::assertSame(0, $fuera, 'el seed no puede dejar filas que la regla de la 013 rechazaria');
    }
}
