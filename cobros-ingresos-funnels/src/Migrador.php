<?php

declare(strict_types=1);

namespace App;

use PDO;
use RuntimeException;

/**
 * Migraciones versionadas: cada archivo NNN_descripcion.sql de
 * database/migraciones es un cambio de esquema, y la tabla
 * migraciones_aplicadas recuerda cuales ya corrieron en esta base.
 *
 * Antes la unica forma de tener el esquema era database/seed.php, que
 * empezaba borrando todas las tablas: no habia manera de llevar un cambio
 * (un CHECK nuevo, la tabla notas_credito) a una base con datos reales sin
 * perderlos.
 */
final class Migrador
{
    /** La migracion que equivale al viejo database/schema.sql (ver marcarComoAplicada()). */
    public const INICIAL = '001_esquema_inicial';

    /**
     * Clave del advisory lock que serializa migradores simultaneos (dos
     * despliegues a la vez): el segundo espera al primero y despues encuentra
     * todo aplicado, en vez de chocar a mitad de un CREATE TABLE.
     */
    private const CANDADO = 20240601;

    public function __construct(
        private readonly string $directorio = __DIR__ . '/../database/migraciones'
    ) {
    }

    /** @return list<string> las versiones que hay en el directorio, en orden */
    public function disponibles(): array
    {
        $archivos = glob($this->directorio . '/*.sql') ?: [];
        $versiones = array_map(static fn (string $archivo): string => basename($archivo, '.sql'), $archivos);
        sort($versiones, SORT_STRING);

        return $versiones;
    }

    /** @return list<string> las versiones ya aplicadas en esta base, en orden */
    public function aplicadas(): array
    {
        $this->crearTablaDeControl();
        $versiones = Database::connection()
            ->query('SELECT version FROM migraciones_aplicadas ORDER BY version')
            ->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_map('strval', $versiones));
    }

    /** @return list<string> */
    public function pendientes(): array
    {
        return array_values(array_diff($this->disponibles(), $this->aplicadas()));
    }

    /**
     * Aplica las pendientes en orden. Cada una corre en su propia
     * transaccion junto con su registro en la tabla de control (Postgres
     * admite DDL dentro de una transaccion): si una falla, esa queda sin
     * aplicar y sin registrar, las anteriores quedan firmes y la excepcion
     * sube con el nombre del archivo.
     *
     * @return list<string> las que aplico
     */
    public function aplicar(): array
    {
        $db = Database::connection();
        $db->query('SELECT pg_advisory_lock(' . self::CANDADO . ')');
        try {
            $aplicadas = [];
            foreach ($this->pendientes() as $version) {
                $sql = (string) file_get_contents($this->directorio . '/' . $version . '.sql');
                try {
                    Database::transaccion(function () use ($db, $sql, $version): void {
                        $db->exec($sql);
                        $this->registrar($version);
                    });
                } catch (\Throwable $e) {
                    throw new RuntimeException("Fallo la migracion {$version}: " . $e->getMessage(), 0, $e);
                }
                $aplicadas[] = $version;
            }

            return $aplicadas;
        } finally {
            $db->query('SELECT pg_advisory_unlock(' . self::CANDADO . ')');
        }
    }

    /**
     * Registra una migracion como aplicada sin ejecutarla. Existe para una
     * sola situacion: una base creada antes de las migraciones con el viejo
     * database/schema.sql, que ya tiene todo lo de la 001 y fallaria si se la
     * corriera de nuevo ("la tabla ya existe").
     */
    public function marcarComoAplicada(string $version): void
    {
        if (!in_array($version, $this->disponibles(), true)) {
            throw new RuntimeException("No existe la migracion {$version}.");
        }
        $this->crearTablaDeControl();
        $this->registrar($version);
    }

    private function registrar(string $version): void
    {
        Database::connection()
            ->prepare('INSERT INTO migraciones_aplicadas (version) VALUES (:version) ON CONFLICT (version) DO NOTHING')
            ->execute([':version' => $version]);
    }

    private function crearTablaDeControl(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS migraciones_aplicadas (
                version TEXT PRIMARY KEY,
                aplicada_en TIMESTAMP NOT NULL DEFAULT now()
            )'
        );
    }
}
