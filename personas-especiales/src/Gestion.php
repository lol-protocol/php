<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

/**
 * El camino de una solicitud: aplica la transición, la guarda y deja el evento de auditoría.
 * Con un PDO, las tres cosas van en una transacción: o quedan las tres o ninguna. Sin él (pruebas
 * con registros en memoria) no hay atomicidad, y la clase lo dice en vez de fingirla.
 */
final class Gestion
{
    public function __construct(
        private RegistroExclusiones $registro,
        private Auditoria $auditoria,
        private ?PDO $pdo = null,
    ) {
    }

    public function solicitar(
        string $id,
        string $personaId,
        Solicitante $solicitante,
        Motivo $motivo,
        string $actor,
        DateTimeImmutable $ahora,
    ): Exclusion {
        return $this->atomico(function () use ($id, $personaId, $solicitante, $motivo, $actor, $ahora): Exclusion {
            // insertar() rechaza un id repetido, también cuando dos solicitudes llegan a la vez.
            $nueva = Exclusion::solicitar($id, $personaId, $solicitante, $motivo, $ahora);
            // El evento se arma (y se valida) antes de escribir: con un actor inválido no queda una
            // solicitud sin su evento, tampoco cuando no hay PDO y la operación no es atómica.
            $evento = new Evento($nueva->id(), TipoEvento::Solicitada, $actor, $ahora);
            $this->registro->insertar($nueva);
            $this->auditoria->registrar($evento);

            return $nueva;
        });
    }

    public function verificar(string $id, string $prueba, string $actor, DateTimeImmutable $ahora): Exclusion
    {
        return $this->transicion($id, TipoEvento::Verificada, $actor, $ahora,
            static fn (Exclusion $e): Exclusion => $e->verificar($prueba, $ahora));
    }

    public function rechazar(string $id, string $razon, string $actor, DateTimeImmutable $ahora): Exclusion
    {
        return $this->transicion($id, TipoEvento::Rechazada, $actor, $ahora,
            static fn (Exclusion $e): Exclusion => $e->rechazar($razon, $ahora));
    }

    public function revocar(string $id, string $actor, DateTimeImmutable $ahora): Exclusion
    {
        return $this->transicion($id, TipoEvento::Revocada, $actor, $ahora,
            static fn (Exclusion $e): Exclusion => $e->revocar($ahora));
    }

    /** @param callable(Exclusion): Exclusion $aplicar */
    private function transicion(string $id, TipoEvento $tipo, string $actor, DateTimeImmutable $ahora, callable $aplicar): Exclusion
    {
        return $this->atomico(function () use ($id, $tipo, $actor, $ahora, $aplicar): Exclusion {
            $actual = $this->registro->porId($id)
                ?? throw new DomainException('No existe esa solicitud.');
            $nueva = $aplicar($actual);
            // Antes de escribir: un actor inválido no deja un cambio de estado sin su evento.
            $evento = new Evento($nueva->id(), $tipo, $actor, $ahora);
            // Falla si otro cambió la solicitud entre leerla y guardarla, en vez de pisarle el cambio.
            $this->registro->actualizar($nueva, $actual->estado());
            $this->auditoria->registrar($evento);

            return $nueva;
        });
    }

    /**
     * @template T
     * @param callable(): T $accion
     * @return T
     */
    private function atomico(callable $accion): mixed
    {
        $pdo = $this->pdo;
        if ($pdo === null) {
            return $accion();
        }
        // Si quien llama ya abrió una transacción, esta se suma a la suya: no abre ni cierra otra.
        $yaEnTransaccion = $pdo->inTransaction();
        if ($yaEnTransaccion) {
            return $accion();
        }
        $pdo->beginTransaction();
        try {
            $resultado = $accion();
            $pdo->commit();

            return $resultado;
        } catch (Throwable $e) {
            // Un commit que falla puede dejar la transacción abierta o ya cerrada: se mira de nuevo.
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
