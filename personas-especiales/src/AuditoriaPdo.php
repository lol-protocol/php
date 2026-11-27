<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use DateTimeImmutable;
use PDO;

/** Auditoría sobre la tabla `exclusion_evento`, que la base protege de UPDATE, DELETE y TRUNCATE. */
final class AuditoriaPdo implements Auditoria
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function registrar(Evento $evento): void
    {
        $consulta = $this->pdo->prepare(
            'INSERT INTO exclusion_evento (exclusion_id, tipo, actor, ocurrido_en)
             VALUES (:exclusion, :tipo, :actor, :ocurrido)'
        );
        $consulta->execute([
            'exclusion' => $evento->exclusionId,
            'tipo' => $evento->tipo->value,
            'actor' => $evento->actor,
            'ocurrido' => $evento->ocurridoEn->format(DATE_ATOM),
        ]);
    }

    public function eventosDe(string $exclusionId): array
    {
        $consulta = $this->pdo->prepare(
            'SELECT exclusion_id, tipo, actor, ocurrido_en FROM exclusion_evento
              WHERE exclusion_id = :exclusion ORDER BY id'
        );
        $consulta->execute(['exclusion' => $exclusionId]);

        $eventos = [];
        while (($fila = Fila::deFetch($consulta->fetch(PDO::FETCH_ASSOC))) !== null) {
            $eventos[] = new Evento(
                Fila::texto($fila, 'exclusion_id'),
                TipoEvento::from(Fila::texto($fila, 'tipo')),
                Fila::texto($fila, 'actor'),
                new DateTimeImmutable(Fila::texto($fila, 'ocurrido_en')),
            );
        }

        return $eventos;
    }
}
