<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use PHPUnit\Framework\TestCase;

/**
 * Base de los tests de integracion: cada test corre dentro de una
 * transaccion que se deshace al terminar, pase o falle. Ningun test ve lo que
 * escribio otro, y no hace falta borrar a mano lo que se creo (incluidas las
 * filas que el codigo bajo prueba escribe por su cuenta, como la auditoria o
 * una nota de credito).
 *
 * Lo que el codigo bajo prueba haga con Database::transaccion() queda anidado
 * con un SAVEPOINT. Un test que necesite una segunda conexion para hacer de
 * otro proceso no puede usar esta base: esa conexion no veria nada de lo que
 * el test escribio sin commitear.
 */
abstract class IntegracionTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Database::connection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $db = Database::connection();
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        parent::tearDown();
    }
}
