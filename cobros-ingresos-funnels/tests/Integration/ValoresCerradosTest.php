<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Etiquetas;
use App\Repositories\ClienteRepository;
use PDOException;

/**
 * Metodo de pago, genero y segmento tienen una lista de valores validos que la
 * app valida en el servidor, y la migracion 004 los restringe tambien en la
 * base. Las listas viven en dos lugares (PHP y SQL): este test las compara, asi
 * que agregar un valor en uno solo falla aca y no en produccion.
 */
final class ValoresCerradosTest extends IntegracionTestCase
{
    /** columna => [tabla, restriccion, valores de la app] */
    private static function restricciones(): array
    {
        return [
            'pagos.metodo' => ['pagos', 'pagos_metodo_valido', array_keys(Etiquetas::metodosPago())],
            'clientes.genero' => ['clientes', 'clientes_genero_valido', ClienteRepository::GENEROS],
            'clientes.segmento' => ['clientes', 'clientes_segmento_valido', ClienteRepository::SEGMENTOS],
        ];
    }

    /** Los valores que permite la restriccion, tal como los lee Postgres. */
    private function valoresPermitidos(string $tabla, string $restriccion): ?array
    {
        $stmt = Database::connection()->prepare(
            'SELECT pg_get_constraintdef(c.oid)
             FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid
             WHERE t.relname = :tabla AND c.conname = :restriccion'
        );
        $stmt->execute([':tabla' => $tabla, ':restriccion' => $restriccion]);
        $definicion = $stmt->fetchColumn();
        if ($definicion === false) {
            return null;
        }
        preg_match_all("/'([^']*)'::text/", (string) $definicion, $coincidencias);

        return $coincidencias[1];
    }

    public function testLaBaseRestringeCadaListaALosMismosValoresQueLaApp(): void
    {
        foreach (self::restricciones() as $columna => [$tabla, $restriccion, $valoresDeLaApp]) {
            $enLaBase = $this->valoresPermitidos($tabla, $restriccion);

            self::assertNotNull($enLaBase, "{$columna}: falta la restriccion {$restriccion} (migracion 004)");
            $a = $enLaBase;
            $b = $valoresDeLaApp;
            sort($a);
            sort($b);
            self::assertSame($b, $a, "{$columna}: la base y la app tienen que permitir exactamente los mismos valores");
        }
    }

    /** Un valor fuera de la lista lo rechaza la base aunque la app no se entere (un script, una carga directa, un bug nuevo). */
    public function testLaBaseRechazaUnValorFueraDeLaLista(): void
    {
        $db = Database::connection();
        $cliente = $db->query('SELECT id, pais_codigo, moneda_codigo FROM clientes c JOIN paises p ON p.codigo = c.pais_codigo ORDER BY c.id LIMIT 1')->fetch();
        self::assertNotFalse($cliente, 'este test asume que el seed dejo al menos un cliente');

        $intentos = [
            'metodo' => fn () => $db->prepare(
                "INSERT INTO pagos (cliente_id, monto, moneda_codigo, fecha_pago, metodo) VALUES (:c, 1, :m, CURRENT_DATE, 'bitcoin')"
            )->execute([':c' => $cliente['id'], ':m' => $cliente['moneda_codigo']]),
            'genero' => fn () => $db->exec(
                "INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais_codigo, ciudad, idioma, genero, fecha_nacimiento)
                 VALUES ('Alien', 'alien-" . uniqid() . "@example.com', 'general', CURRENT_DATE, '{$cliente['pais_codigo']}', 'X', 'Espanol', 'Alienigena', DATE '1990-01-01')"
            ),
            'segmento' => fn () => $db->exec(
                "INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais_codigo, ciudad, idioma, genero, fecha_nacimiento)
                 VALUES ('Vip', 'vip-" . uniqid() . "@example.com', 'vip', CURRENT_DATE, '{$cliente['pais_codigo']}', 'X', 'Espanol', 'No especifica', DATE '1990-01-01')"
            ),
        ];

        foreach ($intentos as $columna => $insertar) {
            try {
                Database::transaccion($insertar);
                self::fail("la base aceptó un {$columna} fuera de la lista");
            } catch (PDOException $e) {
                self::assertSame('23514', $e->getCode(), "{$columna}: tiene que ser una violacion de CHECK");
            }
        }
    }
}
