<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use App\Repositories\ClienteRepository;

/** Corre contra la base configurada por las env vars DB_*. Lo que crea se deshace con la transaccion de cada test. */
final class ClienteRepositoryTest extends IntegracionTestCase
{
    /** Clientes de relleno cuyos nombres ordenan despues de cualquiera del seed. */
    private function crearClientesAlFinal(int $cuantos): void
    {
        Database::connection()->prepare(
            "INSERT INTO clientes (nombre, email, segmento, fecha_alta, pais_codigo, ciudad, idioma, genero, fecha_nacimiento)
             SELECT 'ZZZZ Relleno ' || lpad(g::text, 4, '0'), 'zzzz-relleno-' || g || '@example.com', 'general',
                    CURRENT_DATE, 'AR', 'Rosario', 'Espanol', 'No especifica', DATE '1990-01-01'
             FROM generate_series(1, :cuantos) g"
        )->execute([':cuantos' => $cuantos]);
    }

    private function totalDeClientes(): int
    {
        return (int) Database::connection()->query('SELECT COUNT(*) FROM clientes')->fetchColumn();
    }

    /**
     * Regresion: el desplegable de "Nueva boleta" y "Nuevo pago" se llenaba con
     * un LIMIT 500 sin decir nada, y con mas clientes que eso los demas no se
     * podian elegir ni se sabia por que.
     */
    public function testElSelectorTraeElLimiteYAvisaQueHayMas(): void
    {
        $repo = new ClienteRepository();
        self::assertSame($this->totalDeClientes() > ClienteRepository::LIMITE_SELECTOR, $repo->superaElLimiteDelSelector());

        $this->crearClientesAlFinal(ClienteRepository::LIMITE_SELECTOR + 1);
        $lista = $repo->paraSelector();
        $nombres = array_column($lista, 'nombre');

        self::assertTrue($repo->superaElLimiteDelSelector());
        self::assertCount(ClienteRepository::LIMITE_SELECTOR, $lista);
        self::assertNotContains('ZZZZ Relleno 0501', $nombres, 'el ultimo por nombre queda afuera');
    }

    /** Con pocos clientes entran todos y no hay nada que avisar. */
    public function testConHastaElLimiteDeClientesEntranTodos(): void
    {
        $repo = new ClienteRepository();
        $faltan = ClienteRepository::LIMITE_SELECTOR - $this->totalDeClientes();
        if ($faltan < 0) {
            self::markTestSkipped('La base ya tiene mas clientes que el limite del selector.');
        }
        $this->crearClientesAlFinal($faltan);

        self::assertSame(ClienteRepository::LIMITE_SELECTOR, $this->totalDeClientes());
        self::assertFalse($repo->superaElLimiteDelSelector(), 'justo en el limite todavia entran todos');
        self::assertCount(ClienteRepository::LIMITE_SELECTOR, $repo->paraSelector());
    }

    /** El cliente de ?cliente_id= tiene que poder verse seleccionado aunque el limite lo deje afuera. */
    public function testElClienteElegidoSeIncluyeAunqueQuedeFueraDelLimite(): void
    {
        $repo = new ClienteRepository();
        $this->crearClientesAlFinal(ClienteRepository::LIMITE_SELECTOR + 1);
        $fuera = (int) Database::connection()->query("SELECT id FROM clientes WHERE nombre = 'ZZZZ Relleno 0501'")->fetchColumn();

        self::assertNotContains($fuera, array_map('intval', array_column($repo->paraSelector(), 'id')));

        $conElegido = $repo->paraSelector($fuera);
        self::assertCount(ClienteRepository::LIMITE_SELECTOR + 1, $conElegido);
        self::assertSame($fuera, (int) $conElegido[array_key_last($conElegido)]['id'], 'sigue ordenado por nombre');
    }

    /** Elegir un cliente que ya estaba entre los primeros no lo duplica. */
    public function testElClienteElegidoQueYaEstabaNoSeDuplica(): void
    {
        $repo = new ClienteRepository();
        $primero = (int) $repo->paraSelector()[0]['id'];

        self::assertSame(
            array_column($repo->paraSelector(), 'id'),
            array_column($repo->paraSelector($primero), 'id')
        );
    }
}
