<?php

declare(strict_types=1);

namespace PersonasEspeciales;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Una solicitud para que una persona no sea conectada ni mostrada en la
 * plataforma. Inmutable: cada transición devuelve una instancia nueva.
 *
 * Las entradas inválidas lanzan InvalidArgumentException; una transición que
 * no corresponde al estado actual lanza DomainException.
 */
final class Exclusion
{
    public const PRUEBA_MAX = 1000;

    private function __construct(
        private string $id,
        private string $personaId,
        private Solicitante $solicitante,
        private Motivo $motivo,
        private ?string $prueba,
        private Estado $estado,
        private DateTimeImmutable $creadaEn,
        private DateTimeImmutable $cambiadaEn,
    ) {
    }

    public static function solicitar(
        string $id,
        string $personaId,
        Solicitante $solicitante,
        Motivo $motivo,
        DateTimeImmutable $ahora,
    ): self {
        $id = self::texto($id, 'id', 64);
        $personaId = self::texto($personaId, 'persona', 64);

        return new self($id, $personaId, $solicitante, $motivo, null, Estado::Pendiente, $ahora, $ahora);
    }

    /** Reconstruye una solicitud guardada. Valida lo mismo que solicitar() y el estado. */
    public static function desde(
        string $id,
        string $personaId,
        string $solicitante,
        string $motivo,
        ?string $prueba,
        string $estado,
        DateTimeImmutable $creadaEn,
        DateTimeImmutable $cambiadaEn,
    ): self {
        $sol = Solicitante::tryFrom($solicitante)
            ?? throw new InvalidArgumentException("Solicitante desconocido: {$solicitante}.");
        $mot = Motivo::tryFrom($motivo)
            ?? throw new InvalidArgumentException('Motivo desconocido: no está en la lista cerrada.');
        $est = Estado::tryFrom($estado)
            ?? throw new InvalidArgumentException("Estado desconocido: {$estado}.");
        if ($cambiadaEn < $creadaEn) {
            throw new InvalidArgumentException('cambiadaEn es anterior a creadaEn.');
        }
        $prueba = $prueba === null ? null : self::texto($prueba, 'prueba', self::PRUEBA_MAX, true);
        if ($est === Estado::Activa && $prueba === null) {
            throw new InvalidArgumentException('Una exclusión activa necesita la prueba de quien pide.');
        }

        return new self(
            self::texto($id, 'id', 64),
            self::texto($personaId, 'persona', 64),
            $sol,
            $mot,
            $prueba,
            $est,
            $creadaEn,
            $cambiadaEn,
        );
    }

    /** Pendiente -> activa. Sin prueba de quien pide, no se activa. */
    public function verificar(string $prueba, DateTimeImmutable $ahora): self
    {
        $this->exigir(Estado::Pendiente, 'verificar');
        $prueba = self::texto($prueba, 'prueba', self::PRUEBA_MAX, true);

        return $this->con(Estado::Activa, $prueba, $ahora);
    }

    /** Pendiente -> rechazada. La razón queda en la prueba para que se pueda revisar. */
    public function rechazar(string $razon, DateTimeImmutable $ahora): self
    {
        $this->exigir(Estado::Pendiente, 'rechazar');
        $razon = self::texto($razon, 'razón', self::PRUEBA_MAX, true);

        return $this->con(Estado::Rechazada, $this->prueba, $ahora, $razon);
    }

    /** Activa -> revocada: la persona o su familia pide que se deje de aplicar. */
    public function revocar(DateTimeImmutable $ahora): self
    {
        $this->exigir(Estado::Activa, 'revocar');

        return $this->con(Estado::Revocada, $this->prueba, $ahora);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function personaId(): string
    {
        return $this->personaId;
    }

    public function solicitante(): Solicitante
    {
        return $this->solicitante;
    }

    public function motivo(): Motivo
    {
        return $this->motivo;
    }

    public function prueba(): ?string
    {
        return $this->prueba;
    }

    public function estado(): Estado
    {
        return $this->estado;
    }

    /** Solo una exclusión activa le quita visibilidad a la persona. */
    public function estaActiva(): bool
    {
        return $this->estado === Estado::Activa;
    }

    public function creadaEn(): DateTimeImmutable
    {
        return $this->creadaEn;
    }

    public function cambiadaEn(): DateTimeImmutable
    {
        return $this->cambiadaEn;
    }

    private function exigir(Estado $esperado, string $accion): void
    {
        if ($this->estado !== $esperado) {
            throw new DomainException(
                "No se puede {$accion} una exclusión en estado {$this->estado->value}."
            );
        }
    }

    private function con(Estado $estado, ?string $prueba, DateTimeImmutable $ahora, ?string $razon = null): self
    {
        if ($ahora < $this->cambiadaEn) {
            throw new InvalidArgumentException('La transición es anterior al último cambio.');
        }
        // Al rechazar, la razón ocupa el lugar de la prueba: no hay prueba que mostrar.
        $prueba = $razon ?? $prueba;

        return new self(
            $this->id,
            $this->personaId,
            $this->solicitante,
            $this->motivo,
            $prueba,
            $estado,
            $this->creadaEn,
            $ahora,
        );
    }

    /** El id y la persona son identificadores (sin saltos de línea); la prueba y la razón admiten texto de varias líneas. */
    private static function texto(string $valor, string $campo, int $max, bool $permiteSaltos = false): string
    {
        return Texto::limpio($valor, $campo, $max, $permiteSaltos);
    }
}
