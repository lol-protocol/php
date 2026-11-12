<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Copies of a site's database for bin/copias.php (docs/DESPLIEGUE.md, section 11): one file per site and per day,
 * named «{site}-YYYY-MM-DD.dump» (PostgreSQL, pg_dump -Fc) or «{site}-YYYY-MM-DD.sqlite» (SQLite), and the old ones
 * deleted by the date in their name.
 *
 * A copy is written to a «.parcial» file, checked (pg_restore can read the dump; the SQLite copy opens and passes
 * integrity_check and has tables) and only then renamed, so a copy that failed half way is never taken for a good
 * one. The files are made readable by their owner alone: a copy holds what the site hides from visitors (people who
 * may be alive, the e-mail of whoever asked for a retiro).
 *
 * It does not encrypt (that is still undecided) and does not take the copy off the server: send() runs the command
 * the owner gives it for that, one file at a time.
 */
final class Copias
{
    /** What a copy is called: site, date and extension. */
    private const NOMBRE = '/^(?<sitio>[a-z0-9_]+)-(?<fecha>\d{4}-\d{2}-\d{2})\.(?:dump|sqlite)$/';

    public function __construct(private readonly string $destino, private readonly int $dias = 30)
    {
        if ($dias < 1 || $dias > 3650) {
            throw new \InvalidArgumentException('Los días de conservación son un número de 1 a 3650.');
        }
    }

    /**
     * Makes today's copy of one site's database and returns its path.
     *
     * @throws \RuntimeException when the copy could not be made or does not check out (nothing is left behind)
     */
    public function respaldar(string $sitio, string $dsn, string $usuario = '', string $clave = ''): string
    {
        if (preg_match('/^[a-z0-9_]+$/', $sitio) !== 1) {
            throw new \InvalidArgumentException("«{$sitio}» no es un nombre de sitio.");
        }
        $this->prepararDestino();

        $viejoUmask = umask(0077);
        try {
            if (str_starts_with($dsn, 'sqlite:')) {
                $final = $this->destino . '/' . $sitio . '-' . date('Y-m-d') . '.sqlite';
                $this->copiarSqlite(substr($dsn, strlen('sqlite:')), $final);
            } elseif (str_starts_with($dsn, 'pgsql:')) {
                $final = $this->destino . '/' . $sitio . '-' . date('Y-m-d') . '.dump';
                $this->copiarPostgres($dsn, $usuario, $clave, $final);
            } else {
                throw new \RuntimeException("No sé copiar una base «{$dsn}»: solo sqlite: y pgsql:.");
            }
        } finally {
            umask($viejoUmask);
        }

        return $final;
    }

    /**
     * Deletes the copies of $sitio dated more than $dias days ago (by the date in the name, so what the file
     * system says about modification times does not matter). Today's copy and any file that is not a copy are left
     * alone. Call it only after respaldar() succeeded, or a failing backup would still age out the good ones.
     *
     * @return list<string> the names that were deleted
     */
    public function rotar(string $sitio): array
    {
        $limite = date('Y-m-d', strtotime('-' . $this->dias . ' days') ?: 0);
        $borrados = [];
        foreach (scandir($this->destino) ?: [] as $nombre) {
            if (preg_match(self::NOMBRE, $nombre, $m) !== 1 || $m['sitio'] !== $sitio || $m['fecha'] >= $limite) {
                continue;
            }
            if (@unlink($this->destino . '/' . $nombre)) {
                $borrados[] = $nombre;
            }
        }

        return $borrados;
    }

    /**
     * Runs the owner's command to take a copy off the server. «{archivo}» in $comando is replaced by the path,
     * already quoted for the shell. The command is the owner's own (an option or the .env), never user input.
     *
     * @throws \RuntimeException when the command fails
     */
    public function enviar(string $comando, string $archivo): void
    {
        if (!str_contains($comando, '{archivo}')) {
            throw new \InvalidArgumentException('El comando para enviar tiene que llevar «{archivo}».');
        }
        $linea = str_replace('{archivo}', escapeshellarg($archivo), $comando);
        $proceso = proc_open($linea, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($proceso)) {
            throw new \RuntimeException('No se pudo ejecutar el comando para enviar la copia.');
        }
        stream_get_contents($pipes[1]);
        $error = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($proceso) !== 0) {
            throw new \RuntimeException('El comando para enviar la copia falló' . ($error !== '' ? ": {$error}" : '.'));
        }
    }

    private function prepararDestino(): void
    {
        if (!is_dir($this->destino) && !@mkdir($this->destino, 0700, true) && !is_dir($this->destino)) {
            throw new \RuntimeException("No se pudo crear la carpeta de copias {$this->destino}.");
        }
        if (!is_writable($this->destino)) {
            throw new \RuntimeException("No se puede escribir en la carpeta de copias {$this->destino}.");
        }
    }

    private function copiarSqlite(string $origen, string $final): void
    {
        if ($origen === '' || $origen === ':memory:' || !is_file($origen)) {
            throw new \RuntimeException("No existe la base SQLite {$origen}.");
        }
        $parcial = $final . '.parcial';
        @unlink($parcial);

        try {
            // VACUUM INTO is SQLite's own consistent copy (it reads through the WAL), unlike cp.
            $pdo = new \PDO('sqlite:' . $origen, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $pdo->exec('VACUUM INTO ' . $pdo->quote($parcial));
            $pdo = null;

            $copia = new \PDO('sqlite:' . $parcial, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $integridad = $copia->query('PRAGMA integrity_check');
            $tablas = $copia->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'");
            if ($integridad === false || $tablas === false) {
                throw new \RuntimeException('No se pudo comprobar la copia de SQLite.');
            }
            $integridad = $integridad->fetchColumn();
            $tablas = (int)$tablas->fetchColumn();
            $copia = null;
            if ($integridad !== 'ok' || $tablas === 0) {
                throw new \RuntimeException('La copia de SQLite no pasó la comprobación (integrity_check o sin tablas).');
            }
            $this->cerrar($parcial, $final);
        } catch (\PDOException $e) {
            @unlink($parcial);
            throw new \RuntimeException('No se pudo copiar la base SQLite: ' . $e->getMessage(), 0, $e);
        } catch (\RuntimeException $e) {
            @unlink($parcial);
            throw $e;
        }
    }

    private function copiarPostgres(string $dsn, string $usuario, string $clave, string $final): void
    {
        $partes = [];
        foreach (explode(';', substr($dsn, strlen('pgsql:'))) as $par) {
            if (str_contains($par, '=')) {
                [$k, $v] = explode('=', $par, 2);
                $partes[trim($k)] = trim($v);
            }
        }
        if (($partes['dbname'] ?? '') === '') {
            throw new \RuntimeException('El DSN de PostgreSQL no dice dbname.');
        }

        $parcial = $final . '.parcial';
        @unlink($parcial);
        $comando = ['pg_dump', '-Fc', '--file=' . $parcial];
        foreach (['host' => '-h', 'port' => '-p'] as $clavePartes => $opcion) {
            if (($partes[$clavePartes] ?? '') !== '') {
                array_push($comando, $opcion, $partes[$clavePartes]);
            }
        }
        if ($usuario !== '') {
            array_push($comando, '-U', $usuario);
        }
        $comando[] = $partes['dbname'];

        // The password goes by environment, not on the command line (where `ps` shows it to everyone).
        $entorno = ['PATH' => (string)getenv('PATH')];
        if ($clave !== '') {
            $entorno['PGPASSWORD'] = $clave;
        }

        try {
            [$codigo, $error] = $this->correr($comando, $entorno);
            if ($codigo !== 0) {
                throw new \RuntimeException('pg_dump falló (código ' . $codigo . ')' . ($error !== '' ? ": {$error}" : '.'));
            }
            if (!is_file($parcial) || filesize($parcial) === 0) {
                throw new \RuntimeException('pg_dump no dejó ningún archivo.');
            }
            [$codigo, $error] = $this->correr(['pg_restore', '--list', $parcial], $entorno);
            if ($codigo !== 0) {
                throw new \RuntimeException('pg_restore no puede leer la copia' . ($error !== '' ? ": {$error}" : '.'));
            }
            $this->cerrar($parcial, $final);
        } catch (\RuntimeException $e) {
            @unlink($parcial);
            throw $e;
        }
    }

    /** Renames the checked copy into place and leaves it readable by its owner only. */
    private function cerrar(string $parcial, string $final): void
    {
        @chmod($parcial, 0600);
        if (!rename($parcial, $final)) {
            throw new \RuntimeException("No se pudo dejar la copia en {$final}.");
        }
    }

    /**
     * @param list<string> $comando
     * @param array<string, string> $entorno
     * @return array{0: int, 1: string} exit code and what the command wrote to stderr
     */
    private function correr(array $comando, array $entorno): array
    {
        $proceso = @proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $entorno);
        if (!is_resource($proceso)) {
            throw new \RuntimeException("No se pudo ejecutar {$comando[0]} (¿está instalado y en el PATH?).");
        }
        stream_get_contents($pipes[1]);
        $error = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proceso), $error];
    }
}
