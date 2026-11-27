<?php

declare(strict_types=1);

namespace PersonasEspeciales;

/** Historial de lo que se hizo con cada solicitud. Solo se agrega; no hay cómo editar ni borrar. */
interface Auditoria
{
    public function registrar(Evento $evento): void;

    /**
     * Los eventos de una solicitud, del más antiguo al más reciente.
     *
     * @return list<Evento>
     */
    public function eventosDe(string $exclusionId): array;
}
