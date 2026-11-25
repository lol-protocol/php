<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/** Auditoría sin base de datos, para pruebas. */
final class AuditoriaEnMemoria implements Auditoria
{
    /** @var list<Evento> */
    private array $eventos = [];

    public function registrar(Evento $evento): void
    {
        $this->eventos[] = $evento;
    }

    public function eventosDe(string $exclusionId): array
    {
        return array_values(array_filter(
            $this->eventos,
            static fn (Evento $e): bool => $e->exclusionId === $exclusionId,
        ));
    }
}
