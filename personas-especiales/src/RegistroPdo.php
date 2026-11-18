<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use DateTimeImmutable;
use PDO;

/**
 * Registro sobre la tabla `exclusion` de database/esquema.sql (PostgreSQL).
 *
 * El SQL usa solo sintaxis que PostgreSQL acepta igual que el resto de la base:
 * INSERT ... ON CONFLICT y parámetros con nombre.
 */
final class RegistroPdo implements RegistroExclusiones
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function activasDe(string $personaId): array
    {
        $sql = "SELECT id, persona_id, solicitante, motivo, prueba, estado, creada_en, cambiada_en
                  FROM exclusion
                 WHERE persona_id = :persona AND estado = 'activa'
              ORDER BY id";
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute(['persona' => $personaId]);

        $exclusiones = [];
        while ($fila = $consulta->fetch(PDO::FETCH_ASSOC)) {
            $exclusiones[] = Exclusion::desde(
                (string) $fila['id'],
                (string) $fila['persona_id'],
                (string) $fila['solicitante'],
                (string) $fila['motivo'],
                $fila['prueba'] === null ? null : (string) $fila['prueba'],
                (string) $fila['estado'],
                new DateTimeImmutable((string) $fila['creada_en']),
                new DateTimeImmutable((string) $fila['cambiada_en']),
            );
        }

        return $exclusiones;
    }

    public function guardar(Exclusion $exclusion): void
    {
        $sql = "INSERT INTO exclusion
                    (id, persona_id, solicitante, motivo, prueba, estado, creada_en, cambiada_en)
                VALUES (:id, :persona, :solicitante, :motivo, :prueba, :estado, :creada, :cambiada)
                ON CONFLICT (id) DO UPDATE SET
                    persona_id  = EXCLUDED.persona_id,
                    solicitante = EXCLUDED.solicitante,
                    motivo      = EXCLUDED.motivo,
                    prueba      = EXCLUDED.prueba,
                    estado      = EXCLUDED.estado,
                    creada_en   = EXCLUDED.creada_en,
                    cambiada_en = EXCLUDED.cambiada_en";
        $consulta = $this->pdo->prepare($sql);
        $consulta->execute([
            'id' => $exclusion->id(),
            'persona' => $exclusion->personaId(),
            'solicitante' => $exclusion->solicitante()->value,
            'motivo' => $exclusion->motivo(),
            'prueba' => $exclusion->prueba(),
            'estado' => $exclusion->estado()->value,
            'creada' => $exclusion->creadaEn()->format(DATE_ATOM),
            'cambiada' => $exclusion->cambiadaEn()->format(DATE_ATOM),
        ]);
    }
}
