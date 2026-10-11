<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use PDO;

/**
 * Lo que deja `php database/recrear_con_datos_de_ejemplo.php` tiene que ser algo que la app podria
 * haber producido por su cuenta: aca se revisa contra las reglas de la app, no
 * contra cifras concretas. El seed es reproducible (semilla fija: ver
 * SeedReproducibleTest), asi que el resultado de estos tests no cambia de una
 * corrida a otra. Corre contra la base ya seedeada y no escribe nada. Si falla, ademas de arreglar el seed, volver a
 * correrlo deja la base limpia: la app permite cargar a mano un pago anterior
 * al alta del cliente, y ese dato tambien haria saltar el segundo test.
 */
final class DatosDeEjemploTest extends IntegracionTestCase
{
    /** @return list<mixed> los ids que devuelve la consulta, que son las filas que rompen la regla */
    private function ids(string $sql): array
    {
        return array_values(Database::connection()->query($sql)->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * La app emite la nota de credito al anular la boleta, por lo cobrado
     * hasta ese momento, y desde entonces los pagos de esa boleta quedan
     * congelados (409). El seed anulaba por separado dos pagos al azar, y a
     * veces caian en la boleta anulada: la nota devolvia un cobro que ya no
     * contaba, y los reportes lo descontaban dos veces.
     */
    public function testCadaNotaDeCreditoDevuelveLoQueLaBoletaTieneCobradoVigente(): void
    {
        $notas = $this->ids(
            'SELECT n.id
             FROM notas_credito n JOIN boletas_con_saldo b ON b.id = n.boleta_id
             WHERE ABS(n.monto - b.pagado) > 0.01
             ORDER BY n.id'
        );

        self::assertSame([], $notas, 'ids de notas de credito cuyo monto no es lo cobrado vigente de su boleta');
    }

    /**
     * Las boletas se emiten desde el alta del cliente, pero los anticipos
     * (pagos sin boleta) se fechaban hasta 90 dias atras sin mirar cuando se
     * habia dado de alta: el cliente aparecia pagando antes de existir.
     */
    public function testNingunPagoEstaFechadoAntesDelAltaDelCliente(): void
    {
        $pagos = $this->ids(
            'SELECT p.id
             FROM pagos p JOIN clientes c ON c.id = p.cliente_id
             WHERE p.fecha_pago < c.fecha_alta
             ORDER BY p.id'
        );

        self::assertSame([], $pagos, 'ids de pagos con fecha anterior al alta de su cliente');
    }

    /**
     * La base no deja crear a un menor, pero el seed tiene que cuidar la edad de
     * cada pais antes de llegar al INSERT: con la de 18 para todos, un cliente de
     * 19 en Tailandia (20) haria fallar todo el seed. Y como el catalogo se puede
     * corregir despues con un UPDATE, tampoco puede quedar nadie cargado por
     * debajo de la edad de su pais al correr el seed.
     */
    public function testNingunClienteTieneMenosEdadQueLaMayoriaDeSuPais(): void
    {
        $clientes = $this->ids(
            'SELECT c.id
             FROM clientes c JOIN paises p ON p.codigo = c.pais_codigo
             WHERE c.fecha_nacimiento > CURRENT_DATE - make_interval(years => p.mayoria_de_edad)
             ORDER BY c.id'
        );

        self::assertSame([], $clientes, 'ids de clientes que no llegan a la edad de mayoria de su pais');
    }
}
