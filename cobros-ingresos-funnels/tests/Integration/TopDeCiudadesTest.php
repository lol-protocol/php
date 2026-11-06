<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\BoletaRepository;
use App\Repositories\ClienteRepository;
use App\Repositories\SegmentacionRepository;
use DateTimeImmutable;

/**
 * La ciudad de un cliente se escribe a mano: el top de ciudades del Dashboard tiene que juntar
 * las distintas formas de escribir la misma y separar la que se llama igual en dos paises. El
 * seed no tiene ninguno de los dos casos (cada ciudad esta en un solo pais y sin tildes), asi
 * que cada test arma el suyo, con ciudades que el seed no trae.
 */
final class TopDeCiudadesTest extends IntegracionTestCase
{
    /** Un cliente adulto de $pais en $ciudad, con una boleta vigente de $monto dolares. */
    private function clienteConBoleta(string $pais, string $ciudad, float $monto): void
    {
        $hoy = new DateTimeImmutable('today');
        $clienteId = (new ClienteRepository())->crear([
            'nombre' => 'Cliente de ' . $ciudad,
            'email' => 'ciudad-' . uniqid() . '@example.com',
            'segmento' => 'general',
            'fecha_alta' => $hoy->format('Y-m-d'),
            'pais_codigo' => $pais,
            'ciudad' => $ciudad,
            'idioma' => 'Espanol',
            'genero' => 'No especifica',
            'fecha_nacimiento' => $hoy->modify('-40 years')->format('Y-m-d'),
        ]);
        (new BoletaRepository())->crear([
            'cliente_id' => $clienteId,
            'concepto' => 'Boleta de prueba',
            'monto' => $monto,
            'moneda_codigo' => 'USD',
            'fecha_emision' => $hoy->format('Y-m-d'),
            'fecha_vencimiento' => $hoy->format('Y-m-d'),
        ]);
    }

    private function nombreDelPais(string $codigo): string
    {
        $stmt = Database::connection()->prepare('SELECT nombre FROM paises WHERE codigo = :c');
        $stmt->execute([':c' => $codigo]);
        $nombre = $stmt->fetchColumn();
        self::assertIsString($nombre, "el catalogo trae {$codigo}");

        return $nombre;
    }

    /** @return array<string, array{clientes: int, total: float}> la etiqueta de cada fila => lo que reune */
    private function ciudades(): array
    {
        $filas = [];
        foreach ((new SegmentacionRepository())->topPorDimensiones(1000000)['ciudad'] as $fila) {
            $filas[$fila['etiqueta']] = ['clientes' => (int) $fila['clientes'], 'total' => (float) $fila['total_facturado']];
        }

        return $filas;
    }

    public function testLaMismaCiudadEscritaDeVariasFormasEsUnaSolaFila(): void
    {
        $this->clienteConBoleta('CL', 'Ñuñoa', 100);
        $this->clienteConBoleta('CL', 'Ñuñoa', 50);
        $this->clienteConBoleta('CL', ' nunoa  ', 25);
        $this->clienteConBoleta('CL', 'ÑUÑOA', 25);

        $ciudades = $this->ciudades();
        $etiqueta = 'Ñuñoa (' . $this->nombreDelPais('CL') . ')';

        self::assertArrayHasKey($etiqueta, $ciudades, 'se muestra con la grafia que mas clientes usan');
        self::assertSame(4, $ciudades[$etiqueta]['clientes']);
        self::assertEqualsWithDelta(200.0, $ciudades[$etiqueta]['total'], 0.001);
        foreach (['nunoa', 'nunoa ', 'ÑUÑOA', 'NUNOA'] as $otraGrafia) {
            self::assertArrayNotHasKey(
                $otraGrafia . ' (' . $this->nombreDelPais('CL') . ')',
                $ciudades,
                "«{$otraGrafia}» no queda como fila aparte"
            );
        }
    }

    public function testLaMismaCiudadEnDosPaisesSonDosFilas(): void
    {
        $this->clienteConBoleta('CL', 'Villa Prueba', 100);
        $this->clienteConBoleta('DO', 'Villa Prueba', 40);

        $ciudades = $this->ciudades();
        $chile = 'Villa Prueba (' . $this->nombreDelPais('CL') . ')';
        $dominicana = 'Villa Prueba (' . $this->nombreDelPais('DO') . ')';

        self::assertSame(['clientes' => 1, 'total' => 100.0], $ciudades[$chile] ?? null);
        self::assertSame(['clientes' => 1, 'total' => 40.0], $ciudades[$dominicana] ?? null);
    }

    public function testElTopDeCiudadesSigueOrdenadoPorFacturacionYRespetaElLimite(): void
    {
        // Montos enormes para quedar por encima de cualquier ciudad que traiga el seed.
        $this->clienteConBoleta('CL', 'Ciudad Grande', 400_000_000);
        $this->clienteConBoleta('CL', 'ciudad grande', 400_000_000);
        $this->clienteConBoleta('DO', 'Ciudad Chica', 10);

        $top = (new SegmentacionRepository())->topPorDimensiones(1)['ciudad'];

        self::assertCount(1, $top);
        self::assertSame('Ciudad Grande (' . $this->nombreDelPais('CL') . ')', $top[0]['etiqueta']);
        self::assertSame(2, (int) $top[0]['clientes']);
    }

    /**
     * La clave de SQL tiene que plegar exactamente las mismas letras que este equivalente en
     * PHP: si CON_TILDE y SIN_TILDE se desalinearan, una letra se borraria o se cambiaria por otra.
     */
    public function testLaClaveDeCiudadPlegaTodasLasTildesYCompactaLosEspacios(): void
    {
        $entrada = "  ÁÀÂÃÄ ÉÈÊË\t ÍÌÎÏ ÓÒÔÕÖ   ÚÙÛÜ ÑÇ áàâãäéèêëíìîïóòôõöúùûüñç  ";
        $esperado = 'aaaaa eeee iiii ooooo uuuu nc aaaaaeeeeiiiiooooouuuunc';

        $stmt = Database::connection()->prepare('SELECT ' . SegmentacionRepository::claveDeCiudadSql(':ciudad'));
        $stmt->execute([':ciudad' => $entrada]);

        self::assertSame($esperado, $stmt->fetchColumn());
    }
}
