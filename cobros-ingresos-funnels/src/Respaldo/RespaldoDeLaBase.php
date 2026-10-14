<?php

declare(strict_types=1);

namespace App\Respaldo;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * Un respaldo de la base con pg_dump, listo para correr por cron (database/respaldar.php).
 *
 * La base tiene boletas, pagos y datos de clientes: perderla no se arregla despues. Esto
 * hace un volcado en el formato propio de Postgres (-Fc: comprimido, se restaura con
 * pg_restore, y permite restaurar solo una tabla), y se cuida de lo que sale mal en
 * silencio:
 *
 * - Se escribe a un archivo ".parcial" y recien cuando pg_dump termino bien y pg_restore
 *   --list lo puede leer (y trae datos de tablas) se lo renombra a su nombre definitivo.
 *   Un fallo no deja un archivo a medio hacer que parezca un respaldo.
 * - Los respaldos viejos se borran SOLO despues de un respaldo nuevo bueno: si pg_dump
 *   falla durante semanas, no se va comiendo los anteriores.
 * - Solo se borran archivos de esta misma base con este mismo nombre (nombre-AAAAMMDD-HHMMSS.dump,
 *   hora UTC); nunca un enlace, nunca un archivo de otra base, nunca algo que no se llame asi.
 * - La clave viaja en PGPASSWORD y no en la linea de comandos (que cualquier usuario del
 *   servidor puede ver con ps), y no aparece en ningun mensaje.
 * - Carpeta 0700 y archivos 0600: el respaldo tiene todos los datos.
 * - Un respaldo que pesa mucho menos que el anterior pide atencion: puede ser una base
 *   vacia o rota que esta "respaldando" bien lo que no tiene.
 *
 * Esto no sustituye una copia en OTRA maquina o disco: un respaldo en el mismo servidor no
 * sirve si el servidor se pierde. Ver el README.
 */
final class RespaldoDeLaBase
{
    public const DIAS_POR_DEFECTO = 14;

    /** Un respaldo que pesa menos de esta parte del anterior pide atencion. */
    public const FRACCION_MINIMA = 0.5;

    /** Un ".parcial" mas viejo que esto es de una corrida que murio: se borra. */
    private const SEGUNDOS_PARA_PARCIAL_ABANDONADO = 86_400;

    /**
     * @param array{host: string, port: string, nombre: string, usuario: string, clave: string} $conexion
     * @throws InvalidArgumentException si los dias a conservar no son al menos 1
     */
    public function __construct(
        private readonly array $conexion,
        private readonly string $carpeta,
        private readonly int $diasAConservar = self::DIAS_POR_DEFECTO,
        private readonly string $pgDump = 'pg_dump',
        private readonly string $pgRestore = 'pg_restore',
    ) {
        if ($diasAConservar < 1) {
            throw new InvalidArgumentException('Hay que conservar los respaldos al menos 1 día.');
        }
        if (preg_match('/^[A-Za-z0-9_]+$/', $conexion['nombre']) !== 1) {
            throw new InvalidArgumentException('El nombre de la base solo puede llevar letras, números y guion bajo para usarse en el nombre del archivo.');
        }
    }

    /**
     * @return array{archivo: string, bytes: int, eliminados: list<string>, advertencias: list<string>}
     * @throws RespaldoFallido
     */
    public function respaldar(?DateTimeImmutable $ahora = null): array
    {
        $ahora = ($ahora ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('UTC'));

        $this->prepararCarpeta();
        $anterior = $this->ultimoRespaldo();

        $definitivo = $this->carpeta . '/' . self::nombreDelArchivo($this->conexion['nombre'], $ahora);
        $parcial = $definitivo . '.parcial';
        if (file_exists($definitivo) || file_exists($parcial)) {
            throw new RespaldoFallido('Ya existe un respaldo de este mismo segundo (' . basename($definitivo) . '): no se pisa.');
        }

        $umaskAnterior = umask(0077);
        try {
            $this->volcar($parcial);
            $this->comprobar($parcial);
        } catch (RespaldoFallido $e) {
            @unlink($parcial);
            throw $e;
        } finally {
            umask($umaskAnterior);
        }

        if (!@rename($parcial, $definitivo)) {
            @unlink($parcial);
            throw new RespaldoFallido('No se pudo dejar el respaldo en su lugar definitivo: ' . $definitivo);
        }
        @chmod($definitivo, 0600);
        $bytes = (int) filesize($definitivo);

        $advertencias = [];
        if ($anterior !== null && $bytes < self::FRACCION_MINIMA * $anterior['bytes']) {
            $advertencias[] = sprintf(
                'Este respaldo pesa %s y el anterior (%s) pesaba %s: menos de la mitad. Revisá que la base no esté vacía o dañada.',
                self::tamanio($bytes),
                $anterior['nombre'],
                self::tamanio($anterior['bytes'])
            );
        }

        return [
            'archivo' => $definitivo,
            'bytes' => $bytes,
            'eliminados' => $this->borrarLosViejos($ahora, basename($definitivo)),
            'advertencias' => $advertencias,
        ];
    }

    /** El nombre de un respaldo: base-AAAAMMDD-HHMMSS.dump, en UTC. */
    public static function nombreDelArchivo(string $base, DateTimeImmutable $cuando): string
    {
        return $base . '-' . $cuando->setTimezone(new DateTimeZone('UTC'))->format('Ymd-His') . '.dump';
    }

    public static function tamanio(int $bytes): string
    {
        return match (true) {
            $bytes >= 1_048_576 => sprintf('%.1f MB', $bytes / 1_048_576),
            $bytes >= 1024 => sprintf('%.1f KB', $bytes / 1024),
            default => "{$bytes} bytes",
        };
    }

    /** @throws RespaldoFallido */
    private function prepararCarpeta(): void
    {
        if (is_link($this->carpeta) || (file_exists($this->carpeta) && !is_dir($this->carpeta))) {
            throw new RespaldoFallido("{$this->carpeta} existe y no es una carpeta (o es un enlace): elegí otra con --carpeta.");
        }
        if (!is_dir($this->carpeta) && !@mkdir($this->carpeta, 0700, true) && !is_dir($this->carpeta)) {
            throw new RespaldoFallido("No se pudo crear la carpeta {$this->carpeta}.");
        }
        if (!is_writable($this->carpeta)) {
            throw new RespaldoFallido("No se puede escribir en la carpeta {$this->carpeta}.");
        }
    }

    /** @throws RespaldoFallido */
    private function volcar(string $destino): void
    {
        [$codigo, , $errores] = $this->ejecutar([
            $this->pgDump,
            '--format=custom',
            '--no-owner',
            '--no-privileges',
            '--host=' . $this->conexion['host'],
            '--port=' . $this->conexion['port'],
            '--username=' . $this->conexion['usuario'],
            '--dbname=' . $this->conexion['nombre'],
            '--file=' . $destino,
        ], false);

        if ($codigo === 127 || $codigo === null) {
            throw new RespaldoFallido("No se pudo ejecutar {$this->pgDump}: ¿está instalado el cliente de PostgreSQL? (la variable PG_DUMP puede apuntar a otro lugar).");
        }
        if ($codigo !== 0) {
            throw new RespaldoFallido("pg_dump terminó con el código {$codigo}: " . $this->resumen($errores));
        }
    }

    /** @throws RespaldoFallido */
    private function comprobar(string $archivo): void
    {
        clearstatcache(true, $archivo);
        if (!is_file($archivo) || filesize($archivo) === 0) {
            throw new RespaldoFallido('pg_dump no dejó ningún archivo, o lo dejó vacío.');
        }

        [$codigo, $listado, $errores] = $this->ejecutar([$this->pgRestore, '--list', $archivo], true);
        if ($codigo === 127 || $codigo === null) {
            throw new RespaldoFallido("No se pudo ejecutar {$this->pgRestore}: sin él no se puede comprobar el respaldo.");
        }
        if ($codigo !== 0) {
            throw new RespaldoFallido('El respaldo se generó pero pg_restore no puede leerlo: ' . $this->resumen($errores));
        }
        if (!str_contains($listado, ' TABLE DATA ')) {
            throw new RespaldoFallido('El respaldo no trae los datos de ninguna tabla: la base está vacía o el volcado quedó incompleto.');
        }
    }

    /**
     * Corre un programa sin pasar por el shell. La clave va en el entorno del proceso hijo.
     *
     * @param list<string> $comando
     * @return array{0: ?int, 1: string, 2: string} codigo de salida (null si no arranco), salida, errores
     */
    private function ejecutar(array $comando, bool $capturarSalida): array
    {
        $proceso = @proc_open(
            $comando,
            [
                0 => ['file', '/dev/null', 'r'],
                1 => $capturarSalida ? ['pipe', 'w'] : ['file', '/dev/null', 'w'],
                2 => ['pipe', 'w'],
            ],
            $tuberias,
            null,
            array_merge(getenv(), ['PGPASSWORD' => $this->conexion['clave'], 'PGCONNECT_TIMEOUT' => '15'])
        );
        if ($proceso === false) {
            return [null, '', ''];
        }

        $salida = $capturarSalida ? (string) stream_get_contents($tuberias[1]) : '';
        $errores = (string) stream_get_contents($tuberias[2]);
        foreach ($tuberias as $tuberia) {
            fclose($tuberia);
        }

        return [proc_close($proceso), $salida, $errores];
    }

    /** Lo que dijo el programa, sin la clave por si la repitiera, en una linea y sin pasarse de largo. */
    private function resumen(string $errores): string
    {
        $clave = $this->conexion['clave'];
        $limpio = $clave === '' ? $errores : str_replace($clave, '********', $errores);
        $limpio = trim((string) preg_replace('/\s+/', ' ', $limpio));

        return $limpio === '' ? '(sin detalle)' : mb_strimwidth($limpio, 0, 400, '…');
    }

    /** @return list<array{nombre: string, momento: DateTimeImmutable, bytes: int}> los respaldos de esta base, del mas nuevo al mas viejo */
    private function respaldosExistentes(): array
    {
        $patron = '/^' . preg_quote($this->conexion['nombre'], '/') . '-(\d{8}-\d{6})\.dump$/';
        $encontrados = [];
        foreach (scandir($this->carpeta) ?: [] as $nombre) {
            $ruta = $this->carpeta . '/' . $nombre;
            if (preg_match($patron, $nombre, $m) !== 1 || is_link($ruta) || !is_file($ruta)) {
                continue;
            }
            $momento = DateTimeImmutable::createFromFormat('!Ymd-His', $m[1], new DateTimeZone('UTC'));
            if ($momento === false) {
                continue;
            }
            $encontrados[] = ['nombre' => $nombre, 'momento' => $momento, 'bytes' => (int) filesize($ruta)];
        }
        usort($encontrados, static fn (array $a, array $b): int => $b['momento'] <=> $a['momento']);

        return $encontrados;
    }

    /** @return array{nombre: string, bytes: int}|null */
    private function ultimoRespaldo(): ?array
    {
        $primero = $this->respaldosExistentes()[0] ?? null;

        return $primero === null ? null : ['nombre' => $primero['nombre'], 'bytes' => $primero['bytes']];
    }

    /**
     * Borra los respaldos de esta base de mas de $diasAConservar dias (menos $conservar, el que
     * acaba de salir) y los ".parcial" abandonados. Solo se llama despues de un respaldo bueno.
     *
     * @return list<string> los nombres borrados
     */
    private function borrarLosViejos(DateTimeImmutable $ahora, string $conservar): array
    {
        $limite = $ahora->sub(new DateInterval('P' . $this->diasAConservar . 'D'));
        $borrados = [];

        foreach ($this->respaldosExistentes() as $respaldo) {
            if ($respaldo['nombre'] !== $conservar && $respaldo['momento'] < $limite && @unlink($this->carpeta . '/' . $respaldo['nombre'])) {
                $borrados[] = $respaldo['nombre'];
            }
        }

        $parciales = '/^' . preg_quote($this->conexion['nombre'], '/') . '-\d{8}-\d{6}\.dump\.parcial$/';
        foreach (scandir($this->carpeta) ?: [] as $nombre) {
            $ruta = $this->carpeta . '/' . $nombre;
            if (preg_match($parciales, $nombre) === 1 && is_file($ruta) && !is_link($ruta) && time() - (int) filemtime($ruta) > self::SEGUNDOS_PARA_PARCIAL_ABANDONADO && @unlink($ruta)) {
                $borrados[] = $nombre;
            }
        }

        return $borrados;
    }
}
