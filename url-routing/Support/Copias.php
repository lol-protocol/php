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
 * It encrypts only when it is given recipients (age public keys): the copy then ends in «.age», and the server holds
 * nothing it could decrypt, because the private key lives elsewhere. Without recipients it does not encrypt (that is the
 * owner's decision). It never falls back to a plain copy when encrypting fails: the run fails and leaves nothing. It does
 * not take the copy off the server either: send() runs the command the owner gives it for that, one file at a time.
 *
 * Two checks that do not make a copy: verificar() really restores one (into a scratch database, or by opening it) and
 * counts the rows of every table, which is what a copy that «passed» when it was made does not prove; and vigilar() says
 * when the newest copy of a site is too old or there is none, which is what a cron that stopped running never says.
 */
final class Copias
{
    /** What a copy is called: site, date and extension. */
    private const NOMBRE = '/^(?<sitio>[a-z0-9_]+)-(?<fecha>\d{4}-\d{2}-\d{2})\.(?:dump|sqlite)(?:\.age)?$/';

    /** An age public key (X25519): «age1» and 58 characters of the bech32 alphabet. */
    private const DESTINATARIO = '/^age1[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{58}$/';

    /** The first line of every file age writes: what is checked on an encrypted copy, which cannot be opened without the private key. */
    private const CABECERA_AGE = 'age-encryption.org/v1';

    /**
     * @param list<string> $destinatarios age public keys to encrypt for; empty: the copies are not encrypted
     */
    public function __construct(private readonly string $destino, private readonly int $dias = 30, private readonly array $destinatarios = [])
    {
        if ($dias < 1 || $dias > 3650) {
            throw new \InvalidArgumentException('Los días de conservación son un número de 1 a 3650.');
        }
        foreach ($destinatarios as $destinatario) {
            if (preg_match(self::DESTINATARIO, $destinatario) !== 1) {
                throw new \InvalidArgumentException('Un destinatario de age es una clave pública «age1…» de 62 caracteres (age-keygen la imprime).');
            }
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
        $this->limpiarParciales($sitio);

        $viejoUmask = umask(0077);
        try {
            if (str_starts_with($dsn, 'sqlite:')) {
                $final = $this->destino . '/' . $sitio . '-' . date('Y-m-d') . '.sqlite';
                $copiar = fn(string $a) => $this->copiarSqlite(substr($dsn, strlen('sqlite:')), $a);
            } elseif (str_starts_with($dsn, 'pgsql:')) {
                $final = $this->destino . '/' . $sitio . '-' . date('Y-m-d') . '.dump';
                $copiar = fn(string $a) => $this->copiarPostgres($dsn, $usuario, $clave, $a);
            } else {
                throw new \RuntimeException("No sé copiar una base «{$dsn}»: solo sqlite: y pgsql:.");
            }

            if ($this->destinatarios === []) {
                $copiar($final);

                return $final;
            }

            // Encrypting: the plain copy is made under a name that ends in «.parcial» (never taken for a copy, and swept by
            // limpiarParciales() if the run is killed), encrypted, and only the «.age» takes the name of a copy.
            $plano = $final . '.sin-cifrar.parcial';
            try {
                $copiar($plano);
                $this->cifrar($plano, $final . '.age');
            } finally {
                @unlink($plano);
            }

            return $final . '.age';
        } finally {
            umask($viejoUmask);
        }
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
     * Says what is wrong with the copies of $sitios in the destination folder, by the date in the name: none at all, or
     * the newest older than $maxDias days, or empty. Nothing is read from the databases and nothing is changed, so it can
     * run anywhere the folder is seen (best of all, where the copies are sent to: a server that is down cannot warn).
     *
     * @param list<string> $sitios
     * @return list<string> one sentence per problem; empty when every site has a recent copy
     */
    public function vigilar(array $sitios, int $maxDias, ?\DateTimeImmutable $hoy = null): array
    {
        if ($maxDias < 1 || $maxDias > 3650) {
            throw new \InvalidArgumentException('Los días de antigüedad son un número de 1 a 3650.');
        }
        if (!is_dir($this->destino)) {
            return ["No existe la carpeta de copias {$this->destino}."];
        }
        $hoy ??= new \DateTimeImmutable('today');
        $problemas = [];
        foreach ($sitios as $sitio) {
            $ultima = null;
            foreach (scandir($this->destino) ?: [] as $nombre) {
                if (preg_match(self::NOMBRE, $nombre, $m) === 1 && $m['sitio'] === $sitio && ($ultima === null || $m['fecha'] > $ultima['fecha'])) {
                    $ultima = ['fecha' => $m['fecha'], 'nombre' => $nombre];
                }
            }
            if ($ultima === null) {
                $problemas[] = "Copias de {$sitio}: no hay ninguna en {$this->destino}.";
                continue;
            }
            $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $ultima['fecha']);
            $dias = $fecha === false ? PHP_INT_MAX : max(0, (int)$fecha->diff($hoy)->format('%r%a'));
            if ($dias > $maxDias) {
                $problemas[] = "Copias de {$sitio}: la última es del {$ultima['fecha']}, de hace {$dias} días (el límite es {$maxDias}).";
            } elseif ((int)@filesize($this->destino . '/' . $ultima['nombre']) === 0) {
                $problemas[] = "Copias de {$sitio}: la última ({$ultima['nombre']}) está vacía.";
            }
        }

        return $problemas;
    }

    /**
     * Restores a copy for real and counts the rows of every table: a SQLite copy is opened (integrity_check) and a
     * PostgreSQL dump is restored into a scratch database that is dropped afterwards (createdb, pg_restore, psql and
     * dropdb on the PATH, and a role that may create databases; the connection comes from $entornoPg, the PG* variables).
     * An encrypted copy needs $identidad, the file with the age private key, which is never kept on the server: so this is
     * meant to be run where the key is. The decrypted copy lives in a temporary file that is deleted.
     *
     * @param array<string, string> $entornoPg PGHOST, PGPORT, PGUSER, PGPASSWORD... for the scratch database
     * @return array<string, int> rows per table
     * @throws \RuntimeException when the copy cannot be restored or has no tables at all
     */
    public function verificar(string $archivo, ?string $identidad = null, array $entornoPg = []): array
    {
        if (!is_file($archivo) || preg_match(self::NOMBRE, basename($archivo), $m) !== 1) {
            throw new \RuntimeException("«{$archivo}» no es una copia (se llama sitio-AAAA-MM-DD.dump|sqlite[.age]).");
        }
        $plano = $archivo;
        $temporal = null;
        $viejoUmask = umask(0077);
        try {
            if (str_ends_with($archivo, '.age')) {
                if ($identidad === null || !is_file($identidad)) {
                    throw new \RuntimeException('La copia está cifrada: hace falta --identidad con el archivo de la clave privada de age (la que NO está en el servidor).');
                }
                $temporal = (string)tempnam(sys_get_temp_dir(), 'copia-verificar-');
                [$codigo, $error] = $this->correr(['age', '-d', '-i', $identidad, '-o', $temporal, $archivo], ['PATH' => (string)getenv('PATH')]);
                if ($codigo !== 0) {
                    throw new \RuntimeException('age no pudo descifrar la copia (¿es la clave correcta?)' . ($error !== '' ? ": {$error}" : '.'));
                }
                $plano = $temporal;
            }
            $tablas = str_contains(basename($archivo), '.sqlite') ? $this->contarSqlite($plano) : $this->contarPostgres($plano, $entornoPg);
        } finally {
            umask($viejoUmask);
            if ($temporal !== null) {
                @unlink($temporal);
            }
        }
        if ($tablas === []) {
            throw new \RuntimeException('La copia se abre pero no tiene ninguna tabla.');
        }

        return $tablas;
    }

    /** @return array<string, int> */
    private function contarSqlite(string $ruta): array
    {
        try {
            $pdo = new \PDO('sqlite:' . $ruta, null, null, [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]);
            $integridad = $pdo->query('PRAGMA integrity_check');
            if ($integridad === false || $integridad->fetchColumn() !== 'ok') {
                throw new \RuntimeException('La copia de SQLite no pasa integrity_check.');
            }
            $nombres = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name");
            $tablas = [];
            foreach ($nombres === false ? [] : $nombres->fetchAll(\PDO::FETCH_COLUMN) as $nombre) {
                $cuenta = $pdo->query('SELECT COUNT(*) FROM "' . str_replace('"', '""', (string)$nombre) . '"');
                $tablas[(string)$nombre] = $cuenta === false ? 0 : (int)$cuenta->fetchColumn();
            }

            return $tablas;
        } catch (\PDOException $e) {
            throw new \RuntimeException('No se pudo abrir la copia de SQLite: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, string> $entornoPg
     * @return array<string, int>
     */
    private function contarPostgres(string $volcado, array $entornoPg): array
    {
        $entorno = ['PATH' => (string)getenv('PATH')] + $entornoPg;
        $base = 'copias_verificar_' . bin2hex(random_bytes(4));
        [$codigo, $error] = $this->correr(['createdb', $base], $entorno);
        if ($codigo !== 0) {
            throw new \RuntimeException('No se pudo crear la base temporal (createdb' . ($error !== '' ? ": {$error}" : '') . '): hace falta un rol que pueda crear bases.');
        }
        try {
            [$codigo, $error] = $this->correr(['pg_restore', '--no-owner', '--exit-on-error', '-d', $base, $volcado], $entorno);
            if ($codigo !== 0) {
                throw new \RuntimeException('pg_restore no pudo restaurar la copia' . ($error !== '' ? ": {$error}" : '.'));
            }
            $consulta = "SELECT table_name || '|' || (xpath('/row/c/text()', query_to_xml(format('SELECT count(*) AS c FROM %I.%I', table_schema, table_name), false, true, '')))[1]::text"
                . " FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY 1";
            [$codigo, $error, $salida] = $this->correr(['psql', '-X', '-A', '-t', '-d', $base, '-c', $consulta], $entorno);
            if ($codigo !== 0) {
                throw new \RuntimeException('psql no pudo contar las filas' . ($error !== '' ? ": {$error}" : '.'));
            }
        } finally {
            $this->correr(['dropdb', '--if-exists', $base], $entorno);
        }
        $tablas = [];
        foreach (explode("\n", trim($salida)) as $linea) {
            if (str_contains($linea, '|')) {
                [$nombre, $cuenta] = explode('|', $linea, 2);
                $tablas[$nombre] = (int)$cuenta;
            }
        }

        return $tablas;
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

    /** Deletes what a run that was killed left half done for this site («…parcial»): a copy that never took its name. */
    private function limpiarParciales(string $sitio): void
    {
        foreach (scandir($this->destino) ?: [] as $nombre) {
            if (preg_match('/^' . preg_quote($sitio, '/') . '-\d{4}-\d{2}-\d{2}\..*parcial$/', $nombre) === 1) {
                @unlink($this->destino . '/' . $nombre);
            }
        }
    }

    /**
     * Encrypts a verified plain copy for the recipients and gives the result its final name, or fails with nothing left.
     *
     * @throws \RuntimeException when age is missing or fails, or what it wrote is not an age file
     */
    private function cifrar(string $plano, string $final): void
    {
        $parcial = $final . '.parcial';
        @unlink($parcial);
        $comando = ['age'];
        foreach ($this->destinatarios as $destinatario) {
            array_push($comando, '-r', $destinatario);
        }
        array_push($comando, '-o', $parcial, $plano);

        try {
            [$codigo, $error] = $this->correr($comando, ['PATH' => (string)getenv('PATH')]);
            if ($codigo !== 0) {
                throw new \RuntimeException('age falló (código ' . $codigo . ')' . ($error !== '' ? ": {$error}" : '.') . ' ¿Está instalado? (apt install age)');
            }
            $cabecera = is_file($parcial) ? (string)file_get_contents($parcial, false, null, 0, strlen(self::CABECERA_AGE)) : '';
            if ($cabecera !== self::CABECERA_AGE) {
                throw new \RuntimeException('age no dejó un archivo cifrado.');
            }
            $this->cerrar($parcial, $final);
        } catch (\RuntimeException $e) {
            @unlink($parcial);
            throw $e;
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
     * @return array{0: int, 1: string, 2: string} exit code, what the command wrote to stderr and to stdout
     */
    private function correr(array $comando, array $entorno): array
    {
        $proceso = @proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $entorno);
        if (!is_resource($proceso)) {
            throw new \RuntimeException("No se pudo ejecutar {$comando[0]} (¿está instalado y en el PATH?).");
        }
        $salida = (string)stream_get_contents($pipes[1]);
        $error = trim((string)stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($proceso), $error, $salida];
    }
}
