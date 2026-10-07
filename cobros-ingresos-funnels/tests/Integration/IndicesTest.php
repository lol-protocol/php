<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use PDO;

/**
 * La migracion 006 crea dos indices para las consultas que la app hace sobre
 * tablas que crecen: la ficha de un cliente busca su recorrido de funnel, y el
 * listado de clientes (y el selector de los formularios) ordena por nombre, id y
 * se queda con una pagina. Con los datos del seed una lectura completa tarda lo
 * mismo, asi que ningun otro test notaria que un indice se perdio o que quedo
 * armado para otra consulta (columnas en otro orden): aca se le pregunta a
 * Postgres si cada consulta puede usarlo.
 *
 * Con enable_seqscan apagado el planificador usa cualquier indice que sirva, sin
 * importar lo chica que sea la tabla; si ninguno sirve, cae a la lectura completa
 * y el test falla. SET LOCAL dura hasta el rollback con el que termina cada test.
 *
 * Las consultas son las de FunnelRepository::viajeDeCliente y
 * ClienteRepository::buscar / paraSelector: si esas cambian, este test cambia
 * con ellas.
 */
final class IndicesTest extends IntegracionTestCase
{
    public function testLaFichaDelClienteBuscaSuRecorridoDeFunnelPorIndice(): void
    {
        $plan = $this->plan('SELECT * FROM usuarios_funnel WHERE cliente_id = 1');

        self::assertStringContainsString('idx_funnel_cliente', $plan);
        self::assertStringNotContainsString('Seq Scan', $plan);
    }

    public function testElListadoDeClientesLeeLaPaginaDelIndiceSinOrdenarTodo(): void
    {
        $plan = $this->plan(
            'SELECT c.id, c.nombre, c.email, c.segmento, c.fecha_alta, p.nombre AS pais_nombre
             FROM clientes c
             JOIN paises p ON p.codigo = c.pais_codigo
             ORDER BY c.nombre, c.id
             LIMIT 25 OFFSET 0'
        );

        self::assertStringContainsString('idx_clientes_nombre_id', $plan);
        self::assertStringNotContainsString('Sort', $plan, 'ordenar todos los clientes para devolver una pagina es lo que el indice evita');
    }

    public function testElSelectorDeLosFormulariosTomaSusPrimerosClientesDelIndice(): void
    {
        $plan = $this->plan('SELECT id FROM clientes ORDER BY nombre, id LIMIT 500');

        self::assertStringContainsString('idx_clientes_nombre_id', $plan);
        self::assertStringNotContainsString('Sort', $plan);
    }

    public function testLosDosIndicesQuedaronValidosEnLaBase(): void
    {
        $validos = Database::connection()->query(
            "SELECT c.relname
             FROM pg_index i
             JOIN pg_class c ON c.oid = i.indexrelid
             WHERE i.indisvalid AND c.relname IN ('idx_funnel_cliente', 'idx_clientes_nombre_id')
             ORDER BY c.relname"
        )->fetchAll(PDO::FETCH_COLUMN);

        self::assertSame(['idx_clientes_nombre_id', 'idx_funnel_cliente'], $validos);
    }

    private function plan(string $sql): string
    {
        $db = Database::connection();
        $db->exec('SET LOCAL enable_seqscan = off');

        return implode("\n", $db->query('EXPLAIN ' . $sql)->fetchAll(PDO::FETCH_COLUMN));
    }
}
