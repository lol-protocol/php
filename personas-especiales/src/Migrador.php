<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Aplica en orden los archivos database/migraciones/NNN_nombre.sql que faltan, cada uno en su
 * transacción, y los anota en `schema_migraciones`. Correrlo otra vez no hace nada.
 * Una migración ya aplicada no se edita: se agrega otra con el número siguiente.
 *
 * Toma un candado asesor de PostgreSQL mientras trabaja: si dos instancias migran a la vez (dos
 * servidores que arrancan juntos), la segunda espera a que termine la primera y luego no encuentra
 * nada pendiente. El candado es de la sesión, así que se libera solo si la conexión se cae.
 */
final class Migrador
{
    /** Clave del candado asesor. Cualquier número fijo sirve; otro módulo debe usar otra. */
    public const CLAVE_CANDADO = 7314201;

    public function __construct(private PDO $pdo, private string $carpeta)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /** @return list<string> los nombres que se aplicaron ahora (vacío si no había pendientes) */
    public function aplicar(): array
    {
        $this->pdo->query('SELECT pg_advisory_lock(' . self::CLAVE_CANDADO . ')');
        try {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS schema_migraciones (
                    nombre      TEXT PRIMARY KEY,
                    aplicada_en TIMESTAMPTZ NOT NULL DEFAULT now()
                )'
            );
            $consulta = $this->pdo->query('SELECT nombre FROM schema_migraciones');
            $hechas = $consulta === false ? [] : $consulta->fetchAll(PDO::FETCH_COLUMN);

            $nuevas = [];
            foreach ($this->archivos() as $nombre => $ruta) {
                if (in_array($nombre, $hechas, true)) {
                    continue;
                }
                $sql = file_get_contents($ruta);
                if ($sql === false) {
                    throw new InvalidArgumentException("No se pudo leer {$ruta}.");
                }
                $this->pdo->beginTransaction();
                try {
                    $this->pdo->exec($sql);
                    $insertar = $this->pdo->prepare('INSERT INTO schema_migraciones (nombre) VALUES (:nombre)');
                    $insertar->execute(['nombre' => $nombre]);
                    $this->pdo->commit();
                } catch (Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    throw $e;
                }
                $nuevas[] = $nombre;
            }

            return $nuevas;
        } finally {
            $this->pdo->query('SELECT pg_advisory_unlock(' . self::CLAVE_CANDADO . ')');
        }
    }

    /**
     * Los archivos de la carpeta, por nombre y en orden. Un nombre que no sea NNN_algo.sql es un error:
     * una migración con un nombre raro se saltaría el orden sin avisar.
     *
     * @return array<string, string>
     */
    private function archivos(): array
    {
        $rutas = glob(rtrim($this->carpeta, '/') . '/*.sql');
        if ($rutas === false) {
            return [];
        }
        sort($rutas, SORT_STRING);
        $archivos = [];
        foreach ($rutas as $ruta) {
            $nombre = basename($ruta);
            if (preg_match('/^\d{3}_[a-z0-9_]+\.sql$/', $nombre) !== 1) {
                throw new InvalidArgumentException("Nombre de migración inválido: {$nombre} (debe ser NNN_nombre.sql).");
            }
            $archivos[$nombre] = $ruta;
        }

        return $archivos;
    }
}
