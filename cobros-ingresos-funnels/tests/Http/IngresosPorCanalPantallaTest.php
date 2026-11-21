<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Config;
use App\Database;
use App\Etiquetas;
use App\Repositories\FunnelRepository;

/**
 * El panel «Ingresos por canal de adquisicion» de la pantalla del Funnel contra la app levantada. Casi todo se
 * compara contra el repositorio sobre los mismos datos de ejemplo; el caso del convertido sin cliente enlazado crea
 * su fila (el servidor commitea) y la borra al terminar.
 */
final class IngresosPorCanalPantallaTest extends HttpTestCase
{
    private const AMPLIO = 'desde=2000-01-01&hasta=2100-12-31';

    public function testElPanelMuestraUnaFilaPorCanalConSuCobradoYElTotal(): void
    {
        $respuesta = $this->get('page=funnel&' . self::AMPLIO);
        $this->assertStatus(200, $respuesta);
        $cuerpo = $respuesta['cuerpo'];
        $ingresos = (new FunnelRepository())->ingresosPorCanal('2000-01-01', '2100-12-31');

        self::assertStringContainsString('<h2>Ingresos por canal de adquisición</h2>', $cuerpo);
        self::assertMatchesRegularExpression(
            '~<th>Canal</th>\s*<th class="num">Cobrado \(USD\)</th>\s*<th class="num">LTV por cliente</th>\s*<th class="num">Por visitante</th>\s*<th class="num">Clientes</th>\s*<th class="num">Visitantes</th>~',
            $cuerpo,
            'primero lo que vale cada canal (es lo que se ve en una pantalla chica), despues sus divisores'
        );
        self::assertNotSame([], $ingresos['filas'], 'los datos de ejemplo traen canales');
        foreach ($ingresos['filas'] as $fila) {
            $celdas = [
                '<td>' . e(Etiquetas::canal((string) $fila['etiqueta'])) . '</td>',
                '<td class="num">' . Config::dinero($fila['cobrado_usd']) . '</td>',
                '<td class="num">' . Config::dinero((float) $fila['ltv_usd']) . '</td>',
                '<td class="num">' . Config::dinero((float) $fila['por_visitante_usd']) . '</td>',
                '<td class="num">' . $fila['clientes'] . '</td>',
                '<td class="num">' . $fila['visitantes'] . '</td>',
            ];
            self::assertMatchesRegularExpression(
                '~' . implode('\s*', array_map(static fn (string $celda): string => preg_quote($celda, '~'), $celdas)) . '~',
                $cuerpo,
                'la fila de ' . $fila['etiqueta'] . ' con sus seis celdas, en orden'
            );
        }
        $total = $ingresos['total'];
        self::assertStringContainsString('<td><strong>Todos los canales</strong></td>', $cuerpo);
        self::assertStringContainsString('<td class="num"><strong>' . Config::dinero($total['cobrado_usd']) . '</strong></td>', $cuerpo);
        self::assertStringContainsString('<td class="num"><strong>' . Config::dinero((float) $total['ltv_usd']) . '</strong></td>', $cuerpo);
    }

    public function testDiceCuantosClientesNoTienenRecorridoEnElFunnel(): void
    {
        $sinRecorrido = (new FunnelRepository())->clientesSinRecorrido();
        $cuerpo = $this->get('page=funnel&' . self::AMPLIO)['cuerpo'];

        self::assertGreaterThan(1, $sinRecorrido, 'los datos de ejemplo traen clientes cargados directo');
        self::assertStringContainsString("{$sinRecorrido} clientes no tienen recorrido en el funnel (se cargaron directo) y no entran aquí.", $cuerpo);
    }

    public function testUnPeriodoSinVisitasDiceQueNoHayDatosYNoMuestraTotal(): void
    {
        $cuerpo = $this->get('page=funnel&desde=1999-01-01&hasta=1999-01-31')['cuerpo'];

        self::assertStringContainsString('<h2>Ingresos por canal de adquisición</h2>', $cuerpo);
        self::assertStringNotContainsString('Todos los canales', $cuerpo);
        self::assertStringContainsString('<tr><td colspan="6">Sin datos para este período.</td></tr>', $cuerpo);
    }

    public function testElPanelRespetaElPeriodoPedido(): void
    {
        $repo = new FunnelRepository();
        $dia = (string) Database::connection()->query('SELECT MAX(fecha_visita) FROM usuarios_funnel')->fetchColumn();
        $todo = $repo->ingresosPorCanal('2000-01-01', '2100-12-31')['total'];
        $unDia = $repo->ingresosPorCanal($dia, $dia)['total'];
        self::assertGreaterThan(0, $unDia['visitantes'], 'el ultimo dia con visitas tiene visitas');
        self::assertNotSame($todo['visitantes'], $unDia['visitantes'], 'el rango de un dia es otra cohorte que todo el historial');

        $cuerpo = $this->get("page=funnel&desde={$dia}&hasta={$dia}")['cuerpo'];

        self::assertStringContainsString('<td class="num"><strong>' . $unDia['visitantes'] . '</strong></td>', $cuerpo);
        self::assertStringNotContainsString('<td class="num"><strong>' . $todo['visitantes'] . '</strong></td>', $cuerpo, 'no es el total de todo el historial');
    }

    public function testAvisaDelConvertidoSinClienteEnlazado(): void
    {
        $email = 'sin-enlace-' . uniqid() . '@example.com';
        Database::connection()->prepare(
            "INSERT INTO usuarios_funnel (nombre, email, canal, pais_codigo, ciudad, idioma, genero, fecha_nacimiento,
                                          fecha_visita, fecha_registro, fecha_lead, fecha_conversion, cliente_id)
             VALUES ('Sin enlace', :email, 'ads', 'US', 'Miami', 'Ingles', 'No especifica', '1985-05-05',
                     '2019-03-05', '2019-03-05', '2019-03-05', '2019-03-05', NULL)"
        )->execute([':email' => $email]);
        $this->alTerminar(static function () use ($email): void {
            Database::connection()->prepare('DELETE FROM usuarios_funnel WHERE email = :email')->execute([':email' => $email]);
        });

        $conUno = $this->get('page=funnel&desde=2019-03-01&hasta=2019-03-31')['cuerpo'];
        $sinNinguno = $this->get('page=funnel&' . self::AMPLIO)['cuerpo'];

        self::assertStringContainsString('Uno de los clientes del período no tiene un cliente enlazado en el sistema', $conUno);
        self::assertStringContainsString('el LTV se calcula solo con los enlazados', $conUno);
        self::assertStringContainsString('Uno de los clientes del período no tiene', $sinNinguno, 'el de 2019 tambien esta en todo el historial');
        self::assertStringContainsString('<td class="num"><strong>—</strong></td>', $conUno, 'sin clientes enlazados el LTV total no existe: guion, no un cero');
    }
}
