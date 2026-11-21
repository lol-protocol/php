<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use DateTimeImmutable;
use DomainException;
use PDO;
use PDOException;
use UnexpectedValueException;

/**
 * Registro sobre la tabla `exclusion` (PostgreSQL; ver database/migraciones/).
 *
 * El SQL es estándar con parámetros; solo el código de error 23505 (unique_violation) es de PostgreSQL.
 */
final class RegistroPdo implements RegistroExclusiones
{
    private const COLUMNAS = 'id, persona_id, solicitante, motivo, prueba, estado, creada_en, cambiada_en';

    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function activasDe(string $personaId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT ' . self::COLUMNAS . " FROM exclusion
              WHERE persona_id = :persona AND estado = 'activa' ORDER BY id"
        );
        $consulta->execute(['persona' => $personaId]);

        $exclusiones = [];
        while (($fila = Fila::deFetch($consulta->fetch(PDO::FETCH_ASSOC))) !== null) {
            $exclusiones[] = $this->hidratar($fila);
        }

        return $exclusiones;
    }

    public function porId(string $id): ?Exclusion
    {
        // Dentro de una transacción se bloquea la fila: dos revisores que actúan a la vez sobre la
        // misma solicitud se turnan, y el segundo ve el estado que dejó el primero.
        $bloqueo = $this->pdo->inTransaction() ? ' FOR UPDATE' : '';
        $consulta = $this->pdo->prepare('SELECT ' . self::COLUMNAS . ' FROM exclusion WHERE id = :id' . $bloqueo);
        $consulta->execute(['id' => $id]);
        $fila = Fila::deFetch($consulta->fetch(PDO::FETCH_ASSOC));

        return $fila === null ? null : $this->hidratar($fila);
    }

    public function insertar(Exclusion $exclusion): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO exclusion
                (id, persona_id, solicitante, motivo, prueba, estado, creada_en, cambiada_en)
             VALUES (:id, :persona, :solicitante, :motivo, :prueba, :estado, :creada, :cambiada)'
        );
        try {
            $consulta->execute([
                'id' => $exclusion->id(),
                'persona' => $exclusion->personaId(),
                'solicitante' => $exclusion->solicitante()->value,
                'motivo' => $exclusion->motivo()->value,
                'prueba' => $exclusion->prueba(),
                'estado' => $exclusion->estado()->value,
                'creada' => $exclusion->creadaEn()->format(DATE_ATOM),
                'cambiada' => $exclusion->cambiadaEn()->format(DATE_ATOM),
            ]);
        } catch (PDOException $e) {
            // 23505 = unique_violation: otro (o este mismo) ya agregó una solicitud con ese id.
            if ($e->getCode() === '23505') {
                throw new DomainException('Ya existe una solicitud con ese id.', 0, $e);
            }
            throw $e;
        }
    }

    public function actualizar(Exclusion $exclusion, Estado $estadoEsperado): void
    {
        // Solo las columnas que una transición cambia, y solo si sigue en el estado que se leyó. El
        // WHERE hace la comprobación y el cambio en un solo paso: no hay hueco entre leer y escribir.
        $consulta = $this->pdo->prepare(
            'UPDATE exclusion
                SET prueba = :prueba, estado = :estado, cambiada_en = :cambiada
              WHERE id = :id AND estado = :esperado'
        );
        $consulta->execute([
            'prueba' => $exclusion->prueba(),
            'estado' => $exclusion->estado()->value,
            'cambiada' => $exclusion->cambiadaEn()->format(DATE_ATOM),
            'id' => $exclusion->id(),
            'esperado' => $estadoEsperado->value,
        ]);
        if ($consulta->rowCount() !== 1) {
            throw new DomainException('La solicitud no existe o cambió de estado mientras se trabajaba en ella.');
        }
    }

    /** @param array<array-key, mixed> $fila */
    private function hidratar(array $fila): Exclusion
    {
        return Exclusion::desde(
            Fila::texto($fila, 'id'),
            Fila::texto($fila, 'persona_id'),
            Fila::texto($fila, 'solicitante'),
            Fila::texto($fila, 'motivo'),
            Fila::textoONulo($fila, 'prueba'),
            Fila::texto($fila, 'estado'),
            new DateTimeImmutable(Fila::texto($fila, 'creada_en')),
            new DateTimeImmutable(Fila::texto($fila, 'cambiada_en')),
        );
    }
}
