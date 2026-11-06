<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\FunnelRepository;

/**
 * Corre contra la base configurada por las env vars DB_*. Requiere haber
 * corrido antes `php bin/recrear_con_datos_de_ejemplo.php` (mismas variables) para tener datos.
 */
final class FunnelRepositoryTest extends IntegracionTestCase
{
    public function testResumenEtapasNuncaSuperaElTotalDeVisitantes(): void
    {
        $r = (new FunnelRepository())->resumenEtapas('2000-01-01', '2100-01-01');

        self::assertLessThanOrEqual($r['visitantes'], $r['registrados']);
        self::assertLessThanOrEqual($r['visitantes'], $r['leads']);
        self::assertLessThanOrEqual($r['visitantes'], $r['clientes']);
        foreach ($r as $valor) {
            self::assertGreaterThanOrEqual(0, $valor);
        }
    }

    public function testCohortesSonAcumulativasYMonotonasCrecientes(): void
    {
        $filas = (new FunnelRepository())->cohortes('2000-01-01', '2100-01-01');

        if ($filas === []) {
            self::markTestSkipped('No hay usuarios de funnel seedeados.');
        }

        foreach ($filas as $fila) {
            self::assertLessThanOrEqual($fila['m1'], $fila['m0'], "cohorte {$fila['cohorte']}: m0 no puede superar a m1");
            self::assertLessThanOrEqual($fila['m2'], $fila['m1'], "cohorte {$fila['cohorte']}: m1 no puede superar a m2");
            self::assertLessThanOrEqual($fila['m3'], $fila['m2'], "cohorte {$fila['cohorte']}: m2 no puede superar a m3");
            self::assertLessThanOrEqual($fila['total'], $fila['m3'], "cohorte {$fila['cohorte']}: ninguna columna puede superar el total");
        }
    }

    public function testCohortesDicenCuantosMesesLlevanCerrados(): void
    {
        $filas = (new FunnelRepository())->cohortes('2000-01-01', '2100-01-01');
        if ($filas === []) {
            self::markTestSkipped('No hay usuarios de funnel seedeados.');
        }

        $esteMes = (int) date('Y') * 12 + (int) date('n');
        foreach ($filas as $fila) {
            [$anio, $mes] = array_map('intval', explode('-', $fila['cohorte']));
            self::assertSame($esteMes - ($anio * 12 + $mes), (int) $fila['meses_cerrados'], "cohorte {$fila['cohorte']}");
        }
    }

    public function testTiempoPromedioDeConversionObedeceAlPeriodo(): void
    {
        $repo = new FunnelRepository();
        self::assertGreaterThan(0.0, $repo->tiempoPromedioConversionDias('2000-01-01', '2100-01-01'));
        self::assertSame(0.0, $repo->tiempoPromedioConversionDias('2000-01-01', '2000-01-31'), 'sin visitas en el periodo no hay promedio');
    }

    /**
     * Un periodo que el seed no usa (1950), para contar exacto sin sumarle los datos de ejemplo.
     * Los primeros $cuantos nombres del catalogo de paises, en orden alfabetico.
     *
     * @return list<array{codigo: string, nombre: string}>
     */
    private function paisesDelCatalogo(int $cuantos): array
    {
        $paises = Database::connection()->query("SELECT codigo, nombre FROM paises ORDER BY nombre LIMIT {$cuantos}")->fetchAll();
        self::assertCount($cuantos, $paises, 'el catalogo trae paises de sobra');

        return $paises;
    }

    /** $cuantos visitantes de $pais en el periodo de prueba; los $convertidos llegan hasta cliente. */
    private function visitantes(string $pais, int $cuantos, int $convertidos = 0): void
    {
        $stmt = Database::connection()->prepare(
            "INSERT INTO usuarios_funnel (nombre, email, canal, pais_codigo, ciudad, idioma, genero, fecha_nacimiento,
                                          fecha_visita, fecha_registro, fecha_lead, fecha_conversion)
             VALUES ('Visitante de prueba', :email, 'organico', :pais, 'X', 'Espanol', 'No especifica', '1980-01-01',
                     '1950-03-01', '1950-03-02', :lead, :conversion)"
        );
        for ($i = 0; $i < $cuantos; $i++) {
            $llega = $i < $convertidos;
            $stmt->execute([
                ':email' => 'funnel-' . uniqid('', true) . '@example.com',
                ':pais' => $pais,
                ':lead' => $llega ? '1950-03-03' : null,
                ':conversion' => $llega ? '1950-03-04' : null,
            ]);
        }
    }

    public function testPorPaisJuntaLosPaisesDeSobraEnUnaFilaOtrosQueCuadraConElResumen(): void
    {
        $paises = $this->paisesDelCatalogo(13);
        foreach ($paises as $i => $pais) {
            // 13, 12, 11 ... 1 visitantes: el orden por cantidad queda fijo; el ultimo pais convierte a su unico visitante.
            $this->visitantes($pais['codigo'], 13 - $i, $i === 12 ? 1 : 0);
        }

        $repo = new FunnelRepository();
        $filas = $repo->porPais('1950-01-01', '1950-12-31');

        self::assertCount(FunnelRepository::PAISES_EN_LA_TABLA + 1, $filas);
        self::assertSame(array_column(array_slice($paises, 0, 10), 'nombre'), array_column(array_slice($filas, 0, 10), 'etiqueta'));
        $otros = $filas[10];
        self::assertSame('Otros (3 países)', $otros['etiqueta']);
        self::assertSame(3 + 2 + 1, $otros['visitantes']);
        self::assertSame(3 + 2 + 1, $otros['registrados']);
        self::assertSame(1, $otros['leads']);
        self::assertSame(1, $otros['clientes']);

        $resumen = $repo->resumenEtapas('1950-01-01', '1950-12-31');
        foreach (['visitantes', 'registrados', 'leads', 'clientes'] as $etapa) {
            self::assertSame($resumen[$etapa], array_sum(array_column($filas, $etapa)), "{$etapa}: la tabla tiene que sumar lo mismo que el resumen");
        }
    }

    public function testPorPaisNombraUnicoPaisSobranteEnVezDeEsconderloEnOtros(): void
    {
        $paises = $this->paisesDelCatalogo(11);
        foreach ($paises as $i => $pais) {
            $this->visitantes($pais['codigo'], 11 - $i);
        }

        $filas = (new FunnelRepository())->porPais('1950-01-01', '1950-12-31');

        self::assertSame(array_column($paises, 'nombre'), array_column($filas, 'etiqueta'), 'los once con su nombre, sin fila «Otros»');
    }

    public function testPorPaisDesempataPorNombreParaQueElCorteSeaEstable(): void
    {
        $paises = $this->paisesDelCatalogo(12);
        // Los nueve primeros con mas visitantes, y cuantos mas, mas atras en el alfabeto: asi la
        // base tiene que reordenar de verdad por cantidad. Los tres ultimos, empatados.
        foreach (array_slice($paises, 0, 9) as $i => $pais) {
            $this->visitantes($pais['codigo'], 12 + $i);
        }
        foreach (array_reverse(array_slice($paises, 9)) as $pais) {
            $this->visitantes($pais['codigo'], 1);
        }

        // Con el plan por defecto la base agrupa ordenando por nombre y los empates salen alfabeticos
        // aunque la consulta no los desempate. Sin ordenar, agrupa con hash y los empates salen como caigan.
        Database::connection()->exec('SET LOCAL enable_sort = off');
        $filas = (new FunnelRepository())->porPais('1950-01-01', '1950-12-31');

        self::assertSame($paises[9]['nombre'], $filas[9]['etiqueta'], 'entre los empatados se nombra el de nombre menor');
        self::assertSame('Otros (2 países)', $filas[10]['etiqueta']);
    }

    public function testPorCanalSumaLoMismoQueElResumenGeneral(): void
    {
        $funnelRepo = new FunnelRepository();
        $resumen = $funnelRepo->resumenEtapas('2000-01-01', '2100-01-01');
        $porCanal = $funnelRepo->porCanal('2000-01-01', '2100-01-01');

        self::assertSame($resumen['visitantes'], array_sum(array_column($porCanal, 'visitantes')));
        self::assertSame($resumen['clientes'], array_sum(array_column($porCanal, 'clientes')));
    }
}
