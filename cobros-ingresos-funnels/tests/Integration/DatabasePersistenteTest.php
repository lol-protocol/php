<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Database;
use PDO;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * En la web la conexion a Postgres se reutiliza entre peticiones (conexion
 * persistente): abrirla cuesta ~20 ms, mas que todo lo demas que hace una
 * pantalla liviana. Lo que se podia temer de eso es que una peticion que murio a
 * mitad de una transaccion le deje su estado a la siguiente. Estos tests fijan el
 * supuesto que lo evita: PDO deshace sola la transaccion pendiente cuando se
 * libera la conexion. "Terminar la peticion" es soltar el objeto PDO.
 *
 * No usa IntegracionTestCase a proposito: maneja sus propias conexiones.
 */
final class DatabasePersistenteTest extends TestCase
{
    private function pid(PDO $db): int
    {
        return (int) $db->query('SELECT pg_backend_pid()')->fetchColumn();
    }

    public function testUnaConexionPersistenteSeReutilizaEntrePeticiones(): void
    {
        $primera = Database::conectar(persistente: true);
        $pid = $this->pid($primera);
        unset($primera);

        $segunda = Database::conectar(persistente: true);

        self::assertSame($pid, $this->pid($segunda), 'la segunda peticion tiene que reutilizar la conexion, no abrir otra');
    }

    public function testUnaTransaccionAbiertaNoSeFiltraALaSiguientePeticion(): void
    {
        $primera = Database::conectar(persistente: true);
        $primera->beginTransaction();
        $primera->exec('CREATE TEMP TABLE fuga_entre_peticiones (x integer)');
        unset($primera);

        $segunda = Database::conectar(persistente: true);
        $existe = (bool) $segunda->query("SELECT to_regclass('pg_temp.fuga_entre_peticiones') IS NOT NULL")->fetchColumn();

        self::assertFalse($existe, 'lo que la peticion anterior dejo sin commitear se tiene que haber deshecho');
        self::assertFalse($segunda->inTransaction());
    }

    public function testUnaTransaccionAbortadaPorUnErrorNoRompeLaSiguientePeticion(): void
    {
        $primera = Database::conectar(persistente: true);
        $primera->beginTransaction();
        try {
            $primera->exec('SELECT 1 / 0');
        } catch (Throwable) {
            // Postgres deja la transaccion abortada: cualquier consulta posterior falla hasta el ROLLBACK.
        }
        unset($primera);

        $segunda = Database::conectar(persistente: true);

        self::assertSame(1, (int) $segunda->query('SELECT 1')->fetchColumn());
    }

    /**
     * Si Postgres se reinicia (o corta las conexiones inactivas), la conexion que
     * un proceso de PHP tenia guardada esta muerta. Sin reintento, la primera
     * peticion de cada proceso despues del reinicio fallaba con "SSL connection
     * has been closed unexpectedly" -un 500- y recien la siguiente se recuperaba.
     * Ahora conectar() la reemplaza solo, dentro de la misma peticion.
     */
    public function testUnaConexionPersistenteMuertaSeReemplazaSolaSinFallarLaPeticion(): void
    {
        $guardada = Database::conectar(persistente: true);
        $pidMuerto = $this->pid($guardada);
        unset($guardada);

        // Hace de reinicio de Postgres: mata el proceso de servidor de esa conexion.
        $otra = Database::conectar();
        $otra->query("SELECT pg_terminate_backend({$pidMuerto})");
        for ($i = 0; $i < 50; $i++) {   // la baja es asincronica: espera a que el proceso desaparezca
            if (!(bool) $otra->query("SELECT EXISTS (SELECT 1 FROM pg_stat_activity WHERE pid = {$pidMuerto})")->fetchColumn()) {
                break;
            }
            usleep(20000);
        }

        $nueva = Database::conectar(persistente: true);

        self::assertSame(1, (int) $nueva->query('SELECT 1')->fetchColumn(), 'la peticion tiene que poder consultar');
        self::assertNotSame($pidMuerto, $this->pid($nueva), 'tiene que ser una conexion nueva, no la muerta');
    }

    /**
     * Los tests que hacen de "segundo proceso" (dos anulaciones simultaneas, dos
     * envios del mismo formulario) piden una conexion con conectar() y necesitan
     * que sea propia: si fuera persistente recibirian la misma que ya tienen.
     */
    public function testConectarSinPedirloNoEsPersistenteYDaUnaConexionPropia(): void
    {
        $a = Database::conectar();
        $b = Database::conectar();

        self::assertNotSame($this->pid($a), $this->pid($b));
    }
}
